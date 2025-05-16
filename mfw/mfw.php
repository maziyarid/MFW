<?php
/**
 * Plugin Name: Maziyar Fetcher Writer (MFW)
 * Plugin URI: https://github.com/maziyarid/Maziyar-Fetcher-Writer-MFW-
 * Description: Advanced AI Content Writer & Fetcher
 * Version: 1.0.0
 * Requires at least: 5.8
 * Requires PHP: 8.1
 * Author: Maziyar
 * Author URI: https://github.com/maziyarid
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: mfw
 * Domain Path: /languages
 *
 * @package MFW
 */
<?php
/**
 * Load the main plugin file
 */
require_once plugin_dir_path(__FILE__) . 'modern-framework.php';
// Prevent direct access
if (!defined('ABSPATH')) {
    exit('Direct access not allowed.');
}

// Time constants
define('MFW_HOUR_IN_SECONDS', 3600);
define('MFW_DAY_IN_SECONDS', 86400);
define('MFW_WEEK_IN_SECONDS', 604800);
define('MFW_MONTH_IN_SECONDS', 2592000);

// API Service constants
define('MFW_AI_OPENAI', 'openai');
define('MFW_AI_GEMINI', 'gemini');
define('MFW_AI_DEEPSEEK', 'deepseek');
define('MFW_AI_DEFAULT', MFW_AI_OPENAI);

// Content types
define('MFW_CONTENT_TYPE_ARTICLE', 'article');
define('MFW_CONTENT_TYPE_PRODUCT', 'product');
define('MFW_CONTENT_TYPE_NEWS', 'news');
define('MFW_CONTENT_TYPE_BLOG', 'blog');

// Database tables
define('MFW_TABLE_CONTENT', 'mfw_content');
define('MFW_TABLE_LOGS', 'mfw_logs');
define('MFW_TABLE_ANALYTICS', 'mfw_analytics');
define('MFW_TABLE_QUEUE', 'mfw_queue');
define('MFW_TABLE_CACHE', 'mfw_cache');

/**
 * Trait: Singleton
 * Implements singleton pattern for classes
 */
trait MFW_Singleton {
    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {}
    private function __clone() {}
    public function __wakeup() {
        throw new \Exception("Cannot unserialize singleton");
    }
}

/**
 * Trait: Configurable
 * Implements configuration management
 */
trait MFW_Configurable {
    protected $config = [];

    public function set_config($key, $value) {
        $this->config[$key] = $value;
        return $this;
    }

    public function get_config($key, $default = null) {
        return $this->config[$key] ?? $default;
    }

    public function has_config($key) {
        return isset($this->config[$key]);
    }
}

/**
 * Trait: Loggable
 * Implements logging functionality
 */
trait MFW_Loggable {
    protected function log($message, $level = 'info', $context = []) {
        $timestamp = current_time('mysql');
        $log_entry = sprintf(
            "[%s] [%s] %s %s\n",
            $timestamp,
            strtoupper($level),
            $message,
            !empty($context) ? json_encode($context) : ''
        );

        $log_file = MFW_LOG_DIR . '/' . date('Y-m-d') . '.log';
        error_log($log_entry, 3, $log_file);
    }

    protected function log_error($message, $context = []) {
        $this->log($message, 'error', $context);
    }

    protected function log_warning($message, $context = []) {
        $this->log($message, 'warning', $context);
    }

    protected function log_info($message, $context = []) {
        $this->log($message, 'info', $context);
    }

    protected function log_debug($message, $context = []) {
        if (WP_DEBUG) {
            $this->log($message, 'debug', $context);
        }
    }
}

/**
 * Trait: Cacheable 
 * Implements caching functionality
 */
trait MFW_Cacheable {
    protected function cache_get($key, $group = '') {
        return wp_cache_get($key, 'mfw' . ($group ? '_' . $group : ''));
    }

    protected function cache_set($key, $value, $group = '', $expiration = 0) {
        return wp_cache_set(
            $key,
            $value,
            'mfw' . ($group ? '_' . $group : ''),
            $expiration
        );
    }

    protected function cache_delete($key, $group = '') {
        return wp_cache_delete($key, 'mfw' . ($group ? '_' . $group : ''));
    }

    protected function cache_flush($group = '') {
        return wp_cache_flush();
    }
}

/**
 * Abstract class: Base
 * Base class for all plugin classes
 */
abstract class MFW_Base {
    use MFW_Loggable;
    use MFW_Configurable;
    use MFW_Cacheable;

    protected $plugin;
    protected $version;
    
    public function __construct() {
        $this->plugin = MFW_Plugin::get_instance();
        $this->version = MFW_VERSION;
    }
}

/**
 * Class: Rate Limiter
 * Handles API rate limiting
 */
class MFW_Rate_Limiter extends MFW_Base {
    private $limits = [];
    private $usage = [];
    
    public function __construct() {
        parent::__construct();
        $this->init_limits();
    }

    private function init_limits() {
        $this->limits = [
            'openai' => [
                'requests_per_minute' => 60,
                'tokens_per_minute' => 90000,
            ],
            'gemini' => [
                'requests_per_minute' => 100,
                'characters_per_minute' => 100000,
            ],
            'deepseek' => [
                'requests_per_minute' => 50,
                'tokens_per_minute' => 50000,
            ],
        ];
    }

    public function check_limit($service, $type = 'requests') {
        if (!isset($this->usage[$service][$type])) {
            $this->usage[$service][$type] = [
                'count' => 0,
                'reset_time' => time() + 60,
            ];
        }

        $usage = &$this->usage[$service][$type];
        
        // Reset counter if time expired
        if (time() > $usage['reset_time']) {
            $usage['count'] = 0;
            $usage['reset_time'] = time() + 60;
        }

        // Check if limit exceeded
        if ($usage['count'] >= $this->limits[$service][$type . '_per_minute']) {
            throw new \Exception("Rate limit exceeded for $service ($type)");
        }

        $usage['count']++;
        return true;
    }
}

/**
 * Class: API Client
 * Handles API communication
 */
class MFW_API_Client extends MFW_Base {
    private $rate_limiter;
    private $last_response;
    
    public function __construct() {
        parent::__construct();
        $this->rate_limiter = new MFW_Rate_Limiter();
    }

    public function request($endpoint, $args = [], $method = 'GET') {
        try {
            $this->rate_limiter->check_limit($args['service'] ?? 'default');

            $response = wp_remote_request($endpoint, [
                'method' => $method,
                'headers' => $this->get_headers($args),
                'body' => $this->prepare_body($args),
                'timeout' => $args['timeout'] ?? 30,
            ]);

            if (is_wp_error($response)) {
                throw new \Exception($response->get_error_message());
            }

            $this->last_response = $response;
            return $this->parse_response($response);

        } catch (\Exception $e) {
            $this->log_error("API request failed: " . $e->getMessage(), [
                'endpoint' => $endpoint,
                'args' => $args,
            ]);
            throw $e;
        }
    }

    protected function get_headers($args) {
        $headers = [
            'Accept' => 'application/json',
            'User-Agent' => 'MFW/' . $this->version,
        ];

        if (isset($args['headers'])) {
            $headers = array_merge($headers, $args['headers']);
        }

        return $headers;
    }

    protected function prepare_body($args) {
        if (isset($args['body'])) {
            if (is_array($args['body'])) {
                return json_encode($args['body']);
            }
            return $args['body'];
        }
        return null;
    }

    protected function parse_response($response) {
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception("Invalid JSON response");
        }

        return $data;
    }
}
/**
 * Class: AI Service Base
 * Base class for AI service implementations
 */
class MFW_AI_Service extends MFW_Base {
    protected $api_client;
    protected $service_name;
    protected $current_time = '2025-05-16 09:53:54';
    protected $current_user = 'maziyarid';
    
    public function __construct() {
        parent::__construct();
        $this->api_client = new MFW_API_Client();
    }

    protected function validate_api_key() {
        $api_key = $this->get_api_key();
        if (empty($api_key)) {
            throw new \Exception("API key not configured for {$this->service_name}");
        }
        return $api_key;
    }

    protected function get_api_key() {
        return get_option("mfw_{$this->service_name}_api_key", '');
    }

    protected function log_usage($endpoint, $tokens = 0, $cost = 0) {
        global $wpdb;
        
        $wpdb->insert(
            $wpdb->prefix . MFW_TABLE_ANALYTICS,
            [
                'service' => $this->service_name,
                'endpoint' => $endpoint,
                'tokens' => $tokens,
                'cost' => $cost,
                'user_id' => get_current_user_id(),
                'created_at' => $this->current_time,
            ],
            ['%s', '%s', '%d', '%f', '%d', '%s']
        );
    }
}

/**
 * Class: OpenAI Service
 * Handles communication with OpenAI API
 */
class MFW_OpenAI_Service extends MFW_AI_Service {
    private $api_base_url = 'https://api.openai.com/v1';
    private $max_tokens = 4000;
    
    public function __construct() {
        parent::__construct();
        $this->service_name = 'openai';
    }

    public function generate_content($prompt, $options = []) {
        try {
            $api_key = $this->validate_api_key();
            
            $default_options = [
                'model' => 'gpt-4',
                'temperature' => 0.7,
                'max_tokens' => 2000,
                'top_p' => 1,
                'frequency_penalty' => 0,
                'presence_penalty' => 0,
            ];

            $options = wp_parse_args($options, $default_options);

            $response = $this->api_client->request(
                $this->api_base_url . '/chat/completions',
                [
                    'method' => 'POST',
                    'headers' => [
                        'Authorization' => 'Bearer ' . $api_key,
                        'Content-Type' => 'application/json',
                    ],
                    'body' => [
                        'model' => $options['model'],
                        'messages' => [
                            [
                                'role' => 'system',
                                'content' => 'You are a professional content writer.',
                            ],
                            [
                                'role' => 'user',
                                'content' => $prompt,
                            ],
                        ],
                        'temperature' => $options['temperature'],
                        'max_tokens' => $options['max_tokens'],
                        'top_p' => $options['top_p'],
                        'frequency_penalty' => $options['frequency_penalty'],
                        'presence_penalty' => $options['presence_penalty'],
                    ],
                    'timeout' => 60,
                    'service' => $this->service_name,
                ]
            );

            if (isset($response['error'])) {
                throw new \Exception($response['error']['message']);
            }

            $content = $response['choices'][0]['message']['content'] ?? '';
            $usage = $response['usage'] ?? [];

            // Log usage
            $this->log_usage(
                'chat/completions',
                $usage['total_tokens'] ?? 0,
                $this->calculate_cost($usage['total_tokens'] ?? 0, $options['model'])
            );

            return $content;

        } catch (\Exception $e) {
            $this->log_error('OpenAI content generation failed: ' . $e->getMessage());
            throw $e;
        }
    }

    public function analyze_sentiment($text) {
        try {
            $prompt = "Analyze the sentiment of the following text and return a JSON object with 'sentiment' (positive, negative, or neutral) and 'confidence' (0-1): $text";
            
            $response = $this->generate_content($prompt, [
                'model' => 'gpt-3.5-turbo',
                'temperature' => 0.3,
                'max_tokens' => 100,
            ]);

            return json_decode($response, true);

        } catch (\Exception $e) {
            $this->log_error('Sentiment analysis failed: ' . $e->getMessage());
            throw $e;
        }
    }

    public function generate_image($prompt, $options = []) {
        try {
            $api_key = $this->validate_api_key();

            $default_options = [
                'size' => '1024x1024',
                'quality' => 'standard',
                'style' => 'vivid',
                'n' => 1,
            ];

            $options = wp_parse_args($options, $default_options);

            $response = $this->api_client->request(
                $this->api_base_url . '/images/generations',
                [
                    'method' => 'POST',
                    'headers' => [
                        'Authorization' => 'Bearer ' . $api_key,
                        'Content-Type' => 'application/json',
                    ],
                    'body' => [
                        'prompt' => $prompt,
                        'n' => $options['n'],
                        'size' => $options['size'],
                        'quality' => $options['quality'],
                        'style' => $options['style'],
                    ],
                    'service' => $this->service_name,
                ]
            );

            if (isset($response['error'])) {
                throw new \Exception($response['error']['message']);
            }

            return array_map(function($image) {
                return $image['url'];
            }, $response['data']);

        } catch (\Exception $e) {
            $this->log_error('Image generation failed: ' . $e->getMessage());
            throw $e;
        }
    }

    private function calculate_cost($tokens, $model) {
        $rates = [
            'gpt-4' => 0.03,
            'gpt-3.5-turbo' => 0.002,
        ];

        $rate = $rates[$model] ?? 0.002;
        return ($tokens / 1000) * $rate;
    }
}

/**
 * Class: Gemini Service
 * Handles communication with Google's Gemini API
 */
class MFW_Gemini_Service extends MFW_AI_Service {
    private $api_base_url = 'https://generativelanguage.googleapis.com/v1';
    
    public function __construct() {
        parent::__construct();
        $this->service_name = 'gemini';
    }

    public function generate_content($prompt, $options = []) {
        try {
            $api_key = $this->validate_api_key();

            $default_options = [
                'model' => 'gemini-pro',
                'temperature' => 0.7,
                'topK' => 40,
                'topP' => 0.95,
                'maxOutputTokens' => 2048,
            ];

            $options = wp_parse_args($options, $default_options);

            $response = $this->api_client->request(
                $this->api_base_url . '/models/' . $options['model'] . ':generateContent',
                [
                    'method' => 'POST',
                    'headers' => [
                        'Authorization' => 'Bearer ' . $api_key,
                        'Content-Type' => 'application/json',
                    ],
                    'body' => [
                        'contents' => [
                            [
                                'parts' => [
                                    ['text' => $prompt]
                                ]
                            ]
                        ],
                        'generationConfig' => [
                            'temperature' => $options['temperature'],
                            'topK' => $options['topK'],
                            'topP' => $options['topP'],
                            'maxOutputTokens' => $options['maxOutputTokens'],
                        ],
                    ],
                    'service' => $this->service_name,
                ]
            );

            if (isset($response['error'])) {
                throw new \Exception($response['error']['message']);
            }

            return $response['candidates'][0]['content']['parts'][0]['text'] ?? '';

        } catch (\Exception $e) {
            $this->log_error('Gemini content generation failed: ' . $e->getMessage());
            throw $e;
        }
    }
}
/**
 * Class: DeepSeek Service
 * Handles communication with DeepSeek API
 */
class MFW_DeepSeek_Service extends MFW_AI_Service {
    private $api_base_url = 'https://api.deepseek.com/v1';
    private $current_time = '2025-05-16 09:56:37';
    private $current_user = 'maziyarid';
    
    public function __construct() {
        parent::__construct();
        $this->service_name = 'deepseek';
    }

    public function generate_content($prompt, $options = []) {
        try {
            $api_key = $this->validate_api_key();

            $default_options = [
                'model' => 'deepseek-coder-33b',
                'temperature' => 0.7,
                'max_tokens' => 2048,
                'top_p' => 0.95,
            ];

            $options = wp_parse_args($options, $default_options);

            $response = $this->api_client->request(
                $this->api_base_url . '/chat/completions',
                [
                    'method' => 'POST',
                    'headers' => [
                        'Authorization' => 'Bearer ' . $api_key,
                        'Content-Type' => 'application/json',
                    ],
                    'body' => [
                        'model' => $options['model'],
                        'messages' => [
                            [
                                'role' => 'system',
                                'content' => 'You are a professional content creator specializing in detailed, accurate content.',
                            ],
                            [
                                'role' => 'user',
                                'content' => $prompt,
                            ],
                        ],
                        'temperature' => $options['temperature'],
                        'max_tokens' => $options['max_tokens'],
                        'top_p' => $options['top_p'],
                    ],
                    'service' => $this->service_name,
                ]
            );

            if (isset($response['error'])) {
                throw new \Exception($response['error']['message']);
            }

            return $response['choices'][0]['message']['content'] ?? '';

        } catch (\Exception $e) {
            $this->log_error('DeepSeek content generation failed: ' . $e->getMessage());
            throw $e;
        }
    }
}

/**
 * Class: Content Processor
 * Handles content processing and optimization
 */
class MFW_Content_Processor extends MFW_Base {
    private $ai_service;
    private $sanitizer;
    private $current_time = '2025-05-16 09:56:37';
    private $current_user = 'maziyarid';

    public function __construct() {
        parent::__construct();
        $this->init_ai_service();
        $this->sanitizer = new MFW_Sanitizer();
    }

    private function init_ai_service() {
        $service_type = get_option('mfw_ai_service', MFW_AI_DEFAULT);
        
        switch ($service_type) {
            case MFW_AI_GEMINI:
                $this->ai_service = new MFW_Gemini_Service();
                break;
            case MFW_AI_DEEPSEEK:
                $this->ai_service = new MFW_DeepSeek_Service();
                break;
            default:
                $this->ai_service = new MFW_OpenAI_Service();
        }
    }

    public function process_content($content, $options = []) {
        try {
            $default_options = [
                'type' => MFW_CONTENT_TYPE_ARTICLE,
                'enhance_seo' => true,
                'improve_readability' => true,
                'target_length' => 1000,
                'tone' => 'professional',
                'language' => 'en',
            ];

            $options = wp_parse_args($options, $default_options);

            // Clean and prepare content
            $content = $this->sanitizer->clean_content($content);

            // Generate enhancement prompt
            $prompt = $this->build_enhancement_prompt($content, $options);

            // Process with AI
            $enhanced_content = $this->ai_service->generate_content($prompt, [
                'temperature' => 0.7,
                'max_tokens' => max(1000, strlen($content) * 1.5),
            ]);

            // Post-process content
            $processed_content = $this->post_process_content($enhanced_content, $options);

            // Add metadata
            $metadata = [
                'processed_by' => get_class($this->ai_service),
                'processed_at' => $this->current_time,
                'processed_by_user' => $this->current_user,
                'original_length' => strlen($content),
                'processed_length' => strlen($processed_content),
            ];

            return [
                'content' => $processed_content,
                'metadata' => $metadata,
                'seo_data' => $options['enhance_seo'] ? $this->generate_seo_data($processed_content) : [],
            ];

        } catch (\Exception $e) {
            $this->log_error('Content processing failed: ' . $e->getMessage());
            throw $e;
        }
    }

    private function build_enhancement_prompt($content, $options) {
        $instructions = [
            "Enhance the following content while maintaining its core message and facts.",
            "Target length: {$options['target_length']} words.",
            "Tone: {$options['tone']}",
            "Make it engaging and well-structured.",
            "Optimize for readability and clarity.",
        ];

        if ($options['enhance_seo']) {
            $instructions[] = "Optimize for SEO while maintaining natural flow.";
        }

        return implode("\n", $instructions) . "\n\nContent:\n" . $content;
    }

    private function post_process_content($content, $options) {
        // Add paragraph breaks
        $content = wpautop($content);

        // Add headings if needed
        if (strpos($content, '<h') === false) {
            $content = $this->add_headings($content);
        }

        // Optimize for readability
        if ($options['improve_readability']) {
            $content = $this->improve_readability($content);
        }

        return $content;
    }

    private function add_headings($content) {
        $paragraphs = explode("\n\n", $content);
        $sections = [];
        $current_section = '';
        
        foreach ($paragraphs as $i => $paragraph) {
            if ($i > 0 && $i % 3 === 0) {
                // Generate heading for previous section
                $heading = $this->ai_service->generate_content(
                    "Generate a short, engaging heading for this section:\n" . $current_section,
                    ['temperature' => 0.7, 'max_tokens' => 50]
                );
                
                $sections[] = "<h2>" . trim($heading) . "</h2>\n" . $current_section;
                $current_section = '';
            }
            $current_section .= $paragraph . "\n\n";
        }
        
        if ($current_section) {
            $sections[] = $current_section;
        }
        
        return implode("\n", $sections);
    }

    private function improve_readability($content) {
        // Break long paragraphs
        $content = preg_replace_callback(
            '/(<p[^>]*>.*?<\/p>)/s',
            function($matches) {
                $paragraph = $matches[1];
                if (str_word_count(strip_tags($paragraph)) > 100) {
                    $sentences = preg_split('/(?<=[.!?])\s+/', strip_tags($paragraph), -1, PREG_SPLIT_NO_EMPTY);
                    $chunks = array_chunk($sentences, 3);
                    return implode("\n\n", array_map(function($chunk) {
                        return '<p>' . implode(' ', $chunk) . '</p>';
                    }, $chunks));
                }
                return $paragraph;
            },
            $content
        );

        // Add subheadings for long sections
        $content = preg_replace_callback(
            '/(<h[^>]*>.*?<\/h[^>]*>)(.*?)(?=<h[^>]*>|$)/s',
            function($matches) {
                $heading = $matches[1];
                $section = $matches[2];
                if (str_word_count(strip_tags($section)) > 300) {
                    $subsections = $this->split_into_subsections($section);
                    return $heading . $subsections;
                }
                return $heading . $section;
            },
            $content
        );

        return $content;
    }

    private function split_into_subsections($content) {
        $paragraphs = explode("\n\n", $content);
        $subsections = [];
        $current_subsection = [];
        
        foreach ($paragraphs as $i => $paragraph) {
            $current_subsection[] = $paragraph;
            
            if (count($current_subsection) >= 3) {
                $subheading = $this->ai_service->generate_content(
                    "Generate a brief subheading for:\n" . implode("\n", $current_subsection),
                    ['temperature' => 0.7, 'max_tokens' => 30]
                );
                
                $subsections[] = "<h3>" . trim($subheading) . "</h3>\n" . implode("\n\n", $current_subsection);
                $current_subsection = [];
            }
        }
        
        if (!empty($current_subsection)) {
            $subsections[] = implode("\n\n", $current_subsection);
        }
        
        return implode("\n\n", $subsections);
    }

    private function generate_seo_data($content) {
        try {
            $prompt = "Analyze the following content and provide SEO metadata including title, description, and keywords in JSON format:\n\n" . substr(strip_tags($content), 0, 1000);
            
            $seo_data = $this->ai_service->generate_content($prompt, [
                'temperature' => 0.3,
                'max_tokens' => 500,
            ]);

            return json_decode($seo_data, true) ?: [];

        } catch (\Exception $e) {
            $this->log_error('SEO data generation failed: ' . $e->getMessage());
            return [];
        }
    }
}
/**
 * Class: Image Processor
 * Handles image generation, optimization, and processing
 */
class MFW_Image_Processor extends MFW_Base {
    private $ai_service;
    private $current_time = '2025-05-16 09:58:19';
    private $current_user = 'maziyarid';
    private $allowed_mime_types = ['image/jpeg', 'image/png', 'image/webp'];
    
    public function __construct() {
        parent::__construct();
        $this->ai_service = new MFW_OpenAI_Service(); // Default to OpenAI for image generation
    }

    public function generate_image($prompt, $options = []) {
        try {
            $default_options = [
                'size' => '1024x1024',
                'quality' => 'standard',
                'style' => 'natural',
                'save' => true,
                'optimize' => true,
            ];

            $options = wp_parse_args($options, $default_options);

            // Generate image URL using AI
            $image_urls = $this->ai_service->generate_image($prompt, $options);
            
            if (empty($image_urls)) {
                throw new \Exception('No images generated');
            }

            $results = [];
            foreach ($image_urls as $url) {
                $result = [
                    'url' => $url,
                    'metadata' => [
                        'prompt' => $prompt,
                        'generated_at' => $this->current_time,
                        'generated_by' => $this->current_user,
                        'options' => $options,
                    ],
                ];

                if ($options['save']) {
                    $result['attachment_id'] = $this->save_image($url, $prompt);
                }

                if ($options['optimize']) {
                    $result = $this->optimize_image($result);
                }

                $results[] = $result;
            }

            return $results;

        } catch (\Exception $e) {
            $this->log_error('Image generation failed: ' . $e->getMessage());
            throw $e;
        }
    }

    private function save_image($url, $title) {
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/media.php');
        require_once(ABSPATH . 'wp-admin/includes/image.php');

        // Download image
        $tmp = download_url($url);
        if (is_wp_error($tmp)) {
            throw new \Exception('Failed to download image: ' . $tmp->get_error_message());
        }

        // Prepare file array
        $file_array = [
            'name' => sanitize_file_name($title . '-' . uniqid() . '.png'),
            'tmp_name' => $tmp,
            'type' => 'image/png',
        ];

        // Insert attachment
        $attachment_id = media_handle_sideload($file_array, 0, $title);

        // Clean up
        @unlink($tmp);

        if (is_wp_error($attachment_id)) {
            throw new \Exception('Failed to save image: ' . $attachment_id->get_error_message());
        }

        return $attachment_id;
    }

    private function optimize_image($result) {
        if (!isset($result['attachment_id'])) {
            return $result;
        }

        $file_path = get_attached_file($result['attachment_id']);
        if (!$file_path) {
            return $result;
        }

        // Basic optimization
        $image = wp_get_image_editor($file_path);
        if (!is_wp_error($image)) {
            // Resize if too large
            $max_size = 2000;
            $size = $image->get_size();
            if ($size['width'] > $max_size || $size['height'] > $max_size) {
                $image->resize($max_size, $max_size, false);
            }

            // Set quality
            $image->set_quality(85);

            // Convert to WebP if supported
            if ($this->supports_webp()) {
                $webp_file = preg_replace('/\.[^.]+$/', '.webp', $file_path);
                $image->save($webp_file, 'image/webp');
                $result['webp_url'] = preg_replace('/\.[^.]+$/', '.webp', wp_get_attachment_url($result['attachment_id']));
            }

            // Save optimized original
            $image->save($file_path);
        }

        return $result;
    }

    private function supports_webp() {
        return function_exists('imagewebp');
    }
}

/**
 * Abstract Class: Fetcher Base
 * Base class for all content fetchers
 */
abstract class MFW_Fetcher_Base extends MFW_Base {
    protected $current_time = '2025-05-16 09:58:19';
    protected $current_user = 'maziyarid';
    protected $fetcher_name;
    protected $last_fetch;
    protected $rate_limiter;

    public function __construct() {
        parent::__construct();
        $this->rate_limiter = new MFW_Rate_Limiter();
        $this->last_fetch = get_option("mfw_last_fetch_{$this->fetcher_name}", 0);
    }

    abstract protected function fetch_items($query, $options = []);
    abstract protected function parse_item($item);

    protected function update_last_fetch() {
        update_option("mfw_last_fetch_{$this->fetcher_name}", time());
    }

    protected function validate_response($response) {
        if (is_wp_error($response)) {
            throw new \Exception($response->get_error_message());
        }

        $response_code = wp_remote_retrieve_response_code($response);
        if ($response_code !== 200) {
            throw new \Exception("API returned error code: $response_code");
        }

        return wp_remote_retrieve_body($response);
    }

    protected function log_fetch($query, $count, $success = true) {
        global $wpdb;
        
        $wpdb->insert(
            $wpdb->prefix . MFW_TABLE_ANALYTICS,
            [
                'fetcher' => $this->fetcher_name,
                'query' => $query,
                'items_count' => $count,
                'status' => $success ? 'success' : 'failed',
                'created_at' => $this->current_time,
                'user_id' => get_current_user_id(),
            ],
            ['%s', '%s', '%d', '%s', '%s', '%d']
        );
    }
}

/**
 * Class: Amazon Fetcher
 * Handles fetching product data from Amazon
 */
class MFW_Amazon_Fetcher extends MFW_Fetcher_Base {
    private $api_key;
    private $secret_key;
    private $partner_tag;
    private $marketplace = 'com';

    public function __construct() {
        $this->fetcher_name = 'amazon';
        parent::__construct();
        
        $this->api_key = get_option('mfw_amazon_api_key');
        $this->secret_key = get_option('mfw_amazon_secret_key');
        $this->partner_tag = get_option('mfw_amazon_partner_tag');
    }

    public function fetch_items($query, $options = []) {
        try {
            if (empty($this->api_key) || empty($this->secret_key) || empty($this->partner_tag)) {
                throw new \Exception('Amazon API credentials not configured');
            }

            $default_options = [
                'SearchIndex' => 'All',
                'ResponseGroup' => 'ItemAttributes,Offers,Images',
                'Sort' => 'relevancerank',
                'ItemPage' => 1,
            ];

            $options = wp_parse_args($options, $default_options);

            // Generate signature
            $params = [
                'Service' => 'AWSECommerceService',
                'Operation' => 'ItemSearch',
                'AWSAccessKeyId' => $this->api_key,
                'AssociateTag' => $this->partner_tag,
                'Keywords' => $query,
                'Timestamp' => gmdate('Y-m-d\TH:i:s\Z'),
            ] + $options;

            ksort($params);

            $canonical_query = [];
            foreach ($params as $key => $value) {
                $canonical_query[] = rawurlencode($key) . '=' . rawurlencode($value);
            }

            $string_to_sign = "GET\n" .
                            "webservices.amazon.{$this->marketplace}\n" .
                            "/onca/xml\n" .
                            implode('&', $canonical_query);

            $signature = base64_encode(
                hash_hmac('sha256', $string_to_sign, $this->secret_key, true)
            );

            $request_url = "https://webservices.amazon.{$this->marketplace}/onca/xml?" .
                          implode('&', $canonical_query) .
                          '&Signature=' . rawurlencode($signature);

            $response = wp_remote_get($request_url, [
                'timeout' => 30,
                'user-agent' => 'MFW Amazon Fetcher/1.0',
            ]);

            $body = $this->validate_response($response);
            $xml = simplexml_load_string($body);

            if (!$xml) {
                throw new \Exception('Failed to parse Amazon API response');
            }

            $items = [];
            foreach ($xml->Items->Item as $item) {
                $items[] = $this->parse_item($item);
            }

            $this->log_fetch($query, count($items));
            $this->update_last_fetch();

            return $items;

        } catch (\Exception $e) {
            $this->log_error('Amazon fetch failed: ' . $e->getMessage());
            $this->log_fetch($query, 0, false);
            throw $e;
        }
    }

    protected function parse_item($item) {
        return [
            'type' => 'product',
            'source' => 'amazon',
            'id' => (string)$item->ASIN,
            'title' => (string)$item->ItemAttributes->Title,
            'description' => (string)$item->EditorialReviews->EditorialReview->Content ?? '',
            'url' => (string)$item->DetailPageURL,
            'image_url' => (string)$item->LargeImage->URL ?? '',
            'price' => [
                'amount' => (float)($item->OfferSummary->LowestNewPrice->Amount ?? 0) / 100,
                'currency' => (string)$item->OfferSummary->LowestNewPrice->CurrencyCode ?? 'USD',
            ],
            'rating' => [
                'average' => (float)($item->CustomerReviews->AverageRating ?? 0),
                'count' => (int)($item->CustomerReviews->TotalReviews ?? 0),
            ],
            'metadata' => [
                'brand' => (string)$item->ItemAttributes->Brand ?? '',
                'category' => (string)$item->ItemAttributes->ProductGroup ?? '',
                'features' => array_map('strval', (array)$item->ItemAttributes->Feature ?? []),
            ],
            'fetched_at' => $this->current_time,
            'fetched_by' => $this->current_user,
        ];
    }
}
/**
 * Class: eBay Fetcher
 * Handles fetching product data from eBay
 */
class MFW_Ebay_Fetcher extends MFW_Fetcher_Base {
    private $api_key;
    private $current_time = '2025-05-16 10:00:04';
    private $current_user = 'maziyarid';
    private $endpoint = 'https://api.ebay.com/buy/browse/v1';

    public function __construct() {
        $this->fetcher_name = 'ebay';
        parent::__construct();
        $this->api_key = get_option('mfw_ebay_api_key');
    }

    public function fetch_items($query, $options = []) {
        try {
            if (empty($this->api_key)) {
                throw new \Exception('eBay API key not configured');
            }

            $default_options = [
                'limit' => 50,
                'offset' => 0,
                'filter' => 'conditions:{NEW}',
                'sort' => 'newlyListed',
            ];

            $options = wp_parse_args($options, $default_options);

            $response = wp_remote_get(
                $this->endpoint . '/item_summary/search?' . http_build_query([
                    'q' => $query,
                    'limit' => $options['limit'],
                    'offset' => $options['offset'],
                    'filter' => $options['filter'],
                    'sort' => $options['sort'],
                ]),
                [
                    'headers' => [
                        'Authorization' => 'Bearer ' . $this->api_key,
                        'Content-Type' => 'application/json',
                        'X-EBAY-C-MARKETPLACE-ID' => 'EBAY_US',
                    ],
                    'timeout' => 30,
                ]
            );

            $data = $this->validate_response($response);
            $json = json_decode($data, true);

            if (!isset($json['itemSummaries'])) {
                throw new \Exception('Invalid eBay API response');
            }

            $items = array_map([$this, 'parse_item'], $json['itemSummaries']);

            $this->log_fetch($query, count($items));
            $this->update_last_fetch();

            return $items;

        } catch (\Exception $e) {
            $this->log_error('eBay fetch failed: ' . $e->getMessage());
            $this->log_fetch($query, 0, false);
            throw $e;
        }
    }

    protected function parse_item($item) {
        return [
            'type' => 'product',
            'source' => 'ebay',
            'id' => $item['itemId'],
            'title' => $item['title'],
            'description' => $item['shortDescription'] ?? '',
            'url' => $item['itemWebUrl'],
            'image_url' => $item['image']['imageUrl'] ?? '',
            'price' => [
                'amount' => $item['price']['value'],
                'currency' => $item['price']['currency'],
            ],
            'condition' => $item['condition'] ?? 'Unknown',
            'location' => [
                'country' => $item['itemLocation']['country'],
                'postal_code' => $item['itemLocation']['postalCode'] ?? '',
            ],
            'shipping_options' => array_map(function($option) {
                return [
                    'type' => $option['shippingServiceCode'],
                    'cost' => $option['shippingCost']['value'] ?? 0,
                ];
            }, $item['shippingOptions'] ?? []),
            'seller' => [
                'username' => $item['seller']['username'],
                'feedback_score' => $item['seller']['feedbackScore'] ?? 0,
            ],
            'fetched_at' => $this->current_time,
            'fetched_by' => $this->current_user,
        ];
    }
}

/**
 * Class: Google News Fetcher
 * Handles fetching news articles from Google News
 */
class MFW_Google_News_Fetcher extends MFW_Fetcher_Base {
    private $api_key;
    private $endpoint = 'https://newsapi.org/v2/everything';

    public function __construct() {
        $this->fetcher_name = 'google_news';
        parent::__construct();
        $this->api_key = get_option('mfw_google_news_api_key');
    }

    public function fetch_items($query, $options = []) {
        try {
            if (empty($this->api_key)) {
                throw new \Exception('Google News API key not configured');
            }

            $default_options = [
                'language' => 'en',
                'sortBy' => 'relevancy',
                'pageSize' => 100,
                'page' => 1,
                'from' => date('Y-m-d', strtotime('-30 days')),
                'to' => date('Y-m-d'),
            ];

            $options = wp_parse_args($options, $default_options);

            $response = wp_remote_get(
                $this->endpoint . '?' . http_build_query([
                    'q' => $query,
                    'apiKey' => $this->api_key,
                    'language' => $options['language'],
                    'sortBy' => $options['sortBy'],
                    'pageSize' => $options['pageSize'],
                    'page' => $options['page'],
                    'from' => $options['from'],
                    'to' => $options['to'],
                ]),
                ['timeout' => 30]
            );

            $data = $this->validate_response($response);
            $json = json_decode($data, true);

            if (!isset($json['articles'])) {
                throw new \Exception('Invalid Google News API response');
            }

            $items = array_map([$this, 'parse_item'], $json['articles']);

            $this->log_fetch($query, count($items));
            $this->update_last_fetch();

            return $items;

        } catch (\Exception $e) {
            $this->log_error('Google News fetch failed: ' . $e->getMessage());
            $this->log_fetch($query, 0, false);
            throw $e;
        }
    }

    protected function parse_item($article) {
        $content = $article['content'] ?? $article['description'] ?? '';
        
        // Extract main image if available
        $image_url = $article['urlToImage'] ?? '';
        if (!$image_url && preg_match('/<img[^>]+src=[\'"]([^\'"]+)[\'"][^>]*>/i', $content, $matches)) {
            $image_url = $matches[1];
        }

        return [
            'type' => 'article',
            'source' => 'google_news',
            'source_name' => $article['source']['name'] ?? '',
            'author' => $article['author'] ?? '',
            'title' => $article['title'],
            'description' => $article['description'] ?? '',
            'content' => $content,
            'url' => $article['url'],
            'image_url' => $image_url,
            'published_at' => $article['publishedAt'],
            'metadata' => [
                'language' => $article['language'] ?? 'en',
                'category' => $this->detect_category($article['title'], $content),
                'sentiment' => $this->analyze_sentiment($content),
            ],
            'fetched_at' => $this->current_time,
            'fetched_by' => $this->current_user,
        ];
    }

    private function detect_category($title, $content) {
        // Simple category detection based on keywords
        $categories = [
            'technology' => ['tech', 'technology', 'software', 'hardware', 'ai', 'digital'],
            'business' => ['business', 'economy', 'market', 'finance', 'stock'],
            'health' => ['health', 'medical', 'medicine', 'healthcare', 'wellness'],
            'science' => ['science', 'research', 'study', 'scientific', 'discovery'],
            'entertainment' => ['entertainment', 'movie', 'music', 'celebrity', 'film'],
            'sports' => ['sport', 'sports', 'game', 'match', 'tournament'],
        ];

        $text = strtolower($title . ' ' . $content);
        $max_count = 0;
        $detected_category = 'general';

        foreach ($categories as $category => $keywords) {
            $count = 0;
            foreach ($keywords as $keyword) {
                $count += substr_count($text, $keyword);
            }
            if ($count > $max_count) {
                $max_count = $count;
                $detected_category = $category;
            }
        }

        return $detected_category;
    }

    private function analyze_sentiment($text) {
        // Simple sentiment analysis based on keyword matching
        $positive_words = ['good', 'great', 'awesome', 'excellent', 'positive', 'success'];
        $negative_words = ['bad', 'poor', 'negative', 'failure', 'worst', 'terrible'];

        $text = strtolower($text);
        $positive_count = 0;
        $negative_count = 0;

        foreach ($positive_words as $word) {
            $positive_count += substr_count($text, $word);
        }

        foreach ($negative_words as $word) {
            $negative_count += substr_count($text, $word);
        }

        if ($positive_count > $negative_count) {
            return 'positive';
        } elseif ($negative_count > $positive_count) {
            return 'negative';
        }

        return 'neutral';
    }
}
/**
 * Class: RSS Fetcher
 * Handles fetching content from RSS feeds
 */
class MFW_RSS_Fetcher extends MFW_Fetcher_Base {
    private $current_time = '2025-05-16 10:01:28';
    private $current_user = 'maziyarid';
    private $cache_duration = 3600; // 1 hour

    public function __construct() {
        $this->fetcher_name = 'rss';
        parent::__construct();
    }

    public function fetch_items($feed_url, $options = []) {
        try {
            $default_options = [
                'max_items' => 50,
                'cache' => true,
                'validate_feed' => true,
                'sanitize_content' => true,
            ];

            $options = wp_parse_args($options, $default_options);
            
            // Check cache first
            $cache_key = md5($feed_url);
            if ($options['cache']) {
                $cached = $this->cache_get($cache_key, 'rss_feeds');
                if ($cached) {
                    return $cached;
                }
            }

            // Fetch feed
            $response = wp_remote_get($feed_url, [
                'timeout' => 30,
                'user-agent' => 'MFW RSS Fetcher/1.0',
            ]);

            $content = $this->validate_response($response);

            // Load and parse RSS
            libxml_use_internal_errors(true);
            $rss = simplexml_load_string($content);

            if (!$rss) {
                $errors = libxml_get_errors();
                libxml_clear_errors();
                throw new \Exception('Invalid RSS feed: ' . $errors[0]->message);
            }

            $items = [];
            $count = 0;

            // Handle different RSS formats
            if (isset($rss->channel->item)) {
                // RSS 2.0
                foreach ($rss->channel->item as $item) {
                    if ($count >= $options['max_items']) break;
                    $items[] = $this->parse_item($item, 'rss2');
                    $count++;
                }
            } elseif (isset($rss->entry)) {
                // Atom
                foreach ($rss->entry as $item) {
                    if ($count >= $options['max_items']) break;
                    $items[] = $this->parse_item($item, 'atom');
                    $count++;
                }
            }

            // Cache results
            if ($options['cache']) {
                $this->cache_set($cache_key, $items, 'rss_feeds', $this->cache_duration);
            }

            $this->log_fetch($feed_url, count($items));
            $this->update_last_fetch();

            return $items;

        } catch (\Exception $e) {
            $this->log_error('RSS fetch failed: ' . $e->getMessage());
            $this->log_fetch($feed_url, 0, false);
            throw $e;
        }
    }

    protected function parse_item($item, $format = 'rss2') {
        $parsed = [
            'type' => 'article',
            'source' => 'rss',
            'format' => $format,
            'fetched_at' => $this->current_time,
            'fetched_by' => $this->current_user,
        ];

        if ($format === 'rss2') {
            $parsed += [
                'title' => (string)$item->title,
                'description' => (string)$item->description,
                'content' => (string)$item->children('content', true),
                'link' => (string)$item->link,
                'guid' => (string)$item->guid,
                'published_at' => date('Y-m-d H:i:s', strtotime((string)$item->pubDate)),
                'author' => (string)$item->author,
                'categories' => array_map('strval', (array)$item->category),
            ];
        } else {
            // Atom format
            $parsed += [
                'title' => (string)$item->title,
                'description' => (string)$item->summary,
                'content' => (string)$item->content,
                'link' => (string)$item->link['href'],
                'guid' => (string)$item->id,
                'published_at' => date('Y-m-d H:i:s', strtotime((string)$item->published)),
                'author' => (string)$item->author->name,
                'categories' => array_map(function($category) {
                    return (string)$category['term'];
                }, $item->category),
            ];
        }

        // Extract image if available
        $parsed['image_url'] = $this->extract_image($parsed['content'] ?: $parsed['description']);

        // Add metadata
        $parsed['metadata'] = [
            'word_count' => str_word_count(strip_tags($parsed['content'])),
            'has_images' => !empty($parsed['image_url']),
            'language' => $this->detect_language($parsed['content']),
        ];

        return $parsed;
    }

    private function extract_image($content) {
        // Try to find first image in content
        if (preg_match('/<img[^>]+src=[\'"]([^\'"]+)[\'"][^>]*>/i', $content, $matches)) {
            return $matches[1];
        }

        // Try to find image URL in media:content tag
        if (preg_match('/media:content[^>]+url=[\'"]([^\'"]+)[\'"][^>]*>/i', $content, $matches)) {
            return $matches[1];
        }

        return '';
    }

    private function detect_language($text) {
        // Simple language detection based on common words
        $languages = [
            'en' => ['the', 'be', 'to', 'of', 'and', 'in', 'that', 'have'],
            'es' => ['el', 'la', 'de', 'que', 'y', 'en', 'un', 'ser'],
            'fr' => ['le', 'la', 'de', 'et', 'en', 'un', 'être', 'avoir'],
        ];

        $text = strtolower($text);
        $max_matches = 0;
        $detected = 'en'; // Default to English

        foreach ($languages as $lang => $words) {
            $matches = 0;
            foreach ($words as $word) {
                if (preg_match('/\b' . preg_quote($word, '/') . '\b/', $text)) {
                    $matches++;
                }
            }
            if ($matches > $max_matches) {
                $max_matches = $matches;
                $detected = $lang;
            }
        }

        return $detected;
    }
}

/**
 * Class: YouTube Fetcher
 * Handles fetching videos from YouTube
 */
class MFW_YouTube_Fetcher extends MFW_Fetcher_Base {
    private $api_key;
    private $endpoint = 'https://www.googleapis.com/youtube/v3';
    private $current_time = '2025-05-16 10:01:28';
    private $current_user = 'maziyarid';

    public function __construct() {
        $this->fetcher_name = 'youtube';
        parent::__construct();
        $this->api_key = get_option('mfw_youtube_api_key');
    }

    public function fetch_items($query, $options = []) {
        try {
            if (empty($this->api_key)) {
                throw new \Exception('YouTube API key not configured');
            }

            $default_options = [
                'max_results' => 50,
                'order' => 'relevance',
                'type' => 'video',
                'video_category' => '',
                'published_after' => date('c', strtotime('-1 month')),
                'language' => 'en',
                'region_code' => 'US',
                'safe_search' => 'moderate',
                'fetch_details' => true,
            ];

            $options = wp_parse_args($options, $default_options);

            // Initial search request
            $search_response = wp_remote_get(
                $this->endpoint . '/search?' . http_build_query([
                    'part' => 'snippet',
                    'q' => $query,
                    'maxResults' => $options['max_results'],
                    'order' => $options['order'],
                    'type' => $options['type'],
                    'videoCategory' => $options['video_category'],
                    'publishedAfter' => $options['published_after'],
                    'relevanceLanguage' => $options['language'],
                    'regionCode' => $options['region_code'],
                    'safeSearch' => $options['safe_search'],
                    'key' => $this->api_key,
                ]),
                ['timeout' => 30]
            );

            $search_data = $this->validate_response($search_response);
            $search_json = json_decode($search_data, true);

            if (!isset($search_json['items'])) {
                throw new \Exception('Invalid YouTube API response');
            }

            $items = [];
            $video_ids = [];

            foreach ($search_json['items'] as $item) {
                $video_ids[] = $item['id']['videoId'];
                $items[$item['id']['videoId']] = $this->parse_search_item($item);
            }

            // Fetch additional video details if requested
            if ($options['fetch_details'] && !empty($video_ids)) {
                $details_response = wp_remote_get(
                    $this->endpoint . '/videos?' . http_build_query([
                        'part' => 'contentDetails,statistics,status',
                        'id' => implode(',', $video_ids),
                        'key' => $this->api_key,
                    ]),
                    ['timeout' => 30]
                );

                $details_data = $this->validate_response($details_response);
                $details_json = json_decode($details_data, true);

                if (isset($details_json['items'])) {
                    foreach ($details_json['items'] as $detail) {
                        $video_id = $detail['id'];
                        if (isset($items[$video_id])) {
                            $items[$video_id] = array_merge(
                                $items[$video_id],
                                $this->parse_video_details($detail)
                            );
                        }
                    }
                }
            }

            $this->log_fetch($query, count($items));
            $this->update_last_fetch();

            return array_values($items);

        } catch (\Exception $e) {
            $this->log_error('YouTube fetch failed: ' . $e->getMessage());
            $this->log_fetch($query, 0, false);
            throw $e;
        }
    }

    protected function parse_search_item($item) {
        return [
            'type' => 'video',
            'source' => 'youtube',
            'id' => $item['id']['videoId'],
            'title' => $item['snippet']['title'],
            'description' => $item['snippet']['description'],
            'published_at' => date('Y-m-d H:i:s', strtotime($item['snippet']['publishedAt'])),
            'thumbnail_url' => $item['snippet']['thumbnails']['high']['url'],
            'channel_id' => $item['snippet']['channelId'],
            'channel_title' => $item['snippet']['channelTitle'],
            'url' => 'https://www.youtube.com/watch?v=' . $item['id']['videoId'],
            'embed_url' => 'https://www.youtube.com/embed/' . $item['id']['videoId'],
            'fetched_at' => $this->current_time,
            'fetched_by' => $this->current_user,
        ];
    }

    protected function parse_video_details($item) {
        return [
            'duration' => $this->parse_duration($item['contentDetails']['duration']),
            'dimension' => $item['contentDetails']['dimension'],
            'definition' => $item['contentDetails']['definition'],
            'statistics' => [
                'views' => (int)($item['statistics']['viewCount'] ?? 0),
                'likes' => (int)($item['statistics']['likeCount'] ?? 0),
                'comments' => (int)($item['statistics']['commentCount'] ?? 0),
            ],
            'status' => [
                'privacy' => $item['status']['privacyStatus'],
                'embeddable' => $item['status']['embeddable'],
            ],
        ];
    }

    private function parse_duration($duration) {
        preg_match('/PT(?:(\d+)H)?(?:(\d+)M)?(?:(\d+)S)?/', $duration, $matches);
        
        $hours = !empty($matches[1]) ? (int)$matches[1] : 0;
        $minutes = !empty($matches[2]) ? (int)$matches[2] : 0;
        $seconds = !empty($matches[3]) ? (int)$matches[3] : 0;

        return [
            'hours' => $hours,
            'minutes' => $minutes,
            'seconds' => $seconds,
            'total_seconds' => ($hours * 3600) + ($minutes * 60) + $seconds,
            'formatted' => sprintf(
                '%02d:%02d:%02d',
                $hours,
                $minutes,
                $seconds
            ),
        ];
    }
}
/**
 * Class: Admin Interface
 * Handles all admin-related functionality
 */
class MFW_Admin extends MFW_Base {
    private $current_time = '2025-05-16 10:03:01';
    private $current_user = 'maziyarid';
    private $menu_slug = 'mfw-dashboard';
    private $capability = 'manage_options';

    public function __construct() {
        parent::__construct();
        add_action('admin_menu', [$this, 'register_menu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('admin_notices', [$this, 'display_notices']);
    }

    public function register_menu() {
        // Main menu
        add_menu_page(
            __('MFW Writer', 'mfw'),
            __('MFW Writer', 'mfw'),
            $this->capability,
            $this->menu_slug,
            [$this, 'render_dashboard'],
            'dashicons-edit',
            30
        );

        // Submenus
        $submenus = [
            'dashboard' => [
                'title' => __('Dashboard', 'mfw'),
                'callback' => 'render_dashboard',
            ],
            'content' => [
                'title' => __('Content Manager', 'mfw'),
                'callback' => 'render_content_manager',
            ],
            'fetchers' => [
                'title' => __('Fetchers', 'mfw'),
                'callback' => 'render_fetchers',
            ],
            'analytics' => [
                'title' => __('Analytics', 'mfw'),
                'callback' => 'render_analytics',
            ],
            'settings' => [
                'title' => __('Settings', 'mfw'),
                'callback' => 'render_settings',
            ],
            'tools' => [
                'title' => __('Tools', 'mfw'),
                'callback' => 'render_tools',
            ],
            'logs' => [
                'title' => __('Logs', 'mfw'),
                'callback' => 'render_logs',
            ],
        ];

        foreach ($submenus as $slug => $menu) {
            add_submenu_page(
                $this->menu_slug,
                $menu['title'],
                $menu['title'],
                $this->capability,
                "mfw-{$slug}",
                [$this, $menu['callback']]
            );
        }
    }

    public function enqueue_assets($hook) {
        if (strpos($hook, 'mfw') === false) {
            return;
        }

        // Styles
        wp_enqueue_style(
            'mfw-admin',
            MFW_URL . 'assets/css/mfw-admin.css',
            [],
            $this->version
        );

        // Scripts
        wp_enqueue_script(
            'mfw-admin',
            MFW_URL . 'assets/js/mfw-admin.js',
            ['jquery', 'wp-api'],
            $this->version,
            true
        );

        // Charts
        wp_enqueue_script(
            'mfw-charts',
            'https://cdn.jsdelivr.net/npm/chart.js',
            [],
            '3.7.0',
            true
        );

        // Localize script
        wp_localize_script('mfw-admin', 'mfwAdmin', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'restUrl' => get_rest_url(null, 'mfw/v1'),
            'nonce' => wp_create_nonce('wp_rest'),
            'currentUser' => $this->current_user,
            'currentTime' => $this->current_time,
            'i18n' => [
                'confirmDelete' => __('Are you sure you want to delete this item?', 'mfw'),
                'confirmReset' => __('Are you sure you want to reset all settings?', 'mfw'),
                'saved' => __('Settings saved successfully.', 'mfw'),
                'error' => __('An error occurred.', 'mfw'),
            ],
        ]);
    }

    public function register_settings() {
        // General Settings
        register_setting('mfw_general', 'mfw_ai_service');
        register_setting('mfw_general', 'mfw_default_language');
        register_setting('mfw_general', 'mfw_auto_fetch');
        register_setting('mfw_general', 'mfw_cache_duration');

        // API Keys
        register_setting('mfw_api_keys', 'mfw_openai_api_key');
        register_setting('mfw_api_keys', 'mfw_gemini_api_key');
        register_setting('mfw_api_keys', 'mfw_deepseek_api_key');
        register_setting('mfw_api_keys', 'mfw_amazon_api_key');
        register_setting('mfw_api_keys', 'mfw_ebay_api_key');
        register_setting('mfw_api_keys', 'mfw_youtube_api_key');

        // Content Settings
        register_setting('mfw_content', 'mfw_content_length');
        register_setting('mfw_content', 'mfw_content_tone');
        register_setting('mfw_content', 'mfw_auto_images');
        register_setting('mfw_content', 'mfw_seo_optimize');

        // Fetcher Settings
        register_setting('mfw_fetchers', 'mfw_fetch_interval');
        register_setting('mfw_fetchers', 'mfw_max_items');
        register_setting('mfw_fetchers', 'mfw_allowed_sources');

        // Analytics Settings
        register_setting('mfw_analytics', 'mfw_analytics_enabled');
        register_setting('mfw_analytics', 'mfw_retention_days');
        register_setting('mfw_analytics', 'mfw_track_usage');
    }

    public function render_dashboard() {
        $analytics = new MFW_Analytics();
        $stats = $analytics->get_dashboard_stats();
        
        include MFW_VIEWS_PATH . 'admin/dashboard.php';
    }

    public function render_content_manager() {
        $content_model = new MFW_Content_Model();
        $items = $content_model->get_items([
            'per_page' => 20,
            'page' => $_GET['paged'] ?? 1,
            'orderby' => $_GET['orderby'] ?? 'created_at',
            'order' => $_GET['order'] ?? 'DESC',
        ]);
        
        include MFW_VIEWS_PATH . 'admin/content-manager.php';
    }

    public function render_fetchers() {
        $fetchers = [
            'amazon' => new MFW_Amazon_Fetcher(),
            'ebay' => new MFW_Ebay_Fetcher(),
            'google_news' => new MFW_Google_News_Fetcher(),
            'rss' => new MFW_RSS_Fetcher(),
            'youtube' => new MFW_YouTube_Fetcher(),
        ];

        $stats = [];
        foreach ($fetchers as $key => $fetcher) {
            $stats[$key] = [
                'last_fetch' => get_option("mfw_last_fetch_{$key}", 0),
                'items_count' => $this->get_fetcher_items_count($key),
                'status' => $this->get_fetcher_status($key),
            ];
        }

        include MFW_VIEWS_PATH . 'admin/fetchers.php';
    }

    public function render_analytics() {
        $analytics = new MFW_Analytics();
        $period = $_GET['period'] ?? '30days';
        $type = $_GET['type'] ?? 'content';

        $data = $analytics->get_analytics_data($period, $type);
        
        include MFW_VIEWS_PATH . 'admin/analytics.php';
    }

    public function render_settings() {
        $current_tab = $_GET['tab'] ?? 'general';
        $settings = [
            'general' => [
                'title' => __('General Settings', 'mfw'),
                'fields' => $this->get_general_settings_fields(),
            ],
            'api_keys' => [
                'title' => __('API Keys', 'mfw'),
                'fields' => $this->get_api_keys_fields(),
            ],
            'content' => [
                'title' => __('Content Settings', 'mfw'),
                'fields' => $this->get_content_settings_fields(),
            ],
            'fetchers' => [
                'title' => __('Fetcher Settings', 'mfw'),
                'fields' => $this->get_fetcher_settings_fields(),
            ],
            'analytics' => [
                'title' => __('Analytics Settings', 'mfw'),
                'fields' => $this->get_analytics_settings_fields(),
            ],
        ];

        include MFW_VIEWS_PATH . 'admin/settings.php';
    }

    private function get_general_settings_fields() {
        return [
            'mfw_ai_service' => [
                'type' => 'select',
                'label' => __('AI Service', 'mfw'),
                'options' => [
                    'openai' => 'OpenAI',
                    'gemini' => 'Google Gemini',
                    'deepseek' => 'DeepSeek',
                ],
                'default' => MFW_AI_DEFAULT,
            ],
            'mfw_default_language' => [
                'type' => 'select',
                'label' => __('Default Language', 'mfw'),
                'options' => $this->get_available_languages(),
                'default' => 'en',
            ],
            'mfw_auto_fetch' => [
                'type' => 'checkbox',
                'label' => __('Enable Auto Fetch', 'mfw'),
                'default' => true,
            ],
            'mfw_cache_duration' => [
                'type' => 'number',
                'label' => __('Cache Duration (seconds)', 'mfw'),
                'default' => 3600,
                'min' => 0,
                'max' => 86400,
            ],
        ];
    }
/**
 * Class: Database Handler
 * Manages all database operations
 */
class MFW_Database_Handler extends MFW_Base {
    private $current_time = '2025-05-16 10:04:28';
    private $current_user = 'maziyarid';
    private $tables = [
        MFW_TABLE_CONTENT,
        MFW_TABLE_LOGS,
        MFW_TABLE_ANALYTICS,
        MFW_TABLE_QUEUE,
        MFW_TABLE_CACHE,
    ];

    public function __construct() {
        parent::__construct();
        add_action('init', [$this, 'check_tables']);
    }

    public function check_tables() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        $sql = [];

        // Content table
        $sql[] = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}" . MFW_TABLE_CONTENT . " (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            title varchar(255) NOT NULL,
            content longtext NOT NULL,
            source varchar(50) NOT NULL,
            source_url varchar(255),
            status varchar(20) NOT NULL DEFAULT 'draft',
            type varchar(50) NOT NULL DEFAULT 'article',
            author_id bigint(20) NOT NULL,
            metadata longtext,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            published_at datetime,
            PRIMARY KEY  (id),
            KEY source (source),
            KEY status (status),
            KEY type (type),
            KEY author_id (author_id)
        ) $charset_collate;";

        // Logs table
        $sql[] = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}" . MFW_TABLE_LOGS . " (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            level varchar(20) NOT NULL,
            message text NOT NULL,
            context longtext,
            created_at datetime NOT NULL,
            component varchar(50),
            user_id bigint(20),
            PRIMARY KEY  (id),
            KEY level (level),
            KEY component (component),
            KEY created_at (created_at)
        ) $charset_collate;";

        // Analytics table
        $sql[] = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}" . MFW_TABLE_ANALYTICS . " (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            event_type varchar(50) NOT NULL,
            event_data longtext NOT NULL,
            user_id bigint(20),
            ip_address varchar(45),
            created_at datetime NOT NULL,
            metadata longtext,
            PRIMARY KEY  (id),
            KEY event_type (event_type),
            KEY user_id (user_id),
            KEY created_at (created_at)
        ) $charset_collate;";

        // Queue table
        $sql[] = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}" . MFW_TABLE_QUEUE . " (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            job_type varchar(50) NOT NULL,
            payload longtext NOT NULL,
            priority int(11) NOT NULL DEFAULT 0,
            attempts int(11) NOT NULL DEFAULT 0,
            reserved_at datetime,
            available_at datetime NOT NULL,
            created_at datetime NOT NULL,
            PRIMARY KEY  (id),
            KEY job_type (job_type),
            KEY priority (priority),
            KEY reserved_at (reserved_at),
            KEY available_at (available_at)
        ) $charset_collate;";

        // Cache table
        $sql[] = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}" . MFW_TABLE_CACHE . " (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            cache_key varchar(255) NOT NULL,
            cache_group varchar(50) NOT NULL,
            cache_value longtext NOT NULL,
            expiration datetime NOT NULL,
            created_at datetime NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY cache_key_group (cache_key, cache_group),
            KEY expiration (expiration)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
        foreach ($sql as $query) {
            dbDelta($query);
        }
    }

    public function insert($table, $data, $format = null) {
        global $wpdb;

        // Add timestamps
        if (!isset($data['created_at'])) {
            $data['created_at'] = $this->current_time;
        }
        if (!isset($data['updated_at'])) {
            $data['updated_at'] = $this->current_time;
        }

        return $wpdb->insert(
            $wpdb->prefix . $table,
            $data,
            $format
        );
    }

    public function update($table, $data, $where, $format = null, $where_format = null) {
        global $wpdb;

        // Update timestamp
        $data['updated_at'] = $this->current_time;

        return $wpdb->update(
            $wpdb->prefix . $table,
            $data,
            $where,
            $format,
            $where_format
        );
    }

    public function delete($table, $where, $where_format = null) {
        global $wpdb;

        return $wpdb->delete(
            $wpdb->prefix . $table,
            $where,
            $where_format
        );
    }

    public function get_row($table, $where = [], $output = OBJECT) {
        global $wpdb;

        $conditions = [];
        $values = [];

        foreach ($where as $field => $value) {
            $conditions[] = "`$field` = %s";
            $values[] = $value;
        }

        $where_clause = !empty($conditions) ? 
            'WHERE ' . implode(' AND ', $conditions) : '';

        $query = $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}$table $where_clause LIMIT 1",
            $values
        );

        return $wpdb->get_row($query, $output);
    }

    public function get_results($table, $args = [], $output = OBJECT) {
        global $wpdb;

        $defaults = [
            'where' => [],
            'orderby' => 'id',
            'order' => 'DESC',
            'limit' => 0,
            'offset' => 0,
            'group_by' => '',
            'having' => '',
        ];

        $args = wp_parse_args($args, $defaults);
        $conditions = [];
        $values = [];

        foreach ($args['where'] as $field => $value) {
            if (is_array($value)) {
                $placeholders = array_fill(0, count($value), '%s');
                $conditions[] = "`$field` IN (" . implode(',', $placeholders) . ")";
                $values = array_merge($values, $value);
            } else {
                $conditions[] = "`$field` = %s";
                $values[] = $value;
            }
        }

        $where_clause = !empty($conditions) ? 
            'WHERE ' . implode(' AND ', $conditions) : '';
        
        $group_clause = $args['group_by'] ? 
            'GROUP BY ' . $args['group_by'] : '';
        
        $having_clause = $args['having'] ? 
            'HAVING ' . $args['having'] : '';
        
        $order_clause = "ORDER BY {$args['orderby']} {$args['order']}";
        
        $limit_clause = $args['limit'] ? 
            'LIMIT ' . (int)$args['offset'] . ',' . (int)$args['limit'] : '';

        $query = $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}$table 
            $where_clause 
            $group_clause 
            $having_clause 
            $order_clause 
            $limit_clause",
            $values
        );

        return $wpdb->get_results($query, $output);
    }

    public function count($table, $where = []) {
        global $wpdb;

        $conditions = [];
        $values = [];

        foreach ($where as $field => $value) {
            $conditions[] = "`$field` = %s";
            $values[] = $value;
        }

        $where_clause = !empty($conditions) ? 
            'WHERE ' . implode(' AND ', $conditions) : '';

        $query = $wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}$table $where_clause",
            $values
        );

        return (int)$wpdb->get_var($query);
    }

    public function truncate($table) {
        global $wpdb;
        return $wpdb->query("TRUNCATE TABLE {$wpdb->prefix}$table");
    }

    public function optimize($table) {
        global $wpdb;
        return $wpdb->query("OPTIMIZE TABLE {$wpdb->prefix}$table");
    }

    public function begin_transaction() {
        global $wpdb;
        return $wpdb->query('START TRANSACTION');
    }

    public function commit() {
        global $wpdb;
        return $wpdb->query('COMMIT');
    }

    public function rollback() {
        global $wpdb;
        return $wpdb->query('ROLLBACK');
    }
}
/**
 * Class: Content Model
 * Handles all content-related operations
 */
class MFW_Content_Model extends MFW_Base {
    private $db;
    private $current_time = '2025-05-16 10:06:00';
    private $current_user = 'maziyarid';
    
    public function __construct() {
        parent::__construct();
        $this->db = new MFW_Database_Handler();
    }

    public function create($data) {
        try {
            $defaults = [
                'title' => '',
                'content' => '',
                'source' => '',
                'source_url' => '',
                'status' => 'draft',
                'type' => MFW_CONTENT_TYPE_ARTICLE,
                'author_id' => get_current_user_id(),
                'metadata' => [],
                'created_at' => $this->current_time,
                'updated_at' => $this->current_time,
            ];

            $data = wp_parse_args($data, $defaults);
            
            // Validate data
            $this->validate_content_data($data);

            // Format metadata as JSON
            if (is_array($data['metadata'])) {
                $data['metadata'] = json_encode($data['metadata']);
            }

            // Insert into database
            $result = $this->db->insert(MFW_TABLE_CONTENT, $data, [
                '%s', // title
                '%s', // content
                '%s', // source
                '%s', // source_url
                '%s', // status
                '%s', // type
                '%d', // author_id
                '%s', // metadata
                '%s', // created_at
                '%s', // updated_at
            ]);

            if (!$result) {
                throw new \Exception('Failed to create content');
            }

            global $wpdb;
            $content_id = $wpdb->insert_id;

            // Log creation
            $this->log_info('Content created', [
                'content_id' => $content_id,
                'title' => $data['title'],
                'type' => $data['type'],
                'user' => $this->current_user,
            ]);

            // Trigger hooks
            do_action('mfw_content_created', $content_id, $data);

            return $content_id;

        } catch (\Exception $e) {
            $this->log_error('Content creation failed: ' . $e->getMessage());
            throw $e;
        }
    }

    public function update($id, $data) {
        try {
            // Get existing content
            $existing = $this->get($id);
            if (!$existing) {
                throw new \Exception('Content not found');
            }

            // Update only provided fields
            $update_data = array_merge(
                (array)$existing,
                $data,
                ['updated_at' => $this->current_time]
            );

            // Validate data
            $this->validate_content_data($update_data);

            // Format metadata
            if (isset($update_data['metadata']) && is_array($update_data['metadata'])) {
                $update_data['metadata'] = json_encode($update_data['metadata']);
            }

            // Update database
            $result = $this->db->update(
                MFW_TABLE_CONTENT,
                $update_data,
                ['id' => $id],
                null,
                ['%d']
            );

            if ($result === false) {
                throw new \Exception('Failed to update content');
            }

            // Log update
            $this->log_info('Content updated', [
                'content_id' => $id,
                'title' => $update_data['title'],
                'user' => $this->current_user,
            ]);

            // Trigger hooks
            do_action('mfw_content_updated', $id, $update_data, $existing);

            return true;

        } catch (\Exception $e) {
            $this->log_error('Content update failed: ' . $e->getMessage());
            throw $e;
        }
    }

    public function delete($id) {
        try {
            // Get existing content
            $existing = $this->get($id);
            if (!$existing) {
                throw new \Exception('Content not found');
            }

            // Delete from database
            $result = $this->db->delete(
                MFW_TABLE_CONTENT,
                ['id' => $id],
                ['%d']
            );

            if (!$result) {
                throw new \Exception('Failed to delete content');
            }

            // Log deletion
            $this->log_info('Content deleted', [
                'content_id' => $id,
                'title' => $existing->title,
                'user' => $this->current_user,
            ]);

            // Trigger hooks
            do_action('mfw_content_deleted', $id, $existing);

            return true;

        } catch (\Exception $e) {
            $this->log_error('Content deletion failed: ' . $e->getMessage());
            throw $e;
        }
    }

    public function get($id) {
        $content = $this->db->get_row(MFW_TABLE_CONTENT, ['id' => $id]);
        
        if ($content) {
            // Parse metadata
            if ($content->metadata) {
                $content->metadata = json_decode($content->metadata, true);
            }

            // Add author data
            $content->author = get_userdata($content->author_id);
        }

        return $content;
    }

    public function get_items($args = []) {
        $defaults = [
            'per_page' => 20,
            'page' => 1,
            'orderby' => 'created_at',
            'order' => 'DESC',
            'status' => null,
            'type' => null,
            'author_id' => null,
            'search' => null,
        ];

        $args = wp_parse_args($args, $defaults);

        // Build where clause
        $where = [];
        if ($args['status']) {
            $where['status'] = $args['status'];
        }
        if ($args['type']) {
            $where['type'] = $args['type'];
        }
        if ($args['author_id']) {
            $where['author_id'] = $args['author_id'];
        }

        // Calculate offset
        $offset = ($args['page'] - 1) * $args['per_page'];

        // Get items
        $items = $this->db->get_results(MFW_TABLE_CONTENT, [
            'where' => $where,
            'orderby' => $args['orderby'],
            'order' => $args['order'],
            'limit' => $args['per_page'],
            'offset' => $offset,
        ]);

        // Process items
        foreach ($items as &$item) {
            if ($item->metadata) {
                $item->metadata = json_decode($item->metadata, true);
            }
            $item->author = get_userdata($item->author_id);
        }

        // Get total count for pagination
        $total = $this->db->count(MFW_TABLE_CONTENT, $where);

        return [
            'items' => $items,
            'total' => $total,
            'pages' => ceil($total / $args['per_page']),
            'current_page' => $args['page'],
        ];
    }

    private function validate_content_data($data) {
        $required = ['title', 'content', 'type'];
        
        foreach ($required as $field) {
            if (empty($data[$field])) {
                throw new \Exception("$field is required");
            }
        }

        // Validate content type
        $valid_types = [
            MFW_CONTENT_TYPE_ARTICLE,
            MFW_CONTENT_TYPE_PRODUCT,
            MFW_CONTENT_TYPE_NEWS,
            MFW_CONTENT_TYPE_BLOG,
        ];

        if (!in_array($data['type'], $valid_types)) {
            throw new \Exception('Invalid content type');
        }

        // Validate status
        $valid_statuses = ['draft', 'published', 'pending', 'private'];
        if (!empty($data['status']) && !in_array($data['status'], $valid_statuses)) {
            throw new \Exception('Invalid content status');
        }

        return true;
    }
}
/**
 * Class: View Engine
 * Handles template rendering and view composition
 */
class MFW_View_Engine extends MFW_Base {
    private $current_time = '2025-05-16 10:07:56';
    private $current_user = 'maziyarid';
    private $views_path;
    private $cache_path;
    private $data = [];

    public function __construct() {
        parent::__construct();
        $this->views_path = MFW_VIEWS_PATH;
        $this->cache_path = MFW_CACHE_DIR . '/views';
        
        if (!file_exists($this->cache_path)) {
            wp_mkdir_p($this->cache_path);
        }
    }

    public function render($view, $data = [], $return = false) {
        try {
            $this->data = array_merge($this->data, $data, [
                'current_time' => $this->current_time,
                'current_user' => $this->current_user,
            ]);

            $view_path = $this->resolve_view_path($view);
            if (!file_exists($view_path)) {
                throw new \Exception("View not found: $view");
            }

            // Start output buffering
            ob_start();

            // Extract data to make it available in the view
            extract($this->data);

            // Include the view file
            include $view_path;

            // Get the contents
            $content = ob_get_clean();

            // Process any nested views
            $content = $this->process_includes($content);

            // Process any dynamic content
            $content = $this->process_dynamic_content($content);

            if ($return) {
                return $content;
            }

            echo $content;

        } catch (\Exception $e) {
            $this->log_error('View rendering failed: ' . $e->getMessage());
            throw $e;
        }
    }

    private function resolve_view_path($view) {
        // Convert dot notation to directory structure
        $view = str_replace('.', '/', $view);
        
        // Add .php extension if not present
        if (!preg_match('/\.php$/', $view)) {
            $view .= '.php';
        }

        return $this->views_path . $view;
    }

    private function process_includes($content) {
        return preg_replace_callback(
            '/@include\([\'"]([^\'"]+)[\'"]\s*(,\s*([^)]+))?\)/',
            function($matches) {
                $view = $matches[1];
                $data = isset($matches[3]) ? eval('return ' . $matches[3] . ';') : [];
                return $this->render($view, $data, true);
            },
            $content
        );
    }

    private function process_dynamic_content($content) {
        // Process conditionals
        $content = preg_replace_callback(
            '/@if\((.*?)\)(.*?)(@endif|@else(.*?)@endif)/s',
            function($matches) {
                $condition = $matches[1];
                $if_content = $matches[2];
                $else_content = isset($matches[4]) ? $matches[4] : '';

                return eval("return $condition ? '$if_content' : '$else_content';");
            },
            $content
        );

        // Process loops
        $content = preg_replace_callback(
            '/@foreach\((.*?) as (.*?)\)(.*?)@endforeach/s',
            function($matches) {
                $array = eval("return $matches[1];");
                $iterator = $matches[2];
                $loop_content = $matches[3];
                
                $output = '';
                foreach ($array as $key => $value) {
                    $loop_content_processed = str_replace(
                        ['$' . $iterator, '$loop->index', '$loop->key'],
                        [$value, $key, $key],
                        $loop_content
                    );
                    $output .= $loop_content_processed;
                }
                return $output;
            },
            $content
        );

        return $content;
    }
}

/**
 * Implementation of admin dashboard view template
 */
<div class="wrap mfw-dashboard">
    <h1><?php _e('MFW Dashboard', 'mfw'); ?></h1>

    <div class="mfw-dashboard-stats">
        <div class="stat-box">
            <h3><?php _e('Content Statistics', 'mfw'); ?></h3>
            <div class="stat-grid">
                <div class="stat-item">
                    <span class="stat-label"><?php _e('Total Content', 'mfw'); ?></span>
                    <span class="stat-value"><?php echo esc_html($stats['total_content']); ?></span>
                </div>
                <div class="stat-item">
                    <span class="stat-label"><?php _e('Published', 'mfw'); ?></span>
                    <span class="stat-value"><?php echo esc_html($stats['published_content']); ?></span>
                </div>
                <div class="stat-item">
                    <span class="stat-label"><?php _e('Drafts', 'mfw'); ?></span>
                    <span class="stat-value"><?php echo esc_html($stats['draft_content']); ?></span>
                </div>
                <div class="stat-item">
                    <span class="stat-label"><?php _e('This Month', 'mfw'); ?></span>
                    <span class="stat-value"><?php echo esc_html($stats['monthly_content']); ?></span>
                </div>
            </div>
        </div>

        <div class="stat-box">
            <h3><?php _e('AI Usage', 'mfw'); ?></h3>
            <div class="stat-grid">
                <div class="stat-item">
                    <span class="stat-label"><?php _e('Total Requests', 'mfw'); ?></span>
                    <span class="stat-value"><?php echo esc_html($stats['ai_requests']); ?></span>
                </div>
                <div class="stat-item">
                    <span class="stat-label"><?php _e('Tokens Used', 'mfw'); ?></span>
                    <span class="stat-value"><?php echo esc_html(number_format($stats['ai_tokens'])); ?></span>
                </div>
                <div class="stat-item">
                    <span class="stat-label"><?php _e('Cost', 'mfw'); ?></span>
                    <span class="stat-value">$<?php echo esc_html(number_format($stats['ai_cost'], 2)); ?></span>
                </div>
                <div class="stat-item">
                    <span class="stat-label"><?php _e('Success Rate', 'mfw'); ?></span>
                    <span class="stat-value"><?php echo esc_html($stats['ai_success_rate']); ?>%</span>
                </div>
            </div>
        </div>

        <div class="stat-box">
            <h3><?php _e('Fetcher Statistics', 'mfw'); ?></h3>
            <div class="stat-grid">
                <?php foreach ($stats['fetchers'] as $fetcher => $fetcher_stats): ?>
                <div class="stat-item">
                    <span class="stat-label"><?php echo esc_html(ucfirst($fetcher)); ?></span>
                    <span class="stat-value"><?php echo esc_html($fetcher_stats['count']); ?></span>
                    <span class="stat-meta"><?php echo esc_html($fetcher_stats['last_fetch']); ?></span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <div class="mfw-dashboard-charts">
        <div class="chart-box">
            <h3><?php _e('Content Creation Trend', 'mfw'); ?></h3>
            <canvas id="contentTrendChart"></canvas>
        </div>

        <div class="chart-box">
            <h3><?php _e('AI Service Usage', 'mfw'); ?></h3>
            <canvas id="aiUsageChart"></canvas>
        </div>
    </div>

    <div class="mfw-dashboard-recent">
        <h3><?php _e('Recent Activity', 'mfw'); ?></h3>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?php _e('Time', 'mfw'); ?></th>
                    <th><?php _e('Activity', 'mfw'); ?></th>
                    <th><?php _e('User', 'mfw'); ?></th>
                    <th><?php _e('Details', 'mfw'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($stats['recent_activity'] as $activity): ?>
                <tr>
                    <td><?php echo esc_html($activity['time']); ?></td>
                    <td><?php echo esc_html($activity['action']); ?></td>
                    <td><?php echo esc_html($activity['user']); ?></td>
                    <td><?php echo esc_html($activity['details']); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
/**
 * Class: REST API Controller
 * Handles all REST API endpoints
 */
class MFW_REST_Controller extends WP_REST_Controller {
    private $current_time = '2025-05-16 10:09:28';
    private $current_user = 'maziyarid';
    private $namespace = 'mfw/v1';
    private $content_model;
    private $analytics;

    public function __construct() {
        $this->content_model = new MFW_Content_Model();
        $this->analytics = new MFW_Analytics();
    }

    public function register_routes() {
        // Content endpoints
        register_rest_route($this->namespace, '/content', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'get_content_items'],
                'permission_callback' => [$this, 'check_permission'],
                'args' => $this->get_content_collection_params(),
            ],
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'create_content_item'],
                'permission_callback' => [$this, 'check_permission'],
                'args' => $this->get_content_schema(),
            ],
        ]);

        register_rest_route($this->namespace, '/content/(?P<id>[\d]+)', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'get_content_item'],
                'permission_callback' => [$this, 'check_permission'],
                'args' => ['id' => ['required' => true]],
            ],
            [
                'methods' => WP_REST_Server::EDITABLE,
                'callback' => [$this, 'update_content_item'],
                'permission_callback' => [$this, 'check_permission'],
                'args' => array_merge(
                    ['id' => ['required' => true]],
                    $this->get_content_schema()
                ),
            ],
            [
                'methods' => WP_REST_Server::DELETABLE,
                'callback' => [$this, 'delete_content_item'],
                'permission_callback' => [$this, 'check_permission'],
                'args' => ['id' => ['required' => true]],
            ],
        ]);

        // AI Generation endpoints
        register_rest_route($this->namespace, '/generate', [
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'generate_content'],
                'permission_callback' => [$this, 'check_permission'],
                'args' => $this->get_generation_schema(),
            ],
        ]);

        // Fetcher endpoints
        register_rest_route($this->namespace, '/fetch/(?P<source>[a-zA-Z0-9_-]+)', [
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'fetch_content'],
                'permission_callback' => [$this, 'check_permission'],
                'args' => $this->get_fetch_schema(),
            ],
        ]);

        // Analytics endpoints
        register_rest_route($this->namespace, '/analytics', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'get_analytics'],
                'permission_callback' => [$this, 'check_permission'],
                'args' => $this->get_analytics_params(),
            ],
        ]);
    }

    public function check_permission($request) {
        return current_user_can('manage_options');
    }

    public function get_content_items($request) {
        try {
            $args = [
                'per_page' => $request['per_page'],
                'page' => $request['page'],
                'orderby' => $request['orderby'],
                'order' => $request['order'],
                'status' => $request['status'],
                'type' => $request['type'],
                'author_id' => $request['author_id'],
                'search' => $request['search'],
            ];

            $result = $this->content_model->get_items($args);

            return new WP_REST_Response([
                'success' => true,
                'data' => $result,
            ], 200);

        } catch (\Exception $e) {
            return new WP_REST_Response([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function create_content_item($request) {
        try {
            $data = $this->prepare_content_data($request);
            $content_id = $this->content_model->create($data);

            return new WP_REST_Response([
                'success' => true,
                'data' => [
                    'id' => $content_id,
                    'message' => __('Content created successfully', 'mfw'),
                ],
            ], 201);

        } catch (\Exception $e) {
            return new WP_REST_Response([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function update_content_item($request) {
        try {
            $data = $this->prepare_content_data($request);
            $this->content_model->update($request['id'], $data);

            return new WP_REST_Response([
                'success' => true,
                'message' => __('Content updated successfully', 'mfw'),
            ], 200);

        } catch (\Exception $e) {
            return new WP_REST_Response([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function delete_content_item($request) {
        try {
            $this->content_model->delete($request['id']);

            return new WP_REST_Response([
                'success' => true,
                'message' => __('Content deleted successfully', 'mfw'),
            ], 200);

        } catch (\Exception $e) {
            return new WP_REST_Response([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function generate_content($request) {
        try {
            $ai_service = MFW_AI_Service_Factory::create(
                get_option('mfw_ai_service', MFW_AI_DEFAULT)
            );

            $content = $ai_service->generate_content(
                $request['prompt'],
                $request['options'] ?? []
            );

            return new WP_REST_Response([
                'success' => true,
                'data' => $content,
            ], 200);

        } catch (\Exception $e) {
            return new WP_REST_Response([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function fetch_content($request) {
        try {
            $fetcher = MFW_Fetcher_Factory::create($request['source']);
            
            $items = $fetcher->fetch_items(
                $request['query'],
                $request['options'] ?? []
            );

            return new WP_REST_Response([
                'success' => true,
                'data' => $items,
            ], 200);

        } catch (\Exception $e) {
            return new WP_REST_Response([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function get_analytics($request) {
        try {
            $data = $this->analytics->get_analytics_data(
                $request['period'],
                $request['type']
            );

            return new WP_REST_Response([
                'success' => true,
                'data' => $data,
            ], 200);

        } catch (\Exception $e) {
            return new WP_REST_Response([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    private function prepare_content_data($request) {
        return [
            'title' => sanitize_text_field($request['title']),
            'content' => wp_kses_post($request['content']),
            'source' => sanitize_text_field($request['source']),
            'source_url' => esc_url_raw($request['source_url']),
            'status' => sanitize_text_field($request['status']),
            'type' => sanitize_text_field($request['type']),
            'metadata' => $request['metadata'],
        ];
    }
/**
 * REST API Schema Definitions
 * Continuation of MFW_REST_Controller class
 */

    private function get_content_schema() {
        return [
            'title' => [
                'required' => true,
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field',
                'description' => __('The title of the content item.', 'mfw'),
            ],
            'content' => [
                'required' => true,
                'type' => 'string',
                'sanitize_callback' => 'wp_kses_post',
                'description' => __('The main content of the item.', 'mfw'),
            ],
            'source' => [
                'required' => false,
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field',
                'description' => __('The source of the content.', 'mfw'),
            ],
            'source_url' => [
                'required' => false,
                'type' => 'string',
                'sanitize_callback' => 'esc_url_raw',
                'description' => __('The URL of the content source.', 'mfw'),
            ],
            'status' => [
                'required' => false,
                'type' => 'string',
                'enum' => ['draft', 'published', 'pending', 'private'],
                'default' => 'draft',
                'description' => __('The status of the content.', 'mfw'),
            ],
            'type' => [
                'required' => false,
                'type' => 'string',
                'enum' => [
                    MFW_CONTENT_TYPE_ARTICLE,
                    MFW_CONTENT_TYPE_PRODUCT,
                    MFW_CONTENT_TYPE_NEWS,
                    MFW_CONTENT_TYPE_BLOG,
                ],
                'default' => MFW_CONTENT_TYPE_ARTICLE,
                'description' => __('The type of content.', 'mfw'),
            ],
            'metadata' => [
                'required' => false,
                'type' => 'object',
                'default' => [],
                'description' => __('Additional metadata for the content.', 'mfw'),
            ],
        ];
    }

    private function get_content_collection_params() {
        return [
            'page' => [
                'required' => false,
                'type' => 'integer',
                'default' => 1,
                'minimum' => 1,
                'description' => __('Current page of the collection.', 'mfw'),
            ],
            'per_page' => [
                'required' => false,
                'type' => 'integer',
                'default' => 20,
                'minimum' => 1,
                'maximum' => 100,
                'description' => __('Maximum number of items to be returned in result set.', 'mfw'),
            ],
            'orderby' => [
                'required' => false,
                'type' => 'string',
                'enum' => ['id', 'title', 'created_at', 'updated_at'],
                'default' => 'created_at',
                'description' => __('Sort collection by parameter.', 'mfw'),
            ],
            'order' => [
                'required' => false,
                'type' => 'string',
                'enum' => ['asc', 'desc'],
                'default' => 'desc',
                'description' => __('Order sort parameter ascending or descending.', 'mfw'),
            ],
            'status' => [
                'required' => false,
                'type' => 'string',
                'enum' => ['draft', 'published', 'pending', 'private', 'any'],
                'default' => 'any',
                'description' => __('Limit result set to content with specific status.', 'mfw'),
            ],
            'type' => [
                'required' => false,
                'type' => 'string',
                'enum' => [
                    MFW_CONTENT_TYPE_ARTICLE,
                    MFW_CONTENT_TYPE_PRODUCT,
                    MFW_CONTENT_TYPE_NEWS,
                    MFW_CONTENT_TYPE_BLOG,
                    'any',
                ],
                'default' => 'any',
                'description' => __('Limit result set to content with specific type.', 'mfw'),
            ],
            'author_id' => [
                'required' => false,
                'type' => 'integer',
                'description' => __('Limit result set to content created by specific author.', 'mfw'),
            ],
            'search' => [
                'required' => false,
                'type' => 'string',
                'description' => __('Limit result set to content matching search term.', 'mfw'),
            ],
        ];
    }

    private function get_generation_schema() {
        return [
            'prompt' => [
                'required' => true,
                'type' => 'string',
                'description' => __('The prompt for content generation.', 'mfw'),
            ],
            'options' => [
                'required' => false,
                'type' => 'object',
                'default' => [],
                'properties' => [
                    'model' => [
                        'type' => 'string',
                        'default' => 'gpt-4',
                    ],
                    'temperature' => [
                        'type' => 'number',
                        'minimum' => 0,
                        'maximum' => 1,
                        'default' => 0.7,
                    ],
                    'max_tokens' => [
                        'type' => 'integer',
                        'minimum' => 1,
                        'maximum' => 4000,
                        'default' => 2000,
                    ],
                    'tone' => [
                        'type' => 'string',
                        'enum' => ['professional', 'casual', 'formal', 'friendly'],
                        'default' => 'professional',
                    ],
                ],
                'description' => __('Generation options.', 'mfw'),
            ],
        ];
    }

    private function get_fetch_schema() {
        return [
            'query' => [
                'required' => true,
                'type' => 'string',
                'description' => __('The search query or URL to fetch content from.', 'mfw'),
            ],
            'options' => [
                'required' => false,
                'type' => 'object',
                'default' => [],
                'properties' => [
                    'max_items' => [
                        'type' => 'integer',
                        'minimum' => 1,
                        'maximum' => 100,
                        'default' => 20,
                    ],
                    'language' => [
                        'type' => 'string',
                        'default' => 'en',
                    ],
                    'cache' => [
                        'type' => 'boolean',
                        'default' => true,
                    ],
                ],
                'description' => __('Fetching options.', 'mfw'),
            ],
        ];
    }

    private function get_analytics_params() {
        return [
            'period' => [
                'required' => false,
                'type' => 'string',
                'enum' => ['today', 'week', 'month', 'year', 'custom'],
                'default' => 'month',
                'description' => __('The time period for analytics data.', 'mfw'),
            ],
            'start_date' => [
                'required' => false,
                'type' => 'string',
                'format' => 'date',
                'description' => __('Start date for custom period.', 'mfw'),
            ],
            'end_date' => [
                'required' => false,
                'type' => 'string',
                'format' => 'date',
                'description' => __('End date for custom period.', 'mfw'),
            ],
            'type' => [
                'required' => false,
                'type' => 'string',
                'enum' => ['content', 'ai', 'fetchers', 'all'],
                'default' => 'all',
                'description' => __('Type of analytics data to retrieve.', 'mfw'),
            ],
        ];
    }
}
/**
 * Class: Queue System
 * Handles asynchronous job processing
 */
class MFW_Queue_System extends MFW_Base {
    private $current_time = '2025-05-16 10:12:48';
    private $current_user = 'maziyarid';
    private $db;
    private $batch_size = 50;
    private $max_attempts = 3;
    private $retry_delay = 300; // 5 minutes

    public function __construct() {
        parent::__construct();
        $this->db = new MFW_Database_Handler();
        
        // Register cron event
        add_action('mfw_process_queue', [$this, 'process_queue']);
        
        // Schedule cron if not already scheduled
        if (!wp_next_scheduled('mfw_process_queue')) {
            wp_schedule_event(time(), 'every_minute', 'mfw_process_queue');
        }
    }

    public function push($job_type, $payload, $options = []) {
        try {
            $defaults = [
                'priority' => 0,
                'delay' => 0,
                'unique' => false,
            ];

            $options = wp_parse_args($options, $defaults);

            // Check for unique job
            if ($options['unique']) {
                $existing = $this->db->get_row(MFW_TABLE_QUEUE, [
                    'job_type' => $job_type,
                    'payload' => json_encode($payload),
                ]);

                if ($existing) {
                    return $existing->id;
                }
            }

            $available_at = date('Y-m-d H:i:s', 
                strtotime($this->current_time) + $options['delay']
            );

            $job_id = $this->db->insert(
                MFW_TABLE_QUEUE,
                [
                    'job_type' => $job_type,
                    'payload' => json_encode($payload),
                    'priority' => $options['priority'],
                    'attempts' => 0,
                    'available_at' => $available_at,
                    'created_at' => $this->current_time,
                ],
                [
                    '%s', // job_type
                    '%s', // payload
                    '%d', // priority
                    '%d', // attempts
                    '%s', // available_at
                    '%s', // created_at
                ]
            );

            if (!$job_id) {
                throw new \Exception('Failed to push job to queue');
            }

            $this->log_info('Job pushed to queue', [
                'job_id' => $job_id,
                'job_type' => $job_type,
                'priority' => $options['priority'],
                'delay' => $options['delay'],
            ]);

            return $job_id;

        } catch (\Exception $e) {
            $this->log_error('Failed to push job: ' . $e->getMessage());
            throw $e;
        }
    }

    public function process_queue() {
        try {
            // Get jobs that are available and not currently being processed
            $jobs = $this->db->get_results(MFW_TABLE_QUEUE, [
                'where' => [
                    'reserved_at' => null,
                    'attempts <' => $this->max_attempts,
                ],
                'orderby' => 'priority DESC, created_at ASC',
                'limit' => $this->batch_size,
            ]);

            if (empty($jobs)) {
                return;
            }

            foreach ($jobs as $job) {
                // Mark job as reserved
                $this->db->update(
                    MFW_TABLE_QUEUE,
                    [
                        'reserved_at' => $this->current_time,
                        'attempts' => $job->attempts + 1,
                    ],
                    ['id' => $job->id]
                );

                try {
                    // Process the job
                    $result = $this->process_job($job);

                    // If successful, remove the job
                    if ($result) {
                        $this->db->delete(MFW_TABLE_QUEUE, ['id' => $job->id]);
                        
                        $this->log_info('Job processed successfully', [
                            'job_id' => $job->id,
                            'job_type' => $job->job_type,
                            'attempts' => $job->attempts + 1,
                        ]);
                    }

                } catch (\Exception $e) {
                    $this->handle_job_failure($job, $e);
                }
            }

        } catch (\Exception $e) {
            $this->log_error('Queue processing failed: ' . $e->getMessage());
        }
    }

    private function process_job($job) {
        $payload = json_decode($job->payload, true);

        switch ($job->job_type) {
            case 'generate_content':
                return $this->process_content_generation($payload);

            case 'fetch_content':
                return $this->process_content_fetching($payload);

            case 'optimize_images':
                return $this->process_image_optimization($payload);

            case 'update_analytics':
                return $this->process_analytics_update($payload);

            default:
                // Allow external job handlers through filters
                $result = apply_filters(
                    'mfw_process_queue_job', 
                    false, 
                    $job->job_type, 
                    $payload
                );

                if ($result === false) {
                    throw new \Exception("Unknown job type: {$job->job_type}");
                }

                return $result;
        }
    }

    private function handle_job_failure($job, $exception) {
        $this->log_error('Job processing failed', [
            'job_id' => $job->id,
            'job_type' => $job->job_type,
            'attempts' => $job->attempts + 1,
            'error' => $exception->getMessage(),
        ]);

        if ($job->attempts + 1 >= $this->max_attempts) {
            // Move to failed jobs log
            $this->log_failed_job($job, $exception);
            
            // Remove from queue
            $this->db->delete(MFW_TABLE_QUEUE, ['id' => $job->id]);
        } else {
            // Reset reservation and set next attempt time
            $this->db->update(
                MFW_TABLE_QUEUE,
                [
                    'reserved_at' => null,
                    'available_at' => date('Y-m-d H:i:s', 
                        strtotime($this->current_time) + $this->retry_delay
                    ),
                ],
                ['id' => $job->id]
            );
        }
    }

    private function log_failed_job($job, $exception) {
        $this->db->insert(
            MFW_TABLE_LOGS,
            [
                'level' => 'error',
                'message' => 'Job failed after max attempts',
                'context' => json_encode([
                    'job_id' => $job->id,
                    'job_type' => $job->job_type,
                    'payload' => $job->payload,
                    'error' => $exception->getMessage(),
                    'stack_trace' => $exception->getTraceAsString(),
                ]),
                'component' => 'queue',
                'created_at' => $this->current_time,
                'user_id' => get_current_user_id(),
            ]
        );
    }

    // Job processing methods
    private function process_content_generation($payload) {
        $ai_service = MFW_AI_Service_Factory::create(
            get_option('mfw_ai_service', MFW_AI_DEFAULT)
        );

        $content = $ai_service->generate_content(
            $payload['prompt'],
            $payload['options'] ?? []
        );

        if (empty($content)) {
            throw new \Exception('Content generation failed: Empty response');
        }

        // Create content item if requested
        if (!empty($payload['create_content'])) {
            $content_model = new MFW_Content_Model();
            $content_model->create([
                'title' => $payload['title'] ?? '',
                'content' => $content,
                'type' => $payload['type'] ?? MFW_CONTENT_TYPE_ARTICLE,
                'status' => 'draft',
                'metadata' => [
                    'generated_by' => 'ai',
                    'prompt' => $payload['prompt'],
                    'options' => $payload['options'] ?? [],
                ],
            ]);
        }

        return true;
    }
/**
 * Queue System - Continued
 * Additional processing methods
 */
    private function process_content_fetching($payload) {
        try {
            $fetcher = MFW_Fetcher_Factory::create($payload['source']);
            
            $items = $fetcher->fetch_items(
                $payload['query'],
                $payload['options'] ?? []
            );

            if (empty($items)) {
                $this->log_warning('No items fetched', [
                    'source' => $payload['source'],
                    'query' => $payload['query'],
                ]);
                return true; // Consider empty results as success
            }

            // Process fetched items
            $content_model = new MFW_Content_Model();
            $processed = 0;

            foreach ($items as $item) {
                try {
                    // Skip if duplicate content detection is enabled and content exists
                    if (!empty($payload['check_duplicates']) && 
                        $this->is_duplicate_content($item)) {
                        continue;
                    }

                    $content_data = $this->prepare_fetched_content($item);
                    
                    // Apply content filters if specified
                    if (!empty($payload['content_filters'])) {
                        $content_data = $this->apply_content_filters(
                            $content_data,
                            $payload['content_filters']
                        );
                    }

                    // Create content item
                    $content_model->create($content_data);
                    $processed++;

                } catch (\Exception $e) {
                    $this->log_error('Failed to process fetched item', [
                        'error' => $e->getMessage(),
                        'item' => $item,
                    ]);
                }
            }

            $this->log_info('Content fetching completed', [
                'source' => $payload['source'],
                'total_items' => count($items),
                'processed_items' => $processed,
            ]);

            return true;

        } catch (\Exception $e) {
            $this->log_error('Content fetching failed', [
                'error' => $e->getMessage(),
                'payload' => $payload,
            ]);
            throw $e;
        }
    }

    private function process_image_optimization($payload) {
        try {
            $image_processor = new MFW_Image_Processor();
            
            // Handle single image or batch
            $images = is_array($payload['images']) ? 
                $payload['images'] : [$payload['images']];

            $processed = 0;
            $failed = 0;

            foreach ($images as $image) {
                try {
                    $result = $image_processor->optimize(
                        $image['path'],
                        $payload['options'] ?? []
                    );

                    if ($result) {
                        $processed++;
                        
                        // Update image metadata if it's a media library item
                        if (!empty($image['attachment_id'])) {
                            update_post_meta(
                                $image['attachment_id'],
                                '_mfw_optimized',
                                $this->current_time
                            );
                        }
                    } else {
                        $failed++;
                    }

                } catch (\Exception $e) {
                    $failed++;
                    $this->log_error('Image optimization failed', [
                        'error' => $e->getMessage(),
                        'image' => $image,
                    ]);
                }
            }

            $this->log_info('Image optimization batch completed', [
                'total' => count($images),
                'processed' => $processed,
                'failed' => $failed,
            ]);

            return true;

        } catch (\Exception $e) {
            $this->log_error('Image optimization job failed', [
                'error' => $e->getMessage(),
                'payload' => $payload,
            ]);
            throw $e;
        }
    }

    private function process_analytics_update($payload) {
        try {
            $analytics = new MFW_Analytics();
            
            switch ($payload['type']) {
                case 'daily_summary':
                    $analytics->generate_daily_summary(
                        $payload['date'] ?? $this->current_time
                    );
                    break;

                case 'content_metrics':
                    $analytics->update_content_metrics(
                        $payload['content_id'],
                        $payload['metrics']
                    );
                    break;

                case 'ai_usage':
                    $analytics->record_ai_usage(
                        $payload['service'],
                        $payload['tokens'],
                        $payload['cost']
                    );
                    break;

                case 'performance_metrics':
                    $analytics->update_performance_metrics(
                        $payload['metrics']
                    );
                    break;

                default:
                    throw new \Exception("Unknown analytics type: {$payload['type']}");
            }

            return true;

        } catch (\Exception $e) {
            $this->log_error('Analytics update failed', [
                'error' => $e->getMessage(),
                'payload' => $payload,
            ]);
            throw $e;
        }
    }

    private function is_duplicate_content($item) {
        global $wpdb;

        // Check by source URL if available
        if (!empty($item['source_url'])) {
            $exists = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}" . MFW_TABLE_CONTENT . "
                WHERE source_url = %s",
                $item['source_url']
            ));

            if ($exists) {
                return true;
            }
        }

        // Check by title similarity
        if (!empty($item['title'])) {
            $similar = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}" . MFW_TABLE_CONTENT . "
                WHERE MATCH(title) AGAINST(%s IN BOOLEAN MODE)",
                $item['title']
            ));

            if ($similar) {
                return true;
            }
        }

        return false;
    }

    private function prepare_fetched_content($item) {
        return [
            'title' => $item['title'] ?? '',
            'content' => $item['content'] ?? '',
            'source' => $item['source'] ?? '',
            'source_url' => $item['source_url'] ?? '',
            'status' => 'draft',
            'type' => $this->determine_content_type($item),
            'metadata' => [
                'fetched_at' => $this->current_time,
                'fetched_by' => $this->current_user,
                'original_data' => $item,
            ],
        ];
    }

    private function determine_content_type($item) {
        if (!empty($item['type'])) {
            return $item['type'];
        }

        // Try to infer type from content structure
        if (!empty($item['price'])) {
            return MFW_CONTENT_TYPE_PRODUCT;
        }

        if (!empty($item['published_date']) && 
            strtotime($item['published_date']) > strtotime('-24 hours')) {
            return MFW_CONTENT_TYPE_NEWS;
        }

        return MFW_CONTENT_TYPE_ARTICLE;
    }

    private function apply_content_filters($content_data, $filters) {
        foreach ($filters as $filter => $options) {
            switch ($filter) {
                case 'min_length':
                    if (strlen($content_data['content']) < $options['chars']) {
                        throw new \Exception('Content too short');
                    }
                    break;

                case 'keywords':
                    if (!$this->contains_keywords($content_data['content'], $options['terms'])) {
                        throw new \Exception('Required keywords not found');
                    }
                    break;

                case 'language':
                    if (!$this->verify_language($content_data['content'], $options['lang'])) {
                        throw new \Exception('Content language mismatch');
                    }
                    break;
            }
        }

        return $content_data;
    }
}
/**
 * Class: Cron Jobs Manager
 * Handles scheduled tasks and background processes
 */
class MFW_Cron_Manager extends MFW_Base {
    private $current_time = '2025-05-16 10:15:55';
    private $current_user = 'maziyarid';
    private $queue;

    public function __construct() {
        parent::__construct();
        $this->queue = new MFW_Queue_System();

        // Register cron schedules
        add_filter('cron_schedules', [$this, 'register_schedules']);

        // Register cron hooks
        add_action('mfw_daily_maintenance', [$this, 'daily_maintenance']);
        add_action('mfw_hourly_tasks', [$this, 'hourly_tasks']);
        add_action('mfw_content_scheduling', [$this, 'handle_content_scheduling']);
        add_action('mfw_analytics_update', [$this, 'update_analytics']);
        add_action('mfw_auto_fetch', [$this, 'auto_fetch_content']);

        // Initialize schedules if not already set
        $this->initialize_schedules();
    }

    public function register_schedules($schedules) {
        $schedules['every_minute'] = [
            'interval' => 60,
            'display' => __('Every Minute', 'mfw'),
        ];

        $schedules['every_fifteen_minutes'] = [
            'interval' => 900,
            'display' => __('Every 15 Minutes', 'mfw'),
        ];

        $schedules['twice_daily'] = [
            'interval' => 43200,
            'display' => __('Twice Daily', 'mfw'),
        ];

        return $schedules;
    }

    private function initialize_schedules() {
        $schedules = [
            'mfw_daily_maintenance' => [
                'recurrence' => 'daily',
                'time' => '00:00',
            ],
            'mfw_hourly_tasks' => [
                'recurrence' => 'hourly',
                'time' => 'now',
            ],
            'mfw_content_scheduling' => [
                'recurrence' => 'every_fifteen_minutes',
                'time' => 'now',
            ],
            'mfw_analytics_update' => [
                'recurrence' => 'hourly',
                'time' => 'now',
            ],
            'mfw_auto_fetch' => [
                'recurrence' => 'twice_daily',
                'time' => ['06:00', '18:00'],
            ],
        ];

        foreach ($schedules as $hook => $config) {
            if (!wp_next_scheduled($hook)) {
                $this->schedule_event($hook, $config);
            }
        }
    }

    private function schedule_event($hook, $config) {
        if ($config['time'] === 'now') {
            wp_schedule_event(time(), $config['recurrence'], $hook);
        } elseif (is_array($config['time'])) {
            foreach ($config['time'] as $time) {
                $timestamp = $this->get_next_timestamp($time);
                wp_schedule_event($timestamp, $config['recurrence'], $hook);
            }
        } else {
            $timestamp = $this->get_next_timestamp($config['time']);
            wp_schedule_event($timestamp, $config['recurrence'], $hook);
        }
    }

    private function get_next_timestamp($time) {
        $now = strtotime($this->current_time);
        $scheduled = strtotime(date('Y-m-d ', $now) . $time);
        
        if ($scheduled <= $now) {
            $scheduled = strtotime('+1 day', $scheduled);
        }

        return $scheduled;
    }

    public function daily_maintenance() {
        $this->log_info('Starting daily maintenance');

        try {
            // Clean up expired cache
            $this->cleanup_cache();

            // Optimize database tables
            $this->optimize_tables();

            // Remove old logs
            $this->cleanup_logs();

            // Check system health
            $this->check_system_health();

            $this->log_info('Daily maintenance completed');

        } catch (\Exception $e) {
            $this->log_error('Daily maintenance failed: ' . $e->getMessage());
        }
    }

    public function hourly_tasks() {
        $this->log_info('Starting hourly tasks');

        try {
            // Process failed jobs
            $this->retry_failed_jobs();

            // Update content metrics
            $this->update_content_metrics();

            // Check API quotas
            $this->check_api_quotas();

            $this->log_info('Hourly tasks completed');

        } catch (\Exception $e) {
            $this->log_error('Hourly tasks failed: ' . $e->getMessage());
        }
    }

    public function handle_content_scheduling() {
        $this->log_info('Processing scheduled content');

        try {
            global $wpdb;

            // Get content items scheduled for publishing
            $items = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}" . MFW_TABLE_CONTENT . "
                WHERE status = 'scheduled'
                AND published_at <= %s",
                $this->current_time
            ));

            foreach ($items as $item) {
                try {
                    $content_model = new MFW_Content_Model();
                    $content_model->update($item->id, [
                        'status' => 'published',
                        'published_at' => $this->current_time,
                    ]);

                    do_action('mfw_content_published', $item->id, $item);

                } catch (\Exception $e) {
                    $this->log_error('Failed to publish content', [
                        'content_id' => $item->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            $this->log_info('Content scheduling processed', [
                'processed_items' => count($items),
            ]);

        } catch (\Exception $e) {
            $this->log_error('Content scheduling failed: ' . $e->getMessage());
        }
    }

    public function update_analytics() {
        $this->log_info('Starting analytics update');

        try {
            // Queue analytics jobs
            $this->queue->push('update_analytics', [
                'type' => 'daily_summary',
                'date' => $this->current_time,
            ]);

            $this->queue->push('update_analytics', [
                'type' => 'performance_metrics',
                'metrics' => [
                    'content_count',
                    'ai_usage',
                    'fetch_stats',
                ],
            ]);

            $this->log_info('Analytics update queued');

        } catch (\Exception $e) {
            $this->log_error('Analytics update failed: ' . $e->getMessage());
        }
    }
/**
 * Cron Manager - Auto-fetch Implementation
 * Continuation of MFW_Cron_Manager class
 */
    public function auto_fetch_content() {
        $this->log_info('Starting auto-fetch process');

        try {
            $fetcher_configs = get_option('mfw_auto_fetch_config', []);
            
            if (empty($fetcher_configs)) {
                $this->log_info('No auto-fetch configurations found');
                return;
            }

            foreach ($fetcher_configs as $config) {
                if (!$this->validate_fetch_config($config)) {
                    continue;
                }

                // Check if we're within the configured schedule
                if (!$this->is_scheduled_fetch_time($config)) {
                    continue;
                }

                // Queue fetch job
                $this->queue->push('fetch_content', [
                    'source' => $config['source'],
                    'query' => $config['query'],
                    'options' => array_merge(
                        $config['options'] ?? [],
                        [
                            'check_duplicates' => true,
                            'content_filters' => $config['filters'] ?? [],
                        ]
                    ),
                ], [
                    'priority' => $config['priority'] ?? 0,
                    'unique' => true,
                ]);

                $this->log_info('Auto-fetch job queued', [
                    'source' => $config['source'],
                    'query' => $config['query'],
                ]);
            }

        } catch (\Exception $e) {
            $this->log_error('Auto-fetch process failed: ' . $e->getMessage());
        }
    }

    private function validate_fetch_config($config) {
        $required = ['source', 'query', 'schedule'];

        foreach ($required as $field) {
            if (empty($config[$field])) {
                $this->log_error('Invalid fetch configuration', [
                    'error' => "Missing required field: $field",
                    'config' => $config,
                ]);
                return false;
            }
        }

        // Validate source
        if (!in_array($config['source'], [
            'rss',
            'youtube',
            'amazon',
            'ebay',
            'google_news',
        ])) {
            $this->log_error('Invalid fetch source', [
                'source' => $config['source'],
            ]);
            return false;
        }

        // Validate schedule format
        if (!$this->validate_schedule_format($config['schedule'])) {
            $this->log_error('Invalid schedule format', [
                'schedule' => $config['schedule'],
            ]);
            return false;
        }

        return true;
    }

    private function validate_schedule_format($schedule) {
        if ($schedule === 'hourly' || $schedule === 'daily') {
            return true;
        }

        // Check for time-based schedule (HH:MM format)
        if (preg_match('/^([0-1][0-9]|2[0-3]):[0-5][0-9]$/', $schedule)) {
            return true;
        }

        // Check for cron expression (simplified version)
        if (preg_match('/^[0-9*,\/-]+ [0-9*,\/-]+ [0-9*,\/-]+ [0-9*,\/-]+ [0-9*,\/-]+$/', $schedule)) {
            return true;
        }

        return false;
    }

    private function is_scheduled_fetch_time($config) {
        $current_time = strtotime($this->current_time);
        $schedule = $config['schedule'];

        if ($schedule === 'hourly') {
            return true;
        }

        if ($schedule === 'daily') {
            // Default to midnight if no specific time
            $scheduled_time = strtotime(date('Y-m-d 00:00:00', $current_time));
            return $current_time >= $scheduled_time && 
                   $current_time <= ($scheduled_time + 300); // 5-minute window
        }

        // Handle specific time (HH:MM)
        if (preg_match('/^([0-1][0-9]|2[0-3]):[0-5][0-9]$/', $schedule)) {
            $scheduled_time = strtotime(date('Y-m-d ', $current_time) . $schedule);
            return $current_time >= $scheduled_time && 
                   $current_time <= ($scheduled_time + 300);
        }

        // Handle cron expression
        if (preg_match('/^[0-9*,\/-]+ [0-9*,\/-]+ [0-9*,\/-]+ [0-9*,\/-]+ [0-9*,\/-]+$/', $schedule)) {
            return $this->match_cron_expression($schedule, $current_time);
        }

        return false;
    }

    private function match_cron_expression($expression, $time) {
        list($minute, $hour, $day, $month, $weekday) = explode(' ', $expression);
        
        $time_parts = [
            'i' => date('i', $time),
            'H' => date('H', $time),
            'j' => date('j', $time),
            'n' => date('n', $time),
            'w' => date('w', $time),
        ];

        return $this->match_cron_field($minute, $time_parts['i']) &&
               $this->match_cron_field($hour, $time_parts['H']) &&
               $this->match_cron_field($day, $time_parts['j']) &&
               $this->match_cron_field($month, $time_parts['n']) &&
               $this->match_cron_field($weekday, $time_parts['w']);
    }

    private function match_cron_field($pattern, $value) {
        // Handle asterisk
        if ($pattern === '*') {
            return true;
        }

        // Handle lists
        if (strpos($pattern, ',') !== false) {
            $values = explode(',', $pattern);
            return in_array($value, $values);
        }

        // Handle ranges
        if (strpos($pattern, '-') !== false) {
            list($start, $end) = explode('-', $pattern);
            return $value >= $start && $value <= $end;
        }

        // Handle steps
        if (strpos($pattern, '/') !== false) {
            list($range, $step) = explode('/', $pattern);
            if ($range === '*') {
                return $value % $step === 0;
            }
        }

        // Direct comparison
        return $pattern == $value;
    }

    private function cleanup_cache() {
        global $wpdb;

        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$wpdb->prefix}" . MFW_TABLE_CACHE . "
            WHERE expiration < %s",
            $this->current_time
        ));

        wp_cache_flush();
    }

    private function optimize_tables() {
        global $wpdb;

        $tables = [
            MFW_TABLE_CONTENT,
            MFW_TABLE_LOGS,
            MFW_TABLE_ANALYTICS,
            MFW_TABLE_QUEUE,
            MFW_TABLE_CACHE,
        ];

        foreach ($tables as $table) {
            $wpdb->query("OPTIMIZE TABLE {$wpdb->prefix}$table");
        }
    }

    private function cleanup_logs() {
        global $wpdb;

        $retention_days = get_option('mfw_log_retention_days', 30);
        $cutoff_date = date('Y-m-d H:i:s', strtotime("-$retention_days days"));

        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$wpdb->prefix}" . MFW_TABLE_LOGS . "
            WHERE created_at < %s",
            $cutoff_date
        ));
    }
/**
 * System Health Monitoring
 * Continuation of MFW_Cron_Manager class
 */
    private function check_system_health() {
        $this->log_info('Starting system health check');
        $issues = [];
        $critical_issues = false;

        try {
            // Check disk space
            $disk_usage = $this->check_disk_space();
            if ($disk_usage['percentage'] > 90) {
                $critical_issues = true;
                $issues[] = [
                    'type' => 'critical',
                    'component' => 'disk_space',
                    'message' => sprintf(
                        'Disk space critical: %s%% used (%s free)',
                        $disk_usage['percentage'],
                        size_format($disk_usage['free'])
                    ),
                ];
            }

            // Check database size and performance
            $db_health = $this->check_database_health();
            if (!empty($db_health['issues'])) {
                $issues = array_merge($issues, $db_health['issues']);
                if ($db_health['critical']) {
                    $critical_issues = true;
                }
            }

            // Check API service status
            $api_status = $this->check_api_services();
            foreach ($api_status as $service => $status) {
                if ($status['status'] !== 'operational') {
                    $issues[] = [
                        'type' => $status['critical'] ? 'critical' : 'warning',
                        'component' => "api_$service",
                        'message' => "API service '$service' is {$status['status']}",
                    ];
                    if ($status['critical']) {
                        $critical_issues = true;
                    }
                }
            }

            // Check queue health
            $queue_health = $this->check_queue_health();
            if ($queue_health['failed_jobs'] > 50 || $queue_health['stuck_jobs'] > 10) {
                $issues[] = [
                    'type' => 'warning',
                    'component' => 'queue',
                    'message' => sprintf(
                        'Queue health issues: %d failed jobs, %d stuck jobs',
                        $queue_health['failed_jobs'],
                        $queue_health['stuck_jobs']
                    ),
                ];
            }

            // Check content generation performance
            $content_metrics = $this->check_content_metrics();
            if ($content_metrics['success_rate'] < 80) {
                $issues[] = [
                    'type' => 'warning',
                    'component' => 'content_generation',
                    'message' => sprintf(
                        'Low content generation success rate: %.2f%%',
                        $content_metrics['success_rate']
                    ),
                ];
            }

            // Record health check results
            $this->record_health_check_results($issues, $critical_issues);

            // Send notifications if needed
            if ($critical_issues) {
                $this->notify_critical_issues($issues);
            }

            $this->log_info('System health check completed', [
                'issues_found' => count($issues),
                'critical' => $critical_issues,
            ]);

        } catch (\Exception $e) {
            $this->log_error('System health check failed: ' . $e->getMessage());
            throw $e;
        }
    }

    private function check_disk_space() {
        $upload_dir = wp_upload_dir();
        $total_space = disk_total_space($upload_dir['basedir']);
        $free_space = disk_free_space($upload_dir['basedir']);
        $used_space = $total_space - $free_space;
        
        return [
            'total' => $total_space,
            'used' => $used_space,
            'free' => $free_space,
            'percentage' => round(($used_space / $total_space) * 100, 2),
        ];
    }

    private function check_database_health() {
        global $wpdb;
        $issues = [];
        $critical = false;

        // Check table sizes
        $tables = $wpdb->get_results("SHOW TABLE STATUS");
        $total_size = 0;
        
        foreach ($tables as $table) {
            $size = $table->Data_length + $table->Index_length;
            $total_size += $size;

            if ($size > 1073741824) { // 1GB
                $issues[] = [
                    'type' => 'warning',
                    'component' => 'database_size',
                    'message' => "Table {$table->Name} is large: " . size_format($size),
                ];
            }
        }

        // Check for corrupted tables
        $corrupted = $wpdb->get_results("SELECT * FROM INFORMATION_SCHEMA.TABLES WHERE Table_schema = DATABASE() AND Check_time IS NULL");
        if (!empty($corrupted)) {
            $critical = true;
            $issues[] = [
                'type' => 'critical',
                'component' => 'database_corruption',
                'message' => 'Corrupted tables detected: ' . implode(', ', array_column($corrupted, 'TABLE_NAME')),
            ];
        }

        // Check for slow queries
        $slow_queries = $this->analyze_slow_queries();
        if (!empty($slow_queries)) {
            $issues[] = [
                'type' => 'warning',
                'component' => 'database_performance',
                'message' => 'Slow queries detected: ' . count($slow_queries) . ' queries exceeding threshold',
            ];
        }

        return [
            'issues' => $issues,
            'critical' => $critical,
            'total_size' => $total_size,
            'slow_queries' => $slow_queries,
        ];
    }

    private function analyze_slow_queries() {
        global $wpdb;
        $slow_queries = [];
        
        $log_file = ini_get('slow_query_log_file');
        if (file_exists($log_file)) {
            $content = file_get_contents($log_file);
            preg_match_all('/# Query_time: (\d+\.\d+).*?^(?!#)(.+?)(?=# Time|\Z)/ms', $content, $matches, PREG_SET_ORDER);
            
            foreach ($matches as $match) {
                if (floatval($match[1]) > 1.0) { // Queries taking more than 1 second
                    $slow_queries[] = [
                        'time' => floatval($match[1]),
                        'query' => trim($match[2]),
                    ];
                }
            }
        }

        return $slow_queries;
    }

    private function check_api_services() {
        $services = [
            'openai' => [
                'endpoint' => 'https://api.openai.com/v1/models',
                'timeout' => 5,
            ],
            'gemini' => [
                'endpoint' => 'https://generativelanguage.googleapis.com/v1/models',
                'timeout' => 5,
            ],
            'deepseek' => [
                'endpoint' => 'https://api.deepseek.com/v1/status',
                'timeout' => 5,
            ],
        ];

        $results = [];

        foreach ($services as $service => $config) {
            try {
                $response = wp_remote_get($config['endpoint'], [
                    'timeout' => $config['timeout'],
                    'headers' => $this->get_api_headers($service),
                ]);

                if (is_wp_error($response)) {
                    $results[$service] = [
                        'status' => 'error',
                        'critical' => true,
                        'message' => $response->get_error_message(),
                    ];
                    continue;
                }

                $code = wp_remote_retrieve_response_code($response);
                if ($code !== 200) {
                    $results[$service] = [
                        'status' => 'degraded',
                        'critical' => $code >= 500,
                        'message' => "HTTP $code response",
                    ];
                    continue;
                }

                $results[$service] = [
                    'status' => 'operational',
                    'critical' => false,
                    'latency' => $response['http_response']->get_response_object()->total_time,
                ];

            } catch (\Exception $e) {
                $results[$service] = [
                    'status' => 'unknown',
                    'critical' => true,
                    'message' => $e->getMessage(),
                ];
            }
        }

        return $results;
    }
/**
 * Queue Health and Content Metrics
 * Continuation of MFW_Cron_Manager class
 */
    private function check_queue_health() {
        global $wpdb;
        $current_time = $this->current_time;

        // Check for failed jobs
        $failed_jobs = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}" . MFW_TABLE_QUEUE . "
            WHERE attempts >= %d",
            $this->max_attempts
        ));

        // Check for stuck jobs (reserved but not completed)
        $stuck_threshold = date('Y-m-d H:i:s', 
            strtotime($current_time) - 3600 // 1 hour
        );
        
        $stuck_jobs = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}" . MFW_TABLE_QUEUE . "
            WHERE reserved_at < %s
            AND reserved_at IS NOT NULL",
            $stuck_threshold
        ));

        // Get queue processing rate
        $processed_last_hour = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}" . MFW_TABLE_LOGS . "
            WHERE component = 'queue'
            AND level = 'info'
            AND message = 'Job processed successfully'
            AND created_at > %s",
            date('Y-m-d H:i:s', strtotime($current_time) - 3600)
        ));

        // Calculate average processing time
        $avg_processing_time = $wpdb->get_var($wpdb->prepare(
            "SELECT AVG(TIMESTAMPDIFF(SECOND, reserved_at, updated_at))
            FROM {$wpdb->prefix}" . MFW_TABLE_QUEUE . "
            WHERE updated_at > %s",
            date('Y-m-d H:i:s', strtotime($current_time) - 3600)
        ));

        return [
            'failed_jobs' => (int)$failed_jobs,
            'stuck_jobs' => (int)$stuck_jobs,
            'processed_last_hour' => (int)$processed_last_hour,
            'avg_processing_time' => round($avg_processing_time, 2),
            'queue_size' => $this->get_queue_size(),
            'job_types_distribution' => $this->get_job_types_distribution(),
        ];
    }

    private function get_queue_size() {
        global $wpdb;
        
        return [
            'total' => $wpdb->get_var(
                "SELECT COUNT(*) FROM {$wpdb->prefix}" . MFW_TABLE_QUEUE
            ),
            'pending' => $wpdb->get_var(
                "SELECT COUNT(*) FROM {$wpdb->prefix}" . MFW_TABLE_QUEUE . "
                WHERE reserved_at IS NULL"
            ),
            'processing' => $wpdb->get_var(
                "SELECT COUNT(*) FROM {$wpdb->prefix}" . MFW_TABLE_QUEUE . "
                WHERE reserved_at IS NOT NULL"
            ),
        ];
    }

    private function get_job_types_distribution() {
        global $wpdb;
        
        $results = $wpdb->get_results(
            "SELECT job_type, COUNT(*) as count
            FROM {$wpdb->prefix}" . MFW_TABLE_QUEUE . "
            GROUP BY job_type"
        );

        $distribution = [];
        foreach ($results as $row) {
            $distribution[$row->job_type] = (int)$row->count;
        }

        return $distribution;
    }

    private function check_content_metrics() {
        global $wpdb;
        $current_time = $this->current_time;
        $last_24h = date('Y-m-d H:i:s', strtotime($current_time) - 86400);

        // Content generation success rate
        $total_generations = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}" . MFW_TABLE_LOGS . "
            WHERE component = 'ai_generation'
            AND created_at > %s",
            $last_24h
        ));

        $successful_generations = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}" . MFW_TABLE_LOGS . "
            WHERE component = 'ai_generation'
            AND level = 'info'
            AND created_at > %s",
            $last_24h
        ));

        $success_rate = $total_generations > 0 ? 
            ($successful_generations / $total_generations) * 100 : 100;

        // Content quality metrics
        $quality_metrics = $this->analyze_content_quality();

        // Content engagement metrics
        $engagement_metrics = $this->get_content_engagement();

        // Content production velocity
        $velocity = $this->calculate_content_velocity();

        return [
            'success_rate' => round($success_rate, 2),
            'quality_metrics' => $quality_metrics,
            'engagement_metrics' => $engagement_metrics,
            'velocity' => $velocity,
            'generation_stats' => [
                'total' => $total_generations,
                'successful' => $successful_generations,
                'failed' => $total_generations - $successful_generations,
            ],
        ];
    }

    private function analyze_content_quality() {
        global $wpdb;
        $current_time = $this->current_time;
        $last_24h = date('Y-m-d H:i:s', strtotime($current_time) - 86400);

        // Get recent content
        $content_items = $wpdb->get_results($wpdb->prepare(
            "SELECT id, content, metadata FROM {$wpdb->prefix}" . MFW_TABLE_CONTENT . "
            WHERE created_at > %s",
            $last_24h
        ));

        $metrics = [
            'avg_length' => 0,
            'keyword_density' => 0,
            'readability_score' => 0,
            'plagiarism_score' => 0,
        ];

        if (empty($content_items)) {
            return $metrics;
        }

        $total_length = 0;
        $total_readability = 0;
        $total_plagiarism = 0;

        foreach ($content_items as $item) {
            // Calculate content length
            $length = str_word_count(strip_tags($item->content));
            $total_length += $length;

            // Calculate readability score
            $readability = $this->calculate_readability_score($item->content);
            $total_readability += $readability;

            // Get plagiarism score from metadata if available
            $metadata = json_decode($item->metadata, true);
            if (isset($metadata['plagiarism_score'])) {
                $total_plagiarism += $metadata['plagiarism_score'];
            }
        }

        $count = count($content_items);
        
        return [
            'avg_length' => round($total_length / $count),
            'avg_readability' => round($total_readability / $count, 2),
            'avg_plagiarism' => round($total_plagiarism / $count, 2),
            'samples_analyzed' => $count,
        ];
    }

    private function calculate_readability_score($content) {
        // Strip HTML and decode entities
        $text = strip_tags(html_entity_decode($content));
        
        // Count sentences
        $sentences = preg_split('/[.!?]+/', $text);
        $sentence_count = count(array_filter($sentences));

        // Count words
        $words = str_word_count($text);
        
        // Count syllables (simplified method)
        $syllables = $this->count_syllables($text);

        // Calculate Flesch-Kincaid Reading Ease
        if ($sentence_count > 0 && $words > 0) {
            return 206.835 - 1.015 * ($words / $sentence_count) 
                         - 84.6 * ($syllables / $words);
        }

        return 0;
    }

    private function count_syllables($text) {
        $text = strtolower($text);
        $text = preg_replace('/[^a-z]/', ' ', $text);
        $words = explode(' ', $text);
        $words = array_filter($words);
        
        $syllables = 0;
        foreach ($words as $word) {
            $syllables += max(1, preg_match_all('/[aeiouy]{1,2}/', $word));
        }
        
        return $syllables;
    }
/**
 * Content Engagement Metrics and Notification System
 * Continuation of MFW_Cron_Manager class
 */
    private function get_content_engagement() {
        global $wpdb;
        $current_time = '2025-05-16 10:22:00';
        $last_30d = date('Y-m-d H:i:s', strtotime($current_time) - (30 * 86400));

        // Get engagement metrics for the last 30 days
        $metrics = $wpdb->get_results($wpdb->prepare("
            SELECT 
                c.id,
                c.title,
                c.created_at,
                COALESCE(m.view_count, 0) as views,
                COALESCE(m.share_count, 0) as shares,
                COALESCE(m.comment_count, 0) as comments,
                COALESCE(m.conversion_rate, 0) as conversion_rate
            FROM {$wpdb->prefix}" . MFW_TABLE_CONTENT . " c
            LEFT JOIN {$wpdb->prefix}" . MFW_TABLE_ANALYTICS . " m
            ON c.id = m.content_id
            WHERE c.created_at > %s
            AND c.status = 'published'",
            $last_30d
        ));

        $engagement_data = [
            'total_views' => 0,
            'total_shares' => 0,
            'total_comments' => 0,
            'avg_conversion_rate' => 0,
            'top_performing' => [],
            'trending_content' => [],
            'engagement_by_type' => [],
            'engagement_by_hour' => array_fill(0, 24, 0),
        ];

        if (empty($metrics)) {
            return $engagement_data;
        }

        // Process metrics
        foreach ($metrics as $item) {
            $engagement_data['total_views'] += $item->views;
            $engagement_data['total_shares'] += $item->shares;
            $engagement_data['total_comments'] += $item->comments;

            // Track engagement by hour
            $hour = date('G', strtotime($item->created_at));
            $engagement_data['engagement_by_hour'][$hour] += 
                $item->views + $item->shares + $item->comments;

            // Calculate engagement score
            $engagement_score = $this->calculate_engagement_score([
                'views' => $item->views,
                'shares' => $item->shares,
                'comments' => $item->comments,
                'conversion_rate' => $item->conversion_rate,
            ]);

            // Track top performing content
            if ($engagement_score > 0) {
                $engagement_data['top_performing'][] = [
                    'id' => $item->id,
                    'title' => $item->title,
                    'score' => $engagement_score,
                    'metrics' => [
                        'views' => $item->views,
                        'shares' => $item->shares,
                        'comments' => $item->comments,
                        'conversion_rate' => $item->conversion_rate,
                    ],
                ];
            }
        }

        // Sort and limit top performing content
        usort($engagement_data['top_performing'], function($a, $b) {
            return $b['score'] <=> $a['score'];
        });
        $engagement_data['top_performing'] = array_slice(
            $engagement_data['top_performing'], 
            0, 
            5
        );

        // Calculate trending content
        $engagement_data['trending_content'] = $this->identify_trending_content();

        // Calculate averages
        $count = count($metrics);
        $engagement_data['avg_conversion_rate'] = array_sum(
            array_column($metrics, 'conversion_rate')
        ) / $count;

        return $engagement_data;
    }

    private function calculate_engagement_score($metrics) {
        // Weighted scoring system
        $weights = [
            'views' => 1,
            'shares' => 3,
            'comments' => 2,
            'conversion_rate' => 5,
        ];

        $score = 0;
        foreach ($weights as $metric => $weight) {
            $score += ($metrics[$metric] ?? 0) * $weight;
        }

        return round($score, 2);
    }

    private function identify_trending_content() {
        global $wpdb;
        $current_time = '2025-05-16 10:22:00';
        $last_24h = date('Y-m-d H:i:s', strtotime($current_time) - 86400);
        $previous_24h = date('Y-m-d H:i:s', strtotime($last_24h) - 86400);

        // Get engagement data for the last 48 hours
        $results = $wpdb->get_results($wpdb->prepare("
            SELECT 
                c.id,
                c.title,
                c.created_at,
                SUM(CASE WHEN a.created_at > %s THEN a.view_count ELSE 0 END) as recent_views,
                SUM(CASE WHEN a.created_at BETWEEN %s AND %s THEN a.view_count ELSE 0 END) as previous_views,
                SUM(CASE WHEN a.created_at > %s THEN a.share_count ELSE 0 END) as recent_shares,
                SUM(CASE WHEN a.created_at BETWEEN %s AND %s THEN a.share_count ELSE 0 END) as previous_shares
            FROM {$wpdb->prefix}" . MFW_TABLE_CONTENT . " c
            LEFT JOIN {$wpdb->prefix}" . MFW_TABLE_ANALYTICS . " a ON c.id = a.content_id
            WHERE c.created_at > %s
            GROUP BY c.id
            HAVING recent_views > 0 OR recent_shares > 0",
            $last_24h,
            $previous_24h,
            $last_24h,
            $last_24h,
            $previous_24h,
            $last_24h,
            $previous_24h
        ));

        $trending = [];
        foreach ($results as $item) {
            // Calculate growth rate
            $previous_engagement = $item->previous_views + ($item->previous_shares * 3);
            $recent_engagement = $item->recent_views + ($item->recent_shares * 3);
            
            if ($previous_engagement > 0) {
                $growth_rate = (($recent_engagement - $previous_engagement) / $previous_engagement) * 100;
            } else {
                $growth_rate = $recent_engagement > 0 ? 100 : 0;
            }

            if ($growth_rate > 20) { // 20% growth threshold
                $trending[] = [
                    'id' => $item->id,
                    'title' => $item->title,
                    'growth_rate' => round($growth_rate, 2),
                    'recent_views' => $item->recent_views,
                    'recent_shares' => $item->recent_shares,
                ];
            }
        }

        // Sort by growth rate
        usort($trending, function($a, $b) {
            return $b['growth_rate'] <=> $a['growth_rate'];
        });

        return array_slice($trending, 0, 5);
    }
/**
 * Notification System
 * Handles system alerts and user notifications
 */
class MFW_Notification_System extends MFW_Base {
    private $current_time = '2025-05-16 10:23:57';
    private $current_user = 'maziyarid';
    private $channels = [];
    private $notification_queue = [];

    public function __construct() {
        parent::__construct();
        $this->initialize_channels();
    }

    private function initialize_channels() {
        // Register default notification channels
        $this->channels = [
            'email' => new MFW_Email_Channel(),
            'slack' => new MFW_Slack_Channel(),
            'dashboard' => new MFW_Dashboard_Channel(),
            'webhook' => new MFW_Webhook_Channel(),
        ];

        // Allow additional channels through filter
        $this->channels = apply_filters('mfw_notification_channels', $this->channels);
    }

    public function notify_critical_issues($issues) {
        $settings = get_option('mfw_notification_settings', []);
        
        // Group issues by severity
        $grouped_issues = $this->group_issues_by_severity($issues);
        
        // Prepare notification content
        $content = $this->prepare_issues_notification($grouped_issues);

        // Get notification recipients
        $recipients = $this->get_notification_recipients('critical');

        foreach ($recipients as $recipient) {
            $channels = $recipient['channels'] ?? ['email'];
            
            foreach ($channels as $channel) {
                if (isset($this->channels[$channel])) {
                    $this->queue_notification([
                        'channel' => $channel,
                        'recipient' => $recipient,
                        'subject' => 'Critical System Issues Detected',
                        'content' => $content,
                        'priority' => 'high',
                        'metadata' => [
                            'issues' => $grouped_issues,
                            'timestamp' => $this->current_time,
                        ],
                    ]);
                }
            }
        }

        // Process queued notifications
        $this->process_notification_queue();
    }

    private function group_issues_by_severity($issues) {
        $grouped = [
            'critical' => [],
            'warning' => [],
            'info' => [],
        ];

        foreach ($issues as $issue) {
            $grouped[$issue['type']][] = $issue;
        }

        return $grouped;
    }

    private function prepare_issues_notification($grouped_issues) {
        $content = "System Health Alert\n\n";
        $content .= "Generated at: {$this->current_time}\n\n";

        if (!empty($grouped_issues['critical'])) {
            $content .= "🔴 CRITICAL ISSUES:\n";
            foreach ($grouped_issues['critical'] as $issue) {
                $content .= "- {$issue['component']}: {$issue['message']}\n";
            }
            $content .= "\n";
        }

        if (!empty($grouped_issues['warning'])) {
            $content .= "⚠️ WARNINGS:\n";
            foreach ($grouped_issues['warning'] as $issue) {
                $content .= "- {$issue['component']}: {$issue['message']}\n";
            }
            $content .= "\n";
        }

        if (!empty($grouped_issues['info'])) {
            $content .= "ℹ️ INFORMATION:\n";
            foreach ($grouped_issues['info'] as $issue) {
                $content .= "- {$issue['component']}: {$issue['message']}\n";
            }
        }

        $content .= "\nFor detailed information, please visit the system health dashboard.";

        return $content;
    }

    private function get_notification_recipients($level) {
        $recipients = [];

        // Get system administrators
        $admin_users = get_users([
            'role' => 'administrator',
            'fields' => ['ID', 'user_email', 'display_name'],
        ]);

        foreach ($admin_users as $user) {
            $user_settings = get_user_meta($user->ID, 'mfw_notification_preferences', true);
            
            if ($user_settings && 
                (!isset($user_settings['disabled']) || !$user_settings['disabled'])) {
                $recipients[] = [
                    'type' => 'user',
                    'id' => $user->ID,
                    'email' => $user->user_email,
                    'name' => $user->display_name,
                    'channels' => $user_settings['channels'] ?? ['email'],
                ];
            }
        }

        // Get additional notification endpoints
        $endpoints = get_option('mfw_notification_endpoints', []);
        foreach ($endpoints as $endpoint) {
            if ($endpoint['levels'] && in_array($level, $endpoint['levels'])) {
                $recipients[] = [
                    'type' => 'endpoint',
                    'url' => $endpoint['url'],
                    'channels' => ['webhook'],
                    'secret' => $endpoint['secret'] ?? null,
                ];
            }
        }

        return $recipients;
    }

    private function queue_notification($notification) {
        $this->notification_queue[] = array_merge($notification, [
            'id' => uniqid('notify_'),
            'attempts' => 0,
            'queued_at' => $this->current_time,
        ]);
    }

    private function process_notification_queue() {
        foreach ($this->notification_queue as $index => $notification) {
            try {
                $channel = $this->channels[$notification['channel']];
                $result = $channel->send($notification);

                if ($result) {
                    // Log successful notification
                    $this->log_info('Notification sent successfully', [
                        'notification_id' => $notification['id'],
                        'channel' => $notification['channel'],
                        'recipient' => $notification['recipient'],
                    ]);

                    // Remove from queue
                    unset($this->notification_queue[$index]);
                } else {
                    throw new \Exception('Notification sending failed');
                }

            } catch (\Exception $e) {
                $this->handle_notification_failure($notification, $e);
            }
        }
    }

    private function handle_notification_failure($notification, $exception) {
        $max_attempts = 3;
        
        if ($notification['attempts'] >= $max_attempts) {
            // Log permanent failure
            $this->log_error('Notification failed permanently', [
                'notification_id' => $notification['id'],
                'channel' => $notification['channel'],
                'error' => $exception->getMessage(),
                'attempts' => $notification['attempts'],
            ]);

            // Try fallback channel if available
            $this->try_fallback_notification($notification);
        } else {
            // Increment attempts and requeue
            $notification['attempts']++;
            $this->queue_notification($notification);
        }
    }

    private function try_fallback_notification($failed_notification) {
        // Default to email for fallback
        if ($failed_notification['channel'] !== 'email' && 
            isset($this->channels['email'])) {
            
            $this->queue_notification(array_merge($failed_notification, [
                'channel' => 'email',
                'attempts' => 0,
                'metadata' => array_merge(
                    $failed_notification['metadata'] ?? [],
                    ['original_channel' => $failed_notification['channel']]
                ),
            ]));
        }
    }
}
/**
 * Notification Channel Implementations
 * Base class and specific channel handlers
 */
abstract class MFW_Notification_Channel_Base {
    protected $current_time = '2025-05-16 10:28:57';
    protected $current_user = 'maziyarid';

    abstract public function send($notification);
    abstract public function validate($notification);
    
    protected function log($level, $message, $context = []) {
        $logger = new MFW_Logger();
        $logger->log($level, $message, array_merge($context, [
            'component' => 'notification',
            'channel' => static::class,
        ]));
    }
}

class MFW_Email_Channel extends MFW_Notification_Channel_Base {
    private $mailer;
    
    public function __construct() {
        $this->mailer = new MFW_Mailer();
    }

    public function send($notification) {
        if (!$this->validate($notification)) {
            throw new \Exception('Invalid email notification data');
        }

        $recipient = $notification['recipient'];
        
        // Prepare email content
        $email_content = $this->prepare_email_content($notification);
        
        // Send email
        $result = $this->mailer->send([
            'to' => $recipient['email'],
            'subject' => $notification['subject'],
            'body' => $email_content,
            'headers' => $this->get_email_headers($notification),
            'attachments' => $this->prepare_attachments($notification),
        ]);

        if (!$result) {
            throw new \Exception('Failed to send email notification');
        }

        return true;
    }

    public function validate($notification) {
        return isset($notification['recipient']['email']) &&
               filter_var($notification['recipient']['email'], FILTER_VALIDATE_EMAIL) &&
               !empty($notification['subject']) &&
               !empty($notification['content']);
    }

    private function prepare_email_content($notification) {
        // Get email template
        $template = $this->get_email_template($notification);
        
        // Replace placeholders
        $content = strtr($template, [
            '{{content}}' => $notification['content'],
            '{{timestamp}}' => $this->current_time,
            '{{recipient_name}}' => $notification['recipient']['name'] ?? '',
            '{{dashboard_url}}' => admin_url('admin.php?page=mfw-dashboard'),
        ]);

        return $content;
    }

    private function get_email_template($notification) {
        $template_path = MFW_PLUGIN_DIR . '/templates/email/' . 
                        ($notification['template'] ?? 'default') . '.html';

        if (file_exists($template_path)) {
            return file_get_contents($template_path);
        }

        // Fallback to default template
        return $this->get_default_email_template();
    }

    private function get_default_email_template() {
        return <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body style="font-family: sans-serif; line-height: 1.6; color: #333;">
    <div style="max-width: 600px; margin: 0 auto; padding: 20px;">
        <div style="background: #f5f5f5; padding: 20px; border-radius: 5px;">
            {{content}}
        </div>
        <div style="margin-top: 20px; font-size: 12px; color: #666;">
            Sent at: {{timestamp}}<br>
            <a href="{{dashboard_url}}">View in Dashboard</a>
        </div>
    </div>
</body>
</html>
HTML;
    }

    private function get_email_headers($notification) {
        $headers = [
            'Content-Type: text/html; charset=UTF-8',
            'X-Notification-ID: ' . $notification['id'],
            'X-Notification-Priority: ' . ($notification['priority'] ?? 'normal'),
        ];

        if (!empty($notification['reply_to'])) {
            $headers[] = 'Reply-To: ' . $notification['reply_to'];
        }

        return $headers;
    }

    private function prepare_attachments($notification) {
        $attachments = [];

        if (!empty($notification['metadata']['attachments'])) {
            foreach ($notification['metadata']['attachments'] as $attachment) {
                if (file_exists($attachment['path'])) {
                    $attachments[] = $attachment['path'];
                }
            }
        }

        return $attachments;
    }
}

class MFW_Slack_Channel extends MFW_Notification_Channel_Base {
    private $webhook_url;
    
    public function __construct() {
        $this->webhook_url = get_option('mfw_slack_webhook_url');
    }

    public function send($notification) {
        if (!$this->validate($notification)) {
            throw new \Exception('Invalid Slack notification data');
        }

        $payload = $this->prepare_slack_payload($notification);
        
        $response = wp_remote_post($this->webhook_url, [
            'headers' => [
                'Content-Type' => 'application/json',
            ],
            'body' => json_encode($payload),
            'timeout' => 15,
        ]);

        if (is_wp_error($response)) {
            throw new \Exception('Slack API error: ' . $response->get_error_message());
        }

        $response_code = wp_remote_retrieve_response_code($response);
        if ($response_code !== 200) {
            throw new \Exception("Slack API returned status code: $response_code");
        }

        return true;
    }

    public function validate($notification) {
        return !empty($this->webhook_url) &&
               !empty($notification['content']);
    }

    private function prepare_slack_payload($notification) {
        $blocks = [
            [
                'type' => 'header',
                'text' => [
                    'type' => 'plain_text',
                    'text' => $notification['subject'],
                ],
            ],
            [
                'type' => 'section',
                'text' => [
                    'type' => 'mrkdwn',
                    'text' => $notification['content'],
                ],
            ],
        ];

        // Add metadata if available
        if (!empty($notification['metadata'])) {
            $blocks[] = [
                'type' => 'context',
                'elements' => [
                    [
                        'type' => 'mrkdwn',
                        'text' => "🕒 {$this->current_time}",
                    ],
                ],
            ];
        }

        return [
            'blocks' => $blocks,
            'text' => $notification['subject'], // Fallback text
        ];
    }
}
/**
 * Dashboard and Webhook Notification Channels
 */
class MFW_Dashboard_Channel extends MFW_Notification_Channel_Base {
    private $current_time = '2025-05-16 10:30:57';
    private $db;
    
    public function __construct() {
        parent::__construct();
        $this->db = new MFW_Database_Handler();
    }

    public function send($notification) {
        if (!$this->validate($notification)) {
            throw new \Exception('Invalid dashboard notification data');
        }

        $result = $this->db->insert(
            MFW_TABLE_NOTIFICATIONS,
            [
                'user_id' => $notification['recipient']['id'],
                'title' => $notification['subject'],
                'message' => $notification['content'],
                'type' => $notification['metadata']['type'] ?? 'system',
                'priority' => $notification['priority'] ?? 'normal',
                'status' => 'unread',
                'metadata' => json_encode($notification['metadata'] ?? []),
                'created_at' => $this->current_time,
            ],
            [
                '%d',  // user_id
                '%s',  // title
                '%s',  // message
                '%s',  // type
                '%s',  // priority
                '%s',  // status
                '%s',  // metadata
                '%s',  // created_at
            ]
        );

        if (!$result) {
            throw new \Exception('Failed to save dashboard notification');
        }

        // Trigger real-time notification if enabled
        $this->trigger_realtime_notification($notification);

        return true;
    }

    public function validate($notification) {
        return isset($notification['recipient']['id']) &&
               !empty($notification['subject']) &&
               !empty($notification['content']);
    }

    private function trigger_realtime_notification($notification) {
        $realtime_enabled = get_option('mfw_realtime_notifications', false);
        
        if ($realtime_enabled) {
            do_action('mfw_realtime_notification', [
                'user_id' => $notification['recipient']['id'],
                'notification' => [
                    'title' => $notification['subject'],
                    'message' => wp_trim_words($notification['content'], 20),
                    'type' => $notification['metadata']['type'] ?? 'system',
                    'priority' => $notification['priority'] ?? 'normal',
                    'timestamp' => $this->current_time,
                ],
            ]);
        }
    }
}

class MFW_Webhook_Channel extends MFW_Notification_Channel_Base {
    private $current_time = '2025-05-16 10:30:57';
    private $retry_attempts = 3;
    private $retry_delay = 5; // seconds

    public function send($notification) {
        if (!$this->validate($notification)) {
            throw new \Exception('Invalid webhook notification data');
        }

        $endpoint = $notification['recipient']['url'];
        $payload = $this->prepare_webhook_payload($notification);
        $headers = $this->prepare_webhook_headers($notification);

        $attempt = 0;
        $last_error = null;

        while ($attempt < $this->retry_attempts) {
            try {
                $response = wp_remote_post($endpoint, [
                    'headers' => $headers,
                    'body' => json_encode($payload),
                    'timeout' => 15,
                    'data_format' => 'body',
                ]);

                if (is_wp_error($response)) {
                    throw new \Exception($response->get_error_message());
                }

                $response_code = wp_remote_retrieve_response_code($response);
                if ($response_code >= 200 && $response_code < 300) {
                    $this->log_webhook_success($notification, $response);
                    return true;
                }

                throw new \Exception("HTTP Error: $response_code");

            } catch (\Exception $e) {
                $last_error = $e;
                $attempt++;

                if ($attempt < $this->retry_attempts) {
                    sleep($this->retry_delay);
                }
            }
        }

        $this->log_webhook_failure($notification, $last_error);
        throw new \Exception('Webhook delivery failed after ' . $this->retry_attempts . ' attempts');
    }

    public function validate($notification) {
        return isset($notification['recipient']['url']) &&
               filter_var($notification['recipient']['url'], FILTER_VALIDATE_URL) &&
               !empty($notification['content']);
    }

    private function prepare_webhook_payload($notification) {
        return [
            'id' => $notification['id'],
            'timestamp' => $this->current_time,
            'event' => $notification['metadata']['event'] ?? 'notification',
            'subject' => $notification['subject'],
            'content' => $notification['content'],
            'priority' => $notification['priority'] ?? 'normal',
            'metadata' => $notification['metadata'] ?? [],
            'source' => [
                'plugin' => 'MFW',
                'version' => MFW_VERSION,
                'url' => get_site_url(),
            ],
        ];
    }

    private function prepare_webhook_headers($notification) {
        $headers = [
            'Content-Type' => 'application/json',
            'User-Agent' => 'MFW-Notifier/' . MFW_VERSION,
            'X-MFW-Delivery' => $notification['id'],
            'X-MFW-Event' => $notification['metadata']['event'] ?? 'notification',
            'X-MFW-Timestamp' => $this->current_time,
        ];

        // Add signature if secret is configured
        if (!empty($notification['recipient']['secret'])) {
            $signature = $this->generate_webhook_signature(
                $notification['id'],
                $this->current_time,
                $notification['recipient']['secret']
            );
            $headers['X-MFW-Signature'] = $signature;
        }

        return $headers;
    }

    private function generate_webhook_signature($id, $timestamp, $secret) {
        $payload = sprintf('%s.%s', $id, $timestamp);
        return hash_hmac('sha256', $payload, $secret);
    }

    private function log_webhook_success($notification, $response) {
        $response_data = wp_remote_retrieve_body($response);
        $this->log('info', 'Webhook delivered successfully', [
            'notification_id' => $notification['id'],
            'endpoint' => $notification['recipient']['url'],
            'response_code' => wp_remote_retrieve_response_code($response),
            'response_data' => $response_data,
        ]);
    }

    private function log_webhook_failure($notification, $error) {
        $this->log('error', 'Webhook delivery failed', [
            'notification_id' => $notification['id'],
            'endpoint' => $notification['recipient']['url'],
            'error' => $error->getMessage(),
            'attempts' => $this->retry_attempts,
        ]);
    }
}
/**
 * View Templates Manager
 * Handles rendering of notification and dashboard views
 */
class MFW_View_Manager {
    private $current_time = '2025-05-16 10:40:27';
    private $current_user = 'maziyarid';
    private $template_dir;

    public function __construct() {
        $this->template_dir = MFW_PLUGIN_DIR . '/templates';
    }

    public function render($template, $data = [], $return = false) {
        $template_file = $this->locate_template($template);
        
        if (!$template_file) {
            throw new \Exception("Template not found: $template");
        }

        // Extract data to make it available in template
        extract($data);

        // Start output buffering
        ob_start();

        include $template_file;

        // Get the contents
        $output = ob_get_clean();

        if ($return) {
            return $output;
        }

        echo $output;
    }

    private function locate_template($template) {
        $template_path = $this->template_dir . '/' . $template . '.php';
        
        // Allow theme override
        $theme_template = locate_template('mfw/' . $template . '.php');
        
        if ($theme_template) {
            return $theme_template;
        }

        if (file_exists($template_path)) {
            return $template_path;
        }

        return false;
    }
}

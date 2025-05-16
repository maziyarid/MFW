<?php
/**
 * Main Modern Framework Class
 *
 * @package MFW
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

/**
 * Main Modern Framework Class
 */
final class Modern_Framework {

    /**
     * Single instance of the class
     *
     * @var Modern_Framework
     */
    protected static $_instance = null;

    /**
     * Main Modern_Framework Instance
     *
     * Ensures only one instance of Modern_Framework is loaded or can be loaded.
     *
     * @return Modern_Framework - Main instance
     */
    public static function instance() {
        if (is_null(self::$_instance)) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }

    /**
     * Modern_Framework Constructor.
     */
    public function __construct() {
        $this->define_constants();
        $this->init_hooks();
    }

    /**
     * Define MFW Constants
     */
    private function define_constants() {
        $this->define('MFW_ABSPATH', dirname(__FILE__) . '/');
        $this->define('MFW_PLUGIN_BASENAME', plugin_basename(__FILE__));
        $this->define('MFW_VERSION', '1.0.0');
        $this->define('MFW_API_VERSION', '1.0.0');
    }

    /**
     * Define constant if not already set
     *
     * @param string $name  Constant name.
     * @param mixed  $value Constant value.
     */
    private function define($name, $value) {
        if (!defined($name)) {
            define($name, $value);
        }
    }

    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Admin hooks
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_init', [$this, 'register_settings']);
        
        // Initialize components on admin_init
        if (is_admin()) {
            add_action('admin_init', [$this, 'init_admin']);
        }

        // Initialize API if enabled
        if ($this->is_api_enabled()) {
            add_action('rest_api_init', [$this, 'init_rest_api']);
        }

        // Add settings link on plugin page
        add_filter('plugin_action_links_' . MFW_PLUGIN_BASENAME, [$this, 'add_settings_link']);
    }

    /**
     * Plugin activation
     */
    public static function activate() {
        // Create necessary database tables
        self::create_tables();
        
        // Set default options
        self::set_default_options();
        
        // Clear the permalinks
        flush_rewrite_rules();
    }

    /**
     * Plugin deactivation
     */
    public static function deactivate() {
        // Clear any scheduled hooks
        wp_clear_scheduled_hooks('mfw_daily_tasks');
        
        // Clear the permalinks
        flush_rewrite_rules();
    }

    /**
     * Create required database tables
     */
    private static function create_tables() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        // Logs table
        $table_name = $wpdb->prefix . 'mfw_logs';
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            timestamp datetime DEFAULT CURRENT_TIMESTAMP,
            user_id bigint(20) NOT NULL,
            level varchar(20) NOT NULL,
            type varchar(50) NOT NULL,
            message text NOT NULL,
            context text,
            PRIMARY KEY  (id),
            KEY type (type),
            KEY level (level)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    /**
     * Set default options
     */
    private static function set_default_options() {
        $default_options = [
            'mfw_api_enabled' => 'yes',
            'mfw_default_provider' => 'gemini',
            'mfw_daily_limit' => 100,
            'mfw_batch_size' => 10,
            'mfw_image_generation' => 'yes',
            'mfw_seo_optimization' => 'yes',
        ];

        foreach ($default_options as $option => $value) {
            if (get_option($option) === false) {
                add_option($option, $value);
            }
        }
    }

    /**
     * Check if API is enabled
     *
     * @return bool
     */
    private function is_api_enabled() {
        return 'yes' === get_option('mfw_api_enabled', 'yes');
    }

    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_menu_page(
            __('Modern Framework', 'mfw'),
            __('Modern Framework', 'mfw'),
            'manage_options',
            'mfw-settings',
            [$this, 'render_settings_page'],
            'dashicons-text-page',
            30
        );
    }

    /**
     * Register plugin settings
     */
    public function register_settings() {
        register_setting('mfw_settings', 'mfw_api_enabled');
        register_setting('mfw_settings', 'mfw_default_provider');
        register_setting('mfw_settings', 'mfw_openai_api_key');
        register_setting('mfw_settings', 'mfw_gemini_api_key');
        register_setting('mfw_settings', 'mfw_daily_limit');
    }

    /**
     * Render settings page
     */
    public function render_settings_page() {
        // Check user capabilities
        if (!current_user_can('manage_options')) {
            return;
        }

        // Save settings if data has been posted
        if (isset($_POST['_wpnonce']) && wp_verify_nonce($_POST['_wpnonce'], 'mfw_settings')) {
            update_option('mfw_api_enabled', isset($_POST['mfw_api_enabled']) ? 'yes' : 'no');
            update_option('mfw_default_provider', sanitize_text_field($_POST['mfw_default_provider']));
            if (!empty($_POST['mfw_openai_api_key'])) {
                update_option('mfw_openai_api_key', sanitize_text_field($_POST['mfw_openai_api_key']));
            }
            if (!empty($_POST['mfw_gemini_api_key'])) {
                update_option('mfw_gemini_api_key', sanitize_text_field($_POST['mfw_gemini_api_key']));
            }
        }

        // Show settings form
        include MFW_PLUGIN_DIR . 'templates/admin-settings.php';
    }

    /**
     * Initialize REST API
     */
    public function init_rest_api() {
        // Register REST API endpoints here
    }

    /**
     * Add settings link to plugin page
     *
     * @param array $links Plugin links.
     * @return array Modified plugin links
     */
    public function add_settings_link($links) {
        $settings_link = '<a href="' . admin_url('admin.php?page=mfw-settings') . '">' . __('Settings', 'mfw') . '</a>';
        array_unshift($links, $settings_link);
        return $links;
    }

    /**
     * Initialize admin
     */
    public function init_admin() {
        // Add any admin initialization code here
    }
}
    // Supported AI Providers configuration
    private $supported_providers = [
        'text' => [
            'openai' => [
                'name' => 'OpenAI',
                'models' => ['gpt-4', 'gpt-4-turbo', 'gpt-3.5-turbo'],
                'features' => ['text', 'seo', 'metadata'],
            ],
            'gemini' => [
                'name' => 'Google Gemini',
                'models' => ['gemini-1.5-pro', 'gemini-1.5-flash', 'gemini-pro'],
                'features' => ['text', 'seo', 'metadata', 'images'],
            ],
            'deepseek' => [
                'name' => 'DeepSeek',
                'models' => ['deepseek-coder', 'deepseek-chat'],
                'features' => ['text', 'code'],
            ],
            'anthropic' => [
                'name' => 'Anthropic Claude',
                'models' => ['claude-3-opus', 'claude-3-sonnet', 'claude-2.1'],
                'features' => ['text', 'seo', 'metadata'],
            ],
        ],
        'image' => [
            'dalle' => [
                'name' => 'DALL-E 3',
                'features' => ['product-images', 'featured-images'],
            ],
            'stable_diffusion' => [
                'name' => 'Stable Diffusion',
                'features' => ['product-images', 'featured-images'],
            ],
            'midjourney' => [
                'name' => 'Midjourney',
                'features' => ['product-images', 'featured-images'],
            ],
            'gemini' => [
                'name' => 'Google Gemini',
                'features' => ['product-images', 'featured-images'],
            ],
            'leonardo' => [
                'name' => 'Leonardo AI',
                'features' => ['product-images', 'featured-images'],
            ],
        ],
    ];

    public static function instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->define_constants();
        $this->init_components();
        $this->init_hooks();
    }

    private function define_constants() {
        define('MFW_VERSION', '1.0.0');
        define('MFW_FILE', __FILE__);
        define('MFW_PATH', plugin_dir_path(MFW_FILE));
        define('MFW_URL', plugin_dir_url(MFW_FILE));
        define('MFW_ASSETS_URL', MFW_URL . 'assets/');
    }

    private function init_components() {
        // Initialize core components
        $this->prompt_manager = new MFW_Prompt_Manager();
        $this->ai_generator = new MFW_AI_Generator();
        $this->image_generator = new MFW_Image_Generator();
        $this->seo_optimizer = new MFW_SEO_Optimizer();
        $this->content_updater = new MFW_Content_Updater();
    }

    private function init_hooks() {
        // WordPress core hooks
        add_action('init', [$this, 'init']);
        add_action('admin_init', [$this, 'admin_init']);
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
        add_action('rest_api_init', [$this, 'register_rest_routes']);

        // Custom hooks for the plugin
        add_action('mfw_scheduled_content_update', [$this->content_updater, 'process_scheduled_updates']);
        add_action('mfw_process_bulk_generation', [$this, 'handle_bulk_generation']);
    }

    public function init() {
        load_plugin_textdomain('mfw', false, dirname(plugin_basename(__FILE__)) . '/languages');
        
        // Register post types and taxonomies if needed
        $this->register_post_types();
        
        // Initialize integrations
        $this->init_integrations();
    }

    public function admin_init() {
        // Register settings
        $this->register_settings();
        
        // Maybe handle settings imports
        $this->maybe_handle_settings_import();
    }

    public function register_settings() {
        // Register API settings for each provider
        foreach ($this->supported_providers as $type => $providers) {
            foreach ($providers as $provider_id => $provider) {
                register_setting("mfw_options", "mfw_{$provider_id}_api_key", [
                    'type' => 'string',
                    'sanitize_callback' => [$this, 'sanitize_api_key'],
                ]);
                
                if ($provider_id === 'gemini') {
                    register_setting("mfw_options", "mfw_gemini_project_id", [
                        'type' => 'string',
                    ]);
                }
            }
        }

        // Register general settings
        register_setting('mfw_options', 'mfw_default_text_provider', [
            'type' => 'string',
            'default' => 'gemini',
        ]);
        
        register_setting('mfw_options', 'mfw_default_image_provider', [
            'type' => 'string',
            'default' => 'stable_diffusion',
        ]);

        // Content generation settings
        register_setting('mfw_options', 'mfw_content_settings', [
            'type' => 'object',
            'default' => [
                'update_frequency' => 'monthly',
                'seo_target' => 'yoast',
                'image_style' => 'professional',
                'tone' => 'professional',
                'length' => 'medium',
            ],
        ]);
    }

    public function add_admin_menu() {
        add_menu_page(
            __('AI Content Manager', 'mfw'),
            __('AI Content', 'mfw'),
            'manage_options',
            'mfw-dashboard',
            [$this, 'render_dashboard'],
            'dashicons-update',
            30
        );

        add_submenu_page(
            'mfw-dashboard',
            __('Bulk Generation', 'mfw'),
            __('Bulk Generation', 'mfw'),
            'manage_options',
            'mfw-bulk-generate',
            [$this, 'render_bulk_generation']
        );

        add_submenu_page(
            'mfw-dashboard',
            __('Settings', 'mfw'),
            __('Settings', 'mfw'),
            'manage_options',
            'mfw-settings',
            [$this, 'render_settings']
        );
    }

    public function enqueue_admin_assets($hook) {
        if (strpos($hook, 'mfw-') !== false) {
            wp_enqueue_style(
                'mfw-admin',
                MFW_ASSETS_URL . 'css/admin.css',
                [],
                MFW_VERSION
            );

            wp_enqueue_script(
                'mfw-admin',
                MFW_ASSETS_URL . 'js/admin.js',
                ['jquery'],
                MFW_VERSION,
                true
            );

            wp_localize_script('mfw-admin', 'mfwData', [
                'nonce' => wp_create_nonce('wp_rest'),
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'restUrl' => get_rest_url(null, 'mfw/v1'),
                'providers' => $this->supported_providers,
            ]);
        }
    }

    public function register_rest_routes() {
        register_rest_route('mfw/v1', '/generate', [
            'methods' => 'POST',
            'callback' => [$this, 'handle_generate_request'],
            'permission_callback' => [$this, 'check_permission'],
        ]);

        register_rest_route('mfw/v1', '/bulk-generate', [
            'methods' => 'POST',
            'callback' => [$this, 'handle_bulk_generation'],
            'permission_callback' => [$this, 'check_permission'],
        ]);

        register_rest_route('mfw/v1', '/test-prompt', [
            'methods' => 'POST',
            'callback' => [$this, 'handle_test_prompt'],
            'permission_callback' => [$this, 'check_permission'],
        ]);
    }

    // Main functionality methods
    public function handle_generate_request($request) {
        try {
            $params = $request->get_params();
            
            // Validate required parameters
            if (empty($params['topic'])) {
                return new WP_Error('missing_topic', 'Topic is required');
            }

            // Generate content
            $content = $this->ai_generator->generate_content(
                $params['topic'],
                $params['type'] ?? 'post',
                $params
            );

            // Generate images if requested
            if (!empty($params['generate_images'])) {
                $content['images'] = $this->image_generator->generate(
                    $params['topic'],
                    $params['image_style'] ?? 'professional',
                    $params['image_count'] ?? 1
                );
            }

            // Optimize for SEO if requested
            if (!empty($params['optimize_seo'])) {
                $content = $this->seo_optimizer->optimize(
                    $content,
                    $params['seo_target'] ?? 'yoast'
                );
            }

            return rest_ensure_response([
                'success' => true,
                'data' => $content,
            ]);

        } catch (Exception $e) {
            return new WP_Error(
                'generation_failed',
                $e->getMessage(),
                ['status' => 500]
            );
        }
    }

    public function handle_bulk_generation($request) {
        try {
            $params = $request->get_params();
            $topics = $params['topics'] ?? [];

            if (empty($topics)) {
                return new WP_Error('missing_topics', 'Topics list is required');
            }

            $results = [];
            foreach ($topics as $topic) {
                try {
                    $result = $this->handle_single_topic_generation($topic, $params);
                    $results[] = [
                        'topic' => $topic,
                        'status' => 'success',
                        'data' => $result,
                    ];
                } catch (Exception $e) {
                    $results[] = [
                        'topic' => $topic,
                        'status' => 'error',
                        'message' => $e->getMessage(),
                    ];
                }
            }

            return rest_ensure_response([
                'success' => true,
                'data' => $results,
            ]);

        } catch (Exception $e) {
            return new WP_Error(
                'bulk_generation_failed',
                $e->getMessage(),
                ['status' => 500]
            );
        }
    }

    private function handle_single_topic_generation($topic, $params) {
        // Generate content
        $content = $this->ai_generator->generate_content($topic, $params);

        // Generate images if requested
        if (!empty($params['generate_images'])) {
            $content['images'] = $this->image_generator->generate(
                $topic,
                $params['image_style'] ?? 'professional',
                $params['image_count'] ?? 1
            );
        }

        // Optimize for SEO
        if (!empty($params['optimize_seo'])) {
            $content = $this->seo_optimizer->optimize(
                $content,
                $params['seo_target'] ?? 'yoast'
            );
        }

        // Create post/product
        $post_data = [
            'post_title' => $content['title'],
            'post_content' => $content['content'],
            'post_type' => $params['type'] ?? 'post',
            'post_status' => $params['status'] ?? 'draft',
        ];

        $post_id = wp_insert_post($post_data);

        if (is_wp_error($post_id)) {
            throw new Exception($post_id->get_error_message());
        }

        // Set featured image
        if (!empty($content['images']['featured'])) {
            $this->set_featured_image($post_id, $content['images']['featured']);
        }

        // Handle product-specific data
        if ($params['type'] === 'product' && class_exists('WC_Product')) {
            $this->handle_product_data($post_id, $content);
        }

        return [
            'post_id' => $post_id,
            'permalink' => get_permalink($post_id),
        ];
    }

    private function set_featured_image($post_id, $image_data) {
        // Handle base64 image data
        if (strpos($image_data, 'data:image') === 0) {
            $upload = wp_upload_bits(
                uniqid() . '.jpg',
                null,
                base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $image_data))
            );

            if (!empty($upload['error'])) {
                throw new Exception('Failed to save image: ' . $upload['error']);
            }

            $attachment_id = wp_insert_attachment([
                'post_mime_type' => 'image/jpeg',
                'post_title' => get_the_title($post_id),
                'post_content' => '',
                'post_status' => 'inherit',
            ], $upload['file'], $post_id);

            require_once(ABSPATH . 'wp-admin/includes/image.php');
            wp_update_attachment_metadata(
                $attachment_id,
                wp_generate_attachment_metadata($attachment_id, $upload['file'])
            );

            set_post_thumbnail($post_id, $attachment_id);
        }
    }

    private function handle_product_data($post_id, $content) {
        $product = wc_get_product($post_id);
        
        if ($product) {
            // Set product data
            if (!empty($content['short_description'])) {
                $product->set_short_description($content['short_description']);
            }
            
            if (!empty($content['price'])) {
                $product->set_regular_price($content['price']);
            }
            
            if (!empty($content['features'])) {
                update_post_meta($post_id, '_product_features', $content['features']);
            }
            
            // Handle product images
            if (!empty($content['images']['product'])) {
                $gallery_ids = [];
                foreach ($content['images']['product'] as $image_data) {
                    $attachment_id = $this->handle_product_image($post_id, $image_data);
                    if ($attachment_id) {
                        $gallery_ids[] = $attachment_id;
                    }
                }
                $product->set_gallery_image_ids($gallery_ids);
            }
            
            $product->save();
        }
    }

    public function check_permission() {
        return current_user_can('edit_posts');
    }

    // Plugin activation/deactivation hooks
    public static function activate() {
        // Create necessary database tables
        self::create_tables();
        
        // Schedule cron jobs
        wp_schedule_event(time(), 'daily', 'mfw_scheduled_content_update');
        
        // Set default options
        self::set_default_options();
    }

    public static function deactivate() {
        // Clear scheduled hooks
        wp_clear_scheduled_hook('mfw_scheduled_content_update');
    }

    private static function create_tables() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        // Create tables if they don't exist
        $sql = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}mfw_generation_logs (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            post_id bigint(20) NOT NULL,
            type varchar(50) NOT NULL,
            status varchar(20) NOT NULL,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            meta longtext,
            PRIMARY KEY  (id),
            KEY post_id (post_id)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    private static function set_default_options() {
        $default_options = [
            'mfw_default_text_provider' => 'gemini',
            'mfw_default_image_provider' => 'stable_diffusion',
            'mfw_content_settings' => [
                'update_frequency' => 'monthly',
                'seo_target' => 'yoast',
                'image_style' => 'professional',
                'tone' => 'professional',
                'length' => 'medium',
            ],
        ];

        foreach ($default_options as $option => $value) {
            if (get_option($option) === false) {
                add_option($option, $value);
            }
        }
    }
}
// Add this inside the Modern_Framework class, before the initialization code you shared:

// AI Provider Handler Class (inside Modern_Framework)
private function init_ai_providers() {
    $this->providers = [
        'openai' => [
            'handler' => function($prompt, $config) {
                $api_key = get_option('mfw_openai_api_key');
                $model = $config['model'] ?? 'gpt-4-turbo';
                
                $response = wp_remote_post('https://api.openai.com/v1/chat/completions', [
                    'headers' => [
                        'Authorization' => 'Bearer ' . $api_key,
                        'Content-Type' => 'application/json',
                    ],
                    'body' => json_encode([
                        'model' => $model,
                        'messages' => [
                            ['role' => 'user', 'content' => $prompt]
                        ],
                        'temperature' => $config['temperature'] ?? 0.7,
                    ]),
                    'timeout' => 30,
                ]);

                if (is_wp_error($response)) {
                    throw new Exception($response->get_error_message());
                }

                $body = json_decode(wp_remote_retrieve_body($response), true);
                return $body['choices'][0]['message']['content'] ?? '';
            }
        ],
        'gemini' => [
            'handler' => function($prompt, $config) {
                $api_key = get_option('mfw_gemini_api_key');
                $model = $config['model'] ?? 'gemini-1.5-pro';
                
                $response = wp_remote_post("https://generativelanguage.googleapis.com/v1/models/{$model}:generateContent", [
                    'headers' => [
                        'x-goog-api-key' => $api_key,
                        'Content-Type' => 'application/json',
                    ],
                    'body' => json_encode([
                        'contents' => [
                            [
                                'parts' => [
                                    ['text' => $prompt]
                                ]
                            ]
                        ],
                        'generationConfig' => [
                            'temperature' => $config['temperature'] ?? 0.7,
                            'topK' => $config['top_k'] ?? 40,
                            'topP' => $config['top_p'] ?? 0.95,
                        ],
                    ]),
                    'timeout' => 30,
                ]);

                if (is_wp_error($response)) {
                    throw new Exception($response->get_error_message());
                }

                $body = json_decode(wp_remote_retrieve_body($response), true);
                return $body['candidates'][0]['content']['parts'][0]['text'] ?? '';
            }
        ],
        'anthropic' => [
            'handler' => function($prompt, $config) {
                $api_key = get_option('mfw_anthropic_api_key');
                $model = $config['model'] ?? 'claude-3-opus';
                
                $response = wp_remote_post('https://api.anthropic.com/v1/messages', [
                    'headers' => [
                        'x-api-key' => $api_key,
                        'anthropic-version' => '2024-01-01',
                        'Content-Type' => 'application/json',
                    ],
                    'body' => json_encode([
                        'model' => $model,
                        'messages' => [
                            ['role' => 'user', 'content' => $prompt]
                        ],
                        'max_tokens' => $config['max_tokens'] ?? 1000,
                    ]),
                    'timeout' => 30,
                ]);

                if (is_wp_error($response)) {
                    throw new Exception($response->get_error_message());
                }

                $body = json_decode(wp_remote_retrieve_body($response), true);
                return $body['content'][0]['text'] ?? '';
            }
        ]
    ];
}

// Image Generation Handler (inside Modern_Framework)
private function generate_images($prompt, $config = []) {
    $provider = $config['provider'] ?? get_option('mfw_default_image_provider', 'stable_diffusion');
    $image_handlers = [
        'dalle' => function($prompt, $config) {
            $api_key = get_option('mfw_openai_api_key');
            
            $response = wp_remote_post('https://api.openai.com/v1/images/generations', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $api_key,
                    'Content-Type' => 'application/json',
                ],
                'body' => json_encode([
                    'prompt' => $prompt,
                    'n' => $config['count'] ?? 1,
                    'size' => $config['size'] ?? '1024x1024',
                    'model' => 'dall-e-3',
                ]),
            ]);

            if (is_wp_error($response)) {
                throw new Exception($response->get_error_message());
            }

            $body = json_decode(wp_remote_retrieve_body($response), true);
            return $body['data'][0]['url'] ?? '';
        },
        'stable_diffusion' => function($prompt, $config) {
            $api_key = get_option('mfw_sd_api_key');
            
            $response = wp_remote_post('https://api.stability.ai/v1/generation/stable-diffusion-xl-1024-v1-0/text-to-image', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $api_key,
                    'Content-Type' => 'application/json',
                ],
                'body' => json_encode([
                    'text_prompts' => [
                        ['text' => $prompt]
                    ],
                    'samples' => $config['count'] ?? 1,
                    'style_preset' => $config['style'] ?? 'photographic',
                ]),
            ]);

            if (is_wp_error($response)) {
                throw new Exception($response->get_error_message());
            }

            $body = json_decode(wp_remote_retrieve_body($response), true);
            return $body['artifacts'][0]['base64'] ?? '';
        }
    ];

    if (!isset($image_handlers[$provider])) {
        throw new Exception("Unsupported image provider: $provider");
    }

    return $image_handlers[$provider]($prompt, $config);
}

// SEO Optimization Handler (inside Modern_Framework)
private function optimize_for_seo($content, $target = 'yoast') {
    $seo_handlers = [
        'yoast' => function($content) {
            if (!defined('WPSEO_VERSION')) {
                return $content;
            }

            // Get Yoast SEO analysis
            $analysis = YoastSEO()->analysis->getRegistry();
            $results = $analysis->assess($content);

            // Improve content based on Yoast recommendations
            foreach ($results as $result) {
                if ($result->getRating() === 'red') {
                    // Apply fixes based on assessment type
                    $content = $this->apply_seo_fixes($content, $result);
                }
            }

            return $content;
        },
        'rankmath' => function($content) {
            if (!class_exists('RankMath')) {
                return $content;
            }

            // Get Rank Math analysis
            $analysis = RankMath\Helper::get_page_analysis();
            
            // Improve content based on Rank Math recommendations
            foreach ($analysis as $result) {
                if ($result['status'] === 'error') {
                    $content = $this->apply_seo_fixes($content, $result);
                }
            }

            return $content;
        }
    ];

    if (!isset($seo_handlers[$target])) {
        return $content;
    }

    return $seo_handlers[$target]($content);
}

// Add this to your handle_generate_request method:
private function handle_content_generation($topic, $params = []) {
    $provider = $params['provider'] ?? get_option('mfw_default_text_provider', 'gemini');
    
    if (!isset($this->providers[$provider])) {
        throw new Exception("Unsupported AI provider: $provider");
    }

    // Build prompt based on content type
    $prompt = $this->build_prompt($topic, $params);

    // Generate content using selected provider
    $content = $this->providers[$provider]['handler']($prompt, $params);

    // Generate images if requested
    if (!empty($params['generate_images'])) {
        try {
            $images = $this->generate_images(
                "Create professional image for: $topic",
                [
                    'count' => $params['image_count'] ?? 1,
                    'style' => $params['image_style'] ?? 'professional',
                    'provider' => $params['image_provider'] ?? null,
                ]
            );
            $content['images'] = $images;
        } catch (Exception $e) {
            // Log image generation error but continue with content
            error_log("Image generation failed: " . $e->getMessage());
        }
    }

    // Optimize for SEO if requested
    if (!empty($params['optimize_seo'])) {
        $content = $this->optimize_for_seo(
            $content,
            $params['seo_target'] ?? 'yoast'
        );
    }

    return $content;
}

// Add this method for handling WooCommerce products
private function handle_product_generation($topic, $params = []) {
    if (!class_exists('WC_Product')) {
        throw new Exception('WooCommerce is not active');
    }

    // Generate product content
    $content = $this->handle_content_generation($topic, array_merge($params, [
        'type' => 'product',
        'generate_images' => true,
        'image_count' => 3, // Product usually needs multiple images
    ]));

    // Create new product
    $product = new WC_Product_Simple();
    $product->set_name($content['title']);
    $product->set_description($content['content']);
    $product->set_short_description($content['excerpt'] ?? '');
    
    if (!empty($content['price'])) {
        $product->set_regular_price($content['price']);
    }

    // Save product
    $product_id = $product->save();

    // Set product images
    if (!empty($content['images'])) {
        $this->set_product_images($product_id, $content['images']);
    }

    return [
        'product_id' => $product_id,
        'permalink' => get_permalink($product_id),
        'content' => $content,
    ];
}

// Add these methods inside the Modern_Framework class

// Admin Interface Methods
private function init_admin_interface() {
    add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
    add_action('wp_ajax_mfw_bulk_generate', [$this, 'ajax_bulk_generate']);
    add_action('wp_ajax_mfw_save_settings', [$this, 'ajax_save_settings']);
}

public function enqueue_admin_assets($hook) {
    if (strpos($hook, 'mfw-') === false) {
        return;
    }

    // Enqueue admin styles
    wp_enqueue_style('mfw-admin', false);
    wp_add_inline_style('mfw-admin', $this->get_admin_css());

    // Enqueue admin scripts
    wp_enqueue_script('mfw-admin', false);
    wp_add_inline_script('mfw-admin', $this->get_admin_js());

    // Localize script
    wp_localize_script('mfw-admin', 'mfwData', [
        'nonce' => wp_create_nonce('mfw-ajax-nonce'),
        'ajaxUrl' => admin_url('admin-ajax.php'),
        'currentTime' => '2025-05-16 11:30:54',
        'currentUser' => 'maziyarid',
        'providers' => array_keys($this->providers),
        'imageProviders' => ['dalle', 'stable_diffusion', 'gemini'],
        'strings' => [
            'generating' => __('Generating...', 'mfw'),
            'success' => __('Generation completed!', 'mfw'),
            'error' => __('Error occurred:', 'mfw'),
        ]
    ]);
}

private function get_admin_css() {
    return <<<CSS
    .mfw-dashboard {
        padding: 20px;
    }
    .mfw-stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 20px;
        margin-bottom: 30px;
    }
    .mfw-stat-card {
        background: #fff;
        border-radius: 8px;
        padding: 20px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }
    .mfw-generation-form {
        background: #fff;
        padding: 20px;
        border-radius: 8px;
        max-width: 800px;
    }
    .mfw-form-group {
        margin-bottom: 20px;
    }
    .mfw-progress {
        margin-top: 20px;
        background: #f0f0f1;
        border-radius: 4px;
        height: 20px;
        overflow: hidden;
    }
    .mfw-progress-bar {
        height: 100%;
        background: #2271b1;
        transition: width 0.3s ease;
    }
    CSS;
}

private function get_admin_js() {
    return <<<JAVASCRIPT
    class MFWAdmin {
        constructor() {
            this.currentTime = '2025-05-16 11:30:54';
            this.currentUser = 'maziyarid';
            this.init();
        }

        init() {
            this.initBulkGeneration();
            this.initSettings();
            this.initProgressTracking();
        }

        async initBulkGeneration() {
            const form = document.getElementById('mfw-bulk-form');
            if (!form) return;

            form.addEventListener('submit', async (e) => {
                e.preventDefault();
                await this.handleBulkGeneration(new FormData(form));
            });
        }

        async handleBulkGeneration(formData) {
            try {
                this.showProgress();
                const topics = formData.get('topics').split('\\n').filter(t => t.trim());
                const total = topics.length;
                let completed = 0;

                for (const topic of topics) {
                    await this.generateSingle(topic, formData);
                    completed++;
                    this.updateProgress(completed, total);
                }

                this.showSuccess('Generation completed!');
            } catch (error) {
                this.showError(error.message);
            } finally {
                this.hideProgress();
            }
        }

        async generateSingle(topic, formData) {
            const response = await fetch(ajaxurl, {
                method: 'POST',
                headers: {
                    'X-WP-Nonce': mfwData.nonce
                },
                body: new URLSearchParams({
                    action: 'mfw_bulk_generate',
                    topic: topic,
                    type: formData.get('content_type'),
                    provider: formData.get('ai_provider'),
                    generate_images: formData.get('generate_images'),
                    seo_target: formData.get('seo_target')
                })
            });

            if (!response.ok) {
                throw new Error('Generation failed');
            }

            return await response.json();
        }

        showProgress() {
            const progress = document.querySelector('.mfw-progress');
            if (progress) progress.style.display = 'block';
        }

        updateProgress(completed, total) {
            const progressBar = document.querySelector('.mfw-progress-bar');
            if (progressBar) {
                const percentage = (completed / total) * 100;
                progressBar.style.width = percentage + '%';
            }
        }

        hideProgress() {
            const progress = document.querySelector('.mfw-progress');
            if (progress) progress.style.display = 'none';
        }

        showSuccess(message) {
            this.showNotice(message, 'success');
        }

        showError(message) {
            this.showNotice(message, 'error');
        }

        showNotice(message, type) {
            const notice = document.createElement('div');
            notice.className = `notice notice-${type} is-dismissible`;
            notice.innerHTML = `<p>${message}</p>`;
            
            const wrapper = document.querySelector('.wrap');
            if (wrapper) {
                wrapper.insertBefore(notice, wrapper.firstChild);
                setTimeout(() => notice.remove(), 5000);
            }
        }
    }

    document.addEventListener('DOMContentLoaded', () => {
        window.mfwAdmin = new MFWAdmin();
    });
    JAVASCRIPT;
}

// REST API Endpoints
public function register_rest_routes() {
    register_rest_route('mfw/v1', '/generate', [
        'methods' => 'POST',
        'callback' => [$this, 'handle_rest_generate'],
        'permission_callback' => [$this, 'check_rest_permission'],
        'args' => [
            'topic' => [
                'required' => true,
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'type' => [
                'type' => 'string',
                'default' => 'post',
                'enum' => ['post', 'product', 'docs'],
            ],
            'provider' => [
                'type' => 'string',
                'default' => 'gemini',
            ],
            'generate_images' => [
                'type' => 'boolean',
                'default' => true,
            ],
        ],
    ]);

    register_rest_route('mfw/v1', '/bulk-generate', [
        'methods' => 'POST',
        'callback' => [$this, 'handle_rest_bulk_generate'],
        'permission_callback' => [$this, 'check_rest_permission'],
        'args' => [
            'topics' => [
                'required' => true,
                'type' => 'array',
                'items' => [
                    'type' => 'string',
                ],
            ],
        ],
    ]);
}

public function handle_rest_generate($request) {
    try {
        $params = $request->get_params();
        $content = $this->handle_content_generation(
            $params['topic'],
            $params
        );

        return rest_ensure_response([
            'success' => true,
            'data' => $content,
        ]);

    } catch (Exception $e) {
        return new WP_Error(
            'generation_failed',
            $e->getMessage(),
            ['status' => 500]
        );
    }
}

public function handle_rest_bulk_generate($request) {
    try {
        $params = $request->get_params();
        $results = [];

        foreach ($params['topics'] as $topic) {
            try {
                $result = $this->handle_content_generation($topic, $params);
                $results[] = [
                    'topic' => $topic,
                    'status' => 'success',
                    'data' => $result,
                ];
            } catch (Exception $e) {
                $results[] = [
                    'topic' => $topic,
                    'status' => 'error',
                    'message' => $e->getMessage(),
                ];
            }
        }

        return rest_ensure_response([
            'success' => true,
            'data' => $results,
        ]);

    } catch (Exception $e) {
        return new WP_Error(
            'bulk_generation_failed',
            $e->getMessage(),
            ['status' => 500]
        );
    }
}

// Add these methods inside the Modern_Framework class

// Update Scheduling System
private function init_scheduling_system() {
    $this->current_time = '2025-05-16 11:32:25';
    $this->current_user = 'maziyarid';

    add_action('mfw_scheduled_update', [$this, 'process_scheduled_updates']);
    add_action('init', [$this, 'register_schedule_hooks']);
}

public function register_schedule_hooks() {
    if (!wp_next_scheduled('mfw_scheduled_update')) {
        wp_schedule_event(time(), 'daily', 'mfw_scheduled_update');
    }
}

public function process_scheduled_updates() {
    $update_settings = get_option('mfw_update_settings', [
        'frequency' => 'daily',
        'batch_size' => 10,
        'seo_threshold' => 70,
        'update_images' => true,
    ]);

    $posts_to_update = $this->get_posts_needing_update($update_settings);
    
    foreach ($posts_to_update as $post) {
        try {
            $this->update_single_content($post, $update_settings);
        } catch (Exception $e) {
            $this->log_error($post->ID, 'update_failed', $e->getMessage());
        }
    }
}

private function get_posts_needing_update($settings) {
    $args = [
        'post_type' => ['post', 'product', 'docs'],
        'posts_per_page' => $settings['batch_size'],
        'meta_query' => [
            [
                'key' => 'mfw_last_update',
                'value' => date('Y-m-d H:i:s', strtotime('-1 ' . $settings['frequency'])),
                'compare' => '<=',
                'type' => 'DATETIME'
            ],
            [
                'key' => 'mfw_auto_update',
                'value' => 'yes',
            ]
        ],
        'orderby' => 'meta_value',
        'order' => 'ASC',
        'meta_key' => 'mfw_last_update',
    ];

    return get_posts($args);
}

private function update_single_content($post, $settings) {
    $this->log_info($post->ID, 'update_started', "Starting content update for post {$post->ID}");

    // Get original metadata
    $keywords = get_post_meta($post->ID, 'mfw_keywords', true) ?: [];
    $seo_score = $this->get_seo_score($post->ID);

    // Only update if SEO score is below threshold
    if ($seo_score >= $settings['seo_threshold']) {
        $this->log_info($post->ID, 'update_skipped', "SEO score {$seo_score} above threshold");
        return;
    }

    // Generate improved content
    $new_content = $this->handle_content_generation(
        $post->post_title,
        [
            'type' => $post->post_type,
            'keywords' => $keywords,
            'optimize_seo' => true,
            'seo_target' => $settings['seo_target'] ?? 'yoast',
            'generate_images' => $settings['update_images'],
            'existing_content' => $post->post_content,
        ]
    );

    // Update post
    $update_data = [
        'ID' => $post->ID,
        'post_content' => $new_content['content'],
    ];

    if (!empty($new_content['title'])) {
        $update_data['post_title'] = $new_content['title'];
    }

    wp_update_post($update_data);

    // Update meta
    update_post_meta($post->ID, 'mfw_last_update', $this->current_time);
    update_post_meta($post->ID, 'mfw_update_author', $this->current_user);

    // Handle images if needed
    if ($settings['update_images'] && !empty($new_content['images'])) {
        $this->update_post_images($post->ID, $new_content['images']);
    }

    // Handle WooCommerce specific updates
    if ($post->post_type === 'product') {
        $this->update_product_data($post->ID, $new_content);
    }

    $this->log_info($post->ID, 'update_completed', "Content update completed successfully");
}

// Bulk Generation Processing
private function process_bulk_generation($topics, $settings) {
    $this->log_info('bulk', 'generation_started', "Starting bulk generation for " . count($topics) . " topics");
    
    $results = [
        'success' => [],
        'failed' => [],
        'skipped' => [],
    ];

    $batch_size = $settings['batch_size'] ?? 5;
    $batches = array_chunk($topics, $batch_size);

    foreach ($batches as $batch_index => $batch) {
        $this->log_info('bulk', 'batch_started', "Processing batch " . ($batch_index + 1));
        
        foreach ($batch as $topic) {
            try {
                // Check for duplicates
                if ($this->is_duplicate_content($topic)) {
                    $results['skipped'][] = [
                        'topic' => $topic,
                        'reason' => 'duplicate_content',
                    ];
                    continue;
                }

                // Generate content
                $content = $this->handle_content_generation($topic, $settings);

                // Create post/product
                $post_id = $this->create_content_post($content, $settings);

                $results['success'][] = [
                    'topic' => $topic,
                    'post_id' => $post_id,
                    'permalink' => get_permalink($post_id),
                ];

            } catch (Exception $e) {
                $results['failed'][] = [
                    'topic' => $topic,
                    'error' => $e->getMessage(),
                ];
                $this->log_error('bulk', 'generation_failed', $e->getMessage());
            }
        }

        // Add delay between batches to avoid API rate limits
        if (isset($batches[$batch_index + 1])) {
            sleep(2);
        }
    }

    $this->log_info('bulk', 'generation_completed', json_encode([
        'total' => count($topics),
        'success' => count($results['success']),
        'failed' => count($results['failed']),
        'skipped' => count($results['skipped']),
    ]));

    return $results;
}

private function create_content_post($content, $settings) {
    $post_data = [
        'post_title' => $content['title'],
        'post_content' => $content['content'],
        'post_excerpt' => $content['excerpt'] ?? '',
        'post_type' => $settings['type'] ?? 'post',
        'post_status' => $settings['status'] ?? 'draft',
        'post_author' => get_current_user_id(),
    ];

    // Create post
    $post_id = wp_insert_post($post_data);

    if (is_wp_error($post_id)) {
        throw new Exception($post_id->get_error_message());
    }

    // Handle images
    if (!empty($content['images'])) {
        $this->handle_post_images($post_id, $content['images']);
    }

    // Handle product-specific data
    if ($settings['type'] === 'product') {
        $this->handle_product_data($post_id, $content);
    }

    // Add metadata
    update_post_meta($post_id, 'mfw_generated', 'yes');
    update_post_meta($post_id, 'mfw_generation_time', $this->current_time);
    update_post_meta($post_id, 'mfw_generation_user', $this->current_user);
    
    if (!empty($content['keywords'])) {
        update_post_meta($post_id, 'mfw_keywords', $content['keywords']);
    }

    return $post_id;
}

private function handle_post_images($post_id, $images) {
    if (!empty($images['featured'])) {
        $featured_image_id = $this->upload_image(
            $images['featured'],
            $post_id,
            'featured-image'
        );
        if ($featured_image_id) {
            set_post_thumbnail($post_id, $featured_image_id);
        }
    }

    if (!empty($images['gallery'])) {
        $gallery_ids = [];
        foreach ($images['gallery'] as $image) {
            $image_id = $this->upload_image($image, $post_id, 'gallery-image');
            if ($image_id) {
                $gallery_ids[] = $image_id;
            }
        }
        if (!empty($gallery_ids)) {
            update_post_meta($post_id, '_product_image_gallery', implode(',', $gallery_ids));
        }
    }
}

// Add these methods inside the Modern_Framework class

// Error Handling and Logging System
private function init_logging_system() {
    $this->current_time = '2025-05-16 11:34:16';
    $this->current_user = 'maziyarid';
    
    $this->log_table = $this->create_log_table();
}

private function create_log_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'mfw_logs';

    if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            timestamp datetime DEFAULT '$this->current_time' NOT NULL,
            user_id varchar(100) DEFAULT '$this->current_user' NOT NULL,
            level varchar(20) NOT NULL,
            type varchar(50) NOT NULL,
            message text NOT NULL,
            context text,
            metadata longtext,
            post_id bigint(20),
            PRIMARY KEY  (id),
            KEY type (type),
            KEY post_id (post_id),
            KEY level (level)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    return $table_name;
}

private function log($level, $type, $message, $context = [], $post_id = null) {
    global $wpdb;

    $data = [
        'timestamp' => $this->current_time,
        'user_id' => $this->current_user,
        'level' => $level,
        'type' => $type,
        'message' => $message,
        'context' => json_encode($context),
        'metadata' => json_encode([
            'ip' => $_SERVER['REMOTE_ADDR'] ?? '',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'referer' => $_SERVER['HTTP_REFERER'] ?? '',
        ]),
        'post_id' => $post_id,
    ];

    $wpdb->insert($this->log_table, $data);

    if ($level === 'error') {
        error_log("MFW Error [$type]: $message");
    }
}

public function log_error($type, $message, $context = [], $post_id = null) {
    $this->log('error', $type, $message, $context, $post_id);
}

public function log_info($type, $message, $context = [], $post_id = null) {
    $this->log('info', $type, $message, $context, $post_id);
}

public function log_debug($type, $message, $context = [], $post_id = null) {
    if (defined('WP_DEBUG') && WP_DEBUG) {
        $this->log('debug', $type, $message, $context, $post_id);
    }
}

// SEO Optimization System
private function init_seo_system() {
    $this->seo_providers = [
        'yoast' => [
            'handler' => [$this, 'optimize_for_yoast'],
            'active' => defined('WPSEO_VERSION'),
        ],
        'rankmath' => [
            'handler' => [$this, 'optimize_for_rankmath'],
            'active' => class_exists('RankMath'),
        ],
    ];
}

private function optimize_for_yoast($content, $keywords = []) {
    if (!defined('WPSEO_VERSION')) {
        return $content;
    }

    $this->log_debug('seo', 'Starting Yoast SEO optimization', ['keywords' => $keywords]);

    try {
        // Get Yoast analysis
        $analyzer = YoastSEO()->helpers->analysis;
        $results = $analyzer->getContentAssessment($content);

        // Track improvements needed
        $improvements = [];
        foreach ($results as $result) {
            if ($result->getRating() === 'red') {
                $improvements[] = $result->getText();
            }
        }

        if (!empty($improvements)) {
            // Generate improvement prompt
            $improvement_prompt = $this->generate_seo_improvement_prompt($content, $improvements, $keywords);
            
            // Get improved content using AI
            $improved_content = $this->handle_content_generation(
                $improvement_prompt,
                [
                    'provider' => 'gemini',
                    'type' => 'seo_improvement',
                    'context' => [
                        'original_content' => $content,
                        'improvements' => $improvements,
                        'keywords' => $keywords,
                    ],
                ]
            );

            $content = $improved_content['content'];
        }

        $this->log_info('seo', 'Yoast SEO optimization completed', [
            'improvements' => count($improvements),
            'keywords' => $keywords,
        ]);

        return $content;

    } catch (Exception $e) {
        $this->log_error('seo', 'Yoast SEO optimization failed: ' . $e->getMessage());
        return $content;
    }
}

private function optimize_for_rankmath($content, $keywords = []) {
    if (!class_exists('RankMath')) {
        return $content;
    }

    $this->log_debug('seo', 'Starting Rank Math optimization', ['keywords' => $keywords]);

    try {
        // Get Rank Math analysis
        $analyzer = new RankMath\Content_AI();
        $score = $analyzer->get_content_score($content);

        if ($score < 80) { // If score is less than 80
            // Get detailed recommendations
            $recommendations = $analyzer->get_recommendations();
            
            // Generate improvement prompt
            $improvement_prompt = $this->generate_seo_improvement_prompt($content, $recommendations, $keywords);
            
            // Get improved content using AI
            $improved_content = $this->handle_content_generation(
                $improvement_prompt,
                [
                    'provider' => 'gemini',
                    'type' => 'seo_improvement',
                    'context' => [
                        'original_content' => $content,
                        'recommendations' => $recommendations,
                        'keywords' => $keywords,
                    ],
                ]
            );

            $content = $improved_content['content'];
        }

        $this->log_info('seo', 'Rank Math optimization completed', [
            'initial_score' => $score,
            'keywords' => $keywords,
        ]);

        return $content;

    } catch (Exception $e) {
        $this->log_error('seo', 'Rank Math optimization failed: ' . $e->getMessage());
        return $content;
    }
}

private function generate_seo_improvement_prompt($content, $improvements, $keywords) {
    return sprintf(
        "Improve this content for SEO while maintaining its meaning and tone. " .
        "Focus on these improvements:\n%s\n\n" .
        "Target keywords: %s\n\n" .
        "Original content:\n%s",
        implode("\n", $improvements),
        implode(', ', $keywords),
        $content
    );
}

// Add error handling for API calls
private function handle_api_request($provider, $endpoint, $data) {
    try {
        $api_key = $this->get_provider_api_key($provider);
        if (empty($api_key)) {
            throw new Exception("API key not configured for provider: $provider");
        }

        $response = wp_remote_post($endpoint, [
            'headers' => [
                'Authorization' => "Bearer $api_key",
                'Content-Type' => 'application/json',
            ],
            'body' => json_encode($data),
            'timeout' => 30,
        ]);

        if (is_wp_error($response)) {
            throw new Exception($response->get_error_message());
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        if (!empty($body['error'])) {
            throw new Exception($body['error']['message'] ?? 'Unknown API error');
        }

        return $body;

    } catch (Exception $e) {
        $this->log_error('api', "API request failed for $provider: " . $e->getMessage(), [
            'endpoint' => $endpoint,
            'data' => $data,
        ]);
        throw $e;
    }
}

// Add these methods inside the Modern_Framework class

// WooCommerce Integration System
private function init_woocommerce_system() {
    $this->current_time = '2025-05-16 11:35:59';
    $this->current_user = 'maziyarid';

    add_action('woocommerce_product_options_general_product_data', [$this, 'add_ai_product_fields']);
    add_action('woocommerce_process_product_meta', [$this, 'save_ai_product_fields']);
    add_action('woocommerce_before_product_object_save', [$this, 'process_ai_product_data']);
}

private function handle_woocommerce_product($post_id, $content_data) {
    if (!class_exists('WC_Product')) {
        throw new Exception('WooCommerce not active');
    }

    try {
        $product = wc_get_product($post_id) ?: new WC_Product_Simple();
        
        // Basic product data
        $product->set_name($content_data['title']);
        $product->set_description($content_data['content']);
        $product->set_short_description($content_data['short_description'] ?? '');
        
        // Price and inventory
        if (isset($content_data['price'])) {
            $product->set_regular_price($content_data['price']);
        }
        if (isset($content_data['sale_price'])) {
            $product->set_sale_price($content_data['sale_price']);
        }
        
        // SKU and inventory
        if (!empty($content_data['sku'])) {
            $product->set_sku($content_data['sku']);
        }
        if (isset($content_data['stock_quantity'])) {
            $product->set_manage_stock(true);
            $product->set_stock_quantity($content_data['stock_quantity']);
            $product->set_stock_status($content_data['stock_quantity'] > 0 ? 'instock' : 'outofstock');
        }

        // Categories and tags
        if (!empty($content_data['categories'])) {
            $cat_ids = [];
            foreach ($content_data['categories'] as $category) {
                $term = get_term_by('name', $category, 'product_cat');
                if (!$term) {
                    $term = wp_insert_term($category, 'product_cat');
                }
                if (!is_wp_error($term)) {
                    $cat_ids[] = is_object($term) ? $term->term_id : $term['term_id'];
                }
            }
            $product->set_category_ids($cat_ids);
        }

        if (!empty($content_data['tags'])) {
            $tag_ids = [];
            foreach ($content_data['tags'] as $tag) {
                $term = get_term_by('name', $tag, 'product_tag');
                if (!$term) {
                    $term = wp_insert_term($tag, 'product_tag');
                }
                if (!is_wp_error($term)) {
                    $tag_ids[] = is_object($term) ? $term->term_id : $term['term_id'];
                }
            }
            $product->set_tag_ids($tag_ids);
        }

        // Attributes
        if (!empty($content_data['attributes'])) {
            $attributes = [];
            foreach ($content_data['attributes'] as $name => $values) {
                $attribute = new WC_Product_Attribute();
                $attribute->set_name($name);
                $attribute->set_options($values);
                $attribute->set_visible(true);
                $attributes[] = $attribute;
            }
            $product->set_attributes($attributes);
        }

        // Images
        if (!empty($content_data['images'])) {
            // Featured image
            if (!empty($content_data['images']['featured'])) {
                $featured_image_id = $this->handle_product_image(
                    $content_data['images']['featured'],
                    $content_data['title'] . ' - Featured',
                    $post_id
                );
                if ($featured_image_id) {
                    $product->set_image_id($featured_image_id);
                }
            }

            // Gallery images
            if (!empty($content_data['images']['gallery'])) {
                $gallery_ids = [];
                foreach ($content_data['images']['gallery'] as $index => $image_data) {
                    $image_id = $this->handle_product_image(
                        $image_data,
                        $content_data['title'] . ' - Gallery ' . ($index + 1),
                        $post_id
                    );
                    if ($image_id) {
                        $gallery_ids[] = $image_id;
                    }
                }
                $product->set_gallery_image_ids($gallery_ids);
            }
        }

        // SEO and metadata
        if (!empty($content_data['meta_title'])) {
            update_post_meta($post_id, '_yoast_wpseo_title', $content_data['meta_title']);
            update_post_meta($post_id, 'rank_math_title', $content_data['meta_title']);
        }
        if (!empty($content_data['meta_description'])) {
            update_post_meta($post_id, '_yoast_wpseo_metadesc', $content_data['meta_description']);
            update_post_meta($post_id, 'rank_math_description', $content_data['meta_description']);
        }

        // Custom product tabs
        if (!empty($content_data['tabs'])) {
            $product_tabs = [];
            foreach ($content_data['tabs'] as $tab) {
                $product_tabs[sanitize_title($tab['title'])] = [
                    'title' => $tab['title'],
                    'content' => $tab['content'],
                    'priority' => $tab['priority'] ?? 50,
                ];
            }
            update_post_meta($post_id, '_product_tabs', $product_tabs);
        }

        // Upsells and Cross-sells
        if (!empty($content_data['upsells'])) {
            $product->set_upsell_ids($content_data['upsells']);
        }
        if (!empty($content_data['cross_sells'])) {
            $product->set_cross_sell_ids($content_data['cross_sells']);
        }

        // Save all changes
        $product->save();

        // Log success
        $this->log_info('product', 'Product updated successfully', [
            'product_id' => $post_id,
            'type' => $product->get_type(),
        ]);

        return $product->get_id();

    } catch (Exception $e) {
        $this->log_error('product', 'Failed to update product: ' . $e->getMessage(), [
            'product_id' => $post_id,
            'content_data' => $content_data,
        ]);
        throw $e;
    }
}

private function handle_product_variations($product_id, $variations_data) {
    $product = wc_get_product($product_id);
    if (!$product || !$product->is_type('variable')) {
        return;
    }

    try {
        foreach ($variations_data as $variation) {
            $variation_obj = new WC_Product_Variation();
            $variation_obj->set_parent_id($product_id);
            
            // Set attributes
            $variation_attributes = [];
            foreach ($variation['attributes'] as $attribute => $value) {
                $variation_attributes[sanitize_title($attribute)] = $value;
            }
            $variation_obj->set_attributes($variation_attributes);

            // Set other variation data
            if (isset($variation['sku'])) {
                $variation_obj->set_sku($variation['sku']);
            }
            if (isset($variation['regular_price'])) {
                $variation_obj->set_regular_price($variation['regular_price']);
            }
            if (isset($variation['sale_price'])) {
                $variation_obj->set_sale_price($variation['sale_price']);
            }
            if (isset($variation['stock_quantity'])) {
                $variation_obj->set_manage_stock(true);
                $variation_obj->set_stock_quantity($variation['stock_quantity']);
            }

            // Handle variation image
            if (!empty($variation['image'])) {
                $image_id = $this->handle_product_image(
                    $variation['image'],
                    "Variation {$variation['sku']}",
                    $product_id
                );
                if ($image_id) {
                    $variation_obj->set_image_id($image_id);
                }
            }

            $variation_obj->save();
        }

        // Update parent product
        $product->save();

    } catch (Exception $e) {
        $this->log_error('product', 'Failed to create variations: ' . $e->getMessage(), [
            'product_id' => $product_id,
        ]);
        throw $e;
    }
}

private function generate_product_variations($attributes) {
    $variations = [];
    $attribute_keys = array_keys($attributes);
    $attribute_values = array_values($attributes);
    
    // Generate all possible combinations
    $combinations = $this->generate_attribute_combinations($attribute_values);
    
    foreach ($combinations as $combination) {
        $variation = [
            'attributes' => array_combine($attribute_keys, $combination),
            'sku' => $this->generate_variation_sku($combination),
            'regular_price' => $this->calculate_variation_price($combination),
        ];
        $variations[] = $variation;
    }
    
    return $variations;
}

private function generate_attribute_combinations($arrays, $i = 0) {
    if (!isset($arrays[$i])) {
        return [];
    }
    if ($i == count($arrays) - 1) {
        return array_map(function($v) { return [$v]; }, $arrays[$i]);
    }

    $tmp = $this->generate_attribute_combinations($arrays, $i + 1);
    $result = [];
    foreach ($arrays[$i] as $v) {
        foreach ($tmp as $t) {
            $result[] = array_merge([$v], $t);
        }
    }
    return $result;
}

// Admin Dashboard UI
private function init_admin_dashboard() {
    add_action('admin_menu', [$this, 'add_admin_pages']);
    add_action('admin_init', [$this, 'register_admin_settings']);
    add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
}

public function add_admin_pages() {
    add_menu_page(
        'AI Content Manager',
        'AI Content',
        'manage_options',
        'mfw-dashboard',
        [$this, 'render_dashboard_page'],
        'dashicons-align-left',
        30
    );

    add_submenu_page(
        'mfw-dashboard',
        'Bulk Generation',
        'Bulk Generation',
        'manage_options',
        'mfw-bulk-generation',
        [$this, 'render_bulk_generation_page']
    );

    add_submenu_page(
        'mfw-dashboard',
        'Settings',
        'Settings',
        'manage_options',
        'mfw-settings',
        [$this, 'render_settings_page']
    );
}

// Admin Dashboard Templates and Settings (inside Modern_Framework class)
public function render_dashboard_page() {
    $this->current_time = '2025-05-16 11:38:05';
    $this->current_user = 'maziyarid';
    
    // Stats
    $stats = [
        'total_generated' => $this->get_stat('total_generated'),
        'total_updated' => $this->get_stat('total_updated'),
        'total_products' => $this->get_stat('total_products'),
        'api_usage' => $this->get_api_usage(),
    ];

    // Recent activity
    $recent_logs = $this->get_recent_logs(10);

    // Dashboard template
    ?>
    <div class="wrap mfw-dashboard">
        <h1>AI Content Manager</h1>
        
        <div class="mfw-stats-grid">
            <div class="mfw-stat-card">
                <h3>Generated Content</h3>
                <div class="stat-value"><?php echo number_format($stats['total_generated']); ?></div>
            </div>
            <div class="mfw-stat-card">
                <h3>Updated Content</h3>
                <div class="stat-value"><?php echo number_format($stats['total_updated']); ?></div>
            </div>
            <div class="mfw-stat-card">
                <h3>Products</h3>
                <div class="stat-value"><?php echo number_format($stats['total_products']); ?></div>
            </div>
            <div class="mfw-stat-card">
                <h3>API Usage</h3>
                <div class="stat-value"><?php echo $stats['api_usage']; ?>%</div>
            </div>
        </div>

        <div class="mfw-quick-actions">
            <button class="button button-primary" id="mfw-new-content">Generate New Content</button>
            <button class="button button-secondary" id="mfw-bulk-update">Bulk Update</button>
            <button class="button button-secondary" id="mfw-optimize-all">Optimize All</button>
        </div>

        <div class="mfw-recent-activity">
            <h2>Recent Activity</h2>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>Time</th>
                        <th>Action</th>
                        <th>Status</th>
                        <th>Details</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recent_logs as $log): ?>
                    <tr>
                        <td><?php echo esc_html($log['timestamp']); ?></td>
                        <td><?php echo esc_html($log['action']); ?></td>
                        <td>
                            <span class="mfw-status mfw-status-<?php echo esc_attr($log['status']); ?>">
                                <?php echo esc_html($log['status']); ?>
                            </span>
                        </td>
                        <td><?php echo esc_html($log['details']); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Generation Modal -->
        <div id="mfw-generation-modal" class="mfw-modal">
            <div class="mfw-modal-content">
                <span class="mfw-close">&times;</span>
                <h2>Generate New Content</h2>
                <div class="mfw-generation-form">
                    <form id="mfw-generate-form">
                        <select name="content_type">
                            <option value="post">Blog Post</option>
                            <option value="product">WooCommerce Product</option>
                            <option value="page">Page</option>
                        </select>
                        <textarea name="prompt" placeholder="Enter topic or description..."></textarea>
                        <div class="mfw-form-options">
                            <label>
                                <input type="checkbox" name="generate_images" checked>
                                Generate Images
                            </label>
                            <label>
                                <input type="checkbox" name="optimize_seo" checked>
                                Optimize SEO
                            </label>
                        </div>
                        <button type="submit" class="button button-primary">Generate</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <?php
}

// Settings Management
public function register_admin_settings() {
    // API Settings
    register_setting('mfw_settings', 'mfw_openai_api_key');
    register_setting('mfw_settings', 'mfw_gemini_api_key');
    register_setting('mfw_settings', 'mfw_anthropic_api_key');
    register_setting('mfw_settings', 'mfw_stability_api_key');

    // Content Settings
    register_setting('mfw_settings', 'mfw_default_provider');
    register_setting('mfw_settings', 'mfw_image_generation');
    register_setting('mfw_settings', 'mfw_seo_optimization');
    register_setting('mfw_settings', 'mfw_content_quality');
    register_setting('mfw_settings', 'mfw_update_frequency');

    // Rate Limiting
    register_setting('mfw_settings', 'mfw_daily_limit');
    register_setting('mfw_settings', 'mfw_batch_size');
    register_setting('mfw_settings', 'mfw_concurrent_requests');
}

public function render_settings_page() {
    ?>
    <div class="wrap">
        <h1>AI Content Manager Settings</h1>
        <form method="post" action="options.php">
            <?php settings_fields('mfw_settings'); ?>
            <div class="mfw-settings-grid">
                <!-- API Settings -->
                <div class="mfw-settings-section">
                    <h2>API Configuration</h2>
                    <table class="form-table">
                        <tr>
                            <th>OpenAI API Key</th>
                            <td>
                                <input type="password" name="mfw_openai_api_key" 
                                       value="<?php echo esc_attr(get_option('mfw_openai_api_key')); ?>" class="regular-text">
                            </td>
                        </tr>
                        <tr>
                            <th>Google Gemini API Key</th>
                            <td>
                                <input type="password" name="mfw_gemini_api_key" 
                                       value="<?php echo esc_attr(get_option('mfw_gemini_api_key')); ?>" class="regular-text">
                            </td>
                        </tr>
                    </table>
                </div>

                <!-- Content Settings -->
                <div class="mfw-settings-section">
                    <h2>Content Settings</h2>
                    <table class="form-table">
                        <tr>
                            <th>Default AI Provider</th>
                            <td>
                                <select name="mfw_default_provider">
                                    <option value="gemini" <?php selected(get_option('mfw_default_provider'), 'gemini'); ?>>Google Gemini</option>
                                    <option value="openai" <?php selected(get_option('mfw_default_provider'), 'openai'); ?>>OpenAI</option>
                                    <option value="anthropic" <?php selected(get_option('mfw_default_provider'), 'anthropic'); ?>>Anthropic</option>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th>Update Frequency</th>
                            <td>
                                <select name="mfw_update_frequency">
                                    <option value="daily">Daily</option>
                                    <option value="weekly">Weekly</option>
                                    <option value="monthly">Monthly</option>
                                </select>
                            </td>
                        </tr>
                    </table>
                </div>

                <!-- Rate Limits -->
                <div class="mfw-settings-section">
                    <h2>Rate Limits</h2>
                    <table class="form-table">
                        <tr>
                            <th>Daily Generation Limit</th>
                            <td>
                                <input type="number" name="mfw_daily_limit" 
                                       value="<?php echo esc_attr(get_option('mfw_daily_limit', 100)); ?>">
                            </td>
                        </tr>
                        <tr>
                            <th>Batch Size</th>
                            <td>
                                <input type="number" name="mfw_batch_size" 
                                       value="<?php echo esc_attr(get_option('mfw_batch_size', 10)); ?>">
                            </td>
                        </tr>
                    </table>
                </div>
            </div>
            <?php submit_button(); ?>
        </form>
    </div>
    <?php
}

// Quick implementation of remaining essential methods
private function get_stat($type) {
    global $wpdb;
    switch ($type) {
        case 'total_generated':
            return $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}posts WHERE post_type IN ('post', 'product') AND post_status != 'trash' AND meta_key = 'mfw_generated'");
        case 'total_updated':
            return $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}mfw_logs WHERE type = 'update' AND level = 'info'");
        case 'total_products':
            return $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}posts WHERE post_type = 'product' AND post_status != 'trash'");
        default:
            return 0;
    }
}

private function get_api_usage() {
    $daily_limit = get_option('mfw_daily_limit', 100);
    $used_today = $this->get_daily_usage_count();
    return min(100, round(($used_today / $daily_limit) * 100));
}

private function get_recent_logs($limit = 10) {
    global $wpdb;
    return $wpdb->get_results(
        $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}mfw_logs ORDER BY timestamp DESC LIMIT %d",
            $limit
        ),
        ARRAY_A
    );
}


// Initialize the plugin
function MFW() {
    return Modern_Framework::instance();
}

// Activation/deactivation hooks
register_activation_hook(__FILE__, ['Modern_Framework', 'activate']);
register_deactivation_hook(__FILE__, ['Modern_Framework', 'deactivate']);

// Start the plugin
MFW();
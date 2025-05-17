<?php
/**
 * MFW - Maziyar Fetch Writer (AI Enhanced Super Plugin)
 *
 * @author            maziyar
 * @copyright         2025 maziyarid
 * @license           GPL-2.0-or-later
 *
 * @wordpress-plugin
 * Plugin Name:       MFW AI Enhanced Super Plugin
 * Plugin URI:        https://github.com/maziyarid/MFW
 * Description:       Core framework for Advanced AI Content Generation, PDF creation, Live Chat, and more. Integrates multiple AI providers and extends WordPress capabilities.
 * Version:           2.1.0
 * Requires at least: 6.0
 * Requires PHP:      8.1
 * Author:            Maziyar Moradi
 * Author URI:        https://maziyarid.com/
 * Text Domain:       mfw-ai-super
 * Domain Path:       /languages
 * License:           GPL v2 or later
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

// Define constants
define('MFW_AI_SUPER_VERSION', '2.1.0');
define('MFW_AI_SUPER_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('MFW_AI_SUPER_PLUGIN_URL', plugin_dir_url(__FILE__));
define('MFW_AI_SUPER_PLUGIN_FILE', __FILE__);
define('MFW_AI_SUPER_PLUGIN_BASENAME', plugin_basename(MFW_AI_SUPER_PLUGIN_FILE));
define('MFW_AI_SUPER_TEXT_DOMAIN', 'mfw-ai-super');
define('MFW_AI_SUPER_SETTINGS_SLUG', 'mfw-ai-super-settings');

// Define constants from integrated modules (prefixed)
// These were originally from separate plugins/addons and are now part of the core.
if (!defined('MFW_AI_SUPER_WATSON_NLU_VERSION')) define('MFW_AI_SUPER_WATSON_NLU_VERSION', '2022-08-10');
if (!defined('MFW_AI_SUPER_WATSON_CATEGORY_TAXONOMY')) define('MFW_AI_SUPER_WATSON_CATEGORY_TAXONOMY', 'mfw-super-watson-category');
// GAW Module specific slug (if needed for internal routing/identification)
define('MFW_AI_SUPER_GAW_PLUGIN_SLUG', 'mfw-ai-super-gemini-writer');


/**
 * Main Plugin Class for Maziyar Fetcher Writer AI Super Plugin
 *
 * This class serves as the core orchestrator for the plugin, managing
 * settings, dependencies, hooks, activation/deactivation, and integrating
 * various AI-powered features.
 */
final class Maziyar_Fetcher_Writer_AI_Super {

    // Singleton instance
    private static $_instance = null;

    // Plugin components/dependencies
    public $admin;
    public $settings;
    public $ai_services; // Manages interaction with various AI providers
    public $content_generator; // Handles AI-driven content creation/enhancement
    public $scheduler; // Manages WP Cron events for automation
    public $shortcode_handler; // Registers and processes plugin shortcodes
    public $logger; // Handles logging for the plugin
    public $pdf_generator; // Manages PDF generation feature
    public $live_chat; // Handles the AI-powered live chat feature
    public $dashboard_widget; // Manages the admin dashboard widget
    public $task_queue_manager; // Manages background tasks
    public $addon_services_manager; // Manages integrated "addon" like services (e.g., language processing, image processing)

    // Plugin options
    public $options = [];

    /**
     * Get the singleton instance of the plugin class.
     *
     * @return Maziyar_Fetcher_Writer_AI_Super The single instance of the class.
     */
    public static function instance() {
        if (is_null(self::$_instance)) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }

    /**
     * Constructor.
     *
     * Private to prevent direct instantiation.
     */
    private function __construct() {
        // Load options early
        $this->load_options();

        // Load dependencies (other classes/components)
        $this->load_dependencies();

        // Initialize core plugin functionalities
        $this->init_plugin();

        // Register WordPress hooks (actions and filters)
        $this->init_hooks();

        // Register WP-CLI commands if WP-CLI is available
        $this->register_wp_cli_commands();
    }

    /**
     * Prevent cloning of the instance.
     */
    public function __clone() {
        // Cloning is not allowed
        _doing_it_wrong(__FUNCTION__, __('Cloning is forbidden.', MFW_AI_SUPER_TEXT_DOMAIN), MFW_AI_SUPER_VERSION);
    }

    /**
     * Prevent unserializing of the instance.
     */
    public function __wakeup() {
        // Unserializing is not allowed
        _doing_it_wrong(__FUNCTION__, __('Unserializing is forbidden.', MFW_AI_SUPER_TEXT_DOMAIN), MFW_AI_SUPER_VERSION);
    }

    /**
     * Load plugin options from the database.
     */
    private function load_options() {
        // Retrieve options, merging with defaults to ensure all keys exist
        $this->options = get_option('mfw_ai_super_options', $this->get_default_options());
        // Ensure default options are merged on every load, especially after updates
        $this->options = array_merge($this->get_default_options(), $this->options);
    }

    /**
     * Get a specific plugin option.
     *
     * @param string $key The option key.
     * @param mixed $default The default value if the option is not set.
     * @return mixed The option value or the default value.
     */
    public function get_option($key, $default = null) {
        // Use array_key_exists to check if the key exists, even if the value is null or false
        if (array_key_exists($key, $this->options)) {
            return $this->options[$key];
        }
        // Fallback to default if key is not found in current options (though load_options should prevent this)
        return $default;
    }

    /**
     * Update a specific plugin option.
     *
     * Note: This updates the in-memory options array and saves to the database.
     *
     * @param string $key The option key.
     * @param mixed $value The new value.
     */
    public function update_option($key, $value) {
        $this->options[$key] = $value;
        update_option('mfw_ai_super_options', $this->options);
        // Reload options after saving to ensure consistency (optional but safe)
        $this->load_options();
    }

    /**
     * Get all plugin options.
     *
     * @return array All plugin options, merged with defaults.
     */
    public function get_all_options() {
        // Ensure all default keys are present if not in saved options
        return array_merge($this->get_default_options(), $this->options);
    }

    /**
     * Get the default options for the plugin.
     *
     * @return array An array of default option values.
     */
    public function get_default_options() {
        return [
            // AI Provider Keys & Endpoints
            'openai_api_key' => '',
            'azure_openai_api_key' => '',
            'azure_openai_endpoint' => '',
            'azure_openai_deployment_name' => '', // Required for Azure
            'google_gemini_api_key' => '',
            'xai_grok_api_key' => '', // Assuming Grok will have an API key
            'ollama_api_endpoint' => 'http://localhost:11434',
            'ollama_default_model' => 'llama3',
            'anthropic_api_key' => '', // For Claude
            'ibm_watson_nlu_api_key' => '',
            'ibm_watson_nlu_endpoint' => '',
            'aws_polly_access_key' => '',
            'aws_polly_secret_key' => '',
            'aws_polly_region' => '',
            'azure_speech_api_key' => '',
            'azure_speech_region' => '',
            'azure_vision_api_key' => '',
            'azure_vision_endpoint' => '',

            // Preferred Providers & Fallback
            // These should ideally be managed by the MFW_AI_SUPER_Service_Manager
            // but are kept in options for user configuration.
            'preferred_text_provider' => 'openai', // Default to openai
            'preferred_image_provider' => 'openai_dalle3', // Default to DALL-E 3 via OpenAI
            'preferred_audio_transcription_provider' => 'openai_whisper', // Default to Whisper via OpenAI
            'preferred_tts_provider' => 'openai_tts', // Default to TTS via OpenAI
            'preferred_classification_provider' => 'ollama', // Default to Ollama
            'preferred_embedding_provider' => 'openai_embeddings', // Default to OpenAI Embeddings
            'preferred_chat_provider' => 'openai', // Default to openai for chat

            // Fallback orders should list provider IDs
            'fallback_order_text' => ['openai', 'google_gemini', 'azure_openai', 'anthropic', 'xai_grok', 'ollama'],
            'fallback_order_image' => ['openai_dalle3', 'azure_vision_dalle', /* other image providers */],
            'fallback_order_audio_transcription' => ['openai_whisper', /* other audio providers */],
            'fallback_order_tts' => ['openai_tts', 'aws_polly', 'azure_tts'],
            'fallback_order_classification' => ['ollama', 'openai_embeddings', 'ibm_watson_nlu', 'azure_openai'],
            'fallback_order_chat' => ['openai', 'google_gemini', 'azure_openai', 'anthropic', 'xai_grok', 'ollama'],


            // General Content Settings
            // Post types where AI tools are available in editor/bulk actions
            'auto_content_post_types' => ['post', 'page'],
            // Feature toggles per post type (stored as arrays)
            'enable_summary_generation' => ['post' => true, 'page' => true],
            'enable_takeaways_generation' => ['post' => true],
            'enable_image_alt_text_generation' => ['post' => true, 'page' => true, 'product' => true],
            'enable_tts_feature' => ['post' => true],

            'auto_generate_featured_image_on_creation' => true,
            'default_featured_image_prompt_template' => "A visually appealing image related to the topic: {{title}}",
            'default_image_generation_provider' => 'openai_dalle3', // Specific default for image creation feature

            // SEO & Compatibility
            'seo_compatibility_yoast' => true,
            'seo_compatibility_rankmath' => true,
            'ai_generated_meta_description_enabled' => true,
            'ai_generated_focus_keywords_enabled' => true,

            // Automation (Cron)
            'auto_update_content_interval' => 'daily', // WP Cron schedule name or 'disabled'
            'auto_update_content_prompt' => "Please review and enhance the following content for clarity, engagement, and SEO. Ensure it is up-to-date with the latest information on the topic: {{current_content}}. Original keywords: {{keywords}}",
            'auto_comment_generation_interval' => 'daily',
            'auto_comment_generation_prompt' => "Generate a relevant and insightful comment for this post titled '{{post_title}}'. The comment should encourage discussion and be from the perspective of a reader.",
            'auto_comment_reply_prompt' => "The user commented: '{{user_comment}}' on the post titled '{{post_title}}'. Please draft a helpful and polite reply to this comment.",

            // Shortcode Defaults
            'default_map_shortcode_zoom' => 15,
            'google_maps_api_key' => '', // For [mfw_map]
            'default_cta_text' => 'Learn More!', // For [mfw_cta]
            'default_contact_form_shortcode' => '[contact-form-7 id="your-form-id" title="Contact form 1"]', // Example for [mfw_contact_box]

            // System & Logging
            'ai_assistant_enabled' => true,
            'log_level' => 'INFO', // NONE, ERROR, WARNING, INFO, DEBUG
            'log_to_db_enabled' => true, // Global toggle for database logging
            'max_log_entries_db' => 5000, // Max entries for SYSTEM_LOG type
            'live_chat_max_history_entries' => 1000, // Max entries for CHAT_HISTORY type
            'task_queue_max_log_entries' => 2000, // Max entries for TASK_QUEUE type
            'api_logs_max_entries' => 10000, // Max entries for API_LOGS type


            // Gemini Auto Writer (GAW) Module Defaults (Integrated)
            'gaw_api_key' => '', // Specific Google Gemini API Key for GAW module (optional override)
            'gaw_gemini_model' => 'gemini-1.5-pro-latest',
            'gaw_keywords' => "Example Keyword 1\nExample Keyword 2", // Keywords for bulk generation
            'gaw_prompt' => 'Write a complete and SEO-friendly article about {keyword}. The article should include an introduction, a main body with several subheadings, and a conclusion. The tone of the article should be formal and informative.', // Prompt template
            'gaw_frequency' => 'manual', // Generation frequency ('manual', 'hourly', 'daily', etc.)
            'gaw_post_status' => 'draft', // Default status for generated posts
            'gaw_author_id' => 1, // Default author ID
            'gaw_category_id' => 0, // Default category ID (0 for uncategorized)
            'gaw_tags' => 'ai generated, content', // Default tags (comma-separated)
            'gaw_max_reports' => 100, // Max GAW reports to store
            'gaw_reports_data' => [], // Stores GAW reports (transient or option)

            // PDF Generation Defaults (Integrated)
            'pdf_generation_enabled' => true,
            'pdf_download_prompt_text' => 'Would you like to download this content as a PDF?', // Text for prompt/modal
            'pdf_download_button_text' => 'Download PDF', // Text for the download button
            'pdf_post_types_enabled' => ['post', 'page'], // Post types where PDF download is offered
            'pdf_header_text' => '{{post_title}} - {{site_title}}', // Header template
            'pdf_footer_text' => 'Page {{page_number}} of {{total_pages}} - Generated by {{site_title}} on {{date}}', // Footer template
            'pdf_font_family' => 'dejavusans', // TCPDF standard fonts (DejaVu Sans for UTF-8)
            'pdf_font_size' => 10, // Base font size in points
            'pdf_metadata_author' => '', // PDF metadata author (defaults to site name if empty)
            'pdf_cache_enabled' => true, // Enable PDF caching
            'pdf_custom_css' => "body { font-family: sans-serif; }\nh1 { color: #333; }\n.mfw-pdf-content img { max-width: 100% !important; height: auto; }", // Custom CSS for PDF HTML content

            // Live Chat Defaults (Integrated)
            'live_chat_enabled' => true,
            'live_chat_ai_provider' => 'openai', // Default AI provider for chat
            'live_chat_ai_model' => 'gpt-3.5-turbo', // Default AI model for chat
            'live_chat_system_prompt' => "You are a helpful assistant for the website {{site_title}}. Your goal is to answer user questions based on the site's content (posts, pages, products, documentation). Provide links to relevant pages when possible. If you cannot answer, offer to connect the user with a human via the contact form.", // System prompt for chat AI
            'live_chat_welcome_message' => 'Hello! How can I help you today?', // Welcome message
            'live_chat_knowledge_base_post_types' => ['post', 'page', 'product', 'docs'], // Content types the AI can read from
            'live_chat_contact_form_shortcode' => '[contact-form-7 id="your-chat-contact-form-id" title="Chat Contact Form"]', // Shortcode for fallback contact form
            'live_chat_recipient_email' => get_option('admin_email'), // Email for contact form submissions
            'live_chat_store_history' => true, // Store chat history in DB
            'live_chat_style' => 'jivochat_like', // 'default', 'jivochat_like', 'custom'
            'live_chat_custom_css' => '', // Custom CSS for chat widget


            // Task Queue Defaults (Integrated)
            'task_queue_concurrent_tasks' => 3, // Max tasks to process concurrently
            'task_queue_runner_method' => 'cron', // 'cron' or 'action_scheduler'

            // Access Control
            'ai_features_access_roles' => ['administrator', 'editor'], // User roles with access to AI features/settings

            // API Quota Monitoring (Simplified)
            'quota_monitoring_enabled' => false,
            'quota_alert_threshold_percent' => 80, // Alert when usage exceeds this percent
            'quota_alert_email' => get_option('admin_email'),
            // Per-provider quota limits would be individual settings, e.g., 'quota_openai_requests_monthly'

            // ElasticPress Integration
            'elasticpress_smart_404_enabled' => false,
            'elasticpress_similar_terms_enabled' => false,
        ];
    }

    /**
     * Load plugin dependencies (instantiate other classes).
     */
    private function load_dependencies() {
        // Include necessary files for other classes
        // In a real plugin, each class would likely be in its own file and included here.
        // For this example, assuming they are defined below this class.

        // Instantiate core components, passing the main plugin instance ($this)
        $this->logger = new MFW_AI_SUPER_Logger($this);
        $this->settings = new MFW_AI_SUPER_Settings($this);
        $this->admin = new MFW_AI_SUPER_Admin($this);
        // Assuming MFW_AI_SUPER_Service_Manager, MFW_AI_SUPER_Content_Generator, etc., are defined elsewhere or below
        // For this consolidated file, we'll assume they are defined later.
        $this->ai_services = new MFW_AI_SUPER_Service_Manager($this);
        $this->content_generator = new MFW_AI_SUPER_Content_Generator($this);
        $this->scheduler = new MFW_AI_SUPER_Scheduler($this);
        $this->shortcode_handler = new MFW_AI_SUPER_Shortcode_Handler($this);
        $this->pdf_generator = new MFW_AI_SUPER_PDF_Generator($this);
        $this->live_chat = new MFW_AI_SUPER_Live_Chat($this);
        $this->dashboard_widget = new MFW_AI_SUPER_Dashboard_Widget($this);
        $this->task_queue_manager = new MFW_AI_SUPER_Task_Queue_Manager($this);

        // Initialize integrated "addon" services manager
        $addon_services_to_register = [
            'language_processing' => 'MFW_AI_SUPER_Addon_LanguageProcessingService',
            'image_processing'    => 'MFW_AI_SUPER_Addon_ImageProcessingService',
            // Add other integrated addon services here
        ];
        $this->addon_services_manager = new MFW_AI_SUPER_Addon_ServicesManager($addon_services_to_register, $this);
        $this->addon_services_manager->init_services();
    }

    /**
     * Initialize the core plugin functionalities.
     */
    private function init_plugin() {
        $this->logger->log('Plugin Initialized: MFW AI Enhanced Super Plugin v' . MFW_AI_SUPER_VERSION, 'INFO');
        $this->check_dependencies(); // Check for required/compatible plugins
        $this->task_queue_manager->init(); // Initialize task queue system
        $this->detect
Okay, I've reviewed your `ph.php` plugin file. I see the syntax error you mentioned; it's caused by defining the `MFW_AI_SUPER_Settings` class inside the `load_dependencies` method of the `Maziyar_Fetcher_Writer_AI_Super` class. PHP doesn't allow class definitions to be nested within methods or other classes like that.

To fix this and improve the overall structure and robustness of the plugin, I will rewrite the code. I'll define each class separately and ensure they are correctly instantiated and used by the main plugin class. I'll also address other potential issues like inconsistent option access and improve code clarity and WordPress best practices where possible.

Here is the rewritten code for your plugin:

```php
<?php
/**
 * MFW - Maziyar Fetch Writer (AI Enhanced Super Plugin)
 *
 * @author            maziyar
 * @copyright         2025 maziyarid
 * @license           GPL-2.0-or-later
 *
 * @wordpress-plugin
 * Plugin Name:       MFW AI Enhanced Super Plugin
 * Plugin URI:        [https://github.com/maziyarid/MFW](https://github.com/maziyarid/MFW)
 * Description:       Core framework for Advanced AI Content Generation, PDF creation, Live Chat, and more. Integrates multiple AI providers and extends WordPress capabilities.
 * Version:           2.1.1 // Updated version after rewrite
 * Requires at least: 6.0
 * Requires PHP:      8.1
 * Author:            Maziyar Moradi
 * Author URI:        [https://maziyarid.com/](https://maziyarid.com/)
 * Text Domain:       mfw-ai-super
 * Domain Path:       /languages
 * License:           GPL v2 or later
 * License URI:       [http://www.gnu.org/licenses/gpl-2.0.txt](http://www.gnu.org/licenses/gpl-2.0.txt)
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

// Define constants
define('MFW_AI_SUPER_VERSION', '2.1.1');
define('MFW_AI_SUPER_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('MFW_AI_SUPER_PLUGIN_URL', plugin_dir_url(__FILE__));
define('MFW_AI_SUPER_PLUGIN_FILE', __FILE__);
define('MFW_AI_SUPER_PLUGIN_BASENAME', plugin_basename(MFW_AI_SUPER_PLUGIN_FILE));
define('MFW_AI_SUPER_TEXT_DOMAIN', 'mfw-ai-super');
define('MFW_AI_SUPER_SETTINGS_SLUG', 'mfw-ai-super-settings');

// Define constants from integrated modules (prefixed)
define('MFW_AI_SUPER_GAW_PLUGIN_SLUG', 'mfw-ai-super-gemini-writer');
if (!defined('MFW_AI_SUPER_WATSON_NLU_VERSION')) define('MFW_AI_SUPER_WATSON_NLU_VERSION', '2022-08-10');
if (!defined('MFW_AI_SUPER_WATSON_CATEGORY_TAXONOMY')) define('MFW_AI_SUPER_WATSON_CATEGORY_TAXONOMY', 'mfw-super-watson-category');


/**
 * Main Plugin Class for Maziyar Fetcher Writer AI Super Plugin
 * Orchestrates the loading of dependencies and hooks.
 */
final class Maziyar_Fetcher_Writer_AI_Super {

    private static $_instance = null;

    // Public properties to hold instances of core classes
    public $admin;
    public $settings;
    public $ai_services;
    public $content_generator;
    public $scheduler;
    public $shortcode_handler;
    public $logger;
    public $pdf_generator;
    public $live_chat;
    public $dashboard_widget;
    public $task_queue_manager;
    public $addon_services_manager; // For managing integrated "addon" like services

    private $options = []; // Store plugin options

    /**
     * Private constructor to prevent direct instantiation.
     */
    private function __construct() {
        $this->load_options();
        $this->load_dependencies();
        $this->init_plugin();
        $this->init_hooks();
        $this->register_wp_cli_commands();
    }

    /**
     * Get the singleton instance of the plugin.
     *
     * @return Maziyar_Fetcher_Writer_AI_Super The single instance of the plugin.
     */
    public static function instance() {
        if (is_null(self::$_instance)) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }

    /**
     * Load plugin options from the database.
     */
    private function load_options() {
        // Merge saved options with default options to ensure all keys exist.
        $this->options = array_merge($this->get_default_options(), get_option('mfw_ai_super_options', []));
    }

    /**
     * Get a specific plugin option.
     *
     * @param string $key The option key.
     * @param mixed  $default Default value if the option is not set.
     * @return mixed The option value or the default value.
     */
    public function get_option($key, $default = null) {
        // Use array_key_exists to correctly handle options explicitly set to null or false.
        if (array_key_exists($key, $this->options)) {
            return $this->options[$key];
        }
        // Fallback to default options if key is not in loaded options (shouldn't happen after array_merge, but safe).
        $default_options = $this->get_default_options();
        if (array_key_exists($key, $default_options)) {
            return $default_options[$key];
        }
        return $default;
    }

    /**
     * Update a specific plugin option.
     *
     * @param string $key The option key.
     * @param mixed  $value The new value.
     */
    public function update_option($key, $value) {
        $this->options[$key] = $value;
        update_option('mfw_ai_super_options', $this->options);
    }

    /**
     * Get all plugin options.
     *
     * @return array All plugin options, merged with defaults.
     */
    public function get_all_options() {
        return $this->options; // Options are already merged with defaults on load
    }

    /**
     * Get the default plugin options.
     *
     * @return array An array of default options.
     */
    public function get_default_options() {
        return [
            // AI Provider Keys & Endpoints
            'openai_api_key' => '',
            'azure_openai_api_key' => '',
            'azure_openai_endpoint' => '',
            'azure_openai_deployment_name' => '', // Required for Azure
            'google_gemini_api_key' => '',
            'xai_grok_api_key' => '', // Assuming Grok will have an API key
            'ollama_api_endpoint' => 'http://localhost:11434',
            'ollama_default_model' => 'llama3',
            'anthropic_api_key' => '', // For Claude
            'ibm_watson_nlu_api_key' => '',
            'ibm_watson_nlu_endpoint' => '',
            'aws_polly_access_key' => '',
            'aws_polly_secret_key' => '',
            'aws_polly_region' => '',
            'azure_speech_api_key' => '',
            'azure_speech_region' => '',
            'azure_vision_api_key' => '',
            'azure_vision_endpoint' => '',

            // Preferred Providers & Fallback
            'preferred_text_provider' => 'openai',
            'preferred_image_provider' => 'openai_dalle3',
            'preferred_audio_transcription_provider' => 'openai_whisper',
            'preferred_tts_provider' => 'openai_tts',
            'preferred_classification_provider' => 'ollama',
            'preferred_embedding_provider' => 'openai_embeddings',
            'preferred_chat_provider' => 'openai',

            'fallback_order_text' => ['openai', 'google_gemini', 'azure_openai', 'anthropic', 'xai_grok', 'ollama'],
            'fallback_order_image' => ['openai_dalle3', 'azure_vision_dalle', /* other image providers */],
            'fallback_order_audio_transcription' => ['openai_whisper', /* other audio providers */],
            'fallback_order_tts' => ['openai_tts', 'aws_polly', 'azure_tts'],
            'fallback_order_classification' => ['ollama', 'openai_embeddings', 'ibm_watson_nlu', 'azure_openai'],
            'fallback_order_chat' => ['openai', 'google_gemini', 'azure_openai', 'anthropic', 'xai_grok', 'ollama'],


            // General Content Settings
            'auto_content_post_types' => ['post', 'page'], // Post types where AI tools are available in editor
            'enable_summary_generation' => ['post' => true, 'page' => true], // Per post type
            'enable_takeaways_generation' => ['post' => true], // Per post type
            'enable_image_alt_text_generation' => ['post' => true, 'page' => true, 'product' => true], // Per post type
            'enable_tts_feature' => ['post' => true], // Per post type

            'auto_generate_featured_image_on_creation' => true,
            'default_featured_image_prompt_template' => "A visually appealing image related to the topic: {{title}}",
            'default_image_generation_provider' => 'openai_dalle3', // Specific for image creation

            // SEO & Compatibility
            'seo_compatibility_yoast' => true,
            'seo_compatibility_rankmath' => true,
            'ai_generated_meta_description_enabled' => true,
            'ai_generated_focus_keywords_enabled' => true,

            // Automation (Cron)
            'auto_update_content_interval' => 'daily',
            'auto_update_content_prompt' => "Please review and enhance the following content for clarity, engagement, and SEO. Ensure it is up-to-date with the latest information on the topic: {{current_content}}. Original keywords: {{keywords}}",
            'auto_comment_generation_interval' => 'daily',
            'auto_comment_generation_prompt' => "Generate a relevant and insightful comment for this post titled '{{post_title}}'. The comment should encourage discussion and be from the perspective of a reader.",
            'auto_comment_reply_prompt' => "The user commented: '{{user_comment}}' on the post titled '{{post_title}}'. Please draft a helpful and polite reply to this comment.",

            // Shortcode Defaults
            'default_map_shortcode_zoom' => 15,
            'google_maps_api_key' => '',
            'default_cta_text' => 'Learn More!',
            'default_contact_form_shortcode' => '[contact-form-7 id="your-form-id" title="Contact form 1"]', // Example

            // System & Logging
            'ai_assistant_enabled' => true,
            'log_level' => 'INFO', // NONE, ERROR, WARNING, INFO, DEBUG
            'log_to_db_enabled' => true,
            'max_log_entries_db' => 5000,

            // Gemini Auto Writer (GAW) Module Defaults
            'gaw_api_key' => '', // Specific Google Gemini API Key for GAW module
            'gaw_gemini_model' => 'gemini-1.5-pro-latest',
            'gaw_keywords' => "Example Keyword 1\nExample Keyword 2",
            'gaw_prompt' => 'Write a complete and SEO-friendly article about {keyword}. The article should include an introduction, a main body with several subheadings, and a conclusion. The tone of the article should be formal and informative.',
            'gaw_frequency' => 'manual',
            'gaw_post_status' => 'draft',
            'gaw_author_id' => 1,
            'gaw_category_id' => 0,
            'gaw_tags' => 'ai generated, content',
            'gaw_max_reports' => 100,
            'gaw_reports_data' => [], // Stores GAW reports

            // PDF Generation Defaults
            'pdf_generation_enabled' => true,
            'pdf_download_prompt_text' => 'Would you like to download this content as a PDF?',
            'pdf_download_button_text' => 'Download PDF',
            'pdf_post_types_enabled' => ['post', 'page'], // Post types where PDF download is offered
            'pdf_header_text' => '{{post_title}} - {{site_title}}',
            'pdf_footer_text' => 'Page {{page_number}} of {{total_pages}} - Generated by {{site_title}} on {{date}}',
            'pdf_font_family' => 'helvetica', // Or 'times', 'courier', etc. (TCPDF standard fonts)
            'pdf_font_size' => 10,
            'pdf_metadata_author' => get_bloginfo('name'),
            'pdf_cache_enabled' => true,
            'pdf_custom_css' => "body { font-family: sans-serif; }\nh1 { color: #333; }\n.mfw-pdf-content img { max-width: 100% !important; height: auto; }",

            // Live Chat Defaults
            'live_chat_enabled' => true,
            'live_chat_ai_provider' => 'openai',
            'live_chat_ai_model' => 'gpt-3.5-turbo',
            'live_chat_system_prompt' => "You are a helpful assistant for the website ".get_bloginfo('name').". Your goal is to answer user questions based on the site's content (posts, pages, products, documentation). Provide links to relevant pages when possible. If you cannot answer, offer to connect the user with a human via the contact form.",
            'live_chat_welcome_message' => 'Hello! How can I help you today?',
            'live_chat_contact_form_shortcode' => '[contact-form-7 id="your-chat-contact-form-id" title="Chat Contact Form"]',
            'live_chat_recipient_email' => get_option('admin_email'),
            'live_chat_store_history' => true,
            'live_chat_style' => 'jivochat_like', // 'default', 'jivochat_like', 'custom'
            'live_chat_custom_css' => '',
            'live_chat_knowledge_base_post_types' => ['post', 'page', 'product', 'docs'], // 'docs' for EazyDocs
            'live_chat_max_history_entries' => 1000,

            // Task Queue Defaults
            'task_queue_concurrent_tasks' => 3,
            'task_queue_runner_method' => 'cron', // 'cron' or 'action_scheduler' (if Action Scheduler plugin is active)

            // Access Control
            'ai_features_access_roles' => ['administrator', 'editor'],

            // API Quota Monitoring (Simplified)
            'quota_monitoring_enabled' => false,
            'quota_alert_threshold_percent' => 80,
            'quota_alert_email' => get_option('admin_email'),
            // Per-provider quota limits would be individual settings, e.g., 'quota_openai_requests_monthly'

            // ElasticPress Integration
            'elasticpress_smart_404_enabled' => false,
            'elasticpress_similar_terms_enabled' => false,
            'smart_404_enabled' => false, // Separate from ElasticPress integration
            'smart_404_results_count' => 5,
        ];
    }


    /**
     * Load required plugin dependencies (classes).
     */
    private function load_dependencies() {
        // Include necessary files defining the classes.
        // In a real plugin, these would likely be in separate files and included here.
        // For this example, we assume they are defined below this main class.

        // Instantiate core classes, passing the main plugin instance ($this)
        // so they can access options, logger, and other services.
        $this->logger = new MFW_AI_SUPER_Logger($this);
        $this->settings = new MFW_AI_SUPER_Settings($this);
        $this->admin = new MFW_AI_SUPER_Admin($this);
        $this->ai_services = new MFW_AI_SUPER_Service_Manager($this);
        $this->content_generator = new MFW_AI_SUPER_Content_Generator($this);
        $this->scheduler = new MFW_AI_SUPER_Scheduler($this);
        $this->shortcode_handler = new MFW_AI_SUPER_Shortcode_Handler($this);
        $this->pdf_generator = new MFW_AI_SUPER_PDF_Generator($this);
        $this->live_chat = new MFW_AI_SUPER_Live_Chat($this);
        $this->dashboard_widget = new MFW_AI_SUPER_Dashboard_Widget($this);
        $this->task_queue_manager = new MFW_AI_SUPER_Task_Queue_Manager($this);

        // Initialize integrated "addon" services manager (from mfw-ai-content-addon concept)
        // These services are now part of the super plugin.
        $addon_services_to_register = [
            'language_processing' => 'MFW_AI_SUPER_Addon_LanguageProcessingService',
            'image_processing'    => 'MFW_AI_SUPER_Addon_ImageProcessingService',
        ];
        $this->addon_services_manager = new MFW_AI_SUPER_Addon_ServicesManager($addon_services_to_register, $this);
        $this->addon_services_manager->init_services();
    }

    /**
     * Initialize the plugin.
     */
    private function init_plugin() {
        $this->logger->log('Plugin Initialized: MFW AI Enhanced Super Plugin v' . MFW_AI_SUPER_VERSION, 'INFO');
        $this->check_dependencies();
        $this->task_queue_manager->init();
        $this->detect_compatible_plugins();
    }

    /**
     * Check for required or recommended dependencies (like other plugins).
     */
    private function check_dependencies() {
        // Check ElasticPress dependency if related features are enabled
        if ($this->get_option('elasticpress_smart_404_enabled') || $this->get_option('elasticpress_similar_terms_enabled') || $this->get_option('smart_404_enabled')) {
            if (!class_exists('\ElasticPress\Elasticsearch') && !function_exists('ep_is_activated')) {
                add_action('admin_notices', function() {
                    echo '<div class="notice notice-warning is-dismissible"><p>';
                    echo esc_html__('MFW AI Super Plugin: ElasticPress integration features are enabled in settings, but the ElasticPress plugin is not active or found. Some features may not work.', MFW_AI_SUPER_TEXT_DOMAIN);
                    echo '</p></div>';
                });
                $this->logger->log('ElasticPress dependent feature enabled, but ElasticPress plugin not found.', 'WARNING');
            }
        }
        // Check TCPDF dependency (handled within PDF_Generator class typically)
    }

    /**
     * Detect compatible plugins like WooCommerce, EazyDocs, Action Scheduler.
     */
    private function detect_compatible_plugins() {
        // WooCommerce
        if (class_exists('WooCommerce')) {
            $this->logger->log('WooCommerce detected and supported.', 'INFO');
            // Logic to potentially add 'product' to default post types for features
            // if not already handled by user settings can go here or in settings class.
        }
        // EazyDocs
        if (class_exists('EazyDocs\\Plugin')) { // Check for a core EazyDocs class
            $this->logger->log('EazyDocs detected and supported.', 'INFO');
             // Logic to potentially add 'docs' to default post types for features.
        }
        // Action Scheduler (for task queue)
        if (class_exists('ActionScheduler_QueueRunner')) {
            $this->logger->log('Action Scheduler detected. Can be used for task queue.', 'INFO');
        }
    }


    /**
     * Initialize WordPress hooks.
     */
    private function init_hooks() {
        add_action('plugins_loaded', [$this, 'load_textdomain']);

        // Core Admin Hooks
        add_action('admin_menu', [$this->admin, 'add_admin_pages']);
        add_action('admin_init', [$this->settings, 'register_settings']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);

        // Activation and Deactivation
        register_activation_hook(MFW_AI_SUPER_PLUGIN_FILE, [$this, 'activate']);
        register_deactivation_hook(MFW_AI_SUPER_PLUGIN_FILE, [$this, 'deactivate']);

        // AJAX hooks
        add_action('wp_ajax_mfw_ai_super_perform_task', [$this->admin, 'handle_ai_task_request']);
        add_action('wp_ajax_mfw_ai_super_gaw_test_api', [$this, 'gaw_test_api_callback_super']);
        add_action('wp_ajax_mfw_ai_super_dashboard_widget_data', [$this->dashboard_widget, 'ajax_get_widget_data']);
        add_action('wp_ajax_mfw_ai_super_live_chat_message', [$this->live_chat, 'handle_ajax_message']);
        add_action('wp_ajax_nopriv_mfw_ai_super_live_chat_message', [$this->live_chat, 'handle_ajax_message']); // Allow non-logged-in users for chat
        add_action('wp_ajax_mfw_ai_super_get_chat_history', [$this->live_chat, 'ajax_get_chat_history']);


        // Cron hooks from MFW Core & GAW
        add_action('mfw_ai_super_scheduled_content_update', [$this->scheduler, 'run_scheduled_content_updates']);
        add_action('mfw_ai_super_scheduled_comment_generation', [$this->scheduler, 'run_scheduled_comment_generation']);
        add_action('mfw_ai_super_scheduled_comment_answering', [$this->scheduler, 'run_scheduled_comment_answering']);
        add_action('mfw_ai_super_scheduled_pdf_generation_batch', [$this->scheduler, 'run_scheduled_pdf_generation_batch']);
        add_action('mfw_ai_super_gaw_generate_post_event', [$this, 'gaw_generate_post_super']);

        // Reschedule cron events when relevant options change
        add_action('update_option_mfw_ai_super_options', [$this, 'gaw_schedule_event_on_option_change'], 10, 3);
        add_action('update_option_mfw_ai_super_options', [$this->scheduler, 'reschedule_events_on_option_change'], 10, 3);


        // Shortcode registration
        add_action('init', [$this->shortcode_handler, 'register_shortcodes']);

        // PDF Generation Hooks
        add_action('wp_enqueue_scripts', [$this->pdf_generator, 'enqueue_frontend_scripts_and_styles']);
        add_action('template_redirect', [$this->pdf_generator, 'handle_pdf_download_request']);

        // Live Chat Hooks
        add_action('wp_enqueue_scripts', [$this->live_chat, 'enqueue_frontend_scripts_and_styles']);
        add_action('wp_footer', [$this->live_chat, 'render_chat_widget_html']);

        // Content processing hooks from integrated "addon" services
        // (e.g., meta boxes, bulk actions, image processing)
        $lp_service = $this->addon_services_manager->get_service('language_processing');
        if ($lp_service && method_exists($lp_service, 'register_content_hooks')) {
            $lp_service->register_content_hooks();
        }
        $ip_service = $this->addon_services_manager->get_service('image_processing');
        if ($ip_service && method_exists($ip_service, 'register_attachment_hooks')) {
            $ip_service->register_attachment_hooks();
        }

        // Handle manual generation form from Gemini Auto Writer (adapted)
        add_action('admin_init', [$this, 'gaw_handle_manual_generate_form_super']);
        add_action('admin_notices', [$this, 'gaw_manual_generate_admin_notice_super']);
        add_action('admin_notices', [$this, 'gaw_clear_reports_admin_notice_super']);
        add_action('admin_init', [$this, 'gaw_handle_clear_reports_super']);

        // Dashboard Widget
        add_action('wp_dashboard_setup', [$this->dashboard_widget, 'add_dashboard_widget']);

        // Add hooks for Smart 404 if enabled
        if ($this->get_option('smart_404_enabled') || $this->get_option('elasticpress_smart_404_enabled')) {
             add_action('template_redirect', [$this, 'handle_smart_404']);
        }
    }

    /**
     * Enqueue admin scripts and styles.
     *
     * @param string $hook_suffix The current admin page hook suffix.
     */
    public function enqueue_admin_assets($hook_suffix) {
        // Only enqueue on our plugin's pages, dashboard, or relevant post edit screens
        $is_mfw_page = strpos($hook_suffix, MFW_AI_SUPER_SETTINGS_SLUG) !== false;
        $is_dashboard = $hook_suffix === 'index.php';
        $is_post_edit = in_array($hook_suffix, ['post.php', 'post-new.php']);

        if (!$is_mfw_page && !$is_dashboard && !$is_post_edit) {
            return;
        }

        wp_enqueue_style(MFW_AI_SUPER_TEXT_DOMAIN . '-admin', MFW_AI_SUPER_PLUGIN_URL . 'assets/css/admin-style.css', [], MFW_AI_SUPER_VERSION);

        // Enqueue jQuery UI Sortable for settings page if needed
        if ($is_mfw_page) {
             wp_enqueue_script('jquery-ui-sortable');
        }

        // Enqueue Chart.js for dashboard widget (if not already enqueued by another script)
        if ($is_dashboard && !wp_script_is('chart-js', 'enqueued')) {
            wp_enqueue_script('chart-js', '[https://cdn.jsdelivr.net/npm/chart.js](https://cdn.jsdelivr.net/npm/chart.js)', [], '3.7.0', true);
        }

        wp_enqueue_script(MFW_AI_SUPER_TEXT_DOMAIN . '-admin', MFW_AI_SUPER_PLUGIN_URL . 'assets/js/admin-script.js', ['jquery', 'wp-util'], MFW_AI_SUPER_VERSION, true);

        // Localize script with necessary data
        wp_localize_script(MFW_AI_SUPER_TEXT_DOMAIN . '-admin', 'mfwAiSuper', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('mfw_ai_super_admin_nonce'),
            'gaw_nonce' => wp_create_nonce('mfw_ai_super_gaw_test_api_nonce'), // For GAW API test
            'text_processing' => __('Processing...', MFW_AI_SUPER_TEXT_DOMAIN),
            'dashboard_widget_nonce' => wp_create_nonce('mfw_ai_super_dashboard_widget_nonce'),
            'live_chat_history_nonce' => wp_create_nonce('mfw_ai_super_chat_history_nonce'),
            'task_queue_nonce' => wp_create_nonce('mfw_ai_super_task_queue_nonce'),
            'post_id' => get_the_ID(), // Pass current post ID if on edit screen
        ]);
    }

    /**
     * Load plugin textdomain for internationalization.
     */
    public function load_textdomain() {
        load_plugin_textdomain(
            MFW_AI_SUPER_TEXT_DOMAIN,
            false,
            dirname(MFW_AI_SUPER_PLUGIN_BASENAME) . '/languages'
        );
    }

    /**
     * Activation hook callback.
     */
    public function activate() {
        // Load options before activation to ensure we have defaults
        $this->load_options();

        // Schedule cron events
        $this->scheduler->schedule_events();
        $this->gaw_schedule_event_super(); // Schedule GAW event

        // Create custom database tables
        $this->create_custom_tables();

        // Schedule task queue runner
        $this->task_queue_manager->schedule_cron_runner();

        // Register custom taxonomies if any (e.g. for Watson classification)
        $this->register_custom_taxonomies();

        // Flush rewrite rules if needed (e.g., for custom post types/taxonomies or rewrite endpoints)
        flush_rewrite_rules();

        $this->logger->log('Plugin Activated.', 'INFO');
    }

    /**
     * Deactivation hook callback.
     */
    public function deactivate() {
        // Unschedule cron events
        $this->scheduler->unschedule_events();
        wp_clear_scheduled_hook('mfw_ai_super_gaw_generate_post_event'); // Clear GAW event

        // Unschedule task queue runner
        $this->task_queue_manager->unschedule_cron_runner();

        // Flush rewrite rules
        flush_rewrite_rules();

        $this->logger->log('Plugin Deactivated.', 'INFO');
    }

    /**
     * Register custom taxonomies used by the plugin.
     */
    private function register_custom_taxonomies() {
        // Example for Watson classification taxonomy (from mfw-ai-content-addon)
        $lp_service = $this->addon_services_manager->get_service('language_processing');
        if ($lp_service && method_exists($lp_service, 'register_classification_taxonomy')) {
            $lp_service->register_classification_taxonomy();
        }
    }

    /**
     * Create custom database tables required by the plugin.
     */
    private function create_custom_tables() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

        // Chat History Table
        $table_name_chat_history = $wpdb->prefix . 'mfw_ai_super_chat_history';
        // Only create if live chat history is enabled
        if ($this->get_option('live_chat_store_history', true)) {
            $sql_chat_history = "CREATE TABLE $table_name_chat_history (
                id mediumint(9) NOT NULL AUTO_INCREMENT,
                session_id varchar(255) NOT NULL,
                timestamp datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
                sender enum('user', 'ai', 'system') NOT NULL,
                message longtext NOT NULL,
                ai_provider varchar(50) DEFAULT NULL,
                ai_model varchar(100) DEFAULT NULL,
                user_ip varchar(100) DEFAULT NULL,
                user_id bigint(20) unsigned DEFAULT NULL,
                PRIMARY KEY  (id),
                KEY session_id (session_id),
                KEY timestamp (timestamp)
            ) $charset_collate;";
            dbDelta($sql_chat_history);
            $this->logger->log("Chat history table '{$table_name_chat_history}' checked/created.", 'INFO');
        } else {
             // Optional: Drop table if history is disabled and table exists
             // $wpdb->query("DROP TABLE IF EXISTS $table_name_chat_history");
        }


        // Task Queue Table
        $table_name_task_queue = $wpdb->prefix . 'mfw_ai_super_task_queue';
        $sql_task_queue = "CREATE TABLE $table_name_task_queue (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            task_type varchar(100) NOT NULL,
            task_data longtext NOT NULL,
            status enum('pending', 'processing', 'completed', 'failed', 'retrying', 'on_hold') DEFAULT 'pending' NOT NULL,
            priority tinyint(1) DEFAULT 10 NOT NULL,
            attempts tinyint(1) DEFAULT 0 NOT NULL,
            max_attempts tinyint(1) DEFAULT 3 NOT NULL,
            last_error text DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            scheduled_at datetime DEFAULT NULL,
            processing_started_at datetime DEFAULT NULL,
            completed_at datetime DEFAULT NULL,
            process_id varchar(50) DEFAULT NULL,
            PRIMARY KEY  (id),
            KEY status_priority_scheduled (status, priority, scheduled_at),
            KEY task_type (task_type)
        ) $charset_collate;";
        dbDelta($sql_task_queue);
        $this->logger->log("Task queue table '{$table_name_task_queue}' checked/created.", 'INFO');

        // API Usage Log Table
        $table_name_api_logs = $wpdb->prefix . 'mfw_ai_super_api_logs';
        $sql_api_logs = "CREATE TABLE $table_name_api_logs (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            provider_id varchar(100) NOT NULL,
            feature_id varchar(100) DEFAULT NULL,
            model_id varchar(100) DEFAULT NULL,
            timestamp datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            is_success BOOLEAN NOT NULL,
            tokens_used_prompt int DEFAULT 0,
            tokens_used_completion int DEFAULT 0,
            tokens_used_total int DEFAULT 0,
            cost decimal(10, 6) DEFAULT 0.000000,
            error_message text DEFAULT NULL,
            request_details text DEFAULT NULL,
            response_time_ms int DEFAULT NULL,
            PRIMARY KEY  (id),
            KEY provider_timestamp (provider_id, timestamp),
            KEY feature_id (feature_id)
        ) $charset_collate;";
        dbDelta($sql_api_logs);
        $this->logger->log("API logs table '{$table_name_api_logs}' checked/created.", 'INFO');

        // PDF Cache Table
        $table_name_pdf_cache = $wpdb->prefix . 'mfw_ai_super_pdf_cache';
        // Only create if PDF cache is enabled
        if ($this->get_option('pdf_cache_enabled', true)) {
            $sql_pdf_cache = "CREATE TABLE $table_name_pdf_cache (
                id bigint(20) NOT NULL AUTO_INCREMENT,
                post_id bigint(20) unsigned NOT NULL,
                file_path varchar(255) NOT NULL,
                generated_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
                last_accessed_at datetime DEFAULT NULL,
                PRIMARY KEY  (id),
                UNIQUE KEY post_id (post_id)
            ) $charset_collate;";
            dbDelta($sql_pdf_cache);
            $this->logger->log("PDF Cache table '{$table_name_pdf_cache}' checked/created.", 'INFO');
        } else {
             // Optional: Drop table if cache is disabled and table exists
             // $wpdb->query("DROP TABLE IF EXISTS $table_name_pdf_cache");
        }
    }

    /**
     * Handle Smart 404 page redirection or rendering.
     */
    public function handle_smart_404() {
        // Check if it's a 404 page and Smart 404 is enabled
        if (is_404() && ($this->get_option('smart_404_enabled') || $this->get_option('elasticpress_smart_404_enabled'))) {
            // Prevent WordPress from rendering the default 404 template immediately
            status_header(404);
            nocache_headers();

            // Load a custom template or render content directly
            // You would implement the logic here to find related content using AI/ElasticPress
            // and then display it. This is a placeholder.
            $this->logger->log('Smart 404 triggered.', 'INFO', ['requested_url' => $_SERVER['REQUEST_URI']]);

            // Example: Render a simple message and potentially load a custom template part
            add_filter('template_include', function($template) {
                // Look for a template file in your plugin's theme compatibility directory
                $new_template = MFW_AI_SUPER_PLUGIN_DIR . 'templates/smart-404.php'; // Example path
                if (file_exists($new_template)) {
                    return $new_template;
                }
                // Fallback: Load the theme's 404 template if custom one not found
                return locate_template('404.php');
            });

            // You might also add actions here to hook into the template and display content
            add_action('mfw_ai_super_smart_404_content', [$this, 'render_smart_404_recommendations']);
        }
    }

    /**
     * Render recommendations on the Smart 404 page.
     * This method would contain the logic to query AI/ElasticPress.
     */
    public function render_smart_404_recommendations() {
        // Placeholder for fetching and displaying recommendations
        echo '<h2>' . esc_html__('Content you might be looking for:', MFW_AI_SUPER_TEXT_DOMAIN) . '</h2>';
        echo '<p>' . esc_html__('Searching for relevant content...', MFW_AI_SUPER_TEXT_DOMAIN) . '</p>';

        // TODO: Implement logic to use AI embeddings and/or ElasticPress to find similar content
        // based on the requested URL or keywords extracted from it.
        // Display results here.
        $requested_uri = $_SERVER['REQUEST_URI'];
        $search_query = basename(parse_url($requested_uri, PHP_URL_PATH)); // Simple example

        if ($this->get_option('elasticpress_smart_404_enabled') && function_exists('ep_is_activated') && ep_is_activated()) {
            // Use ElasticPress for search
            // $ep_results = $this->ai_services->search_with_embeddings($search_query, $this->get_option('smart_404_results_count'));
            // Display $ep_results
            echo '<p>ElasticPress search placeholder for: ' . esc_html($search_query) . '</p>'; // Placeholder
        } else {
            // Fallback or alternative AI search logic
            echo '<p>Standard search placeholder for: ' . esc_html($search_query) . '</p>'; // Placeholder
        }
    }


    // --- Integration of Gemini Auto Writer (GAW) Functionality (Adapted) ---
    /**
     * Get a list of available Gemini models for the GAW module.
     *
     * @return array Associative array of model IDs => Model Names.
     */
    public function gaw_get_gemini_models_super() {
        // These models are examples; refer to Google's documentation for the latest.
        return [
            'gemini-pro' => __('Gemini Pro (Legacy - General Text)', MFW_AI_SUPER_TEXT_DOMAIN),
            'gemini-1.0-pro' => __('Gemini 1.0 Pro (Text)', MFW_AI_SUPER_TEXT_DOMAIN),
            'gemini-1.5-flash-latest' => __('Gemini 1.5 Flash (Latest - Speed/Efficiency)', MFW_AI_SUPER_TEXT_DOMAIN),
            'gemini-1.5-pro-latest' => __('Gemini 1.5 Pro (Latest - Advanced Multimodal)', MFW_AI_SUPER_TEXT_DOMAIN),
            // Add other relevant Gemini models here
        ];
    }

    /**
     * AJAX callback to test the GAW API key and model.
     */
    public function gaw_test_api_callback_super() {
        check_ajax_referer('mfw_ai_super_gaw_test_api_nonce', 'nonce'); // Check the nonce passed in JS

        // Check user capability
        if (!current_user_can($this->settings->get_capability_for_feature('view_settings_tab_gaw'))) {
             wp_send_json_error(['message' => __('Permission denied to test API.', MFW_AI_SUPER_TEXT_DOMAIN)]);
             return;
        }

        $api_key = isset($_POST['api_key']) ? sanitize_text_field(wp_unslash($_POST['api_key'])) : '';
        $model = isset($_POST['model']) ? sanitize_text_field(wp_unslash($_POST['model'])) : 'gemini-1.5-pro-latest';

        if (empty($api_key)) {
            wp_send_json_error(['message' => __('GAW Module: API key not provided for testing.', MFW_AI_SUPER_TEXT_DOMAIN)]);
            return;
        }

        $test_prompt = "Hello Gemini! This is a test from the MFW AI Super Plugin (GAW Module). Respond with a short confirmation.";
        $response_data = $this->ai_services->make_request_to_specific_provider(
            'google_gemini',
            $test_prompt,
            [
                'model' => $model,
                'api_key_override' => $api_key, // Use the key from the form for testing
                'max_tokens' => 50,
                'feature_id' => 'gaw_api_test'
            ]
        );

        if ($response_data['success']) {
            wp_send_json_success(['message' => __('GAW Module: API key and model are valid. Sample response received.', MFW_AI_SUPER_TEXT_DOMAIN), 'response' => $response_data['content']]);
        } else {
            $error_message = $response_data['error'] ?? __('An unknown error occurred during the API test.', MFW_AI_SUPER_TEXT_DOMAIN);
            wp_send_json_error(['message' => sprintf(__('GAW Module Error: %s', MFW_AI_SUPER_TEXT_DOMAIN), $error_message)]);
        }
    }

    /**
     * Schedule the GAW post generation cron event based on options.
     */
    public function gaw_schedule_event_super() {
        $freq = $this->get_option('gaw_frequency', 'manual');

        // Clear any existing scheduled event first
        if (wp_next_scheduled('mfw_ai_super_gaw_generate_post_event')) {
            wp_clear_scheduled_hook('mfw_ai_super_gaw_generate_post_event');
        }

        // Schedule the event if frequency is not manual
        if ($freq != 'manual' && !empty($freq)) {
            // Ensure the frequency is a valid cron schedule
            $schedules = wp_get_schedules();
            if (isset($schedules[$freq])) {
                 wp_schedule_event(time(), $freq, 'mfw_ai_super_gaw_generate_post_event');
                 $this->logger->log("GAW module: Scheduled post generation with frequency '{$freq}'.", 'INFO');
            } else {
                 $this->logger->log("GAW module: Attempted to schedule with invalid frequency '{$freq}'. Event not scheduled.", 'WARNING');
            }
        } else {
             $this->logger->log("GAW module: Frequency set to 'manual'. Cron event not scheduled.", 'INFO');
        }
    }

    /**
     * Hook into option update to reschedule GAW event if frequency changes.
     *
     * @param mixed $old_value The old option value.
     * @param mixed $value The new option value.
     * @param string $option_name The option name.
     */
    public function gaw_schedule_event_on_option_change($old_value, $value, $option_name) {
        if ($option_name === 'mfw_ai_super_options') {
            $old_freq = isset($old_value['gaw_frequency']) ? $old_value['gaw_frequency'] : 'manual';
            $new_freq = isset($value['gaw_frequency']) ? $value['gaw_frequency'] : 'manual';
            if ($old_freq !== $new_freq) {
                $this->gaw_schedule_event_super();
                $this->logger->log("GAW module: Frequency changed from '{$old_freq}' to '{$new_freq}'. Rescheduling event.", 'INFO');
            }
        }
    }

    /**
     * Cron callback to generate a post using GAW module.
     * Can also be triggered manually.
     *
     * @param bool $is_manual_trigger Whether the trigger was manual (for notices).
     */
    public function gaw_generate_post_super($is_manual_trigger = false) {
        // Ensure only one instance runs at a time for cron
        if (!$is_manual_trigger && get_transient('mfw_ai_super_gaw_generating')) {
            $this->logger->log("GAW module: Cron trigger skipped, generation already in progress.", 'INFO');
            return;
        }
        if (!$is_manual_trigger) {
             set_transient('mfw_ai_super_gaw_generating', true, 60 * 10); // Set a transient for 10 minutes
        }


        $this->logger->log("GAW module: Starting post generation.", 'INFO', ['manual_trigger' => $is_manual_trigger]);
        $options = $this->get_all_options();

        $keywords_str = $options['gaw_keywords'] ?? '';
        $keywords = !empty($keywords_str) ? array_map('trim', explode("\n", $keywords_str)) : [];
        $keywords = array_filter($keywords); // Remove empty lines

        $prompt_template = $options['gaw_prompt'] ?? 'Write a complete and SEO-friendly article about {keyword}.';
        // Use GAW specific key if set, otherwise fall back to main Google Gemini key
        $api_key = trim(!empty($options['gaw_api_key']) ? $options['gaw_api_key'] : $this->get_option('google_gemini_api_key'));
        $selected_model = $options['gaw_gemini_model'] ?? 'gemini-1.5-pro-latest';
        $post_status = $options['gaw_post_status'] ?? 'draft';
        $author_id = absint($options['gaw_author_id'] ?? 1);
        $category_id = absint($options['gaw_category_id'] ?? 0);
        $tags_input = $options['gaw_tags'] ?? '';

        $error_transient_name = 'mfw_ai_super_gaw_generation_error_message';

        if (empty($keywords)) {
            $message = __('Keyword list for GAW module is empty. No articles to generate.', MFW_AI_SUPER_TEXT_DOMAIN);
            $this->gaw_save_report_super(0, __('No keyword', MFW_AI_SUPER_TEXT_DOMAIN), $selected_model, $message, 'info'); // Use 'info' as it's not a failure, just nothing to do
            $this->logger->log("GAW module: " . $message, 'INFO');
            if ($is_manual_trigger) set_transient($error_transient_name, $message, 60);
             if (!$is_manual_trigger) delete_transient('mfw_ai_super_gaw_generating'); // Clear transient if nothing to do
            return;
        }
        if (empty($prompt_template)) {
            $message = __('Prompt template for GAW module is empty.', MFW_AI_SUPER_TEXT_DOMAIN);
            $this->gaw_save_report_super(0, current($keywords) ?: __('Keyword not specified', MFW_AI_SUPER_TEXT_DOMAIN), $selected_model, $message, 'failed');
            $this->logger->log("GAW module Error: " . $message, 'ERROR');
            if ($is_manual_trigger) set_transient($error_transient_name, $message, 60);
             if (!$is_manual_trigger) delete_transient('mfw_ai_super_gaw_generating');
            return;
        }
        if (empty($api_key)) {
            $message = __('Gemini API key for GAW module is not set.', MFW_AI_SUPER_TEXT_DOMAIN);
            $this->gaw_save_report_super(0, current($keywords) ?: __('Keyword not specified', MFW_AI_SUPER_TEXT_DOMAIN), $selected_model, $message, 'failed');
             $this->logger->log("GAW module Error: " . $message, 'ERROR');
            if ($is_manual_trigger) set_transient($error_transient_name, $message, 60);
             if (!$is_manual_trigger) delete_transient('mfw_ai_super_gaw_generating');
            return;
        }

        $keyword = array_shift($keywords); // Get the first keyword and remove it from the list
        $options['gaw_keywords'] = implode("\n", $keywords); // Update options with remaining keywords
        update_option('mfw_ai_super_options', $options);
        $this->load_options(); // Reload options to reflect change immediately

        $prompt = str_replace('{keyword}', $keyword, $prompt_template);

        $ai_response = $this->ai_services->make_request_to_specific_provider(
            'google_gemini',
            $prompt,
            [
                'model' => $selected_model,
                'api_key_override' => $api_key, // Pass the specific key for this GAW operation
                'timeout' => 180, // Longer timeout for full article generation
                'feature_id' => 'gaw_article_generation'
            ]
        );

        if (!$ai_response['success'] || empty($ai_response['content'])) {
            $error_message = $ai_response['error'] ?? __('Unknown error or empty content from Gemini API for GAW module.', MFW_AI_SUPER_TEXT_DOMAIN);
            $this->gaw_save_report_super(0, $keyword, $selected_model, $error_message, 'failed');
            $this->logger->log("GAW module Error: " . $error_message, 'ERROR', ['keyword' => $keyword]);
            if ($is_manual_trigger) set_transient($error_transient_name, $error_message, 60);
             if (!$is_manual_trigger) delete_transient('mfw_ai_super_gaw_generating');
            return;
        }
        $content = $ai_response['content'];

        // Attempt to extract title if AI generates it, or use keyword
        $post_title = $this->content_generator->extract_title_from_content($content, $keyword);

        $post_data = [
            'post_title'   => sanitize_text_field($post_title),
            'post_content' => wp_kses_post($content),
            'post_status'  => $post_status,
            'post_author'  => $author_id,
        ];
        if($category_id > 0) {
            $post_data['post_category'] = [$category_id];
        }

        $post_id = wp_insert_post($post_data, true);

        if (is_wp_error($post_id)) {
            $error_msg = sprintf(__('Error creating post via GAW module: %s', MFW_AI_SUPER_TEXT_DOMAIN), $post_id->get_error_message());
            $this->gaw_save_report_super(0, $keyword, $selected_model, $error_msg, 'failed');
            $this->logger->log("GAW module Error: " . $error_msg, 'ERROR', ['keyword' => $keyword]);
            if ($is_manual_trigger) set_transient($error_transient_name, $post_id->get_error_message(), 60);
             if (!$is_manual_trigger) delete_transient('mfw_ai_super_gaw_generating');
            return;
        }

        // Add tags
        if (!empty($tags_input)) {
            $tags_array = array_map('trim', explode(',', $tags_input));
            wp_set_post_tags($post_id, $tags_array, false);
        }

        // Handle SEO and Featured Image generation (using main Content_Generator methods)
        $this->content_generator->handle_seo_integration($post_id, $content, [$keyword]);
        if ($this->get_option('auto_generate_featured_image_on_creation')) {
            // Pass the keyword or generated title for the image prompt
            $image_prompt_base = $this->get_option('default_featured_image_prompt_template', 'A visually appealing image related to the topic: {{title}}');
            $image_prompt = str_replace(['{{title}}', '{{keywords}}'], [$post_title, $keyword], $image_prompt_base);
            $this->content_generator->generate_featured_image_for_post($post_id, $image_prompt);
        }

        $this->gaw_save_report_super($post_id, $keyword, $selected_model, __('Article generated successfully via GAW module.', MFW_AI_SUPER_TEXT_DOMAIN), 'success');
        $this->logger->log("GAW module: Successfully generated post ID {$post_id} for keyword '{$keyword}'.", 'INFO');
        if ($is_manual_trigger) set_transient('mfw_ai_super_gaw_generation_success_message', sprintf(__('Article generated successfully via GAW module. Edit post: %s', MFW_AI_SUPER_TEXT_DOMAIN), get_edit_post_link($post_id)), 60);

        if (!$is_manual_trigger) delete_transient('mfw_ai_super_gaw_generating'); // Clear transient on success
    }

    /**
     * Save a GAW generation report.
     *
     * @param int    $post_id The ID of the generated post (0 if failed before post creation).
     * @param string $keyword The keyword used.
     * @param string $model The AI model used.
     * @param string $message A message describing the result.
     * @param string $status The status ('info', 'success', 'failed').
     */
    public function gaw_save_report_super($post_id, $keyword, $model, $message, $status = 'info') {
        $options = $this->get_all_options();
        $reports = isset($options['gaw_reports_data']) && is_array($options['gaw_reports_data']) ? $options['gaw_reports_data'] : [];

        $new_report = [
            'post_id' => absint($post_id),
            'keyword' => sanitize_text_field($keyword),
            'model'   => sanitize_text_field($model),
            'message' => sanitize_text_field($message),
            'status'  => sanitize_text_field($status),
            'date'    => current_time('mysql')
        ];
        array_unshift($reports, $new_report); // Add to the beginning of the array

        $max_reports = $this->get_option('gaw_max_reports', 100);
        if (count($reports) > $max_reports) {
            $reports = array_slice($reports, 0, $max_reports); // Keep only the latest reports
        }

        $options['gaw_reports_data'] = $reports;
        update_option('mfw_ai_super_options', $options);
        $this->load_options(); // Ensure options are reloaded
    }

    /**
     * Handle the manual GAW generation form submission.
     */
    public function gaw_handle_manual_generate_form_super() {
        if (isset($_POST['mfw_ai_super_gaw_action']) && $_POST['mfw_ai_super_gaw_action'] === 'manual_generate') {
            if (!isset($_POST['mfw_ai_super_gaw_manual_generate_nonce']) || !wp_verify_nonce($_POST['mfw_ai_super_gaw_manual_generate_nonce'], 'mfw_ai_super_gaw_manual_generate_action')) {
                wp_die(__('Security error', MFW_AI_SUPER_TEXT_DOMAIN));
            }
            // Check user capability
            if (!current_user_can($this->settings->get_capability_for_feature('gaw_manual_generate'))) {
                wp_die(__('You do not have permission to do this.', MFW_AI_SUPER_TEXT_DOMAIN));
            }

            // Check if a generation is already in progress (prevent double clicks)
             if (get_transient('mfw_ai_super_gaw_generating')) {
                 set_transient('mfw_ai_super_gaw_generation_error_message', __('A GAW generation is already in progress. Please wait.', MFW_AI_SUPER_TEXT_DOMAIN), 60);
                 wp_redirect(admin_url('admin.php?page=' . MFW_AI_SUPER_SETTINGS_SLUG . '-gaw')); // Redirect back to settings page
                 exit;
             }

            // Set transient to indicate generation is in progress
            set_transient('mfw_ai_super_gaw_generating', true, 60 * 10); // Set for 10 minutes

            $this->gaw_generate_post_super(true); // Pass true for manual trigger

            // Redirect to the GAW reports page or settings page
            wp_redirect(admin_url('admin.php?page=' . MFW_AI_SUPER_SETTINGS_SLUG . '-gaw-reports&manual_generated=true'));
            exit;
        }
    }

    /**
     * Display admin notices for GAW manual generation results.
     */
    public function gaw_manual_generate_admin_notice_super() {
        $current_screen = get_current_screen();
        // Ensure this notice only shows on relevant admin pages
        if (!$current_screen || (strpos($current_screen->id, MFW_AI_SUPER_SETTINGS_SLUG) === false && strpos($current_screen->id, 'toplevel_page_' . MFW_AI_SUPER_SETTINGS_SLUG) === false) ) {
            return;
        }

        if (get_transient('mfw_ai_super_gaw_generation_success_message')) {
            $success_message = get_transient('mfw_ai_super_gaw_generation_success_message');
            echo '<div class="notice notice-success is-dismissible"><p>' . wp_kses_post($success_message) . '</p></div>'; // Use wp_kses_post for potential links
            delete_transient('mfw_ai_super_gaw_generation_success_message');
        }
        if (get_transient('mfw_ai_super_gaw_generation_error_message')) {
            $error_message = get_transient('mfw_ai_super_gaw_generation_error_message');
            echo '<div class="notice notice-error is-dismissible"><p>' . sprintf(__('GAW Module: Article generation failed: %s', MFW_AI_SUPER_TEXT_DOMAIN), esc_html($error_message)) . '</p></div>';
            delete_transient('mfw_ai_super_gaw_generation_error_message');
             // Also clear the generating transient on error if it wasn't cleared already
             delete_transient('mfw_ai_super_gaw_generating');
        }
    }

    /**
     * Handle the GAW clear reports form submission.
     */
    public function gaw_handle_clear_reports_super() {
        if (isset($_POST['mfw_ai_super_gaw_action']) && $_POST['mfw_ai_super_gaw_action'] === 'clear_reports') {
            if (!isset($_POST['mfw_ai_super_gaw_clear_reports_nonce']) || !wp_verify_nonce($_POST['mfw_ai_super_gaw_clear_reports_nonce'], 'mfw_ai_super_gaw_clear_reports_action')) {
                wp_die(__('Security error', MFW_AI_SUPER_TEXT_DOMAIN));
            }
             // Check user capability
             if (!current_user_can($this->settings->get_capability_for_feature('gaw_clear_reports'))) {
                wp_die(__('You do not have permission to do this.', MFW_AI_SUPER_TEXT_DOMAIN));
            }

            $options = $this->get_all_options();
            $options['gaw_reports_data'] = [];
            update_option('mfw_ai_super_options', $options);
            $this->load_options(); // Reload options

            wp_redirect(admin_url('admin.php?page=' . MFW_AI_SUPER_SETTINGS_SLUG . '-gaw-reports&reports_cleared=true'));
            exit;
        }
    }

    /**
     * Display admin notice after clearing GAW reports.
     */
    public function gaw_clear_reports_admin_notice_super() {
        $current_screen = get_current_screen();
        $gaw_reports_page_id_part = MFW_AI_SUPER_SETTINGS_SLUG . '-gaw-reports';

        // Check if current screen ID contains the GAW reports page part
        if (!$current_screen || strpos($current_screen->id, $gaw_reports_page_id_part) === false) {
            return;
        }
        if (isset($_GET['reports_cleared']) && $_GET['reports_cleared'] == 'true') {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('GAW Module: All reports cleared successfully.', MFW_AI_SUPER_TEXT_DOMAIN) . '</p></div>';
        }
    }
    // --- End of GAW Integration ---


    // --- WP-CLI Commands Registration ---
    /**
     * Register WP-CLI commands if WP-CLI is available.
     */
    private function register_wp_cli_commands() {
        if (defined('WP_CLI') && WP_CLI) {
            // Ensure the CLI command classes are loaded/available
            // In a real plugin, you'd include files defining these classes here.
            // For this example, we assume they are defined elsewhere or will be.
            if (class_exists('MFW_AI_SUPER_CLI_Content_Commands')) {
                 \WP_CLI::add_command('mfw-ai content', 'MFW_AI_SUPER_CLI_Content_Commands');
            }
             if (class_exists('MFW_AI_SUPER_CLI_Classify_Command')) {
                 \WP_CLI::add_command('mfw-ai classify', 'MFW_AI_SUPER_CLI_Classify_Command');
            }
             if (class_exists('MFW_AI_SUPER_CLI_PDF_Commands')) {
                 \WP_CLI::add_command('mfw-ai pdf', 'MFW_AI_SUPER_CLI_PDF_Commands');
            }
             if (class_exists('MFW_AI_SUPER_CLI_Util_Commands')) {
                 \WP_CLI::add_command('mfw-ai util', 'MFW_AI_SUPER_CLI_Util_Commands');
            }
             if (class_exists('MFW_AI_SUPER_CLI_Task_Commands')) {
                 \WP_CLI::add_command('mfw-ai task', 'MFW_AI_SUPER_CLI_Task_Commands');
            }

            $this->logger->log('WP-CLI commands registered (if classes exist).', 'INFO');
        }
    }
} // END Maziyar_Fetcher_Writer_AI_Super class


/**
 * Logger Class
 * Handles logging messages based on configured level and destination (DB/PHP Error Log).
 */
class MFW_AI_SUPER_Logger {
    private $plugin;
    private $log_levels = ['NONE' => 0, 'ERROR' => 1, 'WARNING' => 2, 'INFO' => 3, 'DEBUG' => 4];
    private $current_log_level_val;
    private $log_to_db;
    private $max_db_log_entries;
    private $system_log_type = 'SYSTEM_LOG'; // Identifier for system logs in the DB table

    /**
     * Constructor.
     *
     * @param Maziyar_Fetcher_Writer_AI_Super $plugin The main plugin instance.
     */
    public function __construct(Maziyar_Fetcher_Writer_AI_Super $plugin) {
        $this->plugin = $plugin;
        // Options are loaded in the main plugin constructor before this is instantiated.
        $log_level_option = $this->plugin->get_option('log_level', 'INFO');
        $this->current_log_level_val = $this->log_levels[strtoupper($log_level_option)] ?? $this->log_levels['INFO'];
        $this->log_to_db = $this->plugin->get_option('log_to_db_enabled', true);
        $this->max_db_log_entries = $this->plugin->get_option('max_log_entries_db', 5000);
    }

    /**
     * Log a message.
     *
     * @param string|array $message The message to log. Can be a string or an array.
     * @param string $level The log level ('NONE', 'ERROR', 'WARNING', 'INFO', 'DEBUG').
     * @param array $context Additional context data.
     */
    public function log($message, $level = 'INFO', $context = []) {
        $level = strtoupper($level);
        if (!isset($this->log_levels[$level]) || $this->log_levels[$level] > $this->current_log_level_val) {
            return; // Don't log if level is below the configured threshold
        }

        // Ensure message is a string for error_log
        $log_message_string = is_string($message) ? $message : wp_json_encode($message, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $context_string = !empty($context) ? wp_json_encode($context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : '';

        // Log to PHP error log if WP_DEBUG_LOG is enabled
        if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG === true) {
            error_log(sprintf("MFW_AI_SUPER_LOG: [%s] [%s] %s %s",
                current_time('mysql'),
                $level,
                $log_message_string,
                $context_string ? 'Context: ' . $context_string : ''
            ));
        }

        // Log to database if enabled
        if ($this->log_to_db) {
            $this->log_to_database($level, $log_message_string, $context_string);
        }
    }

    /**
     * Log a message specifically to the database API log table.
     * Used for system logs, not API call details (which are logged by Service_Manager).
     *
     * @param string $level The log level.
     * @param string $message The log message string.
     * @param string $context_string JSON encoded context string.
     */
    private function log_to_database($level, $message, $context_string) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'mfw_ai_super_api_logs'; // Using this table for system logs too

        // Adapt system log data to fit the API log table structure
        $wpdb->insert(
            $table_name,
            [
                'provider_id' => $this->system_log_type, // Differentiate system logs from API calls
                'feature_id' => $level, // Store log level in feature_id for system logs
                'model_id' => substr($message, 0, 90), // Store start of message in model_id
                'timestamp' => current_time('mysql'),
                'is_success' => !in_array($level, ['ERROR', 'WARNING']), // Crude success mapping
                'error_message' => in_array($level, ['ERROR', 'WARNING']) ? $message : null,
                'request_details' => $context_string, // Store context here
                // Other fields like tokens_used, cost, response_time_ms are not applicable for system logs
            ],
            [
                '%s', '%s', '%s', '%s', '%d', '%s', '%s' // Data formats
            ]
        );

        // Prune old SYSTEM_LOG entries if exceeding max
        $count = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table_name WHERE provider_id = %s", $this->system_log_type));
        if ($count > $this->max_db_log_entries) {
            // Delete the oldest entries
            $limit_to_delete = $count - $this->max_db_log_entries;
            $wpdb->query($wpdb->prepare("DELETE FROM $table_name WHERE provider_id = %s ORDER BY timestamp ASC LIMIT %d", $this->system_log_type, $limit_to_delete));
        }
    }

    /**
     * Retrieve system logs from the database.
     *
     * @param int $limit Number of logs to retrieve.
     * @param int $offset Offset for pagination.
     * @param string|null $level Filter by log level.
     * @return array Database results.
     */
    public function get_logs_from_db($limit = 50, $offset = 0, $level = null) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'mfw_ai_super_api_logs';

        $where_clauses = [$wpdb->prepare("provider_id = %s", $this->system_log_type)];
        if ($level && array_key_exists(strtoupper($level), $this->log_levels)) {
            // For SYSTEM_LOG, level is stored in feature_id
            $where_clauses[] = $wpdb->prepare("feature_id = %s", strtoupper($level));
        }
        $where_sql = implode(" AND ", $where_clauses);

        // Use a more descriptive alias for the message column
        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT timestamp,
                        feature_id as level,
                        model_id as message_start,
                        error_message as full_message,
                        request_details as context,
                        is_success
                 FROM $table_name
                 WHERE $where_sql
                 ORDER BY timestamp DESC
                 LIMIT %d OFFSET %d",
                $limit,
                $offset
            ), ARRAY_A
        );

        // Format results for display
        foreach ($results as &$row) {
            // Combine message_start and full_message for display
            $row['display_message'] = !empty($row['full_message']) ? $row['full_message'] : $row['message_start'];
            unset($row['message_start']);
            unset($row['full_message']);
        }
        unset($row); // Unset reference

        return $results;
    }

    /**
     * Retrieve API usage logs from the database.
     *
     * @param int $limit Number of logs to retrieve.
     * @param int $offset Offset for pagination.
     * @param string|null $provider_filter Filter by provider ID.
     * @return array Database results.
     */
    public function get_api_usage_logs($limit = 50, $offset = 0, $provider_filter = null) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'mfw_ai_super_api_logs';

        $where_clauses = [$wpdb->prepare("provider_id != %s", $this->system_log_type)]; // Exclude system logs
        if ($provider_filter) {
            $where_clauses[] = $wpdb->prepare("provider_id = %s", $provider_filter);
        }
        $where_sql = implode(" AND ", $where_clauses);

        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM $table_name
                 WHERE $where_sql
                 ORDER BY timestamp DESC
                 LIMIT %d OFFSET %d",
                $limit,
                $offset
            ), ARRAY_A
        );
        return $results;
    }
}


/**
 * Admin Class
 * Handles admin menu, pages, settings rendering, and AJAX requests.
 */
class MFW_AI_SUPER_Admin {
    private $plugin;

    /**
     * Constructor.
     *
     * @param Maziyar_Fetcher_Writer_AI_Super $plugin The main plugin instance.
     */
    public function __construct(Maziyar_Fetcher_Writer_AI_Super $plugin) {
        $this->plugin = $plugin;
    }

    /**
     * Add admin menu and sub-menu pages.
     */
    public function add_admin_pages() {
        // Main menu page
        add_menu_page(
            __('MFW AI Super', MFW_AI_SUPER_TEXT_DOMAIN),
            __('MFW AI Super', MFW_AI_SUPER_TEXT_DOMAIN),
            'manage_options', // Minimum capability to see the main menu
            MFW_AI_SUPER_SETTINGS_SLUG,
            [$this, 'render_settings_page'], // Callback for the main page (will redirect or show tabs)
            'dashicons-admin-generic', // Icon
            30 // Position in menu
        );

        // Add settings subpages (handled by the Settings class)
        $this->plugin->settings->add_settings_subpages(MFW_AI_SUPER_SETTINGS_SLUG);

        // Add other top-level sub-menu pages
        // GAW Reports Page (adapted from Gemini Auto Writer)
        add_submenu_page(
            MFW_AI_SUPER_SETTINGS_SLUG,
            __('GAW Reports', MFW_AI_SUPER_TEXT_DOMAIN),
            __('GAW Reports', MFW_AI_SUPER_TEXT_DOMAIN),
            $this->plugin->settings->get_capability_for_feature('gaw_view_reports'),
            MFW_AI_SUPER_SETTINGS_SLUG . '-gaw-reports',
            [$this, 'render_gaw_reports_page_super']
        );

        // Content Tools Page
        add_submenu_page(
            MFW_AI_SUPER_SETTINGS_SLUG,
            __('Content Tools', MFW_AI_SUPER_TEXT_DOMAIN),
            __('Content Tools', MFW_AI_SUPER_TEXT_DOMAIN),
            $this->plugin->settings->get_capability_for_feature('access_content_tools'),
            MFW_AI_SUPER_SETTINGS_SLUG . '-content-tools',
            [$this, 'render_content_tools_page']
        );

        // AI Assistant Page (conditional)
        if ($this->plugin->get_option('ai_assistant_enabled')) {
            add_submenu_page(
                MFW_AI_SUPER_SETTINGS_SLUG,
                __('AI Assistant', MFW_AI_SUPER_TEXT_DOMAIN),
                __('AI Assistant', MFW_AI_SUPER_TEXT_DOMAIN),
                $this->plugin->settings->get_capability_for_feature('use_ai_assistant'),
                MFW_AI_SUPER_SETTINGS_SLUG . '-assistant',
                [$this, 'render_ai_assistant_page']
            );
        }

        // Chat History Page (conditional)
        if ($this->plugin->get_option('live_chat_enabled') && $this->plugin->get_option('live_chat_store_history')) {
            add_submenu_page(
                MFW_AI_SUPER_SETTINGS_SLUG,
                __('Chat History', MFW_AI_SUPER_TEXT_DOMAIN),
                __('Chat History', MFW_AI_SUPER_TEXT_DOMAIN),
                $this->plugin->settings->get_capability_for_feature('view_chat_history'),
                MFW_AI_SUPER_SETTINGS_SLUG . '-chat-history',
                [$this->plugin->live_chat, 'render_chat_history_page']
            );
        }

        // Task Queue Page
        add_submenu_page(
            MFW_AI_SUPER_SETTINGS_SLUG,
            __('Task Queue', MFW_AI_SUPER_TEXT_DOMAIN),
            __('Task Queue', MFW_AI_SUPER_TEXT_DOMAIN),
            $this->plugin->settings->get_capability_for_feature('view_task_queue'),
            MFW_AI_SUPER_SETTINGS_SLUG . '-task-queue',
            [$this->plugin->task_queue_manager, 'render_task_queue_page']
        );

        // Combined System & API Logs Page
        add_submenu_page(
            MFW_AI_SUPER_SETTINGS_SLUG,
            __('System & API Logs', MFW_AI_SUPER_TEXT_DOMAIN),
            __('System & API Logs', MFW_AI_SUPER_TEXT_DOMAIN),
             $this->plugin->settings->get_capability_for_feature('view_system_logs'),
            MFW_AI_SUPER_SETTINGS_SLUG . '-logs',
            [$this, 'render_logs_page']
        );
    }

    /**
     * Render the main settings page (which typically just shows tabs).
     */
    public function render_settings_page() {
        // This page acts as a container for the tabbed interface rendered by MFW_AI_SUPER_Settings::render_settings_tab_page()
        // The actual content is handled by the settings class based on the 'tab' query arg.
        // We just need the wrapper div here.
        echo '<div class="wrap"><h1>' . esc_html($this->get_plugin_title()) . '</h1>';
        // The settings class's render_settings_tab_page method will be called automatically by WordPress
        // based on the 'page' query parameter matching one of the submenu slugs.
        echo '</div>'; // .wrap
    }

    /**
     * Get the plugin title for display in admin pages.
     *
     * @return string The plugin title.
     */
    public function get_plugin_title() {
        // Use the plugin header data for the title
        if (!function_exists('get_plugin_data')) {
            require_once(ABSPATH . 'wp-admin/includes/plugin.php');
        }
        $plugin_data = get_plugin_data(MFW_AI_SUPER_PLUGIN_FILE);
        return $plugin_data['Name'] ?? __('MFW AI Super Plugin', MFW_AI_SUPER_TEXT_DOMAIN);
    }


    /**
     * Render the GAW Reports page.
     */
    public function render_gaw_reports_page_super() {
        // Capability check is done when adding the submenu page, but good practice to double-check.
        if (!current_user_can($this->plugin->settings->get_capability_for_feature('gaw_view_reports'))) {
            wp_die(__('You do not have sufficient permissions to access this page.', MFW_AI_SUPER_TEXT_DOMAIN));
        }
        $options = $this->plugin->get_all_options();
        $reports = isset($options['gaw_reports_data']) && is_array($options['gaw_reports_data']) ? $options['gaw_reports_data'] : [];
        ?>
        <div class="wrap mfw-ai-super-admin-page">
            <h1><?php _e('Gemini Auto Writer (GAW) Module - Generated Articles Report', MFW_AI_SUPER_TEXT_DOMAIN); ?></h1>

            <?php
            // Display manual generation button here for convenience if user has capability
            if (current_user_can($this->plugin->settings->get_capability_for_feature('gaw_manual_generate'))) {
                ?>
                <div class="mfw-gaw-manual-trigger" style="margin-bottom: 20px;">
                    <form method="post" action="<?php echo esc_url(admin_url('admin.php?page=' . MFW_AI_SUPER_SETTINGS_SLUG . '-gaw-reports')); ?>">
                        <?php wp_nonce_field('mfw_ai_super_gaw_manual_generate_action', 'mfw_ai_super_gaw_manual_generate_nonce'); ?>
                        <input type="hidden" name="mfw_ai_super_gaw_action" value="manual_generate">
                        <?php submit_button(__('Generate One Article Now', MFW_AI_SUPER_TEXT_DOMAIN), 'primary', 'mfw_ai_super_gaw_manual_generate_submit', false); ?>
                        <span class="mfw-generation-status" style="margin-left: 10px;"></span>
                        <p class="description"><?php _e('Clicking this button will generate one article using the next keyword from the list in the GAW settings tab.', MFW_AI_SUPER_TEXT_DOMAIN); ?></p>
                    </form>
                </div>
                <?php
            }
            ?>

            <?php if (current_user_can($this->plugin->settings->get_capability_for_feature('gaw_clear_reports'))): ?>
            <form method="post" action="" onsubmit="return confirm('<?php esc_attr_e('Are you sure you want to clear all GAW reports? This action cannot be undone.', MFW_AI_SUPER_TEXT_DOMAIN); ?>');">
                <?php wp_nonce_field('mfw_ai_super_gaw_clear_reports_action', 'mfw_ai_super_gaw_clear_reports_nonce'); ?>
                <input type="hidden" name="mfw_ai_super_gaw_action" value="clear_reports">
                <?php submit_button(__('Clear All GAW Reports', MFW_AI_SUPER_TEXT_DOMAIN), 'delete small', 'mfw_ai_super_gaw_clear_reports_submit', false); ?>
            </form>
            <?php endif; ?>

            <table class="widefat fixed striped mfw-admin-table">
                <thead>
                    <tr>
                        <th scope="col" style="width:150px;"><?php _e('Date', MFW_AI_SUPER_TEXT_DOMAIN); ?></th>
                        <th scope="col"><?php _e('Keyword', MFW_AI_SUPER_TEXT_DOMAIN); ?></th>
                        <th scope="col"><?php _e('Gemini Model', MFW_AI_SUPER_TEXT_DOMAIN); ?></th>
                        <th scope="col"><?php _e('Status', MFW_AI_SUPER_TEXT_DOMAIN); ?></th>
                        <th scope="col"><?php _e('Message/Result', MFW_AI_SUPER_TEXT_DOMAIN); ?></th>
                        <th scope="col" style="width:100px;"><?php _e('Actions', MFW_AI_SUPER_TEXT_DOMAIN); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($reports)): ?>
                        <tr>
                            <td colspan="6"><?php _e('No GAW reports have been recorded yet.', MFW_AI_SUPER_TEXT_DOMAIN); ?></td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($reports as $report): ?>
                            <tr class="status-<?php echo esc_attr($report['status']); ?>">
                                <td><?php echo esc_html(wp_date(get_option('date_format') . ' ' . get_option('time_format'), strtotime($report['date']))); ?></td>
                                <td><?php echo esc_html($report['keyword']); ?></td>
                                <td><?php
                                    $models = $this->plugin->gaw_get_gemini_models_super();
                                    echo isset($models[$report['model']]) ? esc_html($models[$report['model']]) : esc_html($report['model']);
                                ?></td>
                                <td>
                                    <?php if ($report['status'] === 'success'): ?>
                                        <span class="mfw-status-success"><?php _e('Success', MFW_AI_SUPER_TEXT_DOMAIN); ?></span>
                                    <?php elseif ($report['status'] === 'failed'): ?>
                                        <span class="mfw-status-failed"><?php _e('Failed', MFW_AI_SUPER_TEXT_DOMAIN); ?></span>
                                    <?php else: ?>
                                        <span class="mfw-status-info"><?php _e('Info', MFW_AI_SUPER_TEXT_DOMAIN); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo esc_html($report['message']); ?></td>
                                <td>
                                    <?php if (!empty($report['post_id']) && get_post_status($report['post_id'])): // Check if post exists and is not trashed/deleted ?>
                                        <a href="<?php echo esc_url(get_permalink($report['post_id'])); ?>" target="_blank" class="button button-small"><?php _e('View', MFW_AI_SUPER_TEXT_DOMAIN); ?></a>
                                        <a href="<?php echo esc_url(get_edit_post_link($report['post_id'])); ?>" target="_blank" class="button button-small"><?php _e('Edit', MFW_AI_SUPER_TEXT_DOMAIN); ?></a>
                                    <?php else: ?>
                                        <?php _e('Post not created or deleted', MFW_AI_SUPER_TEXT_DOMAIN); ?>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    /**
     * Render the AI Content Tools page.
     */
    public function render_content_tools_page() {
        // Capability check
        if (!current_user_can($this->plugin->settings->get_capability_for_feature('access_content_tools'))) {
            wp_die(__('You do not have sufficient permissions to access this page.', MFW_AI_SUPER_TEXT_DOMAIN));
        }
        echo '<div class="wrap mfw-ai-super-admin-page"><h1>' . esc_html__('AI Content Generation Tools', MFW_AI_SUPER_TEXT_DOMAIN) . '</h1>';
        echo '<p>' . esc_html__('Use these tools to generate content for posts, pages, products, and more.', MFW_AI_SUPER_TEXT_DOMAIN) . '</p>';

        // TODO: Complete the implementation of these forms and their AJAX handlers.
        // The AJAX handler 'mfw_ai_super_perform_task' can be expanded or new specific handlers created.

        echo '<h2>' . esc_html__('Quick Content Actions', MFW_AI_SUPER_TEXT_DOMAIN) . '</h2>';

        // Generate Article from Topic
        echo '<div class="mfw-content-tool-section postbox">';
        echo '<h3 class="hndle"><span>' . esc_html__('Draft Full Article', MFW_AI_SUPER_TEXT_DOMAIN) . '</span></h3>';
        echo '<div class="inside">';
        echo '<p><label for="mfw-topic-input">' . esc_html__('Topic/Keywords:', MFW_AI_SUPER_TEXT_DOMAIN) . '</label>';
        echo '<input type="text" id="mfw-topic-input" class="regular-text" placeholder="' . esc_attr__('e.g., Benefits of renewable energy', MFW_AI_SUPER_TEXT_DOMAIN) . '"/></p>';
        echo '<p><label for="mfw-article-post-type">' . esc_html__('Post Type:', MFW_AI_SUPER_TEXT_DOMAIN) . '</label>';
        echo '<select id="mfw-article-post-type">';
        // Get post types enabled for AI content tools from settings
        $enabled_post_types = $this->plugin->get_option('auto_content_post_types', ['post', 'page']);
        $all_public_post_types = $this->plugin->settings->get_all_public_post_types(true);
        foreach($all_public_post_types as $slug => $label) {
            if (in_array($slug, $enabled_post_types)) {
                echo '<option value="'.esc_attr($slug).'">'.esc_html($label).'</option>';
            }
        }
        echo '</select></p>';
        echo '<button class="button button-primary mfw-ai-task-trigger" data-task="draft_article">' . esc_html__('Draft Article', MFW_AI_SUPER_TEXT_DOMAIN) . '</button>';
        echo '<div class="mfw-task-response" style="margin-top:10px;"></div>';
        echo '</div></div>';

        // Generate Image
        echo '<div class="mfw-content-tool-section postbox">';
        echo '<h3 class="hndle"><span>' . esc_html__('Generate New Image', MFW_AI_SUPER_TEXT_DOMAIN) . '</span></h3>';
        echo '<div class="inside">';
        echo '<p><label for="mfw-image-prompt-input">' . esc_html__('Image Prompt:', MFW_AI_SUPER_TEXT_DOMAIN) . '</label>';
        echo '<input type="text" id="mfw-image-prompt-input" class="regular-text" placeholder="' . esc_attr__('e.g., A futuristic cityscape at dusk', MFW_AI_SUPER_TEXT_DOMAIN) . '"/></p>';
        echo '<p><label for="mfw-image-attach-to-post">' . esc_html__('Attach to Post ID (Optional):', MFW_AI_SUPER_TEXT_DOMAIN) . '</label>';
        echo '<input type="number" id="mfw-image-attach-to-post" class="small-text" placeholder="' . esc_attr__('Post ID', MFW_AI_SUPER_TEXT_DOMAIN) . '"/></p>';
        echo '<button class="button button-primary mfw-ai-task-trigger" data-task="generate_image">' . esc_html__('Generate Image', MFW_AI_SUPER_TEXT_DOMAIN) . '</button>';
        echo '<div class="mfw-task-response" style="margin-top:10px;"></div>';
        echo '</div></div>';

        // Text to Speech (Conditional based on settings)
        if ($this->plugin->get_option('enable_tts_feature', [])) { // Check if TTS is enabled for any post type
             echo '<div class="mfw-content-tool-section postbox">';
             echo '<h3 class="hndle"><span>' . esc_html__('Convert Text to Speech', MFW_AI_SUPER_TEXT_DOMAIN) . '</span></h3>';
             echo '<div class="inside">';
             echo '<p><label for="mfw-tts-input">' . esc_html__('Text to Convert:', MFW_AI_SUPER_TEXT_DOMAIN) . '</label>';
             echo '<textarea id="mfw-tts-input" rows="4" class="large-text" placeholder="' . esc_attr__('Enter text here...', MFW_AI_SUPER_TEXT_DOMAIN) . '"></textarea></p>';
             echo '<button class="button button-primary mfw-ai-task-trigger" data-task="text_to_speech">' . esc_html__('Generate Audio', MFW_AI_SUPER_TEXT_DOMAIN) . '</button>';
             echo '<div class="mfw-task-response" style="margin-top:10px;"></div>'; // Will show download link or player
             echo '</div></div>';
        }


        $this->render_prompt_shortcode_helper();
        echo '</div>'; // .wrap
    }

    /**
     * Render the AI Assistant page.
     */
    public function render_ai_assistant_page() {
        // Capability check
        if (!current_user_can($this->plugin->settings->get_capability_for_feature('use_ai_assistant'))) {
            wp_die(__('You do not have sufficient permissions to access this page.', MFW_AI_SUPER_TEXT_DOMAIN));
        }
        echo '<div class="wrap mfw-ai-super-admin-page"><h1>' . esc_html__('MFW AI Assistant', MFW_AI_SUPER_TEXT_DOMAIN) . '</h1>';
        if (!$this->plugin->get_option('ai_assistant_enabled')) {
            echo '<p>' . esc_html__('AI Assistant is currently disabled in settings.', MFW_AI_SUPER_TEXT_DOMAIN) . '</p>';
            echo '</div>';
            return;
        }
        echo '<p>' . esc_html__('Interact with your WordPress site using natural language. Ask the AI to perform tasks for you.', MFW_AI_SUPER_TEXT_DOMAIN) . '</p>';
        ?>
        <div id="mfw-ai-super-assistant-console">
            <div id="mfw-ai-super-assistant-output" aria-live="polite">
                <p><strong><?php esc_html_e('Assistant:', MFW_AI_SUPER_TEXT_DOMAIN); ?></strong> <?php esc_html_e('Hello! How can I help you today?', MFW_AI_SUPER_TEXT_DOMAIN); ?></p>
            </div>
            <div class="mfw-assistant-input-area">
                <textarea id="mfw-ai-super-assistant-prompt" rows="3" placeholder="<?php esc_attr_e('e.g., Generate a featured image for post ID 123 using a vibrant abstract background.', MFW_AI_SUPER_TEXT_DOMAIN); ?>" aria-label="<?php esc_attr_e('AI Assistant Prompt', MFW_AI_SUPER_TEXT_DOMAIN); ?>"></textarea>
                <button id="mfw-ai-super-assistant-submit" class="button button-primary"><?php esc_html_e('Send Prompt', MFW_AI_SUPER_TEXT_DOMAIN); ?></button>
            </div>
        </div>
        <?php
        echo '</div>'; // .wrap
    }

    /**
     * Handle AJAX requests for AI tasks.
     */
    public function handle_ai_task_request() {
        check_ajax_referer('mfw_ai_super_admin_nonce', '_ajax_nonce');

        // Check user capability for the general AI Assistant/Tools feature
        if (!current_user_can($this->plugin->settings->get_capability_for_feature('use_ai_assistant')) &&
            !current_user_can($this->plugin->settings->get_capability_for_feature('access_content_tools'))) {
            wp_send_json_error(['message' => __('Permission denied to perform AI tasks.', MFW_AI_SUPER_TEXT_DOMAIN)]);
            return;
        }

        $task = isset($_POST['task']) ? sanitize_text_field(wp_unslash($_POST['task'])) : null;
        $prompt = isset($_POST['prompt']) ? sanitize_textarea_field(wp_unslash($_POST['prompt'])) : null; // Used for assistant and image prompts
        $post_id = isset($_POST['post_id']) ? absint(wp_unslash($_POST['post_id'])) : 0; // Used for attaching images, etc.
        $topic = isset($_POST['topic']) ? sanitize_text_field(wp_unslash($_POST['topic'])) : null; // Used for drafting articles
        $post_type = isset($_POST['post_type']) ? sanitize_text_field(wp_unslash($_POST['post_type'])) : 'post'; // Used for drafting articles
        $text_input = isset($_POST['text_input']) ? sanitize_textarea_field(wp_unslash($_POST['text_input'])) : null; // Used for TTS

        $this->plugin->logger->log("AI Task Request Received", "DEBUG", ['task' => $task, 'user_id' => get_current_user_id(), 'data' => $_POST]);

        $response_message = __('Task received.', MFW_AI_SUPER_TEXT_DOMAIN);
        $response_details = null;
        $task_processed_successfully = false;

        try {
            switch ($task) {
                case 'draft_article':
                    if (empty($topic)) {
                        throw new Exception(__('Topic cannot be empty for drafting an article.', MFW_AI_SUPER_TEXT_DOMAIN));
                    }
                    // Ensure the selected post type is enabled for AI content tools
                    $enabled_post_types = $this->plugin->get_option('auto_content_post_types', ['post', 'page']);
                    if (!in_array($post_type, $enabled_post_types)) {
                         throw new Exception(sprintf(__('Article drafting is not enabled for the "%s" post type in plugin settings.', MFW_AI_SUPER_TEXT_DOMAIN), $post_type));
                    }

                    $generation_prompt = $this->plugin->content_generator->prepare_prompt_for_article($topic); // New helper method
                    $new_post_id = $this->plugin->content_generator->generate_content_for_post_type($post_type, $generation_prompt, "Draft: {$topic}", [$topic], get_current_user_id(), 'draft');
                    if ($new_post_id) {
                        $response_message = sprintf(__('Draft article about "%s" created successfully.', MFW_AI_SUPER_TEXT_DOMAIN), esc_html($topic));
                        $response_details = [
                            'post_id' => $new_post_id,
                            'edit_link' => get_edit_post_link($new_post_id),
                            'view_link' => get_permalink($new_post_id)
                        ];
                        $task_processed_successfully = true;
                    } else {
                         // Error would have been logged inside generate_content_for_post_type
                         throw new Exception(__('Failed to create draft article. Check system logs for details.', MFW_AI_SUPER_TEXT_DOMAIN));
                    }
                    break;

                case 'generate_image':
                    if (empty($prompt)) {
                         throw new Exception(__('Image prompt cannot be empty.', MFW_AI_SUPER_TEXT_DOMAIN));
                    }
                    // Check if image generation is enabled for the relevant context (e.g., if post_id is provided, check post type)
                    // For a standalone tool, we might not need a per-post-type check, but good to consider.
                    $attachment_id = $this->plugin->content_generator->generate_and_attach_image($prompt, $post_id);
                    if ($attachment_id) {
                        $image_url = wp_get_attachment_url($attachment_id);
                        $response_message = __('Image generated successfully.', MFW_AI_SUPER_TEXT_DOMAIN);
                        $response_details = ['attachment_id' => $attachment_id, 'image_url' => $image_url];
                        if ($post_id) $response_details['edit_link'] = get_edit_post_link($post_id);
                        $task_processed_successfully = true;
                    } else {
                         // Error would have been logged inside generate_and_attach_image
                         throw new Exception(__('Failed to generate image. Check system logs for details.', MFW_AI_SUPER_TEXT_DOMAIN));
                    }
                    break;

                case 'text_to_speech':
                    if (empty($text_input)) {
                        throw new Exception(__('Text for speech synthesis cannot be empty.', MFW_AI_SUPER_TEXT_DOMAIN));
                    }
                    // Check if TTS feature is generally enabled
                    if (!$this->plugin->get_option('enable_tts_feature', [])) { // Check if the option exists and is not empty
                         throw new Exception(__('Text-to-Speech feature is not enabled in plugin settings.', MFW_AI_SUPER_TEXT_DOMAIN));
                    }
                    // Note: Per-post-type check for TTS is usually done on the frontend rendering the button.
                    // For this tool, we might allow it globally if the setting is enabled.

                    $audio_data = $this->plugin->content_generator->generate_speech_from_text($text_input); // Returns URL or base64 data
                    if ($audio_data) {
                        $response_message = __('Audio generated successfully.', MFW_AI_SUPER_TEXT_DOMAIN);
                        // If it's a URL:
                        if (filter_var($audio_data, FILTER_VALIDATE_URL)) {
                            $response_details = ['audio_url' => $audio_data, 'player' => '<audio controls src="'.esc_url($audio_data).'"></audio>'];
                        } else {
                            // Assume base64 data if not a URL
                            // For security and performance, returning base64 in AJAX is not ideal for large audio.
                            // A better approach is to save it to a temporary file and return a URL.
                            $response_details = ['message' => __('Audio generated. Save functionality for base64 not fully implemented in AJAX handler.', MFW_AI_SUPER_TEXT_DOMAIN)];
                            $this->plugin->logger->log('TTS generated base64 data in AJAX, needs file saving logic.', 'WARNING');
                        }
                        $task_processed_successfully = true;
                    } else {
                         // Error would have been logged inside generate_speech_from_text
                         throw new Exception(__('Failed to generate audio. Check system logs for details.', MFW_AI_SUPER_TEXT_DOMAIN));
                    }
                    break;

                // For AI Assistant direct prompts (not tied to a specific UI button)
                case 'assistant_prompt':
                     if (empty($prompt)) {
                         throw new Exception(__('Assistant prompt cannot be empty.', MFW_AI_SUPER_TEXT_DOMAIN));
                    }
                    // Check if AI Assistant is enabled
                    if (!$this->plugin->get_option('ai_assistant_enabled')) {
                         throw new Exception(__('AI Assistant is not enabled in plugin settings.', MFW_AI_SUPER_TEXT_DOMAIN));
                    }

                    // This is where more complex NLU/intent parsing would go for the assistant
                    // For now, a simple echo or a direct AI call
                    $chat_response = $this->plugin->ai_services->make_request($prompt, [
                        'system_prompt' => $this->plugin->get_option('live_chat_system_prompt', 'You are a helpful assistant.'), // Use chat system prompt
                        'feature_id' => 'ai_assistant_direct_prompt',
                        'model' => $this->plugin->get_option('live_chat_ai_model', null), // Use chat model
                        'provider' => $this->plugin->get_option('live_chat_ai_provider', null), // Use chat provider
                    ]);
                    if ($chat_response['success']) {
                        $response_message = wp_kses_post($chat_response['content']); // Sanitize AI response
                        $task_processed_successfully = true;
                    } else {
                        $error_msg = $chat_response['error'] ?? __('Unknown AI error.', MFW_AI_SUPER_TEXT_DOMAIN);
                        $response_message = sprintf(__('Sorry, I couldn\'t process that. Error: %s', MFW_AI_SUPER_TEXT_DOMAIN), esc_html($error_msg));
                         // Error would have been logged inside make_request
                    }
                    break;

                default:
                    $response_message = sprintf(__('Unknown task: %s', MFW_AI_SUPER_TEXT_DOMAIN), esc_html($task));
                    $this->plugin->logger->log("Received unknown AI task: " . $task, "WARNING", ['_POST' => $_POST]);
                    break;
            }

            if ($task_processed_successfully) {
                wp_send_json_success(['message' => $response_message, 'details' => $response_details]);
            } else {
                 // If task_processed_successfully is false but no exception was thrown, something went wrong
                 // and the message should explain it.
                 wp_send_json_error(['message' => $response_message, 'details' => $response_details]);
            }

        } catch (Exception $e) {
            // Catch any exceptions thrown during task processing
            $error_message = sprintf(__('An error occurred during task processing: %s', MFW_AI_SUPER_TEXT_DOMAIN), $e->getMessage());
            $this->plugin->logger->log("Exception during AI task processing: " . $e->getMessage(), "ERROR", ['task' => $task, 'trace' => $e->getTraceAsString()]);
            wp_send_json_error(['message' => $error_message]);
        }
    }


    /**
     * Render the System & API Logs page.
     */
    public function render_logs_page() {
        // Capability check
        if (!current_user_can($this->plugin->settings->get_capability_for_feature('view_system_logs'))) {
            wp_die(__('You do not have sufficient permissions to access this page.', MFW_AI_SUPER_TEXT_DOMAIN));
        }
        echo '<div class="wrap mfw-ai-super-admin-page"><h1>' . esc_html__('MFW AI Super System & API Logs', MFW_AI_SUPER_TEXT_DOMAIN) . '</h1>';

        $current_tab = isset($_GET['tab']) ? sanitize_key(wp_unslash($_GET['tab'])) : 'system_logs';

        // Pagination setup
        $paged = isset($_GET['paged']) ? absint(wp_unslash($_GET['paged'])) : 1;
        $logs_per_page = 50;
        $offset = ($paged - 1) * $logs_per_page;

        ?>
        <h2 class="nav-tab-wrapper">
            <a href="<?php echo esc_url(admin_url('admin.php?page=' . MFW_AI_SUPER_SETTINGS_SLUG . '-logs&tab=system_logs')); ?>" class="nav-tab <?php echo $current_tab == 'system_logs' ? 'nav-tab-active' : ''; ?>"><?php _e('System Logs', MFW_AI_SUPER_TEXT_DOMAIN); ?></a>
            <a href="<?php echo esc_url(admin_url('admin.php?page=' . MFW_AI_SUPER_SETTINGS_SLUG . '-logs&tab=api_logs')); ?>" class="nav-tab <?php echo $current_tab == 'api_logs' ? 'nav-tab-active' : ''; ?>"><?php _e('API Usage Logs', MFW_AI_SUPER_TEXT_DOMAIN); ?></a>
        </h2>
        <?php

        if ($current_tab === 'system_logs') {
            echo '<h3>' . esc_html__('System Logs (from Database)', MFW_AI_SUPER_TEXT_DOMAIN) . '</h3>';
            if ($this->plugin->get_option('log_to_db_enabled')) {
                $log_level_filter = isset($_GET['log_level']) ? sanitize_text_field(wp_unslash($_GET['log_level'])) : null;
                // TODO: Add a filter dropdown for log level

                $db_logs = $this->plugin->logger->get_logs_from_db($logs_per_page, $offset, $log_level_filter);
                $total_logs = $this->plugin->logger->get_logs_from_db(0, 0, $log_level_filter, true); // Pass true to get count

                if ($db_logs) {
                    echo '<table class="widefat fixed striped mfw-admin-table">';
                    echo '<thead><tr><th>Timestamp</th><th>Level</th><th>Message / Context</th></tr></thead>';
                    echo '<tbody>';
                    foreach ($db_logs as $log_entry) {
                        echo '<tr>';
                        echo '<td>' . esc_html($log_entry['timestamp']) . '</td>';
                        echo '<td><span class="mfw-log-level-' . esc_attr(strtolower($log_entry['level'])) . '">' . esc_html($log_entry['level']) . '</span></td>';
                        echo '<td><strong>' . esc_html(mb_strimwidth($log_entry['display_message'], 0, 150, "...")) . '</strong>';
                        if (!empty($log_entry['context']) && $log_entry['context'] !== 'null') {
                             echo '<br><small>Context: ' . esc_html(mb_strimwidth($log_entry['context'], 0, 200, "...")) . '</small>';
                        }
                        echo '</td>';
                        echo '</tr>';
                    }
                    echo '</tbody></table>';

                    // Pagination
                    $total_pages = ceil($total_logs / $logs_per_page);
                    $current_url = admin_url('admin.php?page=' . MFW_AI_SUPER_SETTINGS_SLUG . '-logs&tab=system_logs');
                    echo '<div class="tablenav"><div class="tablenav-pages">';
                    echo paginate_links([
                        'base' => add_query_arg('paged', '%#%', $current_url),
                        'format' => '',
                        'prev_text' => __('&laquo;'),
                        'next_text' => __('&raquo;'),
                        'total' => $total_pages,
                        'current' => $paged,
                    ]);
                    echo '</div></div>';

                } else {
                    echo '<p>' . esc_html__('No system logs found in the database matching criteria.', MFW_AI_SUPER_TEXT_DOMAIN) . '</p>';
                }
            } else {
                echo '<p>' . esc_html__('Database logging for system events is currently disabled in settings.', MFW_AI_SUPER_TEXT_DOMAIN) . '</p>';
            }
        } elseif ($current_tab === 'api_logs') {
            echo '<h3>' . esc_html__('API Usage Logs (from Database)', MFW_AI_SUPER_TEXT_DOMAIN) . '</h3>';
             if ($this->plugin->get_option('log_to_db_enabled')) { // API logs also depend on this global toggle for now
                $provider_filter = isset($_GET['provider_id']) ? sanitize_text_field(wp_unslash($_GET['provider_id'])) : null;
                // TODO: Add a filter dropdown for provider

                $api_logs = $this->plugin->logger->get_api_usage_logs($logs_per_page, $offset, $provider_filter);
                 $total_api_logs = $this->plugin->logger->get_api_usage_logs(0, 0, $provider_filter, true); // Pass true for count

                 if ($api_logs) {
                    echo '<table class="widefat fixed striped mfw-admin-table">';
                    echo '<thead><tr><th>Timestamp</th><th>Provider</th><th>Feature</th><th>Model</th><th>Success</th><th>Tokens (P/C/T)</th><th>Cost</th><th>Time (ms)</th><th>Error/Details</th></tr></thead>';
                    echo '<tbody>';
                    foreach ($api_logs as $log_entry) {
                        echo '<tr>';
                        echo '<td>' . esc_html($log_entry['timestamp']) . '</td>';
                        echo '<td>' . esc_html($log_entry['provider_id']) . '</td>';
                        echo '<td>' . esc_html($log_entry['feature_id']) . '</td>';
                        echo '<td>' . esc_html($log_entry['model_id']) . '</td>';
                        echo '<td>' . ($log_entry['is_success'] ? '<span class="mfw-status-success">Yes</span>' : '<span class="mfw-status-failed">No</span>') . '</td>';
                        echo '<td>' . esc_html($log_entry['tokens_used_prompt'] . '/' . $log_entry['tokens_used_completion'] . '/' . $log_entry['tokens_used_total']) . '</td>';
                        echo '<td>' . esc_html($log_entry['cost'] > 0 ? number_format($log_entry['cost'], 6) : '-') . '</td>';
                        echo '<td>' . esc_html($log_entry['response_time_ms'] ?? '-') . '</td>';
                        echo '<td>';
                        if (!$log_entry['is_success'] && !empty($log_entry['error_message'])) {
                            echo '<small class="mfw-error-message">' . esc_html(mb_strimwidth($log_entry['error_message'], 0, 100, "...")) . '</small>';
                        } elseif (!empty($log_entry['request_details']) && $log_entry['request_details'] !== 'null') {
                             echo '<small>Details: ' . esc_html(mb_strimwidth($log_entry['request_details'], 0, 100, "...")) . '</small>';
                        }
                        echo '</td>';
                        echo '</tr>';
                    }
                    echo '</tbody></table>';

                    // Pagination
                    $total_pages = ceil($total_api_logs / $logs_per_page);
                    $current_url = admin_url('admin.php?page=' . MFW_AI_SUPER_SETTINGS_SLUG . '-logs&tab=api_logs');
                    echo '<div class="tablenav"><div class="tablenav-pages">';
                    echo paginate_links([
                        'base' => add_query_arg('paged', '%#%', $current_url),
                        'format' => '',
                        'prev_text' => __('&laquo;'),
                        'next_text' => __('&raquo;'),
                        'total' => $total_pages,
                        'current' => $paged,
                    ]);
                    echo '</div></div>';

                } else {
                    echo '<p>' . esc_html__('No API usage logs found in the database matching criteria.', MFW_AI_SUPER_TEXT_DOMAIN) . '</p>';
                }
            } else {
                echo '<p>' . esc_html__('Database logging for API calls is currently disabled in settings (relies on the main DB logging toggle).', MFW_AI_SUPER_TEXT_DOMAIN) . '</p>';
            }
        }


        echo '<h3>' . esc_html__('PHP Error Log (Filtered MFW Excerpt if WP_DEBUG_LOG enabled)', MFW_AI_SUPER_TEXT_DOMAIN) . '</h3>';
        if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG === true && ini_get('error_log') && is_readable(ini_get('error_log'))) {
            echo '<textarea rows="15" style="width:100%; font-family: monospace; white-space: pre;" readonly>';
            $log_file_path = ini_get('error_log');
            // Try to read the last N lines or lines containing MFW_AI_SUPER_LOG
            // This is a simplified approach for brevity. A more robust solution would parse the file.
            $file_content = @file_get_contents($log_file_path, false, null, -50000); // Read last ~50KB
            if ($file_content) {
                $lines = explode("\n", $file_content);
                $mfw_log_lines = [];
                foreach($lines as $line){
                    if(strpos($line, 'MFW_AI_SUPER_LOG:') !== false){
                        $mfw_log_lines[] = $line;
                    }
                }
                if(!empty($mfw_log_lines)){
                    // Show last 100 MFW lines, reversed to show newest at the bottom of the textarea
                    echo esc_textarea(implode("\n", array_slice($mfw_log_lines, -100)));
                } else {
                     echo esc_textarea(__('No MFW_AI_SUPER_LOG entries found in the recent part of the PHP error log.', MFW_AI_SUPER_TEXT_DOMAIN));
                }
            } else {
                echo esc_textarea(sprintf(__('Could not read PHP error log file at: %s', MFW_AI_SUPER_TEXT_DOMAIN), esc_html($log_file_path)));
            }
            echo '</textarea>';
        } else {
             echo '<p>' . esc_html__('WP_DEBUG_LOG is not enabled, or the PHP error_log path is not set or readable. Plugin logs (if level permits and DB logging is off) are sent to the standard PHP error log.', MFW_AI_SUPER_TEXT_DOMAIN) . '</p>';
        }
        echo '</div>'; // .wrap
    }

    /**
     * Render a helper section for prompt shortcodes and placeholders.
     */
    public function render_prompt_shortcode_helper() {
        echo '<div class="mfw-prompt-helper postbox">';
        echo '<h2 class="hndle"><span>' . esc_html__('Prompt Shortcode Helper & Dynamic Placeholders', MFW_AI_SUPER_TEXT_DOMAIN) . '</span></h2>';
        echo '<div class="inside">';
        echo '<p>' . esc_html__('Use these placeholders within your generation prompts for dynamic content. For `[mfw_keywords]`, list keywords one per line in a textarea if the UI provides one, or pass as a comma-separated list.', MFW_AI_SUPER_TEXT_DOMAIN) . '</p>';
        echo '<h4>Dynamic Placeholders (Replaced Before Sending to AI):</h4>';
        echo '<ul>';
        echo '<li><code>{{post_title}}</code> - Current post title (if context is a post).</li>';
        echo '<li><code>{{post_content}}</code> - Current post content (full, if context is a post). Use with caution for long content.</li>';
        echo '<li><code>{{post_excerpt}}</code> - Current post excerpt (if context is a post).</li>';
        echo '<li><code>{{post_keywords}}</code> - Keywords associated with the post (e.g., from categories/tags, or SEO plugin if integrated).</li>';
        echo '<li><code>{{current_date}}</code> - Today\'s date.</li>';
        echo '<li><code>{{site_title}}</code> - Your website\'s title.</li>';
        echo '<li><code>{{user_comment}}</code> - (For comment replies) The user\'s comment text.</li>';
        echo '<li><code>{{selected_text}}</code> - (For editor tools) The text currently selected by the user.</li>';
        echo '<li><code>{{page_url}}</code> - The URL of the current page (for frontend context).</li>';
        echo '<li><code>{{site_url}}</code> - Your website\'s main URL.</li>';
        echo '</ul>';
        echo '<h4>Instructional Shortcodes (Interpreted by AI or Processed by Plugin):</h4>';
        echo '<ul>';
        echo '<li><code>[mfw_keyword]</code> - Primary keyword for generation.</li>';
        echo '<li><code>[mfw_keywords]</code> - List of keywords.</li>';
        echo '<li><code>[mfw_title]</code> - Placeholder for AI to generate a title.</li>';
        echo '<li><code>[mfw_image prompt="your image description"]</code> - Instruction for AI to generate an image.</li>';
        echo '<li><code>[mfw_table data_prompt="describe data for table"]</code> - Instruction for AI to generate a table.</li>';
        echo '<li><code>[mfw_map address="City, Country" zoom="14"]</code> - (Frontend display) Inserts a map.</li>';
        echo '<li><code>[mfw_cta text="Sign Up Now!" link="#signup"]</code> - (Frontend display) Inserts a Call-to-Action.</li>';
        echo '<li><code>[mfw_contact_box]</code> - (Frontend display) Inserts the default contact form.</li>';
        echo '</ul>';
        echo '<p>' . esc_html__('Example prompt for a new post:', MFW_AI_SUPER_TEXT_DOMAIN) . '</p>';
        echo '<pre><code>Write an article about {{post_keywords}}. The desired title is inspired by "{{post_title}}".
Include a section about its benefits.
[mfw_image prompt="A vibrant illustration related to {{post_keywords}}"]
Here is a table of comparisons: [mfw_table data_prompt="Compare feature A, B, C for {{post_keywords}}"]
Conclude with a call to action. [mfw_cta text="Learn more about {{post_keywords}}"]</code></pre>';
        echo '</div></div>';
    }

    // TODO: Implement render methods for other admin pages (Content Tools, AI Assistant, Chat History, Task Queue)
    // These methods would contain the HTML structure and calls to relevant classes to fetch/display data.
}


/**
 * Settings Class
 * Handles plugin settings registration, sections, fields, and rendering.
 */
class MFW_AI_SUPER_Settings {
    private $plugin;
    private $option_group = 'mfw_ai_super_options_group';
    private $option_name = 'mfw_ai_super_options';
    private $tabs = [];

    /**
     * Constructor.
     *
     * @param Maziyar_Fetcher_Writer_AI_Super $plugin The main plugin instance.
     */
    public function __construct(Maziyar_Fetcher_Writer_AI_Super $plugin) {
        $this->plugin = $plugin;
        $this->tabs = [
            'general' => __('General & Logging', MFW_AI_SUPER_TEXT_DOMAIN),
            'api_keys' => __('API Keys & Providers', MFW_AI_SUPER_TEXT_DOMAIN),
            'content_gen' => __('Content Features', MFW_AI_SUPER_TEXT_DOMAIN),
            'gaw' => __('GAW Module', MFW_AI_SUPER_TEXT_DOMAIN), // Gemini Auto Writer
            'pdf_gen' => __('PDF Generation', MFW_AI_SUPER_TEXT_DOMAIN),
            'live_chat' => __('Live Chat', MFW_AI_SUPER_TEXT_DOMAIN),
            'automation' => __('Automation & Cron', MFW_AI_SUPER_TEXT_DOMAIN),
            'advanced' => __('Advanced & Shortcodes', MFW_AI_SUPER_TEXT_DOMAIN),
            'access_control' => __('Access Control', MFW_AI_SUPER_TEXT_DOMAIN),
        ];
    }

    /**
     * Add settings subpages to the admin menu.
     *
     * @param string $parent_slug The slug of the parent menu page.
     */
    public function add_settings_subpages($parent_slug) {
        foreach ($this->tabs as $tab_slug => $tab_title) {
            add_submenu_page(
                $parent_slug,
                $tab_title, // Page Title
                $tab_title, // Menu Title
                $this->get_capability_for_feature('view_settings_tab_' . $tab_slug), // Capability per tab
                $parent_slug . '-' . $tab_slug, // Menu Slug (e.g., mfw-ai-super-settings-api_keys)
                [$this, 'render_settings_tab_page'] // Callback to render the tab content
            );
        }
    }

    /**
     * Render the content for a specific settings tab page.
     */
    public function render_settings_tab_page() {
        // Determine the current tab slug from the page query argument
        $current_screen = get_current_screen();
        $base_slug = MFW_AI_SUPER_SETTINGS_SLUG . '-';
        $current_tab_slug = str_replace([$current_screen->parent_slug . '_page_', $base_slug], '', $current_screen->id);

        // Fallback logic to determine the current tab slug
        if (empty($current_tab_slug) || !array_key_exists($current_tab_slug, $this->tabs)) {
            if (isset($_GET['page']) && strpos(wp_unslash($_GET['page']), $base_slug) === 0) {
                $current_tab_slug = substr(wp_unslash($_GET['page']), strlen($base_slug));
            } else {
                 $current_tab_slug = 'general'; // Default to general tab if all else fails
            }
        }

        // Validate the current tab slug
        if (!array_key_exists($current_tab_slug, $this->tabs)) {
            wp_die(__('Invalid settings page.', MFW_AI_SUPER_TEXT_DOMAIN));
            return;
        }

        // Check user capability for this specific tab
        if (!current_user_can($this->get_capability_for_feature('view_settings_tab_' . $current_tab_slug))) {
            wp_die(__('You do not have sufficient permissions to access this page.', MFW_AI_SUPER_TEXT_DOMAIN));
            return;
        }

        echo '<div class="wrap mfw-ai-super-settings-page">';
        echo '<h1>' . esc_html($this->plugin->admin->get_plugin_title()) . ' - ' . esc_html($this->tabs[$current_tab_slug]) . '</h1>';

        // Navigation tabs
        echo '<h2 class="nav-tab-wrapper">';
        foreach ($this->tabs as $slug => $title) {
            $active_class = ($slug === $current_tab_slug) ? 'nav-tab-active' : '';
            $url = admin_url('admin.php?page=' . MFW_AI_SUPER_SETTINGS_SLUG . '-' . $slug);
            echo '<a href="' . esc_url($url) . '" class="nav-tab ' . esc_attr($active_class) . '">' . esc_html($title) . '</a>';
        }
        echo '</h2>';

        echo '<form method="post" action="options.php">';
        settings_fields($this->option_group); // Handles nonce, action, option_page fields
        // do_settings_sections uses the page slug for the fields
        do_settings_sections(MFW_AI_SUPER_SETTINGS_SLUG . '-' . $current_tab_slug);
        submit_button();
        echo '</form>';

        // Display prompt helper on relevant tabs
        if (in_array($current_tab_slug, ['content_gen', 'gaw', 'automation', 'live_chat'])) {
            $this->plugin->admin->render_prompt_shortcode_helper();
        }

        echo '</div>'; // .wrap
    }


    /**
     * Register plugin settings, sections, and fields with WordPress.
     */
    public function register_settings() {
        register_setting(
            $this->option_group, // Option group
            $this->option_name,  // Option name
            [$this, 'sanitize_options'] // Sanitize callback
        );

        // Add sections and fields for each tab
        foreach (array_keys($this->tabs) as $tab_slug) {
            $method_name = "add_{$tab_slug}_settings_fields";
            if (method_exists($this, $method_name)) {
                $section_id = 'mfw_ai_super_' . $tab_slug . '_section'; // Unique section ID for the tab
                $page_slug_for_fields = MFW_AI_SUPER_SETTINGS_SLUG . '-' . $tab_slug; // Page slug where this section and its fields appear

                add_settings_section(
                    $section_id,
                    null, // Section title (can be empty if fields are self-explanatory)
                    null, // Section callback (optional description)
                    $page_slug_for_fields
                );
                // Call the method to add fields to this section and page
                $this->$method_name($page_slug_for_fields, $section_id);
            }
        }
    }

    /**
     * Determine the required capability for a specific feature or settings tab.
     *
     * @param string $feature_key A key identifying the feature (e.g., 'view_settings_tab_api_keys', 'gaw_manual_generate').
     * @return string The required capability.
     */
    public function get_capability_for_feature($feature_key) {
        $default_cap = 'manage_options'; // Default to admin only
        $feature_roles = $this->plugin->get_option('ai_features_access_roles', ['administrator', 'editor']);
        $user = wp_get_current_user();

        // Admins can always do everything related to this plugin's settings and core features
        if (current_user_can('administrator')) {
            return 'manage_options';
        }

        // Default capability for most settings tabs for users in defined roles
        if (strpos($feature_key, 'view_settings_tab_') === 0) {
             if (array_intersect($feature_roles, $user->roles)) {
                return 'edit_posts'; // Allow editors (or roles in $feature_roles) to view settings pages
            }
        }

        // Specific features capabilities
        switch ($feature_key) {
            case 'gaw_manual_generate':
            case 'gaw_clear_reports':
            case 'access_content_tools':
            case 'use_ai_assistant':
                if (array_intersect($feature_roles, $user->roles)) {
                    return 'edit_posts'; // Users in allowed roles can perform these actions
                }
                break;
            case 'gaw_view_reports':
            case 'view_chat_history':
            case 'view_task_queue':
            case 'view_system_logs': // Includes API logs view
                if (array_intersect($feature_roles, $user->roles)) {
                     // Editors and above can view reports and logs
                    if(in_array('editor', $user->roles) || in_array('administrator', $user->roles)) {
                        return 'edit_others_posts'; // Capability typically needed to view content/data of others
                    }
                }
                break;
        }
        return $default_cap; // Fallback to admin only for anything not explicitly defined
    }


    // --- Methods to add fields for each tab ---

    /**
     * Add settings fields for the General tab.
     *
     * @param string $page_slug The slug of the settings page.
     * @param string $section_id The ID of the settings section.
     */
    private function add_general_settings_fields($page_slug, $section_id) {
        add_settings_field( 'log_level', __('Logging Level', MFW_AI_SUPER_TEXT_DOMAIN), [$this, 'render_select_field'], $page_slug, $section_id,
            [
                'id' => 'log_level',
                'options' => ['NONE' => 'None', 'ERROR' => 'Errors Only', 'WARNING' => 'Warnings & Errors', 'INFO' => 'Info, Warnings & Errors', 'DEBUG' => 'Debug (All)'],
                'description' => __('Select the level of detail for plugin logs.', MFW_AI_SUPER_TEXT_DOMAIN)
            ]
        );
        add_settings_field( 'log_to_db_enabled', __('Enable Database Logging', MFW_AI_SUPER_TEXT_DOMAIN), [$this, 'render_checkbox_field'], $page_slug, $section_id,
            [
                'id' => 'log_to_db_enabled',
                'description' => __('Store system and API logs in the database. If disabled, logs (except fatal errors) might only go to PHP error log if WP_DEBUG_LOG is on.', MFW_AI_SUPER_TEXT_DOMAIN)
            ]
        );
        add_settings_field( 'max_log_entries_db', __('Max DB Log Entries (System)', MFW_AI_SUPER_TEXT_DOMAIN), [$this, 'render_number_field'], $page_slug, $section_id,
            [
                'id' => 'max_log_entries_db',
                'min' => 100,
                'max' => 10000,
                'step' => 100,
                'description' => __('Maximum number of system log entries to keep in the database. API logs and Chat History have their own limits.', MFW_AI_SUPER_TEXT_DOMAIN)
            ]
        );
        add_settings_field( 'ai_assistant_enabled', __('Enable AI Assistant Page', MFW_AI_SUPER_TEXT_DOMAIN), [$this, 'render_checkbox_field'], $page_slug, $section_id,
            [
                'id' => 'ai_assistant_enabled',
                'description' => __('Enable the AI assistant page for direct interaction via prompts in the admin area.', MFW_AI_SUPER_TEXT_DOMAIN)
            ]
        );
    }

    /**
     * Add settings fields for the API Keys & Providers tab.
     *
     * @param string $page_slug The slug of the settings page.
     * @param string $section_id The ID of the settings section.
     */
    private function add_api_keys_settings_fields($page_slug, $section_id) {
        // Get configuration for all registered AI providers
        $all_providers_config = $this->plugin->ai_services->get_all_provider_configs();

        // Add fields for each provider's specific settings (API key, endpoint, etc.)
        foreach ($all_providers_config as $provider_id => $config) {
            // Add API Key field
            if (!empty($config['api_key_option'])) {
                 add_settings_field( $config['api_key_option'], sprintf(__('%s API Key', MFW_AI_SUPER_TEXT_DOMAIN), $config['name']), [$this, 'render_text_field'], $page_slug, $section_id,
                    [
                        'id' => $config['api_key_option'],
                        'type' => 'password',
                        'description' => $config['description'] ?? sprintf(__('Enter your API key for %s.', MFW_AI_SUPER_TEXT_DOMAIN), $config['name'])
                    ]
                );
            }
            // Add Endpoint/URL field if configured
            if (!empty($config['endpoint_option'])) {
                add_settings_field( $config['endpoint_option'], sprintf(__('%s Endpoint/URL', MFW_AI_SUPER_TEXT_DOMAIN), $config['name']), [$this, 'render_text_field'], $page_slug, $section_id,
                    [
                        'id' => $config['endpoint_option'],
                        'type' => 'url',
                        'placeholder' => $config['default_endpoint'] ?? '',
                        'description' => sprintf(__('API endpoint for %s (e.g., Azure, Ollama). Default: %s', MFW_AI_SUPER_TEXT_DOMAIN), $config['name'], $config['default_endpoint'] ?? 'N/A')
                    ]
                );
            }
            // Add Region field if configured (e.g., for AWS, Azure)
            if (!empty($config['region_option'])) {
                 add_settings_field( $config['region_option'], sprintf(__('%s Region', MFW_AI_SUPER_TEXT_DOMAIN), $config['name']), [$this, 'render_text_field'], $page_slug, $section_id,
                    [
                        'id' => $config['region_option'],
                        'type' => 'text',
                        'description' => sprintf(__('Region for %s (e.g., Azure, AWS).', MFW_AI_SUPER_TEXT_DOMAIN), $config['name'])
                    ]
                );
            }
            // Add Deployment Name/ID field if configured (e.g., for Azure)
            if (!empty($config['deployment_option'])) {
                 add_settings_field( $config['deployment_option'], sprintf(__('%s Deployment Name/ID', MFW_AI_SUPER_TEXT_DOMAIN), $config['name']), [$this, 'render_text_field'], $page_slug, $section_id,
                    [
                        'id' => $config['deployment_option'],
                        'type' => 'text',
                        'description' => sprintf(__('Deployment Name/ID for %s (Azure).', MFW_AI_SUPER_TEXT_DOMAIN), $config['name'])
                    ]
                );
            }
             // Add Default Model field if configured (e.g., for Ollama, custom endpoints)
             if (!empty($config['model_option'])) {
                 add_settings_field( $config['model_option'], sprintf(__('%s Default Model', MFW_AI_SUPER_TEXT_DOMAIN), $config['name']), [$this, 'render_text_field'], $page_slug, $section_id,
                    [
                        'id' => $config['model_option'],
                        'type' => 'text',
                        'placeholder' => $config['default_model'] ?? '',
                        'description' => sprintf(__('Default model for %s (e.g., llama3, codellama). Default: %s', MFW_AI_SUPER_TEXT_DOMAIN), $config['name'], $config['default_model'] ?? 'N/A')
                    ]
                );
            }
        }

        // Add fields for Preferred Providers & Fallback Orders per capability
        $capabilities = $this->plugin->ai_services->get_all_capabilities();
        foreach ($capabilities as $cap_key => $cap_label) {
            // Get providers that support this capability
            $providers_for_cap = $this->plugin->ai_services->get_providers_by_capability($cap_key, true); // Get IDs and names
            if (empty($providers_for_cap)) continue; // Skip if no providers support this capability

            // Add Preferred Provider dropdown
            add_settings_field( "preferred_{$cap_key}_provider", sprintf(__('Preferred Provider for %s', MFW_AI_SUPER_TEXT_DOMAIN), $cap_label), [$this, 'render_select_field'], $page_slug, $section_id,
                [
                    'id' => "preferred_{$cap_key}_provider",
                    'options' => ['' => __('Select Provider', MFW_AI_SUPER_TEXT_DOMAIN)] + $providers_for_cap, // Add empty option
                    'description' => sprintf(__('Select the default AI provider for %s tasks.', MFW_AI_SUPER_TEXT_DOMAIN), strtolower($cap_label))
                ]
            );
            // Add Fallback Order sortable list (requires JS implementation)
            add_settings_field( "fallback_order_{$cap_key}", sprintf(__('Fallback Order for %s', MFW_AI_SUPER_TEXT_DOMAIN), $cap_label), [$this, 'render_sortable_multi_select_field'], $page_slug, $section_id,
                [
                    'id' => "fallback_order_{$cap_key}",
                    'options' => $providers_for_cap, // Use the same list of providers
                    'description' => __('Drag and drop the providers to set the fallback order. If the preferred provider fails, these will be tried in the specified order.', MFW_AI_SUPER_TEXT_DOMAIN)
                ]
            );
        }
    }

    /**
     * Add settings fields for the Content Features tab.
     *
     * @param string $page_slug The slug of the settings page.
     * @param string $section_id The ID of the settings section.
     */
    private function add_content_gen_settings_fields($page_slug, $section_id) {
        add_settings_field( 'auto_content_post_types', __('Enable AI Tools for Post Types', MFW_AI_SUPER_TEXT_DOMAIN), [$this, 'render_multi_checkbox_field'], $page_slug, $section_id,
            [
                'id' => 'auto_content_post_types',
                'options' => $this->get_all_public_post_types(true),
                'description' => __('Select post types where MFW AI content generation tools (meta boxes, bulk actions) will be available in the editor.', MFW_AI_SUPER_TEXT_DOMAIN)
            ]
        );

        $features_to_toggle = [
            'summary_generation' => __('Generate Post Summaries (Excerpts)', MFW_AI_SUPER_TEXT_DOMAIN),
            'takeaways_generation' => __('Generate Key Takeaways (Displayed at top of post)', MFW_AI_SUPER_TEXT_DOMAIN),
            'image_alt_text_generation' => __('Generate Image Alt Text (on upload/update)', MFW_AI_SUPER_TEXT_DOMAIN),
            'tts_feature' => __('Enable Text-to-Speech "Read to Me" button', MFW_AI_SUPER_TEXT_DOMAIN),
        ];

        foreach ($features_to_toggle as $feature_key => $label) {
            add_settings_field( 'enable_' . $feature_key, $label, [$this, 'render_post_type_toggle_field'], $page_slug, $section_id,
                [
                    'option_key_name' => 'enable_' . $feature_key,
                    'options' => $this->get_all_public_post_types(true), // Get all CPTs for toggling
                    'description' => sprintf(__('Enable "%s" for selected post types.', MFW_AI_SUPER_TEXT_DOMAIN), $label),
                ]
            );
        }

        add_settings_field( 'auto_generate_featured_image_on_creation', __('Auto-generate Featured Image on AI Content Creation', MFW_AI_SUPER_TEXT_DOMAIN), [$this, 'render_checkbox_field'], $page_slug, $section_id,
            [
                'id' => 'auto_generate_featured_image_on_creation',
                'description' => __('Automatically attempt to generate and set a featured image when new content is created via AI (e.g., GAW module, AI Assistant).', MFW_AI_SUPER_TEXT_DOMAIN)
            ]
        );
        add_settings_field( 'default_featured_image_prompt_template', __('Default Featured Image Prompt Template', MFW_AI_SUPER_TEXT_DOMAIN), [$this, 'render_textarea_field'], $page_slug, $section_id,
            [
                'id' => 'default_featured_image_prompt_template',
                'rows' => 2,
                'description' => __('Template for generating featured images. Use {{title}}, {{keywords}}. Example: "A vibrant illustration related to {{title}}".', MFW_AI_SUPER_TEXT_DOMAIN)
            ]
        );
        add_settings_field( 'default_image_generation_provider', __('Default Provider for New Images', MFW_AI_SUPER_TEXT_DOMAIN), [$this, 'render_select_field'], $page_slug, $section_id,
            [
                'id' => 'default_image_generation_provider',
                'options' => ['' => __('Select Provider', MFW_AI_SUPER_TEXT_DOMAIN)] + $this->plugin->ai_services->get_providers_by_capability('image_generation', true),
                'description' => __('Provider used by default for generating new images (e.g., DALL-E). This is for features like "auto-generate featured image".', MFW_AI_SUPER_TEXT_DOMAIN)
            ]
        );

        // SEO Integration
        add_settings_field( 'seo_compatibility_yoast', __('Yoast SEO Integration', MFW_AI_SUPER_TEXT_DOMAIN), [$this, 'render_checkbox_field'], $page_slug, $section_id,
            [
                'id' => 'seo_compatibility_yoast',
                'description' => __('Attempt to populate Yoast SEO fields (focus keyword, meta description) during AI generation. Ensure Yoast SEO is active.', MFW_AI_SUPER_TEXT_DOMAIN)
            ]
        );
         add_settings_field( 'seo_compatibility_rankmath', __('Rank Math Integration', MFW_AI_SUPER_TEXT_DOMAIN), [$this, 'render_checkbox_field'], $page_slug, $section_id,
            [
                'id' => 'seo_compatibility_rankmath',
                'description' => __('Attempt to populate Rank Math fields during AI generation. Ensure Rank Math is active.', MFW_AI_SUPER_TEXT_DOMAIN)
            ]
        );
        add_settings_field( 'ai_generated_meta_description_enabled', __('AI-Generated Meta Descriptions for SEO', MFW_AI_SUPER_TEXT_DOMAIN), [$this, 'render_checkbox_field'], $page_slug, $section_id,
            [
                'id' => 'ai_generated_meta_description_enabled',
                'description' => __('Enable AI to suggest/generate meta descriptions for supported SEO plugins.', MFW_AI_SUPER_TEXT_DOMAIN)
            ]
        );
        add_settings_field( 'ai_generated_focus_keywords_enabled', __('AI-Generated Focus Keywords for SEO', MFW_AI_SUPER_TEXT_DOMAIN), [$this, 'render_checkbox_field'], $page_slug, $section_id,
            [
                'id' => 'ai_generated_focus_keywords_enabled',
                'description' => __('Enable AI to suggest/generate focus keywords for supported SEO plugins.', MFW_AI_SUPER_TEXT_DOMAIN)
            ]
        );
    }

    /**
     * Add settings fields for the GAW Module tab.
     *
     * @param string $page_slug The slug of the settings page.
     * @param string $section_id The ID of the settings section.
     */
    private function add_gaw_settings_fields($page_slug, $section_id) {
        add_settings_field( 'gaw_api_key', __('GAW Module: Google Gemini API Key', MFW_AI_SUPER_TEXT_DOMAIN), [$this, 'render_text_field'], $page_slug, $section_id,
            [
                'id' => 'gaw_api_key',
                'type' => 'password',
                'description' => __('Specific API key for the GAW module. If empty, the main Google Gemini key from the "API Keys & Providers" tab will be used.', MFW_AI_SUPER_TEXT_DOMAIN)
            ]
        );
        add_settings_field( 'gaw_gemini_model', __('GAW Module: Gemini Model', MFW_AI_SUPER_TEXT_DOMAIN), [$this, 'render_select_field'], $page_slug, $section_id,
            [
                'id' => 'gaw_gemini_model',
                'options' => $this->plugin->gaw_get_gemini_models_super(),
                'description' => __('Select the Google Gemini model for GAW bulk article generation.', MFW_AI_SUPER_TEXT_DOMAIN),
                 'custom_buttons' => '<button type="button" id="mfw_ai_super_gaw_test_api_key_btn" class="button">' . esc_html__('Test GAW API Key & Model', MFW_AI_SUPER_TEXT_DOMAIN) . '</button><span id="mfw_ai_super_gaw_test_api_status" style="margin-left:10px;"></span>'
            ]
        );
        add_settings_field( 'gaw_keywords', __('GAW Module: Keywords (one per line)', MFW_AI_SUPER_TEXT_DOMAIN), [$this, 'render_textarea_field'], $page_slug, $section_id,
            [
                'id' => 'gaw_keywords',
                'rows' => 10,
                'description' => __('Each keyword will be used to generate one article. Articles are generated from top to bottom, one keyword per scheduled run or manual trigger.', MFW_AI_SUPER_TEXT_DOMAIN)
            ]
        );
        add_settings_field( 'gaw_prompt', __('GAW Module: Master Prompt', MFW_AI_SUPER_TEXT_DOMAIN), [$this, 'render_textarea_field'], $page_slug, $section_id,
            [
                'id' => 'gaw_prompt',
                'rows' => 5,
                'description' => __('The main prompt template for generating articles. Use {keyword} to insert the current keyword. Example: "Write a comprehensive and SEO-friendly article about {keyword}."', MFW_AI_SUPER_TEXT_DOMAIN)
            ]
        );
        add_settings_field( 'gaw_frequency', __('GAW Module: Generation Frequency', MFW_AI_SUPER_TEXT_DOMAIN), [$this, 'render_select_field'], $page_slug, $section_id,
            [
                'id' => 'gaw_frequency',
                'options' => $this->get_cron_schedules_with_manual(),
                'description' => __('How often new articles are generated from the keyword list. "Manual" requires using the "Generate One Article Now" button.', MFW_AI_SUPER_TEXT_DOMAIN)
            ]
        );
        add_settings_field( 'gaw_post_status', __('GAW Module: Post Status', MFW_AI_SUPER_TEXT_DOMAIN), [$this, 'render_select_field'], $page_slug, $section_id,
            [
                'id' => 'gaw_post_status',
                'options' => ['draft' => 'Draft', 'pending' => 'Pending Review', 'publish' => 'Published'],
                'description' => __('Status for newly generated articles.', MFW_AI_SUPER_TEXT_DOMAIN)
            ]
        );
        add_settings_field( 'gaw_author_id', __('GAW Module: Post Author', MFW_AI_SUPER_TEXT_DOMAIN), [$this, 'render_user_dropdown_field'], $page_slug, $section_id,
            [
                'id' => 'gaw_author_id',
                'description' => __('Select the author for articles generated by the GAW module.', MFW_AI_SUPER_TEXT_DOMAIN)
            ]
        );
        add_settings_field( 'gaw_category_id', __('GAW Module: Post Category', MFW_AI_SUPER_TEXT_DOMAIN), [$this, 'render_category_dropdown_field'], $page_slug, $section_id,
            [
                'id' => 'gaw_category_id',
                'description' => __('Default category for articles generated by GAW.', MFW_AI_SUPER_TEXT_DOMAIN)
            ]
        );
        add_settings_field( 'gaw_tags', __('GAW Module: Tags (comma-separated)', MFW_AI_SUPER_TEXT_DOMAIN), [$this, 'render_text_field'], $page_slug, $section_id,
            [
                'id' => 'gaw_tags',
                'description' => __('Comma-separated list of tags to add to generated posts (e.g., ai content, tech news).', MFW_AI_SUPER_TEXT_DOMAIN)
            ]
        );
         add_settings_field( 'gaw_max_reports', __('GAW Module: Max Reports to Keep', MFW_AI_SUPER_TEXT_DOMAIN), [$this, 'render_number_field'], $page_slug, $section_id,
            [
                'id' => 'gaw_max_reports',
                'min' => 10,
                'max' => 500,
                'step' => 10,
                'description' => __('Maximum number of GAW generation reports to store. Older reports will be removed.', MFW_AI_SUPER_TEXT_DOMAIN)
            ]
        );
        // Add the manual generate button field
        add_settings_field( 'gaw_manual_generate', __('GAW Module: Manual Generation', MFW_AI_SUPER_TEXT_DOMAIN), [$this, 'render_gaw_manual_generate_button'], $page_slug, $section_id,
            [
                'description' => __('Click to generate one article using the first keyword from the list above. The keyword will then be removed from the list.', MFW_AI_SUPER_TEXT_DOMAIN)
            ]
        );
    }

    /**
     * Add settings fields for the PDF Generation tab.
     *
     * @param string $page_slug The slug of the settings page.
     * @param string $section_id The ID of the settings section.
     */
    private function add_pdf_gen_settings_fields($page_slug, $section_id) {
        add_settings_field( 'pdf_generation_enabled', __('Enable PDF Generation Feature', MFW_AI_SUPER_TEXT_DOMAIN), [$this, 'render_checkbox_field'], $page_slug, $section_id,
            [
                'id' => 'pdf_generation_enabled',
                'description' => __('Allow generation of PDF files from posts and other content. Adds a download button/prompt on enabled post types.', MFW_AI_SUPER_TEXT_DOMAIN)
            ]
        );
        add_settings_field( 'pdf_post_types_enabled', __('Enable PDF Download for Post Types', MFW_AI_SUPER_TEXT_DOMAIN), [$this, 'render_multi_checkbox_field'], $page_slug, $section_id,
            [
                'id' => 'pdf_post_types_enabled',
                'options' => $this->get_all_public_post_types(true),
                'description' => __('Select post types where the PDF download prompt/button will appear on the frontend.', MFW_AI_SUPER_TEXT_DOMAIN)
            ]
        );
        add_settings_field( 'pdf_download_prompt_text', __('PDF Download Prompt Text (Optional)', MFW_AI_SUPER_TEXT_DOMAIN), [$this, 'render_text_field'], $page_slug, $section_id,
            [
                'id' => 'pdf_download_prompt_text',
                'description' => __('Text shown to visitors asking if they want to download the PDF (e.g., for a modal). If empty, only the download button will be shown.', MFW_AI_SUPER_TEXT_DOMAIN)
            ]
        );
         add_settings_field( 'pdf_download_button_text', __('PDF Download Button Text', MFW_AI_SUPER_TEXT_DOMAIN), [$this, 'render_text_field'], $page_slug, $section_id,
            [
                'id' => 'pdf_download_button_text',
                'description' => __('Text for the PDF download button.', MFW_AI_SUPER_TEXT_DOMAIN)
            ]
        );
        add_settings_field( 'pdf_header_text', __('PDF Header Text', MFW_AI_SUPER_TEXT_DOMAIN), [$this, 'render_text_field'], $page_slug, $section_id,
            [
                'id' => 'pdf_header_text',
                'description' => __('Text for PDF header. Use {{post_title}}, {{site_title}}, {{date}}. Leave blank for no header.', MFW_AI_SUPER_TEXT_DOMAIN)
            ]
        );
        add_settings_field( 'pdf_footer_text', __('PDF Footer Text', MFW_AI_SUPER_TEXT_DOMAIN), [$this, 'render_text_field'], $page_slug, $section_id,
            [
                'id' => 'pdf_footer_text',
                'description' => __('Text for PDF footer. Use {{page_number}}, {{total_pages}}, {{site_title}}, {{date}}. Leave blank for no footer.', MFW_AI_SUPER_TEXT_DOMAIN)
            ]
        );
        add_settings_field( 'pdf_font_family', __('PDF Font Family', MFW_AI_SUPER_TEXT_DOMAIN), [$this, 'render_select_field'], $page_slug, $section_id,
            [
                'id' => 'pdf_font_family',
                'options' => $this->plugin->pdf_generator->get_tcpdf_fonts(), // Get available TCPDF fonts
                'description' => __('Select a base font for PDF content. Ensure the font supports characters in your content. DejaVu Sans recommended for broad UTF-8 support.', MFW_AI_SUPER_TEXT_DOMAIN)
            ]
        );
        add_settings_field( 'pdf_font_size', __('PDF Base Font Size', MFW_AI_SUPER_TEXT_DOMAIN), [$this, 'render_number_field'], $page_slug, $section_id,
            [
                'id' => 'pdf_font_size',
                'min' => 8,
                'max' => 16,
                'step' => 1,
                'description' => __('Default font size (in points) for PDF content.', MFW_AI_SUPER_TEXT_DOMAIN)
            ]
        );
        add_settings_field( 'pdf_metadata_author', __('PDF Default Author Metadata', MFW_AI_SUPER_TEXT_DOMAIN), [$this, 'render_text_field'], $page_slug, $section_id,
            [
                'id' => 'pdf_metadata_author',
                'description' => __('Default author name for PDF metadata. Defaults to site title if blank.', MFW_AI_SUPER_TEXT_DOMAIN)
            ]
        );
         add_settings_field( 'pdf_custom_css', __('PDF Custom CSS', MFW_AI_SUPER_TEXT_DOMAIN), [$this, 'render_textarea_field'], $page_slug, $section_id,
            [
                'id' => 'pdf_custom_css',
                'rows' => 8,
                'description' => __('Apply custom CSS styles to the generated PDFs. Use with caution, as not all CSS is supported by PDF generators. Affects HTML content passed to TCPDF.', MFW_AI_SUPER_TEXT_DOMAIN)
            ]
        );
        add_settings_field( 'pdf_cache_enabled', __('Cache Generated PDFs', MFW_AI_SUPER_TEXT_DOMAIN), [$this, 'render_checkbox_field'], $page_slug, $section_id,
            [
                'id' => 'pdf_cache_enabled',
                'description' => __('Store generated PDFs in the database for faster delivery. The cache for a post is cleared when the post is updated.', MFW_AI_SUPER_TEXT_DOMAIN)
            ]
        );
    }

    /**
     * Add settings fields for the Live Chat tab.
     *
     * @param string $page_slug The slug of the settings page.
     * @param string $section_id The ID of the settings section.
     */
    private function add_live_chat_settings_fields($page_slug, $section_id) {
        add_settings_field( 'live_chat_enabled', __('Enable Live Chat Widget', MFW_AI_SUPER_TEXT_DOMAIN), [$this, 'render_checkbox_field'], $page_slug, $section_id,
            [
                'id' => 'live_chat_enabled',
                'description' => __('Enable the AI-powered live chat widget on the frontend of your website.', MFW_AI_SUPER_TEXT_DOMAIN)
            ]
        );
        add_settings_field( 'live_chat_ai_provider', __('Chat AI Provider', MFW_AI_SUPER_TEXT_DOMAIN), [$this, 'render_select_field'], $page_slug, $section_id,
            [
                'id' => 'live_chat_ai_provider',
                'options' => ['' => __('Select Provider', MFW_AI_SUPER_TEXT_DOMAIN)] + $this->plugin->ai_services->get_providers_by_capability('chat', true),
                'description' => __('Select the AI provider for live chat responses.', MFW_AI_SUPER_TEXT_DOMAIN)
            ]
        );
        add_settings_field( 'live_chat_ai_model', __('Chat AI Model', MFW_AI_SUPER_TEXT_DOMAIN), [$this, 'render_text_field'], $page_slug, $section_id,
            [
                'id' => 'live_chat_ai_model',
                'description' => __('Specify the AI model (e.g., gpt-3.5-turbo, gemini-1.5-flash-latest). Ensure it matches the selected provider and is suitable for chat.', MFW_AI_SUPER_TEXT_DOMAIN)
            ]
        );
        add_settings_field( 'live_chat_system_prompt', __('Chat System Prompt', MFW_AI_SUPER_TEXT_DOMAIN), [$this, 'render_textarea_field'], $page_slug, $section_id,
            [
                'id' => 'live_chat_system_prompt',
                'rows' => 4,
                'description' => __('Instructions for the AI on how to behave, its role, personality, and knowledge boundaries. Use {{site_title}} and {{site_url}} placeholders.', MFW_AI_SUPER_TEXT_DOMAIN)
            ]
        );
        add_settings_field( 'live_chat_welcome_message', __('Chat Welcome Message', MFW_AI_SUPER_TEXT_DOMAIN), [$this, 'render_textarea_field'], $page_slug, $section_id,
            [
                'id' => 'live_chat_welcome_message',
                'rows' => 2,
                'description' => __('Initial message shown to the user when they open the chat widget.', MFW_AI_SUPER_TEXT_DOMAIN)
            ]
        );
        add_settings_field( 'live_chat_knowledge_base_post_types', __('Chat Knowledge Base Post Types', MFW_AI_SUPER_TEXT_DOMAIN), [$this, 'render_multi_checkbox_field'], $page_slug, $section_id,
            [
                'id' => 'live_chat_knowledge_base_post_types',
                'options' => $this->get_all_public_post_types(true, true), // Include products/docs if available
                'description' => __('Content types the AI will read from to answer questions. Includes WooCommerce Products and EazyDocs if active and selected.', MFW_AI_SUPER_TEXT_DOMAIN)
            ]
        );
        add_settings_field( 'live_chat_contact_form_shortcode', __('Fallback Contact Form Shortcode', MFW_AI_SUPER_TEXT_DOMAIN), [$this, 'render_text_field'], $page_slug, $section_id,
            [
                'id' => 'live_chat_contact_form_shortcode',
                'description' => __('Enter the shortcode for a contact form (e.g., from Contact Form 7) to display if AI cannot answer or user requests human assistance.', MFW_AI_SUPER_TEXT_DOMAIN)
            ]
        );
        add_settings_field( 'live_chat_recipient_email', __('Contact Form Email Recipient', MFW_AI_SUPER_TEXT_DOMAIN), [$this, 'render_text_field'], $page_slug, $section_id,
            [
                'id' => 'live_chat_recipient_email',
                'type' => 'email',
                'description' => __('Email address where contact form submissions from the chat widget will be sent.', MFW_AI_SUPER_TEXT_DOMAIN)
            ]
        );
        add_settings_field( 'live_chat_store_history', __('Store Chat History', MFW_AI_SUPER_TEXT_DOMAIN), [$this, 'render_checkbox_field'], $page_slug, $section_id,
            [
                'id' => 'live_chat_store_history',
                'description' => __('Save chat conversations in the database for admin review. Max entries controlled by the setting below.', MFW_AI_SUPER_TEXT_DOMAIN)
            ]
        );
         add_settings_field( 'live_chat_max_history_entries', __('Max Chat History Entries (DB)', MFW_AI_SUPER_TEXT_DOMAIN), [$this, 'render_number_field'], $page_slug, $section_id,
            [
                'id' => 'live_chat_max_history_entries',
                'min' => 100,
                'max' => 10000,
                'step' => 100,
                'description' => __('Maximum number of chat history records to keep in the database for all users combined.', MFW_AI_SUPER_TEXT_DOMAIN)
            ]
        );
        add_settings_field( 'live_chat_style', __('Chat Widget Style', MFW_AI_SUPER_TEXT_DOMAIN), [$this, 'render_select_field'], $page_slug, $section_id,
            [
                'id' => 'live_chat_style',
                'options' => ['default' => 'Default Minimal', 'jivochat_like' => 'JivoChat Inspired', 'custom' => 'Custom CSS'],
                'description' => __('Select the visual style for the chat widget.', MFW_AI_SUPER_TEXT_DOMAIN)
            ]
        );
        add_settings_field( 'live_chat_custom_css', __('Chat Widget Custom CSS', MFW_AI_SUPER_TEXT_DOMAIN), [$this, 'render_textarea_field'], $page_slug, $section_id,
            [
                'id' => 'live_chat_custom_css',
                'rows' => 8,
                'description' => __('Apply custom CSS if "Custom CSS" style is selected. Use with caution. Example: .mfw-chat-widget { border-color: #0073aa; }', MFW_AI_SUPER_TEXT_DOMAIN)
            ]
        );
    }

    /**
     * Add settings fields for the Automation & Cron tab.
     *
     * @param string $page_slug The slug of the settings page.
     * @param string $section_id The ID of the settings section.
     */
    private function add_automation_settings_fields($page_slug, $section_id) {
        $intervals = $this->get_cron_schedules_with_manual('disabled'); // 'disabled' is the key for "Disabled"

        add_settings_field( 'auto_update_content_interval', __('Auto Update Content Interval', MFW_AI_SUPER_TEXT_DOMAIN), [$this, 'render_select_field'], $page_slug, $section_id,
            [
                'id' => 'auto_update_content_interval',
                'options' => $intervals,
                'description' => __('How often to automatically update existing content using the prompt below. Select "Disabled" to turn off.', MFW_AI_SUPER_TEXT_DOMAIN)
            ]
        );
        add_settings_field( 'auto_update_content_prompt', __('Auto Update Content Prompt', MFW_AI_SUPER_TEXT_DOMAIN), [$this, 'render_textarea_field'], $page_slug, $section_id,
            [
                'id' => 'auto_update_content_prompt',
                'rows' => 4,
                'description' => __('The prompt used to update content. Use placeholders like {{current_content}}, {{post_title}}, {{post_keywords}}, {{post_excerpt}}.', MFW_AI_SUPER_TEXT_DOMAIN)
            ]
        );
        add_settings_field( 'auto_comment_generation_interval', __('Auto Generate Comments Interval', MFW_AI_SUPER_TEXT_DOMAIN), [$this, 'render_select_field'], $page_slug, $section_id,
            [
                'id' => 'auto_comment_generation_interval',
                'options' => $intervals,
                'description' => __('How often to generate comments for posts. Select "Disabled" to turn off.', MFW_AI_SUPER_TEXT_DOMAIN)
            ]
        );
        add_settings_field( 'auto_comment_generation_prompt', __('Auto Generate Comment Prompt', MFW_AI_SUPER_TEXT_DOMAIN), [$this, 'render_textarea_field'], $page_slug, $section_id,
            [
                'id' => 'auto_comment_generation_prompt',
                'rows' => 3,
                'description' => __('Prompt for generating new comments. Use {{post_title}}, {{post_excerpt}}.', MFW_AI_SUPER_TEXT_DOMAIN)
            ]
        );
         add_settings_field( 'auto_comment_reply_prompt', __('Auto Reply to Comments Prompt', MFW_AI_SUPER_TEXT_DOMAIN), [$this, 'render_textarea_field'], $page_slug, $section_id,
            [
                'id' => 'auto_comment_reply_prompt',
                'rows' => 3,
                'description' => __('Prompt for replying to user comments. Use {{user_comment}}, {{post_title}}.', MFW_AI_SUPER_TEXT_DOMAIN)
            ]
        );
         add_settings_field( 'task_queue_concurrent_tasks', __('Max Concurrent Background Tasks', MFW_AI_SUPER_TEXT_DOMAIN), [$this, 'render_number_field'], $page_slug, $section_id,
            [
                'id' => 'task_queue_concurrent_tasks',
                'min' => 1,
                'max' => 10, // Limit to a reasonable number
                'step' => 1,
                'description' => __('Number of background AI tasks (like bulk processing, PDF generation batches) that can run simultaneously via the task queue.', MFW_AI_SUPER_TEXT_DOMAIN)
            ]
        );
        add_settings_field( 'task_queue_runner_method', __('Task Queue Runner Method', MFW_AI_SUPER_TEXT_DOMAIN), [$this, 'render_select_field'], $page_slug, $section_id,
            [
                'id' => 'task_queue_runner_method',
                 'options' => ['cron' => 'WP Cron (Default)', 'action_schedural' => 'Action Scheduler (if active)'],
                'description' => __('Choose how background tasks are processed. Action Scheduler is more robust if available.', MFW_AI_SUPER_TEXT_DOMAIN)
            ]
        );
    }

    /**
     * Add settings fields for the Advanced & Shortcodes tab.
     *
     * @param string $page_slug The slug of the settings page.
     * @param string $section_id The ID of the settings section.
     */
    private function add_advanced_settings_fields($page_slug, $section_id) {
        add_settings_field( 'default_map_shortcode_zoom', __('Default Map Zoom Level', MFW_AI_SUPER_TEXT_DOMAIN), [$this, 'render_number_field'], $page_slug, $section_id,
            [
                'id' => 'default_map_shortcode_zoom',
                'min' => 1,
                'max' => 20,
                'step' => 1,
                'description' => __('Default zoom level for the [mfw_map] shortcode if not specified.', MFW_AI_SUPER_TEXT_DOMAIN)
            ]
        );
        add_settings_field( 'google_maps_api_key', __('Google Maps API Key', MFW_AI_SUPER_TEXT_DOMAIN), [$this, 'render_text_field'], $page_slug, $section_id,
            [
                'id' => 'google_maps_api_key',
                'type' => 'password',
                'description' => __('Required for the [mfw_map] shortcode to display maps.', MFW_AI_SUPER_TEXT_DOMAIN)
            ]
        );
        add_settings_field( 'default_cta_text', __('Default CTA Shortcode Text', MFW_AI_SUPER_TEXT_DOMAIN), [$this, 'render_text_field'], $page_slug, $section_id,
            [
                'id' => 'default_cta_text',
                'description' => __('Default text for the [mfw_cta] shortcode button if not specified.', MFW_AI_SUPER_TEXT_DOMAIN)
            ]
        );
        add_settings_field( 'default_contact_form_shortcode', __('Default Contact Form Shortcode', MFW_AI_SUPER_TEXT_DOMAIN), [$this, 'render_text_field'], $page_slug, $section_id,
            [
                'id' => 'default_contact_form_shortcode',
                'description' => __('Enter the shortcode for your default contact form (e.g., from Contact Form 7) used by [mfw_contact_box].', MFW_AI_SUPER_TEXT_DOMAIN)
            ]
        );
        add_settings_field( 'smart_404_enabled', __('Enable Smart 404 Page', MFW_AI_SUPER_TEXT_DOMAIN), [$this, 'render_checkbox_field'], $page_slug, $section_id,
            [
                'id' => 'smart_404_enabled',
                'description' => __('Replace the standard 404 page with one that attempts to suggest relevant content using AI.', MFW_AI_SUPER_TEXT_DOMAIN)
            ]
        );
         add_settings_field( 'smart_404_results_count', __('Smart 404 Results Count', MFW_AI_SUPER_TEXT_DOMAIN), [$this, 'render_number_field'], $page_slug, $section_id,
            [
                'id' => 'smart_404_results_count',
                'min' => 1,
                'max' => 10,
                'step' => 1,
                'description' => __('Number of relevant content suggestions to show on the Smart 404 page.', MFW_AI_SUPER_TEXT_DOMAIN)
            ]
        );
        add_settings_field( 'elasticpress_smart_404_enabled', __('ElasticPress Integration (Smart 404)', MFW_AI_SUPER_TEXT_DOMAIN), [$this, 'render_checkbox_field'], $page_slug, $section_id,
            [
                'id' => 'elasticpress_smart_404_enabled',
                'description' => __('Use ElasticPress for finding relevant content on the Smart 404 page (requires ElasticPress plugin).', MFW_AI_SUPER_TEXT_DOMAIN)
            ]
        );
        add_settings_field( 'elasticpress_similar_terms_enabled', __('ElasticPress Integration (Similar Terms)', MFW_AI_SUPER_TEXT_DOMAIN), [$this, 'render_checkbox_field'], $page_slug, $section_id,
            [
                'id' => 'elasticpress_similar_terms_enabled',
                'description' => __('Use ElasticPress to find similar terms/keywords for content analysis (requires ElasticPress plugin).', MFW_AI_SUPER_TEXT_DOMAIN)
            ]
        );
        // TODO: Add fields for API Quota Monitoring if implemented
        // add_settings_field( 'quota_monitoring_enabled', __('Enable API Quota Monitoring', MFW_AI_SUPER_TEXT_DOMAIN), [$this, 'render_checkbox_field'], $page_slug, $section_id, [...] );
        // add_settings_field( 'quota_alert_threshold_percent', __('Quota Alert Threshold (%)', MFW_AI_SUPER_TEXT_DOMAIN), [$this, 'render_number_field'], $page_slug, $section_id, [...] );
        // add_settings_field( 'quota_alert_email', __('Quota Alert Email', MFW_AI_SUPER_TEXT_DOMAIN), [$this, 'render_text_field'], $page_slug, $section_id, [...] );
    }

    /**
     * Add settings fields for the Access Control tab.
     *
     * @param string $page_slug The slug of the settings page.
     * @param string $section_id The ID of the settings section.
     */
    private function add_access_control_settings_fields($page_slug, $section_id) {
        add_settings_field( 'ai_features_access_roles', __('Roles with Access to AI Features', MFW_AI_SUPER_TEXT_DOMAIN), [$this, 'render_multi_checkbox_field'], $page_slug, $section_id,
            [
                'id' => 'ai_features_access_roles',
                'options' => $this->get_all_user_roles(true),
                'description' => __('Select user roles that have access to the MFW AI Super admin menu, settings, and AI content tools (excluding basic frontend features like chat/PDF download). Administrators always have full access.', MFW_AI_SUPER_TEXT_DOMAIN)
            ]
        );
        // TODO: Add more granular access control settings if needed
        // e.g., per-feature capability overrides
    }


    // --- Render Methods for Settings Fields ---

    /**
     * Render a standard text input field.
     *
     * @param array $args Field arguments (id, description, type, placeholder).
     */
    public function render_text_field($args) {
        $option_id = $args['id'];
        $type = $args['type'] ?? 'text';
        $placeholder = $args['placeholder'] ?? '';
        $current_value = $this->plugin->get_option($option_id, '');
        ?>
        <input type="<?php echo esc_attr($type); ?>" id="<?php echo esc_attr($option_id); ?>" name="<?php echo esc_attr($this->option_name); ?>[<?php echo esc_attr($option_id); ?>]" value="<?php echo esc_attr($current_value); ?>" class="regular-text" placeholder="<?php echo esc_attr($placeholder); ?>" />
        <?php if (!empty($args['description'])): ?>
            <p class="description"><?php echo wp_kses_post($args['description']); ?></p>
        <?php endif; ?>
        <?php if (!empty($args['custom_buttons'])): ?>
            <?php echo $args['custom_buttons']; // Render custom buttons/HTML ?>
        <?php endif; ?>
        <?php
    }

    /**
     * Render a textarea field.
     *
     * @param array $args Field arguments (id, description, rows).
     */
    public function render_textarea_field($args) {
        $option_id = $args['id'];
        $rows = $args['rows'] ?? 5;
        $current_value = $this->plugin->get_option($option_id, '');
        ?>
        <textarea id="<?php echo esc_attr($option_id); ?>" name="<?php echo esc_attr($this->option_name); ?>[<?php echo esc_attr($option_id); ?>]" rows="<?php echo absint($rows); ?>" class="large-text code"><?php echo esc_textarea($current_value); ?></textarea>
        <?php if (!empty($args['description'])): ?>
            <p class="description"><?php echo wp_kses_post($args['description']); ?></p>
        <?php endif; ?>
        <?php
    }

    /**
     * Render a single checkbox field.
     *
     * @param array $args Field arguments (id, description).
     */
    public function render_checkbox_field($args) {
        $option_id = $args['id'];
        $current_value = $this->plugin->get_option($option_id, false); // Default to false
        ?>
        <label for="<?php echo esc_attr($option_id); ?>">
            <input type="checkbox" id="<?php echo esc_attr($option_id); ?>" name="<?php echo esc_attr($this->option_name); ?>[<?php echo esc_attr($option_id); ?>]" value="1" <?php checked(1, $current_value, true); ?> />
            <?php if (!empty($args['description'])): ?>
                <?php echo wp_kses_post($args['description']); ?>
            <?php endif; ?>
        </label>
        <?php
    }

     /**
     * Render a multiple checkbox field (for selecting multiple options like post types).
     *
     * @param array $args Field arguments (id, options, description).
     */
    public function render_multi_checkbox_field($args) {
        $option_id = $args['id'];
        $options_list = $args['options'] ?? []; // Associative array: value => label
        $current_values = $this->plugin->get_option($option_id, []); // Expect an array of selected values

        if (!is_array($current_values)) {
             $current_values = []; // Ensure it's an array
        }

        if (empty($options_list)) {
            echo '<p>' . esc_html__('No options available.', MFW_AI_SUPER_TEXT_DOMAIN) . '</p>';
            if (!empty($args['description'])): ?>
                <p class="description"><?php echo wp_kses_post($args['description']); ?></p>
            <?php endif;
            return;
        }

        echo '<fieldset>';
        if (!empty($args['description'])): ?>
            <legend class="screen-reader-text"><span><?php echo wp_kses_post($args['description']); ?></span></legend>
            <p class="description"><?php echo wp_kses_post($args['description']); ?></p>
        <?php endif;

        foreach ($options_list as $value => $label) {
            $field_id = $option_id . '_' . sanitize_key($value);
            ?>
            <label for="<?php echo esc_attr($field_id); ?>">
                <input type="checkbox" id="<?php echo esc_attr($field_id); ?>" name="<?php echo esc_attr($this->option_name); ?>[<?php echo esc_attr($option_id); ?>][]" value="<?php echo esc_attr($value); ?>" <?php checked(in_array($value, $current_values), true, true); ?> />
                <?php echo esc_html($label); ?>
            </label><br>
            <?php
        }
        echo '</fieldset>';
    }


    /**
     * Render a select dropdown field.
     *
     * @param array $args Field arguments (id, options, description, custom_buttons).
     */
    public function render_select_field($args) {
        $option_id = $args['id'];
        $options_list = $args['options'] ?? []; // Associative array: value => label
        $current_value = $this->plugin->get_option($option_id); // Get value without default, sanitize later

        // Ensure current value is one of the valid options, or the first option if none selected and options exist
        if (!array_key_exists($current_value, $options_list) && !empty($options_list)) {
            // If a default is set in get_default_options() and it's valid, get_option would return it.
            // If it's not in the list or no default is set, default to the first option key.
            $current_value = key($options_list);
        } elseif (empty($options_list)) {
             $current_value = ''; // No options available
        }


        ?>
        <select id="<?php echo esc_attr($option_id); ?>" name="<?php echo esc_attr($this->option_name); ?>[<?php echo esc_attr($option_id); ?>]">
            <?php foreach ($options_list as $value => $label): ?>
                <option value="<?php echo esc_attr($value); ?>" <?php selected($current_value, $value, true); ?>><?php echo esc_html($label); ?></option>
            <?php endforeach; ?>
        </select>
        <?php if (!empty($args['description'])): ?>
            <p class="description"><?php echo wp_kses_post($args['description']); ?></p>
        <?php endif; ?>
        <?php if (!empty($args['custom_buttons'])): ?>
            <?php echo $args['custom_buttons']; // Render custom buttons/HTML ?>
        <?php endif; ?>
        <?php
    }

    /**
     * Render a sortable multi-select field (requires JS).
     * The saved value will be an array of ordered keys.
     *
     * @param array $args Field arguments (id, options, description).
     */
    public function render_sortable_multi_select_field($args) {
        $option_id = $args['id'];
        $options_list = $args['options'] ?? []; // Associative array: value => label
        $current_order = $this->plugin->get_option($option_id, []); // Expect an array of ordered keys

        // If no order is saved, default to the order of options_list
        if (empty($current_order) || !is_array($current_order)) {
             $current_order = array_keys($options_list);
        }

        // Filter out any saved keys that are no longer valid options
        $current_order = array_filter($current_order, function($key) use ($options_list) {
            return array_key_exists($key, $options_list);
        });

        // Add any new options that are not in the saved order to the end
        $existing_keys = array_flip($current_order);
        foreach (array_keys($options_list) as $key) {
            if (!isset($existing_keys[$key])) {
                $current_order[] = $key;
            }
        }

        ?>
        <ul id="<?php echo esc_attr($option_id); ?>_sortable" class="mfw-sortable-list">
            <?php foreach ($current_order as $value):
                if (isset($options_list[$value])): // Ensure the option still exists
                ?>
                <li class="ui-state-default" data-value="<?php echo esc_attr($value); ?>">
                    <span class="dashicons dashicons-move"></span>
                    <?php echo esc_html($options_list[$value]); ?>
                    <input type="hidden" name="<?php echo esc_attr($this->option_name); ?>[<?php echo esc_attr($option_id); ?>][]" value="<?php echo esc_attr($value); ?>" />
                </li>
            <?php endif; endforeach; ?>
        </ul>
        <?php if (!empty($args['description'])): ?>
            <p class="description"><?php echo wp_kses_post($args['description']); ?></p>
        <?php endif; ?>
        <style>
            .mfw-sortable-list { list-style: none; margin: 0; padding: 0; }
            .mfw-sortable-list li { margin: 5px 0; padding: 8px 12px; border: 1px solid #ddd; background: #f9f9f9; cursor: grab; border-radius: 4px; }
            .mfw-sortable-list li .dashicons-move { cursor: grab; margin-right: 8px; color: #888; }
            .mfw-sortable-list li.ui-sortable-helper { background: #fff; box-shadow: 0 2px 5px rgba(0,0,0,0.2); }
            .mfw-sortable-list li.ui-sortable-placeholder { border: 1px dashed #aaa; background: #f3f3f3; visibility: visible !important; height: 36px; } /* Match li height */
        </style>
        <script type="text/javascript">
            jQuery(document).ready(function($) {
                $('#<?php echo esc_js($option_id); ?>_sortable').sortable({
                    placeholder: "ui-state-placeholder",
                    handle: ".dashicons-move",
                    axis: "y"
                });
                $('#<?php echo esc_js($option_id); ?>_sortable').disableSelection(); // Prevent text selection during drag
            });
        </script>
        <?php
    }


    /**
     * Render a number input field.
     *
     * @param array $args Field arguments (id, description, min, max, step).
     */
    public function render_number_field($args) {
        $option_id = $args['id'];
        $min = $args['min'] ?? 0;
        $max = $args['max'] ?? 99999;
        $step = $args['step'] ?? 1;
        $current_value = $this->plugin->get_option($option_id); // Get value without default
        // Ensure current value is a number, fallback to default if not
        if (!is_numeric($current_value)) {
             $default_options = $this->plugin->get_default_options();
             $current_value = $default_options[$option_id] ?? '';
        }
        $current_value = esc_attr($current_value);

        ?>
        <input type="number" id="<?php echo esc_attr($option_id); ?>" name="<?php echo esc_attr($this->option_name); ?>[<?php echo esc_attr($option_id); ?>]" value="<?php echo $current_value; ?>" min="<?php echo esc_attr($min); ?>" max="<?php echo esc_attr($max); ?>" step="<?php echo esc_attr($step); ?>" class="small-text" />
        <?php if (!empty($args['description'])): ?>
            <p class="description"><?php echo wp_kses_post($args['description']); ?></p>
        <?php endif; ?>
        <?php
    }

    /**
     * Render a dropdown field for selecting a user.
     *
     * @param array $args Field arguments (id, description).
     */
    public function render_user_dropdown_field($args) {
        $option_id = $args['id'];
        $current_value = $this->plugin->get_option($option_id); // Get value without default
         // Ensure current value is a number, fallback to default if not
         if (!is_numeric($current_value)) {
              $default_options = $this->plugin->get_default_options();
              $current_value = $default_options[$option_id] ?? '';
         }
         $current_value = absint($current_value);

        $users = get_users(['fields' => ['ID', 'display_name']]);
        $options_list = [];
        foreach ($users as $user) {
            $options_list[$user->ID] = $user->display_name;
        }

        ?>
        <select id="<?php echo esc_attr($option_id); ?>" name="<?php echo esc_attr($this->option_name); ?>[<?php echo esc_attr($option_id); ?>]">
            <?php foreach ($options_list as $value => $label): ?>
                <option value="<?php echo esc_attr($value); ?>" <?php selected($current_value, $value, true); ?>><?php echo esc_html($label); ?></option>
            <?php endforeach; ?>
        </select>
        <?php if (!empty($args['description'])): ?>
            <p class="description"><?php echo wp_kses_post($args['description']); ?></p>
        <?php endif; ?>
        <?php
    }

    /**
     * Render a dropdown field for selecting a category.
     *
     * @param array $args Field arguments (id, description).
     */
    public function render_category_dropdown_field($args) {
        $option_id = $args['id'];
        $current_value = $this->plugin->get_option($option_id); // Get value without default
         // Ensure current value is a number, fallback to default if not
         if (!is_numeric($current_value)) {
              $default_options = $this->plugin->get_default_options();
              $current_value = $default_options[$option_id] ?? '';
         }
         $current_value = absint($current_value);

        $categories = get_categories(['hide_empty' => 0]);
        $options_list = [0 => __('Uncategorized', MFW_AI_SUPER_TEXT_DOMAIN)];
        foreach ($categories as $category) {
            $options_list[$category->term_id] = $category->name;
        }

        ?>
        <select id="<?php echo esc_attr($option_id); ?>" name="<?php echo esc_attr($this->option_name); ?>[<?php echo esc_attr($option_id); ?>]">
            <?php foreach ($options_list as $value => $label): ?>
                <option value="<?php echo esc_attr($value); ?>" <?php selected($current_value, $value, true); ?>><?php echo esc_html($label); ?></option>
            <?php endforeach; ?>
        </select>
        <?php if (!empty($args['description'])): ?>
            <p class="description"><?php echo wp_kses_post($args['description']); ?></p>
        <?php endif; ?>
        <?php
    }

    /**
     * Render a field to toggle a feature on/off per post type.
     * Saved as an associative array: post_type_slug => boolean.
     *
     * @param array $args Field arguments (option_key_name, options, description).
     */
    public function render_post_type_toggle_field($args) {
        $option_key_name = $args['option_key_name']; // The key in the options array, e.g., 'enable_summary_generation'
        $post_types_list = $args['options'] ?? []; // Associative array: post_type_slug => label
        $current_values = $this->plugin->get_option($option_key_name, []); // Expect an associative array

        if (!is_array($current_values)) {
             $current_values = []; // Ensure it's an array
        }

        if (empty($post_types_list)) {
             echo '<p>' . esc_html__('No post types available.', MFW_AI_SUPER_TEXT_DOMAIN) . '</p>';
             if (!empty($args['description'])): ?>
                 <p class="description"><?php echo wp_kses_post($args['description']); ?></p>
             <?php endif;
             return;
         }

        echo '<fieldset>';
        if (!empty($args['description'])): ?>
             <legend class="screen-reader-text"><span><?php echo wp_kses_post($args['description']); ?></span></legend>
             <p class="description"><?php echo wp_kses_post($args['description']); ?></p>
         <?php endif;

        foreach ($post_types_list as $slug => $label) {
            // Default to false if not explicitly set in options
            $is_enabled = isset($current_values[$slug]) ? (bool)$current_values[$slug] : false;
            $field_id = $option_key_name . '_' . sanitize_key($slug);
            ?>
            <label for="<?php echo esc_attr($field_id); ?>">
                <input type="checkbox" id="<?php echo esc_attr($field_id); ?>" name="<?php echo esc_attr($this->option_name); ?>[<?php echo esc_attr($option_key_name); ?>][<?php echo esc_attr($slug); ?>]" value="1" <?php checked(true, $is_enabled, true); ?> />
                <?php echo esc_html($label); ?>
            </label><br>
            <?php
        }
        echo '</fieldset>';
    }

     /**
     * Render the manual GAW generate button.
     *
     * @param array $args Field arguments (description).
     */
    public function render_gaw_manual_generate_button($args) {
         // This field is rendered on the GAW reports page, not the settings tab itself.
         // The rendering logic is in MFW_AI_SUPER_Admin::render_gaw_reports_page_super().
         // This method exists primarily to register the setting field for documentation/structure.
         // We can add a placeholder message here if needed.
         echo '<p>' . esc_html__('This button is available on the "GAW Reports" sub-menu page.', MFW_AI_SUPER_TEXT_DOMAIN) . '</p>';
         if (!empty($args['description'])): ?>
             <p class="description"><?php echo wp_kses_post($args['description']); ?></p>
         <?php endif;
    }


    // --- Helper Methods ---

    /**
     * Get all public post types.
     *
     * @param bool $as_options Whether to return as an associative array for select/checkbox options.
     * @param bool $include_custom_integrations Whether to explicitly include known integrations like 'product', 'docs'.
     * @return array A list or associative array of post type slugs and labels.
     */
    public function get_all_public_post_types($as_options = false, $include_custom_integrations = false) {
        $args = [
            'public' => true,
            '_builtin' => false, // Exclude built-in types initially
        ];
        $output = 'objects'; // Return post type objects

        $post_types = get_post_types($args, $output);

        // Include built-in 'post' and 'page' explicitly
        $built_in = ['post', 'page'];
        foreach ($built_in as $slug) {
            $obj = get_post_type_object($slug);
            if ($obj && $obj->public) {
                 $post_types[$slug] = $obj;
            }
        }

        // Include known integration post types if requested and they exist
        if ($include_custom_integrations) {
            $integration_types = [];
            if (class_exists('WooCommerce')) {
                $integration_types[] = 'product';
            }
            if (class_exists('EazyDocs\\Plugin')) { // Check for a core EazyDocs class
                 $integration_types[] = 'docs'; // Assuming 'docs' is the slug
            }
            foreach ($integration_types as $slug) {
                $obj = get_post_type_object($slug);
                if ($obj && $obj->public) {
                     $post_types[$slug] = $obj;
                }
            }
        }


        if ($as_options) {
            $options = [];
            // Sort alphabetically by label
            uasort($post_types, function($a, $b) {
                return strcmp($a->label, $b->label);
            });
            foreach ($post_types as $post_type) {
                $options[$post_type->name] = $post_type->label;
            }
            return $options;
        }

        return array_keys($post_types); // Return just slugs by default
    }

    /**
     * Get available WP Cron schedules, optionally including 'manual' or 'disabled'.
     *
     * @param string|null $include_special Key for special options ('manual', 'disabled', etc.).
     * @return array Associative array of schedule names => display labels.
     */
    public function get_cron_schedules_with_manual($include_special = null) {
        $schedules = wp_get_schedules();
        $options = [];
        if ($include_special === 'manual') {
            $options['manual'] = __('Manual Trigger Only', MFW_AI_SUPER_TEXT_DOMAIN);
        } elseif ($include_special === 'disabled') {
             $options['disabled'] = __('Disabled', MFW_AI_SUPER_TEXT_DOMAIN);
        }

        foreach ($schedules as $key => $schedule) {
            $options[$key] = $schedule['display'];
        }
        return $options;
    }

    /**
     * Get all registered user roles.
     *
     * @param bool $as_options Whether to return as an associative array for select/checkbox options.
     * @return array A list or associative array of role slugs and display names.
     */
    public function get_all_user_roles($as_options = false) {
        if (!function_exists('get_editable_roles')) {
            require_once(ABSPATH . 'wp-admin/includes/user.php');
        }
        $roles = get_editable_roles();
        if ($as_options) {
            $options = [];
            foreach ($roles as $role_slug => $role_info) {
                $options[$role_slug] = $role_info['display_name'];
            }
            return $options;
        }
        return array_keys($roles);
    }


    /**
     * Sanitize the plugin options.
     *
     * @param array $input The input array from the settings form.
     * @return array The sanitized array.
     */
    public function sanitize_options($input) {
        $sanitized_input = [];
        $default_options = $this->plugin->get_default_options();

        // Iterate through default options to ensure all expected keys are handled
        foreach ($default_options as $key => $default_value) {
            // Check if the key exists in the input, otherwise use the default
            $value = isset($input[$key]) ? $input[$key] : $default_value;

            // Sanitize based on the expected type or key
            switch ($key) {
                // Text fields (including API keys, endpoints, URLs, models, etc.)
                case 'openai_api_key':
                case 'azure_openai_api_key':
                case 'azure_openai_endpoint':
                case 'azure_openai_deployment_name':
                case 'google_gemini_api_key':
                case 'xai_grok_api_key':
                case 'ollama_api_endpoint':
                case 'ollama_default_model':
                case 'anthropic_api_key':
                case 'ibm_watson_nlu_api_key':
                case 'ibm_watson_nlu_endpoint':
                case 'aws_polly_access_key':
                case 'aws_polly_secret_key':
                case 'aws_polly_region':
                case 'azure_speech_api_key':
                case 'azure_speech_region':
                case 'azure_vision_api_key':
                case 'azure_vision_endpoint':
                case 'google_maps_api_key':
                case 'default_cta_text':
                case 'default_contact_form_shortcode':
                case 'gaw_api_key':
                case 'gaw_gemini_model':
                case 'gaw_post_status': // Post status is a string slug
                case 'gaw_tags': // Comma-separated string
                case 'pdf_download_prompt_text':
                case 'pdf_download_button_text':
                case 'pdf_header_text':
                case 'pdf_footer_text':
                case 'pdf_font_family':
                case 'pdf_metadata_author':
                case 'live_chat_ai_provider':
                case 'live_chat_ai_model':
                case 'live_chat_contact_form_shortcode':
                case 'live_chat_recipient_email':
                case 'live_chat_style':
                case 'task_queue_runner_method':
                    $sanitized_input[$key] = sanitize_text_field($value);
                    break;

                // Textarea fields (prompts, custom CSS, keywords list)
                case 'default_featured_image_prompt_template':
                case 'gaw_keywords': // Multi-line keywords
                case 'gaw_prompt':
                case 'pdf_custom_css':
                case 'live_chat_system_prompt':
                case 'live_chat_welcome_message':
                case 'auto_update_content_prompt':
                case 'auto_comment_generation_prompt':
                case 'auto_comment_reply_prompt':
                     // Use wp_kses_post for prompts that might contain HTML/shortcodes,
                     // but simple textareas might just need sanitize_textarea_field.
                     // For prompts and CSS, sanitize_textarea_field is safer.
                    $sanitized_input[$key] = sanitize_textarea_field($value);
                    break;

                // Checkbox fields (boolean toggles)
                case 'auto_generate_featured_image_on_creation':
                case 'seo_compatibility_yoast':
                case 'seo_compatibility_rankmath':
                case 'ai_generated_meta_description_enabled':
                case 'ai_generated_focus_keywords_enabled':
                case 'log_to_db_enabled':
                case 'ai_assistant_enabled':
                case 'pdf_generation_enabled':
                case 'pdf_cache_enabled':
                case 'live_chat_enabled':
                case 'live_chat_store_history':
                case 'quota_monitoring_enabled':
                case 'elasticpress_smart_404_enabled':
                case 'elasticpress_similar_terms_enabled':
                case 'smart_404_enabled':
                    $sanitized_input[$key] = (bool)($value == 1);
                    break;

                // Multi-checkbox fields (arrays of selected slugs/values)
                case 'auto_content_post_types':
                case 'pdf_post_types_enabled':
                case 'live_chat_knowledge_base_post_types':
                case 'ai_features_access_roles': // Array of role slugs
                    $sanitized_input[$key] = is_array($value) ? array_map('sanitize_text_field', $value) : [];
                    break;

                // Post Type Toggle fields (associative arrays: slug => boolean)
                case 'enable_summary_generation':
                case 'enable_takeaways_generation':
                case 'enable_image_alt_text_generation':
                case 'enable_tts_feature':
                    $sanitized_input[$key] = [];
                    if (is_array($value)) {
                        foreach ($value as $slug => $enabled) {
                            $sanitized_input[$key][sanitize_key($slug)] = (bool)($enabled == 1);
                        }
                    }
                    break;

                // Select fields (single value from options)
                case 'preferred_text_provider':
                case 'preferred_image_provider':
                case 'preferred_audio_transcription_provider':
                case 'preferred_tts_provider':
                case 'preferred_classification_provider':
                case 'preferred_embedding_provider':
                case 'preferred_chat_provider':
                case 'log_level':
                case 'gaw_frequency':
                    // Ensure the selected value is one of the valid options
                    $valid_options = [];
                    if ($key === 'log_level') {
                        $valid_options = array_keys($this->plugin->logger->log_levels);
                    } elseif ($key === 'gaw_frequency') {
                         $valid_options = array_keys($this->get_cron_schedules_with_manual('disabled'));
                    } elseif (strpos($key, 'preferred_') === 0 && strpos($key, '_provider') !== false) {
                         $capability = str_replace(['preferred_', '_provider'], '', $key);
                         $valid_options = array_keys($this->plugin->ai_services->get_providers_by_capability($capability, true));
                         $valid_options[] = ''; // Allow empty selection
                    } elseif ($key === 'pdf_font_family') {
                         $valid_options = array_keys($this->plugin->pdf_generator->get_tcpdf_fonts());
                    } elseif ($key === 'live_chat_style') {
                         $valid_options = array_keys(['default' => '', 'jivochat_like' => '', 'custom' => '']); // Hardcode for now
                    } elseif ($key === 'task_queue_runner_method') {
                         $valid_options = array_keys(['cron' => '', 'action_scheduler' => '']); // Hardcode for now
                    }

                    $sanitized_input[$key] = in_array($value, $valid_options) ? sanitize_text_field($value) : $default_value;
                    break;

                // Sortable Multi-select fields (arrays of ordered keys)
                case 'fallback_order_text':
                case 'fallback_order_image':
                case 'fallback_order_audio_transcription':
                case 'fallback_order_tts':
                case 'fallback_order_classification':
                case 'fallback_order_chat':
                    $sanitized_input[$key] = is_array($value) ? array_map('sanitize_text_field', $value) : [];
                    // Optional: Filter to ensure only valid provider keys remain
                    break;

                // Number fields (integers or decimals)
                case 'max_log_entries_db':
                case 'live_chat_max_history_entries':
                case 'task_queue_concurrent_tasks':
                case 'default_map_shortcode_zoom':
                case 'pdf_font_size':
                case 'gaw_max_reports':
                case 'smart_404_results_count':
                case 'quota_alert_threshold_percent':
                    $sanitized_input[$key] = absint($value); // Ensure non-negative integer
                    break;

                // User/Category dropdowns (integer IDs)
                case 'gaw_author_id':
                case 'gaw_category_id':
                    $sanitized_input[$key] = absint($value);
                    break;

                // GAW Reports Data (handled separately by gaw_save_report_super)
                case 'gaw_reports_data':
                    // This option is managed internally and should not be directly updated via the settings form.
                    // We'll keep the existing value or the default.
                    $sanitized_input[$key] = $default_value;
                    break;

                default:
                    // For any other key, apply a default sanitization or use the default value
                    // Log a warning if an unexpected key is encountered in the input
                    $this->plugin->logger->log("Unexpected option key encountered during sanitization: " . $key, "WARNING", ['input_value' => $value]);
                    $sanitized_input[$key] = sanitize_text_field($value); // Default to text sanitization
                    break;
            }
        }

        // Ensure any keys in the input that were NOT in default_options are discarded
        // This prevents saving arbitrary data via the settings page.
        $final_sanitized_input = array_intersect_key($sanitized_input, $default_options);

        // Handle cases where a multi-checkbox or sortable list was submitted empty (they won't appear in $_POST)
        // For these cases, the default value (often an empty array) is appropriate if not in $input.
        // The loop above already handles this by using $default_value if $input[$key] is not set.

        return $final_sanitized_input;
    }

} // END MFW_AI_SUPER_Settings class


/**
 * Service Manager Class
 * Handles interaction with various AI providers and manages provider selection/fallback.
 */
class MFW_AI_SUPER_Service_Manager {
    private $plugin;
    private $providers = []; // Registered AI providers
    private $capabilities = []; // Mapping of capabilities (text, image, etc.) to provider IDs

    /**
     * Constructor.
     *
     * @param Maziyar_Fetcher_Writer_AI_Super $plugin The main plugin instance.
     */
    public function __construct(Maziyar_Fetcher_Writer_AI_Super $plugin) {
        $this->plugin = $plugin;
        $this->register_providers();
    }

    /**
     * Register available AI providers and their capabilities.
     * This method should be updated as new providers are integrated.
     */
    private function register_providers() {
        // Define providers and their configurations/capabilities
        // Structure: provider_id => [ 'name' => 'Provider Name', 'class' => 'Provider_Class_Name', 'capabilities' => ['text', 'image', ...], 'options' => [...] ]
        $this->providers = [
            'openai' => [
                'name' => 'OpenAI',
                'class' => 'MFW_AI_SUPER_Provider_OpenAI',
                'capabilities' => ['text', 'image_generation', 'audio_transcription', 'tts', 'embedding', 'chat'],
                'api_key_option' => 'openai_api_key',
                'description' => __('Integrates with OpenAI API (GPT, DALL-E, Whisper, TTS, Embeddings).', MFW_AI_SUPER_TEXT_DOMAIN),
            ],
            'azure_openai' => [
                'name' => 'Azure OpenAI Service',
                'class' => 'MFW_AI_SUPER_Provider_AzureOpenAI',
                'capabilities' => ['text', 'embedding', 'chat'], // Capabilities depend on deployed models
                'api_key_option' => 'azure_openai_api_key',
                'endpoint_option' => 'azure_openai_endpoint',
                'deployment_option' => 'azure_openai_deployment_name', // Required for Azure
                'description' => __('Integrates with Azure OpenAI Service. Requires endpoint and deployment name.', MFW_AI_SUPER_TEXT_DOMAIN),
            ],
            'google_gemini' => [
                'name' => 'Google Gemini',
                'class' => 'MFW_AI_SUPER_Provider_GoogleGemini',
                'capabilities' => ['text', 'embedding', 'chat'], // Capabilities depend on model
                'api_key_option' => 'google_gemini_api_key',
                'description' => __('Integrates with Google Gemini API.', MFW_AI_SUPER_TEXT_DOMAIN),
            ],
            'xai_grok' => [
                'name' => 'xAI Grok',
                'class' => 'MFW_AI_SUPER_Provider_xaiGrok',
                'capabilities' => ['text', 'chat'], // Assuming text/chat capabilities
                'api_key_option' => 'xai_grok_api_key',
                'description' => __('Integrates with xAI Grok API (requires API key).', MFW_AI_SUPER_TEXT_DOMAIN),
            ],
            'ollama' => [
                'name' => 'Ollama (Local/Self-hosted)',
                'class' => 'MFW_AI_SUPER_Provider_Ollama',
                'capabilities' => ['text', 'embedding', 'classification', 'chat'], // Capabilities depend on local models
                'endpoint_option' => 'ollama_api_endpoint',
                'model_option' => 'ollama_default_model',
                'default_endpoint' => 'http://localhost:11434',
                'default_model' => 'llama3',
                'description' => __('Integrates with a local or self-hosted Ollama instance.', MFW_AI_SUPER_TEXT_DOMAIN),
            ],
            'anthropic' => [
                'name' => 'Anthropic',
                'class' => 'MFW_AI_SUPER_Provider_Anthropic',
                'capabilities' => ['text', 'chat'], // For Claude models
                'api_key_option' => 'anthropic_api_key',
                'description' => __('Integrates with Anthropic API (Claude models).', MFW_AI_SUPER_TEXT_DOMAIN),
            ],
            'ibm_watson_nlu' => [
                'name' => 'IBM Watson NLU',
                'class' => 'MFW_AI_SUPER_Provider_WatsonNLU',
                'capabilities' => ['classification', 'text_analysis'], // Specific NLU capabilities
                'api_key_option' => 'ibm_watson_nlu_api_key',
                'endpoint_option' => 'ibm_watson_nlu_endpoint',
                'description' => __('Integrates with IBM Watson Natural Language Understanding for text analysis and classification.', MFW_AI_SUPER_TEXT_DOMAIN),
            ],
             'aws_polly' => [
                'name' => 'AWS Polly',
                'class' => 'MFW_AI_SUPER_Provider_AWSPolly',
                'capabilities' => ['tts'],
                'api_key_option' => 'aws_polly_access_key', // Using access key ID option name
                'secret_key_option' => 'aws_polly_secret_key', // Need a way to handle secret keys
                'region_option' => 'aws_polly_region',
                'description' => __('Integrates with AWS Polly for Text-to-Speech.', MFW_AI_SUPER_TEXT_DOMAIN),
            ],
             'azure_tts' => [
                'name' => 'Azure Text-to-Speech',
                'class' => 'MFW_AI_SUPER_Provider_AzureTTS',
                'capabilities' => ['tts'],
                'api_key_option' => 'azure_speech_api_key',
                'region_option' => 'azure_speech_region',
                'description' => __('Integrates with Azure Cognitive Services Speech for Text-to-Speech.', MFW_AI_SUPER_TEXT_DOMAIN),
            ],
             'azure_vision' => [
                'name' => 'Azure Computer Vision',
                'class' => 'MFW_AI_SUPER_Provider_AzureVision',
                'capabilities' => ['image_analysis', 'image_generation'], // Depends on features used (e.g., DALL-E via Vision)
                'api_key_option' => 'azure_vision_api_key',
                'endpoint_option' => 'azure_vision_endpoint',
                'description' => __('Integrates with Azure Computer Vision for image analysis and potentially DALL-E image generation.', MFW_AI_SUPER_TEXT_DOMAIN),
            ],
            // Add other providers here as they are integrated
        ];

        // Build the capability map
        $this->capabilities = [];
        foreach ($this->providers as $provider_id => $config) {
            foreach ($config['capabilities'] as $capability) {
                $this->capabilities[$capability][$provider_id] = $config['name'];
            }
        }
    }

    /**
     * Get configuration for all registered providers.
     *
     * @return array Associative array of provider IDs => config arrays.
     */
    public function get_all_provider_configs() {
        return $this->providers;
    }

    /**
     * Get a list of capabilities supported by registered providers.
     *
     * @return array Associative array of capability keys => display labels.
     */
    public function get_all_capabilities() {
        // Define display labels for capabilities. Add more as needed.
        $capability_labels = [
            'text' => __('General Text Generation', MFW_AI_SUPER_TEXT_DOMAIN),
            'image_generation' => __('Image Generation', MFW_AI_SUPER_TEXT_DOMAIN),
            'audio_transcription' => __('Audio Transcription', MFW_AI_SUPER_TEXT_DOMAIN),
            'tts' => __('Text-to-Speech', MFW_AI_SUPER_TEXT_DOMAIN),
            'embedding' => __('Text Embedding', MFW_AI_SUPER_TEXT_DOMAIN),
            'classification' => __('Text Classification', MFW_AI_SUPER_TEXT_DOMAIN),
            'chat' => __('Chat/Conversation', MFW_AI_SUPER_TEXT_DOMAIN),
            'image_analysis' => __('Image Analysis', MFW_AI_SUPER_TEXT_DOMAIN),
            'text_analysis' => __('Text Analysis (Sentiment, Entities, etc.)', MFW_AI_SUPER_TEXT_DOMAIN),
            // Add other capabilities here
        ];

        // Filter to only include capabilities actually supported by at least one provider
        return array_intersect_key($capability_labels, $this->capabilities);
    }


    /**
     * Get providers that support a specific capability.
     *
     * @param string $capability The capability key (e.g., 'text', 'image_generation').
     * @param bool $as_options Whether to return as an associative array (provider_id => name) for select options.
     * @return array A list of provider IDs or an associative array.
     */
    public function get_providers_by_capability($capability, $as_options = false) {
        if (!isset($this->capabilities[$capability])) {
            return $as_options ? [] : [];
        }
        if ($as_options) {
            return $this->capabilities[$capability]; // Already stored as ID => Name
        }
        return array_keys($this->capabilities[$capability]);
    }

    /**
     * Get an instance of a specific AI provider class.
     *
     * @param string $provider_id The ID of the provider.
     * @return MFW_AI_SUPER_Provider_Interface|null An instance of the provider class, or null if not found/configured.
     */
    private function get_provider_instance($provider_id) {
        if (!isset($this->providers[$provider_id])) {
            $this->plugin->logger->log("Attempted to get instance for unknown provider ID: " . $provider_id, "WARNING");
            return null;
        }

        $config = $this->providers[$provider_id];
        $class_name = $config['class'];

        if (!class_exists($class_name)) {
            // In a real plugin, you'd include the file here.
            // For this consolidated file, we assume it's defined below.
            $this->plugin->logger->log("Provider class not found: " . $class_name, "ERROR");
            return null;
        }

        // Check if required options (like API key) are set
        $is_configured = true;
        $missing_options = [];
        if (!empty($config['api_key_option']) && empty($this->plugin->get_option($config['api_key_option']))) {
            $is_configured = false;
            $missing_options[] = $config['api_key_option'];
        }
        if (!empty($config['endpoint_option']) && empty($this->plugin->get_option($config['endpoint_option']))) {
             // Allow default endpoint if available and no custom one set
             if (empty($config['default_endpoint']) || $this->plugin->get_option($config['endpoint_option']) !== $config['default_endpoint']) {
                $is_configured = false;
                $missing_options[] = $config['endpoint_option'];
             }
        }
        if (!empty($config['deployment_option']) && empty($this->plugin->get_option($config['deployment_option']))) {
             $is_configured = false;
             $missing_options[] = $config['deployment_option'];
        }
         // Add checks for other required options like region, secret key etc.

        if (!$is_configured) {
            $this->plugin->logger->log(sprintf("Provider %s is not fully configured. Missing options: %s", $provider_id, implode(', ', $missing_options)), "WARNING");
            return null;
        }

        // Instantiate the provider, passing necessary configuration options
        try {
            $provider_instance = new $class_name($this->plugin, $provider_id, $config);
            // Check if the instance implements the expected interface (optional but good practice)
            // if (!($provider_instance instanceof MFW_AI_SUPER_Provider_Interface)) {
            //     $this->plugin->logger->log("Provider class does not implement the required interface: " . $class_name, "ERROR");
            //     return null;
            // }
            return $provider_instance;
        } catch (Exception $e) {
            $this->plugin->logger->log("Error instantiating provider class " . $class_name . ": " . $e->getMessage(), "ERROR");
            return null;
        }
    }

    /**
     * Make a request to the preferred AI provider for a given capability, with fallback.
     *
     * @param string $capability The capability needed (e.g., 'text', 'image_generation').
     * @param mixed $data The data to send to the AI (e.g., prompt string, array of messages).
     * @param array $args Additional arguments (e.g., model, max_tokens, temperature, feature_id).
     * @return array An array with 'success' (bool), 'content' (mixed), and 'error' (string).
     */
    public function make_request($capability, $data, $args = []) {
        $preferred_option_key = "preferred_{$capability}_provider";
        $fallback_option_key = "fallback_order_{$capability}";

        $preferred_provider_id = $this->plugin->get_option($preferred_option_key);
        $fallback_order = $this->plugin->get_option($fallback_option_key, []);

        $tried_providers = [];
        $last_error = '';

        // Try the preferred provider first
        if (!empty($preferred_provider_id) && isset($this->providers[$preferred_provider_id])) {
            $tried_providers[] = $preferred_provider_id;
            $response = $this->make_request_to_specific_provider($preferred_provider_id, $data, $args);
            if ($response['success']) {
                return $response; // Success!
            } else {
                $last_error = $response['error'];
                $this->plugin->logger->log(sprintf("Preferred provider '%s' failed for capability '%s'. Error: %s", $preferred_provider_id, $capability, $last_error), "WARNING", ['args' => $args]);
            }
        } else {
             if (!empty($preferred_provider_id)) {
                 $this->plugin->logger->log(sprintf("Preferred provider '%s' for capability '%s' is not registered or configured. Trying fallback.", $preferred_provider_id, $capability), "WARNING");
             } else {
                 $this->plugin->logger->log(sprintf("No preferred provider set for capability '%s'. Trying fallback.", $capability), "INFO");
             }
        }

        // Try fallback providers in order
        if (is_array($fallback_order)) {
            foreach ($fallback_order as $provider_id) {
                // Skip if already tried or not a valid/configured provider for this capability
                if (in_array($provider_id, $tried_providers) || !isset($this->providers[$provider_id]) || !in_array($capability, $this->providers[$provider_id]['capabilities'])) {
                    continue;
                }

                $tried_providers[] = $provider_id;
                $response = $this->make_request_to_specific_provider($provider_id, $data, $args);
                if ($response['success']) {
                    $this->plugin->logger->log(sprintf("Fallback provider '%s' succeeded for capability '%s' after preferred failed.", $provider_id, $capability), "INFO", ['args' => $args]);
                    return $response; // Success with fallback!
                } else {
                    $last_error = $response['error'];
                    $this->plugin->logger->log(sprintf("Fallback provider '%s' failed for capability '%s'. Error: %s", $provider_id, $capability, $last_error), "WARNING", ['args' => $args]);
                }
            }
        }

        // If all providers failed
        $final_error_message = sprintf(__('All configured providers failed for "%s" capability. Last error: %s', MFW_AI_SUPER_TEXT_DOMAIN), $capability, $last_error);
        $this->plugin->logger->log("All providers failed for capability " . $capability . ". Last error: " . $last_error, "ERROR", ['tried' => $tried_providers, 'args' => $args]);

        return [
            'success' => false,
            'content' => null,
            'error' => $final_error_message,
            'tried_providers' => $tried_providers,
        ];
    }

    /**
     * Make a request to a specific AI provider.
     *
     * @param string $provider_id The ID of the provider.
     * @param mixed $data The data to send to the AI.
     * @param array $args Additional arguments (e.g., model, max_tokens, temperature, api_key_override, feature_id).
     * @return array An array with 'success' (bool), 'content' (mixed), and 'error' (string).
     */
    public function make_request_to_specific_provider($provider_id, $data, $args = []) {
        $provider_instance = $this->get_provider_instance($provider_id);

        if (!$provider_instance) {
            $error = sprintf(__('Provider "%s" is not available or not configured.', MFW_AI_SUPER_TEXT_DOMAIN), $provider_id);
            $this->log_api_call($provider_id, $args['feature_id'] ?? 'unknown', $args['model'] ?? null, false, 0, 0, 0, 0, $error, json_encode(['data' => $data, 'args' => $args]));
            return ['success' => false, 'content' => null, 'error' => $error];
        }

        // Log the start of the API call
        $start_time = microtime(true);
        $request_details = json_encode(['data' => $data, 'args' => $args], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        try {
            // Delegate the request to the specific provider instance
            $response = $provider_instance->call_api($data, $args);

            $end_time = microtime(true);
            $response_time_ms = round(($end_time - $start_time) * 1000);

            // Log the successful API call
            $this->log_api_call(
                $provider_id,
                $args['feature_id'] ?? 'unknown',
                $response['model'] ?? $args['model'] ?? null, // Use model from response if available
                true,
                $response['tokens_prompt'] ?? 0,
                $response['tokens_completion'] ?? 0,
                $response['tokens_total'] ?? 0,
                $response['cost'] ?? 0,
                null, // No error message on success
                $request_details,
                $response_time_ms
            );

            return $response; // Should contain 'success', 'content', 'model', 'tokens_...', 'cost', etc.

        } catch (Exception $e) {
            $end_time = microtime(true);
            $response_time_ms = round(($end_time - $start_time) * 1000);
            $error_message = $e->getMessage();
            $this->plugin->logger->log(sprintf("API call to provider '%s' failed: %s", $provider_id, $error_message), "ERROR", ['args' => $args, 'trace' => $e->getTraceAsString()]);

            // Log the failed API call
            $this->log_api_call(
                $provider_id,
                $args['feature_id'] ?? 'unknown',
                $args['model'] ?? null,
                false,
                0, 0, 0, 0, // No tokens/cost on failure (usually)
                $error_message,
                $request_details,
                $response_time_ms
            );

            return ['success' => false, 'content' => null, 'error' => $error_message];
        }
    }

    /**
     * Log an API call to the database.
     *
     * @param string $provider_id The ID of the provider.
     * @param string $feature_id Identifier for the feature using the API (e.g., 'gaw_article_generation', 'summary', 'chat').
     * @param string|null $model_id The specific model used (e.g., 'gpt-4', 'gemini-1.5-pro').
     * @param bool $is_success Whether the API call was successful.
     * @param int $tokens_prompt Tokens used for the prompt.
     * @param int $tokens_completion Tokens used for the completion.
     * @param int $tokens_total Total tokens used.
     * @param float $cost Estimated cost of the call.
     * @param string|null $error_message Error message if failed.
     * @param string|null $request_details JSON string of request details.
     * @param int|null $response_time_ms Response time in milliseconds.
     */
    private function log_api_call($provider_id, $feature_id, $model_id, $is_success, $tokens_prompt = 0, $tokens_completion = 0, $tokens_total = 0, $cost = 0.0, $error_message = null, $request_details = null, $response_time_ms = null) {
        if (!$this->plugin->get_option('log_to_db_enabled', true)) {
            return; // Don't log to DB if disabled
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'mfw_ai_super_api_logs';
        $max_api_log_entries = $this->plugin->get_option('api_logs_max_entries', 10000); // Assuming a separate option for API logs

        $wpdb->insert(
            $table_name,
            [
                'provider_id' => sanitize_text_field($provider_id),
                'feature_id' => sanitize_text_field($feature_id),
                'model_id' => sanitize_text_field($model_id),
                'timestamp' => current_time('mysql'),
                'is_success' => (int)$is_success,
                'tokens_used_prompt' => absint($tokens_prompt),
                'tokens_used_completion' => absint($tokens_completion),
                'tokens_used_total' => absint($tokens_total),
                'cost' => (float)$cost,
                'error_message' => sanitize_textarea_field($error_message),
                'request_details' => $request_details, // Store as JSON string
                'response_time_ms' => absint($response_time_ms),
            ],
            [
                '%s', '%s', '%s', '%s', '%d', '%d', '%d', '%d', '%f', '%s', '%s', '%d'
            ]
        );

        // Prune old API log entries if exceeding max
        $count = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table_name WHERE provider_id != %s", $this->plugin->logger->system_log_type)); // Exclude system logs
        if ($count > $max_api_log_entries) {
            // Delete the oldest entries
            $limit_to_delete = $count - $max_api_log_entries;
            $wpdb->query($wpdb->prepare("DELETE FROM $table_name WHERE provider_id != %s ORDER BY timestamp ASC LIMIT %d", $this->plugin->logger->system_log_type, $limit_to_delete));
        }
    }

    // TODO: Implement methods for calculating total usage, checking quotas, sending alerts
}

// --- AI Provider Interface (Optional but Recommended) ---
// interface MFW_AI_SUPER_Provider_Interface {
//     public function call_api($data, $args = []);
//     // Add other standard methods like get_supported_models(), check_connection(), etc.
// }


// --- AI Provider Classes (Implementations) ---
// Each provider class would extend a base class or implement an interface
// and contain the specific logic for interacting with that provider's API.

/**
 * Base Provider Class (Optional)
 * Provides common methods and structure for AI provider integrations.
 */
abstract class MFW_AI_SUPER_Provider_Base {
    protected $plugin;
    protected $provider_id;
    protected $config; // Configuration array for this provider

    /**
     * Constructor.
     *
     * @param Maziyar_Fetcher_Writer_AI_Super $plugin The main plugin instance.
     * @param string $provider_id The ID of this provider.
     * @param array $config The configuration array for this provider.
     */
    public function __construct(Maziyar_Fetcher_Writer_AI_Super $plugin, $provider_id, $config) {
        $this->plugin = $plugin;
        $this->provider_id = $provider_id;
        $this->config = $config;
    }

    /**
     * Abstract method to be implemented by concrete provider classes.
     * Handles the actual API call logic.
     *
     * @param mixed $data The data to send to the AI.
     * @param array $args Additional arguments for the API call.
     * @return array An array with 'success', 'content', 'model', 'tokens_prompt', 'tokens_completion', 'tokens_total', 'cost', 'error'.
     */
    abstract public function call_api($data, $args = []);

    /**
     * Helper method to get the API key for this provider, allowing override via args.
     *
     * @param array $args Additional arguments, potentially containing 'api_key_override'.
     * @return string|null The API key.
     */
    protected function get_api_key($args = []) {
        // Use override key from args if provided (e.g., for GAW specific key)
        if (isset($args['api_key_override']) && !empty($args['api_key_override'])) {
            return $args['api_key_override'];
        }
        // Fallback to the key stored in plugin options
        $option_key = $this->config['api_key_option'] ?? null;
        if ($option_key) {
            return $this->plugin->get_option($option_key);
        }
        return null;
    }

    /**
     * Helper method to get the API endpoint for this provider, allowing override via args.
     *
     * @param array $args Additional arguments, potentially containing 'endpoint_override'.
     * @return string|null The API endpoint URL.
     */
    protected function get_endpoint($args = []) {
        // Use override endpoint from args if provided
        if (isset($args['endpoint_override']) && !empty($args['endpoint_override'])) {
            return $args['endpoint_override'];
        }
        // Fallback to the endpoint stored in plugin options or default config
        $option_key = $this->config['endpoint_option'] ?? null;
        if ($option_key) {
            $endpoint = $this->plugin->get_option($option_key);
            if (!empty($endpoint)) return $endpoint;
        }
        // Use default endpoint from config if no option is set
        return $this->config['default_endpoint'] ?? null;
    }

    /**
     * Helper method to get the specific model ID for the API call.
     * Prioritizes args, then provider-specific default option, then a hardcoded default.
     *
     * @param array $args Additional arguments, potentially containing 'model'.
     * @return string|null The model ID.
     */
    protected function get_model($args = []) {
        // Use model from args if provided
        if (isset($args['model']) && !empty($args['model'])) {
            return $args['model'];
        }
        // Fallback to the provider's default model option
        $option_key = $this->config['model_option'] ?? null;
        if ($option_key) {
             $model = $this->plugin->get_option($option_key);
             if (!empty($model)) return $model;
        }
        // Fallback to a hardcoded default in config
        return $this->config['default_model'] ?? null;
    }

    /**
     * Estimate the cost of an API call based on token usage and model pricing.
     * This is a placeholder and needs actual pricing data per model/provider.
     *
     * @param string $model_id The model used.
     * @param int $tokens_prompt Tokens used for the prompt.
     * @param int $tokens_completion Tokens used for the completion.
     * @return float Estimated cost.
     */
    protected function estimate_cost($model_id, $tokens_prompt, $tokens_completion) {
        // TODO: Implement actual pricing logic based on provider and model
        // This would likely involve a separate class or data structure for pricing.
        $cost_per_million_tokens_input = 0; // Example price per 1M input tokens
        $cost_per_million_tokens_output = 0; // Example price per 1M output tokens

        // Example (very simplified):
        switch ($this->provider_id) {
            case 'openai':
                // Prices vary by model (gpt-3.5-turbo, gpt-4, etc.)
                // Example for gpt-3.5-turbo (approx $0.50 / 1M input, $1.50 / 1M output)
                $cost_per_million_tokens_input = 0.50;
                $cost_per_million_tokens_output = 1.50;
                // Need more detailed logic here based on $model_id
                break;
            case 'google_gemini':
                 // Prices vary by model (gemini-pro, gemini-1.5-pro, etc.)
                 // Example for gemini-1.0-pro (approx $0.50 / 1M input, $1.50 / 1M output)
                 $cost_per_million_tokens_input = 0.50;
                 $cost_per_million_tokens_output = 1.50;
                 // Need more detailed logic here based on $model_id
                 break;
            // Add other providers and their pricing
        }

        $cost = ($tokens_prompt / 1000000) * $cost_per_million_tokens_input + ($tokens_completion / 1000000) * $cost_per_million_tokens_output;

        return $cost;
    }

    /**
     * Helper method to handle HTTP requests using WordPress HTTP API.
     *
     * @param string $url The API endpoint URL.
     * @param array $args Arguments for wp_remote_request.
     * @return array WordPress HTTP API response array.
     * @throws Exception If the HTTP request fails or returns an error.
     */
    protected function make_http_request($url, $args = []) {
        $defaults = [
            'timeout' => 60, // Default timeout
            'headers' => [
                'Content-Type' => 'application/json',
            ],
            'method' => 'POST',
            'body' => null, // Request body (should be JSON encoded)
        ];
        $args = wp_parse_args($args, $defaults);

        // Ensure body is a JSON string if it's an array
        if (is_array($args['body'])) {
            $args['body'] = wp_json_encode($args['body']);
        }

        $response = wp_remote_request($url, $args);

        if (is_wp_error($response)) {
            throw new Exception(sprintf(__('HTTP request failed: %s', MFW_AI_SUPER_TEXT_DOMAIN), $response->get_error_message()));
        }

        $http_status = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);

        if ($http_status < 200 || $http_status >= 300) {
            // Attempt to parse error message from body if available
            $error_details = $response_body;
            $decoded_body = json_decode($response_body, true);
            if ($decoded_body && isset($decoded_body['error']['message'])) {
                $error_details = $decoded_body['error']['message'];
            } elseif ($decoded_body && isset($decoded_body['error'])) {
                 $error_details = is_array($decoded_body['error']) ? json_encode($decoded_body['error']) : $decoded_body['error'];
            } elseif ($decoded_body && isset($decoded_body['message'])) {
                 $error_details = $decoded_body['message'];
            }

            throw new Exception(sprintf(__('API returned HTTP status %d: %s', MFW_AI_SUPER_TEXT_DOMAIN), $http_status, $error_details));
        }

        return $response;
    }

    /**
     * Helper method to parse JSON response body and handle common API response structures.
     *
     * @param string $response_body The JSON response body string.
     * @return array The decoded response array.
     * @throws Exception If JSON decoding fails or response structure is unexpected.
     */
    protected function parse_json_response($response_body) {
        $decoded_body = json_decode($response_body, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception(sprintf(__('Failed to decode JSON response: %s', MFW_AI_SUPER_TEXT_DOMAIN), json_last_error_msg()));
        }

        if (!is_array($decoded_body)) {
             throw new Exception(__('Unexpected API response format (not a JSON object).', MFW_AI_SUPER_TEXT_DOMAIN));
        }

        return $decoded_body;
    }
}


// --- Concrete AI Provider Classes ---

/**
 * OpenAI Provider Implementation
 */
class MFW_AI_SUPER_Provider_OpenAI extends MFW_AI_SUPER_Provider_Base {

    public function call_api($data, $args = []) {
        $api_key = $this->get_api_key($args);
        if (empty($api_key)) {
            throw new Exception(__('OpenAI API key is not set.', MFW_AI_SUPER_TEXT_DOMAIN));
        }

        $model = $this->get_model($args) ?? 'gpt-3.5-turbo'; // Default OpenAI text model
        $feature_id = $args['feature_id'] ?? 'unknown';
        $endpoint = 'https://api.openai.com/v1/';

        $request_body = [];
        $response_content = null;
        $tokens_prompt = 0;
        $tokens_completion = 0;
        $tokens_total = 0;
        $cost = 0;
        $returned_model = null;

        try {
            switch ($feature_id) {
                case 'text': // General text completion (legacy or simple)
                case 'chat': // Chat completion (recommended)
                case 'gaw_article_generation': // GAW uses chat completion
                case 'ai_assistant_direct_prompt': // Assistant uses chat completion
                    $endpoint .= 'chat/completions';
                    // $data is expected to be an array of messages or a single prompt string
                    if (is_string($data)) {
                         $request_body['messages'] = [['role' => 'user', 'content' => $data]];
                    } elseif (is_array($data)) {
                         $request_body['messages'] = $data; // Assume data is already in message format
                    } else {
                         throw new Exception(__('Invalid data format for chat completion.', MFW_AI_SUPER_TEXT_DOMAIN));
                    }
                    $request_body['model'] = $model;
                    $request_body['max_tokens'] = $args['max_tokens'] ?? 4096; // Default max tokens
                    $request_body['temperature'] = $args['temperature'] ?? 0.7;

                    $response = $this->make_http_request($endpoint, [
                        'headers' => [
                            'Content-Type' => 'application/json',
                            'Authorization' => 'Bearer ' . $api_key,
                        ],
                        'body' => $request_body,
                    ]);

                    $decoded_response = $this->parse_json_response(wp_remote_retrieve_body($response));

                    if (isset($decoded_response['choices'][0]['message']['content'])) {
                        $response_content = $decoded_response['choices'][0]['message']['content'];
                        $tokens_prompt = $decoded_response['usage']['prompt_tokens'] ?? 0;
                        $tokens_completion = $decoded_response['usage']['completion_tokens'] ?? 0;
                        $tokens_total = $decoded_response['usage']['total_tokens'] ?? 0;
                        $returned_model = $decoded_response['model'] ?? $model;
                        $cost = $this->estimate_cost($returned_model, $tokens_prompt, $tokens_completion); // Estimate cost
                    } else {
                        throw new Exception(__('Unexpected response structure from OpenAI Chat API.', MFW_AI_SUPER_TEXT_DOMAIN));
                    }
                    break;

                case 'image_generation': // DALL-E
                    $endpoint .= 'images/generations';
                    // $data is expected to be the image prompt string
                    if (!is_string($data) || empty($data)) {
                         throw new Exception(__('Invalid or empty prompt for image generation.', MFW_AI_SUPER_TEXT_DOMAIN));
                    }
                    $request_body['prompt'] = $data;
                    $request_body['n'] = $args['n'] ?? 1; // Number of images
                    $request_body['size'] = $args['size'] ?? '1024x1024'; // Image size
                    $request_body['response_format'] = $args['response_format'] ?? 'url'; // 'url' or 'b64_json'
                    $request_body['model'] = $model === 'dall-e-3' ? 'dall-e-3' : 'dall-e-2'; // Ensure correct DALL-E model

                    $response = $this->make_http_request($endpoint, [
                        'headers' => [
                            'Content-Type' => 'application/json',
                            'Authorization' => 'Bearer ' . $api_key,
                        ],
                        'body' => $request_body,
                    ]);

                    $decoded_response = $this->parse_json_response(wp_remote_retrieve_body($response));

                    if (isset($decoded_response['data'][0])) {
                        // Returns an array of image objects, each with 'url' or 'b64_json'
                        $response_content = $decoded_response['data'];
                        // DALL-E pricing is typically per image generated, not per token.
                        // Need to adjust cost estimation logic.
                        $cost = $this->estimate_cost($model, $request_body['n'], 0); // Placeholder
                        $returned_model = $decoded_response['model'] ?? $model;
                    } else {
                        throw new Exception(__('Unexpected response structure from OpenAI Image API.', MFW_AI_SUPER_TEXT_DOMAIN));
                    }
                    break;

                case 'audio_transcription': // Whisper
                    $endpoint .= 'audio/transcriptions';
                    // $data is expected to be the file path or file data
                    // This requires a different content type ('multipart/form-data') and file handling.
                    // Implementing file uploads via wp_remote_request is more complex.
                    // For simplicity, this is a placeholder. A real implementation would handle file input.
                    throw new Exception(__('Audio transcription feature not fully implemented for OpenAI provider.', MFW_AI_SUPER_TEXT_DOMAIN));
                    break;

                case 'tts': // Text-to-Speech
                    $endpoint .= 'audio/speech';
                    // $data is expected to be the text string
                    if (!is_string($data) || empty($data)) {
                         throw new Exception(__('Invalid or empty text for TTS.', MFW_AI_SUPER_TEXT_DOMAIN));
                    }
                    $request_body['model'] = $model === 'tts-1' ? 'tts-1' : 'tts-1-hd'; // Ensure correct TTS model
                    $request_body['input'] = $data;
                    $request_body['voice'] = $args['voice'] ?? 'alloy'; // Default voice
                    $request_body['response_format'] = $args['response_format'] ?? 'mp3'; // mp3, opus, aac, flac
                    $request_body['speed'] = $args['speed'] ?? 1.0; // 0.25 to 4.0

                    $response = $this->make_http_request($endpoint, [
                        'headers' => [
                            'Authorization' => 'Bearer ' . $api_key,
                        ],
                        'body' => wp_json_encode($request_body),
                        'method' => 'POST',
                        'timeout' => 60,
                        'stream' => true, // Expecting binary data stream
                    ]);

                    // TTS response is binary audio data, not JSON
                    $response_content = wp_remote_retrieve_body($response);
                    // Cost estimation for TTS is based on input characters.
                    $cost = $this->estimate_cost($model, mb_strlen($data), 0); // Placeholder
                    $returned_model = $model; // Model is sent in request, not returned in header/body

                    // For AJAX, we might need to return a data URL or save to a temp file.
                    // For now, returning the raw binary content. The caller needs to handle this.
                    // A better approach for web would be to save to wp-content/uploads and return the URL.

                    break;

                case 'embedding':
                    $endpoint .= 'embeddings';
                     // $data is expected to be a string or array of strings
                    if (empty($data)) {
                         throw new Exception(__('Invalid or empty input for embedding.', MFW_AI_SUPER_TEXT_DOMAIN));
                    }
                    $request_body['model'] = $model === 'text-embedding-3-small' ? 'text-embedding-3-small' : 'text-embedding-ada-002'; // Ensure correct embedding model
                    $request_body['input'] = $data;
                    // Optional: encoding_format, user

                    $response = $this->make_http_request($endpoint, [
                        'headers' => [
                            'Content-Type' => 'application/json',
                            'Authorization' => 'Bearer ' . $api_key,
                        ],
                        'body' => $request_body,
                    ]);

                    $decoded_response = $this->parse_json_response(wp_remote_retrieve_body($response));

                    if (isset($decoded_response['data'][0]['embedding'])) {
                        // Returns an array of embedding objects, each with 'embedding' (array of floats)
                        $response_content = $decoded_response['data'];
                         $tokens_prompt = $decoded_response['usage']['prompt_tokens'] ?? 0;
                         $tokens_total = $decoded_response['usage']['total_tokens'] ?? 0;
                         $returned_model = $decoded_response['model'] ?? $model;
                         $cost = $this->estimate_cost($returned_model, $tokens_total, 0); // Embedding cost is based on total tokens
                    } else {
                        throw new Exception(__('Unexpected response structure from OpenAI Embedding API.', MFW_AI_SUPER_TEXT_DOMAIN));
                    }
                    break;

                default:
                    throw new Exception(sprintf(__('Unsupported feature "%s" for OpenAI provider.', MFW_AI_SUPER_TEXT_DOMAIN), $feature_id));
            }

            // Return a structured response array
            return [
                'success' => true,
                'content' => $response_content,
                'model' => $returned_model,
                'tokens_prompt' => $tokens_prompt,
                'tokens_completion' => $tokens_completion,
                'tokens_total' => $tokens_total,
                'cost' => $cost,
                'error' => null,
            ];

        } catch (Exception $e) {
            // Re-throw the exception after logging/handling within Service_Manager
            throw $e;
        }
    }
}

/**
 * Azure OpenAI Service Provider Implementation
 */
class MFW_AI_SUPER_Provider_AzureOpenAI extends MFW_AI_SUPER_Provider_Base {

    public function call_api($data, $args = []) {
        $api_key = $this->get_api_key($args);
        $endpoint_base = $this->get_endpoint($args); // e.g., https://YOUR_RESOURCE_NAME.openai.azure.com/
        $deployment_name = $this->plugin->get_option('azure_openai_deployment_name'); // Deployment name is crucial for Azure
        $api_version = '2024-02-15-preview'; // Or another supported version

        if (empty($api_key) || empty($endpoint_base) || empty($deployment_name)) {
            throw new Exception(__('Azure OpenAI Service is not fully configured (API Key, Endpoint, or Deployment Name missing).', MFW_AI_SUPER_TEXT_DOMAIN));
        }

        $model = $this->get_model($args); // Model might be less relevant for Azure, deployment name is key
        $feature_id = $args['feature_id'] ?? 'unknown';
        $endpoint = trailingslashit($endpoint_base) . 'openai/deployments/' . sanitize_text_field($deployment_name) . '/';

        $request_body = [];
        $response_content = null;
        $tokens_prompt = 0;
        $tokens_completion = 0;
        $tokens_total = 0;
        $cost = 0;
        $returned_model = null; // Azure might return deployment name, not model ID

        try {
            switch ($feature_id) {
                 case 'text': // General text completion (legacy or simple)
                 case 'chat': // Chat completion (recommended)
                 case 'ai_assistant_direct_prompt': // Assistant uses chat completion
                     $endpoint .= 'chat/completions?api-version=' . $api_version;
                     // $data is expected to be an array of messages or a single prompt string
                     if (is_string($data)) {
                          $request_body['messages'] = [['role' => 'user', 'content' => $data]];
                     } elseif (is_array($data)) {
                          $request_body['messages'] = $data; // Assume data is already in message format
                     } else {
                          throw new Exception(__('Invalid data format for chat completion.', MFW_AI_SUPER_TEXT_DOMAIN));
                     }
                     // Model is often implicitly tied to the deployment in Azure, but can sometimes be specified
                     if (!empty($model)) $request_body['model'] = $model; // Include model if specified
                     $request_body['max_tokens'] = $args['max_tokens'] ?? 4096;
                     $request_body['temperature'] = $args['temperature'] ?? 0.7;

                     $response = $this->make_http_request($endpoint, [
                         'headers' => [
                             'Content-Type' => 'application/json',
                             'api-key' => $api_key, // Azure uses 'api-key' header
                         ],
                         'body' => $request_body,
                     ]);

                     $decoded_response = $this->parse_json_response(wp_remote_retrieve_body($response));

                     if (isset($decoded_response['choices'][0]['message']['content'])) {
                         $response_content = $decoded_response['choices'][0]['message']['content'];
                         $tokens_prompt = $decoded_response['usage']['prompt_tokens'] ?? 0;
                         $tokens_completion = $decoded_response['usage']['completion_tokens'] ?? 0;
                         $tokens_total = $decoded_response['usage']['total_tokens'] ?? 0;
                         $returned_model = $decoded_response['model'] ?? $deployment_name; // Azure often returns deployment name
                         $cost = $this->estimate_cost($returned_model, $tokens_prompt, $tokens_completion); // Estimate cost (Azure pricing differs)
                     } else {
                         throw new Exception(__('Unexpected response structure from Azure OpenAI Chat API.', MFW_AI_SUPER_TEXT_DOMAIN));
                     }
                     break;

                 case 'embedding':
                    $endpoint .= 'embeddings?api-version=' . $api_version;
                    // $data is expected to be a string or array of strings
                    if (empty($data)) {
                         throw new Exception(__('Invalid or empty input for embedding.', MFW_AI_SUPER_TEXT_DOMAIN));
                    }
                    $request_body['input'] = $data;
                    // Model might be needed depending on deployment setup
                    if (!empty($model)) $request_body['model'] = $model;

                    $response = $this->make_http_request($endpoint, [
                        'headers' => [
                            'Content-Type' => 'application/json',
                            'api-key' => $api_key,
                        ],
                        'body' => $request_body,
                    ]);

                    $decoded_response = $this->parse_json_response(wp_remote_retrieve_body($response));

                    if (isset($decoded_response['data'][0]['embedding'])) {
                        $response_content = $decoded_response['data'];
                         $tokens_prompt = $decoded_response['usage']['prompt_tokens'] ?? 0;
                         $tokens_total = $decoded_response['usage']['total_tokens'] ?? 0;
                         $returned_model = $decoded_response['model'] ?? $deployment_name; // Azure often returns deployment name
                         $cost = $this->estimate_cost($returned_model, $tokens_total, 0); // Embedding cost is based on total tokens
                    } else {
                        throw new Exception(__('Unexpected response structure from Azure OpenAI Embedding API.', MFW_AI_SUPER_TEXT_DOMAIN));
                    }
                    break;

                // Azure might support other capabilities like image generation (DALL-E) depending on deployment
                // Add cases for 'image_generation', 'audio_transcription', 'tts', 'image_analysis' if supported and deployed

                default:
                    throw new Exception(sprintf(__('Unsupported feature "%s" for Azure OpenAI provider.', MFW_AI_SUPER_TEXT_DOMAIN), $feature_id));
            }

            // Return a structured response array
            return [
                'success' => true,
                'content' => $response_content,
                'model' => $returned_model,
                'tokens_prompt' => $tokens_prompt,
                'tokens_completion' => $tokens_completion,
                'tokens_total' => $tokens_total,
                'cost' => $cost,
                'error' => null,
            ];

        } catch (Exception $e) {
            // Re-throw the exception
            throw $e;
        }
    }
}

/**
 * Google Gemini Provider Implementation
 */
class MFW_AI_SUPER_Provider_GoogleGemini extends MFW_AI_SUPER_Provider_Base {

    public function call_api($data, $args = []) {
        $api_key = $this->get_api_key($args);
        if (empty($api_key)) {
            throw new Exception(__('Google Gemini API key is not set.', MFW_AI_SUPER_TEXT_DOMAIN));
        }

        $model = $this->get_model($args) ?? 'gemini-1.5-pro-latest'; // Default Gemini model
        $feature_id = $args['feature_id'] ?? 'unknown';
        $endpoint_base = 'https://generativelanguage.googleapis.com/v1beta/';

        $request_body = [];
        $response_content = null;
        $tokens_prompt = 0;
        $tokens_completion = 0;
        $tokens_total = 0;
        $cost = 0;
        $returned_model = null;

        try {
            switch ($feature_id) {
                case 'text': // General text generation
                case 'chat': // Chat conversation
                case 'gaw_article_generation': // GAW uses text generation or chat
                case 'ai_assistant_direct_prompt': // Assistant uses chat
                    $endpoint = $endpoint_base . 'models/' . sanitize_text_field($model) . ':generateContent?key=' . $api_key;

                    // Gemini API expects 'contents' array, each with 'parts'
                    if (is_string($data)) {
                        $request_body['contents'] = [['parts' => [['text' => $data]]]];
                    } elseif (is_array($data) && isset($data[0]['parts'])) {
                         $request_body['contents'] = $data; // Assume data is already in Gemini format
                    } elseif (is_array($data) && isset($data[0]['role'])) {
                         // Convert OpenAI-style messages to Gemini format
                         $request_body['contents'] = $this->convert_openai_messages_to_gemini($data);
                    }
                    else {
                        throw new Exception(__('Invalid data format for Gemini text generation/chat.', MFW_AI_SUPER_TEXT_DOMAIN));
                    }

                    // Add generation config and safety settings if needed from args
                    $request_body['generationConfig'] = [
                         'temperature' => $args['temperature'] ?? 0.7,
                         'maxOutputTokens' => $args['max_tokens'] ?? 4096,
                         // Add other config like topP, topK, stopSequences
                    ];
                    // $request_body['safetySettings'] = [...];

                    $response = $this->make_http_request($endpoint, [
                        'headers' => [
                            'Content-Type' => 'application/json',
                        ],
                        'body' => $request_body,
                    ]);

                    $decoded_response = $this->parse_json_response(wp_remote_retrieve_body($response));

                    // Gemini response structure: candidates[0].content.parts[0].text
                    if (isset($decoded_response['candidates'][0]['content']['parts'][0]['text'])) {
                        $response_content = $decoded_response['candidates'][0]['content']['parts'][0]['text'];

                        // Gemini token usage is in 'usageMetadata'
                        $tokens_prompt = $decoded_response['usageMetadata']['promptTokenCount'] ?? 0;
                        $tokens_completion = $decoded_response['usageMetadata']['candidatesTokenCount'] ?? 0;
                        $tokens_total = $tokens_prompt + $tokens_completion; // Gemini provides total directly sometimes too
                        $returned_model = $decoded_response['model'] ?? $model;
                        $cost = $this->estimate_cost($returned_model, $tokens_prompt, $tokens_completion); // Estimate cost

                    } elseif (isset($decoded_response['promptFeedback']['blockReason'])) {
                        // Handle safety blocking
                        $block_reason = $decoded_response['promptFeedback']['blockReason'];
                        $filter_reason = $decoded_response['promptFeedback']['safetyRatings'][0]['probability'] ?? 'unknown';
                        throw new Exception(sprintf(__('Gemini API blocked response: %s (Reason: %s)', MFW_AI_SUPER_TEXT_DOMAIN), $block_reason, $filter_reason));
                    }
                    else {
                        throw new Exception(__('Unexpected response structure from Google Gemini API.', MFW_AI_SUPER_TEXT_DOMAIN));
                    }
                    break;

                case 'embedding':
                    $endpoint = $endpoint_base . 'models/' . sanitize_text_field($model) . ':embedContent?key=' . $api_key;
                    // $data is expected to be a string or array of strings
                    if (empty($data)) {
                         throw new Exception(__('Invalid or empty input for embedding.', MFW_AI_SUPER_TEXT_DOMAIN));
                    }
                    // Embedding requires 'content' with 'parts'
                    if (is_string($data)) {
                        $request_body['content'] = ['parts' => [['text' => $data]]];
                    } elseif (is_array($data) && isset($data[0]['parts'])) {
                         // Handle batch embedding if data is an array of content structures
                         $endpoint = $endpoint_base . 'models/' . sanitize_text_field($model) . ':batchEmbedContents?key=' . $api_key;
                         $request_body['requests'] = [];
                         foreach($data as $item) {
                             if(isset($item['parts'])) {
                                 $request_body['requests'][] = ['model' => $model, 'content' => $item];
                             }
                         }
                         if(empty($request_body['requests'])) throw new Exception(__('Invalid data format for batch embedding.', MFW_AI_SUPER_TEXT_DOMAIN));

                    } else {
                        throw new Exception(__('Invalid data format for embedding.', MFW_AI_SUPER_TEXT_DOMAIN));
                    }


                    $response = $this->make_http_request($endpoint, [
                        'headers' => [
                            'Content-Type' => 'application/json',
                        ],
                        'body' => $request_body,
                    ]);

                    $decoded_response = $this->parse_json_response(wp_remote_retrieve_body($response));

                    if (isset($decoded_response['embedding']['values']) || isset($decoded_response['embeddings'])) {
                        // Single embedding: embedding.values
                        // Batch embedding: embeddings[0].values
                        $response_content = isset($decoded_response['embedding']['values']) ? [$decoded_response['embedding']] : $decoded_response['embeddings'];

                         // Gemini embedding cost is based on total tokens
                         // Need to calculate total tokens from input data
                         $total_input_tokens = 0;
                         if (is_string($data)) {
                             // Rough estimation or use a token counter if available
                             $total_input_tokens = str_word_count($data) * 1.3; // Very rough estimate
                         } elseif (is_array($data)) {
                              foreach($data as $item) {
                                  if(isset($item['parts'])) {
                                      foreach($item['parts'] as $part) {
                                          if(isset($part['text'])) {
                                               $total_input_tokens += str_word_count($part['text']) * 1.3; // Rough estimate
                                          }
                                      }
                                  }
                              }
                         }
                         // Gemini API might return token count in usageMetadata for embedding too
                         $tokens_total = $decoded_response['usageMetadata']['totalTokenCount'] ?? round($total_input_tokens);
                         $cost = $this->estimate_cost($model, $tokens_total, 0); // Embedding cost is based on total tokens
                         $returned_model = $model; // Model is in URL, not response body

                    } else {
                        throw new Exception(__('Unexpected response structure from Google Gemini Embedding API.', MFW_AI_SUPER_TEXT_DOMAIN));
                    }
                    break;

                // Add other capabilities like image analysis if Gemini supports them via this API

                default:
                    throw new Exception(sprintf(__('Unsupported feature "%s" for Google Gemini provider.', MFW_AI_SUPER_TEXT_DOMAIN), $feature_id));
            }

            // Return a structured response array
            return [
                'success' => true,
                'content' => $response_content,
                'model' => $returned_model,
                'tokens_prompt' => $tokens_prompt,
                'tokens_completion' => $tokens_completion,
                'tokens_total' => $tokens_total,
                'cost' => $cost,
                'error' => null,
            ];

        } catch (Exception $e) {
            // Re-throw the exception
            throw $e;
        }
    }

    /**
     * Helper to convert OpenAI-style messages to Gemini format.
     *
     * @param array $openai_messages Array of messages in OpenAI format (role, content).
     * @return array Messages in Gemini format (contents array with parts).
     */
    private function convert_openai_messages_to_gemini($openai_messages) {
        $gemini_contents = [];
        foreach ($openai_messages as $message) {
            $role = $message['role'] === 'assistant' ? 'model' : $message['role']; // Map 'assistant' to 'model'
            $gemini_contents[] = [
                'role' => $role,
                'parts' => [['text' => $message['content']]],
            ];
        }
        return $gemini_contents;
    }
}

/**
 * xAI Grok Provider Implementation (Placeholder)
 */
class MFW_AI_SUPER_Provider_xaiGrok extends MFW_AI_SUPER_Provider_Base {

    public function call_api($data, $args = []) {
        // This is a placeholder. Actual implementation requires Grok API details.
        throw new Exception(__('xAI Grok provider is a placeholder and not yet fully implemented.', MFW_AI_SUPER_TEXT_DOMAIN));

        // Example structure (highly speculative):
        // $api_key = $this->get_api_key($args);
        // $endpoint = 'https://api.grok.xai/v1/'; // Speculative endpoint
        // $model = $this->get_model($args) ?? 'grok-1'; // Speculative model
        // $feature_id = $args['feature_id'] ?? 'unknown';

        // try {
        //     switch ($feature_id) {
        //         case 'text':
        //         case 'chat':
        //             $endpoint .= 'chat/completions'; // Speculative endpoint path
        //             // ... build request body based on Grok API spec ...
        //             $response = $this->make_http_request($endpoint, [
        //                 'headers' => [
        //                     'Content-Type' => 'application/json',
        //                     'Authorization' => 'Bearer ' . $api_key, // Speculative auth
        //                 ],
        //                 'body' => $request_body,
        //             ]);
        //             // ... parse response ...
        //             break;
        //         // Add other Grok capabilities
        //         default:
        //             throw new Exception(sprintf(__('Unsupported feature "%s" for xAI Grok provider.', MFW_AI_SUPER_TEXT_DOMAIN), $feature_id));
        //     }
        //     // ... return structured response ...
        // } catch (Exception $e) {
        //     throw $e; // Re-throw
        // }
    }
}

/**
 * Ollama Provider Implementation
 */
class MFW_AI_SUPER_Provider_Ollama extends MFW_AI_SUPER_Provider_Base {

    public function call_api($data, $args = []) {
        $endpoint_base = $this->get_endpoint($args) ?? 'http://localhost:11434';
        $model = $this->get_model($args) ?? 'llama3'; // Default Ollama model
        $feature_id = $args['feature_id'] ?? 'unknown';
        $endpoint = trailingslashit($endpoint_base) . 'api/';

        $request_body = [];
        $response_content = null;
        $tokens_prompt = 0;
        $tokens_completion = 0;
        $tokens_total = 0;
        $cost = 0; // Ollama is typically free/local, cost is 0

        try {
            switch ($feature_id) {
                case 'text': // General text generation
                    $endpoint .= 'generate';
                     // $data is expected to be the prompt string
                    if (!is_string($data) || empty($data)) {
                         throw new Exception(__('Invalid or empty prompt for Ollama text generation.', MFW_AI_SUPER_TEXT_DOMAIN));
                    }
                    $request_body['model'] = $model;
                    $request_body['prompt'] = $data;
                    $request_body['stream'] = false; // Request non-streaming response for simplicity
                    // Add other Ollama generation parameters from args if needed

                    $response = $this->make_http_request($endpoint, [
                        'headers' => [
                            'Content-Type' => 'application/json',
                        ],
                        'body' => $request_body,
                    ]);

                    $decoded_response = $this->parse_json_response(wp_remote_retrieve_body($response));

                    if (isset($decoded_response['response'])) {
                        $response_content = $decoded_response['response'];
                        $tokens_prompt = $decoded_response['prompt_eval_count'] ?? 0;
                        $tokens_completion = $decoded_response['eval_count'] ?? 0;
                        $tokens_total = $tokens_prompt + $tokens_completion;
                        $returned_model = $decoded_response['model'] ?? $model;
                        $cost = 0; // Ollama is local, no API cost
                    } else {
                        throw new Exception(__('Unexpected response structure from Ollama generate API.', MFW_AI_SUPER_TEXT_DOMAIN));
                    }
                    break;

                 case 'chat': // Chat completion
                    $endpoint .= 'chat';
                     // $data is expected to be an array of messages in Ollama format
                    if (!is_array($data) || empty($data)) {
                         throw new Exception(__('Invalid or empty messages for Ollama chat.', MFW_AI_SUPER_TEXT_DOMAIN));
                    }
                    $request_body['model'] = $model;
                    $request_body['messages'] = $data; // Expects [{ role: "user", content: "..." }, { role: "assistant", content: "..." }, ...]
                    $request_body['stream'] = false; // Request non-streaming response

                    $response = $this->make_http_request($endpoint, [
                        'headers' => [
                            'Content-Type' => 'application/json',
                        ],
                        'body' => $request_body,
                    ]);

                    $decoded_response = $this->parse_json_response(wp_remote_retrieve_body($response));

                    if (isset($decoded_response['message']['content'])) {
                        $response_content = $decoded_response['message']['content'];
                        $tokens_prompt = $decoded_response['prompt_eval_count'] ?? 0;
                        $tokens_completion = $decoded_response['eval_count'] ?? 0;
                        $tokens_total = $tokens_prompt + $tokens_completion;
                        $returned_model = $decoded_response['model'] ?? $model;
                        $cost = 0; // Ollama is local
                    } else {
                        throw new Exception(__('Unexpected response structure from Ollama chat API.', MFW_AI_SUPER_TEXT_DOMAIN));
                    }
                    break;

                case 'embedding':
                    $endpoint .= 'embeddings';
                     // $data is expected to be the text string
                    if (!is_string($data) || empty($data)) {
                         throw new Exception(__('Invalid or empty text for Ollama embedding.', MFW_AI_SUPER_TEXT_DOMAIN));
                    }
                    $request_body['model'] = $model;
                    $request_body['prompt'] = $data;

                    $response = $this->make_http_request($endpoint, [
                        'headers' => [
                            'Content-Type' => 'application/json',
                        ],
                        'body' => $request_body,
                    ]);

                    $decoded_response = $this->parse_json_response(wp_remote_retrieve_body($response));

                    if (isset($decoded_response['embedding'])) {
                        $response_content = $decoded_response['embedding']; // Array of floats
                         // Ollama embedding token count might be in response headers or inferred from prompt length
                         // Placeholder for token count
                         $tokens_total = str_word_count($data) * 1.3; // Very rough estimate
                         $returned_model = $model;
                         $cost = 0; // Ollama is local
                    } else {
                        throw new Exception(__('Unexpected response structure from Ollama embeddings API.', MFW_AI_SUPER_TEXT_DOMAIN));
                    }
                    break;

                case 'classification':
                     // Ollama doesn't have a dedicated classification endpoint.
                     // Classification would typically be done by sending text to a text/chat model
                     // with a prompt asking it to classify.
                     // This case might delegate to the 'text' or 'chat' case with a specific prompt.
                     // For now, mark as not fully implemented.
                     throw new Exception(__('Ollama classification feature requires specific prompt engineering via text/chat endpoint.', MFW_AI_SUPER_TEXT_DOMAIN));
                     break;

                default:
                    throw new Exception(sprintf(__('Unsupported feature "%s" for Ollama provider.', MFW_AI_SUPER_TEXT_DOMAIN), $feature_id));
            }

            // Return a structured response array
            return [
                'success' => true,
                'content' => $response_content,
                'model' => $returned_model,
                'tokens_prompt' => $tokens_prompt,
                'tokens_completion' => $tokens_completion,
                'tokens_total' => $tokens_total,
                'cost' => $cost,
                'error' => null,
            ];

        } catch (Exception $e) {
            // Re-throw the exception
            throw $e;
        }
    }
}

/**
 * Anthropic Provider Implementation (for Claude)
 */
class MFW_AI_SUPER_Provider_Anthropic extends MFW_AI_SUPER_Provider_Base {

     public function call_api($data, $args = []) {
        $api_key = $this->get_api_key($args);
        if (empty($api_key)) {
            throw new Exception(__('Anthropic API key is not set.', MFW_AI_SUPER_TEXT_DOMAIN));
        }

        $model = $this->get_model($args) ?? 'claude-3-5-sonnet-20240620'; // Default Anthropic model (Sonnet 3.5)
        $feature_id = $args['feature_id'] ?? 'unknown';
        $endpoint = 'https://api.anthropic.com/v1/';
        $anthropic_version = '2023-06-01'; // Required header

        $request_body = [];
        $response_content = null;
        $tokens_prompt = 0;
        $tokens_completion = 0;
        $tokens_total = 0;
        $cost = 0;
        $returned_model = null;

        try {
            switch ($feature_id) {
                case 'text': // General text generation
                case 'chat': // Chat conversation (Messages API)
                case 'ai_assistant_direct_prompt': // Assistant uses chat
                    $endpoint .= 'messages'; // Anthropic's recommended endpoint for text/chat
                    // $data is expected to be an array of messages in Anthropic format or OpenAI format
                    // Anthropic Messages API expects [{ "role": "user", "content": "..." }, { "role": "assistant", "content": "..." }]
                    if (is_string($data)) {
                         throw new Exception(__('Anthropic Messages API requires data as an array of messages.', MFW_AI_SUPER_TEXT_DOMAIN));
                    } elseif (is_array($data) && isset($data[0]['role']) && isset($data[0]['content'])) {
                         // Assume data is already in message format (either Anthropic or OpenAI format)
                         // We need to ensure roles are 'user' or 'assistant'
                         $request_body['messages'] = array_map(function($msg) {
                             $msg['role'] = ($msg['role'] === 'model') ? 'assistant' : $msg['role']; // Map 'model' to 'assistant'
                             return $msg;
                         }, $data);
                    } else {
                         throw new Exception(__('Invalid data format for Anthropic Messages API.', MFW_AI_SUPER_TEXT_DOMAIN));
                    }

                    $request_body['model'] = $model;
                    $request_body['max_tokens'] = $args['max_tokens'] ?? 4096;
                    $request_body['temperature'] = $args['temperature'] ?? 0.7;
                    // $request_body['system'] = $args['system_prompt'] ?? null; // System prompt can be a separate field

                    $response = $this->make_http_request($endpoint, [
                        'headers' => [
                            'Content-Type' => 'application/json',
                            'x-api-key' => $api_key, // Anthropic uses 'x-api-key' header
                            'anthropic-version' => $anthropic_version, // Required version header
                        ],
                        'body' => $request_body,
                    ]);

                    $decoded_response = $this->parse_json_response(wp_remote_retrieve_body($response));

                    // Anthropic response structure: content[0].text
                    if (isset($decoded_response['content'][0]['text'])) {
                        $response_content = $decoded_response['content'][0]['text'];

                        // Anthropic token usage is in 'usage'
                        $tokens_prompt = $decoded_response['usage']['input_tokens'] ?? 0;
                        $tokens_completion = $decoded_response['usage']['output_tokens'] ?? 0;
                        $tokens_total = $tokens_prompt + $tokens_completion;
                        $returned_model = $decoded_response['model'] ?? $model;
                        $cost = $this->estimate_cost($returned_model, $tokens_prompt, $tokens_completion); // Estimate cost (Anthropic pricing differs)

                    } elseif (isset($decoded_response['type']) && $decoded_response['type'] === 'error') {
                         // Handle API errors returned in the body
                         $error_msg = $decoded_response['error']['message'] ?? 'Unknown Anthropic API error.';
                         throw new Exception(sprintf(__('Anthropic API Error: %s', MFW_AI_SUPER_TEXT_DOMAIN), $error_msg));
                    }
                    else {
                        throw new Exception(__('Unexpected response structure from Anthropic Messages API.', MFW_AI_SUPER_TEXT_DOMAIN));
                    }
                    break;

                // Add other Anthropic capabilities if available (e.g., embeddings if they add them)

                default:
                    throw new Exception(sprintf(__('Unsupported feature "%s" for Anthropic provider.', MFW_AI_SUPER_TEXT_DOMAIN), $feature_id));
            }

            // Return a structured response array
            return [
                'success' => true,
                'content' => $response_content,
                'model' => $returned_model,
                'tokens_prompt' => $tokens_prompt,
                'tokens_completion' => $tokens_completion,
                'tokens_total' => $tokens_total,
                'cost' => $cost,
                'error' => null,
            ];

        } catch (Exception $e) {
            // Re-throw the exception
            throw $e;
        }
    }
}

/**
 * IBM Watson NLU Provider Implementation
 */
class MFW_AI_SUPER_Provider_WatsonNLU extends MFW_AI_SUPER_Provider_Base {

    public function call_api($data, $args = []) {
        $api_key = $this->get_api_key($args);
        $endpoint_base = $this->get_endpoint($args); // e.g., https://api.{region}.natural-language-understanding.watson.cloud.ibm.com/instances/{instance_id}/
        $api_version = MFW_AI_SUPER_WATSON_NLU_VERSION; // Use the defined version constant

        if (empty($api_key) || empty($endpoint_base)) {
            throw new Exception(__('IBM Watson NLU is not fully configured (API Key or Endpoint missing).', MFW_AI_SUPER_TEXT_DOMAIN));
        }

        $feature_id = $args['feature_id'] ?? 'unknown';
        $endpoint = trailingslashit($endpoint_base) . 'v1/analyze?version=' . $api_version;

        $request_body = [];
        $response_content = null;
        $cost = 0; // Watson pricing is typically per feature/call, not tokens

        try {
            // Watson NLU 'analyze' endpoint takes text or URL and a list of features to analyze.
            // $data is expected to be the text string or a URL.
            if (empty($data) || (!is_string($data) && !is_array($data))) {
                 throw new Exception(__('Invalid or empty input for Watson NLU analysis.', MFW_AI_SUPER_TEXT_DOMAIN));
            }

            if (filter_var($data, FILTER_VALIDATE_URL)) {
                $request_body['url'] = $data;
            } else {
                $request_body['text'] = $data;
            }

            $request_body['features'] = []; // Specify which NLU features to use

            switch ($feature_id) {
                case 'classification':
                    // Watson NLU can do classification if a custom model is trained,
                    // or use a built-in model like 'categories'.
                    // This implementation assumes using the built-in 'categories' feature.
                    $request_body['features']['categories'] = new stdClass(); // Empty object for default options
                    // If using a custom model, you'd add model='your-model-id'
                    break;

                case 'text_analysis':
                    // Analyze for sentiment, entities, keywords, etc.
                    $request_body['features']['sentiment'] = new stdClass();
                    $request_body['features']['entities'] = new stdClass();
                    $request_body['features']['keywords'] = new stdClass();
                    // Add other features as needed: concepts, relations, semantic_roles, etc.
                    break;

                // Add other specific Watson NLU features as cases

                default:
                    throw new Exception(sprintf(__('Unsupported feature "%s" for IBM Watson NLU provider.', MFW_AI_SUPER_TEXT_DOMAIN), $feature_id));
            }

            $response = $this->make_http_request($endpoint, [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Authorization' => 'Basic ' . base64_encode('apikey:' . $api_key), // Watson uses Basic Auth with 'apikey'
                ],
                'body' => $request_body,
            ]);

            $decoded_response = $this->parse_json_response(wp_remote_retrieve_body($response));

            // Response structure depends on the features requested.
            // For 'categories', it's typically decoded_response['categories']
            // For 'sentiment', it's decoded_response['sentiment']
            // We'll return the whole decoded response and let the caller handle the specific feature data.
            $response_content = $decoded_response;
            $cost = $this->estimate_cost($feature_id, 0, 0); // Placeholder for Watson cost

            // Watson NLU doesn't have token counts in the same way as generative models.
            $tokens_prompt = 0;
            $tokens_completion = 0;
            $tokens_total = 0;
            $returned_model = $api_version; // Use API version or feature ID as model identifier

            // Check for Watson-specific errors in the response body
            if (isset($decoded_response['error'])) {
                 throw new Exception(sprintf(__('Watson NLU API Error: %s', MFW_AI_SUPER_TEXT_DOMAIN), $decoded_response['error']));
            }


            // Return a structured response array
            return [
                'success' => true,
                'content' => $response_content, // Return the full analysis result
                'model' => $returned_model,
                'tokens_prompt' => $tokens_prompt,
                'tokens_completion' => $tokens_completion,
                'tokens_total' => $tokens_total,
                'cost' => $cost,
                'error' => null,
            ];

        } catch (Exception $e) {
            // Re-throw the exception
            throw $e;
        }
    }
}

/**
 * AWS Polly Provider Implementation (Placeholder)
 */
class MFW_AI_SUPER_Provider_AWSPolly extends MFW_AI_SUPER_Provider_Base {

     public function call_api($data, $args = []) {
        // This is a placeholder. Actual implementation requires AWS SDK or signing requests manually.
        throw new Exception(__('AWS Polly provider is a placeholder and not yet fully implemented.', MFW_AI_SUPER_TEXT_DOMAIN));

        // Example structure (requires AWS SDK or complex manual signing):
        // $access_key = $this->get_api_key($args); // Using api_key_option for access key ID
        // $secret_key = $this->plugin->get_option('aws_polly_secret_key'); // Need a separate option key
        // $region = $this->plugin->get_option('aws_polly_region');

        // if (empty($access_key) || empty($secret_key) || empty($region)) {
        //     throw new Exception(__('AWS Polly is not fully configured (Access Key, Secret Key, or Region missing).', MFW_AI_SUPER_TEXT_DOMAIN));
        // }

        // $feature_id = $args['feature_id'] ?? 'tts'; // Polly is primarily TTS
        // $endpoint = 'https://polly.' . sanitize_text_field($region) . '.amazonaws.com/v1/speech'; // Example endpoint

        // try {
        //     switch ($feature_id) {
        //         case 'tts':
        //             // $data is expected to be the text string
        //             if (!is_string($data) || empty($data)) {
        //                  throw new Exception(__('Invalid or empty text for AWS Polly TTS.', MFW_AI_SUPER_TEXT_DOMAIN));
        //             }
        //             $request_body = [
        //                 'Text' => $data,
        //                 'OutputFormat' => $args['response_format'] ?? 'mp3',
        //                 'VoiceId' => $args['voice'] ?? 'Joanna', // Default voice ID
        //                 // Add other parameters like SampleRate, SpeechMarkTypes, TextType
        //             ];

        //             // Making signed AWS requests is complex. Requires calculating signatures.
        //             // Using AWS SDK for PHP is the recommended approach.
        //             // This would involve:
        //             // 1. Including the AWS SDK autoload file.
        //             // 2. Using the PollyClient from the SDK.
        //             // 3. Calling $client->synthesizeSpeech($request_body);
        //             // 4. Handling the result stream.

        //             // Placeholder HTTP request (won't work without signing)
        //             // $response = $this->make_http_request($endpoint, [
        //             //     'headers' => [
        //             //         'Content-Type' => 'application/json',
        //             //         // AWS requires complex 'Authorization' header with signature
        //             //     ],
        //             //     'body' => $request_body,
        //             //     'method' => 'POST',
        //             //     'timeout' => 60,
        //             //     'stream' => true, // Expect binary data
        //             // ]);

        //             // For now, throw exception indicating not implemented
        //             throw new Exception(__('AWS Polly TTS implementation requires AWS SDK.', MFW_AI_SUPER_TEXT_DOMAIN));

        //             // If using SDK:
        //             // $result = $client->synthesizeSpeech($request_body);
        //             // $response_content = $result->get('AudioStream')->getContents(); // Get binary data
        //             // Cost is per character. Need to calculate cost.
        //             // $cost = $this->estimate_cost($args['voice'] ?? 'standard', mb_strlen($data), 0); // Placeholder cost
        //             // $returned_model = $args['voice'] ?? 'standard';

        //             break;

        //         default:
        //             throw new Exception(sprintf(__('Unsupported feature "%s" for AWS Polly provider.', MFW_AI_SUPER_TEXT_DOMAIN), $feature_id));
        //     }

        //     // ... return structured response ...
        // } catch (Exception $e) {
        //     throw $e; // Re-throw
        // }
    }
}

/**
 * Azure Text-to-Speech Provider Implementation (Placeholder)
 */
class MFW_AI_SUPER_Provider_AzureTTS extends MFW_AI_SUPER_Provider_Base {

     public function call_api($data, $args = []) {
        // This is a placeholder. Actual implementation requires Azure Speech API details.
        throw new Exception(__('Azure Text-to-Speech provider is a placeholder and not yet fully implemented.', MFW_AI_SUPER_TEXT_DOMAIN));

        // Example structure (requires Azure Speech API details):
        // $api_key = $this->get_api_key($args); // Using api_key_option for speech key
        // $region = $this->plugin->get_option('azure_speech_region');

        // if (empty($api_key) || empty($region)) {
        //     throw new Exception(__('Azure Text-to-Speech is not fully configured (API Key or Region missing).', MFW_AI_SUPER_TEXT_DOMAIN));
        // }

        // $feature_id = $args['feature_id'] ?? 'tts'; // Azure TTS is primarily TTS
        // $endpoint = 'https://' . sanitize_text_field($region) . '.tts.speech.microsoft.com/cognitiveservices/v1'; // Example endpoint

        // try {
        //     switch ($feature_id) {
        //         case 'tts':
        //             // $data is expected to be the text string
        //             if (!is_string($data) || empty($data)) {
        //                  throw new Exception(__('Invalid or empty text for Azure TTS.', MFW_AI_SUPER_TEXT_DOMAIN));
        //             }

        //             // Azure TTS uses SSML (Speech Synthesis Markup Language) or plain text.
        //             // Request body is XML or plain text. Content-Type: application/ssml+xml or text/plain
        //             // Example SSML:
        //             // <speak version='1.0' xml:lang='en-US'><voice name='en-US-AvaMultilingualNeural'>Hello World!</voice></speak>

        //             $voice_name = $args['voice'] ?? 'en-US-AvaMultilingualNeural'; // Default voice name
        //             $output_format = $args['response_format'] ?? 'audio-16khz-128kbitrate-mp3'; // Default format

        //             $request_body = "<speak version='1.0' xml:lang='en-US'><voice name='" . esc_attr($voice_name) . "'>" . esc_html($data) . "</voice></speak>";


        //             $response = $this->make_http_request($endpoint, [
        //                 'headers' => [
        //                     'Content-Type' => 'application/ssml+xml',
        //                     'Ocp-Apim-Subscription-Key' => $api_key, // Azure uses this header
        //                     'X-Microsoft-OutputFormat' => $output_format,
        //                     'User-Agent' => 'MFW-AI-Super-Plugin', // Recommended User-Agent
        //                 ],
        //                 'body' => $request_body,
        //                 'method' => 'POST',
        //                 'timeout' => 60,
        //                 'stream' => true, // Expecting binary data stream
        //             ]);

        //             // Response is binary audio data
        //             $response_content = wp_remote_retrieve_body($response);
        //             // Cost estimation for Azure TTS is per character.
        //             $cost = $this->estimate_cost($voice_name, mb_strlen($data), 0); // Placeholder cost
        //             $returned_model = $voice_name;

        //             break;

        //         default:
        //             throw new Exception(sprintf(__('Unsupported feature "%s" for Azure Text-to-Speech provider.', MFW_AI_SUPER_TEXT_DOMAIN), $feature_id));
        //     }

        //     // ... return structured response ...
        // } catch (Exception $e) {
        //     throw $e; // Re-throw
        // }
    }
}

/**
 * Azure Computer Vision Provider Implementation (Placeholder)
 */
class MFW_AI_SUPER_Provider_AzureVision extends MFW_AI_SUPER_Provider_Base {

     public function call_api($data, $args = []) {
        // This is a placeholder. Actual implementation requires Azure Vision API details.
        throw new Exception(__('Azure Computer Vision provider is a placeholder and not yet fully implemented.', MFW_AI_SUPER_TEXT_DOMAIN));

        // Example structure (requires Azure Vision API details):
        // $api_key = $this->get_api_key($args); // Using api_key_option for vision key
        // $endpoint_base = $this->get_endpoint($args); // e.g., https://YOUR_RESOURCE_NAME.cognitiveservices.azure.com/

        // if (empty($api_key) || empty($endpoint_base)) {
        //     throw new Exception(__('Azure Computer Vision is not fully configured (API Key or Endpoint missing).', MFW_AI_SUPER_TEXT_DOMAIN));
        // }

        // $feature_id = $args['feature_id'] ?? 'image_analysis';
        // $api_version = '2024-02-01'; // Example API version

        // try {
        //     switch ($feature_id) {
        //         case 'image_analysis':
        //             // $data is expected to be the image URL or binary image data
        //             if (empty($data)) {
        //                  throw new Exception(__('Invalid or empty input for Azure Vision analysis.', MFW_AI_SUPER_TEXT_DOMAIN));
        //             }

        //             $endpoint = trailingslashit($endpoint_base) . 'computervision/imageanalysis:analyze?api-version=' . $api_version;

        //             // Specify features to analyze (e.g., Caption, DenseCaptions, Tags, Objects, People, Read, SmartCrops)
        //             $features = $args['features'] ?? ['Caption', 'Tags']; // Default features
        //             $endpoint .= '&features=' . implode(',', array_map('sanitize_text_field', $features));

        //             $request_body = [];
        //             $headers = ['Ocp-Apim-Subscription-Key' => $api_key];

        //             if (filter_var($data, FILTER_VALIDATE_URL)) {
        //                 $request_body['url'] = $data;
        //                 $headers['Content-Type'] = 'application/json';
        //             } else {
        //                 // Assuming $data is binary image content
        //                 $request_body = $data; // Send binary data directly as body
        //                 $headers['Content-Type'] = 'application/octet-stream'; // Or image/jpeg, image/png etc.
        //             }

        //             $response = $this->make_http_request($endpoint, [
        //                 'headers' => $headers,
        //                 'body' => $request_body,
        //                 'method' => 'POST',
        //                 'timeout' => 60,
        //             ]);

        //             $decoded_response = $this->parse_json_response(wp_remote_retrieve_body($response));

        //             // Response structure depends on features requested.
        //             // Example for Caption: decoded_response['caption']['text']
        //             $response_content = $decoded_response; // Return the full analysis result
        //             $cost = $this->estimate_cost($feature_id, 0, 0); // Placeholder for Azure Vision cost
        //             $returned_model = $api_version; // Use API version as model identifier

        //             // Check for Azure-specific errors
        //             if (isset($decoded_response['error'])) {
        //                  throw new Exception(sprintf(__('Azure Vision API Error: %s', MFW_AI_SUPER_TEXT_DOMAIN), $decoded_response['error']['message'] ?? 'Unknown error.'));
        //             }

        //             break;

        //         case 'image_generation': // DALL-E via Azure Vision
        //             // Azure can host DALL-E. The endpoint might be different or require a specific deployment.
        //             // This would likely mirror the OpenAI DALL-E implementation but with Azure auth/endpoint.
        //             throw new Exception(__('Azure Vision DALL-E image generation not fully implemented.', MFW_AI_SUPER_TEXT_DOMAIN));
        //             break;

        //         default:
        //             throw new Exception(sprintf(__('Unsupported feature "%s" for Azure Computer Vision provider.', MFW_AI_SUPER_TEXT_DOMAIN), $feature_id));
        //     }

        //     // ... return structured response ...
        // } catch (Exception $e) {
        //     throw $e; // Re-throw
        // }
    }
}


// --- Core Plugin Component Classes ---

/**
 * Content Generator Class
 * Handles AI-driven content creation, enhancement, and processing.
 */
class MFW_AI_SUPER_Content_Generator {
    private $plugin;

    /**
     * Constructor.
     *
     * @param Maziyar_Fetcher_Writer_AI_Super $plugin The main plugin instance.
     */
    public function __construct(Maziyar_Fetcher_Writer_AI_Super $plugin) {
        $this->plugin = $plugin;
    }

    /**
     * Generate new content for a specific post type using AI.
     *
     * @param string $post_type The slug of the post type.
     * @param string $prompt The prompt for the AI.
     * @param string $default_title Default title if AI doesn't provide one.
     * @param array $keywords Keywords related to the content.
     * @param int $author_id The author ID for the new post.
     * @param string $status The post status ('draft', 'publish', etc.).
     * @return int|WP_Error The new post ID on success, WP_Error on failure.
     */
    public function generate_content_for_post_type($post_type, $prompt, $default_title, $keywords = [], $author_id = 0, $status = 'draft') {
        // Check if the post type is enabled for AI content tools
        $enabled_post_types = $this->plugin->get_option('auto_content_post_types', ['post', 'page']);
        if (!in_array($post_type, $enabled_post_types)) {
             $error_msg = sprintf(__('AI content generation is not enabled for the "%s" post type.', MFW_AI_SUPER_TEXT_DOMAIN), $post_type);
             $this->plugin->logger->log($error_msg, 'ERROR', ['post_type' => $post_type]);
             return new WP_Error('mfw_ai_super_post_type_disabled', $error_msg);
        }

        // Use the preferred text provider for general content generation
        $ai_response = $this->plugin->ai_services->make_request('text', $prompt, [
            'feature_id' => 'content_generation',
            // Optionally pass model, temperature etc. from settings or args
        ]);

        if (!$ai_response['success']) {
            $error_msg = sprintf(__('AI content generation failed: %s', MFW_AI_SUPER_TEXT_DOMAIN), $ai_response['error']);
            $this->plugin->logger->log($error_msg, 'ERROR', ['prompt' => $prompt]);
            return new WP_Error('mfw_ai_super_ai_error', $error_msg);
        }

        $content = $ai_response['content'];

        // Attempt to extract title from content, fallback to default
        $post_title = $this->extract_title_from_content($content, $default_title);

        $post_data = [
            'post_title'   => sanitize_text_field($post_title),
            'post_content' => wp_kses_post($content), // Sanitize content
            'post_status'  => sanitize_text_field($status),
            'post_type'    => sanitize_text_field($post_type),
            'post_author'  => absint($author_id > 0 ? $author_id : get_current_user_id()), // Use current user if author_id is 0
        ];

        $post_id = wp_insert_post($post_data, true);

        if (is_wp_error($post_id)) {
            $error_msg = sprintf(__('Error creating post: %s', MFW_AI_SUPER_TEXT_DOMAIN), $post_id->get_error_message());
            $this->plugin->logger->log($error_msg, 'ERROR', ['post_data' => $post_data]);
            return $post_id; // Return the WP_Error object
        }

        $this->plugin->logger->log("Generated new content for post ID " . $post_id, "INFO", ['post_type' => $post_type, 'status' => $status]);

        // Handle SEO integration (meta description, focus keywords)
        $this->handle_seo_integration($post_id, $content, $keywords);

        // Auto-generate featured image if enabled
        if ($this->plugin->get_option('auto_generate_featured_image_on_creation')) {
             $image_prompt_base = $this->plugin->get_option('default_featured_image_prompt_template', 'A visually appealing image related to the topic: {{title}}');
             $image_prompt = str_replace(['{{title}}', '{{keywords}}'], [$post_title, implode(', ', $keywords)], $image_prompt_base);
             $this->generate_featured_image_for_post($post_id, $image_prompt);
        }

        return $post_id;
    }

    /**
     * Attempt to extract a title from the generated content.
     * Looks for H1 tags or the first line if no H1 is found.
     *
     * @param string $content The generated content string.
     * @param string $fallback_title A title to use if extraction fails.
     * @return string The extracted or fallback title.
     */
    public function extract_title_from_content($content, $fallback_title = '') {
        // Use DOMDocument to parse HTML and find H1
        $dom = new DOMDocument();
        // Suppress warnings for malformed HTML
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="utf-8" ?>' . $content, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();

        $xpath = new DOMXPath($dom);
        $h1_nodes = $xpath->query('//h1');

        if ($h1_nodes && $h1_nodes->length > 0) {
            $title = $h1_nodes->item(0)->textContent;
            // Remove the H1 tag from the content after extraction
            $h1_nodes->item(0)->parentNode->removeChild($h1_nodes->item(0));
            $content = $dom->saveHTML(); // Update content without H1

            // Clean up remaining HTML declaration if any
            $content = preg_replace('/^<!DOCTYPE.+?>/', '', $content);
            $content = preg_replace('/<html.+?>.*?<\/html>/is', '$0', $content); // Keep main HTML structure if any
            $content = trim($content);

            return sanitize_text_field($title);
        }

        // If no H1, try to use the first line as the title
        $lines = explode("\n", trim($content));
        if (!empty($lines[0])) {
            $title = $lines[0];
            // Remove the first line from content
            unset($lines[0]);
            $content = implode("\n", $lines);
            return sanitize_text_field($title);
        }

        // Fallback to the provided default title
        return sanitize_text_field($fallback_title);
    }


    /**
     * Handle integration with SEO plugins (Yoast, Rank Math) to populate fields.
     *
     * @param int $post_id The ID of the post.
     * @param string $content The generated content.
     * @param array $keywords Keywords related to the content.
     */
    public function handle_seo_integration($post_id, $content, $keywords = []) {
        $post = get_post($post_id);
        if (!$post) return;

        $post_type = $post->post_type;

        // Check if SEO features are enabled in settings
        $meta_description_enabled = $this->plugin->get_option('ai_generated_meta_description_enabled', true);
        $focus_keywords_enabled = $this->plugin->get_option('ai_generated_focus_keywords_enabled', true);

        if (!$meta_description_enabled && !$focus_keywords_enabled) {
            $this->plugin->logger->log("SEO integration disabled in settings.", "DEBUG");
            return; // SEO features disabled globally
        }

        $yoast_enabled = $this->plugin->get_option('seo_compatibility_yoast', true) && class_exists('WPSEO_Meta');
        $rankmath_enabled = $this->plugin->get_option('seo_compatibility_rankmath', true) && class_exists('RankMath');

        if (!$yoast_enabled && !$rankmath_enabled) {
            $this->plugin->logger->log("No supported SEO plugin detected or enabled in settings for SEO integration.", "DEBUG");
            return; // No supported/enabled SEO plugin
        }

        // Prepare data for AI analysis
        $analysis_data = [
            'title' => $post->post_title,
            'content' => $content,
            'keywords' => $keywords,
            'excerpt' => $post->post_excerpt,
        ];

        $meta_description = null;
        $focus_keyword = null; // Singular focus keyword for Yoast/Rank Math

        // Use AI to generate meta description and/or focus keyword if enabled
        if ($meta_description_enabled || $focus_keywords_enabled) {
            $analysis_prompt = $this->prepare_prompt_for_seo_analysis($analysis_data, $meta_description_enabled, $focus_keywords_enabled);

            $ai_response = $this->plugin->ai_services->make_request('text_analysis', $analysis_prompt, [
                'feature_id' => 'seo_analysis',
                // Use a text analysis capable model/provider
            ]);

            if ($ai_response['success'] && !empty($ai_response['content'])) {
                // Parse the AI response to extract meta description and focus keyword
                $parsed_seo_data = $this->parse_seo_analysis_response($ai_response['content']);
                $meta_description = $parsed_seo_data['meta_description'] ?? null;
                $focus_keyword = $parsed_seo_data['focus_keyword'] ?? null;
            } else {
                 $this->plugin->logger->log("AI SEO analysis failed: " . ($ai_response['error'] ?? 'Unknown error'), "WARNING", ['post_id' => $post_id]);
            }
        }

        // Update SEO fields in the database
        if ($yoast_enabled) {
            if ($meta_description_enabled && !empty($meta_description)) {
                WPSEO_Meta::set_value('metadesc', $meta_description, $post_id);
                 $this->plugin->logger->log("Updated Yoast SEO meta description for post ID " . $post_id, "DEBUG");
            }
            if ($focus_keywords_enabled && !empty($focus_keyword)) {
                 // Yoast stores focus keywords as a comma-separated string in a single meta key
                 // We'll just set the primary one for simplicity here.
                 WPSEO_Meta::set_value('focuskw', $focus_keyword, $post_id);
                 $this->plugin->logger->log("Updated Yoast SEO focus keyword for post ID " . $post_id, "DEBUG");
            }
        }

        if ($rankmath_enabled) {
             if ($meta_description_enabled && !empty($meta_description)) {
                 update_post_meta($post_id, 'rank_math_description', $meta_description);
                  $this->plugin->logger->log("Updated Rank Math meta description for post ID " . $post_id, "DEBUG");
             }
             if ($focus_keywords_enabled && !empty($focus_keyword)) {
                 // Rank Math stores focus keywords as a comma-separated string in a single meta key
                 update_post_meta($post_id, 'rank_math_focus_keyword', $focus_keyword);
                  $this->plugin->logger->log("Updated Rank Math focus keyword for post ID " . $post_id, "DEBUG");
             }
        }
    }

    /**
     * Prepare the prompt for AI SEO analysis.
     *
     * @param array $data Content data (title, content, keywords, excerpt).
     * @param bool $request_meta_description Whether to request a meta description.
     * @param bool $request_focus_keyword Whether to request a focus keyword.
     * @return string The prompt string.
     */
    private function prepare_prompt_for_seo_analysis($data, $request_meta_description, $request_focus_keyword) {
        $prompt = "Analyze the following content and provide SEO recommendations.\n";
        $prompt .= "Title: " . ($data['title'] ?? '') . "\n";
        $prompt .= "Content: " . ($data['content'] ?? '') . "\n";
        if (!empty($data['keywords'])) {
            $prompt .= "Keywords: " . implode(', ', $data['keywords']) . "\n";
        }
        if (!empty($data['excerpt'])) {
             $prompt .= "Excerpt: " . ($data['excerpt'] ?? '') . "\n";
        }
        $prompt .= "\n";

        $requested_items = [];
        if ($request_meta_description) {
            $requested_items[] = "a concise meta description (under 160 characters)";
        }
        if ($request_focus_keyword) {
            $requested_items[] = "a primary focus keyword";
        }

        if (!empty($requested_items)) {
            $prompt .= "Based on this, provide:\n";
            $prompt .= "- " . implode("\n- ", $requested_items) . "\n";
            $prompt .= "Format your response clearly, perhaps using labels like 'Meta Description:' and 'Focus Keyword:'.";
        } else {
             $prompt .= "Provide a general SEO analysis."; // Should not happen if both are false
        }

        return $prompt;
    }

    /**
     * Parse the AI response for SEO analysis results.
     *
     * @param string $response_text The text response from the AI.
     * @return array Associative array with 'meta_description' and 'focus_keyword' keys.
     */
    private function parse_seo_analysis_response($response_text) {
        $results = ['meta_description' => null, 'focus_keyword' => null];

        // Simple pattern matching to extract fields
        if (preg_match('/Meta Description:\s*(.+)/i', $response_text, $matches)) {
            $results['meta_description'] = trim($matches[1]);
        }
        if (preg_match('/Focus Keyword:\s*(.+)/i', $response_text, $matches)) {
            $results['focus_keyword'] = trim($matches[1]);
        }
        // Add more sophisticated parsing if needed (e.g., JSON response)

        return $results;
    }


    /**
     * Generate and attach an image to a post or as a standalone attachment.
     *
     * @param string $prompt The image generation prompt.
     * @param int $post_id Optional. The post ID to attach the image to.
     * @return int|WP_Error The attachment ID on success, WP_Error on failure.
     */
    public function generate_and_attach_image($prompt, $post_id = 0) {
        if (empty($prompt)) {
            $error_msg = __('Image generation prompt is empty.', MFW_AI_SUPER_TEXT_DOMAIN);
            $this->plugin->logger->log($error_msg, 'ERROR');
            return new WP_Error('mfw_ai_super_empty_image_prompt', $error_msg);
        }

        // Use the preferred image generation provider
        $provider_id = $this->plugin->get_option('default_image_generation_provider');
        if (empty($provider_id)) {
             $error_msg = __('No default image generation provider is set in settings.', MFW_AI_SUPER_TEXT_DOMAIN);
             $this->plugin->logger->log($error_msg, 'ERROR');
             return new WP_Error('mfw_ai_super_no_image_provider', $error_msg);
        }

        $ai_response = $this->plugin->ai_services->make_request('image_generation', $prompt, [
            'feature_id' => 'image_generation_tool',
            'model' => $provider_id === 'openai_dalle3' ? 'dall-e-3' : 'dall-e-2', // Specify DALL-E model if OpenAI
            'n' => 1, // Generate 1 image
            'size' => '1024x1024', // Default size
            'response_format' => 'url', // Request a URL
        ]);

        if (!$ai_response['success'] || empty($ai_response['content']) || !isset($ai_response['content'][0]['url'])) {
            $error_msg = sprintf(__('AI image generation failed: %s', MFW_AI_SUPER_TEXT_DOMAIN), $ai_response['error'] ?? 'Unknown error or no image URL returned.');
            $this->plugin->logger->log($error_msg, 'ERROR', ['prompt' => $prompt]);
            return new WP_Error('mfw_ai_super_image_gen_error', $error_msg);
        }

        $image_url = $ai_response['content'][0]['url'];

        // Download the image from the URL and upload it to the WordPress media library
        $upload_dir = wp_upload_dir();
        if (is_wp_error($upload_dir)) {
             $error_msg = sprintf(__('Error getting upload directory: %s', MFW_AI_SUPER_TEXT_DOMAIN), $upload_dir->get_error_message());
             $this->plugin->logger->log($error_msg, 'ERROR');
             return $upload_dir;
        }

        $image_data = file_get_contents($image_url); // Use file_get_contents for simplicity, wp_remote_get is better for external URLs
        if ($image_data === false) {
            // Fallback to wp_remote_get if file_get_contents fails (e.g., due to allow_url_fopen)
            $response = wp_remote_get($image_url, ['timeout' => 30]);
            if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
                 $error_msg = sprintf(__('Failed to download image from URL: %s', MFW_AI_SUPER_TEXT_DOMAIN), is_wp_error($response) ? $response->get_error_message() : wp_remote_retrieve_response_message($response));
                 $this->plugin->logger->log($error_msg, 'ERROR', ['image_url' => $image_url]);
                 return new WP_Error('mfw_ai_super_image_download_failed', $error_msg);
            }
            $image_data = wp_remote_retrieve_body($response);
        }


        $filename = basename(parse_url($image_url, PHP_URL_PATH));
        // Sanitize filename and ensure it has an extension
        $filename = sanitize_file_name($filename);
        if (empty(pathinfo($filename, PATHINFO_EXTENSION))) {
            $filename .= '.png'; // Assume PNG if no extension
        }

        $upload_path = $upload_dir['path'] . '/' . $filename;
        $upload_url = $upload_dir['url'] . '/' . $filename;

        // Ensure unique filename if needed
        $i = 1;
        $original_filename = $filename;
        while (file_exists($upload_path)) {
            $filename = pathinfo($original_filename, PATHINFO_FILENAME) . "-{$i}." . pathinfo($original_filename, PATHINFO_EXTENSION);
            $upload_path = $upload_dir['path'] . '/' . $filename;
            $upload_url = $upload_dir['url'] . '/' . $filename;
            $i++;
        }

        if (file_put_contents($upload_path, $image_data) === false) {
            $error_msg = sprintf(__('Failed to save image file to uploads directory: %s', MFW_AI_SUPER_TEXT_DOMAIN), $upload_path);
            $this->plugin->logger->log($error_msg, 'ERROR', ['upload_path' => $upload_path]);
            return new WP_Error('mfw_ai_super_image_save_failed', $error_msg);
        }

        // Create attachment post
        $file_type = wp_check_filetype($filename, null);
        $attachment_data = [
            'post_mime_type' => $file_type['type'],
            'post_title'     => sanitize_text_field(pathinfo($filename, PATHINFO_FILENAME)),
            'post_content'   => '',
            'post_status'    => 'inherit',
        ];

        $attachment_id = wp_insert_attachment($attachment_data, $upload_path, $post_id);

        if (is_wp_error($attachment_id)) {
            $error_msg = sprintf(__('Error creating attachment post: %s', MFW_AI_SUPER_TEXT_DOMAIN), $attachment_id->get_error_message());
            $this->plugin->logger->log($error_msg, 'ERROR');
            // Clean up the file if attachment post creation failed
            @unlink($upload_path);
            return $attachment_id;
        }

        // Generate attachment metadata and update
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        $attachment_meta = wp_generate_attachment_metadata($attachment_id, $upload_path);
        wp_update_attachment_metadata($attachment_id, $attachment_meta);

        // Set as featured image if post_id is provided
        if ($post_id > 0) {
            set_post_thumbnail($post_id, $attachment_id);
            $this->plugin->logger->log("Generated and set featured image for post ID " . $post_id, "INFO", ['attachment_id' => $attachment_id]);
        } else {
             $this->plugin->logger->log("Generated image attachment ID " . $attachment_id, "INFO");
        }

        // Generate Alt Text using AI if enabled
        $enabled_post_types_alt_text = $this->plugin->get_option('enable_image_alt_text_generation', []);
        $post_type_obj = $post_id > 0 ? get_post_type_object(get_post_type($post_id)) : null;
        $is_alt_text_enabled_for_context = ($post_id > 0 && isset($enabled_post_types_alt_text[$post_type_obj->name]) && $enabled_post_types_alt_text[$post_type_obj->name]) || ($post_id === 0 && isset($enabled_post_types_alt_text['attachment']) && $enabled_post_types_alt_text['attachment']); // Check if enabled for the post type or for attachments generally

        if ($this->plugin->get_option('enable_image_alt_text_generation') && $is_alt_text_enabled_for_context) {
             $alt_text_prompt = sprintf(__('Generate a concise alt text description for an image based on the following prompt: "%s"', MFW_AI_SUPER_TEXT_DOMAIN), $prompt);
             $alt_text_response = $this->plugin->ai_services->make_request('text', $alt_text_prompt, [
                 'feature_id' => 'image_alt_text',
                 'max_tokens' => 50, // Keep alt text short
             ]);

             if ($alt_text_response['success'] && !empty($alt_text_response['content'])) {
                 $alt_text = sanitize_text_field($alt_text_response['content']);
                 update_post_meta($attachment_id, '_wp_attachment_image_alt', $alt_text);
                 $this->plugin->logger->log("Generated and saved alt text for attachment ID " . $attachment_id, "DEBUG");
             } else {
                  $this->plugin->logger->log("Failed to generate alt text for attachment ID " . $attachment_id . ": " . ($alt_text_response['error'] ?? 'Unknown error'), "WARNING");
             }
        }


        return $attachment_id;
    }

    /**
     * Generate speech audio from text using AI.
     *
     * @param string $text The text to convert to speech.
     * @param array $args Additional arguments (e.g., voice, format).
     * @return string|WP_Error The URL to the audio file on success, WP_Error on failure.
     */
    public function generate_speech_from_text($text, $args = []) {
        if (empty($text)) {
             $error_msg = __('Text for speech synthesis is empty.', MFW_AI_SUPER_TEXT_DOMAIN);
             $this->plugin->logger->log($error_msg, 'ERROR');
             return new WP_Error('mfw_ai_super_empty_tts_text', $error_msg);
        }

        // Use the preferred TTS provider
        $provider_id = $this->plugin->get_option('preferred_tts_provider');
         if (empty($provider_id)) {
              $error_msg = __('No preferred Text-to-Speech provider is set in settings.', MFW_AI_SUPER_TEXT_DOMAIN);
              $this->plugin->logger->log($error_msg, 'ERROR');
              return new WP_Error('mfw_ai_super_no_tts_provider', $error_msg);
         }


        $ai_response = $this->plugin->ai_services->make_request('tts', $text, [
            'feature_id' => 'text_to_speech',
            // Pass voice, format etc. from args if needed
            'voice' => $args['voice'] ?? null,
            'response_format' => $args['format'] ?? 'mp3',
        ]);

        if (!$ai_response['success'] || empty($ai_response['content'])) {
            $error_msg = sprintf(__('AI speech generation failed: %s', MFW_AI_SUPER_TEXT_DOMAIN), $ai_response['error'] ?? 'Unknown error or no audio data returned.');
            $this->plugin->logger->log($error_msg, 'ERROR', ['text' => mb_strimwidth($text, 0, 100, '...')]);
            return new WP_Error('mfw_ai_super_tts_gen_error', $error_msg);
        }

        $audio_data = $ai_response['content']; // This is expected to be binary audio data

        // Save the audio data to a temporary file in the uploads directory
        $upload_dir = wp_upload_dir();
        if (is_wp_error($upload_dir)) {
             $error_msg = sprintf(__('Error getting upload directory for TTS file: %s', MFW_AI_SUPER_TEXT_DOMAIN), $upload_dir->get_error_message());
             $this->plugin->logger->log($error_msg, 'ERROR');
             return $upload_dir;
        }

        $filename = 'mfw-tts-' . md5($text . time()) . '.' . ($args['format'] ?? 'mp3');
        $upload_path = $upload_dir['path'] . '/' . $filename;
        $upload_url = $upload_dir['url'] . '/' . $filename;

        if (file_put_contents($upload_path, $audio_data) === false) {
            $error_msg = sprintf(__('Failed to save TTS audio file to uploads directory: %s', MFW_AI_SUPER_TEXT_DOMAIN), $upload_path);
            $this->plugin->logger->log($error_msg, 'ERROR', ['upload_path' => $upload_path]);
            return new WP_Error('mfw_ai_super_tts_save_failed', $error_msg);
        }

        $this->plugin->logger->log("Generated TTS audio file: " . $upload_url, "INFO");

        // Return the URL to the saved audio file
        return $upload_url;
    }

    /**
     * Prepare a prompt for drafting a full article based on a topic.
     *
     * @param string $topic The topic or keywords for the article.
     * @return string The prepared prompt.
     */
    public function prepare_prompt_for_article($topic) {
        // This could be a setting, but for now, a default template.
        // Use placeholders that will be replaced before sending to AI if needed (e.g., site title)
        $prompt_template = $this->plugin->get_option('gaw_prompt', 'Write a complete and SEO-friendly article about {keyword}. The article should include an introduction, a main body with several subheadings, and a conclusion. The tone of the article should be formal and informative.');

        // Replace {keyword} placeholder
        $prompt = str_replace('{keyword}', sanitize_text_field($topic), $prompt_template);

        // Replace other potential placeholders like {{site_title}}
        $prompt = $this->replace_placeholders($prompt);

        return $prompt;
    }

    /**
     * Replace dynamic placeholders in a prompt string.
     *
     * @param string $prompt The prompt string with placeholders.
     * @param array $context Optional context data (e.g., post data, user comment).
     * @return string The prompt string with placeholders replaced.
     */
    public function replace_placeholders($prompt, $context = []) {
        $replacements = [
            '{{site_title}}' => get_bloginfo('name'),
            '{{site_url}}' => home_url(),
            '{{current_date}}' => wp_date('Y-m-d H:i:s'),
            // Add other global placeholders
        ];

        // Add context-specific placeholders if available
        if (isset($context['post_id']) && $context['post_id'] > 0) {
            $post = get_post($context['post_id']);
            if ($post) {
                $replacements['{{post_title}}'] = $post->post_title;
                // Be cautious with {{post_content}} for very long posts
                $replacements['{{post_content}}'] = $post->post_content;
                $replacements['{{post_excerpt}}'] = $post->post_excerpt;
                // Get post keywords (tags, categories)
                $post_keywords = [];
                $tags = wp_get_post_tags($post->ID, ['fields' => 'names']);
                if (!empty($tags)) $post_keywords = array_merge($post_keywords, $tags);
                $categories = wp_get_post_categories($post->ID, ['fields' => 'names']);
                if (!empty($categories)) $post_keywords = array_merge($post_keywords, $categories);
                // TODO: Integrate with SEO plugins for focus keywords if available
                $replacements['{{post_keywords}}'] = implode(', ', array_unique($post_keywords));
            }
        }
        if (isset($context['user_comment'])) {
             $replacements['{{user_comment}}'] = $context['user_comment'];
        }
        if (isset($context['selected_text'])) {
             $replacements['{{selected_text}}'] = $context['selected_text'];
        }
         if (isset($context['page_url'])) {
             $replacements['{{page_url}}'] = $context['page_url'];
         }


        // Perform replacement
        $processed_prompt = str_replace(array_keys($replacements), array_values($replacements), $prompt);

        // Handle instructional shortcodes like [mfw_image], [mfw_table] etc.
        // These might need specific processing depending on the AI or context.
        // For now, they are primarily documented for the user.
        // If the AI is capable of interpreting them, they might be left as is.
        // If the plugin needs to process them *before* sending to AI, this is where that logic goes.
        // Example: [mfw_image prompt="..."] might trigger an image generation task *before* the main text generation.
        // This is advanced and likely requires breaking down the task into steps.

        return $processed_prompt;
    }

    // TODO: Add methods for other content generation tasks:
    // - generate_summary($post_id)
    // - generate_takeaways($post_id)
    // - generate_alt_text_for_attachment($attachment_id)
    // - generate_comment_for_post($post_id)
    // - generate_reply_to_comment($comment_id)
    // - generate_table_from_prompt($prompt)
    // - generate_list_from_prompt($prompt)
    // - generate_faq_from_content($post_id)
    // - etc.
}


/**
 * Scheduler Class
 * Manages WP Cron events for automated tasks.
 */
class MFW_AI_SUPER_Scheduler {
    private $plugin;

    /**
     * Constructor.
     *
     * @param Maziyar_Fetcher_Writer_AI_Super $plugin The main plugin instance.
     */
    public function __construct(Maziyar_Fetcher_Writer_AI_Super $plugin) {
        $this->plugin = $plugin;
    }

    /**
     * Schedule all necessary cron events on plugin activation.
     */
    public function schedule_events() {
        $this->schedule_content_updates();
        $this->schedule_comment_generation();
        $this->schedule_comment_answering();
        // Add scheduling for other recurring tasks like PDF generation batches, cleanup etc.
        // $this->schedule_pdf_generation_batches();
        // $this->schedule_cache_cleanup();
        // $this->schedule_log_pruning(); // DB logs are pruned during insertion, but a scheduled check is safer
    }

    /**
     * Unschedule all plugin cron events on deactivation.
     */
    public function unschedule_events() {
        wp_clear_scheduled_hook('mfw_ai_super_scheduled_content_update');
        wp_clear_scheduled_hook('mfw_ai_super_scheduled_comment_generation');
        wp_clear_scheduled_hook('mfw_ai_super_scheduled_comment_answering');
        // Clear other scheduled hooks
        // wp_clear_scheduled_hook('mfw_ai_super_scheduled_pdf_generation_batch');
        // wp_clear_scheduled_hook('mfw_ai_super_scheduled_cache_cleanup');
        // wp_clear_scheduled_hook('mfw_ai_super_scheduled_log_pruning');
    }

    /**
     * Schedule the content update cron event based on settings.
     */
    private function schedule_content_updates() {
        $interval = $this->plugin->get_option('auto_update_content_interval', 'disabled');
        $hook = 'mfw_ai_super_scheduled_content_update';

        // Clear existing event first
        if (wp_next_scheduled($hook)) {
            wp_clear_scheduled_hook($hook);
        }

        if ($interval !== 'disabled') {
            $schedules = wp_get_schedules();
            if (isset($schedules[$interval])) {
                wp_schedule_event(time(), $interval, $hook);
                $this->plugin->logger->log("Scheduled content update cron event with interval: " . $interval, "INFO");
            } else {
                 $this->plugin->logger->log("Attempted to schedule content update with invalid interval: " . $interval, "WARNING");
            }
        } else {
             $this->plugin->logger->log("Content update cron event disabled.", "INFO");
        }
    }

    /**
     * Schedule the comment generation cron event based on settings.
     */
    private function schedule_comment_generation() {
        $interval = $this->plugin->get_option('auto_comment_generation_interval', 'disabled');
        $hook = 'mfw_ai_super_scheduled_comment_generation';

        if (wp_next_scheduled($hook)) {
            wp_clear_scheduled_hook($hook);
        }

        if ($interval !== 'disabled') {
            $schedules = wp_get_schedules();
            if (isset($schedules[$interval])) {
                wp_schedule_event(time(), $interval, $hook);
                $this->plugin->logger->log("Scheduled comment generation cron event with interval: " . $interval, "INFO");
            } else {
                 $this->plugin->logger->log("Attempted to schedule comment generation with invalid interval: " . $interval, "WARNING");
            }
        } else {
             $this->plugin->logger->log("Comment generation cron event disabled.", "INFO");
        }
    }

    /**
     * Schedule the comment answering cron event based on settings.
     */
    private function schedule_comment_answering() {
        // Assuming comment answering uses the same interval setting as generation for simplicity
        $interval = $this->plugin->get_option('auto_comment_generation_interval', 'disabled');
        $hook = 'mfw_ai_super_scheduled_comment_answering';

        if (wp_next_scheduled($hook)) {
            wp_clear_scheduled_hook($hook);
        }

        if ($interval !== 'disabled') {
            $schedules = wp_get_schedules();
            if (isset($schedules[$interval])) {
                wp_schedule_event(time(), $interval, $hook);
                $this->plugin->logger->log("Scheduled comment answering cron event with interval: " . $interval, "INFO");
            } else {
                 $this->plugin->logger->log("Attempted to schedule comment answering with invalid interval: " . $interval, "WARNING");
            }
        } else {
             $this->plugin->logger->log("Comment answering cron event disabled.", "INFO");
        }
    }


    /**
     * Cron callback to run scheduled content updates.
     */
    public function run_scheduled_content_updates() {
        $this->plugin->logger->log("Running scheduled content updates.", "INFO");
        // TODO: Implement logic to find posts that need updating and trigger update tasks.
        // This might involve finding posts older than a certain age, or posts marked for update.
        // It should likely add tasks to the task queue manager rather than processing directly.
        $this->plugin->logger->log("Scheduled content update task completed (placeholder).", "INFO");
    }

    /**
     * Cron callback to run scheduled comment generation.
     */
    public function run_scheduled_comment_generation() {
        $this->plugin->logger->log("Running scheduled comment generation.", "INFO");
         // TODO: Implement logic to find posts that could use new comments and trigger comment generation tasks.
         // This might involve finding recent posts with few comments.
         // It should likely add tasks to the task queue manager.
         $this->plugin->logger->log("Scheduled comment generation task completed (placeholder).", "INFO");
    }

    /**
     * Cron callback to run scheduled comment answering.
     */
    public function run_scheduled_comment_answering() {
        $this->plugin->logger->log("Running scheduled comment answering.", "INFO");
         // TODO: Implement logic to find unanswered comments and trigger comment answering tasks.
         // It should likely add tasks to the task queue manager.
         $this->plugin->logger->log("Scheduled comment answering task completed (placeholder).", "INFO");
    }


    /**
     * Hook into option updates to reschedule cron events if intervals change.
     *
     * @param mixed $old_value The old option value.
     * @param mixed $value The new option value.
     * @param string $option_name The option name.
     */
    public function reschedule_events_on_option_change($old_value, $value, $option_name) {
        if ($option_name === 'mfw_ai_super_options') {
            $old_update_interval = $old_value['auto_update_content_interval'] ?? 'disabled';
            $new_update_interval = $value['auto_update_content_interval'] ?? 'disabled';
            if ($old_update_interval !== $new_update_interval) {
                $this->schedule_content_updates();
                $this->plugin->logger->log("Content update interval changed. Rescheduling cron.", "INFO");
            }

            $old_comment_interval = $old_value['auto_comment_generation_interval'] ?? 'disabled';
            $new_comment_interval = $value['auto_comment_generation_interval'] ?? 'disabled';
            if ($old_comment_interval !== $new_comment_interval) {
                $this->schedule_comment_generation();
                $this->schedule_comment_answering(); // Answering uses the same interval
                $this->plugin->logger->log("Comment generation/answering interval changed. Rescheduling cron.", "INFO");
            }

            // TODO: Add checks for other scheduled tasks' interval options
        }
    }

    // TODO: Add methods for scheduling/unscheduling specific tasks like PDF batches, cleanup
}


/**
 * Shortcode Handler Class
 * Registers and processes plugin shortcodes.
 */
class MFW_AI_SUPER_Shortcode_Handler {
    private $plugin;

    /**
     * Constructor.
     *
     * @param Maziyar_Fetcher_Writer_AI_Super $plugin The main plugin instance.
     */
    public function __construct(Maziyar_Fetcher_Writer_AI_Super $plugin) {
        $this->plugin = $plugin;
    }

    /**
     * Register plugin shortcodes.
     */
    public function register_shortcodes() {
        add_shortcode('mfw_map', [$this, 'render_map_shortcode']);
        add_shortcode('mfw_cta', [$this, 'render_cta_shortcode']);
        add_shortcode('mfw_contact_box', [$this, 'render_contact_box_shortcode']);
        // Add other shortcodes here
        // add_shortcode('mfw_ai_generated_content', [$this, 'render_ai_generated_content']); // Example for embedding AI content
        // add_shortcode('mfw_ai_summary', [$this, 'render_ai_summary']);
        // add_shortcode('mfw_ai_takeaways', [$this, 'render_ai_takeaways']);
    }

    /**
     * Render the [mfw_map] shortcode.
     * Displays a Google Map based on address. Requires Google Maps API key.
     *
     * @param array $atts Shortcode attributes.
     * @return string The HTML output for the map.
     */
    public function render_map_shortcode($atts) {
        $atts = shortcode_atts([
            'address' => '',
            'zoom' => $this->plugin->get_option('default_map_shortcode_zoom', 15),
            'width' => '100%',
            'height' => '400px',
            'id' => 'mfw-map-' . uniqid(),
        ], $atts, 'mfw_map');

        $address = sanitize_text_field($atts['address']);
        $zoom = absint($atts['zoom']);
        $width = esc_attr($atts['width']);
        $height = esc_attr($atts['height']);
        $map_id = esc_attr($atts['id']);
        $api_key = $this->plugin->get_option('google_maps_api_key');

        if (empty($api_key)) {
            // Log error or show message if API key is missing
            $this->plugin->logger->log("Google Maps API key is missing for [mfw_map] shortcode.", "WARNING");
            if (current_user_can('manage_options')) {
                 return '<p style="color: red;">' . esc_html__('MFW AI Super: Google Maps API key is not configured.', MFW_AI_SUPER_TEXT_DOMAIN) . '</p>';
            }
            return '';
        }

        if (empty($address)) {
             // Log error or show message if address is missing
             $this->plugin->logger->log("[mfw_map] shortcode used without an address.", "WARNING");
             if (current_user_can('manage_options')) {
                  return '<p style="color: red;">' . esc_html__('MFW AI Super: [mfw_map] shortcode requires an address attribute.', MFW_AI_SUPER_TEXT_DOMAIN) . '</p>';
             }
             return '';
        }

        // Enqueue Google Maps API script (only once)
        wp_enqueue_script('google-maps-api', 'https://maps.googleapis.com/maps/api/js?key=' . esc_attr($api_key) . '&libraries=geometry,places&callback=mfwInitMap', [], null, true);

        // Add inline script to initialize the map
        ob_start();
        ?>
        <div id="<?php echo $map_id; ?>" style="width: <?php echo $width; ?>; height: <?php echo $height; ?>;"></div>
        <script type="text/javascript">
            var mfwMaps = mfwMaps || {};
            mfwMaps['<?php echo $map_id; ?>'] = {
                address: '<?php echo esc_js($address); ?>',
                zoom: <?php echo absint($zoom); ?>
            };

            function mfwInitMap() {
                for (var mapId in mfwMaps) {
                    if (mfwMaps.hasOwnProperty(mapId)) {
                        var mapData = mfwMaps[mapId];
                        var geocoder = new google.maps.Geocoder();
                        geocoder.geocode({ 'address': mapData.address }, function(results, status) {
                            if (status === 'OK') {
                                var map = new google.maps.Map(document.getElementById(mapId), {
                                    zoom: mapData.zoom,
                                    center: results[0].geometry.location
                                });
                                new google.maps.Marker({
                                    map: map,
                                    position: results[0].geometry.location
                                });
                            } else {
                                console.error('Geocode was not successful for the following reason: ' + status);
                                document.getElementById(mapId).innerHTML = '<?php echo esc_js(__('Could not display map.', MFW_AI_SUPER_TEXT_DOMAIN)); ?>';
                            }
                        });
                    }
                }
            }

            // If the API script has already loaded and called the callback, run init manually
            if (typeof google !== 'undefined' && typeof google.maps !== 'undefined' && typeof mfwInitMap === 'function') {
                 // Check if the callback has already been executed by the API script load
                 if (typeof window.mfwMapApiLoaded === 'undefined') {
                     window.mfwMapApiLoaded = true; // Mark as loaded
                     mfwInitMap(); // Run the init function
                 }
            } else {
                 // Ensure the global callback exists if the API script hasn't loaded yet
                 window.mfwInitMap = mfwInitMap;
            }

        </script>
        <?php
        return ob_get_clean();
    }

    /**
     * Render the [mfw_cta] shortcode.
     * Displays a Call-to-Action button.
     *
     * @param array $atts Shortcode attributes.
     * @param string $content The content inside the shortcode (used as button text if 'text' attribute is missing).
     * @return string The HTML output for the CTA.
     */
    public function render_cta_shortcode($atts, $content = null) {
        $atts = shortcode_atts([
            'text' => $this->plugin->get_option('default_cta_text', 'Learn More!'),
            'link' => '#',
            'class' => 'button button-primary', // Default WordPress button classes
        ], $atts, 'mfw_cta');

        $text = !empty($atts['text']) ? sanitize_text_field($atts['text']) : (!empty($content) ? sanitize_text_field($content) : __('Learn More', MFW_AI_SUPER_TEXT_DOMAIN));
        $link = esc_url($atts['link']);
        $class = esc_attr($atts['class']);

        if (empty($link) || $link === '#') {
             // Log warning if link is missing or default
             $this->plugin->logger->log("[mfw_cta] shortcode used with default or empty link.", "DEBUG");
        }

        return sprintf('<a href="%s" class="%s">%s</a>', $link, $class, $text);
    }

    /**
     * Render the [mfw_contact_box] shortcode.
     * Displays the default contact form shortcode from settings.
     *
     * @param array $atts Shortcode attributes.
     * @return string The output of the contact form shortcode.
     */
    public function render_contact_box_shortcode($atts) {
        $default_shortcode = $this->plugin->get_option('default_contact_form_shortcode', '');

        if (empty($default_shortcode)) {
             $this->plugin->logger->log("[mfw_contact_box] shortcode used but default contact form shortcode is not set.", "WARNING");
             if (current_user_can('manage_options')) {
                  return '<p style="color: red;">' . esc_html__('MFW AI Super: Default contact form shortcode is not configured.', MFW_AI_SUPER_TEXT_DOMAIN) . '</p>';
             }
             return '';
        }

        // Do the shortcode to render the actual form
        return do_shortcode($default_shortcode);
    }

    // TODO: Implement render methods for other shortcodes if needed
    // Example: [mfw_ai_summary post_id="123"]
    // public function render_ai_summary($atts) {
    //     $atts = shortcode_atts(['post_id' => get_the_ID()], $atts, 'mfw_ai_summary');
    //     $post_id = absint($atts['post_id']);
    //     if (!$post_id) return '';
    //     // Fetch or generate the summary for the post
    //     $summary = $this->plugin->content_generator->get_post_summary($post_id); // Requires get_post_summary method
    //     return wp_kses_post($summary); // Sanitize output
    // }
}


/**
 * PDF Generator Class
 * Handles creating PDF files from post content.
 */
class MFW_AI_SUPER_PDF_Generator {
    private $plugin;
    private $tcpdf_loaded = false; // Flag to ensure TCPDF is included only once

    /**
     * Constructor.
     *
     * @param Maziyar_Fetcher_Writer_AI_Super $plugin The main plugin instance.
     */
    public function __construct(Maziyar_Fetcher_Writer_AI_Super $plugin) {
        $this->plugin = $plugin;
    }

    /**
     * Get a list of available TCPDF fonts.
     *
     * @return array Associative array of font names => display names.
     */
    public function get_tcpdf_fonts() {
         // TCPDF standard fonts. DejaVu Sans supports UTF-8.
         return [
             'helvetica' => 'Helvetica',
             'times' => 'Times New Roman',
             'courier' => 'Courier',
             'cid0jp' => 'Japanese (cid0jp)',
             'cid0kr' => 'Korean (cid0kr)',
             'cid0cs' => 'Chinese Simplified (cid0cs)',
             'cid0tc' => 'Chinese Traditional (cid0tc)',
             'dejavusans' => 'DejaVu Sans (UTF-8)', // Recommended for broad character support
             'dejavuserif' => 'DejaVu Serif (UTF-8)',
             'dejavumono' => 'DejaVu Mono (UTF-8)',
         ];
    }


    /**
     * Enqueue frontend scripts and styles for the PDF download button/prompt.
     */
    public function enqueue_frontend_scripts_and_styles() {
        // Only enqueue if PDF generation is enabled and on a single post/page that supports it
        if (!$this->plugin->get_option('pdf_generation_enabled')) {
            return;
        }

        $enabled_post_types = $this->plugin->get_option('pdf_post_types_enabled', []);
        if (!is_singular($enabled_post_types)) {
             return;
        }

        // Enqueue CSS for the button/prompt
        wp_enqueue_style(MFW_AI_SUPER_TEXT_DOMAIN . '-pdf-frontend', MFW_AI_SUPER_PLUGIN_URL . 'assets/css/pdf-frontend-style.css', [], MFW_AI_SUPER_VERSION);

        // Enqueue JS to handle the button click/prompt display
        wp_enqueue_script(MFW_AI_SUPER_TEXT_DOMAIN . '-pdf-frontend', MFW_AI_SUPER_PLUGIN_URL . 'assets/js/pdf-frontend-script.js', ['jquery'], MFW_AI_SUPER_VERSION, true);

        // Localize script with necessary data
        wp_localize_script(MFW_AI_SUPER_TEXT_DOMAIN . '-pdf-frontend', 'mfwPdf', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('mfw_ai_super_pdf_nonce'), // Need a specific nonce for PDF requests
            'post_id' => get_the_ID(),
            'prompt_text' => $this->plugin->get_option('pdf_download_prompt_text'),
            'button_text' => $this->plugin->get_option('pdf_download_button_text', __('Download PDF', MFW_AI_SUPER_TEXT_DOMAIN)),
            'download_url_base' => add_query_arg(['mfw_pdf_download' => 1, 'post_id' => get_the_ID()], home_url('/')), // Base URL for direct download request
        ]);

        // Add the PDF download button/prompt to the content (or a specific hook)
        add_filter('the_content', [$this, 'add_pdf_download_button']);
    }

    /**
     * Add the PDF download button/prompt HTML to the end of the content.
     *
     * @param string $content The post content.
     * @return string The content with the button/prompt added.
     */
    public function add_pdf_download_button($content) {
         // Check if we are on a single view and the post type is enabled
         $enabled_post_types = $this->plugin->get_option('pdf_post_types_enabled', []);
         if (!is_singular($enabled_post_types) || !in_the_loop()) {
              return $content;
         }

        $prompt_text = $this->plugin->get_option('pdf_download_prompt_text');
        $button_text = $this->plugin->get_option('pdf_download_button_text', __('Download PDF', MFW_AI_SUPER_TEXT_DOMAIN));
        $post_id = get_the_ID();
         $download_url = add_query_arg(['mfw_pdf_download' => 1, 'post_id' => $post_id], get_permalink($post_id)); // Use permalink for cleaner URL

        ob_start();
        ?>
        <div class="mfw-pdf-download-box">
            <?php if (!empty($prompt_text)): ?>
                <p class="mfw-pdf-prompt"><?php echo esc_html($prompt_text); ?></p>
            <?php endif; ?>
            <a href="<?php echo esc_url($download_url); ?>" class="mfw-pdf-download-button button"><?php echo esc_html($button_text); ?></a>
        </div>
        <?php
        $button_html = ob_get_clean();

        return $content . $button_html;
    }


    /**
     * Handle the PDF download request.
     * Triggered by the 'template_redirect' hook when 'mfw_pdf_download' query var is present.
     */
    public function handle_pdf_download_request() {
        if (isset($_GET['mfw_pdf_download']) && $_GET['mfw_pdf_download'] == 1 && isset($_GET['post_id'])) {
            $post_id = absint(wp_unslash($_GET['post_id']));

            // Basic security checks
            if (!$post_id || !get_post($post_id)) {
                wp_die(__('Invalid post ID for PDF download.', MFW_AI_SUPER_TEXT_DOMAIN));
            }

            // Check if PDF generation is enabled for this post type
            $post_type = get_post_type($post_id);
            $enabled_post_types = $this->plugin->get_option('pdf_post_types_enabled', []);
            if (!in_array($post_type, $enabled_post_types)) {
                 wp_die(__('PDF download is not enabled for this content type.', MFW_AI_SUPER_TEXT_DOMAIN));
            }

            // Optional: Add capability check if only certain users should download PDFs
            // if (!current_user_can('read_post', $post_id)) {
            //     wp_die(__('You do not have permission to download this PDF.', MFW_AI_SUPER_TEXT_DOMAIN));
            // }

            $this->plugin->logger->log("PDF download requested for post ID " . $post_id, "INFO");

            // Generate or retrieve the PDF
            $pdf_file_path = $this->generate_pdf_for_post($post_id);

            if (is_wp_error($pdf_file_path)) {
                 $this->plugin->logger->log("PDF generation failed for post ID " . $post_id . ": " . $pdf_file_path->get_error_message(), "ERROR");
                 wp_die(sprintf(__('Failed to generate PDF: %s', MFW_AI_SUPER_TEXT_DOMAIN), $pdf_file_path->get_error_message()));
            }

            // Serve the PDF file
            if (file_exists($pdf_file_path)) {
                $filename = sanitize_file_name(get_post_field('post_name', $post_id) . '.pdf');
                header('Content-type: application/pdf');
                header('Content-Disposition: attachment; filename="' . $filename . '"');
                header('Content-Length: ' . filesize($pdf_file_path));
                readfile($pdf_file_path);
                exit; // Stop further WordPress execution
            } else {
                 $this->plugin->logger->log("Generated PDF file not found at expected path: " . $pdf_file_path, "ERROR");
                 wp_die(__('Generated PDF file not found.', MFW_AI_SUPER_TEXT_DOMAIN));
            }
        }
    }


    /**
     * Generate a PDF for a specific post.
     * Uses TCPDF library. Needs to handle including TCPDF.
     *
     * @param int $post_id The ID of the post.
     * @return string|WP_Error The file path to the generated PDF on success, WP_Error on failure.
     */
    public function generate_pdf_for_post($post_id) {
        if (!$this->plugin->get_option('pdf_generation_enabled')) {
             return new WP_Error('mfw_ai_super_pdf_disabled', __('PDF generation is disabled in plugin settings.', MFW_AI_SUPER_TEXT_DOMAIN));
        }

        $post = get_post($post_id);
        if (!$post) {
             return new WP_Error('mfw_ai_super_invalid_post', __('Invalid post ID.', MFW_AI_SUPER_TEXT_DOMAIN));
        }

        // Check PDF cache first
        if ($this->plugin->get_option('pdf_cache_enabled')) {
            $cached_pdf_path = $this->get_cached_pdf_path($post_id);
            if ($cached_pdf_path && file_exists($cached_pdf_path)) {
                $this->plugin->logger->log("Serving PDF from cache for post ID " . $post_id, "INFO");
                // Update last accessed timestamp in cache table (optional)
                $this->update_cached_pdf_access_time($post_id);
                return $cached_pdf_path;
            }
        }

        // Include TCPDF library
        if (!$this->tcpdf_loaded) {
            // Assuming TCPDF is in a 'lib/tcpdf' directory within the plugin
            $tcpdf_path = MFW_AI_SUPER_PLUGIN_DIR . 'lib/tcpdf/tcpdf.php';
            if (!file_exists($tcpdf_path)) {
                $error_msg = sprintf(__('TCPDF library not found at: %s', MFW_AI_SUPER_TEXT_DOMAIN), $tcpdf_path);
                $this->plugin->logger->log($error_msg, 'ERROR');
                return new WP_Error('mfw_ai_super_tcpdf_missing', $error_msg);
            }
            require_once($tcpdf_path);
            $this->tcpdf_loaded = true;
        }

        // Get PDF options
        $font_family = $this->plugin->get_option('pdf_font_family', 'dejavusans');
        $font_size = $this->plugin->get_option('pdf_font_size', 10);
        $header_text_template = $this->plugin->get_option('pdf_header_text', '');
        $footer_text_template = $this->plugin->get_option('pdf_footer_text', '');
        $metadata_author = $this->plugin->get_option('pdf_metadata_author', get_bloginfo('name'));
        $custom_css = $this->plugin->get_option('pdf_custom_css', '');

        // Prepare HTML content for PDF
        // This involves getting the post content and potentially filtering it for PDF output
        $html_content = apply_filters('the_content', $post->post_content); // Apply content filters
        $html_content = $this->prepare_html_for_pdf($html_content); // Custom preparation (e.g., handle shortcodes, images)

        // Replace placeholders in header/footer
        $header_text = $this->plugin->content_generator->replace_placeholders($header_text_template, ['post_id' => $post_id]);
        $footer_text = $this->plugin->content_generator->replace_placeholders($footer_text_template, ['post_id' => $post_id]);


        // Create new TCPDF object
        // TCPDF(orientation, unit, format, unicode, encoding, diskcache, pdfa)
        $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false, false);

        // Set document information
        $pdf->SetCreator(get_bloginfo('name'));
        $pdf->SetAuthor($metadata_author);
        $pdf->SetTitle($post->post_title);
        $pdf->SetSubject($post->post_title);
        $pdf->SetKeywords(implode(', ', wp_get_post_tags($post_id, ['fields' => 'names']))); // Use post tags as keywords

        // Set default header data
        $pdf->SetHeaderData(PDF_HEADER_LOGO, PDF_HEADER_LOGO_WIDTH, $header_text, '', [0,0,0], [0,0,0]);
        // TCPDF doesn't have a direct footer text method, need to extend or use SetPrintFooter
        // For simplicity, we'll use SetPrintHeader/Footer and handle text inside.

        // Set header and footer fonts
        $pdf->setHeaderFont([PDF_FONT_NAME_MAIN, '', PDF_FONT_SIZE_MAIN]);
        $pdf->setFooterFont([PDF_FONT_NAME_DATA, '', PDF_FONT_SIZE_DATA]);

        // Set default monospaced font
        $pdf->SetDefaultMonospacedFont(PDF_FONT_MONOSPACED);

        // Set margins
        $pdf->SetMargins(PDF_MARGIN_LEFT, PDF_MARGIN_TOP, PDF_MARGIN_RIGHT);
        $pdf->SetHeaderMargin(PDF_MARGIN_HEADER);
        $pdf->SetFooterMargin(PDF_MARGIN_FOOTER);

        // Set auto page breaks
        $pdf->SetAutoPageBreak(TRUE, PDF_MARGIN_BOTTOM);

        // Set image scale factor
        $pdf->setImageScale(PDF_IMAGE_SCALE_RATIO);

        // Set default font
        $pdf->SetFont($font_family, '', $font_size);

        // Add a page
        $pdf->AddPage();

        // Set some content to print
        // TCPDF's writeHTML method is used to parse HTML and CSS
        $pdf_html = '<h1>' . esc_html($post->post_title) . '</h1>';
        $pdf_html .= '<div class="mfw-pdf-content">' . $html_content . '</div>'; // Wrap content for custom CSS targeting

        // Add custom CSS
        $pdf_css = '<style>' . $custom_css . '</style>';

        // Write the HTML content to the PDF
        $pdf->writeHTML($pdf_css . $pdf_html, true, false, true, false, '');

        // Add footer text manually if needed (or override TCPDF methods)
        // Example using SetPrintFooter (requires overriding Header() and Footer() methods in a custom class)
        // For now, rely on TCPDF's default header/footer or leave blank.

        // Close and output PDF document
        // Output('filename.pdf', 'I' for inline, 'D' for download, 'F' for file, 'S' for string)

        $upload_dir = wp_upload_dir();
        if (is_wp_error($upload_dir)) {
             return $upload_dir;
        }

        $pdf_filename = sanitize_file_name(get_post_field('post_name', $post_id) . '_' . date('YmdHis') . '.pdf');
        $pdf_filepath = $upload_dir['basedir'] . '/mfw-pdfs/' . $pdf_filename;
        $pdf_dir = dirname($pdf_filepath);

        // Create directory if it doesn't exist
        if (!wp_mkdir_p($pdf_dir)) {
             $error_msg = sprintf(__('Failed to create PDF upload directory: %s', MFW_AI_SUPER_TEXT_DOMAIN), $pdf_dir);
             $this->plugin->logger->log($error_msg, 'ERROR');
             return new WP_Error('mfw_ai_super_pdf_mkdir_failed', $error_msg);
        }

        // Save the PDF to the file system
        $pdf->Output($pdf_filepath, 'F');

        // Store in cache if enabled
        if ($this->plugin->get_option('pdf_cache_enabled')) {
            $this->store_cached_pdf_path($post_id, $pdf_filepath);
        }

        $this->plugin->logger->log("Generated PDF file saved to: " . $pdf_filepath, "INFO");

        return $pdf_filepath;
    }

    /**
     * Prepare HTML content for TCPDF.
     * This might involve cleaning up unsupported HTML, handling images, etc.
     *
     * @param string $html The raw HTML content.
     * @return string The cleaned HTML content.
     */
    private function prepare_html_for_pdf($html) {
        // TODO: Implement robust HTML cleaning and preparation for TCPDF.
        // This might include:
        // - Removing scripts, styles, and other non-content tags.
        // - Converting relative image URLs to absolute URLs.
        // - Ensuring images have width/height or max-width styles for scaling.
        // - Handling shortcodes that shouldn't be rendered in PDF.
        // - Fixing potentially malformed HTML.

        // Basic cleanup: remove script and style tags
        $html = preg_replace('/<script\b[^>]*>(.*?)<\/script>/is', '', $html);
        $html = preg_replace('/<style\b[^>]*>(.*?)<\/style>/is', '', $html);

        // Convert relative image URLs to absolute (basic example)
        $html = preg_replace_callback('/<img[^>]+src=["\']([^"\']+)["\'][^>]*>/i', function($matches) {
            $img_tag = $matches[0];
            $src = $matches[1];
            if (strpos($src, 'http') !== 0 && strpos($src, '//') !== 0) {
                // Assume relative URL, prepend site URL
                $absolute_src = home_url($src);
                $img_tag = str_replace($src, $absolute_src, $img_tag);
            }
            // Ensure images have max-width for responsiveness in PDF
            if (strpos($img_tag, 'style=') === false && strpos($img_tag, 'width=') === false) {
                 $img_tag = rtrim($img_tag, '>') . ' style="max-width: 100%; height: auto;">';
            } elseif (preg_match('/style=["\']([^"\']+)["\']/i', $img_tag, $style_matches)) {
                 $existing_style = $style_matches[1];
                 if (strpos($existing_style, 'max-width') === false) {
                     $img_tag = str_replace($existing_style, $existing_style . '; max-width: 100%; height: auto;', $img_tag);
                 }
            }

            return $img_tag;
        }, $html);


        // Ensure basic HTML structure for TCPDF
        $html = '<div>' . $html . '</div>'; // Wrap in a div

        return $html;
    }

    /**
     * Get the cached PDF file path for a post.
     * Checks the database table.
     *
     * @param int $post_id The ID of the post.
     * @return string|false The file path if cached and valid, false otherwise.
     */
    private function get_cached_pdf_path($post_id) {
        if (!$this->plugin->get_option('pdf_cache_enabled')) return false;

        global $wpdb;
        $table_name = $wpdb->prefix . 'mfw_ai_super_pdf_cache';

        $result = $wpdb->get_row($wpdb->prepare(
            "SELECT file_path, generated_at FROM $table_name WHERE post_id = %d",
            $post_id
        ), ARRAY_A);

        if ($result && !empty($result['file_path'])) {
            $cached_path = $result['file_path'];
            $generated_at = $result['generated_at'];

            // Check if the post has been updated since the PDF was cached
            $post_modified_date = get_post_modified_time('mysql', true, $post_id);
            if ($post_modified_date > $generated_at) {
                $this->plugin->logger->log("PDF cache expired for post ID " . $post_id . " (post updated).", "INFO");
                $this->delete_cached_pdf($post_id); // Clear the expired cache
                return false;
            }

            // Check if the file actually exists
            if (file_exists($cached_path)) {
                return $cached_path;
            } else {
                $this->plugin->logger->log("PDF cache entry found for post ID " . $post_id . " but file is missing. Clearing cache.", "WARNING");
                $this->delete_cached_pdf($post_id); // Clear invalid cache entry
                return false;
            }
        }

        return false;
    }

    /**
     * Store the generated PDF file path in the cache table.
     *
     * @param int $post_id The ID of the post.
     * @param string $file_path The file path to the PDF.
     */
    private function store_cached_pdf_path($post_id, $file_path) {
        if (!$this->plugin->get_option('pdf_cache_enabled')) return;

        global $wpdb;
        $table_name = $wpdb->prefix . 'mfw_ai_super_pdf_cache';

        // Delete any existing cache entry for this post
        $this->delete_cached_pdf($post_id);

        // Insert the new cache entry
        $wpdb->insert(
            $table_name,
            [
                'post_id' => absint($post_id),
                'file_path' => sanitize_text_field($file_path),
                'generated_at' => current_time('mysql'),
                'last_accessed_at' => current_time('mysql'),
            ],
            ['%d', '%s', '%s', '%s']
        );
        $this->plugin->logger->log("PDF cache stored for post ID " . $post_id, "DEBUG");
    }

    /**
     * Delete the cached PDF file and its database entry for a post.
     *
     * @param int $post_id The ID of the post.
     */
    private function delete_cached_pdf($post_id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'mfw_ai_super_pdf_cache';

        $cached_entry = $wpdb->get_row($wpdb->prepare(
            "SELECT file_path FROM $table_name WHERE post_id = %d",
            $post_id
        ), ARRAY_A);

        if ($cached_entry && !empty($cached_entry['file_path'])) {
            $file_path = $cached_entry['file_path'];
            // Attempt to delete the file
            if (file_exists($file_path)) {
                if (@unlink($file_path)) {
                    $this->plugin->logger->log("Deleted cached PDF file: " . $file_path, "DEBUG");
                } else {
                     $this->plugin->logger->log("Failed to delete cached PDF file: " . $file_path, "WARNING");
                }
            } else {
                 $this->plugin->logger->log("Cached PDF file not found for deletion: " . $file_path, "WARNING");
            }

            // Delete the database entry
            $wpdb->delete($table_name, ['post_id' => $post_id], ['%d']);
            $this->plugin->logger->log("Deleted PDF cache database entry for post ID " . $post_id, "DEBUG");
        }
    }

    /**
     * Update the last accessed timestamp for a cached PDF.
     *
     * @param int $post_id The ID of the post.
     */
    private function update_cached_pdf_access_time($post_id) {
        if (!$this->plugin->get_option('pdf_cache_enabled')) return;

        global $wpdb;
        $table_name = $wpdb->prefix . 'mfw_ai_super_pdf_cache';

        $wpdb->update(
            $table_name,
            ['last_accessed_at' => current_time('mysql')],
            ['post_id' => absint($post_id)],
            ['%s'],
            ['%d']
        );
        $this->plugin->logger->log("Updated PDF cache access time for post ID " . $post_id, "DEBUG");
    }

    // TODO: Add methods for scheduled PDF generation batches (e.g., for bulk generation)
    // public function run_scheduled_pdf_generation_batch() { ... }
}


/**
 * Live Chat Class
 * Handles the AI-powered live chat feature on the frontend.
 */
class MFW_AI_SUPER_Live_Chat {
    private $plugin;
    private $chat_history_table;

    /**
     * Constructor.
     *
     * @param Maziyar_Fetcher_Writer_AI_Super $plugin The main plugin instance.
     */
    public function __construct(Maziyar_Fetcher_Writer_AI_Super $plugin) {
        $this->plugin = $plugin;
        global $wpdb;
        $this->chat_history_table = $wpdb->prefix . 'mfw_ai_super_chat_history';
    }

    /**
     * Enqueue frontend scripts and styles for the chat widget.
     */
    public function enqueue_frontend_scripts_and_styles() {
        if (!$this->plugin->get_option('live_chat_enabled')) {
            return;
        }

        // Enqueue CSS for the chat widget based on selected style
        $chat_style = $this->plugin->get_option('live_chat_style', 'default');
        wp_enqueue_style(MFW_AI_SUPER_TEXT_DOMAIN . '-chat-frontend', MFW_AI_SUPER_PLUGIN_URL . 'assets/css/chat-frontend-style-' . esc_attr($chat_style) . '.css', [], MFW_AI_SUPER_VERSION);

        // Add custom CSS if enabled
        $custom_css = $this->plugin->get_option('live_chat_custom_css', '');
        if ($chat_style === 'custom' && !empty($custom_css)) {
             wp_add_inline_style(MFW_AI_SUPER_TEXT_DOMAIN . '-chat-frontend', $custom_css);
        }

        // Enqueue JS for chat functionality
        wp_enqueue_script(MFW_AI_SUPER_TEXT_DOMAIN . '-chat-frontend', MFW_AI_SUPER_PLUGIN_URL . 'assets/js/chat-frontend-script.js', ['jquery'], MFW_AI_SUPER_VERSION, true);

        // Localize script with necessary data
        wp_localize_script(MFW_AI_SUPER_TEXT_DOMAIN . '-chat-frontend', 'mfwChat', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'message_nonce' => wp_create_nonce('mfw_ai_super_live_chat_message_nonce'), // Nonce for sending messages
            'history_nonce' => wp_create_nonce('mfw_ai_super_chat_history_nonce'), // Nonce for getting history
            'welcome_message' => wp_kses_post($this->plugin->get_option('live_chat_welcome_message', __('Hello! How can I help you today?', MFW_AI_SUPER_TEXT_DOMAIN))),
            'contact_form_shortcode' => $this->plugin->get_option('live_chat_contact_form_shortcode', ''),
            'store_history' => (bool)$this->plugin->get_option('live_chat_store_history', true),
            'user_id' => get_current_user_id(), // 0 if not logged in
            'is_user_logged_in' => is_user_logged_in(),
            'session_id' => $this->get_chat_session_id(), // Get or create a session ID
            'text_loading' => __('Loading...', MFW_AI_SUPER_TEXT_DOMAIN),
            'text_error' => __('An error occurred. Please try again.', MFW_AI_SUPER_TEXT_DOMAIN),
            'text_contact_fallback' => __('I cannot answer that right now. Would you like to contact us?', MFW_AI_SUPER_TEXT_DOMAIN),
        ]);
    }

    /**
     * Render the HTML structure for the chat widget in the footer.
     */
    public function render_chat_widget_html() {
        if (!$this->plugin->get_option('live_chat_enabled')) {
            return;
        }
        // Add the chat widget HTML structure here. This will be hidden by default and controlled by JS.
        ?>
        <div id="mfw-ai-super-chat-widget" class="mfw-chat-widget">
            <div class="mfw-chat-header">
                <span class="mfw-chat-title"><?php _e('AI Assistant', MFW_AI_SUPER_TEXT_DOMAIN); ?></span>
                <button class="mfw-chat-close">&times;</button>
            </div>
            <div class="mfw-chat-body">
                <div class="mfw-chat-messages">
                    </div>
                <div class="mfw-chat-loading" style="display: none;"><?php _e('AI is typing...', MFW_AI_SUPER_TEXT_DOMAIN); ?></div>
                 <div class="mfw-chat-contact-fallback" style="display: none;">
                     <p><?php echo esc_html__('I cannot answer that right now. Would you like to contact us?', MFW_AI_SUPER_TEXT_DOMAIN); ?></p>
                     <div class="mfw-chat-contact-form">
                         <?php
                         // Render the contact form shortcode here if set
                         $contact_shortcode = $this->plugin->get_option('live_chat_contact_form_shortcode', '');
                         if (!empty($contact_shortcode)) {
                             echo do_shortcode($contact_shortcode);
                         } else {
                              echo '<p>' . esc_html__('Contact form shortcode not configured.', MFW_AI_SUPER_TEXT_DOMAIN) . '</p>';
                         }
                         ?>
                     </div>
                 </div>
            </div>
            <div class="mfw-chat-footer">
                <textarea class="mfw-chat-input" placeholder="<?php esc_attr_e('Type your message...', MFW_AI_SUPER_TEXT_DOMAIN); ?>"></textarea>
                <button class="mfw-chat-send"><?php _e('Send', MFW_AI_SUPER_TEXT_DOMAIN); ?></button>
            </div>
        </div>
        <button id="mfw-ai-super-chat-toggle" class="mfw-chat-toggle"><?php _e('Chat', MFW_AI_SUPER_TEXT_DOMAIN); ?></button>
        <?php
    }

    /**
     * Get or create a unique chat session ID for the current user/visitor.
     * Uses cookies for non-logged-in users and user ID for logged-in users.
     *
     * @return string The chat session ID.
     */
    private function get_chat_session_id() {
        $user_id = get_current_user_id();

        if ($user_id > 0) {
            // For logged-in users, use user ID as session ID
            return 'user_' . $user_id;
        } else {
            // For guests, use a cookie-based session ID
            $cookie_name = 'mfw_ai_super_chat_session';
            $session_id = $_COOKIE[$cookie_name] ?? '';

            if (empty($session_id)) {
                // Generate a new unique session ID
                $session_id = 'guest_' . uniqid() . '_' . time();
                // Set the cookie (valid for 30 days)
                setcookie($cookie_name, $session_id, time() + (DAY_IN_SECONDS * 30), COOKIEPATH, COOKIE_DOMAIN, is_ssl(), true);
            }
            return $session_id;
        }
    }

    /**
     * Handle AJAX requests for sending chat messages.
     */
    public function handle_ajax_message() {
        check_ajax_referer('mfw_ai_super_live_chat_message_nonce', 'nonce');

        if (!$this->plugin->get_option('live_chat_enabled')) {
             wp_send_json_error(['message' => __('Live chat is currently disabled.', MFW_AI_SUPER_TEXT_DOMAIN)]);
             return;
        }

        $user_message = isset($_POST['message']) ? sanitize_textarea_field(wp_unslash($_POST['message'])) : '';
        $session_id = isset($_POST['session_id']) ? sanitize_text_field(wp_unslash($_POST['session_id'])) : $this->get_chat_session_id(); // Use provided or generate
        $user_id = get_current_user_id();
        $user_ip = $this->get_user_ip();

        if (empty($user_message)) {
            wp_send_json_error(['message' => __('Message cannot be empty.', MFW_AI_SUPER_TEXT_DOMAIN)]);
            return;
        }

        // Store user message in history if enabled
        if ($this->plugin->get_option('live_chat_store_history', true)) {
             $this->store_chat_message($session_id, 'user', $user_message, $user_ip, $user_id);
        }

        // Get previous chat history for context (if storing history)
        $history = $this->plugin->get_option('live_chat_store_history', true) ? $this->get_chat_history($session_id, $this->plugin->get_option('live_chat_max_history_entries', 1000)) : [];

        // Prepare messages for the AI (OpenAI format or similar)
        $messages = [];
        // Add system prompt first
        $system_prompt_template = $this->plugin->get_option('live_chat_system_prompt', 'You are a helpful assistant.');
        $system_prompt = $this->plugin->content_generator->replace_placeholders($system_prompt_template);
        $messages[] = ['role' => 'system', 'content' => $system_prompt];

        // Add historical messages
        foreach ($history as $entry) {
             // Map stored roles ('user', 'ai', 'system') to AI provider roles ('user', 'assistant')
             $role = ($entry['sender'] === 'ai') ? 'assistant' : $entry['sender'];
             if ($role !== 'system') { // Avoid adding system messages from history again
                 $messages[] = ['role' => $role, 'content' => $entry['message']];
             }
        }

        // Add the current user message
        $messages[] = ['role' => 'user', 'content' => $user_message];

        // TODO: Implement knowledge base retrieval here
        // Based on the user's message and potentially history, query the knowledge base (enabled post types)
        // and add relevant snippets to the AI prompt as context.
        // This is a complex step involving embedding search or keyword matching.
        $knowledge_base_context = $this->retrieve_knowledge_base_context($user_message, $history);
        if (!empty($knowledge_base_context)) {
            // Add knowledge base context to the prompt (e.g., as a system message or part of user message)
            // Example: Add to the system prompt or as a separate message before the user's query
             $messages[0]['content'] .= "\n\nKnowledge Base Context:\n" . $knowledge_base_context;
        }


        // Use the preferred chat provider
        $provider_id = $this->plugin->get_option('live_chat_ai_provider');
         if (empty($provider_id)) {
              $error_msg = __('No preferred chat provider is set in settings.', MFW_AI_SUPER_TEXT_DOMAIN);
              $this->plugin->logger->log($error_msg, 'ERROR');
              wp_send_json_error(['message' => $error_msg]);
              return;
         }

        $ai_response = $this->plugin->ai_services->make_request('chat', $messages, [
            'feature_id' => 'live_chat',
            'model' => $this->plugin->get_option('live_chat_ai_model'), // Use chat model from settings
            // Pass other chat-specific args like temperature, max_tokens
        ]);

        if ($ai_response['success'] && !empty($ai_response['content'])) {
            $ai_message = $ai_response['content'];
            // Store AI message in history if enabled
            if ($this->plugin->get_option('live_chat_store_history', true)) {
                 $this->store_chat_message($session_id, 'ai', $ai_message, $user_ip, $user_id, $ai_response['model'], $ai_response['tokens_total'], $ai_response['cost']);
            }
            wp_send_json_success(['message' => wp_kses_post($ai_message)]); // Sanitize output
        } else {
            $error_msg = sprintf(__('AI response failed: %s', MFW_AI_SUPER_TEXT_DOMAIN), $ai_response['error'] ?? 'Unknown error.');
            $this->plugin->logger->log($error_msg, 'ERROR', ['session_id' => $session_id, 'user_message' => $user_message]);
            // Store system error message in history if enabled
            if ($this->plugin->get_option('live_chat_store_history', true)) {
                 $this->store_chat_message($session_id, 'system', $error_msg, $user_ip, $user_id);
            }

            // Optionally provide a fallback message or contact form shortcode
            $fallback_message = __('Sorry, I am unable to answer that question right now.', MFW_AI_SUPER_TEXT_DOMAIN);
            // If contact form shortcode is set, indicate that it should be shown
            $contact_shortcode = $this->plugin->get_option('live_chat_contact_form_shortcode', '');
            $details = !empty($contact_shortcode) ? ['show_contact_fallback' => true] : [];

            wp_send_json_error(['message' => $fallback_message, 'details' => $details]);
        }
    }

    /**
     * Retrieve relevant knowledge base context based on user input.
     *
     * @param string $user_query The user's current query.
     * @param array $chat_history The recent chat history.
     * @return string Relevant text snippets from the knowledge base.
     */
    private function retrieve_knowledge_base_context($user_query, $chat_history) {
        // TODO: Implement knowledge base retrieval logic.
        // This is a complex feature requiring:
        // 1. Identifying knowledge base post types from settings.
        // 2. Indexing content from these post types (e.g., using embeddings or keyword indexing).
        // 3. Performing a search/similarity query based on the user's message (and potentially history).
        // 4. Retrieving relevant text snippets from the found content.
        // 5. Formatting the snippets to be included in the AI prompt.

        $knowledge_base_post_types = $this->plugin->get_option('live_chat_knowledge_base_post_types', ['post', 'page']);
        if (empty($knowledge_base_post_types)) {
            $this->plugin->logger->log("Live chat knowledge base post types not configured.", "DEBUG");
            return ''; // No knowledge base configured
        }

        // Example placeholder logic (simple keyword search):
        // This is NOT recommended for production as it lacks nuance and context.
        // A proper solution would use embeddings and similarity search.
        $search_query = $user_query; // Use the current query for search
        // Could also analyze history to refine the search query

        $args = [
            'post_type' => $knowledge_base_post_types,
            's' => $search_query, // Standard WordPress search (very basic)
            'posts_per_page' => 3, // Limit results
            'post_status' => 'publish',
            'ignore_sticky_posts' => true,
        ];

        $query = new WP_Query($args);
        $context = '';

        if ($query->have_posts()) {
            $context .= "Relevant information from the website:\n\n";
            while ($query->have_posts()) {
                $query->the_post();
                $context .= "Title: " . get_the_title() . "\n";
                // Get a snippet of content (e.g., first few sentences or excerpt)
                $snippet = get_the_excerpt(); // Use excerpt if available
                if (empty($snippet)) {
                     $snippet = wp_strip_all_tags(get_the_content());
                     $snippet = mb_strimwidth($snippet, 0, 300, '...'); // Truncate long content
                }
                $context .= "Content Snippet: " . $snippet . "\n";
                $context .= "URL: " . get_permalink() . "\n\n";
            }
            wp_reset_postdata(); // Restore original post data
        } else {
            $this->plugin->logger->log("Knowledge base search for '" . $search_query . "' found no results.", "DEBUG");
        }

        return $context;
    }


    /**
     * Store a chat message in the database history table.
     *
     * @param string $session_id The chat session ID.
     * @param string $sender The sender ('user', 'ai', 'system').
     * @param string $message The message text.
     * @param string|null $user_ip The user's IP address.
     * @param int|null $user_id The user's ID (0 if guest).
     * @param string|null $ai_model The AI model used (if sender is 'ai').
     * @param int|null $tokens_used Tokens used for the AI response.
     * @param float|null $cost Estimated cost for the AI response.
     */
    private function store_chat_message($session_id, $sender, $message, $user_ip = null, $user_id = null, $ai_model = null, $tokens_used = null, $cost = null) {
        if (!$this->plugin->get_option('live_chat_store_history', true)) {
            return;
        }

        global $wpdb;
        $table_name = $this->chat_history_table;
        $max_history_entries = $this->plugin->get_option('live_chat_max_history_entries', 1000);

        $wpdb->insert(
            $table_name,
            [
                'session_id' => sanitize_text_field($session_id),
                'timestamp' => current_time('mysql'),
                'sender' => sanitize_text_field($sender),
                'message' => sanitize_textarea_field($message),
                'ai_provider' => ($sender === 'ai') ? sanitize_text_field($this->plugin->get_option('live_chat_ai_provider')) : null,
                'ai_model' => ($sender === 'ai') ? sanitize_text_field($ai_model) : null,
                'user_ip' => sanitize_text_field($user_ip),
                'user_id' => absint($user_id),
                // Add columns for tokens, cost if needed in history table (currently in API logs)
            ],
            ['%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d']
        );

        // Prune old entries for this session if exceeding max (or prune globally)
        // Global pruning is simpler for now
        $count = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
        if ($count > $max_history_entries) {
             // Delete the oldest entries globally
             $limit_to_delete = $count - $max_history_entries;
             $wpdb->query($wpdb->prepare("DELETE FROM $table_name ORDER BY timestamp ASC LIMIT %d", $limit_to_delete));
             $this->plugin->logger->log("Pruned old chat history entries.", "INFO");
        }
    }

    /**
     * Retrieve chat history for a given session ID.
     *
     * @param string $session_id The chat session ID.
     * @param int $limit Maximum number of messages to retrieve.
     * @return array An array of chat message entries.
     */
    private function get_chat_history($session_id, $limit = 1000) {
        if (!$this->plugin->get_option('live_chat_store_history', true)) {
            return [];
        }

        global $wpdb;
        $table_name = $this->chat_history_table;

        // Retrieve messages for the session, ordered by timestamp
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT session_id, timestamp, sender, message, user_id
             FROM $table_name
             WHERE session_id = %s
             ORDER BY timestamp ASC
             LIMIT %d",
            sanitize_text_field($session_id),
            absint($limit)
        ), ARRAY_A);

        return $results ?: [];
    }

    /**
     * AJAX callback to retrieve chat history.
     */
    public function ajax_get_chat_history() {
        check_ajax_referer('mfw_ai_super_chat_history_nonce', 'nonce');

        if (!$this->plugin->get_option('live_chat_enabled') || !$this->plugin->get_option('live_chat_store_history', true)) {
             wp_send_json_error(['message' => __('Chat history is not enabled.', MFW_AI_SUPER_TEXT_DOMAIN)]);
             return;
        }

        $session_id = isset($_POST['session_id']) ? sanitize_text_field(wp_unslash($_POST['session_id'])) : $this->get_chat_session_id();
        $limit = $this->plugin->get_option('live_chat_max_history_entries', 1000); // Use the max history setting

        $history = $this->get_chat_history($session_id, $limit);

        // Format history for frontend display (e.g., add user display name)
        $formatted_history = [];
        foreach ($history as $entry) {
            $formatted_entry = [
                'sender' => $entry['sender'],
                'message' => wp_kses_post($entry['message']), // Sanitize message for display
                'timestamp' => human_time_diff(strtotime($entry['timestamp']), current_time('timestamp')) . ' ago', // Or format as needed
                'user_display' => ($entry['user_id'] > 0) ? get_userdata($entry['user_id'])->display_name : __('Guest', MFW_AI_SUPER_TEXT_DOMAIN),
            ];
            $formatted_history[] = $formatted_entry;
        }

        wp_send_json_success(['history' => $formatted_history]);
    }

    /**
     * Render the chat history admin page.
     */
    public function render_chat_history_page() {
        // Capability check is done when adding the submenu page
        if (!current_user_can($this->plugin->settings->get_capability_for_feature('view_chat_history'))) {
            wp_die(__('You do not have sufficient permissions to access this page.', MFW_AI_SUPER_TEXT_DOMAIN));
        }
        ?>
        <div class="wrap mfw-ai-super-admin-page">
            <h1><?php _e('MFW AI Super - Chat History', MFW_AI_SUPER_TEXT_DOMAIN); ?></h1>
            <?php if (!$this->plugin->get_option('live_chat_enabled') || !$this->plugin->get_option('live_chat_store_history', true)): ?>
                <p><?php _e('Live chat or chat history storage is disabled in settings.', MFW_AI_SUPER_TEXT_DOMAIN); ?></p>
            <?php else: ?>
                <p><?php _e('Review recent chat conversations.', MFW_AI_SUPER_TEXT_DOMAIN); ?></p>
                <div id="mfw-chat-history-browser">
                    <h2><?php _e('Recent Sessions', MFW_AI_SUPER_TEXT_DOMAIN); ?></h2>
                    <ul id="mfw-chat-session-list">
                        <?php
                        // Fetch unique session IDs
                        global $wpdb;
                        $sessions = $wpdb->get_col("SELECT DISTINCT session_id FROM {$this->chat_history_table} ORDER BY timestamp DESC LIMIT 50"); // Limit sessions

                        if ($sessions) {
                            foreach ($sessions as $session_id) {
                                // Get the last message timestamp and sender for display
                                $last_message = $wpdb->get_row($wpdb->prepare(
                                    "SELECT timestamp, sender, message FROM {$this->chat_history_table} WHERE session_id = %s ORDER BY timestamp DESC LIMIT 1",
                                    $session_id
                                ), ARRAY_A);
                                $display_id = esc_html(substr($session_id, 0, 8) . '...'); // Truncate ID
                                $last_time = $last_message ? human_time_diff(strtotime($last_message['timestamp']), current_time('timestamp')) . ' ago' : '';
                                $last_sender = $last_message ? esc_html($last_message['sender']) : '';
                                $last_snippet = $last_message ? esc_html(mb_strimwidth($last_message['message'], 0, 50, '...')) : '';

                                echo '<li data-session-id="' . esc_attr($session_id) . '">';
                                echo '<strong>' . $display_id . '</strong> (' . $last_time . ') - ' . $last_sender . ': ' . $last_snippet;
                                echo '</li>';
                            }
                        } else {
                            echo '<li>' . esc_html__('No chat sessions found.', MFW_AI_SUPER_TEXT_DOMAIN) . '</li>';
                        }
                        ?>
                    </ul>

                    <div id="mfw-chat-session-messages" style="display: none;">
                        <h3><?php _e('Messages for Session:', MFW_AI_SUPER_TEXT_DOMAIN); ?> <span id="current-session-id"></span></h3>
                        <button id="mfw-back-to-sessions" class="button button-secondary"><?php _e('Back to Sessions', MFW_AI_SUPER_TEXT_DOMAIN); ?></button>
                        <div class="mfw-chat-messages-display">
                            </div>
                    </div>
                </div>

                <script type="text/javascript">
                    jQuery(document).ready(function($) {
                        var chatHistoryNonce = '<?php echo esc_js(wp_create_nonce('mfw_ai_super_chat_history_nonce')); ?>';

                        $('#mfw-chat-session-list li').on('click', function() {
                            var sessionId = $(this).data('session-id');
                            $('#current-session-id').text(sessionId);
                            $('#mfw-chat-session-list').hide();
                            $('#mfw-chat-session-messages').show();
                            loadSessionMessages(sessionId);
                        });

                        $('#mfw-back-to-sessions').on('click', function() {
                            $('#mfw-chat-session-messages').hide();
                            $('.mfw-chat-messages-display').empty(); // Clear messages
                            $('#mfw-chat-session-list').show();
                        });

                        function loadSessionMessages(sessionId) {
                            $('.mfw-chat-messages-display').html('<p><?php echo esc_js(__('Loading messages...', MFW_AI_SUPER_TEXT_DOMAIN)); ?></p>');
                            $.ajax({
                                url: ajaxurl,
                                type: 'POST',
                                data: {
                                    action: 'mfw_ai_super_get_chat_history',
                                    nonce: chatHistoryNonce,
                                    session_id: sessionId
                                },
                                success: function(response) {
                                    $('.mfw-chat-messages-display').empty();
                                    if (response.success && response.data.history.length > 0) {
                                        response.data.history.forEach(function(msg) {
                                            var senderClass = 'mfw-chat-' + msg.sender;
                                            $('.mfw-chat-messages-display').append(
                                                '<div class="mfw-chat-message ' + senderClass + '">' +
                                                    '<span class="mfw-chat-sender">' + escHtml(msg.user_display) + ': </span>' +
                                                    '<span class="mfw-chat-text">' + msg.message + '</span>' + // Message is already sanitized by wp_kses_post
                                                    '<span class="mfw-chat-timestamp">' + escHtml(msg.timestamp) + '</span>' +
                                                '</div>'
                                            );
                                        });
                                    } else {
                                        $('.mfw-chat-messages-display').html('<p><?php echo esc_js(__('No messages found for this session.', MFW_AI_SUPER_TEXT_DOMAIN)); ?></p>');
                                    }
                                },
                                error: function(xhr, status, error) {
                                    $('.mfw-chat-messages-display').html('<p><?php echo esc_js(__('Error loading messages:', MFW_AI_SUPER_TEXT_DOMAIN)); ?> ' + escHtml(error) + '</p>');
                                }
                            });
                        }

                        // Basic HTML escaping function for display
                        function escHtml(str) {
                            var div = document.createElement('div');
                            div.appendChild(document.createTextNode(str));
                            return div.innerHTML;
                        }
                    });
                </script>
                <style>
                    #mfw-chat-history-browser { margin-top: 20px; }
                    #mfw-chat-session-list li { cursor: pointer; padding: 8px; border-bottom: 1px solid #eee; }
                    #mfw-chat-session-list li:hover { background-color: #f5f5f5; }
                    .mfw-chat-messages-display { border: 1px solid #ddd; padding: 10px; max-height: 500px; overflow-y: auto; background-color: #fff; }
                    .mfw-chat-message { margin-bottom: 10px; padding: 8px; border-radius: 5px; }
                    .mfw-chat-user { background-color: #e1ffc7; text-align: right; }
                    .mfw-chat-ai { background-color: #f1f0f0; text-align: left; }
                     .mfw-chat-system { background-color: #fff3cd; color: #856404; font-style: italic; text-align: center; }
                    .mfw-chat-sender { font-weight: bold; }
                    .mfw-chat-timestamp { font-size: 0.8em; color: #888; margin-left: 10px; }
                     .mfw-chat-user .mfw-chat-timestamp { margin-right: 10px; margin-left: 0; }
                </style>

            <?php endif; ?>
        </div>
        <?php
    }


    /**
     * Get the user's IP address.
     *
     * @return string|null The user's IP address, or null if not available.
     */
    private function get_user_ip() {
        $ip = null;
        if (isset($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = sanitize_text_field(wp_unslash($_SERVER['HTTP_CLIENT_IP']));
        } elseif (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip = sanitize_text_field(wp_unslash($_SERVER['HTTP_X_FORWARDED_FOR']));
        } elseif (isset($_SERVER['REMOTE_ADDR'])) {
            $ip = sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR']));
        }
        return $ip;
    }

    // TODO: Add methods for chat history pruning (can be done during insertion or via scheduled task)
}


/**
 * Dashboard Widget Class
 * Adds a custom widget to the WordPress admin dashboard.
 */
class MFW_AI_SUPER_Dashboard_Widget {
    private $plugin;
    private $widget_id = 'mfw_ai_super_dashboard_widget';

    /**
     * Constructor.
     *
     * @param Maziyar_Fetcher_Writer_AI_Super $plugin The main plugin instance.
     */
    public function __construct(Maziyar_Fetcher_Writer_AI_Super $plugin) {
        $this->plugin = $plugin;
    }

    /**
     * Add the dashboard widget.
     */
    public function add_dashboard_widget() {
        // Check if the user has capability to view dashboard widgets and relevant plugin data
        if (!current_user_can($this->plugin->settings->get_capability_for_feature('view_system_logs'))) { // Use log viewing capability as a proxy
            return;
        }

        wp_add_dashboard_widget(
            $this->widget_id,
            __('MFW AI Super Overview', MFW_AI_SUPER_TEXT_DOMAIN),
            [$this, 'render_dashboard_widget']
        );
    }

    /**
     * Render the content of the dashboard widget.
     */
    public function render_dashboard_widget() {
        // Display some basic stats or links
        ?>
        <div class="mfw-dashboard-widget">
            <p><strong><?php _e('Plugin Version:', MFW_AI_SUPER_TEXT_DOMAIN); ?></strong> <?php echo esc_html(MFW_AI_SUPER_VERSION); ?></p>
            <p><strong><?php _e('Status:', MFW_AI_SUPER_TEXT_DOMAIN); ?></strong> <?php _e('Active', MFW_AI_SUPER_TEXT_DOMAIN); ?></p>

            <h3><?php _e('Quick Links:', MFW_AI_SUPER_TEXT_DOMAIN); ?></h3>
            <ul>
                <li><a href="<?php echo esc_url(admin_url('admin.php?page=' . MFW_AI_SUPER_SETTINGS_SLUG)); ?>"><?php _e('Settings', MFW_AI_SUPER_TEXT_DOMAIN); ?></a></li>
                <?php if ($this->plugin->get_option('ai_assistant_enabled') && current_user_can($this->plugin->settings->get_capability_for_feature('use_ai_assistant'))): ?>
                     <li><a href="<?php echo esc_url(admin_url('admin.php?page=' . MFW_AI_SUPER_SETTINGS_SLUG . '-assistant')); ?>"><?php _e('AI Assistant', MFW_AI_SUPER_TEXT_DOMAIN); ?></a></li>
                <?php endif; ?>
                 <?php if (current_user_can($this->plugin->settings->get_capability_for_feature('access_content_tools'))): ?>
                     <li><a href="<?php echo esc_url(admin_url('admin.php?page=' . MFW_AI_SUPER_SETTINGS_SLUG . '-content-tools')); ?>"><?php _e('Content Tools', MFW_AI_SUPER_TEXT_DOMAIN); ?></a></li>
                 <?php endif; ?>
                 <?php if (current_user_can($this->plugin->settings->get_capability_for_feature('gaw_view_reports'))): ?>
                     <li><a href="<?php echo esc_url(admin_url('admin.php?page=' . MFW_AI_SUPER_SETTINGS_SLUG . '-gaw-reports')); ?>"><?php _e('GAW Reports', MFW_AI_SUPER_TEXT_DOMAIN); ?></a></li>
                 <?php endif; ?>
                 <?php if ($this->plugin->get_option('live_chat_enabled') && $this->plugin->get_option('live_chat_store_history') && current_user_can($this->plugin->settings->get_capability_for_feature('view_chat_history'))): ?>
                     <li><a href="<?php echo esc_url(admin_url('admin.php?page=' . MFW_AI_SUPER_SETTINGS_SLUG . '-chat-history')); ?>"><?php _e('Chat History', MFW_AI_SUPER_TEXT_DOMAIN); ?></a></li>
                 <?php endif; ?>
                  <?php if (current_user_can($this->plugin->settings->get_capability_for_feature('view_task_queue'))): ?>
                     <li><a href="<?php echo esc_url(admin_url('admin.php?page=' . MFW_AI_SUPER_SETTINGS_SLUG . '-task-queue')); ?>"><?php _e('Task Queue', MFW_AI_SUPER_TEXT_DOMAIN); ?></a></li>
                 <?php endif; ?>
                  <?php if (current_user_can($this->plugin->settings->get_capability_for_feature('view_system_logs'))): ?>
                     <li><a href="<?php echo esc_url(admin_url('admin.php?page=' . MFW_AI_SUPER_SETTINGS_SLUG . '-logs')); ?>"><?php _e('System & API Logs', MFW_AI_SUPER_TEXT_DOMAIN); ?></a></li>
                 <?php endif; ?>
            </ul>

            <h3><?php _e('Recent Activity', MFW_AI_SUPER_TEXT_DOMAIN); ?></h3>
            <div id="mfw-dashboard-recent-activity">
                <p><?php _e('Loading recent activity...', MFW_AI_SUPER_TEXT_DOMAIN); ?></p>
                </div>

            <h3><?php _e('API Usage Summary (Last 7 Days)', MFW_AI_SUPER_TEXT_DOMAIN); ?></h3>
             <div id="mfw-dashboard-api-usage-chart-container">
                 <canvas id="mfw-dashboard-api-usage-chart"></canvas>
                 <p id="mfw-dashboard-api-usage-status"><?php _e('Loading API usage data...', MFW_AI_SUPER_TEXT_DOMAIN); ?></p>
             </div>


            <script type="text/javascript">
                jQuery(document).ready(function($) {
                    var dashboardNonce = '<?php echo esc_js(wp_create_nonce('mfw_ai_super_dashboard_widget_nonce')); ?>';

                    // Load recent activity
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'mfw_ai_super_dashboard_widget_data',
                            nonce: dashboardNonce,
                            data_type: 'recent_activity'
                        },
                        success: function(response) {
                            $('#mfw-dashboard-recent-activity').empty();
                            if (response.success && response.data.activity.length > 0) {
                                var activityList = '<ul>';
                                response.data.activity.forEach(function(item) {
                                    activityList += '<li><strong>[' + escHtml(item.level) + ']</strong> ' + escHtml(item.timestamp) + ': ' + escHtml(item.message) + '</li>';
                                });
                                activityList += '</ul>';
                                $('#mfw-dashboard-recent-activity').html(activityList);
                            } else {
                                $('#mfw-dashboard-recent-activity').html('<p><?php echo esc_js(__('No recent activity logs.', MFW_AI_SUPER_TEXT_DOMAIN)); ?></p>');
                            }
                        },
                        error: function(xhr, status, error) {
                            $('#mfw-dashboard-recent-activity').html('<p><?php echo esc_js(__('Error loading recent activity:', MFW_AI_SUPER_TEXT_DOMAIN)); ?> ' + escHtml(error) + '</p>');
                        }
                    });

                     // Load API Usage Data and render chart
                     $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'mfw_ai_super_dashboard_widget_data',
                            nonce: dashboardNonce,
                            data_type: 'api_usage_summary'
                        },
                        success: function(response) {
                            $('#mfw-dashboard-api-usage-status').hide();
                            if (response.success && response.data.usage_data) {
                                renderApiUsageChart(response.data.usage_data);
                            } else {
                                $('#mfw-dashboard-api-usage-chart-container').html('<p><?php echo esc_js(__('Could not load API usage data:', MFW_AI_SUPER_TEXT_DOMAIN)); ?> ' + escHtml(response.data.error || 'Unknown error') + '</p>');
                            }
                        },
                        error: function(xhr, status, error) {
                            $('#mfw-dashboard-api-usage-chart-container').html('<p><?php echo esc_js(__('Error loading API usage data:', MFW_AI_SUPER_TEXT_DOMAIN)); ?> ' + escHtml(error) + '</p>');
                        }
                    });


                    // Function to render the API Usage Chart
                    function renderApiUsageChart(usageData) {
                         var ctx = document.getElementById('mfw-dashboard-api-usage-chart').getContext('2d');
                         var chartLabels = usageData.dates;
                         var chartDatasets = [];

                         // Create a dataset for each provider
                         for (var providerId in usageData.providers) {
                             if (usageData.providers.hasOwnProperty(providerId)) {
                                 chartDatasets.push({
                                     label: usageData.providers[providerId].name,
                                     data: usageData.providers[providerId].costs,
                                     borderColor: usageData.providers[providerId].color || getRandomColor(), // Use defined color or random
                                     fill: false,
                                     tension: 0.1
                                 });
                             }
                         }

                         new Chart(ctx, {
                             type: 'line',
                             data: {
                                 labels: chartLabels,
                                 datasets: chartDatasets
                             },
                             options: {
                                 responsive: true,
                                 maintainAspectRatio: false, // Allow chart to fit container
                                 scales: {
                                     y: {
                                         beginAtZero: true,
                                         title: {
                                             display: true,
                                             text: '<?php echo esc_js(__('Estimated Cost ($)', MFW_AI_SUPER_TEXT_DOMAIN)); ?>'
                                         }
                                     },
                                     x: {
                                         title: {
                                             display: true,
                                             text: '<?php echo esc_js(__('Date', MFW_AI_SUPER_TEXT_DOMAIN)); ?>'
                                         }
                                     }
                                 },
                                 plugins: {
                                     legend: {
                                         display: chartDatasets.length > 1 // Only show legend if more than one provider
                                     },
                                     title: {
                                         display: true,
                                         text: '<?php echo esc_js(__('Estimated API Cost by Provider', MFW_AI_SUPER_TEXT_DOMAIN)); ?>'
                                     }
                                 }
                             }
                         });
                    }

                     // Helper to generate random color if needed
                     function getRandomColor() {
                         var letters = '0123456789ABCDEF';
                         var color = '#';
                         for (var i = 0; i < 6; i++) {
                             color += letters[Math.floor(Math.random() * 16)];
                         }
                         return color;
                     }


                    // Basic HTML escaping function for display
                    function escHtml(str) {
                        var div = document.createElement('div');
                        div.appendChild(document.createTextNode(str));
                        return div.innerHTML;
                    }
                });
            </script>
            <style>
                .mfw-dashboard-widget ul { margin-left: 20px; list-style: disc; }
                .mfw-dashboard-widget h3 { margin-top: 1.5em; margin-bottom: 0.5em; }
                 #mfw-dashboard-api-usage-chart-container { position: relative; height: 250px; width: 100%; } /* Set height for chart */
            </style>
        </div>
        <?php
    }

    /**
     * AJAX callback to provide data for the dashboard widget.
     */
    public function ajax_get_widget_data() {
        check_ajax_referer('mfw_ai_super_dashboard_widget_nonce', 'nonce');

        // Check user capability
        if (!current_user_can($this->plugin->settings->get_capability_for_feature('view_system_logs'))) {
             wp_send_json_error(['message' => __('Permission denied to access dashboard widget data.', MFW_AI_SUPER_TEXT_DOMAIN)]);
             return;
        }

        $data_type = isset($_POST['data_type']) ? sanitize_text_field(wp_unslash($_POST['data_type'])) : null;

        switch ($data_type) {
            case 'recent_activity':
                // Fetch recent system logs
                $logs = $this->plugin->logger->get_logs_from_db(10, 0); // Get latest 10 system logs
                wp_send_json_success(['activity' => $logs]);
                break;

            case 'api_usage_summary':
                 // Fetch API usage data for the last 7 days
                 $usage_data = $this->get_api_usage_summary(7); // Get data for last 7 days
                 wp_send_json_success(['usage_data' => $usage_data]);
                 break;

            default:
                wp_send_json_error(['message' => sprintf(__('Unknown data type requested: %s', MFW_AI_SUPER_TEXT_DOMAIN), esc_html($data_type))]);
                break;
        }
    }

    /**
     * Get API usage summary data for a given number of days.
     *
     * @param int $days The number of past days to include.
     * @return array Structured array with dates and provider usage data.
     */
    private function get_api_usage_summary($days = 7) {
        if (!$this->plugin->get_option('log_to_db_enabled', true)) {
             return ['error' => __('API logging is disabled.', MFW_AI_SUPER_TEXT_DOMAIN)];
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'mfw_ai_super_api_logs';
        $system_log_type = $this->plugin->logger->system_log_type;

        // Calculate the start date
        $start_date = date('Y-m-d 00:00:00', strtotime("-{$days} days"));

        // Get total cost per provider per day
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT provider_id, DATE(timestamp) as log_date, SUM(cost) as daily_cost
             FROM $table_name
             WHERE provider_id != %s AND timestamp >= %s
             GROUP BY provider_id, log_date
             ORDER BY log_date ASC, provider_id ASC",
            $system_log_type,
            $start_date
        ), ARRAY_A);

        // Structure the data for Chart.js
        $chart_data = [
            'dates' => [], // Array of date strings (e.g., '2024-07-01')
            'providers' => [], // Associative array: provider_id => { name: '...', color: '...', costs: [cost_day1, cost_day2, ...] }
        ];

        // Generate the list of dates for the last $days
        for ($i = $days - 1; $i >= 0; $i--) {
            $chart_data['dates'][] = date('Y-m-d', strtotime("-{$i} days"));
        }

        // Initialize provider data structure
        $all_providers = $this->plugin->ai_services->get_all_provider_configs();
        foreach ($all_providers as $provider_id => $config) {
             $chart_data['providers'][$provider_id] = [
                 'name' => $config['name'],
                 'color' => $this->get_provider_color($provider_id), // Get a consistent color
                 'costs' => array_fill(0, $days, 0.0), // Initialize costs for each day to 0
             ];
        }

        // Populate costs from query results
        foreach ($results as $row) {
            $provider_id = $row['provider_id'];
            $log_date = $row['log_date'];
            $daily_cost = (float)$row['daily_cost'];

            // Find the index of the date in the dates array
            $date_index = array_search($log_date, $chart_data['dates']);

            if ($date_index !== false && isset($chart_data['providers'][$provider_id])) {
                $chart_data['providers'][$provider_id]['costs'][$date_index] = $daily_cost;
            }
        }

        // Remove providers with zero total cost over the period to keep the chart clean
        foreach ($chart_data['providers'] as $provider_id => $provider_data) {
            if (array_sum($provider_data['costs']) === 0.0) {
                unset($chart_data['providers'][$provider_id]);
            }
        }


        return $chart_data;
    }

    /**
     * Get a consistent color for a provider for charting.
     *
     * @param string $provider_id The provider ID.
     * @return string A hex color code.
     */
    private function get_provider_color($provider_id) {
        // Define colors for known providers
        $colors = [
            'openai' => '#412991', // OpenAI purple
            'azure_openai' => '#0078D4', // Azure blue
            'google_gemini' => '#4285F4', // Google blue
            'anthropic' => '#E5492D', // Anthropic red/orange
            'ollama' => '#55acee', // Light blue (example)
            'ibm_watson_nlu' => '#171717', // IBM dark
            'aws_polly' => '#FF9900', // AWS orange
            'azure_tts' => '#0078D4', // Azure blue
            'azure_vision' => '#0078D4', // Azure blue
            'xai_grok' => '#000000', // Black (example)
            // Add colors for other providers
        ];
        // Return defined color or a default/random one
        return $colors[$provider_id] ?? '#cccccc'; // Default grey
    }
}


/**
 * Task Queue Manager Class
 * Manages background tasks using WP Cron or Action Scheduler.
 */
class MFW_AI_SUPER_Task_Queue_Manager {
    private $plugin;
    private $task_queue_table;
    private $runner_hook = 'mfw_ai_super_run_task_queue';

    /**
     * Constructor.
     *
     * @param Maziyar_Fetcher_Writer_AI_Super $plugin The main plugin instance.
     */
    public function __construct(Maziyar_Fetcher_Writer_AI_Super $plugin) {
        $this->plugin = $plugin;
        global $wpdb;
        $this->task_queue_table = $wpdb->prefix . 'mfw_ai_super_task_queue';
    }

    /**
     * Initialize the task queue system.
     * Registers the cron/action scheduler hook.
     */
    public function init() {
        // Register the hook that will run tasks
        add_action($this->runner_hook, [$this, 'run_tasks']);

        // Schedule the runner if needed (handled during activation/option change)
    }

    /**
     * Schedule the task queue runner using WP Cron or Action Scheduler.
     */
    public function schedule_cron_runner() {
        $runner_method = $this->plugin->get_option('task_queue_runner_method', 'cron');
        $hook = $this->runner_hook;

        // Clear existing schedules first
        wp_clear_scheduled_hook($hook);
        if (function_exists('as_unschedule_action')) {
             as_unschedule_action($hook);
        }


        if ($runner_method === 'action_scheduler' && function_exists('as_schedule_recurring_action')) {
            // Use Action Scheduler if available and preferred
            // Schedule to run frequently, e.g., every minute
             as_schedule_recurring_action(time(), MINUTE_IN_SECONDS, $hook, [], $hook);
             $this->plugin->logger->log("Scheduled task queue runner using Action Scheduler.", "INFO");
        } else {
            // Fallback to WP Cron, schedule to run frequently (e.g., every 5 minutes)
            // Add a custom cron interval if needed (e.g., 'every_minute')
            if (!wp_get_schedule('every_minute')) {
                 add_filter('cron_schedules', function($schedules) {
                     $schedules['every_minute'] = [
                         'interval' => MINUTE_IN_SECONDS,
                         'display'  => __('Every Minute', MFW_AI_SUPER_TEXT_DOMAIN),
                     ];
                     return $schedules;
                 });
            }
            wp_schedule_event(time(), 'every_minute', $hook);
            $this->plugin->logger->log("Scheduled task queue runner using WP Cron.", "INFO");
        }
    }

     /**
     * Unschedule the task queue runner.
     */
    public function unschedule_cron_runner() {
        $hook = $this->runner_hook;
        wp_clear_scheduled_hook($hook);
         if (function_exists('as_unschedule_action')) {
              as_unschedule_action($hook);
         }
         $this->plugin->logger->log("Unscheduled task queue runner.", "INFO");
    }


    /**
     * Add a task to the queue.
     *
     * @param string $task_type A string identifier for the task type (e.g., 'generate_summary', 'generate_pdf_batch').
     * @param array $task_data An associative array containing data needed to perform the task.
     * @param int $priority Priority level (lower is higher priority).
     * @param int $max_attempts Maximum attempts before failing.
     * @param string|null $scheduled_at Optional timestamp for future scheduling.
     * @return int|false The ID of the inserted task, or false on failure.
     */
    public function add_task($task_type, $task_data, $priority = 10, $max_attempts = 3, $scheduled_at = null) {
        global $wpdb;
        $table_name = $this->task_queue_table;

        $insert_data = [
            'task_type' => sanitize_text_field($task_type),
            'task_data' => wp_json_encode($task_data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'status' => 'pending',
            'priority' => absint($priority),
            'attempts' => 0,
            'max_attempts' => absint($max_attempts),
            'created_at' => current_time('mysql'),
            'scheduled_at' => $scheduled_at ? sanitize_text_field($scheduled_at) : current_time('mysql'),
        ];

        $inserted = $wpdb->insert($table_name, $insert_data, ['%s', '%s', '%s', '%d', '%d', '%d', '%s', '%s']);

        if ($inserted) {
            $task_id = $wpdb->insert_id;
            $this->plugin->logger->log("Task added to queue: ID " . $task_id, "INFO", ['task_type' => $task_type, 'priority' => $priority]);
            return $task_id;
        } else {
            $this->plugin->logger->log("Failed to add task to queue: " . $wpdb->last_error, "ERROR", ['task_type' => $task_type, 'task_data' => $task_data]);
            return false;
        }
    }

    /**
     * The main task runner callback, executed by WP Cron or Action Scheduler.
     */
    public function run_tasks() {
        $this->plugin->logger->log("Task queue runner started.", "INFO");

        $concurrent_tasks = $this->plugin->get_option('task_queue_concurrent_tasks', 3);
        global $wpdb;
        $table_name = $this->task_queue_table;
        $process_id = uniqid('runner_'); // Unique ID for this runner instance

        // Prevent multiple runners from processing the same tasks simultaneously
        // Use SELECT ... FOR UPDATE or update tasks to 'processing' state atomically.
        // A simple approach: Select tasks that are 'pending' or 'retrying' and not currently being processed by THIS runner_id (if a previous run crashed)
        // And update their status to 'processing' with this runner_id.

        // Get tasks to process
        $tasks_to_process = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table_name
             WHERE (status = 'pending' OR (status = 'retrying' AND scheduled_at <= %s))
             ORDER BY priority ASC, scheduled_at ASC
             LIMIT %d",
            current_time('mysql'),
            $concurrent_tasks
        ), ARRAY_A);

        if (empty($tasks_to_process)) {
             $this->plugin->logger->log("Task queue runner found no tasks to process.", "DEBUG");
             return;
        }

        $task_ids = wp_list_pluck($tasks_to_process, 'id');

        // Mark tasks as processing by this runner instance
        $updated = $wpdb->query($wpdb->prepare(
            "UPDATE $table_name
             SET status = 'processing', processing_started_at = %s, process_id = %s
             WHERE id IN (" . implode(',', array_fill(0, count($task_ids), '%d')) . ")",
            array_merge([current_time('mysql'), $process_id], $task_ids)
        ));

        if ($updated === false) {
             $this->plugin->logger->log("Task queue runner failed to mark tasks as processing: " . $wpdb->last_error, "ERROR", ['task_ids' => $task_ids]);
             // Revert status if possible or just log and exit
             return;
        }

        $this->plugin->logger->log(sprintf("Task queue runner processing %d tasks.", count($tasks_to_process)), "INFO", ['task_ids' => $task_ids, 'process_id' => $process_id]);

        // Process each task
        foreach ($tasks_to_process as $task) {
            $task_id = $task['id'];
            $task_type = $task['task_type'];
            $task_data = json_decode($task['task_data'], true);
            $attempts = $task['attempts'];
            $max_attempts = $task['max_attempts'];

            $task_processed = false;
            $error_message = null;

            try {
                // Delegate task processing to a handler method based on task type
                $handler_method = 'handle_task_' . sanitize_key($task_type);
                if (method_exists($this, $handler_method)) {
                    $this->plugin->logger->log("Handling task ID " . $task_id . ": " . $task_type, "DEBUG");
                    $result = $this->$handler_method($task_data); // Call the specific handler

                    if (is_wp_error($result)) {
                        throw new Exception($result->get_error_message());
                    }

                    $task_processed = true; // Task handler completed without throwing exception
                    $this->plugin->logger->log("Task ID " . $task_id . " completed successfully.", "INFO", ['task_type' => $task_type]);

                } else {
                    $error_message = sprintf(__('No handler found for task type: %s', MFW_AI_SUPER_TEXT_DOMAIN), $task_type);
                    $this->plugin->logger->log("Task queue error: " . $error_message, "ERROR", ['task_id' => $task_id, 'task_type' => $task_type]);
                }

            } catch (Exception $e) {
                $error_message = $e->getMessage();
                $this->plugin->logger->log("Exception processing task ID " . $task_id . ": " . $error_message, "ERROR", ['task_type' => $task_type, 'trace' => $e->getTraceAsString()]);
            }

            // Update task status based on outcome
            if ($task_processed) {
                $wpdb->update(
                    $table_name,
                    ['status' => 'completed', 'completed_at' => current_time('mysql')],
                    ['id' => $task_id],
                    ['%s', '%s'],
                    ['%d']
                );
            } else {
                // Task failed, handle retries
                $new_attempts = $attempts + 1;
                if ($new_attempts < $max_attempts) {
                    // Schedule for retry (e.g., 5 minutes from now)
                    $retry_time = date('Y-m-d H:i:s', strtotime('+5 minutes', current_time('timestamp')));
                    $wpdb->update(
                        $table_name,
                        ['status' => 'retrying', 'attempts' => $new_attempts, 'last_error' => $error_message, 'scheduled_at' => $retry_time],
                        ['id' => $task_id],
                        ['%s', '%d', '%s', '%s'],
                        ['%d']
                    );
                    $this->plugin->logger->log(sprintf("Task ID %d failed (Attempt %d/%d). Retrying at %s.", $task_id, $new_attempts, $max_attempts, $retry_time), "WARNING", ['task_type' => $task_type, 'error' => $error_message]);
                } else {
                    // Max attempts reached, mark as failed
                    $wpdb->update(
                        $table_name,
                        ['status' => 'failed', 'attempts' => $new_attempts, 'last_error' => $error_message, 'completed_at' => current_time('mysql')],
                        ['id' => $task_id],
                        ['%s', '%d', '%s', '%s'],
                        ['%d']
                    );
                    $this->plugin->logger->log(sprintf("Task ID %d failed after %d attempts. Marking as failed.", $task_id, $max_attempts), "ERROR", ['task_type' => $task_type, 'error' => $error_message]);
                }
            }
        }

        $this->plugin->logger->log("Task queue runner finished processing tasks.", "INFO", ['process_id' => $process_id]);
    }

    // --- Task Handler Methods ---
    // Implement a method for each supported task type: handle_task_{task_type}

    /**
     * Handle 'generate_summary' task.
     * Task data should contain 'post_id'.
     *
     * @param array $task_data Task data.
     * @return true|WP_Error True on success, WP_Error on failure.
     */
    private function handle_task_generate_summary($task_data) {
        $post_id = absint($task_data['post_id'] ?? 0);
        if (!$post_id || !get_post($post_id)) {
            return new WP_Error('mfw_task_invalid_post', __('Invalid post ID for summary generation task.', MFW_AI_SUPER_TEXT_DOMAIN));
        }
        // TODO: Call the actual summary generation logic from Content_Generator
        // $result = $this->plugin->content_generator->generate_summary($post_id);
        // return $result; // Assuming generate_summary returns true or WP_Error

        $this->plugin->logger->log("Processing generate
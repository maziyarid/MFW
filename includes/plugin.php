<?php
namespace MFW;

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

/**
 * Main Plugin Class
 */
final class Plugin {
    /**
     * Single instance of the class
     *
     * @var Plugin
     */
    private static $_instance = null;

    /**
     * Main Plugin Instance
     *
     * @return Plugin Main instance
     */
    public static function instance() {
        if (is_null(self::$_instance)) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }

    /**
     * Plugin Constructor
     */
    public function __construct() {
        $this->init_hooks();
    }

    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Admin hooks
        if (is_admin()) {
            add_action('admin_menu', [$this, 'add_admin_menu']);
            add_action('admin_init', [$this, 'register_settings']);
        }

        // API hooks
        add_action('rest_api_init', [$this, 'init_rest_api']);
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
        wp_clear_scheduled_hooks('mfw_daily_tasks');
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
        $defaults = [
            'mfw_api_enabled' => 'yes',
            'mfw_default_provider' => 'gemini',
            'mfw_daily_limit' => 100,
            'mfw_batch_size' => 10,
        ];

        foreach ($defaults as $key => $value) {
            if (get_option($key) === false) {
                add_option($key, $value);
            }
        }
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
     * Register settings
     */
    public function register_settings() {
        register_setting('mfw_settings', 'mfw_api_enabled');
        register_setting('mfw_settings', 'mfw_default_provider');
        register_setting('mfw_settings', 'mfw_openai_api_key');
        register_setting('mfw_settings', 'mfw_gemini_api_key');
    }

    /**
     * Render settings page
     */
    public function render_settings_page() {
        if (!current_user_can('manage_options')) {
            return;
        }

        // Include settings template
        include MFW_PLUGIN_DIR . 'templates/admin-settings.php';
    }

    /**
     * Initialize REST API
     */
    public function init_rest_api() {
        // Register routes here
    }
}
<?php
/**
 * Plugin Name: SureFeedback
 * Plugin URI: https://surefeedback.com
 * Description: A powerful feedback collection and management plugin for WordPress.
 * Version: 1.0.0
 * Author: SureFeedback Team
 * License: GPL v2 or later
 * Text Domain: surefeedback
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('SUREFEEDBACK_VERSION', '1.0.0');
define('SUREFEEDBACK_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('SUREFEEDBACK_PLUGIN_URL', plugin_dir_url(__FILE__));

class SureFeedback {
    
    public function __construct() {
        add_action('init', array($this, 'init'));
    }
    
    public function init() {
        // Hook into admin_menu to add menu items
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
    }
    
    public function add_admin_menu() {
        add_menu_page(
            'SureFeedback',
            'SureFeedback',
            'manage_options',
            'surefeedback',
            array($this, 'admin_page'),
            'dashicons-feedback',
            30
        );
        
        add_submenu_page(
            'surefeedback',
            'SureFeedback Settings',
            'Settings',
            'manage_options',
            'surefeedback-settings',
            array($this, 'settings_page')
        );
    }
    
    public function admin_page() {
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            <div class="card">
                <h2>Welcome to SureFeedback</h2>
                <p>Thank you for installing SureFeedback! This powerful plugin helps you collect and manage feedback from your users.</p>
                <p><a href="<?php echo admin_url('admin.php?page=surefeedback-settings'); ?>" class="button button-primary">Go to Settings</a></p>
            </div>
        </div>
        <?php
    }
    
    public function register_settings() {
        register_setting('surefeedback_settings', 'surefeedback_enable_feedback');
        register_setting('surefeedback_settings', 'surefeedback_feedback_email');
        register_setting('surefeedback_settings', 'surefeedback_auto_approve');
        
        add_settings_section(
            'surefeedback_general_section',
            'General Settings',
            array($this, 'general_section_callback'),
            'surefeedback_settings'
        );
        
        add_settings_field(
            'surefeedback_enable_feedback',
            'Enable Feedback Collection',
            array($this, 'enable_feedback_callback'),
            'surefeedback_settings',
            'surefeedback_general_section'
        );
        
        add_settings_field(
            'surefeedback_feedback_email',
            'Notification Email',
            array($this, 'feedback_email_callback'),
            'surefeedback_settings',
            'surefeedback_general_section'
        );
        
        add_settings_field(
            'surefeedback_auto_approve',
            'Auto-approve Feedback',
            array($this, 'auto_approve_callback'),
            'surefeedback_settings',
            'surefeedback_general_section'
        );
    }
    
    public function general_section_callback() {
        echo '<p>Configure the general settings for SureFeedback.</p>';
    }
    
    public function enable_feedback_callback() {
        $value = get_option('surefeedback_enable_feedback', 1);
        echo '<input type="checkbox" id="surefeedback_enable_feedback" name="surefeedback_enable_feedback" value="1" ' . checked(1, $value, false) . ' />';
        echo '<label for="surefeedback_enable_feedback">Enable feedback collection on your site</label>';
    }
    
    public function feedback_email_callback() {
        $value = get_option('surefeedback_feedback_email', get_option('admin_email'));
        echo '<input type="email" id="surefeedback_feedback_email" name="surefeedback_feedback_email" value="' . esc_attr($value) . '" class="regular-text" />';
        echo '<p class="description">Email address to receive feedback notifications</p>';
    }
    
    public function auto_approve_callback() {
        $value = get_option('surefeedback_auto_approve', 0);
        echo '<input type="checkbox" id="surefeedback_auto_approve" name="surefeedback_auto_approve" value="1" ' . checked(1, $value, false) . ' />';
        echo '<label for="surefeedback_auto_approve">Automatically approve new feedback</label>';
    }
    
    public function settings_page() {
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            <form method="post" action="options.php">
                <?php
                settings_fields('surefeedback_settings');
                do_settings_sections('surefeedback_settings');
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }
}

// Activation hook
function surefeedback_activate() {
    // Set default options
    add_option('surefeedback_enable_feedback', 1);
    add_option('surefeedback_feedback_email', get_option('admin_email'));
    add_option('surefeedback_auto_approve', 0);
    
    // Create database tables if needed
    surefeedback_create_tables();
}

// Deactivation hook
function surefeedback_deactivate() {
    // Clean up scheduled hooks if any
    wp_clear_scheduled_hook('surefeedback_cleanup');
}

// Create database tables
function surefeedback_create_tables() {
    global $wpdb;
    
    $table_name = $wpdb->prefix . 'surefeedback_entries';
    
    $charset_collate = $wpdb->get_charset_collate();
    
    $sql = "CREATE TABLE $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        user_id bigint(20),
        feedback_text text NOT NULL,
        feedback_rating int(1),
        feedback_email varchar(100),
        feedback_url varchar(255),
        status varchar(20) DEFAULT 'pending',
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id)
    ) $charset_collate;";
    
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}

// Register activation and deactivation hooks
register_activation_hook(__FILE__, 'surefeedback_activate');
register_deactivation_hook(__FILE__, 'surefeedback_deactivate');

// Initialize the plugin
new SureFeedback();
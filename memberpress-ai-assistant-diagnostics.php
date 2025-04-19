<?php
/**
 * Plugin Name: MemberPress AI Assistant Diagnostics
 * Plugin URI: https://memberpress.com/memberpress-ai-assistant
 * Description: Diagnostic and testing tools for the MemberPress AI Assistant plugin.
 * Version: 1.0.0
 * Author: MemberPress
 * Author URI: https://memberpress.com
 * Text Domain: memberpress-ai-assistant-diagnostics
 * Domain Path: /languages
 * License: GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 */

if (!defined('WPINC')) {
    die;
}

// Define plugin constants
define('MPAI_DIAG_VERSION', '1.0.0');
define('MPAI_DIAG_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('MPAI_DIAG_PLUGIN_URL', plugin_dir_url(__FILE__));
define('MPAI_DIAG_PLUGIN_BASENAME', plugin_basename(__FILE__));

/**
 * Class MemberPress_AI_Assistant_Diagnostics
 *
 * Main class responsible for initializing the MemberPress AI Assistant Diagnostics plugin
 */
class MemberPress_AI_Assistant_Diagnostics {

    /**
     * Instance of this class
     *
     * @var MemberPress_AI_Assistant_Diagnostics
     */
    private static $instance = null;

    /**
     * Main plugin instance reference
     *
     * @var MemberPress_AI_Assistant
     */
    private $main_plugin = null;

    /**
     * Constructor
     */
    private function __construct() {
        // If the main plugin is not active, show a notice but still load our functionality
        if (!$this->check_main_plugin()) {
            add_action('admin_notices', array($this, 'display_dependency_notice'));
            // Continue loading even without the main plugin
        }

        // Load dependencies
        $this->load_dependencies();
        
        // Register activation and deactivation hooks
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));

        // Initialize plugin components
        add_action('init', array($this, 'init'));
        
        // Register admin page in WordPress Tools menu - no need for special priority
        add_action('admin_menu', array($this, 'register_admin_page'));
        
        // Register AJAX handlers
        $this->register_ajax_handlers();
    }

    /**
     * Get plugin instance
     *
     * @return MemberPress_AI_Assistant_Diagnostics
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Check if the main plugin is active
     * 
     * @return bool
     */
    private function check_main_plugin() {
        // First check if the main plugin class exists
        if (!class_exists('MemberPress_AI_Assistant')) {
            // Will be handled by mpai_log_error if loaded later
            return false;
        }

        // Get an instance of the main plugin to reference later
        if (method_exists('MemberPress_AI_Assistant', 'get_instance')) {
            $this->main_plugin = MemberPress_AI_Assistant::get_instance();
            
            // Log successful detection using proper logging
            if (function_exists('mpai_log_debug')) {
                mpai_log_debug('Diagnostics plugin initialized successfully', 'diagnostics');
            }
        }

        return true;
    }

    /**
     * Display dependency notice
     */
    public function display_dependency_notice() {
        ?>
        <div class="error notice">
            <p><?php _e('MemberPress AI Assistant Diagnostics requires the MemberPress AI Assistant plugin to be installed and activated.', 'memberpress-ai-assistant-diagnostics'); ?></p>
        </div>
        <?php
    }

    /**
     * Load dependencies
     */
    private function load_dependencies() {
        // Include diagnostic page class
        require_once MPAI_DIAG_PLUGIN_DIR . 'includes/class-mpai-diagnostics-page.php';
        
        // Include helper functions
        require_once MPAI_DIAG_PLUGIN_DIR . 'includes/functions.php';
    }

    /**
     * Register AJAX handlers
     */
    private function register_ajax_handlers() {
        // Register AJAX handler for running edge case tests
        add_action('wp_ajax_mpai_run_edge_case_tests', array($this, 'run_edge_case_tests_ajax'));
        
        // Register AJAX handler for testing error recovery
        add_action('wp_ajax_mpai_test_error_recovery', array($this, 'test_error_recovery_ajax'));
    }

    /**
     * Initialize plugin
     */
    public function init() {
        // Make sure we're in the admin area
        if (!is_admin()) {
            return;
        }
        
        // Check if the diagnostics page class is available - this is our own class
        if (!class_exists('MPAI_Diagnostics_Page')) {
            // Try to load it if it wasn't loaded already
            if (file_exists(MPAI_DIAG_PLUGIN_DIR . 'includes/class-mpai-diagnostics-page.php')) {
                require_once MPAI_DIAG_PLUGIN_DIR . 'includes/class-mpai-diagnostics-page.php';
            }
            
            if (!class_exists('MPAI_Diagnostics_Page')) {
                if (function_exists('mpai_log_error')) {
                    mpai_log_error('MPAI_Diagnostics_Page class not found', 'diagnostics');
                }
                error_log('MPAI Diagnostics: Class MPAI_Diagnostics_Page not found');
                return;
            }
        }
        
        // Initialize the diagnostics page
        try {
            new MPAI_Diagnostics_Page();
            
            if (function_exists('mpai_log_debug')) {
                mpai_log_debug('Diagnostics page initialized successfully', 'diagnostics');
            }
        } catch (Exception $e) {
            if (function_exists('mpai_log_error')) {
                mpai_log_error('Error initializing diagnostics page: ' . $e->getMessage(), 'diagnostics');
            }
            error_log('MPAI Diagnostics: Error initializing diagnostics page: ' . $e->getMessage());
        }
    }

    /**
     * Plugin activation
     */
    public function activate() {
        // Perform activation tasks if needed
    }

    /**
     * Plugin deactivation
     */
    public function deactivate() {
        // Perform deactivation tasks if needed
    }

    /**
     * Register admin page in WordPress Tools menu
     */
    public function register_admin_page() {
        // Log admin page registration
        if (function_exists('mpai_log_debug')) {
            mpai_log_debug('Registering diagnostics page in WordPress Tools menu', 'diagnostics');
        }
        
        // Add the diagnostics page to the Tools menu
        add_submenu_page(
            'tools.php',  // Parent slug (Tools menu)
            __('MemberPress AI Assistant Diagnostics', 'memberpress-ai-assistant-diagnostics'),
            __('MemberPress AI Diagnostics', 'memberpress-ai-assistant-diagnostics'),
            'manage_options',
            'memberpress-ai-assistant-diagnostics',
            array($this, 'render_admin_page')
        );
        
        if (function_exists('mpai_log_debug')) {
            mpai_log_debug('Added diagnostics page to WordPress Tools menu', 'diagnostics');
        }
    }
    
    /**
     * This function is now deprecated as we're registering the page directly
     * in the WordPress Tools menu
     */
    public function check_diagnostics_page() {
        // No longer needed as we're properly registering the page
        if (function_exists('mpai_log_debug')) {
            mpai_log_debug('check_diagnostics_page is deprecated', 'diagnostics');
        }
    }

    /**
     * Render admin page
     * 
     * CRITICAL FIX: Updated to work without menu registration
     */
    public function render_admin_page() {
        // Render diagnostics main page with updated styling for off-menu access
        ?>
        <style>
        .diagnostics-heading {
            background-color: #fff;
            padding: 20px;
            margin-left: -20px;
            margin-right: -20px;
            margin-top: -20px;
            border-bottom: 1px solid #ccd0d4;
            box-shadow: 0 1px 1px rgba(0,0,0,.04);
        }
        .diagnostics-heading h1 {
            font-size: 23px;
            font-weight: 400;
            margin: 0;
            padding: 9px 0 4px;
            line-height: 1.3;
        }
        .diagnostics-content {
            margin-top: 20px;
        }
        </style>
        
        <div class="wrap">
            <div class="diagnostics-heading">
                <h1><?php echo esc_html__('MemberPress AI Assistant Diagnostics', 'memberpress-ai-assistant-diagnostics'); ?></h1>
            </div>
            
            <div class="diagnostics-content">
                <div class="mpai-diagnostic-container">
                    <?php do_action('mpai_render_diagnostics'); ?>
                </div>
            </div>
            
            <p>
                <a href="<?php echo admin_url('admin.php?page=memberpress-ai-assistant-settings'); ?>" class="button">
                    <?php _e('Back to Settings', 'memberpress-ai-assistant-diagnostics'); ?>
                </a>
            </p>
        </div>
        <?php
    }

    /**
     * AJAX handler for running edge case tests
     */
    public function run_edge_case_tests_ajax() {
        // Check nonce for security
        check_ajax_referer('mpai_nonce', 'nonce');
        
        // Only allow logged-in users with appropriate capabilities
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized access');
            return;
        }
        
        try {
            // Load the Edge Case Test Suite file only when explicitly requested
            mpai_log_debug('Loading edge case test suite for AJAX request', 'diagnostics');
            $test_file = MPAI_DIAG_PLUGIN_DIR . 'test/edge-cases/test-edge-cases.php';
            if (file_exists($test_file)) {
                require_once $test_file;
            } else {
                mpai_log_error('Edge case test file not found at: ' . $test_file, 'diagnostics');
                wp_send_json_error('Test file not found');
                return;
            }
            
            if (function_exists('mpai_display_all_edge_case_tests')) {
                ob_start();
                mpai_display_all_edge_case_tests();
                $output = ob_get_clean();
                
                wp_send_json_success($output);
            } else {
                wp_send_json_error('Edge Case Test Suite functions not found');
            }
        } catch (Exception $e) {
            wp_send_json_error('Error running edge case tests: ' . $e->getMessage());
        }
    }

    /**
     * AJAX handler for testing the Error Recovery System
     */
    public function test_error_recovery_ajax() {
        // Check nonce for security
        check_ajax_referer('mpai_nonce', 'nonce');
        
        // Only allow logged-in users with appropriate capabilities
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized access');
            return;
        }
        
        try {
            // Include the test script
            $test_file = MPAI_DIAG_PLUGIN_DIR . 'test/test-error-recovery.php';
            if (file_exists($test_file)) {
                require_once($test_file);
                
                if (function_exists('mpai_test_error_recovery')) {
                    $results = mpai_test_error_recovery();
                    wp_send_json($results);
                } else {
                    wp_send_json_error(array(
                        'message' => 'Error recovery test function not found',
                        'success' => false
                    ));
                }
            } else {
                wp_send_json_error(array(
                    'message' => 'Error recovery test file not found at: ' . $test_file,
                    'success' => false
                ));
            }
        } catch (Exception $e) {
            wp_send_json_error(array(
                'message' => 'Error running tests: ' . $e->getMessage(),
                'success' => false
            ));
        }
    }
}

// Initialize the plugin AFTER the main plugin has loaded (main plugin uses priority 10)
add_action('plugins_loaded', array('MemberPress_AI_Assistant_Diagnostics', 'get_instance'), 20);
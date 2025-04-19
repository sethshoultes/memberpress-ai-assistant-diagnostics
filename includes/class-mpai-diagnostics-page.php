<?php
/**
 * Diagnostics Page Class
 * 
 * Handles all diagnostic functionality in a dedicated page
 *
 * @package MemberPress AI Assistant Diagnostics
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

/**
 * Class MPAI_Diagnostics_Page
 * 
 * Responsible for diagnostics page rendering and functionality
 */
class MPAI_Diagnostics_Page {
    /**
     * Constructor
     */
    public function __construct() {
        // We don't need to register a page here - it's handled by the main plugin class
        
        // Register AJAX handlers
        add_action('wp_ajax_mpai_run_diagnostic_test', [$this, 'handle_ajax_run_test']);
        add_action('wp_ajax_mpai_run_category_tests', [$this, 'handle_ajax_run_category_tests']);
        add_action('wp_ajax_mpai_run_all_tests', [$this, 'handle_ajax_run_all_tests']);
        
        // Register actions for rendering diagnostics
        add_action('mpai_render_diagnostics', [$this, 'render_diagnostic_interface']);
        
        // Enqueue required assets
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
    }

    /**
     * Register the diagnostics page
     * 
     * Note: This is no longer used as we register the page directly in the main class
     * to avoid conflicts with the main plugin menu.
     */
    public function register_page() {
        // No longer attempting to register with the main plugin menu
        // to avoid conflicts and menu disappearance.
        if (function_exists('mpai_log_debug')) {
            mpai_log_debug('Diagnostics page registration method called but skipped intentionally', 'diagnostics');
        }
    }

    /**
     * Enqueue page assets
     * 
     * @param string $hook Current admin page hook
     */
    public function enqueue_assets($hook) {
        // Only load assets on the diagnostics page for safety
        $is_diagnostics_page = (strpos($hook, 'memberpress-ai-assistant-diagnostics') !== false);
        
        // Log asset loading
        if (function_exists('mpai_log_debug')) {
            mpai_log_debug('Checking if diagnostics assets should be loaded on hook: ' . $hook, 'diagnostics');
            mpai_log_debug('Is diagnostics page: ' . ($is_diagnostics_page ? 'yes' : 'no'), 'diagnostics');
        }
        
        // Don't load if not on our diagnostics page
        if (!$is_diagnostics_page) {
            return;
        }
        
        // Enqueue CSS for diagnostics
        wp_enqueue_style(
            'mpai-diag-diagnostics-css', // Use a unique handle
            MPAI_DIAG_PLUGIN_URL . 'assets/css/diagnostics.css',
            [],
            MPAI_DIAG_VERSION
        );
        
        // Enqueue diagnostics JavaScript - also with a unique handle
        wp_enqueue_script(
            'mpai-diag-diagnostics-js',
            MPAI_DIAG_PLUGIN_URL . 'assets/js/diagnostics.js',
            ['jquery'],
            MPAI_DIAG_VERSION,
            true
        );
        
        // Pass data to script
        wp_localize_script(
            'mpai-diag-diagnostics-js',
            'mpai_diagnostics',
            [
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('mpai_nonce'),
                'tests' => $this->get_available_tests(),
                'categories' => $this->get_test_categories(),
                'is_diagnostics_page' => true
            ]
        );
        
        // Log successful asset loading
        if (function_exists('mpai_log_debug')) {
            mpai_log_debug('Diagnostics assets loaded successfully', 'diagnostics');
        }
    }

    /**
     * Render the diagnostic interface for use within other pages
     */
    public function render_diagnostic_interface() {
        // Add styles for the diagnostic interface
        $this->render_styles();
        
        ?>
        <div class="mpai-diagnostics-container">
            <div class="mpai-diagnostics-intro">
                <p><?php _e('Run diagnostic tests to check the health of your MemberPress AI Assistant installation.', 'memberpress-ai-assistant-diagnostics'); ?></p>
            </div>
            
            <div class="mpai-diagnostics-categories">
                <h2><?php _e('System Information', 'memberpress-ai-assistant-diagnostics'); ?></h2>
                <div class="mpai-system-info-container">
                    <?php $this->render_system_info(); ?>
                </div>
                
                <h2><?php _e('Diagnostic Tests', 'memberpress-ai-assistant-diagnostics'); ?></h2>
                <div class="mpai-test-categories">
                    <?php $this->render_test_categories(); ?>
                </div>
            </div>
            
            <div class="mpai-test-results">
                <h2><?php _e('Test Results', 'memberpress-ai-assistant-diagnostics'); ?></h2>
                <div id="mpai-test-results-container">
                    <p class="mpai-empty-results"><?php _e('Run a test to see results.', 'memberpress-ai-assistant-diagnostics'); ?></p>
                </div>
            </div>
            
            <div class="mpai-global-actions">
                <button type="button" id="mpai-run-all-tests" class="button button-primary">
                    <?php _e('Run All Diagnostics', 'memberpress-ai-assistant-diagnostics'); ?>
                </button>
            </div>
            
            <div id="mpai-test-results-summary" class="mpai-test-results-summary" style="display: none;">
                <h3><?php _e('Test Results Summary', 'memberpress-ai-assistant-diagnostics'); ?></h3>
                <div id="mpai-summary-content"></div>
            </div>
        </div>
        
        <!-- Add a custom message to show diagnostics in dashboard view -->
        <?php if (!isset($_GET['page']) || $_GET['page'] !== 'memberpress-ai-assistant-diagnostics'): ?>
        <div class="mpai-dashboard-view-only notice notice-info">
            <p><?php _e('You are viewing a preview of the diagnostics dashboard. For full testing functionality, click the button below:', 'memberpress-ai-assistant-diagnostics'); ?></p>
            <p><a href="<?php echo admin_url('admin.php?page=memberpress-ai-assistant-diagnostics'); ?>" class="button button-primary"><?php _e('Go to Diagnostics Page', 'memberpress-ai-assistant-diagnostics'); ?></a></p>
        </div>
        <style>
        .mpai-dashboard-view-only {
            margin: 20px 0;
            padding: 15px;
        }
        </style>
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            // Simple tab handling for dashboard display
            $('.mpai-category-tabs a').on('click', function(e) {
                e.preventDefault();
                
                // Get the target tab
                var target = $(this).attr('href');
                
                // Remove active class from all tabs
                $('.mpai-category-tabs a').removeClass('active');
                
                // Hide all category content
                $('.mpai-category-content').hide();
                
                // Add active class to the clicked tab and show its content
                $(this).addClass('active');
                $(target).show();
            });
            
            // Activate first tab by default
            if ($('.mpai-category-tabs a:first').length) {
                $('.mpai-category-tabs a:first').click();
            }
            
            // Redirect all test buttons to the main diagnostics page
            $('.mpai-run-test, #mpai-run-all-tests').on('click', function(e) {
                e.preventDefault();
                window.location.href = '<?php echo admin_url('admin.php?page=memberpress-ai-assistant-diagnostics'); ?>';
                return false;
            });
        });
        </script>
        <?php else: ?>
        <!-- Only load the full diagnostics UI on the dedicated page -->
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            // Initialize tabs
            $('.mpai-category-tabs a').on('click', function(e) {
                e.preventDefault();
                
                // Get the target tab
                var target = $(this).attr('href');
                
                // Remove active class from all tabs
                $('.mpai-category-tabs a').removeClass('active');
                
                // Hide all category content
                $('.mpai-category-content').hide();
                
                // Add active class to the clicked tab and show its content
                $(this).addClass('active');
                $(target).show();
            });
            
            // Activate first tab by default
            if ($('.mpai-category-tabs a:first').length) {
                $('.mpai-category-tabs a:first').click();
            }
        });
        </script>
        <?php endif; ?>
        <?php
    }

    /**
     * Render the diagnostics page
     */
    public function render_page() {
        ?>
        <div class="wrap">
            <h1><?php _e('MemberPress AI Assistant Diagnostics', 'memberpress-ai-assistant-diagnostics'); ?></h1>
            
            <?php $this->render_diagnostic_interface(); ?>
        </div>
        <?php
    }
    
    /**
     * Render the system styles for the diagnostic page
     */
    private function render_styles() {
        ?>
        <style>
        /* Diagnostic System Styles */
        .mpai-diagnostics-container {
            margin-top: 20px;
        }
        
        .mpai-category-tabs {
            display: flex;
            flex-wrap: wrap;
            padding: 0;
            margin: 0 0 20px 0;
            list-style: none;
            border-bottom: 1px solid #ccc;
        }
        
        .mpai-category-tabs li {
            margin-bottom: -1px;
        }
        
        .mpai-category-tabs a {
            display: block;
            padding: 8px 12px;
            text-decoration: none;
            margin-right: 5px;
            border: 1px solid transparent;
            border-bottom: none;
            border-radius: 4px 4px 0 0;
            background: #f1f1f1;
            color: #555;
        }
        
        .mpai-category-tabs a:hover {
            background: #e5e5e5;
        }
        
        .mpai-category-tabs a.active {
            background: #fff;
            color: #000;
            border-color: #ccc;
            border-bottom-color: #fff;
        }
        
        .mpai-category-content {
            margin-bottom: 30px;
            background: #fff;
            border: 1px solid #ccc;
            border-top: none;
            padding: 20px;
            border-radius: 0 0 4px 4px;
        }
        
        .mpai-test-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(400px, 1fr));
            gap: 20px;
            margin-top: 20px;
            margin-bottom: 20px;
        }
        
        .mpai-test-card {
            border: 1px solid #e0e0e0;
            border-radius: 5px;
            padding: 15px;
            background: #f9f9f9;
            transition: box-shadow 0.3s;
        }
        
        .mpai-test-card:hover {
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        
        .mpai-test-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }
        
        .mpai-test-header h4 {
            margin: 0;
            font-size: 16px;
        }
        
        .mpai-test-status {
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .mpai-status-dot {
            width: 12px;
            height: 12px;
            border-radius: 50%;
        }
        
        .mpai-status-unknown {
            background-color: #bbb;
        }
        
        .mpai-status-loading {
            background-color: #2271b1;
            animation: pulse 1.5s infinite;
        }
        
        .mpai-status-success {
            background-color: #46b450;
        }
        
        .mpai-status-error {
            background-color: #dc3232;
        }
        
        .mpai-status-warning {
            background-color: #dba617;
        }
        
        .mpai-test-actions {
            display: flex;
            gap: 10px;
            margin-top: 15px;
        }
        
        .mpai-test-result {
            margin-top: 15px;
            border-top: 1px solid #e0e0e0;
            padding-top: 15px;
        }
        
        .mpai-test-result-content {
            margin-bottom: 15px;
        }
        
        .mpai-result-header {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 10px;
            padding: 8px 12px;
            border-radius: 4px;
        }
        
        .mpai-result-header.success {
            background-color: rgba(70, 180, 80, 0.1);
            color: #2e7d32;
        }
        
        .mpai-result-header.error {
            background-color: rgba(220, 50, 50, 0.1);
            color: #c62828;
        }
        
        .mpai-result-header.warning {
            background-color: rgba(219, 166, 23, 0.1);
            color: #b45309;
        }
        
        .mpai-result-header h4 {
            margin: 0;
            font-size: 15px;
        }
        
        .mpai-result-message {
            margin-bottom: 15px;
        }
        
        .mpai-timing-info {
            font-size: 13px;
            color: #777;
            margin-bottom: 15px;
        }
        
        .mpai-subtests {
            margin-top: 20px;
        }
        
        .mpai-doc-link {
            margin-top: 15px;
            font-size: 13px;
        }
        
        .mpai-category-actions {
            margin-top: 20px;
        }
        
        .mpai-global-actions {
            margin-top: 30px;
            margin-bottom: 20px;
        }
        
        .mpai-test-results-summary {
            margin-top: 30px;
            padding: 20px;
            background: #fff;
            border: 1px solid #ccc;
            border-radius: 4px;
        }
        
        .mpai-empty-tests {
            grid-column: 1 / -1;
            padding: 20px;
            text-align: center;
            background: rgba(0,0,0,0.03);
            border-radius: 4px;
        }
        
        .mpai-system-info-table {
            border-collapse: collapse;
            width: 100%;
            margin-bottom: 20px;
        }
        
        .mpai-system-info-table th,
        .mpai-system-info-table td {
            padding: 8px 12px;
            text-align: left;
            border-bottom: 1px solid #e0e0e0;
        }
        
        .mpai-system-info-table th {
            background-color: #f5f5f5;
            font-weight: bold;
        }
        
        .mpai-status-good {
            color: #46b450;
        }
        
        .mpai-status-warning {
            color: #dba617;
        }
        
        .mpai-status-error {
            color: #dc3232;
        }
        
        .mpai-status-message {
            font-size: 12px;
            color: #666;
            display: block;
            margin-top: 3px;
        }
        
        @keyframes pulse {
            0% { opacity: 1; }
            50% { opacity: 0.4; }
            100% { opacity: 1; }
        }
        
        /* Responsive adjustments */
        @media (max-width: 782px) {
            .mpai-test-grid {
                grid-template-columns: 1fr;
            }
            
            .mpai-category-tabs {
                flex-direction: column;
                border-bottom: none;
            }
            
            .mpai-category-tabs li {
                margin-bottom: 5px;
            }
            
            .mpai-category-tabs a {
                border: 1px solid #ccc;
                border-radius: 4px;
            }
            
            .mpai-category-tabs a.active {
                border-bottom-color: #ccc;
            }
            
            .mpai-category-content {
                border: 1px solid #ccc;
                border-radius: 4px;
            }
        }
        </style>
        <?php
    }

    /**
     * Render system information
     */
    private function render_system_info() {
        $info = $this->get_system_info();
        
        ?>
        <table class="widefat mpai-system-info-table">
            <thead>
                <tr>
                    <th><?php _e('Setting', 'memberpress-ai-assistant'); ?></th>
                    <th><?php _e('Value', 'memberpress-ai-assistant'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($info as $label => $value): ?>
                <tr>
                    <td><?php echo esc_html($label); ?></td>
                    <td>
                        <?php 
                        if (is_array($value) && isset($value['value'], $value['status'])) {
                            echo '<span class="mpai-status-' . esc_attr($value['status']) . '">';
                            echo esc_html($value['value']);
                            echo '</span>';
                            
                            if (isset($value['message'])) {
                                echo ' <span class="mpai-status-message">' . esc_html($value['message']) . '</span>';
                            }
                        } else {
                            echo esc_html($value);
                        }
                        ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php
    }

    /**
     * Get system information
     * 
     * @return array System information
     */
    private function get_system_info() {
        global $wp_version;
        
        // Get WordPress info
        $info = [
            __('WordPress Version', 'memberpress-ai-assistant') => $wp_version,
            __('PHP Version', 'memberpress-ai-assistant') => phpversion(),
            __('Plugin Version', 'memberpress-ai-assistant') => MPAI_VERSION,
        ];
        
        // Add MemberPress detection status
        $has_memberpress = class_exists('MeprAppCtrl');
        $info[__('MemberPress', 'memberpress-ai-assistant')] = [
            'value' => $has_memberpress ? __('Detected', 'memberpress-ai-assistant') : __('Not Detected', 'memberpress-ai-assistant'),
            'status' => $has_memberpress ? 'good' : 'warning',
            'message' => !$has_memberpress ? __('MemberPress is recommended for full functionality.', 'memberpress-ai-assistant') : ''
        ];
        
        // Add OpenAI API connection status
        $openai_api_key = get_option('mpai_api_key', '');
        $info[__('OpenAI API', 'memberpress-ai-assistant')] = [
            'value' => !empty($openai_api_key) ? __('Configured', 'memberpress-ai-assistant') : __('Not Configured', 'memberpress-ai-assistant'),
            'status' => !empty($openai_api_key) ? 'good' : 'warning',
            'message' => empty($openai_api_key) ? __('API key required for AI functionality.', 'memberpress-ai-assistant') : ''
        ];
        
        // Add Anthropic API connection status
        $anthropic_api_key = get_option('mpai_anthropic_api_key', '');
        $info[__('Anthropic API', 'memberpress-ai-assistant')] = [
            'value' => !empty($anthropic_api_key) ? __('Configured', 'memberpress-ai-assistant') : __('Not Configured', 'memberpress-ai-assistant'),
            'status' => !empty($anthropic_api_key) ? 'good' : 'warning',
            'message' => empty($anthropic_api_key) ? __('API key required for Claude AI functionality.', 'memberpress-ai-assistant') : ''
        ];
        
        // Add primary API provider
        $primary_api = get_option('mpai_primary_api', 'openai');
        $has_primary_key = $primary_api === 'openai' ? !empty($openai_api_key) : !empty($anthropic_api_key);
        $info[__('Primary AI Provider', 'memberpress-ai-assistant')] = [
            'value' => $primary_api === 'openai' ? __('OpenAI (GPT)', 'memberpress-ai-assistant') : __('Anthropic (Claude)', 'memberpress-ai-assistant'),
            'status' => $has_primary_key ? 'good' : 'warning',
            'message' => !$has_primary_key ? __('Primary provider API key not configured.', 'memberpress-ai-assistant') : ''
        ];
        
        // Add platform info
        $info[__('Operating System', 'memberpress-ai-assistant')] = PHP_OS;
        $info[__('Server Software', 'memberpress-ai-assistant')] = $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown';
        $info[__('Memory Limit', 'memberpress-ai-assistant')] = ini_get('memory_limit');
        $info[__('Max Execution Time', 'memberpress-ai-assistant')] = ini_get('max_execution_time') . 's';
        
        // Add curl info
        $info[__('cURL Enabled', 'memberpress-ai-assistant')] = [
            'value' => function_exists('curl_version') ? __('Yes', 'memberpress-ai-assistant') : __('No', 'memberpress-ai-assistant'),
            'status' => function_exists('curl_version') ? 'good' : 'error',
            'message' => !function_exists('curl_version') ? __('cURL is required for API communication.', 'memberpress-ai-assistant') : ''
        ];
        
        if (function_exists('curl_version')) {
            $curl_version = curl_version();
            $info[__('cURL Version', 'memberpress-ai-assistant')] = $curl_version['version'];
            $info[__('SSL Version', 'memberpress-ai-assistant')] = $curl_version['ssl_version'];
        }
        
        return $info;
    }

    /**
     * Render test categories
     */
    private function render_test_categories() {
        $categories = $this->get_test_categories();
        
        ?>
        <div class="mpai-category-tabs-container">
            <ul class="mpai-category-tabs">
                <?php foreach ($categories as $category_id => $category): ?>
                    <li>
                        <a href="#category-<?php echo esc_attr($category_id); ?>" class="<?php echo $category_id === 'core' ? 'active' : ''; ?>">
                            <?php echo esc_html($category['name']); ?>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
            
            <?php foreach ($categories as $category_id => $category): ?>
                <div id="category-<?php echo esc_attr($category_id); ?>" class="mpai-category-content" 
                    style="<?php echo $category_id === 'core' ? '' : 'display:none;'; ?>">
                    <h3><?php echo esc_html($category['name']); ?></h3>
                    <p><?php echo esc_html($category['description']); ?></p>
                    
                    <div class="mpai-test-grid">
                        <?php 
                        $tests = $this->get_tests_by_category($category_id);
                        if (!empty($tests)): 
                            foreach ($tests as $test_id => $test): 
                        ?>
                            <div class="mpai-test-card" data-test-id="<?php echo esc_attr($test_id); ?>">
                                <div class="mpai-test-header">
                                    <h4><?php echo esc_html($test['name']); ?></h4>
                                    <div class="mpai-test-status">
                                        <span class="mpai-status-dot mpai-status-unknown"></span>
                                        <span class="mpai-status-text"><?php _e('Not Run', 'memberpress-ai-assistant'); ?></span>
                                    </div>
                                </div>
                                <p><?php echo esc_html($test['description']); ?></p>
                                <div class="mpai-test-actions">
                                    <button type="button" class="button mpai-run-test">
                                        <?php _e('Run Test', 'memberpress-ai-assistant'); ?>
                                    </button>
                                    <?php if (!empty($test['direct_url'])): ?>
                                        <a href="<?php echo esc_url(MPAI_PLUGIN_URL . $test['direct_url']); ?>" class="button" target="_blank">
                                            <?php _e('Direct Test', 'memberpress-ai-assistant'); ?>
                                        </a>
                                    <?php endif; ?>
                                </div>
                                <div class="mpai-test-result" style="display: none;"></div>
                            </div>
                        <?php 
                            endforeach;
                        else:
                        ?>
                            <div class="mpai-empty-tests">
                                <p><?php _e('No tests available in this category.', 'memberpress-ai-assistant'); ?></p>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <?php if (count($tests) > 1): ?>
                        <div class="mpai-category-actions">
                            <button type="button" class="button button-secondary mpai-run-category-tests" 
                                    data-category="<?php echo esc_attr($category_id); ?>">
                                <?php _e('Run All Tests in Category', 'memberpress-ai-assistant'); ?>
                            </button>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
        <?php
    }

    /**
     * Get test categories
     * 
     * @return array Test categories
     */
    private function get_test_categories() {
        return [
            'core' => [
                'name' => __('Core System', 'memberpress-ai-assistant'),
                'description' => __('Tests for core system functionality and environment', 'memberpress-ai-assistant')
            ],
            'api' => [
                'name' => __('API Connections', 'memberpress-ai-assistant'),
                'description' => __('Tests for API connectivity and functionality', 'memberpress-ai-assistant')
            ],
            'tools' => [
                'name' => __('AI Tools', 'memberpress-ai-assistant'),
                'description' => __('Tests for AI tool functionality', 'memberpress-ai-assistant')
            ],
            'plugins' => [
                'name' => __('Plugin Management', 'memberpress-ai-assistant'),
                'description' => __('Information about installed plugins and plugin history', 'memberpress-ai-assistant')
            ],
            'integration' => [
                'name' => __('Integration Tests', 'memberpress-ai-assistant'),
                'description' => __('Tests for integration with WordPress and external systems', 'memberpress-ai-assistant')
            ]
        ];
    }

    /**
     * Get available diagnostic tests
     * 
     * @return array Available tests
     */
    private function get_available_tests() {
        return [
            'system_info' => [
                'name' => __('System Information', 'memberpress-ai-assistant'),
                'description' => __('Get detailed information about your WordPress and PHP environment', 'memberpress-ai-assistant'),
                'category' => 'core',
                'callback' => [$this, 'test_system_info'],
            ],
            'openai_connection' => [
                'name' => __('OpenAI API Connection', 'memberpress-ai-assistant'),
                'description' => __('Tests the connection to OpenAI API.', 'memberpress-ai-assistant'),
                'category' => 'api',
                'callback' => [$this, 'test_openai_connection'],
            ],
            'anthropic_connection' => [
                'name' => __('Anthropic API Connection', 'memberpress-ai-assistant'),
                'description' => __('Tests the connection to Anthropic API.', 'memberpress-ai-assistant'),
                'category' => 'api',
                'callback' => [$this, 'test_anthropic_connection'],
            ],
            'memberpress_detection' => [
                'name' => __('MemberPress Detection', 'memberpress-ai-assistant'),
                'description' => __('Verifies that MemberPress is properly detected.', 'memberpress-ai-assistant'),
                'category' => 'integration',
                'callback' => [$this, 'test_memberpress_detection'],
            ],
            'error_recovery' => [
                'name' => __('Error Recovery System', 'memberpress-ai-assistant'),
                'description' => __('Tests the Error Recovery System functionality.', 'memberpress-ai-assistant'),
                'category' => 'core',
                'callback' => [$this, 'test_error_recovery'],
                'direct_url' => 'test/test-error-recovery-page.php',
            ],
            'console_logging' => [
                'name' => __('Console Logging System', 'memberpress-ai-assistant'),
                'description' => __('Tests the Console Logging System functionality.', 'memberpress-ai-assistant'),
                'category' => 'core',
                'callback' => [$this, 'test_console_logging'],
            ],
            'ajax_communication' => [
                'name' => __('AJAX Communication', 'memberpress-ai-assistant'),
                'description' => __('Tests AJAX communication between browser and server.', 'memberpress-ai-assistant'),
                'category' => 'core',
                'callback' => [$this, 'test_ajax_communication'],
            ],
            'nonce_verification' => [
                'name' => __('Nonce Verification', 'memberpress-ai-assistant'),
                'description' => __('Tests WordPress nonce verification functionality.', 'memberpress-ai-assistant'),
                'category' => 'core',
                'callback' => [$this, 'test_nonce_verification'],
            ],
            'system_cache' => [
                'name' => __('System Cache', 'memberpress-ai-assistant'),
                'description' => __('Tests the System Cache functionality for improved performance.', 'memberpress-ai-assistant'),
                'category' => 'core',
                'callback' => [$this, 'test_system_cache'],
                'direct_url' => 'test/test-system-cache.php',
            ],
            'wp_cli_tool' => [
                'name' => __('WP-CLI Tool', 'memberpress-ai-assistant'),
                'description' => __('Tests the WP-CLI tool functionality.', 'memberpress-ai-assistant'),
                'category' => 'tools',
                'callback' => [$this, 'test_wp_cli_tool'],
            ],
            'plugin_logs_tool' => [
                'name' => __('Plugin Logs Tool', 'memberpress-ai-assistant'),
                'description' => __('Tests the Plugin Logs tool functionality.', 'memberpress-ai-assistant'),
                'category' => 'tools',
                'callback' => [$this, 'test_plugin_logs_tool'],
            ],
            'active_plugins' => [
                'name' => __('Active Plugins', 'memberpress-ai-assistant'),
                'description' => __('Displays a list of active plugins on the site.', 'memberpress-ai-assistant'),
                'category' => 'plugins',
                'callback' => [$this, 'test_active_plugins'],
            ],
            'plugin_history' => [
                'name' => __('Plugin History', 'memberpress-ai-assistant'),
                'description' => __('Shows recent plugin installation, activation, deactivation, and update history.', 'memberpress-ai-assistant'),
                'category' => 'plugins',
                'callback' => [$this, 'test_plugin_history'],
            ]
        ];
    }

    /**
     * Get tests by category
     * 
     * @param string $category Category ID
     * @return array Tests in category
     */
    private function get_tests_by_category($category) {
        $all_tests = $this->get_available_tests();
        $tests = [];
        
        foreach ($all_tests as $test_id => $test) {
            if ($test['category'] === $category) {
                $tests[$test_id] = $test;
            }
        }
        
        return $tests;
    }

    /**
     * Handle AJAX request to run a single diagnostic test
     */
    public function handle_ajax_run_test() {
        // Check nonce for security
        check_ajax_referer('mpai_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized access', 'memberpress-ai-assistant')]);
            return;
        }
        
        $test_id = isset($_POST['test_id']) ? sanitize_key($_POST['test_id']) : '';
        if (empty($test_id)) {
            wp_send_json_error(['message' => __('Missing test ID', 'memberpress-ai-assistant')]);
            return;
        }
        
        $tests = $this->get_available_tests();
        if (!isset($tests[$test_id])) {
            wp_send_json_error(['message' => __('Invalid test ID', 'memberpress-ai-assistant')]);
            return;
        }
        
        $test = $tests[$test_id];
        if (!isset($test['callback']) || !is_callable($test['callback'])) {
            wp_send_json_error(['message' => __('Test callback not defined', 'memberpress-ai-assistant')]);
            return;
        }
        
        try {
            // Enhanced error logging for debugging
            mpai_log_debug('Running diagnostic test: ' . $test_id, 'diagnostics');
            
            // Start timing
            $start_time = microtime(true);
            
            // Run the test
            $result = call_user_func($test['callback']);
            
            // End timing
            $end_time = microtime(true);
            $total_time = $end_time - $start_time;
            
            // Add timing data
            $result['timing'] = [
                'start' => $start_time,
                'end' => $end_time,
                'total' => $total_time,
            ];
            
            // Add test metadata
            $result['test_id'] = $test_id;
            $result['test_name'] = $test['name'];
            
            mpai_log_debug('Test completed successfully: ' . $test_id, 'diagnostics');
            wp_send_json_success($result);
        } catch (\Throwable $e) {
            mpai_log_error('Error running test ' . $test_id . ': ' . $e->getMessage(), 'diagnostics', array('file' => $e->getFile(), 'line' => $e->getLine()));
            // Error trace is now included in the context array
            
            wp_send_json_error([
                'message' => __('Error running test:', 'memberpress-ai-assistant') . ' ' . $e->getMessage(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'test_id' => $test_id,
                'test_name' => $test['name'],
            ]);
        }
    }
    
    /**
     * Handle AJAX request to run all tests in a category
     */
    public function handle_ajax_run_category_tests() {
        // Check nonce for security
        check_ajax_referer('mpai_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized access', 'memberpress-ai-assistant')]);
            return;
        }
        
        $category_id = isset($_POST['category_id']) ? sanitize_key($_POST['category_id']) : '';
        if (empty($category_id)) {
            wp_send_json_error(['message' => __('Missing category ID', 'memberpress-ai-assistant')]);
            return;
        }
        
        $categories = $this->get_test_categories();
        if (!isset($categories[$category_id])) {
            wp_send_json_error(['message' => __('Invalid category ID', 'memberpress-ai-assistant')]);
            return;
        }
        
        // Log the start of category tests
        mpai_log_debug('Running all tests in category: ' . $category_id, 'diagnostics');
        
        // Get all tests in this category
        $tests = $this->get_tests_by_category($category_id);
        
        $results = [];
        foreach ($tests as $test_id => $test) {
            if (isset($test['callback']) && is_callable($test['callback'])) {
                try {
                    // Log the test being run
                    mpai_log_debug('Running test in category ' . $category_id . ': ' . $test_id, 'diagnostics');
                    
                    // Start timing
                    $start_time = microtime(true);
                    
                    // Run the test
                    $result = call_user_func($test['callback']);
                    
                    // End timing
                    $end_time = microtime(true);
                    $total_time = $end_time - $start_time;
                    
                    // Add timing data
                    $result['timing'] = [
                        'start' => $start_time,
                        'end' => $end_time,
                        'total' => $total_time,
                    ];
                    
                    // Add test metadata
                    $result['test_id'] = $test_id;
                    $result['test_name'] = $test['name'];
                    
                    $results[$test_id] = $result;
                    mpai_log_debug('Test completed successfully: ' . $test_id, 'diagnostics');
                } catch (\Throwable $e) {
                    mpai_log_error('Error running test ' . $test_id . ' in category ' . $category_id . ': ' . $e->getMessage(), 'diagnostics', array('file' => $e->getFile(), 'line' => $e->getLine(), 'trace' => $e->getTraceAsString()));
                    // Error trace is now included in the context array
                    
                    $results[$test_id] = [
                        'success' => false,
                        'message' => __('Error running test:', 'memberpress-ai-assistant') . ' ' . $e->getMessage(),
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString(),
                        'test_id' => $test_id,
                        'test_name' => $test['name'],
                    ];
                }
            }
        }
        
        mpai_log_debug('Completed all tests in category: ' . $category_id, 'diagnostics');
        wp_send_json_success([
            'category_id' => $category_id,
            'category_name' => $categories[$category_id]['name'],
            'results' => $results
        ]);
    }
    
    /**
     * Handle AJAX request to run all diagnostic tests
     */
    public function handle_ajax_run_all_tests() {
        // Check nonce for security
        check_ajax_referer('mpai_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized access', 'memberpress-ai-assistant')]);
            return;
        }
        
        mpai_log_debug('Starting to run all diagnostic tests', 'diagnostics');
        
        $tests = $this->get_available_tests();
        $results = [];
        
        foreach ($tests as $test_id => $test) {
            if (isset($test['callback']) && is_callable($test['callback'])) {
                try {
                    // Log the test being run
                    mpai_log_debug('Running test: ' . $test_id, 'diagnostics');
                    
                    // Start timing
                    $start_time = microtime(true);
                    
                    // Run the test
                    $result = call_user_func($test['callback']);
                    
                    // End timing
                    $end_time = microtime(true);
                    $total_time = $end_time - $start_time;
                    
                    // Add timing data
                    $result['timing'] = [
                        'start' => $start_time,
                        'end' => $end_time,
                        'total' => $total_time,
                    ];
                    
                    // Add test metadata
                    $result['test_id'] = $test_id;
                    $result['test_name'] = $test['name'];
                    $result['category'] = $test['category'];
                    
                    $results[$test_id] = $result;
                    mpai_log_debug('Test completed successfully: ' . $test_id, 'diagnostics');
                } catch (\Throwable $e) {
                    mpai_log_error('Error running test ' . $test_id . ': ' . $e->getMessage(), 'diagnostics', array('file' => $e->getFile(), 'line' => $e->getLine()));
                    // Error trace is now included in the context array
                    
                    $results[$test_id] = [
                        'success' => false,
                        'message' => __('Error running test:', 'memberpress-ai-assistant') . ' ' . $e->getMessage(),
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString(),
                        'test_id' => $test_id,
                        'test_name' => $test['name'],
                        'category' => $test['category'],
                    ];
                }
            }
        }
        
        mpai_log_debug('All tests completed, grouping results by category', 'diagnostics');
        
        // Group results by category
        $grouped_results = [];
        $categories = $this->get_test_categories();
        
        foreach ($categories as $category_id => $category) {
            $grouped_results[$category_id] = [
                'name' => $category['name'],
                'description' => $category['description'],
                'results' => [],
                'success_count' => 0,
                'fail_count' => 0,
                'warning_count' => 0,
            ];
        }
        
        foreach ($results as $test_id => $result) {
            $category = $result['category'];
            $grouped_results[$category]['results'][$test_id] = $result;
            
            // Increment success/fail counters
            if (!isset($result['success']) || $result['success'] === false) {
                $grouped_results[$category]['fail_count']++;
            } else if (isset($result['status']) && $result['status'] === 'warning') {
                $grouped_results[$category]['warning_count']++;
            } else {
                $grouped_results[$category]['success_count']++;
            }
        }
        
        mpai_log_debug('Sending test results to client', 'diagnostics');
        wp_send_json_success([
            'all_results' => $results,
            'grouped_results' => $grouped_results,
        ]);
    }

    /**
     * Handle AJAX request to test error recovery
     */
    public function handle_test_error_recovery() {
        check_ajax_referer('mpai_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized access');
            return;
        }
        
        try {
            // Include the test script
            $test_file = MPAI_PLUGIN_DIR . 'test/test-error-recovery.php';
            if (file_exists($test_file)) {
                require_once($test_file);
                
                if (function_exists('mpai_test_error_recovery')) {
                    $results = mpai_test_error_recovery();
                    wp_send_json($results);
                } else {
                    wp_send_json_error([
                        'message' => 'Error recovery test function not found',
                        'success' => false
                    ]);
                }
            } else {
                wp_send_json_error([
                    'message' => 'Error recovery test file not found at: ' . $test_file,
                    'success' => false
                ]);
            }
        } catch (Exception $e) {
            wp_send_json_error([
                'message' => 'Error running tests: ' . $e->getMessage(),
                'success' => false
            ]);
        }
    }

    /**
     * Test system information
     * 
     * @return array Test result
     */
    public function test_system_info() {
        // Get system information
        $sys_info = $this->get_system_info();
        
        // Check for critical issues
        $critical_issues = [];
        $warnings = [];
        
        // Check PHP version
        $php_version = phpversion();
        if (version_compare($php_version, '7.4', '<')) {
            $critical_issues[] = sprintf(
                __('PHP version %s is below the recommended minimum of 7.4', 'memberpress-ai-assistant'),
                $php_version
            );
        } else if (version_compare($php_version, '8.0', '<')) {
            $warnings[] = sprintf(
                __('PHP version %s is supported, but PHP 8.0 or higher is recommended for best performance', 'memberpress-ai-assistant'),
                $php_version
            );
        }
        
        // Check for cURL
        if (!function_exists('curl_version')) {
            $critical_issues[] = __('cURL is not available but is required for API communication', 'memberpress-ai-assistant');
        }
        
        // Check memory limit
        $memory_limit = ini_get('memory_limit');
        $memory_limit_bytes = $this->return_bytes($memory_limit);
        if ($memory_limit_bytes < 64 * 1024 * 1024) { // 64MB
            $critical_issues[] = sprintf(
                __('Memory limit %s is below the recommended minimum of 64MB', 'memberpress-ai-assistant'),
                $memory_limit
            );
        } else if ($memory_limit_bytes < 128 * 1024 * 1024) { // 128MB
            $warnings[] = sprintf(
                __('Memory limit %s is acceptable, but 128MB or higher is recommended for optimal operation', 'memberpress-ai-assistant'),
                $memory_limit
            );
        }
        
        // Check max execution time
        $max_execution_time = ini_get('max_execution_time');
        if ($max_execution_time > 0 && $max_execution_time < 30) {
            $warnings[] = sprintf(
                __('Max execution time %s seconds is low, 30 seconds or higher is recommended for API operations', 'memberpress-ai-assistant'),
                $max_execution_time
            );
        }
        
        // Check API keys
        $openai_api_key = get_option('mpai_api_key', '');
        $anthropic_api_key = get_option('mpai_anthropic_api_key', '');
        $primary_api = get_option('mpai_primary_api', 'openai');
        
        if (empty($openai_api_key) && empty($anthropic_api_key)) {
            $critical_issues[] = __('No API keys configured for OpenAI or Anthropic', 'memberpress-ai-assistant');
        } else if ($primary_api === 'openai' && empty($openai_api_key)) {
            $critical_issues[] = __('OpenAI is set as primary provider but no API key is configured', 'memberpress-ai-assistant');
        } else if ($primary_api === 'anthropic' && empty($anthropic_api_key)) {
            $critical_issues[] = __('Anthropic is set as primary provider but no API key is configured', 'memberpress-ai-assistant');
        }
        
        // Determine overall status
        $success = empty($critical_issues);
        $status = empty($critical_issues) ? (empty($warnings) ? 'success' : 'warning') : 'error';
        
        return [
            'success' => $success,
            'status' => $status,
            'message' => $success 
                ? (empty($warnings) 
                    ? __('System information looks good!', 'memberpress-ai-assistant') 
                    : __('System information has some warnings but no critical issues.', 'memberpress-ai-assistant'))
                : __('System information check found critical issues that need attention.', 'memberpress-ai-assistant'),
            'critical_issues' => $critical_issues,
            'warnings' => $warnings,
            'system_info' => $sys_info,
        ];
    }
    
    /**
     * Convert PHP ini size value to bytes
     * 
     * @param string $val Size value (e.g. 128M, 1G)
     * @return int Size in bytes
     */
    private function return_bytes($val) {
        $val = trim($val);
        $last = strtolower($val[strlen($val)-1]);
        $val = (int)$val;
        
        switch($last) {
            case 'g':
                $val *= 1024;
            case 'm':
                $val *= 1024;
            case 'k':
                $val *= 1024;
        }
        
        return $val;
    }

    /**
     * Test OpenAI connection
     * 
     * @return array Test result
     */
    public function test_openai_connection() {
        $api_key = get_option('mpai_api_key', '');
        
        if (empty($api_key)) {
            return [
                'success' => false,
                'status' => 'warning',
                'message' => __('OpenAI API key not configured.', 'memberpress-ai-assistant'),
                'details' => [
                    'api_key_configured' => false
                ]
            ];
        }
        
        // Load OpenAI class if needed
        if (!class_exists('MPAI_OpenAI')) {
            require_once MPAI_PLUGIN_DIR . 'includes/class-mpai-openai.php';
        }
        
        try {
            $openai = new MPAI_OpenAI();
            $model = get_option('mpai_model', 'gpt-4o');
            
            // Make a simple completion request
            $messages = [
                ['role' => 'system', 'content' => 'You are a system diagnostic tool.'],
                ['role' => 'user', 'content' => 'Respond with "Connection successful" if you receive this message.']
            ];
            $response = $openai->generate_chat_completion($messages);
            
            if (empty($response)) {
                return [
                    'success' => false,
                    'status' => 'error',
                    'message' => __('No response received from OpenAI.', 'memberpress-ai-assistant'),
                    'details' => [
                        'api_key_configured' => true,
                        'error' => 'Empty response'
                    ]
                ];
            }
            
            return [
                'success' => true,
                'status' => 'success',
                'message' => __('Successfully connected to OpenAI API.', 'memberpress-ai-assistant'),
                'details' => [
                    'api_key_configured' => true,
                    'model_used' => $model,
                    'response' => $response
                ]
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'status' => 'error',
                'message' => __('Error connecting to OpenAI API:', 'memberpress-ai-assistant') . ' ' . $e->getMessage(),
                'details' => [
                    'api_key_configured' => true,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]
            ];
        }
    }

    /**
     * Test Anthropic connection
     * 
     * @return array Test result
     */
    public function test_anthropic_connection() {
        $api_key = get_option('mpai_anthropic_api_key', '');
        
        if (empty($api_key)) {
            return [
                'success' => false,
                'status' => 'warning',
                'message' => __('Anthropic API key not configured.', 'memberpress-ai-assistant'),
                'details' => [
                    'api_key_configured' => false
                ]
            ];
        }
        
        // Load Anthropic class if needed
        if (!class_exists('MPAI_Anthropic')) {
            require_once MPAI_PLUGIN_DIR . 'includes/class-mpai-anthropic.php';
        }
        
        try {
            $anthropic = new MPAI_Anthropic();
            $model = get_option('mpai_anthropic_model', 'claude-3-opus-20240229');
            
            // Make a simple completion request
            $messages = [
                ['role' => 'system', 'content' => 'You are a system diagnostic tool.'],
                ['role' => 'user', 'content' => 'Respond with "Connection successful" if you receive this message.']
            ];
            $response = $anthropic->generate_completion($messages);
            
            if (empty($response)) {
                return [
                    'success' => false,
                    'status' => 'error',
                    'message' => __('No response received from Anthropic.', 'memberpress-ai-assistant'),
                    'details' => [
                        'api_key_configured' => true,
                        'error' => 'Empty response'
                    ]
                ];
            }
            
            return [
                'success' => true,
                'status' => 'success',
                'message' => __('Successfully connected to Anthropic API.', 'memberpress-ai-assistant'),
                'details' => [
                    'api_key_configured' => true,
                    'model_used' => $model,
                    'response' => $response
                ]
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'status' => 'error',
                'message' => __('Error connecting to Anthropic API:', 'memberpress-ai-assistant') . ' ' . $e->getMessage(),
                'details' => [
                    'api_key_configured' => true,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]
            ];
        }
    }

    /**
     * Test MemberPress detection
     * 
     * @return array Test result
     */
    public function test_memberpress_detection() {
        // Check for MemberPress class definitions
        $has_memberpress = false;
        $memberpress_classes = [
            'MeprAppCtrl',
            'MeprOptions',
            'MeprUser',
            'MeprProduct',
            'MeprTransaction',
            'MeprSubscription'
        ];
        
        $detected_classes = [];
        
        foreach ($memberpress_classes as $class) {
            if (class_exists($class)) {
                $has_memberpress = true;
                $detected_classes[] = $class;
            }
        }
        
        // Check for MemberPress constants
        $memberpress_constants = [
            'MEPR_VERSION',
            'MEPR_PLUGIN_NAME',
            'MEPR_PATH',
            'MEPR_URL'
        ];
        
        $detected_constants = [];
        
        foreach ($memberpress_constants as $constant) {
            if (defined($constant)) {
                $has_memberpress = true;
                $detected_constants[] = $constant;
            }
        }
        
        // Check if the plugin is active
        $plugin_active = false;
        if (function_exists('is_plugin_active')) {
            $plugin_active = is_plugin_active('memberpress/memberpress.php');
            if ($plugin_active) {
                $has_memberpress = true;
            }
        }
        
        if (!$has_memberpress) {
            return [
                'success' => false,
                'status' => 'warning',
                'message' => __('MemberPress is not detected.', 'memberpress-ai-assistant'),
                'details' => [
                    'memberpress_detected' => false,
                    'plugin_active' => $plugin_active,
                    'detected_classes' => $detected_classes,
                    'detected_constants' => $detected_constants
                ]
            ];
        }
        
        return [
            'success' => true,
            'status' => 'success',
            'message' => __('MemberPress is properly detected.', 'memberpress-ai-assistant'),
            'details' => [
                'memberpress_detected' => true,
                'plugin_active' => $plugin_active,
                'detected_classes' => $detected_classes,
                'detected_constants' => $detected_constants
            ]
        ];
    }

    /**
     * Test error recovery system
     * 
     * @return array Test result
     */
    public function test_error_recovery() {
        // Check if error recovery class exists
        if (!class_exists('MPAI_Error_Recovery')) {
            $error_recovery_file = MPAI_PLUGIN_DIR . 'includes/class-mpai-error-recovery.php';
            
            if (!file_exists($error_recovery_file)) {
                return [
                    'success' => false,
                    'status' => 'error',
                    'message' => __('Error Recovery System file not found.', 'memberpress-ai-assistant'),
                    'details' => [
                        'file_path' => $error_recovery_file,
                        'file_exists' => false
                    ]
                ];
            }
            
            // Try to include the file
            require_once $error_recovery_file;
            
            if (!class_exists('MPAI_Error_Recovery')) {
                return [
                    'success' => false,
                    'status' => 'error',
                    'message' => __('Error Recovery System class not found after loading file.', 'memberpress-ai-assistant'),
                    'details' => [
                        'file_path' => $error_recovery_file,
                        'file_exists' => true,
                        'class_exists' => false
                    ]
                ];
            }
        }
        
        // Make sure plugin logger is also loaded
        if (!class_exists('MPAI_Plugin_Logger')) {
            $plugin_logger_file = MPAI_PLUGIN_DIR . 'includes/class-mpai-plugin-logger.php';
            if (file_exists($plugin_logger_file)) {
                require_once $plugin_logger_file;
            }
        }
        
        // Check for test file
        $test_file = MPAI_PLUGIN_DIR . 'test/test-error-recovery.php';
        
        if (!file_exists($test_file)) {
            return [
                'success' => false,
                'status' => 'error',
                'message' => __('Error Recovery test file not found.', 'memberpress-ai-assistant'),
                'details' => [
                    'test_file_path' => $test_file,
                    'test_file_exists' => false
                ]
            ];
        }
        
        // Include test file and run tests
        require_once $test_file;
        
        if (!function_exists('mpai_test_error_recovery')) {
            return [
                'success' => false,
                'status' => 'error',
                'message' => __('Error Recovery test function not found.', 'memberpress-ai-assistant'),
                'details' => [
                    'test_file_path' => $test_file,
                    'test_file_exists' => true,
                    'function_exists' => false
                ]
            ];
        }
        
        // Run the tests
        try {
            mpai_log_debug('Running comprehensive error recovery tests', 'diagnostics');
            $result = mpai_test_error_recovery();
            
            // Format test results 
            $test_summary = [];
            $failed_tests = [];
            $passed_tests = [];
            
            if (isset($result['data']['tests'])) {
                foreach ($result['data']['tests'] as $test_name => $test_data) {
                    if (isset($test_data['success']) && $test_data['success']) {
                        $passed_tests[] = $test_name;
                    } else {
                        $failed_tests[] = $test_name;
                    }
                    
                    $test_summary[] = [
                        'name' => ucfirst(str_replace('_', ' ', $test_name)),
                        'success' => isset($test_data['success']) ? $test_data['success'] : false,
                        'message' => isset($test_data['message']) ? $test_data['message'] : 'No test message available'
                    ];
                }
            }
            
            // Determine overall status and message
            $status = $result['success'] ? 'success' : ($passed_tests ? 'warning' : 'error');
            $message = '';
            
            if (count($failed_tests) === 0) {
                $message = __('All Error Recovery System tests passed successfully!', 'memberpress-ai-assistant');
            } else {
                $message = sprintf(
                    __('%d of %d Error Recovery System tests failed: %s', 'memberpress-ai-assistant'),
                    count($failed_tests),
                    count($passed_tests) + count($failed_tests),
                    implode(', ', array_map(function($test) {
                        return ucfirst(str_replace('_', ' ', $test));
                    }, $failed_tests))
                );
            }
            
            return [
                'success' => $result['success'],
                'status' => $status,
                'message' => $message,
                'details' => [
                    'test_summary' => $test_summary,
                    'passed_tests' => $passed_tests,
                    'failed_tests' => $failed_tests,
                    'raw_result' => $result
                ]
            ];
        } catch (Exception $e) {
            mpai_log_error('Error running error recovery tests: ' . $e->getMessage(), 'diagnostics', array('file' => $e->getFile(), 'line' => $e->getLine(), 'trace' => $e->getTraceAsString()));
            return [
                'success' => false,
                'status' => 'error',
                'message' => __('Error running Error Recovery tests:', 'memberpress-ai-assistant') . ' ' . $e->getMessage(),
                'details' => [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]
            ];
        }
    }

    /**
     * Test console logging system
     * 
     * @return array Test result
     */
    public function test_console_logging() {
        // Check console logging configuration
        $enabled = get_option('mpai_enable_console_logging', '0') === '1';
        $log_level = get_option('mpai_console_log_level', 'info');
        $log_api_calls = get_option('mpai_log_api_calls', '0') === '1';
        $log_tool_usage = get_option('mpai_log_tool_usage', '0') === '1';
        $log_agent_activity = get_option('mpai_log_agent_activity', '0') === '1';
        $log_timing = get_option('mpai_log_timing', '0') === '1';
        
        // Check if the logger script exists
        $logger_file = MPAI_PLUGIN_URL . 'assets/js/mpai-logger.js';
        $logger_file_path = MPAI_PLUGIN_DIR . 'assets/js/mpai-logger.js';
        
        $file_exists = file_exists($logger_file_path);
        
        if (!$file_exists) {
            return [
                'success' => false,
                'status' => 'error',
                'message' => __('Console logging system script not found.', 'memberpress-ai-assistant'),
                'details' => [
                    'enabled' => $enabled,
                    'log_level' => $log_level,
                    'categories' => [
                        'api_calls' => $log_api_calls,
                        'tool_usage' => $log_tool_usage,
                        'agent_activity' => $log_agent_activity,
                        'timing' => $log_timing
                    ],
                    'logger_file' => $logger_file,
                    'file_exists' => $file_exists
                ]
            ];
        }
        
        // If logging is disabled, return warning
        if (!$enabled) {
            return [
                'success' => true,
                'status' => 'warning',
                'message' => __('Console logging system is available but disabled.', 'memberpress-ai-assistant'),
                'details' => [
                    'enabled' => $enabled,
                    'log_level' => $log_level,
                    'categories' => [
                        'api_calls' => $log_api_calls,
                        'tool_usage' => $log_tool_usage,
                        'agent_activity' => $log_agent_activity,
                        'timing' => $log_timing
                    ],
                    'logger_file' => $logger_file,
                    'file_exists' => $file_exists
                ]
            ];
        }
        
        return [
            'success' => true,
            'status' => 'success',
            'message' => __('Console logging system is properly configured.', 'memberpress-ai-assistant'),
            'details' => [
                'enabled' => $enabled,
                'log_level' => $log_level,
                'categories' => [
                    'api_calls' => $log_api_calls,
                    'tool_usage' => $log_tool_usage,
                    'agent_activity' => $log_agent_activity,
                    'timing' => $log_timing
                ],
                'logger_file' => $logger_file,
                'file_exists' => $file_exists
            ]
        ];
    }
    
    /**
     * Test WP-CLI tool
     * 
     * @return array Test result
     */
    public function test_wp_cli_tool() {
        // Check if WP-CLI tool class exists
        if (!class_exists('MPAI_WPCLI_Tool')) {
            $wpcli_tool_file = MPAI_PLUGIN_DIR . 'includes/tools/implementations/class-mpai-wpcli-tool.php';
            
            if (!file_exists($wpcli_tool_file)) {
                return [
                    'success' => false,
                    'status' => 'error',
                    'message' => __('WP-CLI Tool file not found.', 'memberpress-ai-assistant'),
                    'details' => [
                        'file_path' => $wpcli_tool_file,
                        'file_exists' => false
                    ]
                ];
            }
        }
        
        // Check if the WP-CLI tool is registered in the tool registry
        $tool_registered = false;
        $registry_output = '';
        
        if (class_exists('MPAI_Tool_Registry')) {
            $registry = new MPAI_Tool_Registry();
            $available_tools = $registry->get_available_tools();
            
            // Check if wp_cli or wpcli is in the available tools
            if (isset($available_tools['wp_cli']) || isset($available_tools['wpcli'])) {
                $tool_registered = true;
                $registry_output = isset($available_tools['wpcli']) ? $available_tools['wpcli'] : $available_tools['wp_cli'];
            }
        }
        
        // WP-CLI is always enabled now (settings were removed from UI)
        $wpcli_enabled = true;
        mpai_log_debug('WP-CLI is always enabled in diagnostics', 'diagnostics');
        
        if (!$tool_registered) {
            return [
                'success' => false,
                'status' => 'error',
                'message' => __('WP-CLI Tool is not registered in the tool registry.', 'memberpress-ai-assistant'),
                'details' => [
                    'tool_registered' => $tool_registered,
                    'tool_enabled' => $wpcli_enabled
                ]
            ];
        }
        
        return [
            'success' => true,
            'status' => 'success',
            'message' => __('WP-CLI Tool is properly configured.', 'memberpress-ai-assistant'),
            'details' => [
                'tool_registered' => $tool_registered,
                'tool_enabled' => $wpcli_enabled,
                'registry_info' => $registry_output
            ]
        ];
    }
    
    /**
     * Test Plugin Logs tool
     * 
     * @return array Test result
     */
    public function test_plugin_logs_tool() {
        // Check if Plugin Logs tool class exists
        if (!class_exists('MPAI_Plugin_Logs_Tool')) {
            $plugin_logs_tool_file = MPAI_PLUGIN_DIR . 'includes/tools/implementations/class-mpai-plugin-logs-tool.php';
            
            if (!file_exists($plugin_logs_tool_file)) {
                return [
                    'success' => false,
                    'status' => 'error',
                    'message' => __('Plugin Logs Tool file not found.', 'memberpress-ai-assistant'),
                    'details' => [
                        'file_path' => $plugin_logs_tool_file,
                        'file_exists' => false
                    ]
                ];
            }
            
            // Try to include the file
            require_once $plugin_logs_tool_file;
        }
        
        // Check if the Plugin Logs tool is registered in the tool registry
        $tool_registered = false;
        $registry_output = '';
        
        if (class_exists('MPAI_Tool_Registry')) {
            $registry = new MPAI_Tool_Registry();
            $available_tools = $registry->get_available_tools();
            
            // Check if plugin_logs is in the available tools
            if (isset($available_tools['plugin_logs'])) {
                $tool_registered = true;
                $registry_output = $available_tools['plugin_logs'];
            }
        }
        
        // Plugin Logs is always enabled now (settings were removed from UI)
        $plugin_logs_enabled = true;
        mpai_log_debug('Plugin Logs is always enabled in diagnostics', 'diagnostics');
        
        if (!$tool_registered) {
            return [
                'success' => false,
                'status' => 'error',
                'message' => __('Plugin Logs Tool is not registered in the tool registry.', 'memberpress-ai-assistant'),
                'details' => [
                    'tool_registered' => $tool_registered,
                    'tool_enabled' => $plugin_logs_enabled
                ]
            ];
        }
        
        // Check if the plugin logger class exists
        if (!class_exists('MPAI_Plugin_Logger')) {
            $plugin_logger_file = MPAI_PLUGIN_DIR . 'includes/class-mpai-plugin-logger.php';
            
            if (file_exists($plugin_logger_file)) {
                // Try to include the file
                require_once $plugin_logger_file;
            } else {
                return [
                    'success' => false,
                    'status' => 'error',
                    'message' => __('Plugin Logger class file not found.', 'memberpress-ai-assistant'),
                    'details' => [
                        'file_path' => $plugin_logger_file,
                        'file_exists' => false
                    ]
                ];
            }
        }
        
        // Check if the plugin logger is initialized
        $plugin_logger_initialized = function_exists('mpai_init_plugin_logger');
        
        if (!$plugin_logger_initialized) {
            return [
                'success' => false,
                'status' => 'error',
                'message' => __('Plugin Logger is not initialized.', 'memberpress-ai-assistant'),
                'details' => [
                    'tool_registered' => $tool_registered,
                    'tool_enabled' => $plugin_logs_enabled,
                    'logger_initialized' => $plugin_logger_initialized
                ]
            ];
        }
        
        // Try initializing the plugin logger to verify it works
        try {
            $logger = mpai_init_plugin_logger();
            $logger_working = ($logger !== null);
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'status' => 'error',
                'message' => __('Error initializing Plugin Logger:', 'memberpress-ai-assistant') . ' ' . $e->getMessage(),
                'details' => [
                    'tool_registered' => $tool_registered,
                    'tool_enabled' => $plugin_logs_enabled,
                    'logger_initialized' => $plugin_logger_initialized,
                    'logger_working' => false,
                    'error' => $e->getMessage()
                ]
            ];
        }
        
        return [
            'success' => true,
            'status' => 'success',
            'message' => __('Plugin Logs Tool is properly configured.', 'memberpress-ai-assistant'),
            'details' => [
                'tool_registered' => $tool_registered,
                'tool_enabled' => $plugin_logs_enabled,
                'logger_initialized' => $plugin_logger_initialized,
                'logger_working' => $logger_working ?? true,
                'registry_info' => $registry_output
            ]
        ];
    }
    
    /**
     * Test Active Plugins
     * 
     * @return array Test result
     */
    public function test_active_plugins() {
        // Check for WordPress function
        if (!function_exists('get_plugins')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        
        // Get all plugins
        $all_plugins = get_plugins();
        
        // Check for active plugins function
        if (!function_exists('is_plugin_active')) {
            include_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        
        // Categorize plugins as active or inactive
        $active_plugins = [];
        $inactive_plugins = [];
        
        foreach ($all_plugins as $plugin_path => $plugin_data) {
            $is_active = is_plugin_active($plugin_path);
            $plugin_info = [
                'name' => $plugin_data['Name'],
                'version' => $plugin_data['Version'],
                'description' => $plugin_data['Description'],
                'author' => $plugin_data['Author'],
                'plugin_uri' => $plugin_data['PluginURI'],
                'path' => $plugin_path,
                'slug' => dirname($plugin_path),
                'network_active' => is_plugin_active_for_network($plugin_path),
            ];
            
            if ($is_active) {
                $active_plugins[$plugin_path] = $plugin_info;
            } else {
                $inactive_plugins[$plugin_path] = $plugin_info;
            }
        }
        
        // Sort plugins by name
        uasort($active_plugins, function($a, $b) {
            return strcasecmp($a['name'], $b['name']);
        });
        
        uasort($inactive_plugins, function($a, $b) {
            return strcasecmp($a['name'], $b['name']);
        });
        
        // Create HTML table of active plugins
        $active_plugins_html = '<table class="widefat mpai-plugins-table">';
        $active_plugins_html .= '<thead>';
        $active_plugins_html .= '<tr>';
        $active_plugins_html .= '<th>' . __('Plugin Name', 'memberpress-ai-assistant') . '</th>';
        $active_plugins_html .= '<th>' . __('Version', 'memberpress-ai-assistant') . '</th>';
        $active_plugins_html .= '<th>' . __('Author', 'memberpress-ai-assistant') . '</th>';
        $active_plugins_html .= '</tr>';
        $active_plugins_html .= '</thead>';
        $active_plugins_html .= '<tbody>';
        
        foreach ($active_plugins as $plugin_info) {
            $active_plugins_html .= '<tr>';
            $active_plugins_html .= '<td><strong>' . esc_html($plugin_info['name']) . '</strong>';
            if (!empty($plugin_info['description'])) {
                $active_plugins_html .= '<br><span class="description">' . esc_html($plugin_info['description']) . '</span>';
            }
            $active_plugins_html .= '</td>';
            $active_plugins_html .= '<td>' . esc_html($plugin_info['version']) . '</td>';
            $active_plugins_html .= '<td>' . wp_kses_post($plugin_info['author']) . '</td>';
            $active_plugins_html .= '</tr>';
        }
        
        $active_plugins_html .= '</tbody>';
        $active_plugins_html .= '</table>';
        
        // Create HTML table of inactive plugins
        $inactive_plugins_html = '<h4>' . __('Inactive Plugins', 'memberpress-ai-assistant') . ' (' . count($inactive_plugins) . ')</h4>';
        $inactive_plugins_html .= '<table class="widefat mpai-plugins-table">';
        $inactive_plugins_html .= '<thead>';
        $inactive_plugins_html .= '<tr>';
        $inactive_plugins_html .= '<th>' . __('Plugin Name', 'memberpress-ai-assistant') . '</th>';
        $inactive_plugins_html .= '<th>' . __('Version', 'memberpress-ai-assistant') . '</th>';
        $inactive_plugins_html .= '<th>' . __('Author', 'memberpress-ai-assistant') . '</th>';
        $inactive_plugins_html .= '</tr>';
        $inactive_plugins_html .= '</thead>';
        $inactive_plugins_html .= '<tbody>';
        
        foreach ($inactive_plugins as $plugin_info) {
            $inactive_plugins_html .= '<tr>';
            $inactive_plugins_html .= '<td><strong>' . esc_html($plugin_info['name']) . '</strong>';
            if (!empty($plugin_info['description'])) {
                $inactive_plugins_html .= '<br><span class="description">' . esc_html($plugin_info['description']) . '</span>';
            }
            $inactive_plugins_html .= '</td>';
            $inactive_plugins_html .= '<td>' . esc_html($plugin_info['version']) . '</td>';
            $inactive_plugins_html .= '<td>' . wp_kses_post($plugin_info['author']) . '</td>';
            $inactive_plugins_html .= '</tr>';
        }
        
        $inactive_plugins_html .= '</tbody>';
        $inactive_plugins_html .= '</table>';
        
        // Generate summary data
        $total_plugins = count($all_plugins);
        $active_count = count($active_plugins);
        $inactive_count = count($inactive_plugins);
        $network_active_count = 0;
        
        foreach ($active_plugins as $plugin_info) {
            if ($plugin_info['network_active']) {
                $network_active_count++;
            }
        }
        
        return [
            'success' => true,
            'status' => 'success',
            'message' => sprintf(
                __('Found %d plugins (%d active, %d inactive).', 'memberpress-ai-assistant'),
                $total_plugins,
                $active_count,
                $inactive_count
            ),
            'html_content' => $active_plugins_html . $inactive_plugins_html,
            'details' => [
                'total_plugins' => $total_plugins,
                'active_plugins' => $active_count,
                'inactive_plugins' => $inactive_count,
                'network_active_plugins' => $network_active_count,
                'active_plugins_list' => array_values($active_plugins),
                'inactive_plugins_list' => array_values($inactive_plugins)
            ]
        ];
    }
    
    /**
     * Test Plugin History
     * 
     * @return array Test result
     */
    public function test_plugin_history() {
        // Check if the plugin logger class exists
        if (!class_exists('MPAI_Plugin_Logger')) {
            $plugin_logger_file = MPAI_PLUGIN_DIR . 'includes/class-mpai-plugin-logger.php';
            
            if (file_exists($plugin_logger_file)) {
                // Try to include the file
                require_once $plugin_logger_file;
            } else {
                return [
                    'success' => false,
                    'status' => 'error',
                    'message' => __('Plugin Logger class file not found.', 'memberpress-ai-assistant'),
                    'details' => [
                        'file_path' => $plugin_logger_file,
                        'file_exists' => false
                    ]
                ];
            }
        }
        
        // Check if the plugin logger is initialized
        if (!function_exists('mpai_init_plugin_logger')) {
            return [
                'success' => false,
                'status' => 'error',
                'message' => __('Plugin Logger is not initialized.', 'memberpress-ai-assistant'),
                'details' => [
                    'error' => 'Plugin logger initialization function not found'
                ]
            ];
        }
        
        try {
            // Initialize plugin logger
            $logger = mpai_init_plugin_logger();
            
            if (!$logger) {
                return [
                    'success' => false,
                    'status' => 'error',
                    'message' => __('Failed to initialize plugin logger.', 'memberpress-ai-assistant'),
                    'details' => [
                        'error' => 'Logger initialization returned null'
                    ]
                ];
            }
            
            // Get plugin activity summary
            $days = 30; // Default: show last 30 days
            $summary = $logger->get_activity_summary($days);
            
            // Get recent plugin logs
            $logs = $logger->get_logs([
                'limit' => 50,
                'orderby' => 'date_time',
                'order' => 'DESC'
            ]);
            
            // Check if we got any logs
            if (empty($logs) && isset($summary['is_fallback']) && $summary['is_fallback']) {
                // This is fallback data, show a warning
                return [
                    'success' => true,
                    'status' => 'warning',
                    'message' => __('Plugin logging database is not available or empty. Showing synthetic data.', 'memberpress-ai-assistant'),
                    'details' => [
                        'is_fallback' => true,
                        'summary' => $summary
                    ],
                    'html_content' => $this->generate_plugin_history_html($summary, $logs, true)
                ];
            } else if (empty($logs)) {
                // No logs found
                return [
                    'success' => true,
                    'status' => 'warning',
                    'message' => __('No plugin history logs found.', 'memberpress-ai-assistant'),
                    'details' => [
                        'summary' => $summary
                    ],
                    'html_content' => '<p>' . __('No plugin history logs found. Plugin actions will be recorded as they occur.', 'memberpress-ai-assistant') . '</p>'
                ];
            }
            
            // Success with data
            return [
                'success' => true,
                'status' => 'success',
                'message' => sprintf(
                    __('Found %d plugin history logs.', 'memberpress-ai-assistant'),
                    count($logs)
                ),
                'details' => [
                    'log_count' => count($logs),
                    'summary' => $summary,
                    'logs' => $logs 
                ],
                'html_content' => $this->generate_plugin_history_html($summary, $logs)
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'status' => 'error',
                'message' => __('Error retrieving plugin history:', 'memberpress-ai-assistant') . ' ' . $e->getMessage(),
                'details' => [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]
            ];
        }
    }
    
    /**
     * Generate HTML for plugin history display
     *
     * @param array $summary Activity summary data
     * @param array $logs Recent log entries
     * @param bool $is_fallback Whether this is fallback data
     * @return string HTML content
     */
    private function generate_plugin_history_html($summary, $logs, $is_fallback = false) {
        $html = '';
        
        // Add notice if this is fallback data
        if ($is_fallback) {
            $html .= '<div class="notice notice-warning inline"><p>' . 
                     __('Plugin logging database is not available or empty. Showing synthetic data for demonstration purposes.', 'memberpress-ai-assistant') . 
                     '</p></div>';
        }
        
        // Add summary section
        $html .= '<div class="mpai-plugin-history-summary">';
        $html .= '<h4>' . __('Activity Summary', 'memberpress-ai-assistant') . '</h4>';
        
        // Create activity counts table
        if (isset($summary['action_counts']) && !empty($summary['action_counts'])) {
            $html .= '<table class="widefat mpai-summary-table" style="width: auto; margin-bottom: 20px;">';
            $html .= '<thead>';
            $html .= '<tr>';
            $html .= '<th>' . __('Action', 'memberpress-ai-assistant') . '</th>';
            $html .= '<th>' . __('Count', 'memberpress-ai-assistant') . '</th>';
            $html .= '</tr>';
            $html .= '</thead>';
            $html .= '<tbody>';
            
            $action_labels = [
                'installed' => __('Installed', 'memberpress-ai-assistant'),
                'updated' => __('Updated', 'memberpress-ai-assistant'),
                'activated' => __('Activated', 'memberpress-ai-assistant'),
                'deactivated' => __('Deactivated', 'memberpress-ai-assistant'),
                'deleted' => __('Deleted', 'memberpress-ai-assistant')
            ];
            
            foreach ($summary['action_counts'] as $action_data) {
                if (isset($action_data['action']) && isset($action_data['count'])) {
                    $action = $action_data['action'];
                    $count = $action_data['count'];
                    $label = isset($action_labels[$action]) ? $action_labels[$action] : ucfirst($action);
                    
                    $html .= '<tr>';
                    $html .= '<td>' . $label . '</td>';
                    $html .= '<td>' . $count . '</td>';
                    $html .= '</tr>';
                }
            }
            
            $html .= '</tbody>';
            $html .= '</table>';
        }
        
        // Create most active plugins table
        if (isset($summary['most_active_plugins']) && !empty($summary['most_active_plugins'])) {
            $html .= '<h4>' . __('Most Active Plugins', 'memberpress-ai-assistant') . '</h4>';
            $html .= '<table class="widefat mpai-summary-table">';
            $html .= '<thead>';
            $html .= '<tr>';
            $html .= '<th>' . __('Plugin', 'memberpress-ai-assistant') . '</th>';
            $html .= '<th>' . __('Actions', 'memberpress-ai-assistant') . '</th>';
            $html .= '<th>' . __('Last Activity', 'memberpress-ai-assistant') . '</th>';
            $html .= '</tr>';
            $html .= '</thead>';
            $html .= '<tbody>';
            
            $action_labels = [
                'installed' => __('Installed', 'memberpress-ai-assistant'),
                'updated' => __('Updated', 'memberpress-ai-assistant'),
                'activated' => __('Activated', 'memberpress-ai-assistant'),
                'deactivated' => __('Deactivated', 'memberpress-ai-assistant'),
                'deleted' => __('Deleted', 'memberpress-ai-assistant')
            ];
            
            // Limit to first 10 most active plugins
            $most_active = array_slice($summary['most_active_plugins'], 0, 10);
            
            foreach ($most_active as $plugin_data) {
                if (isset($plugin_data['plugin_name']) && isset($plugin_data['count'])) {
                    $html .= '<tr>';
                    $html .= '<td>' . esc_html($plugin_data['plugin_name']) . '</td>';
                    $html .= '<td>' . $plugin_data['count'] . '</td>';
                    
                    $last_action = '';
                    if (isset($plugin_data['last_action'])) {
                        $label = isset($action_labels[$plugin_data['last_action']]) ? 
                            $action_labels[$plugin_data['last_action']] : 
                            ucfirst($plugin_data['last_action']);
                        $last_action = $label;
                    }
                    
                    $last_date = '';
                    if (isset($plugin_data['last_date'])) {
                        $last_date = date('M j, Y g:i a', strtotime($plugin_data['last_date']));
                    }
                    
                    $html .= '<td>' . $last_action . ' on ' . $last_date . '</td>';
                    $html .= '</tr>';
                }
            }
            
            $html .= '</tbody>';
            $html .= '</table>';
        }
        $html .= '</div>'; // Close summary section
        
        // Add recent activity section
        if (!empty($logs)) {
            $html .= '<div class="mpai-plugin-history-logs">';
            $html .= '<h4>' . __('Recent Plugin Activity', 'memberpress-ai-assistant') . '</h4>';
            $html .= '<table class="widefat mpai-logs-table">';
            $html .= '<thead>';
            $html .= '<tr>';
            $html .= '<th>' . __('Date', 'memberpress-ai-assistant') . '</th>';
            $html .= '<th>' . __('Plugin', 'memberpress-ai-assistant') . '</th>';
            $html .= '<th>' . __('Action', 'memberpress-ai-assistant') . '</th>';
            $html .= '<th>' . __('Version', 'memberpress-ai-assistant') . '</th>';
            $html .= '<th>' . __('User', 'memberpress-ai-assistant') . '</th>';
            $html .= '</tr>';
            $html .= '</thead>';
            $html .= '<tbody>';
            
            $action_classes = [
                'installed' => 'mpai-action-installed',
                'updated' => 'mpai-action-updated',
                'activated' => 'mpai-action-activated',
                'deactivated' => 'mpai-action-deactivated',
                'deleted' => 'mpai-action-deleted'
            ];
            
            $action_labels = [
                'installed' => __('Installed', 'memberpress-ai-assistant'),
                'updated' => __('Updated', 'memberpress-ai-assistant'),
                'activated' => __('Activated', 'memberpress-ai-assistant'),
                'deactivated' => __('Deactivated', 'memberpress-ai-assistant'),
                'deleted' => __('Deleted', 'memberpress-ai-assistant')
            ];
            
            foreach ($logs as $log) {
                $html .= '<tr>';
                
                // Date column
                $date = isset($log['date_time']) ? date('M j, Y g:i a', strtotime($log['date_time'])) : '';
                $html .= '<td>' . $date . '</td>';
                
                // Plugin column
                $html .= '<td>' . esc_html($log['plugin_name']) . '</td>';
                
                // Action column
                $action = $log['action'];
                $action_class = isset($action_classes[$action]) ? $action_classes[$action] : '';
                $action_label = isset($action_labels[$action]) ? $action_labels[$action] : ucfirst($action);
                $html .= '<td><span class="' . $action_class . '">' . $action_label . '</span></td>';
                
                // Version column
                $version_text = $log['plugin_version'];
                if (!empty($log['plugin_prev_version']) && $action === 'updated') {
                    $version_text = $log['plugin_prev_version'] . ' → ' . $log['plugin_version'];
                }
                $html .= '<td>' . esc_html($version_text) . '</td>';
                
                // User column
                $user_text = $log['user_login'];
                if (isset($log['user_info']) && !empty($log['user_info']['display_name'])) {
                    $user_text = $log['user_info']['display_name'];
                }
                $html .= '<td>' . esc_html($user_text) . '</td>';
                
                $html .= '</tr>';
            }
            
            $html .= '</tbody>';
            $html .= '</table>';
            $html .= '</div>'; // Close logs section
        }
        
        // Add custom CSS for the tables and action colors
        $html .= '<style>
            .mpai-summary-table, .mpai-logs-table {
                margin-bottom: 20px;
            }
            .mpai-action-installed {
                color: #2271b1;
                font-weight: bold;
            }
            .mpai-action-updated {
                color: #007017;
                font-weight: bold;
            }
            .mpai-action-activated {
                color: #46b450;
                font-weight: bold;
            }
            .mpai-action-deactivated {
                color: #dba617;
                font-weight: bold;
            }
            .mpai-action-deleted {
                color: #dc3232;
                font-weight: bold;
            }
            .mpai-plugins-table td {
                vertical-align: top;
            }
            .mpai-plugins-table .description {
                color: #666;
                font-size: 12px;
            }
        </style>';
        
        return $html;
    }
    
    /**
     * Test AJAX Communication
     * 
     * @return array Test result
     */
    public function test_ajax_communication() {
        // Check that admin-ajax.php is accessible
        $admin_ajax_url = admin_url('admin-ajax.php');
        $direct_ajax_handler_url = MPAI_PLUGIN_URL . 'includes/direct-ajax-handler.php';
        
        // Test that we have a valid nonce
        $nonce = wp_create_nonce('mpai_nonce');
        $nonce_valid = wp_verify_nonce($nonce, 'mpai_nonce');
        
        // Test that we can do a direct ajax handler request
        $direct_ajax_file = MPAI_PLUGIN_DIR . 'includes/direct-ajax-handler.php';
        $direct_ajax_exists = file_exists($direct_ajax_file);
        
        // Create test result based on checkable components
        $success = true;
        $status = 'success';
        $message = __('AJAX Communication is properly configured.', 'memberpress-ai-assistant');
        $warnings = [];
        $critical_issues = [];
        
        if (!$direct_ajax_exists) {
            $success = false;
            $status = 'error';
            $critical_issues[] = __('Direct AJAX handler file not found.', 'memberpress-ai-assistant');
        }
        
        if (!$nonce_valid) {
            $success = false;
            $status = 'error';
            $critical_issues[] = __('Nonce validation is not working correctly.', 'memberpress-ai-assistant');
        }
        
        return [
            'success' => $success,
            'status' => $status,
            'message' => !empty($critical_issues) 
                ? __('AJAX Communication has critical issues.', 'memberpress-ai-assistant') 
                : (!empty($warnings) 
                    ? __('AJAX Communication has some warnings.', 'memberpress-ai-assistant')
                    : $message),
            'critical_issues' => $critical_issues,
            'warnings' => $warnings,
            'details' => [
                'admin_ajax_url' => $admin_ajax_url,
                'direct_ajax_handler_url' => $direct_ajax_handler_url,
                'direct_ajax_exists' => $direct_ajax_exists,
                'nonce_valid' => $nonce_valid,
                'nonce_value' => substr($nonce, 0, 5) . '...',
            ]
        ];
    }
    
    /**
     * Test Nonce Verification
     * 
     * @return array Test result
     */
    public function test_nonce_verification() {
        // Create a test nonce
        $nonce = wp_create_nonce('mpai_nonce');
        
        // Test that we can verify the nonce
        $nonce_valid = wp_verify_nonce($nonce, 'mpai_nonce');
        
        // Test an invalid nonce
        $invalid_nonce = 'invalid_nonce_value';
        $invalid_nonce_valid = wp_verify_nonce($invalid_nonce, 'mpai_nonce');
        
        // Create test result based on verification results
        $success = $nonce_valid && !$invalid_nonce_valid;
        $status = $success ? 'success' : 'error';
        $message = $success 
            ? __('Nonce verification is working correctly.', 'memberpress-ai-assistant') 
            : __('Nonce verification has issues.', 'memberpress-ai-assistant');
        
        $critical_issues = [];
        if (!$nonce_valid) {
            $critical_issues[] = __('Valid nonce is not being properly verified.', 'memberpress-ai-assistant');
        }
        
        if ($invalid_nonce_valid) {
            $critical_issues[] = __('Invalid nonce is being incorrectly accepted.', 'memberpress-ai-assistant');
        }
        
        return [
            'success' => $success,
            'status' => $status,
            'message' => $message,
            'critical_issues' => $critical_issues,
            'details' => [
                'nonce_created' => true,
                'nonce_value' => substr($nonce, 0, 5) . '...',
                'nonce_valid' => $nonce_valid,
                'invalid_nonce_rejected' => !$invalid_nonce_valid,
            ]
        ];
    }
    
    /**
     * Test System Cache
     * 
     * @return array Test result
     */
    public function test_system_cache() {
        // Check if System Cache class exists
        if (!class_exists('MPAI_System_Cache')) {
            $system_cache_file = MPAI_PLUGIN_DIR . 'includes/class-mpai-system-cache.php';
            
            if (!file_exists($system_cache_file)) {
                return [
                    'success' => false,
                    'status' => 'error',
                    'message' => __('System Cache file not found.', 'memberpress-ai-assistant'),
                    'details' => [
                        'file_path' => $system_cache_file,
                        'file_exists' => false
                    ]
                ];
            }
            
            // Try to include the file
            require_once $system_cache_file;
            
            if (!class_exists('MPAI_System_Cache')) {
                return [
                    'success' => false,
                    'status' => 'error',
                    'message' => __('System Cache class not found after loading file.', 'memberpress-ai-assistant'),
                    'details' => [
                        'file_path' => $system_cache_file,
                        'file_exists' => true,
                        'class_exists' => false
                    ]
                ];
            }
        }
        
        // Create a cache instance
        try {
            $cache = MPAI_System_Cache::get_instance();
            
            // Test key and data
            $test_key = 'mpai_diagnostic_test_' . time();
            $test_data = [
                'test_value' => 'This is a test value for system cache',
                'timestamp' => current_time('mysql')
            ];
            
            // Set the data in cache
            $set_result = $cache->set($test_key, $test_data, 'default');
            
            // Get the data back from cache
            $retrieved_data = $cache->get($test_key, 'default');
            
            // Delete the test entry
            $cache->delete($test_key);
            
            // Check if delete worked
            $after_delete = $cache->get($test_key, 'default');
            
            // Check filesystem persistence
            $cache->set('filesystem_test', ['test' => true], 'default');
            
            // Clear the in-memory cache
            $reflection = new \ReflectionClass($cache);
            $memory_cache_prop = $reflection->getProperty('cache');
            $memory_cache_prop->setAccessible(true);
            $memory_cache_prop->setValue($cache, []);
            
            // Reload from filesystem
            $load_method = $reflection->getMethod('maybe_load_filesystem_cache');
            $load_method->setAccessible(true);
            $load_method->invoke($cache);
            
            // Check if data was reloaded
            $persisted_data = $cache->get('filesystem_test', 'default');
            
            // Clean up
            $cache->delete('filesystem_test');
            
            return [
                'success' => ($set_result && $retrieved_data !== null && $after_delete === null && $persisted_data !== null),
                'status' => ($set_result && $retrieved_data !== null && $after_delete === null && $persisted_data !== null) ? 'success' : 'error',
                'message' => ($set_result && $retrieved_data !== null && $after_delete === null && $persisted_data !== null)
                    ? __('System Cache is working correctly.', 'memberpress-ai-assistant')
                    : __('System Cache has issues.', 'memberpress-ai-assistant'),
                'details' => [
                    'set_success' => $set_result,
                    'get_success' => ($retrieved_data !== null),
                    'delete_success' => ($after_delete === null),
                    'persistence_success' => ($persisted_data !== null),
                    'data_match' => ($retrieved_data == $test_data)
                ]
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'status' => 'error',
                'message' => __('Error testing System Cache:', 'memberpress-ai-assistant') . ' ' . $e->getMessage(),
                'details' => [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]
            ];
        }
    }
}
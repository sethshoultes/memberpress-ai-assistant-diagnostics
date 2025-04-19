<?php
/**
 * Helper functions for the MemberPress AI Assistant Diagnostics plugin
 *
 * @package MemberPress AI Assistant Diagnostics
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Wrapper for mpai_log_debug to ensure it's available
 *
 * @param string $message The log message
 * @param string $component The component identifier (default: 'diagnostics')
 * @param array $context Additional context (optional)
 * @return void
 */
function mpai_diag_log_debug($message, $component = 'diagnostics', $context = array()) {
    if (function_exists('mpai_log_debug')) {
        mpai_log_debug($message, $component, $context);
    } else {
        // Fallback to standard error_log if main plugin logging isn't available
        error_log('MPAI DIAGNOSTICS DEBUG: ' . $message);
    }
}

/**
 * Wrapper for mpai_log_info to ensure it's available
 *
 * @param string $message The log message
 * @param string $component The component identifier (default: 'diagnostics')
 * @param array $context Additional context (optional)
 * @return void
 */
function mpai_diag_log_info($message, $component = 'diagnostics', $context = array()) {
    if (function_exists('mpai_log_info')) {
        mpai_log_info($message, $component, $context);
    } else {
        // Fallback to standard error_log if main plugin logging isn't available
        error_log('MPAI DIAGNOSTICS INFO: ' . $message);
    }
}

/**
 * Wrapper for mpai_log_warning to ensure it's available
 *
 * @param string $message The log message
 * @param string $component The component identifier (default: 'diagnostics')
 * @param array $context Additional context (optional)
 * @return void
 */
function mpai_diag_log_warning($message, $component = 'diagnostics', $context = array()) {
    if (function_exists('mpai_log_warning')) {
        mpai_log_warning($message, $component, $context);
    } else {
        // Fallback to standard error_log if main plugin logging isn't available
        error_log('MPAI DIAGNOSTICS WARNING: ' . $message);
    }
}

/**
 * Wrapper for mpai_log_error to ensure it's available
 *
 * @param string $message The log message
 * @param string $component The component identifier (default: 'diagnostics')
 * @param array $context Additional context (optional)
 * @return void
 */
function mpai_diag_log_error($message, $component = 'diagnostics', $context = array()) {
    if (function_exists('mpai_log_error')) {
        mpai_log_error($message, $component, $context);
    } else {
        // Fallback to standard error_log if main plugin logging isn't available
        error_log('MPAI DIAGNOSTICS ERROR: ' . $message);
        
        // Log additional context if provided
        if (!empty($context)) {
            error_log('MPAI DIAGNOSTICS ERROR CONTEXT: ' . json_encode($context));
        }
    }
}

/**
 * Check if the MemberPress AI Assistant plugin is active
 * 
 * @return bool
 */
function mpai_diag_is_main_plugin_active() {
    return class_exists('MemberPress_AI_Assistant');
}

/**
 * Get the version of the main plugin
 * 
 * @return string|null Main plugin version or null if not available
 */
function mpai_diag_get_main_plugin_version() {
    if (defined('MPAI_VERSION')) {
        return MPAI_VERSION;
    }
    return null;
}

/**
 * Enqueue admin scripts and styles
 */
function mpai_diag_enqueue_admin_assets() {
    wp_enqueue_style(
        'mpai-diagnostics-css',
        MPAI_DIAG_PLUGIN_URL . 'assets/css/diagnostics.css',
        array(),
        MPAI_DIAG_VERSION
    );

    wp_enqueue_script(
        'mpai-diagnostics-js',
        MPAI_DIAG_PLUGIN_URL . 'assets/js/diagnostics.js',
        array('jquery'),
        MPAI_DIAG_VERSION,
        true
    );

    wp_localize_script(
        'mpai-diagnostics-js',
        'mpai_diag_data',
        array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('mpai_nonce'),
        )
    );
}
add_action('admin_enqueue_scripts', 'mpai_diag_enqueue_admin_assets');
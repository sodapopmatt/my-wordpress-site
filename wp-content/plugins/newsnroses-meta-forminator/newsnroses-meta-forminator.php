<?php
/**
 * Plugin Name: News N' Roses Meta Forminator
 * Description: Sends Meta browser and server CompleteRegistration events for Forminator submissions.
 * Version: 2.0.2
 * Author: Matthew Ramirez
 */

if (!defined('ABSPATH')) {
    exit;
}

define('NR_META_PLUGIN_DIR', plugin_dir_path(__FILE__));

require_once NR_META_PLUGIN_DIR . '../newsnroses-beehiiv-forminator/includes/class-nr-logger.php';
require_once NR_META_PLUGIN_DIR . 'includes/class-nr-meta-settings.php';
require_once NR_META_PLUGIN_DIR . 'includes/class-nr-meta-capi.php';

// Boot settings
NR_Meta_Settings::init();

// Enqueue browser JS + pass nonce
add_action('wp_enqueue_scripts', function () {
    $page_ids = array_map('intval', explode(',', NR_Meta_Settings::get('page_id')));

    if (!empty(array_filter($page_ids)) && is_page($page_ids)) {
        wp_enqueue_script(
            'nr-meta-browser',
            plugin_dir_url(__FILE__) . 'assets/js/nr-meta-browser.js',
            ['jquery'],
            '2.0.1',
            true
        );

        wp_localize_script('nr-meta-browser', 'nnMeta', [
            'nonce' => wp_create_nonce('wp_rest'),
        ]);
    }
});

// REST endpoint — receives event_id from browser after pixel fires
add_action('rest_api_init', function () {
    register_rest_route('newsnroses/v1', '/meta-subscribe', [
        'methods'             => 'POST',
        'callback'            => ['NR_Meta_CAPI', 'handle_rest_request'],
        'permission_callback' => '__return_true',
    ]);
});
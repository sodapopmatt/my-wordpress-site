<?php
/**
 * Plugin Name: NNR Events
 * Description: Manual event backend for News N' Roses. Provides an Event post type, event categories, and an [nnr_events] shortcode for embedding event listings on any page.
 * Version: 1.0.0
 * Author: News N' Roses
 * Text Domain: nnr-events
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'NNR_EVENTS_PATH', plugin_dir_path( __FILE__ ) );
define( 'NNR_EVENTS_URL', plugin_dir_url( __FILE__ ) );
define( 'NNR_EVENTS_VERSION', '1.0.0' );

require_once NNR_EVENTS_PATH . 'includes/class-nnr-cpt.php';
require_once NNR_EVENTS_PATH . 'includes/class-nnr-taxonomy.php';
require_once NNR_EVENTS_PATH . 'includes/class-nnr-meta-box.php';
require_once NNR_EVENTS_PATH . 'includes/class-nnr-query.php';
require_once NNR_EVENTS_PATH . 'includes/class-nnr-shortcode.php';

function nnr_events_init() {
	new NNR_CPT();
	new NNR_Taxonomy();
	new NNR_Meta_Box();
	new NNR_Shortcode();
}
add_action( 'plugins_loaded', 'nnr_events_init' );

function nnr_events_activate() {
	$cpt = new NNR_CPT();
	$cpt->register();

	$tax = new NNR_Taxonomy();
	$tax->register();
	$tax->seed_terms();

	flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'nnr_events_activate' );

function nnr_events_deactivate() {
	flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'nnr_events_deactivate' );

function nnr_events_enqueue_assets() {
	if ( ! is_singular() ) {
		return;
	}

	global $post;
	if ( ! $post || ! has_shortcode( $post->post_content, 'nnr_events' ) ) {
		return;
	}

	wp_enqueue_style(
		'nnr-events',
		NNR_EVENTS_URL . 'assets/css/nnr-events.css',
		array(),
		NNR_EVENTS_VERSION
	);
}
add_action( 'wp_enqueue_scripts', 'nnr_events_enqueue_assets' );

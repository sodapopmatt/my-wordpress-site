<?php
/**
 * Plugin Name: NNR Events
 * Description: Manual event backend for News N' Roses. Provides an Event post type, event categories, and an [nnr_events] shortcode for embedding event listings on any page.
 * Version: 1.3.0
 * Author: News N' Roses
 * Text Domain: nnr-events
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'NNR_EVENTS_PATH', plugin_dir_path( __FILE__ ) );
define( 'NNR_EVENTS_URL', plugin_dir_url( __FILE__ ) );
define( 'NNR_EVENTS_VERSION', '1.3.0' );

require_once NNR_EVENTS_PATH . 'includes/class-nnr-cpt.php';
require_once NNR_EVENTS_PATH . 'includes/class-nnr-taxonomy.php';
require_once NNR_EVENTS_PATH . 'includes/class-nnr-meta-box.php';
require_once NNR_EVENTS_PATH . 'includes/class-nnr-query.php';
require_once NNR_EVENTS_PATH . 'includes/class-nnr-shortcode.php';
require_once NNR_EVENTS_PATH . 'includes/class-nnr-settings.php';
require_once NNR_EVENTS_PATH . 'includes/class-nnr-shortcode-page.php';
require_once NNR_EVENTS_PATH . 'includes/class-nnr-admin-list.php';
require_once NNR_EVENTS_PATH . 'includes/class-nnr-ics.php';
require_once NNR_EVENTS_PATH . 'includes/class-nnr-duplicate.php';
require_once NNR_EVENTS_PATH . 'includes/class-nnr-block.php';

function nnr_events_init() {
	new NNR_CPT();
	new NNR_Taxonomy();
	new NNR_Meta_Box();
	new NNR_Shortcode();
	new NNR_Settings();
	new NNR_Shortcode_Page();
	new NNR_Admin_List();
	new NNR_ICS();
	new NNR_Duplicate();
	new NNR_Block();
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

/**
 * Always load the (small, scoped) front-end assets rather than trying to
 * detect the shortcode/block in advance. Page builders like WPBakery can
 * store shortcode text base64-encoded (e.g. inside a Raw HTML element),
 * which defeats a has_shortcode()/has_block() content check even though
 * the shortcode still renders fine — that silently dropped the CSS/JS on
 * pages built that way.
 */
function nnr_events_enqueue_assets() {
	wp_enqueue_style(
		'nnr-events',
		NNR_EVENTS_URL . 'assets/css/nnr-events.css',
		array(),
		NNR_EVENTS_VERSION
	);
	wp_enqueue_script( 'nnr-events-frontend' );
}
add_action( 'wp_enqueue_scripts', 'nnr_events_enqueue_assets' );

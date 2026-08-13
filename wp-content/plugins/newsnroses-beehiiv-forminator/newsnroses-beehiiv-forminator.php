<?php
/**
 * Plugin Name: News N' Roses Beehiiv Forminator
 * Description: Sends Forminator subscribers to Beehiiv with UTMs and custom fields.
 * Version: 2.0.0
 * Author: Matthew Ramirez
 */

if (!defined('ABSPATH')) {
    exit;
}

define('NR_BEEHIIV_PLUGIN_DIR', plugin_dir_path(__FILE__));

require_once NR_BEEHIIV_PLUGIN_DIR . 'includes/class-nr-logger.php';
require_once NR_BEEHIIV_PLUGIN_DIR . 'includes/class-nr-beehiiv-settings.php';
require_once NR_BEEHIIV_PLUGIN_DIR . 'includes/class-nr-beehiiv.php';

NR_Beehiiv_Settings::init();

add_action(
    'forminator_custom_form_submit_before_set_fields',
    ['NR_Beehiiv', 'handle_form_submission'],
    10,
    3
);

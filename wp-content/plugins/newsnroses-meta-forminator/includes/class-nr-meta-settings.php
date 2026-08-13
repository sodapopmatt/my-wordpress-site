<?php

if (!defined('ABSPATH')) {
    exit;
}

class NR_Meta_Settings {

    public static function init() {
        add_action('admin_menu', [__CLASS__, 'add_settings_page']);
        add_action('admin_init', [__CLASS__, 'register_settings']);
    }

    public static function add_settings_page() {
        add_options_page(
            'NR Meta Settings',
            'NR Meta',
            'manage_options',
            'nr-meta-settings',
            [__CLASS__, 'render_settings_page']
        );
    }

    public static function register_settings() {
        register_setting('nr_meta_settings_group', 'nr_meta_pixel_id', [
            'sanitize_callback' => 'sanitize_text_field',
        ]);
        register_setting('nr_meta_settings_group', 'nr_meta_access_token', [
            'sanitize_callback' => 'sanitize_text_field',
        ]);
        register_setting('nr_meta_settings_group', 'nr_meta_form_id', [
            'sanitize_callback' => 'absint',
        ]);
        register_setting('nr_meta_settings_group', 'nr_meta_page_id', [
            'sanitize_callback' => 'sanitize_text_field',
        ]);
        register_setting('nr_meta_settings_group', 'nr_meta_graph_version', [
            'sanitize_callback' => 'sanitize_text_field',
        ]);
        register_setting('nr_meta_settings_group', 'nr_meta_test_event_code', [
            'sanitize_callback' => 'sanitize_text_field',
        ]);
        register_setting('nr_meta_settings_group', 'nr_meta_debug_logging', [
            'sanitize_callback' => 'absint',
        ]);
    }

    public static function render_settings_page() {
        if (!current_user_can('manage_options')) {
            return;
        }
        ?>
        <div class="wrap">
            <h1>NR Meta Forminator Settings</h1>
            <form method="post" action="options.php">
                <?php settings_fields('nr_meta_settings_group'); ?>
                <?php do_settings_sections('nr-meta-settings'); ?>
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="nr_meta_pixel_id">Pixel ID</label></th>
                        <td>
                            <input type="text" id="nr_meta_pixel_id" name="nr_meta_pixel_id"
                                value="<?php echo esc_attr(get_option('nr_meta_pixel_id')); ?>"
                                class="regular-text" />
                            <p class="description">Your Meta Pixel ID</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="nr_meta_access_token">Access Token</label></th>
                        <td>
                            <input type="password" id="nr_meta_access_token" name="nr_meta_access_token"
                                value="<?php echo esc_attr(get_option('nr_meta_access_token')); ?>"
                                class="regular-text" />
                            <p class="description">Your Meta Conversions API access token</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="nr_meta_form_id">Forminator Form ID</label></th>
                        <td>
                            <input type="number" id="nr_meta_form_id" name="nr_meta_form_id"
                                value="<?php echo esc_attr(get_option('nr_meta_form_id')); ?>"
                                class="regular-text" />
                            <p class="description">The ID of your Forminator form</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="nr_meta_page_id">Page ID</label></th>
                        <td>
                            <input type="text" id="nr_meta_page_id" name="nr_meta_page_id"
                                value="<?php echo esc_attr(get_option('nr_meta_page_id')); ?>"
                                class="regular-text" />
                            <p class="description">Page IDs where your signup form lives (comma-separated, e.g. 123, 456, 789)</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="nr_meta_graph_version">Graph API Version</label></th>
                        <td>
                            <input type="text" id="nr_meta_graph_version" name="nr_meta_graph_version"
                                value="<?php echo esc_attr(get_option('nr_meta_graph_version', 'v22.0')); ?>"
                                class="regular-text" />
                            <p class="description">Meta Graph API version (e.g. v25.0)</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="nr_meta_debug_logging">Debug Logging</label></th>
                        <td>
                            <input type="checkbox" id="nr_meta_debug_logging" name="nr_meta_debug_logging" value="1"
                                <?php checked(1, get_option('nr_meta_debug_logging', 0)); ?> />
                            <p class="description">Enable verbose logging for debugging. All events are logged to <code>/wp-content/logs/nr-plugins.log</code>. When enabled, adds detailed user data/payload info. Logs rotate at 10MB (keeps 5 backups).</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="nr_meta_test_event_code">Test Event Code</label></th>
                        <td>
                            <input type="text" id="nr_meta_test_event_code" name="nr_meta_test_event_code"
                                value="<?php echo esc_attr(get_option('nr_meta_test_event_code')); ?>"
                                class="regular-text" />
                            <p class="description">Leave blank for live events. Add your test code (e.g. TEST1064) during testing only.</p>
                        </td>
                    </tr>
                </table>
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }

    public static function get(string $key, string $fallback = ''): string {
        return get_option('nr_meta_' . $key, $fallback) ?: $fallback;
    }

    public static function is_debug(): bool {
        return (bool) get_option('nr_meta_debug_logging', 0);
    }
}
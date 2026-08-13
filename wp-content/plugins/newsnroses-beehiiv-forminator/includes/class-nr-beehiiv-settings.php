<?php

if (!defined('ABSPATH')) {
    exit;
}

class NR_Beehiiv_Settings {

    public static function init() {
        add_action('admin_menu', [__CLASS__, 'add_settings_page']);
        add_action('admin_init', [__CLASS__, 'register_settings']);
    }

    public static function add_settings_page() {
        add_options_page(
            'NR Beehiiv Settings',
            'NR Beehiiv',
            'manage_options',
            'nr-beehiiv-settings',
            [__CLASS__, 'render_settings_page']
        );
    }

    public static function register_settings() {
        register_setting('nr_beehiiv_settings_group', 'nr_beehiiv_publication_id', [
            'sanitize_callback' => 'sanitize_text_field',
        ]);
        register_setting('nr_beehiiv_settings_group', 'nr_beehiiv_api_key', [
            'sanitize_callback' => 'sanitize_text_field',
        ]);
        register_setting('nr_beehiiv_settings_group', 'nr_beehiiv_form_id', [
            'sanitize_callback' => 'sanitize_text_field',
        ]);
        register_setting('nr_beehiiv_settings_group', 'nr_beehiiv_debug_logging', [
            'sanitize_callback' => 'absint',
        ]);
    }

    public static function render_settings_page() {
        if (!current_user_can('manage_options')) {
            return;
        }
        ?>
        <div class="wrap">
            <h1>NR Beehiiv Forminator Settings</h1>
            <form method="post" action="options.php">
                <?php settings_fields('nr_beehiiv_settings_group'); ?>
                <?php do_settings_sections('nr-beehiiv-settings'); ?>
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="nr_beehiiv_publication_id">Publication ID</label></th>
                        <td>
                            <input type="text" id="nr_beehiiv_publication_id" name="nr_beehiiv_publication_id"
                                value="<?php echo esc_attr(get_option('nr_beehiiv_publication_id')); ?>"
                                class="regular-text" />
                            <p class="description">Your Beehiiv Publication ID (e.g. pub_xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx)</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="nr_beehiiv_api_key">API Key</label></th>
                        <td>
                            <input type="password" id="nr_beehiiv_api_key" name="nr_beehiiv_api_key"
                                value="<?php echo esc_attr(get_option('nr_beehiiv_api_key')); ?>"
                                class="regular-text" />
                            <p class="description">Your Beehiiv API key</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="nr_beehiiv_form_id">Forminator Form ID</label></th>
                        <td>
                            <input type="text" id="nr_beehiiv_form_id" name="nr_beehiiv_form_id"
                                value="<?php echo esc_attr(get_option('nr_beehiiv_form_id')); ?>"
                                class="regular-text" />
                            <p class="description">The ID of your Forminator form</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="nr_beehiiv_debug_logging">Debug Logging</label></th>
                        <td>
                            <input type="checkbox" id="nr_beehiiv_debug_logging" name="nr_beehiiv_debug_logging" value="1"
                                <?php checked(1, get_option('nr_beehiiv_debug_logging', 0)); ?> />
                            <p class="description">Enable verbose logging for debugging. All submissions are logged to <code>/wp-content/logs/nr-plugins.log</code>. When enabled, adds detailed email/IP/geo data. Logs rotate at 10MB (keeps 5 backups).</p>
                        </td>
                    </tr>
                </table>
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }

    public static function get(string $key, string $fallback = ''): string {
        return get_option('nr_beehiiv_' . $key, $fallback) ?: $fallback;
    }

    public static function is_debug(): bool {
        return (bool) get_option('nr_beehiiv_debug_logging', 0);
    }
}
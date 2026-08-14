<?php

if (!defined('ABSPATH')) {
    exit;
}

class NR_Meta_CAPI {

    public static function handle_rest_request(WP_REST_Request $request) {
        $event_id = sanitize_text_field($request->get_param('event_id'));
        $email    = sanitize_text_field($request->get_param('email'));
        $email    = strtolower(trim($email));
        $form_id  = intval($request->get_param('form_id'));

        if (NR_Meta_Settings::is_debug()) {
            NR_Logger::log('NR REST email after sanitize: "' . $email . '"');
            NR_Logger::log('NR REST email valid: ' . (filter_var($email, FILTER_VALIDATE_EMAIL) ? 'yes' : 'no'));
            NR_Logger::log('NR REST received - event_id: ' . $event_id);
            NR_Logger::log('NR REST received - email: ' . $email);
            NR_Logger::log('NR REST received - form_id: ' . $form_id);
        }

        if (empty($event_id)) {
            return new WP_REST_Response(['error' => 'missing event_id'], 400);
        }

        $expected_form_id = (int) NR_Meta_Settings::get('form_id');
        if ($form_id && $expected_form_id && $form_id !== $expected_form_id) {
            NR_Logger::log('NR Meta CAPI SKIPPED - Email: ' . $email . ', Reason: wrong form ID (expected ' . $expected_form_id . ', got ' . $form_id . ')');
            return new WP_REST_Response(['error' => 'wrong form'], 400);
        }

        self::send_subscribe_event($email, $event_id);

        return new WP_REST_Response(['ok' => true], 200);
    }

    public static function send_subscribe_event($email, $event_id = '') {
        $debug = NR_Meta_Settings::is_debug();

        if ($debug) NR_Logger::log('NR send_subscribe_event called - email param: "' . $email . '"');

        $pixel_id        = NR_Meta_Settings::get('pixel_id');
        $access_token    = NR_Meta_Settings::get('access_token');
        $graph_version   = NR_Meta_Settings::get('graph_version', 'v22.0');
        $test_event_code = NR_Meta_Settings::get('test_event_code');

        if (empty($pixel_id) || empty($access_token)) {
            NR_Logger::log('NR Meta CAPI SKIPPED - Email: ' . $email . ', Reason: missing Pixel ID or access token');
            return;
        }

        $fbp = self::get_cookie_value('_fbp');
        $fbc = self::get_cookie_value('_fbc');
        if ($debug) {
            NR_Logger::log('NR CAPI fbp: ' . $fbp);
            NR_Logger::log('NR CAPI fbc: ' . $fbc);
        }

        $ip = self::get_ip_address();
        if ($debug) NR_Logger::log('NR CAPI client_ip_address: ' . $ip);
        $ua = self::get_user_agent();

        $user_data = array_filter([
            'client_ip_address' => $ip,
            'client_user_agent' => $ua,
            'fbp'               => $fbp,
            'fbc'               => $fbc,
        ]);

        if (!empty($email) && filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $user_data['em'] = self::hash_email($email);
            $user_data['external_id'] = self::hash_email($email);
        }

        if ($debug) {
            NR_Logger::log('NR CAPI user_data keys: ' . implode(', ', array_keys($user_data)));
            NR_Logger::log('NR CAPI em present: ' . (isset($user_data['em']) ? 'yes' : 'no'));
        }

        $event = [
            'event_name'       => 'CompleteRegistration',
            'event_time'       => time(),
            'action_source'    => 'website',
            'event_source_url' => self::get_event_source_url(),
            'event_id'         => $event_id,
            'user_data'        => $user_data,
        ];

        if ($debug) NR_Logger::log('NR Meta CAPI event_id: ' . $event_id);

        $payload = ['data' => [$event]];

        if (!empty($test_event_code)) {
            $payload['test_event_code'] = $test_event_code;
        }

        if ($debug) NR_Logger::log('NR Meta CAPI payload: ' . wp_json_encode($payload));

        $url = sprintf(
            'https://graph.facebook.com/%s/%s/events?access_token=%s',
            $graph_version,
            $pixel_id,
            rawurlencode($access_token)
        );

        $response = wp_remote_post($url, [
            'timeout' => 20,
            'headers' => ['Content-Type' => 'application/json'],
            'body'    => wp_json_encode($payload),
        ]);

        if (is_wp_error($response)) {
            NR_Logger::log('NR Meta CAPI FAILED - Email: ' . $email . ', Error: ' . $response->get_error_message());
            return;
        }

        $http_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);

        if ($http_code >= 200 && $http_code < 300) {
            NR_Logger::log('NR Meta CAPI SUCCESS - Email: ' . $email . ', HTTP Status: ' . $http_code);
        } else {
            NR_Logger::log('NR Meta CAPI FAILED - Email: ' . $email . ', HTTP Status: ' . $http_code . ', Response: ' . $response_body);
        }
    }

    private static function hash_email($email) {
        return hash('sha256', strtolower(trim($email)));
    }

    private static function get_cookie_value($key) {
        if (!empty($_COOKIE[$key]) && is_string($_COOKIE[$key])) {
            return sanitize_text_field(wp_unslash($_COOKIE[$key]));
        }
        return '';
    }

    private static function get_ip_address() {
        if (!empty($_SERVER['HTTP_CF_CONNECTING_IP'])) {
            return sanitize_text_field(wp_unslash($_SERVER['HTTP_CF_CONNECTING_IP']));
        }
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ips = explode(',', wp_unslash($_SERVER['HTTP_X_FORWARDED_FOR']));
            return sanitize_text_field(trim($ips[0]));
        }
        if (!empty($_SERVER['REMOTE_ADDR'])) {
            return sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR']));
        }
        return '';
    }

    private static function get_user_agent() {
        if (!empty($_SERVER['HTTP_USER_AGENT']) && is_string($_SERVER['HTTP_USER_AGENT'])) {
            return sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT']));
        }
        return '';
    }

    private static function get_event_source_url() {
        $signupPage = self::get_cookie_value('signup_page');
        if ($signupPage !== '') {
            return esc_url_raw($signupPage);
        }
        return home_url('/');
    }
}
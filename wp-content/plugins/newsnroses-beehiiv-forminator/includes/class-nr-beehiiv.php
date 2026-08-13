<?php

if (!defined('ABSPATH')) {
    exit;
}

class NR_Beehiiv {

    public static function handle_form_submission($entry, $form_id, $field_data_array) {
        $debug = NR_Beehiiv_Settings::is_debug();
        $timestamp = current_time('mysql');

        NR_Logger::log("NR Beehiiv: Processing form submission");

        $expected_form_id = (int) NR_Beehiiv_Settings::get('form_id');
        if ($expected_form_id && (int) $form_id !== $expected_form_id) {
            NR_Logger::log("NR Beehiiv: Skipped - wrong form ID (expected {$expected_form_id}, got {$form_id})");
            return;
        }

        $email = self::extract_email($field_data_array);
        $ip    = self::extract_ip($field_data_array);

        if ($debug) {
            NR_Logger::log("NR Beehiiv: Email = {$email}");
            NR_Logger::log("NR Beehiiv: IP = {$ip}");
        }

        if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            NR_Logger::log("NR Beehiiv: FAILED - Invalid or missing email. Raw value: " . (!$email ? '(empty)' : 'validation failed'));
            return;
        }

        $geo_data = self::lookup_geo_data($ip);
        $ip_zip   = $geo_data['zip'] ?? '';
        $ip_city  = $geo_data['city'] ?? '';

        if ($debug) {
            NR_Logger::log("NR Beehiiv: IP Zip = {$ip_zip}");
            NR_Logger::log("NR Beehiiv: IP City = {$ip_city}");
        }

        $subscriber_data = self::build_subscriber_data($email, $ip_zip, $ip_city);

        $result = self::send_to_beehiiv_with_retry($subscriber_data, $timestamp);

        if (!$result['success']) {
            NR_Logger::log("NR Beehiiv: FAILED - Email: {$email}, HTTP Status: {$result['http_code']}, Error: {$result['error_message']}");
            return;
        }

        NR_Logger::log("NR Beehiiv: SUCCESS - Email: {$email}, HTTP Status: {$result['http_code']}, Status: {$result['subscriber_status']}");
    }

    private static function send_to_beehiiv_with_retry($subscriber_data, $timestamp) {
        $max_attempts = 3;
        $backoff_base = 2;
        // Retry logic: attempt once, then wait 1s, 2s if needed (exponential backoff)
        // This handles transient failures (429 rate limit, 5xx server errors)
        // Sleep is done during form submission - acceptable since most calls succeed immediately

        for ($attempt = 1; $attempt <= $max_attempts; $attempt++) {
            $result = self::send_to_beehiiv($subscriber_data);

            if (is_wp_error($result)) {
                $error_msg = $result->get_error_message();
                NR_Logger::log("NR Beehiiv: Attempt {$attempt}/{$max_attempts} - WP_Error: {$error_msg}");

                if ($attempt < $max_attempts) {
                    $wait_seconds = pow($backoff_base, $attempt - 1);
                    sleep($wait_seconds);
                    continue;
                }

                return [
                    'success' => false,
                    'http_code' => 0,
                    'error_message' => "Network error after {$max_attempts} attempts: {$error_msg}",
                    'subscriber_status' => 'failed',
                ];
            }

            $http_code = wp_remote_retrieve_response_code($result);
            $body = wp_remote_retrieve_body($result);
            $response_data = json_decode($body, true);

            NR_Logger::log("NR Beehiiv: Attempt {$attempt}/{$max_attempts} - HTTP {$http_code}");

            if ($http_code >= 200 && $http_code < 300) {
                $subscriber_status = 'created';
                if (!empty($response_data['data']['reactivated'])) {
                    $subscriber_status = 'reactivated';
                }

                return [
                    'success' => true,
                    'http_code' => $http_code,
                    'error_message' => '',
                    'subscriber_status' => $subscriber_status,
                ];
            }

            if ($http_code === 409) {
                return [
                    'success' => true,
                    'http_code' => $http_code,
                    'error_message' => '',
                    'subscriber_status' => 'already_subscribed',
                ];
            }

            if (in_array($http_code, [429, 500, 502, 503, 504])) {
                NR_Logger::log("NR Beehiiv: Attempt {$attempt}/{$max_attempts} - Retryable error (HTTP {$http_code})");

                if ($attempt < $max_attempts) {
                    $wait_seconds = pow($backoff_base, $attempt - 1);
                    sleep($wait_seconds);
                    continue;
                }
            }

            $error_msg = $body;
            if (is_array($response_data) && !empty($response_data['errors'])) {
                $error_msg = json_encode($response_data['errors']);
            }

            return [
                'success' => false,
                'http_code' => $http_code,
                'error_message' => "HTTP {$http_code}: {$error_msg}",
                'subscriber_status' => 'failed',
            ];
        }

        return [
            'success' => false,
            'http_code' => 0,
            'error_message' => 'Unknown error',
            'subscriber_status' => 'failed',
        ];
    }

    private static function extract_email($field_data_array) {
        if (!is_array($field_data_array)) {
            return '';
        }

        foreach ($field_data_array as $field) {
            if (!empty($field['value']) && is_string($field['value'])) {
                $value = trim($field['value']);

                if (filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    return $value;
                }
            }
        }

        return '';
    }

    private static function extract_ip($field_data_array) {
        if (!is_array($field_data_array)) {
            return '';
        }

        foreach ($field_data_array as $field) {
            if (
                !empty($field['name']) &&
                $field['name'] === '_forminator_user_ip' &&
                !empty($field['value']) &&
                is_string($field['value'])
            ) {
                return sanitize_text_field(wp_unslash($field['value']));
            }
        }

        return '';
    }

    private static function lookup_geo_data($ip) {
        $result = [
            'zip'  => '',
            'city' => '',
        ];

        if (empty($ip) || !is_string($ip)) {
            return $result;
        }

        $response = wp_remote_get(
            "http://ip-api.com/json/{$ip}",
            ['timeout' => 3]
        );

        if (is_wp_error($response)) {
            if (NR_Beehiiv_Settings::is_debug()) {
                NR_Logger::log('NR Beehiiv: Geo lookup failed (non-blocking): ' . $response->get_error_message());
            }
            return $result;
        }

        $http_code = wp_remote_retrieve_response_code($response);
        if ($http_code < 200 || $http_code >= 300) {
            if (NR_Beehiiv_Settings::is_debug()) {
                NR_Logger::log('NR Beehiiv: Geo lookup returned HTTP ' . $http_code . ' (non-blocking)');
            }
            return $result;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (!is_array($body)) {
            return $result;
        }

        if (!empty($body['zip']) && is_string($body['zip'])) {
            $result['zip'] = sanitize_text_field($body['zip']);
        }

        if (!empty($body['city']) && is_string($body['city'])) {
            $result['city'] = sanitize_text_field($body['city']);
        }

        return $result;
    }

    private static function build_subscriber_data($email, $ip_zip, $ip_city) {
        $subscriber_data = [
            'email'               => $email,
            'reactivate_existing' => true,
            'send_welcome_email'  => true,
        ];

        $utm_keys = [
            'utm_source',
            'utm_medium',
            'utm_campaign',
            'utm_term',
            'utm_content',
            'referring_site',
        ];

        foreach ($utm_keys as $key) {
            $value = self::get_cookie_value($key);
            if ($value !== '') {
                $subscriber_data[$key] = $value;
            }
        }

        $custom_fields = [];

        if ($ip_zip !== '') {
            $custom_fields[] = ['name' => 'IP Zip', 'value' => $ip_zip];
        }

        if ($ip_city !== '') {
            $custom_fields[] = ['name' => 'IP City', 'value' => $ip_city];
        }

        $signup_page = self::get_cookie_value('signup_page');
        if ($signup_page !== '') {
            $custom_fields[] = ['name' => 'Signup Page', 'value' => $signup_page];
        }

        $device = self::get_device_type();
        if ($device !== '') {
            $custom_fields[] = ['name' => 'Device', 'value' => $device];
        }

        $fbp = self::get_cookie_value('_fbp');
        if ($fbp !== '') {
            $custom_fields[] = ['name' => 'fbp', 'value' => $fbp];
        }

        if (!empty($custom_fields)) {
            $subscriber_data['custom_fields'] = $custom_fields;
        }

        return $subscriber_data;
    }

    private static function send_to_beehiiv($subscriber_data) {
        $publication_id = NR_Beehiiv_Settings::get('publication_id');
        $api_key        = NR_Beehiiv_Settings::get('api_key');

        if (empty($publication_id) || empty($api_key)) {
            if (NR_Beehiiv_Settings::is_debug()) NR_Logger::log('NR Beehiiv skipped: missing Publication ID or API key');
            return new WP_Error('missing_credentials', 'Missing Beehiiv credentials');
        }

        $url = sprintf(
            'https://api.beehiiv.com/v2/publications/%s/subscriptions',
            $publication_id
        );

        return wp_remote_post($url, [
            'timeout' => 20,
            'headers' => [
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type'  => 'application/json',
            ],
            'body' => wp_json_encode($subscriber_data),
        ]);
    }

    private static function get_cookie_value($key) {
        if (!empty($_COOKIE[$key]) && is_string($_COOKIE[$key])) {
            return sanitize_text_field(wp_unslash($_COOKIE[$key]));
        }

        return '';
    }

    private static function get_device_type() {
        if (empty($_SERVER['HTTP_USER_AGENT']) || !is_string($_SERVER['HTTP_USER_AGENT'])) {
            return 'Desktop';
        }

        $user_agent = wp_unslash($_SERVER['HTTP_USER_AGENT']);

        if (preg_match('/mobile|android|iphone|ipad|ipod/i', $user_agent)) {
            return 'Mobile';
        }

        return 'Desktop';
    }
}
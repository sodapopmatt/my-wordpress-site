<?php

if (!defined('ABSPATH')) {
    exit;
}

class NR_Logger {
    private static $log_file;
    private static $max_size = 10485760; // 10MB in bytes
    private static $max_backups = 5;

    public static function init() {
        self::$log_file = WP_CONTENT_DIR . '/logs/nr-plugins.log';
        if (!is_dir(dirname(self::$log_file))) {
            wp_mkdir_p(dirname(self::$log_file));
        }
    }

    public static function log($message) {
        if (!self::$log_file) {
            self::init();
        }
        $timestamp = current_time('mysql');
        $formatted = "[{$timestamp}] {$message}\n";

        // Write with lock to ensure atomic operation
        file_put_contents(self::$log_file, $formatted, FILE_APPEND | LOCK_EX);

        // Check if rotation is needed
        if (file_exists(self::$log_file) && filesize(self::$log_file) > self::$max_size) {
            self::rotate_logs();
        }
    }

    private static function rotate_logs() {
        // Remove oldest backup if we're at max
        $oldest = self::$log_file . '.' . self::$max_backups;
        if (file_exists($oldest)) {
            @unlink($oldest);
        }

        // Shift existing backups: .4 -> .5, .3 -> .4, etc.
        for ($i = self::$max_backups - 1; $i >= 1; $i--) {
            $current = self::$log_file . '.' . $i;
            $next = self::$log_file . '.' . ($i + 1);
            if (file_exists($current)) {
                @rename($current, $next);
            }
        }

        // Archive current log as .1
        if (file_exists(self::$log_file)) {
            @rename(self::$log_file, self::$log_file . '.1');
        }
    }

    public static function get_log_file() {
        if (!self::$log_file) {
            self::init();
        }
        return self::$log_file;
    }
}

NR_Logger::init();

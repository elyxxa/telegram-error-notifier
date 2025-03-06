<?php
namespace Webkonsulenterne\TelegramErrorNotifier;

class Settings {
    private static $instance = null;
    private $settings = null;
    private $option_name = 'wp_telegram_error_notifier_settings';
    
    private $default_settings = [
        'bot_token' => '',
        'chat_id' => '',
        'notification_interval' => 'daily',
        'alert_staging' => 'off',
        'plugin_notifications' => [],
        'user_notifications' => [],
        'woocommerce_notifications' => [],
        'disable_error_reporting' => 'off',
        'pagespeed_api_key' => '',
        'pagespeed_threshold' => 90,
        'cloudflare_api_key' => '',
        'menu' => '',
        'wordfence_severity_level' => 'critical',
		'wordfence' => true,
		'plugin_auto_updates' => true,
		'front_page_meta_robots' => true,
		'sitemap' => true,
		'cloudflare_cache' => true,
		'billwerk_settings' => true,
		'cloudflare_settings' => true,
		'rankmath_redirect' => true,
		'web_fonts' => true,
		'autoloaded_options' => true,
		'avif_webp_images' => true,
		'updraft_backups' => true,
		'woocommerce_hpos' => true,
		'security_headers' => true,
		'pagespeed' => true,
		'woocommerce_orders' => true,
        'check_permalinks' => true,
        'check_under_attack_mode' => true,
        'wordfence_waf' => true,
        'check_acymailing' => true,
        'check_404_redirects' => true,
    ];
    
    private function __construct() {
        $this->load_settings();
    }
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function load_settings() {
        if ($this->settings === null) {
            $saved_settings = get_option($this->option_name, []);
            $this->settings = wp_parse_args($saved_settings, $this->default_settings);
        }
    }
    
    public function get($key = null, $default = null) {
        if ($key === null) {
            return $this->settings;
        }
        
        if (!array_key_exists($key, $this->settings)) {
            return $default;
        }
        
        return $this->settings[$key];
    }
    
    public function update($new_settings) {
        if (!is_array($new_settings)) {
            return false;
        }
        
        // Sanitize the settings
        $sanitized_settings = $this->sanitize($new_settings);
        
        // Merge with existing settings
        $this->settings = wp_parse_args($sanitized_settings, $this->settings);
        
        // Save and return result
        $saved = update_option($this->option_name, $this->settings);
        
        if ($saved && defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Telegram Error Notifier settings updated successfully');
        }
        
        return $saved;
    }

    // Prevent cloning of the instance
    private function __clone() {}

    // Prevent unserializing of the instance
    public function __wakeup() {}

    // Add method to validate settings
    public function validate_settings($settings) {
        $validated = [];
        
        // Basic settings
        $validated['bot_token'] = sanitize_text_field($settings['bot_token'] ?? '');
        $validated['chat_id'] = sanitize_text_field($settings['chat_id'] ?? '');
        $validated['notification_interval'] = sanitize_text_field($settings['notification_interval'] ?? 'daily');
        $validated['alert_staging'] = ($settings['alert_staging'] ?? 'off');
        $validated['disable_error_reporting'] = ($settings['disable_error_reporting'] ?? 'off');
        $validated['disable_billwerk_error_reporting'] = ($settings['disable_billwerk_error_reporting'] ?? 'off');
        
        // API Keys and thresholds
        $validated['pagespeed_api_key'] = sanitize_text_field($settings['pagespeed_api_key'] ?? '');
        $validated['pagespeed_threshold'] = min(100, max(0, intval($settings['pagespeed_threshold'] ?? 90)));
        $validated['cloudflare_api_key'] = sanitize_text_field($settings['cloudflare_api_key'] ?? '');
        $validated['security_headers_api_key'] = sanitize_text_field($settings['security_headers_api_key'] ?? '');
        
        // Arrays
        $validated['plugin_notifications'] = isset($settings['plugin_notifications']) ? (array)$settings['plugin_notifications'] : [];
        $validated['user_notifications'] = isset($settings['user_notifications']) ? (array)$settings['user_notifications'] : [];
        $validated['woocommerce_notifications'] = isset($settings['woocommerce_notifications']) ? (array)$settings['woocommerce_notifications'] : [];
        
        // Menu settings
        $validated['menu'] = sanitize_text_field($settings['menu'] ?? '');
        
        // Wordfence settings
        $severity_levels = ['none', 'low', 'medium', 'high', 'critical'];
        $severity = isset($settings['wordfence_severity_level']) ? sanitize_text_field($settings['wordfence_severity_level']) : 'critical';
        $validated['wordfence_severity_level'] = in_array($severity, $severity_levels) ? $severity : 'critical';
        
        // Checker settings
        $checker_settings = [
            'wordfence',
            'plugin_auto_updates',
            'front_page_meta_robots',
            'sitemap',
            'cloudflare_cache',
            'billwerk_settings',
            'cloudflare_settings',
            'rankmath_redirect',
            'web_fonts',
            'autoloaded_options',
            'avif_webp_images',
            'updraft_backups',
            'woocommerce_hpos',
            'security_headers',
            'pagespeed',
            'woocommerce_orders',
            'check_permalinks',
            'check_under_attack_mode',
            'check_404_redirects'
        ];
        
        foreach ($checker_settings as $setting) {
            $validated[$setting] = isset($settings[$setting]) && $settings[$setting] == '1';
        }

        return $validated;
    }

    // Add method to set defaults
    public function set_defaults() {
        $current_settings = $this->get();
        $updated_settings = wp_parse_args($current_settings, $this->default_settings);
        $this->update($updated_settings);
    }

    public function sanitize($settings) {
        if (!is_array($settings)) {
            return [];
        }
        
        $sanitized = [];
        foreach ($settings as $key => $value) {
            switch ($key) {
                case 'google_search_console_api_key':
                    $sanitized[$key] = trim($value);
                    break;
                case 'google_search_console_site_url':
                    $sanitized[$key] = sanitize_text_field($value);
                    break;
                default:
                    $sanitized[$key] = is_array($value) ? array_map('sanitize_text_field', $value) : sanitize_text_field($value);
            }
        }
        
        return $sanitized;
    }
}
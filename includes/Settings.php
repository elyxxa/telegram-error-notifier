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
		'cpanel_usage_check' => true,
		'cpanel_hostname' => '',
		'cpanel_username' => '',
		'cpanel_token' => '',
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
    public function validate_settings($input) {
        $validated = [];
        
        // Validate bot token
        $validated['bot_token'] = sanitize_text_field($input['bot_token'] ?? '');
        
        // Validate chat ID
        $validated['chat_id'] = sanitize_text_field($input['chat_id'] ?? '');
        
        // Validate notification interval
        $validated['notification_interval'] = sanitize_text_field($input['notification_interval'] ?? '5minutes');
        
        // Validate alert_staging
        $validated['alert_staging'] = ($input['alert_staging'] ?? 'off') === 'on' ? 'on' : 'off';
        
        // Validate plugin notifications
        $validated['plugin_notifications'] = isset($input['plugin_notifications']) ? (array)$input['plugin_notifications'] : [];
        
        // Validate user notifications
        $validated['user_notifications'] = isset($input['user_notifications']) ? (array)$input['user_notifications'] : [];
        
        // Validate woocommerce notifications
        $validated['woocommerce_notifications'] = isset($input['woocommerce_notifications']) ? (array)$input['woocommerce_notifications'] : [];
        
        // Validate disable error reporting
        $validated['disable_error_reporting'] = ($input['disable_error_reporting'] ?? 'off') === 'on' ? 'on' : 'off';
        
        // Validate disable billwerk error reporting
        $validated['disable_billwerk_error_reporting'] = ($input['disable_billwerk_error_reporting'] ?? 'off') === 'on' ? 'on' : 'off';
        
        // Validate Wordfence severity level
        $validated['wordfence_severity_level'] = sanitize_text_field($input['wordfence_severity_level'] ?? 'none');
        
        // Validate PageSpeed API key
        $validated['pagespeed_api_key'] = sanitize_text_field($input['pagespeed_api_key'] ?? '');
        
        // Validate PageSpeed threshold
        $validated['pagespeed_threshold'] = absint($input['pagespeed_threshold'] ?? 90);
        
        // Validate Cloudflare API key
        $validated['cloudflare_api_key'] = sanitize_text_field($input['cloudflare_api_key'] ?? '');
        
        // Validate menu slug
        $validated['menu'] = sanitize_text_field($input['menu'] ?? '');

        // Validate cPanel settings
        $validated['cpanel_hostname'] = sanitize_text_field($input['cpanel_hostname'] ?? '');
        $validated['cpanel_username'] = sanitize_text_field($input['cpanel_username'] ?? '');
        $validated['cpanel_token'] = sanitize_text_field($input['cpanel_token'] ?? '');
        
        // Validate check toggles
        $check_options = [
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
            'pagespeed',
            'woocommerce_orders',
            'check_permalinks',
            'check_under_attack_mode',
            'wordfence_waf',
            'check_acymailing',
            'check_404_redirects',
            'cpanel_usage_check'  // Add the new check option
        ];
        
        foreach ($check_options as $option) {
            $validated[$option] = !empty($input[$option]);
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
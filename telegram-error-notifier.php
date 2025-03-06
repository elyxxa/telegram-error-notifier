<?php
/*
* Plugin Name: Telegram Error Notifier
* Plugin URI: https://webkonsulenterne.dk
* Description: Telegram fatal error notifier for WordPress
* Version: 2.0.9
* Author: Md Rashedul Islam, Webkonsulenterne
* Author URI: https://webkonsulenterne.dk
* License: GPL2
* License URI: https://www.gnu.org/licenses/gpl-2.0.html
* Text Domain: telegram-error-notifier
* Domain Path: /languages
* Requires at least: 6.2
* Requires PHP: 7.4
*/

if (!defined('ABSPATH')) {
    exit;
}

// Add at the top after ABSPATH check
if (!defined('TEN_PLUGIN_VERSION')) {
    define('TEN_PLUGIN_VERSION', '1.9.5');
}
if (!defined('TEN_PLUGIN_DIR')) {
    define('TEN_PLUGIN_DIR', plugin_dir_path(__FILE__));
}
if (!defined('TEN_PLUGIN_URL')) {
    define('TEN_PLUGIN_URL', plugin_dir_url(__FILE__));
}

// Autoload classes using Composer
require_once plugin_dir_path(__FILE__) . 'vendor/autoload.php';

use Webkonsulenterne\TelegramErrorNotifier\Admin;
use Webkonsulenterne\TelegramErrorNotifier\Alert;
use Webkonsulenterne\TelegramErrorNotifier\Task;
use Webkonsulenterne\TelegramErrorNotifier\WooCommerceAlert;
use Webkonsulenterne\TelegramErrorNotifier\Wordfence;
use Webkonsulenterne\TelegramErrorNotifier\CloudflareApi;
use Webkonsulenterne\TelegramErrorNotifier\MenuChangeChecker;
use Webkonsulenterne\TelegramErrorNotifier\BackgroundProcess;
use Webkonsulenterne\TelegramErrorNotifier\Settings;

class WPTelegramErrorNotifier {
	private static $instance = null;
	private $alert;
	private $site_url;
	private $task;
	private $wordfence;
	private $menucheck;
	private $cf;
	private $background_process;
	private $settings;

    public function __construct() {
		$this->settings = Settings::get_instance();

		$this->site_url = $this->remove_www(get_site_url());

        // Initialize Alert first since other components depend on it
        $this->alert = new Alert();
        
        // Then initialize other components
        new Admin();
        $this->background_process = new BackgroundProcess($this->alert, $this->site_url);
        $this->cf = new CloudflareApi();
        $this->task = new Task($this->alert, $this->site_url, $this->cf, $this->background_process);

		if ($this->wordfence_installed()) {
			$this->wordfence = new Wordfence($this->alert, $this->site_url);
		}

		$this->menucheck = new MenuChangeChecker($this->alert);

        add_action('activated_plugin', [$this, 'plugin_activated'], 10, 2);
        add_action('deactivated_plugin', [$this, 'plugin_deactivated'], 10, 2);
        add_action('delete_plugin', [$this, 'plugin_deleted'], 10, 2);
        add_action('upgrader_process_complete', [$this, 'plugin_installed'], 10, 2);
		add_action('upgrader_process_complete', [$this, 'plugin_updated'], 10, 2);
        add_action('plugins_loaded', [$this, 'register_litespeed_hooks']);

		add_action('user_register', [$this, 'user_registered']);
		add_action('wp_login', [$this, 'user_logged_in'], 10, 2);

		register_activation_hook(__FILE__, array($this, 'on_activation'));
        register_deactivation_hook(__FILE__, array($this, 'on_deactivation'));

		// Initialize WooCommerce Notifications
		if ($this->is_plugin_active('woocommerce/woocommerce.php')) {
            new WooCommerceAlert($this->alert);
        }

        register_shutdown_function([$this, 'handle_shutdown']);

		// Email failure detection
        add_action('wp_mail_failed', [$this, 'notify_failed_wp_mail']);
		add_action('wp_login', [$this, 'track_admin_login'], 10, 2);

    }

	    // Prevent cloning
		private function __clone() {}

		// Prevent unserialization
		public function __wakeup() {
			throw new \Exception("Cannot unserialize singleton");
		}
	
		public static function get_instance(): self {
			if (null === self::$instance) {
				self::$instance = new self();
			}
			return self::$instance;
		}

	public function on_activation() {
		// Check minimum requirements
		if (version_compare(PHP_VERSION, '7.4', '<')) {
			deactivate_plugins(plugin_basename(__FILE__));
			wp_die('Telegram Error Notifier requires PHP 7.4 or higher.');
		}

		if (version_compare($GLOBALS['wp_version'], '6.2', '<')) {
			deactivate_plugins(plugin_basename(__FILE__));
			wp_die('Telegram Error Notifier requires WordPress 6.2 or higher.');
		}

		// Existing activation code...
		$this->task->schedule_daily_check();
		$this->task->schedule_hourly_check();
		$this->menucheck->schedule_daily_menu_check();
		if ($this->wordfence_installed()) {
			$this->wordfence->schedule_daily_alerts();
		}

		// Set default options if not exists
		$this->settings->set_defaults();
	}

	public function on_deactivation() {
		$this->task->clear_daily_check();
		$this->task->clear_hourly_check();
		$this->menucheck->clear_daily_menu_check();
		if ($this->wordfence_installed()) {
			$this->wordfence->clear_daily_alerts();
		}

		// Clear background process crons
		wp_clear_scheduled_hook('wp_pagespeed_score_check_cron');
		wp_clear_scheduled_hook('wp_telegram_alert_process_cron');
	}

	private function remove_www($url) {
        $parsed_url = wp_parse_url($url);
        $host = $parsed_url['host'];
        $host = preg_replace('/^www\./', '', $host);
        return $host;
    }

    public function handle_shutdown() {
        $error = error_get_last();
        if ($error !== NULL && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
            $errno   = $error["type"];
            $errfile = $error["file"];
            $errline = $error["line"];
			$errstr  = strtok($error["message"], "\n");
            $message = "Fatal Error [$errno]: $errstr in $errfile on line $errline";

            $this->record_error($message);
        }
    }

	private function record_error($message) {
		$alert_staging = $this->settings->get('alert_staging', 'off');
		$disable_error_reporting = $this->settings->get('disable_error_reporting', 'off');
		
		if ($disable_error_reporting === 'on') {
			return;
		}
	
		if (!$this->is_production_site() && $alert_staging !== 'on') {
			return;
		}
	
		$transient_key = 'telegram_fatal_error_' . md5($message);
		$expires_at = $this->get_next_8am_cet();
	
		if (!get_transient($transient_key)) {
			set_transient($transient_key, true, $expires_at);
			$this->alert->send_telegram_message($this->format_message($message));
		}
	}
	

    private function get_next_8am_cet() {
		$now = new DateTime("now", new DateTimeZone("CET"));
		$next_8am = new DateTime("08:00:00", new DateTimeZone("CET"));
		
		if ($now >= $next_8am) {
			// It's after 8 AM today, so calculate time to 8 AM tomorrow
			$next_8am->modify('+1 day');
		}
		
		// Calculate the difference in seconds
		$diff = $next_8am->getTimestamp() - $now->getTimestamp();
		
		return $diff;
	}
	
    private function is_production_site() {
        $parsed_url = wp_parse_url($this->site_url);
        $host = $parsed_url['path'];
        return (substr($host, 0, 4) === 'www.' || count(explode('.', $host)) === 2);
    }

    private function format_message($message) {
        $current_user = wp_get_current_user();
        $name = ($current_user->first_name && $current_user->last_name) ? $current_user->first_name . ' ' . $current_user->last_name : $current_user->user_email;
        return "$message\nUser: $name\nSite: $this->site_url";
    }

    public function plugin_activated($plugin, $network_wide) {
        $plugin_notifications = $this->settings->get('plugin_notifications', []);
        if (in_array('activation', $plugin_notifications, true)) {
            $plugin_data = get_plugin_data(WP_PLUGIN_DIR . '/' . $plugin);
            $this->alert->send_telegram_message($this->format_plugin_message("Plugin activated", $plugin_data), true);
            
            $this->background_process->push_to_queue(['url' => home_url()])->save()->dispatch();
        }
    }

    public function plugin_deactivated($plugin, $network_wide) {
        $plugin_notifications = $this->settings->get('plugin_notifications', []);
        if (in_array('deactivation', $plugin_notifications, true)) {
            $plugin_data = get_plugin_data(WP_PLUGIN_DIR . '/' . $plugin);
            $this->alert->send_telegram_message($this->format_plugin_message("Plugin deactivated", $plugin_data), true);
        }
    }

    public function plugin_deleted($plugin) {
        $plugin_notifications = $this->settings->get('plugin_notifications', []);
        if (in_array('deletion', $plugin_notifications, true)) {
            $plugin_data = get_plugin_data(WP_PLUGIN_DIR . '/' . $plugin);
            $this->alert->send_telegram_message($this->format_plugin_message("Plugin deleted", $plugin_data), true);
        }
    }

    public function plugin_installed($upgrader_object, $options) {
        if ($options['action'] === 'install' && $options['type'] === 'plugin') {
            $plugin_notifications = $this->settings->get('plugin_notifications', []);
            if (in_array('installation', $plugin_notifications, true)) {
                $plugin_data = $upgrader_object->new_plugin_data;
                $this->alert->send_telegram_message($this->format_plugin_message("Plugin installed", $plugin_data), true);
            }
        }
    }

	public function plugin_updated($upgrader_object, $options) {
		if ($options['action'] === 'update' && $options['type'] === 'plugin') {
			$plugin_notifications = $this->settings->get('plugin_notifications', []);
			if (in_array('update', $plugin_notifications, true)) {
				$plugin_data = $upgrader_object->new_plugin_data;
				if (empty($plugin_data)) {
					$plugin = $upgrader_object->skin->plugin;
					$plugin_data = get_plugin_data(WP_PLUGIN_DIR . '/' . $plugin);
				}
                $this->alert->send_telegram_message($this->format_plugin_message("Plugin updated", $plugin_data), true);
			}
		}
	}

    private function format_plugin_message($action, $plugin_data) {
        $current_user = wp_get_current_user();
        $name = ($current_user->first_name && $current_user->last_name) ? ($current_user->first_name . ' ' . $current_user->last_name) : ($current_user->user_email ? $current_user->user_email : 'System');

        return "$action: $plugin_data[Name]\nUser: $name\nVersion: {$plugin_data['Version']}\nSite: $this->site_url";
    }

    public function register_litespeed_hooks() {
        add_action('litespeed_purged_all', [$this, 'all_cache_purge_notify_telegram_channel']);
        add_action('wp_ajax_swcfpc_purge_whole_cache', [$this, 'all_cache_purge_notify_telegram_channel']);
		add_action('swcfpc_purge_all', [$this, 'all_cache_purge_notify_telegram_channel']);
		add_action('wp_ajax_swcfpc_purge_everything', [$this, 'all_cache_purge_notify_telegram_channel']);
    }

    public function all_cache_purge_notify_telegram_channel() {
        $current_user = wp_get_current_user();
		$current_ip = strtok($this->get_user_ip(), ',');
        $name = ($current_user->first_name && $current_user->last_name) ? ($current_user->first_name . ' ' . $current_user->last_name) : ($current_user->user_email ? $current_user->user_email : 'System Trigger');
        $message = "The cache for $this->site_url has been flushed by $name.\nIP: $current_ip\nPlease note that The site will be slower until the cache is fully rebuilt again.";
        $this->alert->send_telegram_message($message, true);
    }

    public function single_post_cache_purge_notify_telegram_channel($post_id) {
        $current_user = wp_get_current_user();
        $name = ($current_user->first_name && $current_user->last_name) ? $current_user->first_name . ' ' . $current_user->last_name : $current_user->user_email;
        $post_title = get_the_title($post_id);
        $message = "Cache purge triggered for post " . $post_title . " (" . $post_id . ") on $this->site_url by " . $name;
        $this->alert->send_telegram_message($message, true);
    }

	public function user_registered($user_id) {
        $user_notifications = $this->settings->get('user_notifications', []);
        if (in_array('registration', $user_notifications, true)) {
            $user_info = get_userdata($user_id);
            if ($user_info->roles[0] == 'administrator') {
                $message = sprintf(
                    "New user registration: %s (Email: %s, ID: %d, User Role: %s)\nSite: %s",
                    $user_info->user_login,
                    $user_info->user_email,
                    $user_info->ID,
                    $user_info->roles[0],
                    $this->site_url
                );
                $this->alert->send_telegram_message($message, true);
            }
        }
    }

    public function user_logged_in($user_login, $user) {
        if ($user->roles[0] == 'administrator') {
            $user_notifications = $this->settings->get('user_notifications', []);
            if (in_array('login', $user_notifications, true)) {
                $message = sprintf(
                    "User logged in: %s (Email: %s, ID: %d)\nSite: %s",
                    $user_login,
                    $user->user_email,
                    $user->ID,
                    $this->site_url
                );
                $this->alert->send_telegram_message($message, true);
            }
        }
    }

	public function wordfence_installed() {
		include_once(ABSPATH . 'wp-admin/includes/plugin.php');
        return is_plugin_active('wordfence/wordfence.php');
	}

    public function notify_failed_wp_mail($wp_error) {
        $error_data = $wp_error->get_error_data();
        $subject = isset($error_data['subject']) ? $error_data['subject'] : 'Unknown';
        $error_message = $wp_error->get_error_message();

        $message = "Email failed to send.\n";
        $message .= "Subject: " . $subject . "\n";
        $message .= "Error: " . $error_message;

        $this->alert->send_telegram_message($message, true);
    }

	private function is_danish_ip($ip) {
		// Use ip-api.com to check location (free tier allows 45 requests per minute)
		$response = wp_remote_get("http://ip-api.com/json/{$ip}?fields=countryCode");
		
		if (is_wp_error($response)) {
			return false;
		}
		
		$data = json_decode(wp_remote_retrieve_body($response), true);
		return isset($data['countryCode']) && $data['countryCode'] === 'DK';
	}

	private function get_ip_location($ip) {
		$response = wp_remote_get("http://ip-api.com/json/{$ip}?fields=country,city");
		
		if (is_wp_error($response)) {
			return 'Unknown';
		}
		
		$data = json_decode(wp_remote_retrieve_body($response), true);
		if (isset($data['city']) && isset($data['country'])) {
			return "{$data['city']}, {$data['country']}";
		}
		
		return 'Unknown';
	}

	public function track_admin_login($user_login, $user) {
		// Check if the user is an administrator
		if (!in_array('administrator', (array) $user->roles)) {
			return;
		}

		$current_ip = strtok($this->get_user_ip(), ',');
		$last_ip = strtok(get_user_meta($user->ID, 'last_known_ip', true), ',');

		// Skip if no previous IP exists
		if (!$last_ip || $current_ip === $last_ip) {
			update_user_meta($user->ID, 'last_known_ip', $current_ip);
			return;
		}

		// Check if IP is from Denmark
		$is_danish_ip = $this->is_danish_ip($current_ip);
		if ($is_danish_ip) {
			update_user_meta($user->ID, 'last_known_ip', $current_ip);
			return;
		}

		// If we get here, send the alert
		$message = sprintf(
			"⚠️ Admin Login Alert: User '%s' has logged in from a new IP address.\n" .
			"Previous IP: %s\n" .
			"Current IP: %s\n" .
			"Location: %s\n",
			$user_login,
			$last_ip,
			$current_ip,
			$this->get_ip_location($current_ip)
		);
		
		$this->alert->send_telegram_message($message, true);
		update_user_meta($user->ID, 'last_known_ip', $current_ip);
	}
	
	private function get_user_ip() {
		if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
			$ip = $_SERVER['HTTP_CLIENT_IP'];
		} elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
			$ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
		} else {
			$ip = $_SERVER['REMOTE_ADDR'];
		}
		return sanitize_text_field($ip);
	}
	
	private function is_plugin_active($plugin) {
        return in_array($plugin, apply_filters('active_plugins', get_option('active_plugins')), true);
    }

	public function check_plugin_health() {
        $issues = [];
        
        // Check if Telegram bot token is configured
        if (empty($this->settings->get('telegram_bot_token'))) {
            $issues[] = 'Telegram bot token is not configured';
        }
        
        // Check if chat ID is configured
        if (empty($this->settings->get('telegram_chat_id'))) {
            $issues[] = 'Telegram chat ID is not configured';
        }
        
        // Check if required cron events are scheduled
        if (!wp_next_scheduled('ten_daily_check')) {
            $issues[] = 'Daily check cron event is not scheduled';
        }
        
        if (!wp_next_scheduled('ten_hourly_check')) {
            $issues[] = 'Hourly check cron event is not scheduled';
        }
        
        return $issues;
    }

}

// Initialize the plugin
function telegram_error_notifier() {
    return WPTelegramErrorNotifier::get_instance();
}

// Start the plugin
telegram_error_notifier();
<?php
namespace Webkonsulenterne\TelegramErrorNotifier;

class Admin {

    private $settings;

    /**
     * Register the necessary hooks to add a settings page and register settings.
     */
    public function __construct() {
        $this->settings = Settings::get_instance();
        add_action('admin_menu', [$this, 'add_settings_page']);
        add_action('admin_init', [$this, 'register_settings']);
    }

    public function add_settings_page() {
        add_management_page(
            'Telegram Error Notifier Settings',
            'Telegram Error Notifier',
            'manage_options',
            'telegram-error-notifier',
            [$this, 'render_settings_page']
        );
    }

    public function register_settings() {
        register_setting(
            'wp_telegram_error_notifier', 
            'wp_telegram_error_notifier_settings',
            [$this->settings, 'validate_settings']
        );
    }

	public function render_settings_page() {
		$settings = $this->settings->get();
		?>
		<div class="wrap">
			<h2>Telegram Error Notifier Settings</h2>
			<form method="post" action="options.php">
				<?php settings_fields('wp_telegram_error_notifier'); ?>
				<table class="form-table">
					<tr>
						<th>Bot Token</th>
						<td>
							<input type="text" name="wp_telegram_error_notifier_settings[bot_token]" 
								value="<?php echo esc_attr($this->settings->get('bot_token', '')); ?>" class="regular-text">
						</td>
					</tr>
					<tr>
						<th>Chat ID</th>
						<td>
							<input type="text" name="wp_telegram_error_notifier_settings[chat_id]" 
								value="<?php echo esc_attr($this->settings->get('chat_id', '')); ?>" class="regular-text">
						</td>
					</tr>
					<tr>
						<th>Notification Interval</th>
						<td>
							<select name="wp_telegram_error_notifier_settings[notification_interval]">
								<option value="5minutes" <?php selected($this->settings->get('notification_interval', '5minutes')); ?>>Every 5 Minutes</option>
								<option value="hourly" <?php selected($this->settings->get('notification_interval', 'hourly')); ?>>Hourly</option>
								<option value="daily" <?php selected($this->settings->get('notification_interval', 'daily')); ?>>Daily</option>
								<option value="weekly" <?php selected($this->settings->get('notification_interval', 'weekly')); ?>>Weekly</option>
							</select>
						</td>
					</tr>
					<tr>
						<th>Alert on Staging Site</th>
						<td>
							<input type="checkbox" name="wp_telegram_error_notifier_settings[alert_staging]" value="on" <?php checked($this->settings->get('alert_staging', 'off'), 'on'); ?> />
						</td>
					</tr>
					<tr>
						<th>Plugin Notification Settings</th>
						<td>
							<input type="checkbox" name="wp_telegram_error_notifier_settings[plugin_notifications][]" 
								value="installation" <?php checked(in_array('installation', $this->settings->get('plugin_notifications', [])), true); ?> /> Installation<br>
							<input type="checkbox" name="wp_telegram_error_notifier_settings[plugin_notifications][]" 
								value="activation" <?php checked(in_array('activation', $this->settings->get('plugin_notifications', [])), true); ?> /> Activation<br>
							<input type="checkbox" name="wp_telegram_error_notifier_settings[plugin_notifications][]" 
								value="deactivation" <?php checked(in_array('deactivation', $this->settings->get('plugin_notifications', [])), true); ?> /> Deactivation<br>
							<input type="checkbox" name="wp_telegram_error_notifier_settings[plugin_notifications][]" 
								value="update" <?php checked(in_array('update', $this->settings->get('plugin_notifications', [])), true); ?> /> Update<br>
							<input type="checkbox" name="wp_telegram_error_notifier_settings[plugin_notifications][]" 
								value="deletion" <?php checked(in_array('deletion', $this->settings->get('plugin_notifications', [])), true); ?> /> Deletion<br>
						</td>
					</tr>
					<tr>
						<th>User Notification Settings</th>
						<td>
							<input type="checkbox" name="wp_telegram_error_notifier_settings[user_notifications][]" 
								value="login" <?php checked(in_array('login', $this->settings->get('user_notifications', [])), true); ?> /> Login<br>
							<input type="checkbox" name="wp_telegram_error_notifier_settings[user_notifications][]" 
								value="registration" <?php checked(in_array('registration', $this->settings->get('user_notifications', [])), true); ?> /> Registration<br>
						</td>
					</tr>
					<?php if ( in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ): ?>
					<tr>
						<th>Woocommerce Notification Settings</th>
						<td>
							<input type="checkbox" name="wp_telegram_error_notifier_settings[woocommerce_notifications][]" 
								value="add_to_cart" <?php checked(in_array('add_to_cart', $this->settings->get('woocommerce_notifications', [])), true); ?> /> Add to cart<br>
							<input type="checkbox" name="wp_telegram_error_notifier_settings[woocommerce_notifications][]" 
								value="order_placed" <?php checked(in_array('order_placed', $this->settings->get('woocommerce_notifications', [])), true); ?> /> Order placed<br>
							<input type="checkbox" name="wp_telegram_error_notifier_settings[woocommerce_notifications][]" 
								value="order_completed" <?php checked(in_array('order_completed', $this->settings->get('woocommerce_notifications', [])), true); ?> /> Order completed<br>
						</td>
					</tr>
					<?php endif; ?>
					<tr>
						<th>Disable Error Reporting</th>
						<td>
							<input type="checkbox" name="wp_telegram_error_notifier_settings[disable_error_reporting]" value="on" <?php checked($this->settings->get('disable_error_reporting', 'off'), 'on'); ?> />
						</td>
					</tr>
					<tr>
						<th>Disable Billwerk Error Reporting</th>
						<td>
							<input type="checkbox" name="wp_telegram_error_notifier_settings[disable_billwerk_error_reporting]" value="on" <?php checked($this->settings->get('disable_billwerk_error_reporting', 'off'), 'on'); ?> />
						</td>
					</tr>
					<tr>
						<th>Wordfence Security Alert Level</th>
						<td>
							<select name="wp_telegram_error_notifier_settings[wordfence_severity_level]">
								<option value="none" <?php selected($this->settings->get('wordfence_severity_level'), 'none'); ?>>None</option>
								<option value="low" <?php selected($this->settings->get('wordfence_severity_level'), 'low'); ?>>Low</option>
								<option value="medium" <?php selected($this->settings->get('wordfence_severity_level'), 'medium'); ?>>Medium</option>
								<option value="high" <?php selected($this->settings->get('wordfence_severity_level'), 'high'); ?>>High</option>
								<option value="critical" <?php selected($this->settings->get('wordfence_severity_level'), 'critical'); ?>>Critical</option>
							</select>
						</td>
					</tr>
					<!-- New fields for PageSpeed Integration -->
                    <tr>
                        <th>Google PageSpeed API Key</th>
                        <td>
                            <input type="text" name="wp_telegram_error_notifier_settings[pagespeed_api_key]" value="<?php echo esc_attr($this->settings->get('pagespeed_api_key', '')); ?>" class="regular-text" />
                        </td>
                    </tr>
                    <tr>
                        <th>PageSpeed Score Threshold</th>
                        <td>
                            <input type="number" name="wp_telegram_error_notifier_settings[pagespeed_threshold]" value="<?php echo esc_attr($this->settings->get('pagespeed_threshold', 90)); ?>" class="small-text" />
                        </td>
                    </tr>
					<!-- New fields for Cloudflare Integration -->
                    <tr>
                        <th>Cloudflare API Key</th>
                        <td>
                            <input type="password" name="wp_telegram_error_notifier_settings[cloudflare_api_key]" value="<?php echo esc_attr($this->settings->get('cloudflare_api_key', '')); ?>" class="regular-text" />
                        </td>
                    </tr>
					<!-- New fields for Menu Slug -->
                    <tr>
                        <th>Menu Slug</th>
                        <td>
                            <input type="text" name="wp_telegram_error_notifier_settings[menu]" value="<?php echo esc_attr($this->settings->get('menu', '')); ?>" class="regular-text" />
                        </td>
                    </tr>
					<tr>
						<th>Enable/Disable Checks</th>
						<td>
							<fieldset>
								<legend class="screen-reader-text">Enable/Disable Checks</legend>
								
								<label>
									<input type="checkbox" name="wp_telegram_error_notifier_settings[wordfence]" 
										value="1" <?php checked($this->settings->get('wordfence', true)); ?>>
									Wordfence Alerts
								</label><br>

								<label>
									<input type="checkbox" name="wp_telegram_error_notifier_settings[plugin_auto_updates]" 
										value="1" <?php checked($this->settings->get('plugin_auto_updates', true)); ?>>
									Plugin Auto-Updates Check
								</label><br>

								<label>
									<input type="checkbox" name="wp_telegram_error_notifier_settings[front_page_meta_robots]" 
										value="1" <?php checked($this->settings->get('front_page_meta_robots', true)); ?>>
									Front Page Meta Robots Check
								</label><br>

								<label>
									<input type="checkbox" name="wp_telegram_error_notifier_settings[sitemap]" 
										value="1" <?php checked($this->settings->get('sitemap', true)); ?>>
									Sitemap Check
								</label><br>

								<label>
									<input type="checkbox" name="wp_telegram_error_notifier_settings[cloudflare_cache]" 
										value="1" <?php checked($this->settings->get('cloudflare_cache', true)); ?>>
									Cloudflare Cache Check
								</label><br>

								<label>
									<input type="checkbox" name="wp_telegram_error_notifier_settings[billwerk_settings]" 
										value="1" <?php checked($this->settings->get('billwerk_settings', true)); ?>>
									Billwerk Settings Check
								</label><br>

								<label>
									<input type="checkbox" name="wp_telegram_error_notifier_settings[cloudflare_settings]" 
										value="1" <?php checked($this->settings->get('cloudflare_settings', true)); ?>>
									Cloudflare Settings Check
								</label><br>

								<label>
									<input type="checkbox" name="wp_telegram_error_notifier_settings[rankmath_redirect]" 
										value="1" <?php checked($this->settings->get('rankmath_redirect', true)); ?>>
									RankMath Redirect Module Check
								</label><br>

								<label>
									<input type="checkbox" name="wp_telegram_error_notifier_settings[web_fonts]" 
										value="1" <?php checked($this->settings->get('web_fonts', true)); ?>>
									Web Fonts Check
								</label><br>

								<label>
									<input type="checkbox" name="wp_telegram_error_notifier_settings[autoloaded_options]" 
										value="1" <?php checked($this->settings->get('autoloaded_options', true)); ?>>
									Autoloaded Options Size Check
								</label><br>

								<label>
									<input type="checkbox" name="wp_telegram_error_notifier_settings[avif_webp_images]" 
										value="1" <?php checked($this->settings->get('avif_webp_images', true)); ?>>
									AVIF/WebP Images Check
								</label><br>

								<label>
									<input type="checkbox" name="wp_telegram_error_notifier_settings[updraft_backups]" 
										value="1" <?php checked($this->settings->get('updraft_backups', true)); ?>>
									UpdraftPlus Backups Check
								</label><br>

								<label>
									<input type="checkbox" name="wp_telegram_error_notifier_settings[woocommerce_hpos]" 
										value="1" <?php checked($this->settings->get('woocommerce_hpos', true)); ?>>
									WooCommerce HPOS Check
								</label><br>

								<label>
									<input type="checkbox" name="wp_telegram_error_notifier_settings[pagespeed]" 
										value="1" <?php checked($this->settings->get('pagespeed', true)); ?>>
									PageSpeed Check
								</label><br>

								<label>
									<input type="checkbox" name="wp_telegram_error_notifier_settings[woocommerce_orders]" 
										value="1" <?php checked($this->settings->get('woocommerce_orders', true)); ?>>
									WooCommerce Order Monitor
								</label><br>

								<label>
									<input type="checkbox" name="wp_telegram_error_notifier_settings[check_permalinks]" 
										value="1" <?php checked($this->settings->get('check_permalinks', true)); ?>>
									Check Permalinks
								</label><br>

								<label>
									<input type="checkbox" name="wp_telegram_error_notifier_settings[check_under_attack_mode]" 
										value="1" <?php checked($this->settings->get('check_under_attack_mode', true)); ?>>
									Check Under Attack Mode
								</label><br>

								<label>
									<input type="checkbox" name="wp_telegram_error_notifier_settings[wordfence_waf]" 
										value="1" <?php checked($this->settings->get('wordfence_waf', true)); ?>>
									Wordfence WAF Status Check
								</label><br>

								<label>
									<input type="checkbox" name="wp_telegram_error_notifier_settings[check_acymailing]" 
										value="1" <?php checked($this->settings->get('check_acymailing', true)); ?>>
									AcyMailing Version Check
								</label>
							</fieldset>
						</td>
					</tr>
				</table>
				<?php submit_button(); ?>
			</form>
		</div>
		<style>
		.gsc-setting {
			padding: 10px;
			background: #f9f9f9;
			border: 1px solid #e5e5e5;
			border-radius: 4px;
		}
		.gsc-setting .description {
			margin: 8px 0 0;
		}
		</style>
		<?php
	}
	
	private function get_notification_intervals() {
		return [
			'5minutes' => 'Every 5 Minutes',
			'hourly' => 'Hourly',
			'daily' => 'Daily',
			'weekly' => 'Weekly'
		];
	}
}

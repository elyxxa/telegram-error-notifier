<?php
namespace Webkonsulenterne\TelegramErrorNotifier;

class Task {

	private $alert;
	private $site_url;
	private $cf;
	private $settings;
	private $background_process;

    public function __construct($alert, $site_url, $cf, $background_process) {
		$this->alert = $alert;
		$this->site_url = $site_url;
		$this->cf = $cf;
		$this->settings = Settings::get_instance();
		$this->background_process = $background_process;
		
		// Add the action for PageSpeed checks
		add_action('telegram_error_notifier_pagespeed_check', [$this, 'schedule_pagespeed_check']);
        add_action('telegram_error_notifier_daily_check', array($this, 'telegram_daily_task_checker'));
        add_action('telegram_error_notifier_hourly_check', array($this, 'telegram_hourly_task_checker'));
    }

    public function schedule_pagespeed_check() {
        if (!$this->settings->get('pagespeed', true)) {
            return;
        }
        
        // Get URLs to check
        $urls_to_check = $this->get_pagespeed_urls();
        if (empty($urls_to_check)) {
            return;
        }
        
        // Queue all URLs for processing in a single batch
        $batch_data = [
            'urls' => $urls_to_check,
            'threshold' => $this->settings->get('pagespeed_threshold', 90),
            'site_url' => $this->site_url,
            'attempts' => 3 // Number of times to check each URL
        ];
        
        $this->background_process->push_to_queue($batch_data);
        $this->background_process->save()->dispatch();
    }

    private function get_pagespeed_urls() {
        $urls = [];
        
        // Add homepage
        $urls['home'] = home_url();
        
        // Get oldest product URL
        $oldest_product = get_posts([
            'post_type' => 'product',
            'posts_per_page' => 1,
            'orderby' => 'date',
            'order' => 'ASC',
        ]);
        
        if (!empty($oldest_product)) {
            $urls['product'] = get_permalink($oldest_product[0]->ID);
        }
        
        // Get oldest category URL
        $oldest_category = get_terms([
            'taxonomy' => 'product_cat',
            'number' => 1,
            'orderby' => 'id',
            'order' => 'ASC',
        ]);
        
        if (!empty($oldest_category) && !is_wp_error($oldest_category)) {
            $urls['category'] = get_term_link($oldest_category[0]);
        }
        
        return $urls;
    }

    // Schedule the daily check
    public function schedule_daily_check() {
        if (!wp_next_scheduled('telegram_error_notifier_daily_check')) {
            $start_time = new \DateTime('08:00', new \DateTimeZone('CET'));
            wp_schedule_event($start_time->getTimestamp(), 'daily', 'telegram_error_notifier_daily_check');
        }

        // Add PageSpeed check schedule
        if (!wp_next_scheduled('telegram_error_notifier_pagespeed_check')) {
            $start_time = new \DateTime('09:00', new \DateTimeZone('CET'));
            wp_schedule_event($start_time->getTimestamp(), 'daily', 'telegram_error_notifier_pagespeed_check');
        }
    }

    // Clear the scheduled checks
    public function clear_daily_check() {
        $timestamp = wp_next_scheduled('telegram_error_notifier_daily_check');
        if ($timestamp) {
            wp_unschedule_event($timestamp, 'telegram_error_notifier_daily_check');
        }

        $pagespeed_timestamp = wp_next_scheduled('telegram_error_notifier_pagespeed_check');
        if ($pagespeed_timestamp) {
            wp_unschedule_event($pagespeed_timestamp, 'telegram_error_notifier_pagespeed_check');
        }
    }

	public function schedule_hourly_check() {
		if (!wp_next_scheduled('telegram_error_notifier_hourly_check')) {
			$start_time = new \DateTime('08:00', new \DateTimeZone('CET'));
			wp_schedule_event($start_time->getTimestamp(), 'hourly', 'telegram_error_notifier_hourly_check');
		}
	}

	public function clear_hourly_check() {
		$timestamp = wp_next_scheduled('telegram_error_notifier_hourly_check');
		if ($timestamp) {
			wp_unschedule_event($timestamp, 'telegram_error_notifier_hourly_check');
		}
	}

	// public function schedule_daily_check() {
	// 	if (!wp_next_scheduled('telegram_error_notifier_daily_check')) {
	// 		wp_schedule_event(time(), 'every_minute', 'telegram_error_notifier_daily_check');
	// 	}
	// }

    public function telegram_daily_task_checker() {
        include_once(ABSPATH . 'wp-admin/includes/plugin.php');
        
        if ($this->settings->get('wordfence', true)) {
            if (!is_plugin_active('wordfence/wordfence.php')) {
                $this->alert->send_telegram_message('Warning: The Wordfence plugin is currently not installed or activated, leaving the site more vulnerable to potential hacker attacks. For enhanced security, we recommend installing the plugin, which is available in a free version.', true);
            } else {
                $this->check_wordfence_waf_status();
            }
        }

        // Add check for 404 redirects
        if ($this->settings->get('check_404_redirects', true)) {
            $this->check_404_redirects();
        }

        if ($this->settings->get('plugin_auto_updates', true)) {
            $this->check_plugin_auto_updates();
        }

        if ($this->settings->get('front_page_meta_robots', true)) {
            $this->check_front_page_meta_robots();
        }

        if ($this->settings->get('sitemap', true)) {
            $this->check_sitemap_file();
        }

        if ($this->settings->get('cloudflare_cache', true)) {
            $this->check_cloudflare_cache();
        }

        if ($this->settings->get('billwerk_settings', true)) {
            $this->check_billwerk_settings();
        }

        if ($this->settings->get('cloudflare_settings', true)) {
            $this->check_cloudflare_settings();
        }

        if ($this->settings->get('rankmath_redirect', true)) {
            $this->check_rankmath_redirect_module();
        }

        if ($this->settings->get('web_fonts', true)) {
            $this->check_web_fonts();
        }

        if ($this->settings->get('autoloaded_options', true)) {
            $this->check_autoloaded_options_size();
        }

        if ($this->settings->get('avif_webp_images', true)) {
            $this->check_avif_webp_images();
        }

        if ($this->settings->get('updraft_backups', true)) {
            $this->check_updraft_backups();
        }

        if ($this->settings->get('woocommerce_hpos', true)) {
            $this->check_woocommerce_hpos();
        }

		if ($this->settings->get('woocommerce_orders', true)) {
            $this->check_woocommerce_orders();
        }

        if ($this->settings->get('wordfence_waf', true)) {
            $this->check_wordfence_waf_status();
        }

        if ($this->settings->get('acymailing_version', true)) {
            $this->check_acymailing_version();
        }
    }

	public function telegram_hourly_task_checker() {
        if ($this->settings->get('check_permalinks', true)) {
            $this->checkPermalinks();
        }
        
        if ($this->settings->get('check_under_attack_mode', true)) {
            $this->checkUnderAttackMode();
        }
	}

	public function check_front_page_meta_robots() {
		// Get the main site URL dynamically
		$front_page_url = home_url();
		
		// Fetch the front page HTML
		$response = wp_remote_get($front_page_url);
		
		if (is_wp_error($response)) {
			$message = "Failed to fetch front page content: " . $response->get_error_message();
			$this->alert->send_telegram_message($message, true);
			return;
		}
		
		// Use get_meta_tags to extract all meta tags
		$meta_tags = get_meta_tags($front_page_url);
		
		if (isset($meta_tags['robots'])) {
			$meta_robots = strtolower($meta_tags['robots']);
			
			// Check if 'noindex' is present in the content attribute
			if (strpos($meta_robots, 'noindex') !== false) {
				$message = "Warning: The front page is set to 'noindex' on {$this->site_url}.";
				$this->alert->send_telegram_message($message, true);
			}
		} else {
			$message = "No 'robots' meta tag found on the front page.";
			$this->alert->send_telegram_message($message, true);
		}
	}

	public function check_sitemap_file() {
        // Set the URL for the sitemap file.
        $sitemap_url = home_url('/sitemap_index.xml');
        $args = array(
            'headers' => array(
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36'
            )
        );
        // Use wp_remote_get to check if the file exists.
        $response = wp_remote_get($sitemap_url, $args);
        
        $status_code = wp_remote_retrieve_response_code($response);
        $ip_address = isset($_SERVER['SERVER_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['SERVER_ADDR'])) : 'Unknown';
    
        // Check if the request was successful.
        if (is_wp_error($response) || $status_code != 200) {
            $message = "Warning: The " . $this->site_url . "/sitemap_index.xml file is missing or not accessible on " . $this->site_url . "\nStatus Code: " . $status_code . "\nIP Address: " . $ip_address;
            $this->alert->send_telegram_message($message, true);
        }
    }

	public function check_cloudflare_cache() {
        $urls_to_check = $this->get_urls_for_cache_check();
        $cache_hits = 0;
        $total_urls = count($urls_to_check);
        $uncached_urls = [];

        foreach ($urls_to_check as $url_type => $url) {
            $response = wp_remote_get($url, ['timeout' => 30]);
            
            if (is_wp_error($response)) {
                continue;
            }

            $cache_status = wp_remote_retrieve_header($response, 'CF-Cache-Status');
            
            if ($cache_status === 'HIT') {
                $cache_hits++;
            } else {
                $uncached_urls[] = [
                    'type' => $url_type,
                    'url' => $url,
                    'status' => $cache_status ?: 'MISS'
                ];
            }
        }

        // Alert if we have 2 or fewer cache hits
        if ($cache_hits <= 2) {
            $message = sprintf(
                "⚠️ Warning: Low Cloudflare cache hit rate detected on %s.\n" .
                "Only %d out of %d checked pages were served from cache.\n\n" .
                "Uncached Pages:\n",
                $this->site_url,
                $cache_hits,
                $total_urls
            );

            foreach ($uncached_urls as $url_info) {
                $message .= sprintf(
                    "- %s (%s): %s\n",
                    ucfirst($url_info['type']),
                    $url_info['status'],
                    $url_info['url']
                );
            }

            $message .= "\nLow cache hits may lead to suboptimal loading speeds. Consider reviewing your caching configuration and page optimization settings.";
            
            $this->alert->send_telegram_message($message, true);
        }
    }

	private function get_urls_for_cache_check() {
        $urls = [];
        
        // Add homepage if it's not noindex
        if (!$this->is_page_noindex(home_url())) {
            $urls['homepage'] = home_url();
        }
        
        // Add 2 latest non-noindex posts
        $recent_posts = get_posts([
            'posts_per_page' => 4, // Get more to account for possible noindex posts
            'orderby' => 'date',
            'order' => 'DESC'
        ]);
        
        $post_count = 0;
        foreach ($recent_posts as $index => $post) {
            $post_url = get_permalink($post->ID);
            if (!$this->is_page_noindex($post_url)) {
                $urls['latest_post_' . ($post_count + 1)] = $post_url;
                $post_count++;
                if ($post_count >= 2) break; // Stop after getting 2 non-noindex posts
            }
        }
        
        // Add 3 random non-noindex pages
        $pages = get_posts([
            'post_type' => 'page',
            'posts_per_page' => 6, // Get more to account for possible noindex pages
            'orderby' => 'rand'
        ]);
        
        $page_count = 0;
        foreach ($pages as $index => $page) {
            $page_url = get_permalink($page->ID);
            if (!$this->is_page_noindex($page_url)) {
                $urls['page_' . ($page_count + 1)] = $page_url;
                $page_count++;
                if ($page_count >= 3) break; // Stop after getting 3 non-noindex pages
            }
        }
        
        return $urls;
    }

    private function is_page_noindex($url) {
        $response = wp_remote_get($url);
        if (is_wp_error($response)) {
            return false; // Consider page as indexable if we can't check
        }
        
        $body = wp_remote_retrieve_body($response);
        
        // Check meta robots tag
        if (preg_match('/<meta[^>]*name=["\']robots["\'][^>]*content=["\'][^"\']*noindex[^"\']*["\']/', $body)) {
            return true;
        }
        
        // Check X-Robots-Tag header
        $headers = wp_remote_retrieve_headers($response);
        if (isset($headers['x-robots-tag']) && stripos($headers['x-robots-tag'], 'noindex') !== false) {
            return true;
        }
        
        return false;
    }

	public function check_billwerk_settings() {
		if ($this->settings->get('disable_billwerk_error_reporting') === null) {
			return;
		}
		if ($this->settings->get('disable_billwerk_error_reporting') !== 'on') {
			include_once(ABSPATH . 'wp-admin/includes/plugin.php');
			if (is_plugin_active('reepay-checkout-gateway/reepay-woocommerce-payment.php')) {
				$gateway_settings = get_option('woocommerce_reepay_checkout_settings');
				if (!($gateway_settings['enable_sync'] == 'yes' && $gateway_settings['status_created'] == 'wc-pending' && $gateway_settings['status_authorized'] == 'wc-processing' && $gateway_settings['status_settled'] == 'wc-completed')) {
					$message = "Warning: Wrong Billwerk payment settings on {$this->site_url}\nPlease set the following settings: \nSync statuses to Enable sync, \nStatus: Billwerk+ Pay Created to 'Pending Payment', \nStatus: Billwerk+ Pay Authorized to 'Processing', \nStatus: Billwerk+ Pay Settled to 'Completed'.";
					$this->alert->send_telegram_message($message, true);
				}
			}
		}
	}

	public function check_cloudflare_settings() {
		if ($this->settings->get('cloudflare_api_key') == '') {
			$message = "Warning: Cloudflare API key is not set on {$this->site_url}.";
			$this->alert->send_telegram_message($message, true);
		} else {
			$isEnabled = $this->cf->isCacheReserveEnabled();
			if (!$isEnabled) {
				$message = "Warning: Cloudflare cache reserve is not enabled on {$this->site_url}.";
				$this->alert->send_telegram_message($message, true);
			}
		}
		
	}

	public function checkPermalinks() {
        $missingUrls = [
            'posts' => $this->checkPostTypeUrls('post', 5),
            'pages' => $this->checkPostTypeUrls('page', 5)
        ];

        // Only check WooCommerce products if WooCommerce is active
        if (in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
            $missingUrls['products'] = $this->checkPostTypeUrls('product', 5);
        }

        // Flatten missing URLs and send alert if there are any 404s
        $flatMissingUrls = array_merge(...array_values($missingUrls));
        if (!empty($flatMissingUrls)) {
            $this->sendAlert($flatMissingUrls);
        }
    }

    private function checkPostTypeUrls($postType, $limit) {
        // Skip check for deleted/archived event posts
        if ($postType === 'tribe_events') {
            return [];
        }

        $args = [
            'post_type' => $postType,
            'posts_per_page' => $limit,
            'post_status' => 'publish',
            'orderby' => 'date',
            'order' => 'DESC'
        ];
        
        $posts = get_posts($args);
        $missingUrls = [];

        foreach ($posts as $post) {
            $url = get_permalink($post);
            if ($this->isUrl404($url)) {
                $missingUrls[] = $url;
            }
        }

        return $missingUrls;
    }

    private function isUrl404($url) {
        $response = wp_remote_head($url);
        return wp_remote_retrieve_response_code($response) === 404;
    }

    private function sendAlert($missingUrls) {
        $message = "🚨 Permalink Check Alert: The following URLs returned 404 errors:\n";
        foreach ($missingUrls as $url) {
            $message .= "- $url\n";
        }
        $this->alert->send_telegram_message($message, true);
    }

	public function check_rankmath_redirect_module() {
		if (is_plugin_active('seo-by-rank-math/rank-math.php')) {
			$rankmath_modules = get_option('rank_math_modules', []);
	
			if (is_array($rankmath_modules) && in_array('redirections', $rankmath_modules)) {
				return;
			}
	
			$message = "Warning: The Rank Math Redirect module is not enabled on {$this->site_url}.";
			$this->alert->send_telegram_message($message, true);
		}
	}

	public function check_web_fonts() {
		$front_page_url = home_url();
		$response = wp_remote_get($front_page_url);
		
		if (is_wp_error($response)) {
			return;
		}
		
		$html = wp_remote_retrieve_body($response);
		
		// Initialize arrays for different resource types
		$failed_fonts = [];
		
		// Create DOM document
		$dom = new \DOMDocument();
		libxml_use_internal_errors(true);
		@$dom->loadHTML($html);
		libxml_clear_errors();
		
		// Check link elements (for preloaded fonts and stylesheets)
		$links = $dom->getElementsByTagName('link');
		foreach ($links as $link) {
			$rel = $link->getAttribute('rel');
			$href = $link->getAttribute('href');
			
			// Skip if not a font resource
			if (!in_array($rel, ['preload', 'stylesheet']) || empty($href)) {
				continue;
			}
			
			// Skip if it's not a font file
			$ext = strtolower(pathinfo($href, PATHINFO_EXTENSION));
			if (!in_array($ext, ['woff', 'woff2', 'ttf', 'otf', 'eot', 'css'])) {
				continue;
			}
			
			// Convert relative URLs to absolute
			$absolute_url = $this->make_absolute_url($href, $front_page_url);
			
			// Check if the resource loads
			$resource_response = wp_remote_head($absolute_url);
			if (is_wp_error($resource_response) || wp_remote_retrieve_response_code($resource_response) !== 200) {
				$failed_fonts[] = $absolute_url;
			}
		}
		
		// Check @font-face rules in stylesheets
		$styles = $dom->getElementsByTagName('style');
		foreach ($styles as $style) {
			$css = $style->nodeValue;
			if (preg_match_all('/url\([\'"]?([^\'")\s]+)[\'"]?\)/i', $css, $matches)) {
				foreach ($matches[1] as $font_url) {
					// Skip if it's not a font file
					$ext = strtolower(pathinfo($font_url, PATHINFO_EXTENSION));
					if (!in_array($ext, ['woff', 'woff2', 'ttf', 'otf', 'eot'])) {
						continue;
					}
					
					$absolute_url = $this->make_absolute_url($font_url, $front_page_url);
					$resource_response = wp_remote_head($absolute_url);
					if (is_wp_error($resource_response) || wp_remote_retrieve_response_code($resource_response) !== 200) {
						$failed_fonts[] = $absolute_url;
					}
				}
			}
		}
		
		// Send alert if there are failed font resources
		if (!empty($failed_fonts)) {
			$message = "🚨 Web Font Check Alert: The following font URLs failed to load properly:\n\n";
			foreach ($failed_fonts as $url) {
				$message .= "• {$url}\n";
			}
			$this->alert->send_telegram_message($message, true);
		}
	}

	private function make_absolute_url($url, $base_url) {
		// If URL is already absolute, return it
		if (strpos($url, 'http') === 0) {
			return $url;
		}
		
		// If URL starts with //, add https:
		if (strpos($url, '//') === 0) {
			return 'https:' . $url;
		}
		
		// If URL starts with /, add domain
		if (strpos($url, '/') === 0) {
			$parsed_base = parse_url($base_url);
			return $parsed_base['scheme'] . '://' . $parsed_base['host'] . $url;
		}
		
		// Otherwise, resolve relative to base URL
		return rtrim($base_url, '/') . '/' . ltrim($url, '/');
	}

	public function check_autoloaded_options_size() {
		global $wpdb;
		$query = $wpdb->prepare(
			"SELECT 'autoloaded data in KiB' as name, ROUND(SUM(OCTET_LENGTH(option_value)) / 1024) as value FROM {$wpdb->prefix}options WHERE autoload = %s",
			'yes'
		);
		$result = $wpdb->get_row($query);
		$size_kb = (int)($result->autoload_size / 1024); // Convert to KB and remove decimals
		
		if ($size_kb > 400) {
			$message = sprintf(
				"Information: The total size of autoloaded options in the database has reached %d KB, surpassing the recommended threshold of 400 KB. " .
				"Excessive autoloaded data can affect site performance, particularly for the backend and the checkout. " .
				"To maintain optimal performance, it is recommended to review and reduce autoloaded data.",
				$size_kb
			);
			$this->alert->send_telegram_message($message, true);
		}
	}

	public function check_avif_webp_images() {
		$front_page_url = home_url();
		$non_optimized_images = [];

		// Fetch the homepage content
		$response = wp_remote_get($front_page_url, [
			'timeout' => 30,
			'sslverify' => false
		]);

		if (is_wp_error($response)) {
			if (defined('WP_DEBUG') && WP_DEBUG) {
				error_log('Failed to fetch homepage: ' . $response->get_error_message());
			}
			return;
		}

		$html = wp_remote_retrieve_body($response);

		// Use DOMDocument to parse HTML and find images
		$dom = new \DOMDocument();
		libxml_use_internal_errors(true);
		@$dom->loadHTML($html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
		libxml_clear_errors();

		// Get all img elements
		$images = $dom->getElementsByTagName('img');
		
		foreach ($images as $img) {
			$src = $img->getAttribute('src');
			$srcset = $img->getAttribute('srcset');
			if (empty($src)) continue;

			// Make URL absolute if it's relative
			if (strpos($src, 'http') !== 0) {
				$src = $this->make_absolute_url($src, $front_page_url);
			}

			// Skip external images and tracking pixels
			if (!$this->is_internal_image($src, $front_page_url)) {
				continue;
			}

			// Check if image has AVIF/WebP in srcset
			$has_modern_format = false;
			if ($srcset) {
				$srcset_urls = explode(',', $srcset);
				foreach ($srcset_urls as $srcset_url) {
					if (strpos($srcset_url, '.avif') !== false || 
						strpos($srcset_url, '.webp') !== false) {
						$has_modern_format = true;
						break;
					}
				}
			}

			// Skip if image has modern format in srcset
			if ($has_modern_format) {
				continue;
			}

			// Check if image exists and get its MIME type
			$headers = get_headers($src, 1);
			if ($headers === false) continue;

			$content_type = is_array($headers['Content-Type']) 
				? $headers['Content-Type'][0] 
				: $headers['Content-Type'];

			// Skip if already WebP or AVIF
			if (strpos($content_type, 'image/webp') !== false || 
				strpos($content_type, 'image/avif') !== false) {
				continue;
			}

			// Get image dimensions
			$size = @getimagesize($src);
			$dimensions = $size ? $size[0] . 'x' . $size[1] : 'unknown';

			// Skip small images
			if ($size && ($size[0] < 200 || $size[1] < 200)) {
				continue;
			}

			$non_optimized_images[] = [
				'url' => $src,
				'mime' => $content_type,
				'dimensions' => $dimensions
			];
		}

		if (!empty($non_optimized_images)) {
			$message = "⚠️ The following homepage images lack AVIF/WebP versions:\n\n";
			foreach ($non_optimized_images as $image) {
				$message .= sprintf(
					"• %s\n  Size: %s\n  Type: %s\n",
					$image['url'],
					$image['dimensions'],
					$image['mime']
				);
			}
			$message .= "\nConsider optimizing these images to improve homepage performance.";
			
			$this->alert->send_telegram_message($message, true);
		}
	}

	private function is_internal_image($url, $site_url) {
		// Skip tracking pixels and external images
		$skip_domains = ['facebook.com', 'google.com', 'analytics'];
		
		foreach ($skip_domains as $domain) {
			if (strpos($url, $domain) !== false) {
				return false;
			}
		}

		// Check if image is from same domain
		$site_host = parse_url($site_url, PHP_URL_HOST);
		$image_host = parse_url($url, PHP_URL_HOST);
		
		return $site_host === $image_host;
	}

	public function checkUnderAttackMode() {
        if ($this->cf->isUnderAttackMode()) {
            $message = "⚠️ Warning: Cloudflare Under Attack Mode is enabled on {$this->site_url}\n";
            $message .= "This might affect user experience and should be disabled when the threat is over.";
            $this->alert->send_telegram_message($message, true);
        }
    }

	public function check_updraft_backups() {
        // Check if UpdraftPlus is active
        if (!$this->is_updraft_active()) {
            return;
        }

        // Get UpdraftPlus installation date
        $install_date = $this->get_updraft_install_date();
        if (!$install_date || (time() - $install_date) < (3 * DAY_IN_SECONDS)) {
            return; // Plugin is too new, skip check
        }

        // Get latest backup time
        $latest_backup = $this->get_latest_updraft_backup();
        if (!$latest_backup || (time() - $latest_backup) > (2 * DAY_IN_SECONDS)) {
            $message = sprintf(
                "⚠️ Warning: No recent UpdraftPlus backup found for %s.\nLast backup was taken: %s",
                $this->site_url,
                $latest_backup ? human_time_diff($latest_backup, time()) . ' ago' : 'never'
            );
            $this->alert->send_telegram_message($message, true);
        }
    }

	private function is_updraft_active() {
        include_once(ABSPATH . 'wp-admin/includes/plugin.php');
        return is_plugin_active('updraftplus/updraftplus.php');
    }

	private function get_updraft_install_date() {
        $install_time = get_option('updraft_install_time');
        if (!$install_time) {
            // Try to get from plugin file modification time as fallback
            $plugin_file = WP_PLUGIN_DIR . '/updraftplus/updraftplus.php';
            if (file_exists($plugin_file)) {
                $install_time = filemtime($plugin_file);
                update_option('updraft_install_time', $install_time);
            }
        }
        return $install_time;
    }

	private function get_latest_updraft_backup() {
        global $wpdb;
        
        // Try to get from UpdraftPlus options first
        $updraft_last_backup = get_option('updraft_last_backup');
        if ($updraft_last_backup && is_array($updraft_last_backup)) {
            return $updraft_last_backup['backup_time'];
        }

        // Fallback: Check backup files directly
        $backup_dir = WP_CONTENT_DIR . '/updraft';
        if (!is_dir($backup_dir)) {
            return false;
        }

        $latest_time = 0;
        $files = scandir($backup_dir);
        
        if ($files) {
            foreach ($files as $file) {
                if (preg_match('/backup_([\d-]+)_.*\.zip/', $file, $matches)) {
                    $backup_time = strtotime($matches[1]);
                    $latest_time = max($latest_time, $backup_time);
                }
            }
        }

        return $latest_time > 0 ? $latest_time : false;
    }

	public function check_woocommerce_hpos() {
        // Check if WooCommerce is active
        if (!$this->is_woocommerce_active()) {
            return;
        }

        try {
            $hpos_enabled = get_option('woocommerce_custom_orders_table_enabled') === 'yes';

            if (!$hpos_enabled) {
                $message = sprintf(
                    "ℹ️ Advice: The WooCommerce High-Performance Order Storage (HPOS) feature is not yet enabled on %s. " .
                    "Activating HPOS can enhance your site's performance when processing and managing orders. " .
                    "To enable HPOS, navigate to: WooCommerce > Settings > Advanced > Custom Data Stores. " .
                    "Before enabling this feature, please ensure all plugins of the site are compatible with HPOS to avoid potential conflicts.",
                    $this->site_url
                );
                $this->alert->send_telegram_message($message, true);
            }
        } catch (\Exception $e) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('Error checking WooCommerce HPOS: ' . $e->getMessage());
            }
        }
    }

    private function is_woocommerce_active() {
        include_once(ABSPATH . 'wp-admin/includes/plugin.php');
        return is_plugin_active('woocommerce/woocommerce.php');
    }

    public function check_plugin_auto_updates() {
        $auto_update_plugins = [];
        $all_plugins = get_plugins();
        
        // Get auto-update settings
        $auto_updates = (array) get_site_option('auto_update_plugins', []);
        
        foreach ($all_plugins as $plugin_file => $plugin_data) {
            // Check if plugin is in auto-update list
            if (in_array($plugin_file, $auto_updates)) {
                $auto_update_plugins[] = $plugin_data['Name'];
            }
        }

        if (!empty($auto_update_plugins)) {
            $message = "⚠️ Warning: The following plugins have auto-updates enabled on {$this->site_url}:\n";
            foreach ($auto_update_plugins as $plugin_name) {
                $message .= "- {$plugin_name}\n";
            }
            $message .= "\nAutomatic updates may cause compatibility issues or site breakage. Consider disabling auto-updates and managing updates manually.";
            
            $this->alert->send_telegram_message($message, true);
        }
    }

	private function check_woocommerce_orders() {
        // Check if WooCommerce is active first
        if (!$this->is_woocommerce_active()) {
            return;
        }

        // Only run during business hours (8 AM CET)
        $current_hour = (new \DateTime('now', new \DateTimeZone('CET')))->format('G');
        if ($current_hour != 8) {
            return;
        }

        try {
            // Get orders from the last 24 hours
            $now = new \DateTime('now', new \DateTimeZone('CET'));
            $yesterday = clone $now;
            $yesterday->modify('-1 day');
            
            $args = array(
                'date_created' => '>' . $yesterday->getTimestamp(),
                'status' => array('wc-processing', 'wc-completed', 'wc-on-hold'),
                'limit' => -1,
            );

            $orders = wc_get_orders($args);
            
            if (empty($orders)) {
                $message = sprintf(
                    "⚠️ No orders received in the last 24 hours on %s\n" .
                    "Period: %s to %s CET",
                    $this->site_url,
                    $yesterday->format('Y-m-d H:i:s'),
                    $now->format('Y-m-d H:i:s')
                );
                $this->alert->send_telegram_message($message, true);
            }
        } catch (\Exception $e) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('Error checking WooCommerce orders: ' . $e->getMessage());
            }
        }
    }

    public function check_wordfence_waf_status() {
        if (!$this->is_wordfence_active()) {
            return;
        }

        try {
            // Check WAF status and protection level
            $learning_mode = false;
            if (class_exists('\wfWAF')) {
                $waf = \wfWAF::getInstance();
                if ($waf) {
                    $learning_mode = $waf->isInLearningMode();
                }
            }

            $basic_firewall = get_option('wordfence_basicConfigured', false);
            $protection_level = get_option('wordfence_protectionLevel', 'basic');
            
            // Alert for Learning Mode
            if ($learning_mode) {
                $message = sprintf(
                    "⚠️ Warning: Wordfence Web Application Firewall (WAF) is currently in Learning Mode on %s. " .
                    "While this is normal for newly installed WAF, leaving it in Learning Mode reduces your site's security. " .
                    "We recommend reviewing and optimizing the firewall rules after the learning period.\n\n" .
                    "To optimize the WAF:\n" .
                    "1. Go to Wordfence > Firewall\n" .
                    "2. Click 'OPTIMIZE THE WORDFENCE FIREWALL'\n" .
                    "3. Follow the optimization steps",
                    $this->site_url
                );
                $this->alert->send_telegram_message($message, true);
            }
            
            // Alert for Basic Protection Level
            if ($protection_level === 'basic') {
                $message = sprintf(
                    "⚠️ Warning: Wordfence Firewall is running in Basic Protection Mode on %s. " .
                    "For maximum security, we recommend enabling Extended Protection Mode.\n\n" .
                    "To enable Extended Protection:\n" .
                    "1. Go to Wordfence > Firewall > Firewall Configuration\n" .
                    "2. Set Protection Level to 'Extended Protection'\n" .
                    "3. Save Changes",
                    $this->site_url
                );
                $this->alert->send_telegram_message($message, true);
            }
            
            // Alert for unconfigured basic firewall
            if (!$basic_firewall) {
                $message = sprintf(
                    "⚠️ Warning: Wordfence Basic Firewall Protection is not fully configured on %s. " .
                    "To maximize your site's security, please complete the basic firewall setup:\n\n" .
                    "1. Go to Wordfence > Firewall\n" .
                    "2. Click 'OPTIMIZE THE WORDFENCE FIREWALL'\n" .
                    "3. Follow the configuration steps",
                    $this->site_url
                );
                $this->alert->send_telegram_message($message, true);
            }
            
        } catch (\Exception $e) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('Error checking Wordfence WAF status: ' . $e->getMessage());
            }
        }
    }

    private function is_wordfence_active() {
        include_once(ABSPATH . 'wp-admin/includes/plugin.php');
        return is_plugin_active('wordfence/wordfence.php');
    }

    private function check_acymailing_version() {

        // Check if AcyMailing is installed
        if (!$this->is_acymailing_active()) {
            $message = "AcyMailing is not installed on this site.";
			$this->alert->send_telegram_message($message, true);
			return;
        }

        try {

            $is_enterprise = acym_level(ACYM_ENTERPRISE);

            // Send alert if not Enterprise
            if (!$is_enterprise) {
                $message = "Warning: The paid AcyMailing Enterprise license was downgraded to the free Starter license. Please investigate the cause of this fallback.";
                $this->alert->send_telegram_message($message, true);
            }
        } catch (\Exception $e) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('Error checking AcyMailing version: ' . $e->getMessage());
            }
        }
    }

    private function is_acymailing_active() {
        include_once(ABSPATH . 'wp-admin/includes/plugin.php');
        return is_plugin_active('acymailing/index.php');
    }

    /**
     * Check 404 redirects from wk_404_redirects option
     */
    private function check_404_redirects() {
        $redirects = get_option('wk_404_redirects', []);
        
        if (!empty($redirects)) {
            $message = "📋 404 Redirect Links Report for {$this->site_url}\n\n";
            $message .= "Found " . count($redirects) . " redirect(s):\n\n";
            
            foreach ($redirects as $index => $redirect) {
                $message .= "From: {$redirect['from']} To: {$redirect['to']}\n\n";
            }
            
            $this->alert->send_telegram_message($message, true);
        }
    }

}
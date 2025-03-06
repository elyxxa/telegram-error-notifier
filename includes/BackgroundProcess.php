<?php

namespace Webkonsulenterne\TelegramErrorNotifier;

use WP_Background_Process;

class BackgroundProcess extends WP_Background_Process {

    private $alert;
    private $site_url;
    private $settings;
    protected $action = 'pagespeed_score_check';
    private $check_history_key = 'pagespeed_check_history';
    protected $cron_interval = 3600; // Set to 1 hour in seconds

    public function __construct($alert, $site_url) {
        parent::__construct();
        $this->alert = $alert;
        $this->site_url = $site_url;
        $this->settings = Settings::get_instance();
    }

    protected function task($item) {
        if (!isset($item['urls']) || !is_array($item['urls'])) {
            return false;
        }

        $results = [
            'homepage' => [],
            'product' => [],
            'category' => []
        ];

        // Run checks multiple times for each URL
        for ($i = 0; $i < $item['attempts']; $i++) {
            foreach ($item['urls'] as $type => $url) {
                $score = $this->check_pagespeed_with_retry($url);
                if ($score !== false) {
                    $results[$type][] = $score;
                }
            }
            // Add delay between attempts
            sleep(2);
        }

        // Analyze results
        $analysis = [];
        foreach ($results as $type => $scores) {
            if (!empty($scores)) {
                $best_score = max($scores);
                if ($best_score < $item['threshold']) {
                    $analysis[] = [
                        'type' => $type,
                        'url' => $item['urls'][$type],
                        'best_score' => $best_score,
                        'all_scores' => $scores
                    ];
                }
            }
        }

        // Send alert if any issues found
        if (!empty($analysis)) {
            $message = sprintf(
                "⚠️ PageSpeed Performance Issues Detected for %s\n\n",
                $item['site_url']
            );

            foreach ($analysis as $result) {
                $message .= sprintf(
                    "%s page:\nURL: %s\nBest Score: %d\nAll Scores: %s\nYou can run a new speed test here: https://pagespeed.web.dev/analysis?url=%s\n\n",
                    ucfirst($result['type']),
                    $result['url'],
                    $result['best_score'],
                    implode(', ', $result['all_scores']),
                    urlencode($result['url'])
                );
            }

            $message .= "\nConsider optimizing these pages to improve performance.";
            $this->alert->send_telegram_message($message, true);
        }

        return false;
    }

    private function check_pagespeed_with_retry($url, $max_retries = 3) {
        $attempt = 0;
        while ($attempt < $max_retries) {
            $score = $this->check_pagespeed($url);
            if ($score !== false) {
                return $score;
            }
            $attempt++;
            if ($attempt < $max_retries) {
                sleep(5); // Wait 5 seconds before retrying
            }
        }
        return false;
    }

    private function check_pagespeed($url) {
        $api_key = $this->settings->get('pagespeed_api_key', '');
        if (empty($api_key)) {
            return false;
        }

        $pagespeed_url = add_query_arg([
            'url' => $url,
            'key' => $api_key,
            'strategy' => 'mobile',
        ], 'https://www.googleapis.com/pagespeedonline/v5/runPagespeed');

        $response = wp_remote_get($pagespeed_url, [
            'timeout' => 60,
        ]);

        if (is_wp_error($response)) {
            return false;
        }

        $data = json_decode(wp_remote_retrieve_body($response), true);
        return isset($data['lighthouseResult']['categories']['performance']['score']) 
            ? $data['lighthouseResult']['categories']['performance']['score'] * 100 
            : false;
    }

    private function was_recently_checked($url) {
        $history = get_transient($this->check_history_key) ?: [];
        $base_url = $this->normalize_url($url);
        
        return isset($history[$base_url]) && 
               (time() - $history[$base_url]['time']) < 3600;
    }

    private function record_check($url, $score) {
        $history = get_transient($this->check_history_key) ?: [];
        $base_url = $this->normalize_url($url);
        
        $history[$base_url] = [
            'time' => time(),
            'score' => $score
        ];

        set_transient($this->check_history_key, $history, DAY_IN_SECONDS);
    }

    private function normalize_url($url) {
        $base_url = strtok($url, '?');
        $base_url = strtok($base_url, '#');
        return untrailingslashit($base_url);
    }

    protected function complete() {
        parent::complete();
    }
}
<?php
namespace Webkonsulenterne\TelegramErrorNotifier;

class Wordfence {

    private $alert;
    private $site_url;
    private $settings;
    private $severity_levels = [
        'none' => 0,
        'low' => 25,
        'medium' => 50,
        'high' => 75,
        'critical' => 100,
    ];

    // Constructor
    public function __construct($alert, $site_url) {
        $this->alert = $alert;
        $this->site_url = $site_url;
        $this->settings = Settings::get_instance();

        // Hook for new alerts and daily scheduled alerts
        add_action('telegram_wordfence_daily_alert', [$this, 'send_daily_alerts']);
    }

    //Schedule daily alerts at 8 AM CET
    public function schedule_daily_alerts() {
        if (!wp_next_scheduled('telegram_wordfence_daily_alert')) {
            $start_time = new \DateTime('08:00', new \DateTimeZone('CET'));
            wp_schedule_event($start_time->getTimestamp(), 'daily', 'telegram_wordfence_daily_alert');
        }
    }

	public function clear_daily_alerts() {
        $timestamp = wp_next_scheduled('telegram_wordfence_daily_alert');
        if ($timestamp) {
            wp_unschedule_event($timestamp, 'telegram_wordfence_daily_alert');
        }
    }

	// public function schedule_daily_alerts() {
	// 	if (!wp_next_scheduled('telegram_wordfence_daily_alert')) {
	// 		wp_schedule_event(time(), 'every_minute', 'telegram_wordfence_daily_alert');
	// 	}
	// }

    // Check and send immediate alerts for new security issues
    public function check_security_alerts() {
        $scan_results = $this->get_wordfence_scan_results();
        $user_defined_severity = $this->get_user_defined_severity();

        foreach ($scan_results as $result) {
            if ($result['severity'] >= $user_defined_severity && $this->is_new_alert($result['issue_id'])) {
                $this->send_alert($result);
                $this->mark_alert_as_sent($result['issue_id']);
            }
        }
    }

    // Retrieve unresolved Wordfence scan results
	private function get_wordfence_scan_results() {
		global $wpdb;
		$wf_scan_results_table = $wpdb->prefix . 'wfissues';
	
		$query = $wpdb->prepare(
			"SELECT * FROM {$wf_scan_results_table} WHERE status = %s AND severity >= %d",
			'new',
			$this->get_user_defined_severity()
		);
		
		$results = $wpdb->get_results($query);
		
		$alerts = [];
		foreach ($results as $result) {
			$alerts[] = [
				'issue_id' => $result->id,
				'severity' => $result->severity,
				'issue' => $result->shortMsg
			];
		}
		return $alerts;
	}
	

    // Get user-defined severity level for sending alerts
    private function get_user_defined_severity() {
        return $this->severity_levels[$this->settings->get('wordfence_severity_level', 'medium')];
    }

    // Send alert message to Telegram
    private function send_alert($result) {
        $message = sprintf(
            "Wordfence Security Alert: %s\nSeverity: %s\nSite: %s",
            $result['issue'],
            $this->severity_name($result['severity']),
            $this->site_url
        );
        $this->alert->send_telegram_message($message, true);
    }

    // Helper function to convert severity number to name
    private function severity_name($severity) {
        return strtoupper(array_search($severity, $this->severity_levels));
    }

    // Check if an alert has already been sent
    private function is_new_alert($issue_id) {
		$existing_alerts = get_option('wp_sent_wordfence_alerts', []);
        return !in_array( $issue_id, $existing_alerts );
    }

    // Mark an alert as sent
    private function mark_alert_as_sent($issue_id) {
		$existing_alerts = get_option('wp_sent_wordfence_alerts', []);
        $existing_alerts[] = $issue_id;
        update_option('wp_sent_wordfence_alerts', $existing_alerts);
    }

    // Send daily summary of unresolved issues
    public function send_daily_alerts() {
        $scan_results = $this->get_wordfence_scan_results();
        if (!empty($scan_results)) {
            $message = "Wordfence Security Alert Summary:\n\n";
            foreach ($scan_results as $result) {
                $message .= sprintf(
                    "- %s (Severity: %s)\n\n",
                    $result['issue'],
                    $this->severity_name($result['severity'])
                );
            }
            $message .= "\nSite: " . $this->site_url;
            $this->alert->send_telegram_message($message, true);
        }
    }
}

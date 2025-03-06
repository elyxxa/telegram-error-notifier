<?php
namespace Webkonsulenterne\TelegramErrorNotifier;

class BackgroundAlert extends \WP_Background_Process {
    protected $action = 'telegram_alert_process';
    protected $cron_interval = 3600; // Set to 1 hour in seconds
    private $settings;
    private $api_base = 'https://api.telegram.org/bot';

    public function __construct() {
        parent::__construct();
        $this->settings = Settings::get_instance();
    }

    protected function task($message) {
        $this->send_telegram_message($message);
        return false;
    }

    protected function complete() {
        parent::complete();
    }

    private function send_telegram_message($message) {
        $bot_token = $this->settings->get('bot_token');
        $chat_id = $this->settings->get('chat_id');
        
        if (empty($bot_token) || empty($chat_id)) {
            return false;
        }

        $url = $this->api_base . $bot_token . '/sendMessage';
        $args = [
            'body' => [
                'chat_id' => $chat_id,
                'text' => $message,
                'parse_mode' => 'HTML'
            ],
            'timeout' => 30,
            'blocking' => true,
            'headers' => [
                'Content-Type' => 'application/x-www-form-urlencoded'
            ]
        ];

        $response = wp_remote_post($url, $args);

        if (is_wp_error($response)) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('Telegram API Error: ' . $response->get_error_message());
            }
            return false;
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        return isset($data['ok']) && $data['ok'] === true;
    }
} 
<?php
namespace Webkonsulenterne\TelegramErrorNotifier;

class Alert {
    private $settings;
    private $api_base = 'https://api.telegram.org/bot';
    private static $background_process = null;

    public function __construct() {
        $this->settings = Settings::get_instance();
        if (self::$background_process === null) {
            self::$background_process = new BackgroundAlert();
        }
    }

    public function send_telegram_message($message, $background = false) {
        if ($background) {
            return $this->send_in_background($message);
        }
        return $this->send_immediate($message);
    }

    private function send_in_background($message) {
        self::$background_process->push_to_queue($message)->save();
        self::$background_process->dispatch();
        return true;
    }

    private function send_immediate($message) {
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
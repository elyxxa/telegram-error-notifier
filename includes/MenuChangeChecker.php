<?php

namespace Webkonsulenterne\TelegramErrorNotifier;

class MenuChangeChecker {
    private $alert;
    private $settings;
    private $menu_slug;
    private $option_key;
    private $last_notification_key;

    public function __construct($alert) {
        $this->alert = $alert;
        $this->settings = Settings::get_instance();
        $this->menu_slug = $this->settings->get('menu');
        $this->option_key = 'saved_menu_' . $this->menu_slug;
        $this->last_notification_key = 'last_menu_notification_' . $this->menu_slug;

        add_action('wp_update_nav_menu', [$this, 'menuUpdated']);
        add_action('wp_create_nav_menu', [$this, 'menuCreated']);
        add_action('wp_delete_nav_menu', [$this, 'menuDeleted']);
    }

    public function schedule_daily_menu_check() {
        if (!wp_next_scheduled('telegram_menu_change_notifier_daily_check')) {
            $start_time = new \DateTime('08:00', new \DateTimeZone('CET'));
            wp_schedule_event($start_time->getTimestamp(), 'daily', 'telegram_menu_change_notifier_daily_check');
        }
    }

    public function clear_daily_menu_check() {
        $timestamp = wp_next_scheduled('telegram_menu_change_notifier_daily_check');
        if ($timestamp) {
            wp_unschedule_event($timestamp, 'telegram_menu_change_notifier_daily_check');
        }
    }

    private function shouldThrottleNotification() {
        $last_notification = get_transient($this->last_notification_key);
        if ($last_notification) {
            return true;
        }
        
        set_transient($this->last_notification_key, time(), 5 * MINUTE_IN_SECONDS);
        return false;
    }

    public function menuUpdated($menu_id) {
        if ($this->shouldThrottleNotification()) {
            return;
        }

        $menu = wp_get_nav_menu_object($menu_id);
        if ($menu && $menu->slug === $this->menu_slug) {
            $saved_menu = get_option($this->option_key, '');
            $current_menu = $this->getCurrentMenuItems();

            if ($current_menu !== $saved_menu) {
                $diff = $this->get_menu_diff($saved_menu, $current_menu);
                $message = $this->format_menu_change_message($diff);
                $this->alert->send_telegram_message($message, true);
                $this->saveCurrentMenu();
            }
        }
    }

    public function menuCreated($menu_id) {
        if ($this->shouldThrottleNotification()) {
            return;
        }

        $menu = wp_get_nav_menu_object($menu_id);
        if ($menu) {
            $message = "New menu '{$menu->name}' has been created";
            $this->alert->send_telegram_message($message, true);
        }
    }

    public function menuDeleted($menu_id) {
        if ($this->shouldThrottleNotification()) {
            return;
        }

        $message = "A menu has been deleted (ID: {$menu_id})";
        $this->alert->send_telegram_message($message, true);
    }

    private function getCurrentMenuItems() {
        $locations = get_nav_menu_locations();
        if (!isset($locations[$this->menu_slug])) {
            return '';
        }

        $menu = wp_get_nav_menu_object($locations[$this->menu_slug]);
        if (!$menu) {
            return '';
        }

        return wp_json_encode(wp_get_nav_menu_items($menu->term_id));
    }

    private function saveCurrentMenu() {
        update_option($this->option_key, $this->getCurrentMenuItems());
    }

    private function get_menu_diff($old_menu, $new_menu) {
        $old_items = json_decode($old_menu, true) ?: [];
        $new_items = json_decode($new_menu, true) ?: [];
        
        $added = array_udiff($new_items, $old_items, function($a, $b) {
            return $a['ID'] - $b['ID'];
        });
        
        $removed = array_udiff($old_items, $new_items, function($a, $b) {
            return $a['ID'] - $b['ID'];
        });
        
        return [
            'added' => $added,
            'removed' => $removed
        ];
    }

    private function format_menu_change_message($diff) {
        $message = "Menu item changed for '{$this->menu_slug}'\n";
        if (!empty($diff['added'])) {
            $message .= "Added items:\n";
            foreach ($diff['added'] as $item) {
                $message .= "- {$item['title']} (ID: {$item['ID']})\n";
            }
        }
        if (!empty($diff['removed'])) {
            $message .= "Removed items:\n";
            foreach ($diff['removed'] as $item) {
                $message .= "- {$item['title']} (ID: {$item['ID']})\n";
            }
        }
        return $message;
    }
}

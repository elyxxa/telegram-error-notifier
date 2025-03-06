<?php
namespace Webkonsulenterne\TelegramErrorNotifier;

class WooCommerceAlert {
    private $alert;
    private $settings;
    private $site_url;

    public function __construct(Alert $alert) {
        $this->alert = $alert;
        $this->settings = Settings::get_instance();
        $this->site_url = $this->remove_www(get_site_url());

        // WooCommerce hooks
        add_action('woocommerce_add_to_cart', [$this, 'product_added_to_cart']);
        add_action('woocommerce_thankyou', [$this, 'order_placed']);
        add_action('woocommerce_order_status_completed', [$this, 'payment_completed']);
    }

    public function product_added_to_cart($cart_item_key) {
        if (!$this->should_send_notification('add_to_cart')) {
            return;
        }

        $cart = WC()->cart->get_cart();
        $item = $cart[$cart_item_key];
        $product = wc_get_product($item['product_id']);
        
        $message = sprintf(
            "Product added to cart:\n%s\nQuantity: %d\nSite: %s",
            $product->get_name(),
            $item['quantity'],
            $this->site_url
        );
        
        $this->alert->send_telegram_message($message, true);
    }

    public function order_placed($order_id) {
        if (!$this->should_send_notification('order_placed')) {
            return;
        }

        $order = wc_get_order($order_id);
        if (!$order) {
            return;
        }

        $message = sprintf(
            "New order placed: #%d\nTotal: %s %s\nUser: %s\nSite: %s",
            $order_id,
            $order->get_total(),
            $order->get_currency(),
            $order->get_billing_email(),
            $this->site_url
        );

        $this->alert->send_telegram_message($message, true);
    }

    public function payment_completed($order_id) {
        if (!$this->should_send_notification('payment_completed')) {
            return;
        }

        $order = wc_get_order($order_id);
        $message = sprintf(
            "Payment completed for order ID: %d\nTotal: %s %s\nUser: %s\nSite: %s",
            $order_id,
            $order->get_total(),
            $order->get_currency(),
            $order->get_billing_email(),
            $this->site_url
        );

        $this->alert->send_telegram_message($message, true);
    }

    private function should_send_notification($type) {
        $notifications = $this->settings->get('woocommerce_notifications', []);
        return !empty($notifications) && in_array($type, $notifications);
    }

    private function remove_www($url) {
        $parsed_url = wp_parse_url($url);
        $host = $parsed_url['host'];
        return preg_replace('/^www\./', '', $host);
    }
}

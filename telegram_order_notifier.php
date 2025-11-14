<?php
/**
 * Telegram WooCommerce Order Notifier Script
 * This script can be placed on a third-party server and run as a cron job
 * 
 * Usage:
 * 1. Fill config.php with your information
 * 2. Upload this file to your third-party server
 * 3. Set up a cron job to run this file every 1-5 minutes:
 *    */5 * * * * /usr/bin/php /path/to/telegram_order_notifier.php
 * 
 * Or you can run it manually from your web host:
 * https://your-third-server.com/telegram_order_notifier.php
 */

// Load configuration
require_once __DIR__ . '/config.php';

// Main class
class WooCommerceTelegramNotifier {
    
    private $wp_site_url;
    private $wp_api_user;
    private $wp_api_pass;
    private $telegram_bot_token;
    private $telegram_chat_id;
    private $log_file;
    
    public function __construct() {
        $this->wp_site_url = WP_SITE_URL;
        $this->wp_api_user = WP_API_USER;
        $this->wp_api_pass = WP_API_PASS;
        $this->telegram_bot_token = TELEGRAM_BOT_TOKEN;
        $this->telegram_chat_id = TELEGRAM_CHAT_ID;
        $this->log_file = LOG_FILE;
        
        // Validate configuration
        if (empty($this->wp_site_url) || 
            empty($this->wp_api_user) || 
            empty($this->wp_api_pass) || 
            empty($this->telegram_bot_token) || 
            empty($this->telegram_chat_id)) {
            $this->log_error('Configuration is incomplete. Please check config.php file.');
            die('Error: Configuration is incomplete. Please check config.php file.');
        }
    }
    
    /**
     * Main script execution
     */
    public function run() {
        // Get new orders
        $orders = $this->get_new_orders();
        
        if (empty($orders)) {
            $this->log('No new orders found.');
            return;
        }
        
        // Send notification for each new order
        foreach ($orders as $order) {
            if ($this->is_order_sent($order['id'])) {
                continue; // Already sent
            }
            
            $message = $this->format_order_message($order);
            if ($this->send_telegram_message($message)) {
                $this->mark_order_as_sent($order['id']);
                $this->log("Order #{$order['id']} sent successfully.");
            } else {
                $this->log_error("Error sending order #{$order['id']}");
            }
            
            // Short delay to prevent rate limiting
            sleep(1);
        }
    }
    
    /**
     * Get new orders from WordPress REST API
     */
    private function get_new_orders() {
        $url = rtrim($this->wp_site_url, '/') . '/wp-json/wc/v3/orders';
        $url .= '?status=pending,processing,on-hold&per_page=10&orderby=date&order=desc';
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        curl_setopt($ch, CURLOPT_USERPWD, $this->wp_api_user . ':' . $this->wp_api_pass);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            $this->log_error("Error connecting to WordPress: " . $error);
            return array();
        }
        
        if ($http_code !== 200) {
            $this->log_error("Error fetching orders. HTTP Code: " . $http_code);
            return array();
        }
        
        $orders = json_decode($response, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->log_error("JSON parsing error: " . json_last_error_msg());
            return array();
        }
        
        return is_array($orders) ? $orders : array();
    }
    
    /**
     * Format order message
     */
    private function format_order_message($order) {
        $order_id = isset($order['id']) ? $order['id'] : 'N/A';
        $order_total = isset($order['total']) ? $order['total'] : '0';
        $order_currency = isset($order['currency']) ? $order['currency'] : '';
        
        $billing = isset($order['billing']) ? $order['billing'] : array();
        $billing_name = trim((isset($billing['first_name']) ? $billing['first_name'] : '') . ' ' . 
                            (isset($billing['last_name']) ? $billing['last_name'] : ''));
        $billing_email = isset($billing['email']) ? $billing['email'] : 'N/A';
        $billing_phone = isset($billing['phone']) ? $billing['phone'] : 'N/A';
        
        $order_date = isset($order['date_created']) ? date('Y/m/d H:i', strtotime($order['date_created'])) : 'N/A';
        $payment_method = isset($order['payment_method_title']) ? $order['payment_method_title'] : 'N/A';
        $order_status = isset($order['status']) ? $this->translate_status($order['status']) : 'N/A';
        
        // Product list
        $items = isset($order['line_items']) ? $order['line_items'] : array();
        $items_list = '';
        foreach ($items as $item) {
            $item_name = isset($item['name']) ? $item['name'] : 'Product';
            $item_qty = isset($item['quantity']) ? $item['quantity'] : 1;
            $items_list .= "• " . $item_name . " (Qty: " . $item_qty . ")\n";
        }
        
        // Shipping address
        $shipping = isset($order['shipping']) ? $order['shipping'] : array();
        $shipping_address = '';
        if (!empty($shipping)) {
            $address_parts = array();
            if (!empty($shipping['address_1'])) $address_parts[] = $shipping['address_1'];
            if (!empty($shipping['address_2'])) $address_parts[] = $shipping['address_2'];
            if (!empty($shipping['city'])) $address_parts[] = $shipping['city'];
            if (!empty($shipping['state'])) $address_parts[] = $shipping['state'];
            if (!empty($shipping['postcode'])) $address_parts[] = $shipping['postcode'];
            if (!empty($shipping['country'])) $address_parts[] = $shipping['country'];
            $shipping_address = implode(', ', $address_parts);
        }
        
        $message = "🛒 *New Order*\n\n";
        $message .= "📋 *Order ID:* #" . $order_id . "\n";
        $message .= "👤 *Customer:* " . ($billing_name ?: 'N/A') . "\n";
        $message .= "📧 *Email:* " . $billing_email . "\n";
        $message .= "📱 *Phone:* " . $billing_phone . "\n";
        $message .= "📅 *Date:* " . $order_date . "\n";
        $message .= "💳 *Payment Method:* " . $payment_method . "\n";
        $message .= "📊 *Status:* " . $order_status . "\n";
        $message .= "💰 *Total:* " . number_format((float)$order_total, 2) . " " . $order_currency . "\n\n";
        
        if (!empty($items_list)) {
            $message .= "*Products:*\n" . $items_list;
        }
        
        if (!empty($shipping_address)) {
            $message .= "\n*Shipping Address:*\n" . $shipping_address;
        }
        
        return $message;
    }
    
    /**
     * Translate order status
     */
    private function translate_status($status) {
        $statuses = array(
            'pending' => 'Pending Payment',
            'processing' => 'Processing',
            'on-hold' => 'On Hold',
            'completed' => 'Completed',
            'cancelled' => 'Cancelled',
            'refunded' => 'Refunded',
            'failed' => 'Failed'
        );
        
        return isset($statuses[$status]) ? $statuses[$status] : $status;
    }
    
    /**
     * Send message to Telegram
     */
    private function send_telegram_message($message) {
        $url = "https://api.telegram.org/bot{$this->telegram_bot_token}/sendMessage";
        
        $data = array(
            'chat_id' => $this->telegram_chat_id,
            'text' => $message,
            'parse_mode' => 'Markdown',
            'disable_web_page_preview' => true
        );
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            $this->log_error("Error sending to Telegram: " . $error);
            return false;
        }
        
        if ($http_code !== 200) {
            $this->log_error("Error sending to Telegram. HTTP Code: " . $http_code);
            return false;
        }
        
        $result = json_decode($response, true);
        
        if (isset($result['ok']) && $result['ok'] === true) {
            return true;
        } else {
            $error_msg = isset($result['description']) ? $result['description'] : 'Unknown error';
            $this->log_error("Telegram API error: " . $error_msg);
            return false;
        }
    }
    
    /**
     * Check if order has already been sent
     */
    private function is_order_sent($order_id) {
        if (!file_exists($this->log_file)) {
            return false;
        }
        
        $sent_orders = file($this->log_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        return in_array($order_id, $sent_orders);
    }
    
    /**
     * Mark order as sent
     */
    private function mark_order_as_sent($order_id) {
        file_put_contents($this->log_file, $order_id . "\n", FILE_APPEND | LOCK_EX);
    }
    
    /**
     * Log message
     */
    private function log($message) {
        $timestamp = date('Y-m-d H:i:s');
        $log_message = "[{$timestamp}] {$message}\n";
        error_log($log_message, 3, __DIR__ . '/notifier.log');
    }
    
    /**
     * Log error
     */
    private function log_error($message) {
        $timestamp = date('Y-m-d H:i:s');
        $log_message = "[{$timestamp}] ERROR: {$message}\n";
        error_log($log_message, 3, __DIR__ . '/notifier.log');
    }
}

// Execute script
try {
    $notifier = new WooCommerceTelegramNotifier();
    $notifier->run();
    echo "Script executed successfully.\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    error_log("Fatal error: " . $e->getMessage());
}

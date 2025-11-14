<?php
/**
 * اسکریپت ارسال اعلان سفارشات ووکامرس به تلگرام
 * این اسکریپت را می‌توانید در سرور ثالث قرار دهید و به صورت cron job اجرا کنید
 * 
 * نحوه استفاده:
 * 1. فایل config.php را با اطلاعات خود پر کنید
 * 2. این فایل را در سرور ثالث آپلود کنید
 * 3. یک cron job تنظیم کنید که هر 1-5 دقیقه این فایل را اجرا کند:
 *    */5 * * * * /usr/bin/php /path/to/telegram_order_notifier.php
 * 
 * یا می‌توانید از وب هاست خود به صورت دستی اجرا کنید:
 * https://your-third-server.com/telegram_order_notifier.php
 */

// بارگذاری تنظیمات
require_once __DIR__ . '/config.php';

// کلاس اصلی
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
        
        // بررسی تنظیمات
        if (empty($this->wp_site_url) || 
            empty($this->wp_api_user) || 
            empty($this->wp_api_pass) || 
            empty($this->telegram_bot_token) || 
            empty($this->telegram_chat_id)) {
            $this->log_error('تنظیمات ناقص است. لطفاً فایل config.php را بررسی کنید.');
            die('Error: تنظیمات ناقص است. لطفاً فایل config.php را بررسی کنید.');
        }
    }
    
    /**
     * اجرای اصلی اسکریپت
     */
    public function run() {
        // دریافت آخرین سفارشات
        $orders = $this->get_new_orders();
        
        if (empty($orders)) {
            $this->log('هیچ سفارش جدیدی یافت نشد.');
            return;
        }
        
        // ارسال اعلان برای هر سفارش جدید
        foreach ($orders as $order) {
            if ($this->is_order_sent($order['id'])) {
                continue; // قبلاً ارسال شده
            }
            
            $message = $this->format_order_message($order);
            if ($this->send_telegram_message($message)) {
                $this->mark_order_as_sent($order['id']);
                $this->log("سفارش #{$order['id']} با موفقیت ارسال شد.");
            } else {
                $this->log_error("خطا در ارسال سفارش #{$order['id']}");
            }
            
            // تاخیر کوتاه برای جلوگیری از rate limit
            sleep(1);
        }
    }
    
    /**
     * دریافت سفارشات جدید از REST API وردپرس
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
            $this->log_error("خطا در اتصال به وردپرس: " . $error);
            return array();
        }
        
        if ($http_code !== 200) {
            $this->log_error("خطا در دریافت سفارشات. کد HTTP: " . $http_code);
            return array();
        }
        
        $orders = json_decode($response, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->log_error("خطا در پردازش JSON: " . json_last_error_msg());
            return array();
        }
        
        return is_array($orders) ? $orders : array();
    }
    
    /**
     * فرمت پیام سفارش
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
        
        // لیست محصولات
        $items = isset($order['line_items']) ? $order['line_items'] : array();
        $items_list = '';
        foreach ($items as $item) {
            $item_name = isset($item['name']) ? $item['name'] : 'محصول';
            $item_qty = isset($item['quantity']) ? $item['quantity'] : 1;
            $items_list .= "• " . $item_name . " (تعداد: " . $item_qty . ")\n";
        }
        
        // آدرس
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
        
        $message = "🛒 *سفارش جدید*\n\n";
        $message .= "📋 *شماره سفارش:* #" . $order_id . "\n";
        $message .= "👤 *مشتری:* " . ($billing_name ?: 'N/A') . "\n";
        $message .= "📧 *ایمیل:* " . $billing_email . "\n";
        $message .= "📱 *تلفن:* " . $billing_phone . "\n";
        $message .= "📅 *تاریخ:* " . $order_date . "\n";
        $message .= "💳 *روش پرداخت:* " . $payment_method . "\n";
        $message .= "📊 *وضعیت:* " . $order_status . "\n";
        $message .= "💰 *مبلغ کل:* " . number_format((float)$order_total, 0) . " " . $order_currency . "\n\n";
        
        if (!empty($items_list)) {
            $message .= "*محصولات:*\n" . $items_list;
        }
        
        if (!empty($shipping_address)) {
            $message .= "\n*آدرس ارسال:*\n" . $shipping_address;
        }
        
        return $message;
    }
    
    /**
     * ترجمه وضعیت سفارش
     */
    private function translate_status($status) {
        $statuses = array(
            'pending' => 'در انتظار پرداخت',
            'processing' => 'در حال پردازش',
            'on-hold' => 'در انتظار',
            'completed' => 'تکمیل شده',
            'cancelled' => 'لغو شده',
            'refunded' => 'بازگشت وجه',
            'failed' => 'ناموفق'
        );
        
        return isset($statuses[$status]) ? $statuses[$status] : $status;
    }
    
    /**
     * ارسال پیام به تلگرام
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
            $this->log_error("خطا در ارسال به تلگرام: " . $error);
            return false;
        }
        
        if ($http_code !== 200) {
            $this->log_error("خطا در ارسال به تلگرام. کد HTTP: " . $http_code);
            return false;
        }
        
        $result = json_decode($response, true);
        
        if (isset($result['ok']) && $result['ok'] === true) {
            return true;
        } else {
            $error_msg = isset($result['description']) ? $result['description'] : 'خطای ناشناخته';
            $this->log_error("خطا از API تلگرام: " . $error_msg);
            return false;
        }
    }
    
    /**
     * بررسی اینکه آیا سفارش قبلاً ارسال شده یا نه
     */
    private function is_order_sent($order_id) {
        if (!file_exists($this->log_file)) {
            return false;
        }
        
        $sent_orders = file($this->log_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        return in_array($order_id, $sent_orders);
    }
    
    /**
     * علامت‌گذاری سفارش به عنوان ارسال شده
     */
    private function mark_order_as_sent($order_id) {
        file_put_contents($this->log_file, $order_id . "\n", FILE_APPEND | LOCK_EX);
    }
    
    /**
     * ثبت لاگ
     */
    private function log($message) {
        $timestamp = date('Y-m-d H:i:s');
        $log_message = "[{$timestamp}] {$message}\n";
        error_log($log_message, 3, __DIR__ . '/notifier.log');
    }
    
    /**
     * ثبت خطا
     */
    private function log_error($message) {
        $timestamp = date('Y-m-d H:i:s');
        $log_message = "[{$timestamp}] ERROR: {$message}\n";
        error_log($log_message, 3, __DIR__ . '/notifier.log');
    }
}

// اجرای اسکریپت
try {
    $notifier = new WooCommerceTelegramNotifier();
    $notifier->run();
    echo "اسکریپت با موفقیت اجرا شد.\n";
} catch (Exception $e) {
    echo "خطا: " . $e->getMessage() . "\n";
    error_log("Fatal error: " . $e->getMessage());
}


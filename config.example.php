<?php
/**
 * فایل تنظیمات نمونه
 * این فایل را کپی کنید و نام آن را به config.php تغییر دهید
 * سپس اطلاعات خود را وارد کنید
 */

// تنظیمات وردپرس
define('WP_SITE_URL', 'https://yoursite.com'); // آدرس سایت وردپرس شما
define('WP_API_USER', 'your_api_username');     // نام کاربری API
define('WP_API_PASS', 'your_api_password');     // رمز عبور API (Application Password)

// تنظیمات تلگرام
define('TELEGRAM_BOT_TOKEN', '123456789:ABCdefGHIjklMNOpqrsTUVwxyz'); // توکن ربات تلگرام
define('TELEGRAM_CHAT_ID', '123456789'); // شناسه چت شما در تلگرام

// تنظیمات اسکریپت
define('CHECK_INTERVAL', 60); // فاصله بررسی سفارشات جدید (ثانیه)
define('LOG_FILE', __DIR__ . '/orders_log.txt'); // فایل لاگ سفارشات ارسال شده


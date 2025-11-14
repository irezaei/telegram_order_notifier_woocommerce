<?php
/**
 * Configuration File Example
 * Copy this file and rename it to config.php
 * Then fill in your information
 */

// WordPress Settings
define('WP_SITE_URL', 'https://yoursite.com'); // Your WordPress site URL
define('WP_API_USER', 'your_api_username');     // WordPress API username
define('WP_API_PASS', 'your_api_password');     // Application Password

// Telegram Settings
define('TELEGRAM_BOT_TOKEN', '123456789:ABCdefGHIjklMNOpqrsTUVwxyz'); // Telegram bot token
define('TELEGRAM_CHAT_ID', '123456789'); // Your Telegram chat ID

// Script Settings
define('CHECK_INTERVAL', 60); // Interval for checking new orders (seconds)
define('LOG_FILE', __DIR__ . '/orders_log.txt'); // Log file for sent orders

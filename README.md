# 📱 Telegram WooCommerce Order Notifier

[![PHP Version](https://img.shields.io/badge/PHP-7.2%2B-blue.svg)](https://php.net)
[![WooCommerce](https://img.shields.io/badge/WooCommerce-3.0%2B-green.svg)](https://woocommerce.com)
[![License](https://img.shields.io/badge/License-MIT-yellow.svg)](LICENSE)

A standalone PHP script that automatically sends new WooCommerce order notifications to Telegram. You can deploy this script on a third-party server and receive order notifications via REST API without installing any WordPress plugin.

## 🌟 Features

- ✅ **Third-party Server** - No need to install on WordPress
- ✅ **REST API Integration** - Secure connection to WordPress
- ✅ **Cron Job Support** - Automated execution
- ✅ **Duplicate Prevention** - Smart notification management
- ✅ **Complete Logging System** - Easy tracking and debugging
- ✅ **Multi-language Ready** - Easy to customize messages
- ✅ **High Security** - Protected sensitive information

## 📋 Requirements

- PHP 7.2 or higher
- cURL extension enabled
- Internet access
- WordPress Application Password

## 🚀 Installation & Setup

### 1. Get Application Password in WordPress

1. Go to your WordPress admin panel
2. Navigate to **Users > Profile**
3. Scroll down to **Application Passwords** section:
   - Enter a name for the password (e.g., Telegram Bot)
   - Click **Add New Application Password**
   - Copy the generated password (it's only shown once)

### 2. Get Telegram Bot Token

1. Open Telegram and message [@BotFather](https://t.me/BotFather)
2. Send the `/newbot` command
3. Enter your bot name and username
4. Copy the received token

### 3. Get Chat ID

1. Message [@userinfobot](https://t.me/userinfobot) on Telegram
2. Copy the numeric ID you receive (e.g., `123456789`)

### 4. Configure config.php

Open `config.example.php`, copy it to `config.php` and fill in the following information:

```php
define('WP_SITE_URL', 'https://yoursite.com'); // Your WordPress site URL
define('WP_API_USER', 'your_api_username');     // WordPress username
define('WP_API_PASS', 'your_api_password');     // Application Password
define('TELEGRAM_BOT_TOKEN', 'your_bot_token'); // Telegram bot token
define('TELEGRAM_CHAT_ID', 'your_chat_id');     // Telegram chat ID
```

### 5. Upload Files to Third-party Server

Upload the following files to your third-party server:
- `telegram_order_notifier.php`
- `config.php`

**Security Note:** Place `config.php` in a directory outside of public_html or use `.htaccess` for protection.

### 6. Setup Cron Job

To run the script automatically, set up a Cron Job:

#### In cPanel:
1. Go to **Cron Jobs** section
2. Create a new Cron Job:
   - **Minute:** `*/5` (every 5 minutes)
   - **Hour:** `*`
   - **Day:** `*`
   - **Month:** `*`
   - **Weekday:** `*`
   - **Command:** `/usr/bin/php /path/to/telegram_order_notifier.php`

#### Command Line:
```bash
*/5 * * * * /usr/bin/php /path/to/telegram_order_notifier.php
```

#### Or run manually from your web host:
```
https://your-third-server.com/telegram_order_notifier.php
```

## 🧪 Testing

To test the script:

1. Fill `config.php` with correct information
2. Run the script manually:
   ```bash
   php telegram_order_notifier.php
   ```
3. Or visit the file URL in your browser
4. Create a test order in WooCommerce
5. Run the script again
6. Check your Telegram for the message

## 📝 Log Files

The script creates two log files:

- `orders_log.txt`: List of sent orders (prevents duplicate notifications)
- `notifier.log`: Complete execution and error logs

## 🔧 Troubleshooting

### Error: "Configuration is incomplete"
- Check that all fields in `config.php` are filled

### Error: "Error connecting to WordPress"
- Verify that `WP_SITE_URL` is correct
- Verify that Application Password is correct
- Verify that WooCommerce REST API is enabled

### Error: "Error sending to Telegram"
- Verify that bot token is correct
- Verify that chat ID is correct
- Verify that Telegram bot is active

### Orders are not being sent
- Check that Cron Job is set up correctly
- Check `notifier.log` file
- Verify that orders are in `pending`, `processing`, or `on-hold` status

## 🔒 Security

- Place `config.php` in a secure directory
- Use `.htaccess` to protect files
- Keep Application Password secure
- Restrict access to log files

## 📞 Support

If you encounter any issues, check the `notifier.log` file to see error messages.

## 📄 License

This script is provided free and open source.

## 🤝 Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

## ⭐ Star History

If you find this project useful, please consider giving it a star ⭐

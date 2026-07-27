<?php
/**
 * Config - نسخه بهبود یافته v2.0 - تنظیمات هاست اصلی شما
 * بدون نیاز به نصب دوباره - فقط آپلود کنید
 */
$envPath = __DIR__ . '/../.env';
if (file_exists($envPath)) {
    $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') continue;
        if (!str_contains($line, '=')) continue;
        [$k, $v] = explode('=', $line, 2);
        $k = trim($k); $v = trim(trim($v), "\"'");
        if (!getenv($k)) { putenv("$k=$v"); $_ENV[$k] = $v; }
    }
}
if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
    if (class_exists('Dotenv\Dotenv')) {
        try { Dotenv\Dotenv::createImmutable(__DIR__ . '/..')->safeLoad(); } catch (Throwable $e) {}
    }
}
function env($key, $default = null) {
    $val = $_ENV[$key] ?? getenv($key);
    if ($val === false || $val === null || $val === '') return $default;
    return $val;
}
define('BOT_TOKEN', env('BOT_TOKEN', '1706344265:70iFbRkBeq92GWyXnUwrIQqKkd2HBdZJShM'));
define('BOT_USERNAME', env('BOT_USERNAME', 'your_bot_username'));
define('BALE_API_URL', 'https://tapi.bale.ai/bot' . BOT_TOKEN . '/');
define('WEBHOOK_SECRET', env('WEBHOOK_SECRET', 'xK9mP2qR7nL4wZ3'));
define('DB_HOST', env('DB_HOST', 'localhost'));
define('DB_NAME', env('DB_NAME', 'opujkdbu_dating'));
define('DB_USER', env('DB_USER', 'opujkdbu_dating'));
define('DB_PASS', env('DB_PASS', 'amir1381@@'));
define('DB_CHARSET', env('DB_CHARSET', 'utf8mb4'));
define('DB_PORT', (int) env('DB_PORT', 3306));
define('ADMIN_BALE_ID', (int) env('ADMIN_BALE_ID', 1480128084));
define('UPLOAD_PATH', __DIR__ . '/../uploads/');
define('LOG_PATH', __DIR__ . '/../logs/');
define('BASE_URL', rtrim(env('BASE_URL', 'https://a7vbot.ir/bale'), '/'));
define('FREE_DAILY_LIKES', (int) env('FREE_DAILY_LIKES', 20));
define('FREE_DAILY_VIEWS', (int) env('FREE_DAILY_VIEWS', 30));
defined('FREE_DAILY_SECRET') || define('FREE_DAILY_SECRET', 5);
defined('MAX_BIO_LENGTH') || define('MAX_BIO_LENGTH', 500);
defined('COST_RANDOM') || define('COST_RANDOM', 0);
defined('COST_GENDER') || define('COST_GENDER', 2);
defined('COST_PROVINCE') || define('COST_PROVINCE', 4);
defined('COST_GIFT') || define('COST_GIFT', 10);
defined('DAILY_BONUS_COINS') || define('DAILY_BONUS_COINS', 5);
defined('REFERRAL_BONUS') || define('REFERRAL_BONUS', 20);
defined('START_BONUS') || define('START_BONUS', 10);
defined('RATE_LIMIT_MESSAGES') || define('RATE_LIMIT_MESSAGES', 20);
defined('RATE_LIMIT_WINDOW') || define('RATE_LIMIT_WINDOW', 60);
define('ADMIN_USERNAME', env('ADMIN_USERNAME', 'admin'));
define('ADMIN_PASSWORD', env('ADMIN_PASSWORD', 'change_this_password'));
if (!is_dir(LOG_PATH)) @mkdir(LOG_PATH, 0755, true);
if (!is_dir(UPLOAD_PATH)) @mkdir(UPLOAD_PATH, 0755, true);
date_default_timezone_set('Asia/Tehran');
if (env('APP_DEBUG', false)) { ini_set('display_errors', 1); error_reporting(E_ALL); } else { ini_set('display_errors', 0); error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED); }

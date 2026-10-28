<?php
declare(strict_types=1);

/**
 * AgriMarket – application configuration.
 *
 * Every database value can be overridden with an environment variable,
 * so no credentials need to be committed to version control.
 */

const APP_NAME      = 'AgriMarket';
const APP_TAGLINE   = 'Fresh from the farm to your table';
const CURRENCY      = 'KES';
const DELIVERY_FEE  = 150;     // flat fee added to every order
const PER_PAGE      = 8;       // products per catalogue page
const SUPPORT_EMAIL = 'support@agrimarket.co.ke';
const SUPPORT_PHONE = '+254 700 123 456';

// Required by setup_admin.php to create the first administrator.
// Change it before deploying, then delete setup_admin.php.
define('ADMIN_SETUP_KEY', getenv('ADMIN_SETUP_KEY') ?: 'change-this-key');

define('DB_HOST', getenv('DB_HOST') ?: '127.0.0.1');
define('DB_NAME', getenv('DB_NAME') ?: 'agrimarket');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') ?: '');

date_default_timezone_set('Africa/Nairobi');

if (PHP_SAPI !== 'cli' && session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => !empty($_SERVER['HTTPS']),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

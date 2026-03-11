<?php
/**
 * NodeShop — Cấu hình hệ thống
 */

define('APP_NAME',    'NodeShop');
define('APP_VERSION', '1.1.0');
define('APP_URL',     getenv('APP_URL') ?: 'http://localhost:8888');
define('APP_PORT',    getenv('APP_PORT') ?: '8888');

// Thư mục data (ẩn)
define('DATA_DIR', dirname(__DIR__) . '/.data');

// Upload
define('UPLOAD_DIR',  dirname(__DIR__) . '/public/uploads');
define('UPLOAD_URL',  '/public/uploads');

// Sepay API
define('SEPAY_TOKEN',      getenv('SEPAY_TOKEN') ?: '');
define('SEPAY_ACCOUNT_NO', getenv('SEPAY_ACCOUNT_NO') ?: '');
define('SEPAY_BANK_CODE',  getenv('SEPAY_BANK_CODE') ?: 'MB');
define('SEPAY_WEBHOOK_SECRET', getenv('SEPAY_WEBHOOK_SECRET') ?: 'SEPAY_WEBHOOK_SECRET');

// NodePanel Manager Bridge
define('MANAGER_URL',        'http://gold05.vpsbumboo.com:25745');
define('BRIDGE_SECRET',      'mInhsoora@xyz1000');
define('MANAGER_API_SECRET', getenv('MANAGER_API_SECRET') ?: 'nodepanel_secret_change_me');
define('BRIDGE_LOG',         DATA_DIR . '/bridge.log');
define('CRASH_LOG',          DATA_DIR . '/crash.log');
define('CRASH_LOG_JSON',     DATA_DIR . '/crashes.json');

// Groq AI
define('GROQ_API_KEY', getenv('GROQ_API_KEY') ?: '');

// Session
define('SESSION_TIMEOUT', 7 * 24 * 3600);

// Currency
define('CURRENCY', 'VNĐ');

// Tạo thư mục
foreach ([DATA_DIR, DATA_DIR.'/orders', DATA_DIR.'/tickets', DATA_DIR.'/transactions', UPLOAD_DIR] as $d) {
    if (!is_dir($d)) mkdir($d, 0755, true);
}

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/helpers.php';

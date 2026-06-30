<?php
// ============================================================
// PersonaX v3 — config/config.php
// ============================================================

define('PX_VERSION', '3.0.0');
define('PX_ROOT',    dirname(__DIR__));

// ── DATABASE ─────────────────────────────────────────────
define('DB_HOST', getenv('PX_DB_HOST') ?: 'localhost');
define('DB_NAME', getenv('PX_DB_NAME') ?: 'personax');
define('DB_USER', getenv('PX_DB_USER') ?: 'root');
define('DB_PASS', getenv('PX_DB_PASS') ?: '');
define('DB_PORT', getenv('PX_DB_PORT') ?: '3306');

// ── SECURITY ─────────────────────────────────────────────
define('APP_KEY',       getenv('PX_APP_KEY') ?: 'change_this_32_char_secret_key!!');
define('CSRF_TOKEN_LEN', 32);
define('OTP_LENGTH',     6);
define('SESSION_HOURS',  24);

// ── EMAIL (SMTP) ─────────────────────────────────────────
define('SMTP_HOST',      getenv('PX_SMTP_HOST') ?: '');
define('SMTP_PORT',      getenv('PX_SMTP_PORT') ?: 587);
define('SMTP_USER',      getenv('PX_SMTP_USER') ?: '');
define('SMTP_PASS',      getenv('PX_SMTP_PASS') ?: '');
define('SMTP_FROM_EMAIL',getenv('PX_SMTP_FROM') ?: 'noreply@personax.ai');
define('SMTP_FROM_NAME', 'PersonaX');

// ── AI PROVIDERS ─────────────────────────────────────────
define('CLAUDE_API_KEY',  getenv('ANTHROPIC_API_KEY') ?: '');
define('OPENAI_API_KEY',  getenv('OPENAI_API_KEY')    ?: '');
define('GEMINI_API_KEY',  getenv('GEMINI_API_KEY')    ?: '');

// ── PYTHON AI SERVICE ────────────────────────────────────
define('PY_SERVICE_URL',  getenv('PX_PY_URL') ?: 'http://127.0.0.1:5050');
define('PY_SERVICE_KEY',  getenv('PX_PY_KEY') ?: 'internal_secret_key');

// ── PATHS ────────────────────────────────────────────────
define('UPLOAD_DIR', PX_ROOT . '/uploads/');
define('LOG_DIR',    PX_ROOT . '/logs/');

// ── ENVIRONMENT ──────────────────────────────────────────
define('PX_ENV', getenv('PX_ENV') ?: 'production');
define('PX_DEBUG', PX_ENV === 'development');

if (PX_DEBUG) {
    ini_set('display_errors', 1);
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', 0);
    error_reporting(0);
}

// ── TIMEZONE ─────────────────────────────────────────────
date_default_timezone_set('UTC');

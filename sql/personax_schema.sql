-- ============================================================
-- PersonaX v3 — Complete MySQL Database Schema
-- ============================================================

CREATE DATABASE IF NOT EXISTS personax CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE personax;

-- ── USERS ──────────────────────────────────────────────────
CREATE TABLE users (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name          VARCHAR(120) NOT NULL,
  email         VARCHAR(191) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  avatar        VARCHAR(255) DEFAULT NULL,
  role          ENUM('user','admin') DEFAULT 'user',
  is_verified   TINYINT(1) DEFAULT 0,
  otp_code      VARCHAR(10) DEFAULT NULL,
  otp_expires   DATETIME DEFAULT NULL,
  last_login    DATETIME DEFAULT NULL,
  created_at    DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at    DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_email (email),
  INDEX idx_role  (role)
) ENGINE=InnoDB;

-- ── SESSIONS ───────────────────────────────────────────────
CREATE TABLE sessions (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id     INT UNSIGNED NOT NULL,
  token       VARCHAR(255) NOT NULL UNIQUE,
  ip_address  VARCHAR(45)  DEFAULT NULL,
  user_agent  TEXT         DEFAULT NULL,
  expires_at  DATETIME     NOT NULL,
  created_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_token   (token),
  INDEX idx_user_id (user_id)
) ENGINE=InnoDB;

-- ── MEMORIES ───────────────────────────────────────────────
CREATE TABLE memories (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id    INT UNSIGNED NOT NULL,
  tag        VARCHAR(80)  NOT NULL DEFAULT 'note',
  content    TEXT         NOT NULL,
  embedding  JSON         DEFAULT NULL,     -- future vector search
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_user_tag (user_id, tag),
  FULLTEXT  ft_content (content)
) ENGINE=InnoDB;

-- ── PERSONALITY PROFILES ───────────────────────────────────
CREATE TABLE personality_profiles (
  id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id         INT UNSIGNED NOT NULL UNIQUE,
  formality       TINYINT DEFAULT 3,       -- 1-5
  humor           TINYINT DEFAULT 3,
  energy          TINYINT DEFAULT 3,
  communication   ENUM('friendly','professional','casual','concise') DEFAULT 'friendly',
  tone            ENUM('warm','neutral','direct') DEFAULT 'warm',
  custom_prompt   TEXT DEFAULT NULL,
  updated_at      DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ── VOICE PROFILES ─────────────────────────────────────────
CREATE TABLE voice_profiles (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id      INT UNSIGNED NOT NULL UNIQUE,
  voice_name   VARCHAR(120) DEFAULT 'default',
  language     VARCHAR(20)  DEFAULT 'en-US',
  rate         DECIMAL(3,2) DEFAULT 0.95,
  pitch        DECIMAL(3,2) DEFAULT 1.05,
  volume       DECIMAL(3,2) DEFAULT 1.00,
  wake_word    VARCHAR(80)  DEFAULT 'PersonaX',
  updated_at   DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ── CONVERSATIONS ──────────────────────────────────────────
CREATE TABLE conversations (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id     INT UNSIGNED NOT NULL,
  session_key VARCHAR(64)  NOT NULL,        -- groups turns in one session
  role        ENUM('user','assistant') NOT NULL,
  content     TEXT NOT NULL,
  provider    VARCHAR(40)  DEFAULT NULL,    -- which LLM answered
  tokens_in   INT DEFAULT 0,
  tokens_out  INT DEFAULT 0,
  created_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_user_session (user_id, session_key),
  INDEX idx_created      (created_at)
) ENGINE=InnoDB;

-- ── REMINDERS / CALENDAR ───────────────────────────────────
CREATE TABLE reminders (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id      INT UNSIGNED NOT NULL,
  title        VARCHAR(255) NOT NULL,
  notes        TEXT         DEFAULT NULL,
  remind_at    DATETIME     DEFAULT NULL,
  is_done      TINYINT(1)   DEFAULT 0,
  notified     TINYINT(1)   DEFAULT 0,
  created_at   DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_user_remind (user_id, remind_at),
  INDEX idx_pending     (remind_at, is_done, notified)
) ENGINE=InnoDB;

-- ── AUTOMATION COMMANDS ────────────────────────────────────
CREATE TABLE commands (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name         VARCHAR(120) NOT NULL,
  trigger_phrase VARCHAR(255) NOT NULL,
  action_type  ENUM('open_url','send_email','create_reminder','generate_report','custom') NOT NULL,
  action_data  JSON         DEFAULT NULL,
  is_active    TINYINT(1)   DEFAULT 1,
  requires_approval TINYINT(1) DEFAULT 1,
  created_by   INT UNSIGNED DEFAULT NULL,
  created_at   DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ── AUTOMATION LOGS ────────────────────────────────────────
CREATE TABLE automation_logs (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id      INT UNSIGNED NOT NULL,
  command_id   INT UNSIGNED DEFAULT NULL,
  action       VARCHAR(255) NOT NULL,
  status       ENUM('pending','approved','executed','denied','failed') DEFAULT 'pending',
  result       TEXT         DEFAULT NULL,
  approved_at  DATETIME DEFAULT NULL,
  created_at   DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id)    REFERENCES users(id)    ON DELETE CASCADE,
  FOREIGN KEY (command_id) REFERENCES commands(id) ON DELETE SET NULL,
  INDEX idx_user_status (user_id, status)
) ENGINE=InnoDB;

-- ── AI PROVIDERS ───────────────────────────────────────────
CREATE TABLE ai_providers (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name         VARCHAR(80)  NOT NULL,
  slug         VARCHAR(40)  NOT NULL UNIQUE,
  api_key_enc  TEXT         DEFAULT NULL,   -- AES-256 encrypted
  model        VARCHAR(80)  DEFAULT NULL,
  endpoint     VARCHAR(255) DEFAULT NULL,
  is_active    TINYINT(1)   DEFAULT 0,
  is_default   TINYINT(1)   DEFAULT 0,
  priority     INT          DEFAULT 0,
  created_at   DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ── SYSTEM SETTINGS ────────────────────────────────────────
CREATE TABLE settings (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `key`        VARCHAR(120) NOT NULL UNIQUE,
  value        TEXT         DEFAULT NULL,
  label        VARCHAR(255) DEFAULT NULL,
  group_name   VARCHAR(80)  DEFAULT 'general',
  updated_at   DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ── EMAIL LOG ──────────────────────────────────────────────
CREATE TABLE email_log (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id      INT UNSIGNED DEFAULT NULL,
  to_email     VARCHAR(191) NOT NULL,
  subject      VARCHAR(255) NOT NULL,
  type         ENUM('otp','welcome','notification','report') DEFAULT 'notification',
  status       ENUM('sent','failed','queued') DEFAULT 'queued',
  sent_at      DATETIME DEFAULT NULL,
  created_at   DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ── SEED: DEFAULT AI PROVIDERS ─────────────────────────────
INSERT INTO ai_providers (name, slug, model, endpoint, is_active, is_default, priority) VALUES
  ('Claude (Anthropic)', 'claude',  'claude-sonnet-4-6', 'https://api.anthropic.com/v1/messages', 1, 1, 1),
  ('OpenAI GPT-4o',      'openai',  'gpt-4o',            'https://api.openai.com/v1/chat/completions', 0, 0, 2),
  ('Google Gemini',      'gemini',  'gemini-1.5-pro',    'https://generativelanguage.googleapis.com/v1beta/models', 0, 0, 3),
  ('Local LLM',          'local',   'llama3',            'http://localhost:11434/api/chat', 0, 0, 4);

-- ── SEED: DEFAULT SYSTEM SETTINGS ─────────────────────────
INSERT INTO settings (`key`, value, label, group_name) VALUES
  ('app_name',           'PersonaX',        'Application Name',      'general'),
  ('app_tagline',        'Your Voice. Your Memory. Your Digital Presence.', 'Tagline', 'general'),
  ('otp_expiry_minutes', '15',              'OTP Expiry (minutes)',  'auth'),
  ('session_hours',      '24',              'Session Length (hours)','auth'),
  ('smtp_host',          '',                'SMTP Host',             'email'),
  ('smtp_port',          '587',             'SMTP Port',             'email'),
  ('smtp_user',          '',                'SMTP Username',         'email'),
  ('smtp_pass',          '',                'SMTP Password',         'email'),
  ('smtp_from_name',     'PersonaX',        'From Name',             'email'),
  ('max_memory_per_user','500',             'Max Memories Per User', 'limits'),
  ('max_conv_history',   '50',              'Conversation History Depth', 'limits'),
  ('maintenance_mode',   '0',              'Maintenance Mode',       'general');

-- ── SEED: DEFAULT ADMIN USER ───────────────────────────────
-- Password: Admin@PersonaX1  (change immediately after setup)
INSERT INTO users (name, email, password_hash, role, is_verified) VALUES
  ('Admin', 'admin@personax.ai', '$2y$12$placeholder_change_this_hash', 'admin', 1);

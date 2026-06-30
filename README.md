# PersonaX v3 — Complete Setup & Deployment Guide

> **Your Voice. Your Memory. Your Digital Presence.**  
> Version 3.0.0 | PHP 8.1+ · MySQL 8+ · Python 3.10+

---

## Project Structure

```
personax/
├── index.php               ← Main UI entry point
├── .htaccess               ← Apache security & routing
│
├── config/
│   └── config.php          ← All config (env-driven)
│
├── includes/
│   ├── Database.php        ← PDO singleton wrapper
│   ├── Auth.php            ← Register, OTP, login, sessions
│   ├── LLMManager.php      ← Claude/OpenAI/Gemini/Local provider
│   └── AutomationEngine.php← Secure command execution
│
├── api/
│   └── index.php           ← JSON REST API (all endpoints)
│
├── admin/
│   └── index.html          ← Futuristic admin control center
│
├── python/
│   └── app.py              ← Flask AI microservice (NLP, intent)
│
├── cron/
│   └── send_reminders.php  ← Reminder email dispatcher
│
├── sql/
│   └── personax_schema.sql ← Complete MySQL schema + seed data
│
├── uploads/                ← User uploads (writable)
└── logs/                   ← Error logs (writable)
```

---

## Phase 1 — Server Requirements

| Requirement | Minimum |
|-------------|---------|
| PHP | 8.1+ with `pdo_mysql`, `curl`, `openssl`, `mbstring` |
| MySQL | 8.0+ |
| Python | 3.10+ (for AI microservice) |
| Apache/Nginx | Any modern version |
| SSL certificate | Required for microphone access |

---

## Phase 2 — Database Setup

```bash
# Create the database and run the schema
mysql -u root -p < sql/personax_schema.sql

# Verify tables created
mysql -u root -p personax -e "SHOW TABLES;"
```

---

## Phase 3 — Configuration

Set environment variables (recommended) or edit `config/config.php`:

```bash
# Database
export PX_DB_HOST=localhost
export PX_DB_NAME=personax
export PX_DB_USER=personax_user
export PX_DB_PASS=your_secure_password

# Security (generate with: openssl rand -base64 32)
export PX_APP_KEY=your_32_char_secret_key_here_change

# AI Providers (set whichever you use)
export ANTHROPIC_API_KEY=sk-ant-...
export OPENAI_API_KEY=sk-...
export GEMINI_API_KEY=AIza...

# Email (SMTP — use Gmail App Password, SendGrid, Mailgun, etc.)
export PX_SMTP_HOST=smtp.gmail.com
export PX_SMTP_PORT=587
export PX_SMTP_USER=you@gmail.com
export PX_SMTP_PASS=your_app_password
export PX_SMTP_FROM=noreply@yourdomain.com

# Python AI service key (internal auth)
export PX_PY_KEY=your_internal_secret
```

---

## Phase 4 — Python AI Microservice

```bash
cd python/

# Install dependencies
pip install flask requests

# Start service
python app.py

# Or run as background service
nohup python app.py > ../logs/python.log 2>&1 &

# Test
curl http://127.0.0.1:5050/health
```

For production, use **gunicorn**:
```bash
pip install gunicorn
gunicorn -w 2 -b 127.0.0.1:5050 app:app
```

Create a **systemd service** (`/etc/systemd/system/personax-ai.service`):
```ini
[Unit]
Description=PersonaX AI Microservice
After=network.target

[Service]
User=www-data
WorkingDirectory=/var/www/personax/python
ExecStart=/usr/local/bin/gunicorn -w 2 -b 127.0.0.1:5050 app:app
Restart=always
Environment=PX_PY_KEY=your_internal_secret

[Install]
WantedBy=multi-user.target
```

```bash
systemctl enable personax-ai
systemctl start  personax-ai
```

---

## Phase 5 — Web Server Configuration

### Apache (`/etc/apache2/sites-available/personax.conf`)

```apache
<VirtualHost *:443>
  ServerName yourdomain.com
  DocumentRoot /var/www/personax

  SSLEngine on
  SSLCertificateFile    /etc/letsencrypt/live/yourdomain.com/fullchain.pem
  SSLCertificateKeyFile /etc/letsencrypt/live/yourdomain.com/privkey.pem

  <Directory /var/www/personax>
    AllowOverride All
    Options -Indexes
    Require all granted
  </Directory>

  ErrorLog  /var/log/apache2/personax_error.log
  CustomLog /var/log/apache2/personax_access.log combined
</VirtualHost>
```

### Nginx (`/etc/nginx/sites-available/personax`)

```nginx
server {
  listen 443 ssl;
  server_name yourdomain.com;
  root /var/www/personax;
  index index.php;

  ssl_certificate     /etc/letsencrypt/live/yourdomain.com/fullchain.pem;
  ssl_certificate_key /etc/letsencrypt/live/yourdomain.com/privkey.pem;

  location / {
    try_files $uri $uri/ /index.php?$query_string;
  }

  location ~ \.php$ {
    fastcgi_pass   unix:/var/run/php/php8.1-fpm.sock;
    fastcgi_index  index.php;
    include        fastcgi_params;
    fastcgi_param  SCRIPT_FILENAME $document_root$fastcgi_script_name;
  }

  # Block sensitive directories
  location ~ ^/(config|includes|python|sql|cron|logs) {
    deny all;
  }
}
```

---

## Phase 6 — File Permissions

```bash
cd /var/www/personax

# Set ownership
chown -R www-data:www-data .

# Directory permissions
chmod 755 .
chmod -R 644 *.php
chmod -R 644 **/*.php
chmod 600 config/config.php

# Writable directories
chmod 775 uploads/ logs/
chmod 700 cron/
```

---

## Phase 7 — Email Setup

### Option A — Gmail (development/small scale)
1. Enable 2FA on your Google account
2. Generate an App Password: Google Account → Security → App passwords
3. Set `SMTP_HOST=smtp.gmail.com`, `SMTP_PORT=587`

### Option B — Mailgun (production recommended)
1. Sign up at mailgun.com → add your domain
2. Get SMTP credentials from the dashboard
3. Verify your domain's DNS records

### Option C — SendGrid
1. Sign up at sendgrid.com → Settings → API Keys
2. Use `smtp.sendgrid.net`, port `587`, user `apikey`, password = your API key

### Install PHPMailer (recommended)
```bash
composer require phpmailer/phpmailer
# Then require the autoloader in config.php:
# require_once PX_ROOT . '/vendor/autoload.php';
```

---

## Phase 8 — Cron Jobs

```bash
# Edit crontab
crontab -e

# Add these lines:
# Send due reminder emails every minute
* * * * * php /var/www/personax/cron/send_reminders.php >> /var/www/personax/logs/reminders.log 2>&1

# Optional: Clean expired sessions daily
0 2 * * * mysql -u root -p'password' personax -e "DELETE FROM sessions WHERE expires_at < NOW();"
```

---

## Phase 9 — Admin Panel

Access at: `https://yourdomain.com/admin/index.html`

**First login:**
1. Update the admin password hash in the database:
```sql
UPDATE users SET password_hash = '$2y$12$...' WHERE email = 'admin@personax.ai';
-- Generate hash: php -r "echo password_hash('YourNewPassword', PASSWORD_BCRYPT, ['cost'=>12]);"
```

2. Add your AI provider API keys via the Admin → AI Providers panel

---

## Phase 10 — AI Provider Setup

In **Admin → AI Providers**, for each provider:
1. Paste your API key (stored AES-256 encrypted)
2. Set the model name
3. Toggle "Active" on
4. Set one as "Default"

**Provider priority:** If the default provider fails, PersonaX automatically falls back to the next active provider.

---

## Phase 11 — HTTPS & Microphone

> ⚠️ The Web Speech API **requires HTTPS** in production.  
> Use Let's Encrypt (free):
```bash
certbot --apache -d yourdomain.com
# or for Nginx:
certbot --nginx -d yourdomain.com
```

---

## Phase 12 — Security Checklist

- [ ] Changed default admin password
- [ ] Set a strong `PX_APP_KEY` (32+ chars)
- [ ] Configured SMTP (OTP emails work)
- [ ] SSL certificate installed
- [ ] `.htaccess` / Nginx config blocking `/config`, `/includes`, etc.
- [ ] `uploads/` and `logs/` are writable but not web-accessible for PHP
- [ ] All API keys set as environment variables, not hardcoded
- [ ] Firewall: only ports 80, 443, 22 open
- [ ] Python service only binding to `127.0.0.1` (not public)

---

## API Reference

All endpoints: `POST /api/index.php?action=<action>`

| Action | Method | Auth | Description |
|--------|--------|------|-------------|
| `register` | POST | ✗ | Register new user |
| `verify_otp` | POST | ✗ | Verify email OTP |
| `resend_otp` | POST | ✗ | Resend OTP code |
| `login` | POST | ✗ | Login |
| `logout` | POST | ✓ | Logout |
| `me` | GET | ✓ | Current user |
| `chat` | POST | ✓ | AI conversation |
| `memories` | GET/POST/DELETE | ✓ | Manage memories |
| `reminders` | GET/POST/PATCH/DELETE | ✓ | Manage reminders |
| `personality` | GET/POST | ✓ | Personality profile |
| `voice` | GET/POST | ✓ | Voice settings |
| `admin_users` | GET/DELETE | Admin | User management |
| `admin_providers` | GET/POST | Admin | AI providers |
| `admin_settings` | GET/POST | Admin | System settings |
| `admin_stats` | GET | Admin | Dashboard stats |

---

## Troubleshooting

**Microphone not working?**  
→ Must be on HTTPS. Chrome/Edge only for full support.

**OTP emails not sending?**  
→ Check `logs/php_errors.log`. Verify SMTP credentials. Try PHPMailer.

**AI responses failing?**  
→ Check API key in Admin panel. Check `logs/php_errors.log`.

**Python service errors?**  
→ `curl http://127.0.0.1:5050/health` — should return `{"status":"ok"}`.

**Database connection failed?**  
→ Verify `PX_DB_*` environment variables. Check MySQL is running.

---

*PersonaX v3 — Built for final year CS projects and real-world deployment.*

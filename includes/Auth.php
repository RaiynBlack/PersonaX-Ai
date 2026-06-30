<?php
// ============================================================
// PersonaX v3 — includes/Auth.php
// ============================================================

class Auth {
    private Database $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    // ── SESSION ────────────────────────────────────────────

    public function startSecureSession(): void {
        if (session_status() === PHP_SESSION_NONE) {
            session_set_cookie_params([
                'lifetime' => 0,
                'path'     => '/',
                'secure'   => isset($_SERVER['HTTPS']),
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
            session_start();
        }
    }

    public function generateCsrf(): string {
        $this->startSecureSession();
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(CSRF_TOKEN_LEN));
        }
        return $_SESSION['csrf_token'];
    }

    public function verifyCsrf(string $token): bool {
        $this->startSecureSession();
        return isset($_SESSION['csrf_token']) &&
               hash_equals($_SESSION['csrf_token'], $token);
    }

    // ── REGISTRATION ───────────────────────────────────────

    public function register(string $name, string $email, string $password): array {
        $email = strtolower(trim($email));

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'message' => 'Invalid email address.'];
        }
        if (strlen($password) < 8) {
            return ['success' => false, 'message' => 'Password must be at least 8 characters.'];
        }
        if ($this->db->fetchOne("SELECT id FROM users WHERE email = ?", [$email])) {
            return ['success' => false, 'message' => 'An account with this email already exists.'];
        }

        $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
        $otp  = $this->generateOtp();
        $exp  = date('Y-m-d H:i:s', strtotime('+15 minutes'));

        $userId = $this->db->insert('users', [
            'name'          => htmlspecialchars(trim($name), ENT_QUOTES),
            'email'         => $email,
            'password_hash' => $hash,
            'otp_code'      => $otp,
            'otp_expires'   => $exp,
            'is_verified'   => 0,
        ]);

        // Create default personality & voice profiles
        $this->db->insert('personality_profiles', ['user_id' => $userId]);
        $this->db->insert('voice_profiles',       ['user_id' => $userId]);

        // Send OTP email
        $this->sendOtpEmail($email, $name, $otp);

        return ['success' => true, 'user_id' => $userId, 'email' => $email];
    }

    // ── OTP VERIFICATION ───────────────────────────────────

    public function verifyOtp(string $email, string $code): array {
        $email = strtolower(trim($email));
        $user  = $this->db->fetchOne(
            "SELECT * FROM users WHERE email = ? AND otp_code = ? AND otp_expires > NOW()",
            [$email, $code]
        );
        if (!$user) {
            return ['success' => false, 'message' => 'Invalid or expired code.'];
        }

        $this->db->update('users',
            ['is_verified' => 1, 'otp_code' => null, 'otp_expires' => null],
            ['id' => $user['id']]
        );

        $this->startSecureSession();
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['email']   = $user['email'];
        session_regenerate_id(true);

        return ['success' => true, 'user' => $this->safeUser($user)];
    }

    public function resendOtp(string $email): array {
        $email = strtolower(trim($email));
        $user  = $this->db->fetchOne("SELECT * FROM users WHERE email = ?", [$email]);
        if (!$user) return ['success' => false, 'message' => 'User not found.'];

        $otp = $this->generateOtp();
        $exp = date('Y-m-d H:i:s', strtotime('+15 minutes'));
        $this->db->update('users', ['otp_code' => $otp, 'otp_expires' => $exp], ['id' => $user['id']]);
        $this->sendOtpEmail($email, $user['name'], $otp);

        return ['success' => true, 'message' => 'New code sent.'];
    }

    // ── LOGIN ──────────────────────────────────────────────

    public function login(string $email, string $password): array {
        $email = strtolower(trim($email));
        $user  = $this->db->fetchOne("SELECT * FROM users WHERE email = ?", [$email]);

        if (!$user || !password_verify($password, $user['password_hash'])) {
            return ['success' => false, 'message' => 'Incorrect email or password.'];
        }
        if (!$user['is_verified']) {
            return ['success' => false, 'message' => 'Please verify your email first.', 'needs_verify' => true];
        }

        $this->db->update('users', ['last_login' => date('Y-m-d H:i:s')], ['id' => $user['id']]);

        $this->startSecureSession();
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['email']   = $user['email'];
        session_regenerate_id(true);

        return ['success' => true, 'user' => $this->safeUser($user)];
    }

    // ── LOGOUT ─────────────────────────────────────────────

    public function logout(): void {
        $this->startSecureSession();
        $_SESSION = [];
        session_destroy();
        if (ini_get('session.use_cookies')) {
            setcookie(session_name(), '', time() - 42000, '/');
        }
    }

    // ── CHECK AUTH ─────────────────────────────────────────

    public function check(): ?array {
        $this->startSecureSession();
        if (empty($_SESSION['user_id'])) return null;
        return $this->db->fetchOne("SELECT * FROM users WHERE id = ?", [$_SESSION['user_id']]);
    }

    public function requireAuth(): array {
        $user = $this->check();
        if (!$user) {
            http_response_code(401);
            die(json_encode(['error' => 'Unauthorized']));
        }
        return $user;
    }

    public function requireAdmin(): array {
        $user = $this->requireAuth();
        if ($user['role'] !== 'admin') {
            http_response_code(403);
            die(json_encode(['error' => 'Forbidden']));
        }
        return $user;
    }

    // ── HELPERS ────────────────────────────────────────────

    private function generateOtp(): string {
        return str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }

    private function safeUser(array $user): array {
        unset($user['password_hash'], $user['otp_code'], $user['otp_expires']);
        return $user;
    }

    private function sendOtpEmail(string $to, string $name, string $otp): void {
        $subject = 'Your PersonaX verification code';
        $html = <<<HTML
        <div style="font-family:sans-serif;max-width:480px;margin:auto;background:#1a1635;color:white;padding:36px;border-radius:16px;">
          <h2 style="background:linear-gradient(135deg,#00e5ff,#a855f7);-webkit-background-clip:text;-webkit-text-fill-color:transparent;font-size:24px;">PersonaX</h2>
          <p style="color:rgba(255,255,255,0.7);">Hi {$name},</p>
          <p style="color:rgba(255,255,255,0.7);">Your verification code is:</p>
          <div style="font-size:40px;font-weight:700;letter-spacing:12px;text-align:center;padding:24px 0;color:#00e5ff;">{$otp}</div>
          <p style="color:rgba(255,255,255,0.45);font-size:13px;">This code expires in 15 minutes. If you didn't request this, ignore this email.</p>
        </div>
        HTML;

        $this->sendMail($to, $subject, $html);
        $this->db->insert('email_log', [
            'to_email' => $to,
            'subject'  => $subject,
            'type'     => 'otp',
            'status'   => 'sent',
            'sent_at'  => date('Y-m-d H:i:s'),
        ]);
    }

    private function sendMail(string $to, string $subject, string $html): bool {
        // Uses PHPMailer if available, fallback to mail()
        if (class_exists('PHPMailer\PHPMailer\PHPMailer') && SMTP_HOST) {
            $mailer = new PHPMailer\PHPMailer\PHPMailer(true);
            try {
                $mailer->isSMTP();
                $mailer->Host       = SMTP_HOST;
                $mailer->Port       = SMTP_PORT;
                $mailer->SMTPAuth   = true;
                $mailer->Username   = SMTP_USER;
                $mailer->Password   = SMTP_PASS;
                $mailer->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
                $mailer->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
                $mailer->addAddress($to);
                $mailer->isHTML(true);
                $mailer->Subject = $subject;
                $mailer->Body    = $html;
                $mailer->send();
                return true;
            } catch (Exception $e) {
                error_log('[PersonaX Mail] ' . $e->getMessage());
                return false;
            }
        }
        // Fallback
        $headers  = "MIME-Version: 1.0\r\n";
        $headers .= "Content-type: text/html; charset=utf-8\r\n";
        $headers .= "From: " . SMTP_FROM_NAME . " <" . SMTP_FROM_EMAIL . ">\r\n";
        return mail($to, $subject, $html, $headers);
    }
}

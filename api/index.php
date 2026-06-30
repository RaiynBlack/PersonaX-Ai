<?php
// ============================================================
// PersonaX v3 — api/index.php
// JSON REST API — all frontend AJAX calls route here
// ============================================================

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/Database.php';
require_once __DIR__ . '/../includes/Auth.php';
require_once __DIR__ . '/../includes/LLMManager.php';

header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');

$auth = new Auth();
$auth->startSecureSession();

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';
$body   = json_decode(file_get_contents('php://input'), true) ?? [];

// ── RATE LIMITING (simple IP-based) ───────────────────────
function rateLimit(string $key, int $max, int $window): bool {
    $file  = sys_get_temp_dir() . '/px_rl_' . md5($key);
    $data  = @json_decode(@file_get_contents($file), true) ?? ['count' => 0, 'reset' => time() + $window];
    if (time() > $data['reset']) $data = ['count' => 0, 'reset' => time() + $window];
    $data['count']++;
    file_put_contents($file, json_encode($data));
    return $data['count'] <= $max;
}

$ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
if (!rateLimit("api_{$ip}", 120, 60)) {
    http_response_code(429);
    die(json_encode(['error' => 'Too many requests. Slow down.']));
}

$db = Database::getInstance();

try {
    echo json_encode(route($action, $method, $body, $auth, $db));
} catch (Throwable $e) {
    error_log('[PersonaX API] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error.']);
}

// ── ROUTER ────────────────────────────────────────────────

function route(string $action, string $method, array $body, Auth $auth, Database $db): array {
    return match($action) {
        // Auth
        'register'    => register($body, $auth),
        'verify_otp'  => verifyOtp($body, $auth),
        'resend_otp'  => resendOtp($body, $auth),
        'login'       => login($body, $auth),
        'logout'      => logout($auth),
        'me'          => me($auth),
        // Chat / AI
        'chat'        => chat($body, $auth, $db),
        // Memories
        'memories'    => memories($body, $method, $auth, $db),
        // Reminders
        'reminders'   => reminders($body, $method, $auth, $db),
        // Personality
        'personality' => personality($body, $method, $auth, $db),
        // Voice profile
        'voice'       => voiceProfile($body, $method, $auth, $db),
        // Admin
        'admin_users'     => adminUsers($body, $method, $auth, $db),
        'admin_providers' => adminProviders($body, $method, $auth, $db),
        'admin_settings'  => adminSettings($body, $method, $auth, $db),
        'admin_stats'     => adminStats($auth, $db),
        default       => ['error' => 'Unknown action.'],
    };
}

// ── AUTH HANDLERS ─────────────────────────────────────────

function register(array $b, Auth $auth): array {
    return $auth->register($b['name'] ?? '', $b['email'] ?? '', $b['password'] ?? '');
}
function verifyOtp(array $b, Auth $auth): array {
    return $auth->verifyOtp($b['email'] ?? '', $b['code'] ?? '');
}
function resendOtp(array $b, Auth $auth): array {
    return $auth->resendOtp($b['email'] ?? '');
}
function login(array $b, Auth $auth): array {
    return $auth->login($b['email'] ?? '', $b['password'] ?? '');
}
function logout(Auth $auth): array {
    $auth->logout();
    return ['success' => true];
}
function me(Auth $auth): array {
    $user = $auth->check();
    if (!$user) return ['authenticated' => false];
    unset($user['password_hash'], $user['otp_code'], $user['otp_expires']);
    return ['authenticated' => true, 'user' => $user];
}

// ── CHAT ──────────────────────────────────────────────────

function chat(array $b, Auth $auth, Database $db): array {
    $user    = $auth->requireAuth();
    $message = trim($b['message'] ?? '');
    $session = $b['session_key'] ?? bin2hex(random_bytes(8));
    if (!$message) return ['error' => 'Empty message.'];
    $llm = new LLMManager();
    return $llm->chat((int)$user['id'], $message, $session);
}

// ── MEMORIES ──────────────────────────────────────────────

function memories(array $b, string $method, Auth $auth, Database $db): array {
    $user = $auth->requireAuth();
    $uid  = (int)$user['id'];
    if ($method === 'GET') {
        return ['memories' => $db->fetchAll("SELECT * FROM memories WHERE user_id = ? ORDER BY created_at DESC", [$uid])];
    }
    if ($method === 'POST') {
        $tag     = htmlspecialchars(trim($b['tag'] ?? 'note'), ENT_QUOTES);
        $content = htmlspecialchars(trim($b['content'] ?? ''), ENT_QUOTES);
        if (!$content) return ['error' => 'Content required.'];
        $id = $db->insert('memories', ['user_id' => $uid, 'tag' => $tag, 'content' => $content]);
        return ['success' => true, 'id' => $id];
    }
    if ($method === 'DELETE') {
        $db->query("DELETE FROM memories WHERE id = ? AND user_id = ?", [$b['id'], $uid]);
        return ['success' => true];
    }
    return ['error' => 'Method not allowed.'];
}

// ── REMINDERS ─────────────────────────────────────────────

function reminders(array $b, string $method, Auth $auth, Database $db): array {
    $user = $auth->requireAuth();
    $uid  = (int)$user['id'];
    if ($method === 'GET') {
        return ['reminders' => $db->fetchAll(
            "SELECT * FROM reminders WHERE user_id = ? ORDER BY COALESCE(remind_at,'9999-01-01') ASC",
            [$uid]
        )];
    }
    if ($method === 'POST') {
        $title    = htmlspecialchars(trim($b['title'] ?? ''), ENT_QUOTES);
        $remind   = $b['remind_at'] ? date('Y-m-d H:i:s', strtotime($b['remind_at'])) : null;
        $notes    = htmlspecialchars(trim($b['notes'] ?? ''), ENT_QUOTES);
        if (!$title) return ['error' => 'Title required.'];
        $id = $db->insert('reminders', ['user_id' => $uid, 'title' => $title, 'remind_at' => $remind, 'notes' => $notes]);
        return ['success' => true, 'id' => $id];
    }
    if ($method === 'PATCH') {
        $db->update('reminders', ['is_done' => (int)($b['is_done'] ?? 0)], ['id' => $b['id'], 'user_id' => $uid]);
        return ['success' => true];
    }
    if ($method === 'DELETE') {
        $db->query("DELETE FROM reminders WHERE id = ? AND user_id = ?", [$b['id'], $uid]);
        return ['success' => true];
    }
    return ['error' => 'Method not allowed.'];
}

// ── PERSONALITY ───────────────────────────────────────────

function personality(array $b, string $method, Auth $auth, Database $db): array {
    $user = $auth->requireAuth();
    $uid  = (int)$user['id'];
    if ($method === 'GET') {
        return ['profile' => $db->fetchOne("SELECT * FROM personality_profiles WHERE user_id = ?", [$uid])];
    }
    if ($method === 'POST') {
        $db->update('personality_profiles', [
            'formality'     => (int)($b['formality'] ?? 3),
            'humor'         => (int)($b['humor'] ?? 3),
            'energy'        => (int)($b['energy'] ?? 3),
            'communication' => $b['communication'] ?? 'friendly',
            'tone'          => $b['tone'] ?? 'warm',
            'custom_prompt' => htmlspecialchars($b['custom_prompt'] ?? '', ENT_QUOTES),
        ], ['user_id' => $uid]);
        return ['success' => true];
    }
    return ['error' => 'Method not allowed.'];
}

// ── VOICE PROFILE ─────────────────────────────────────────

function voiceProfile(array $b, string $method, Auth $auth, Database $db): array {
    $user = $auth->requireAuth();
    $uid  = (int)$user['id'];
    if ($method === 'GET') {
        return ['voice' => $db->fetchOne("SELECT * FROM voice_profiles WHERE user_id = ?", [$uid])];
    }
    if ($method === 'POST') {
        $db->update('voice_profiles', [
            'voice_name' => $b['voice_name'] ?? 'default',
            'language'   => $b['language']   ?? 'en-US',
            'rate'       => (float)($b['rate']   ?? 0.95),
            'pitch'      => (float)($b['pitch']  ?? 1.05),
            'wake_word'  => $b['wake_word'] ?? 'PersonaX',
        ], ['user_id' => $uid]);
        return ['success' => true];
    }
    return ['error' => 'Method not allowed.'];
}

// ── ADMIN ─────────────────────────────────────────────────

function adminUsers(array $b, string $method, Auth $auth, Database $db): array {
    $auth->requireAdmin();
    if ($method === 'GET') {
        return ['users' => $db->fetchAll(
            "SELECT id,name,email,role,is_verified,last_login,created_at FROM users ORDER BY created_at DESC"
        )];
    }
    if ($method === 'DELETE') {
        $db->query("DELETE FROM users WHERE id = ?", [(int)$b['id']]);
        return ['success' => true];
    }
    return ['error' => 'Method not allowed.'];
}

function adminProviders(array $b, string $method, Auth $auth, Database $db): array {
    $auth->requireAdmin();
    if ($method === 'GET') {
        return ['providers' => $db->fetchAll("SELECT id,name,slug,model,endpoint,is_active,is_default,priority FROM ai_providers ORDER BY priority")];
    }
    if ($method === 'POST') {
        // Update provider (API key encrypted)
        $data = ['is_active' => (int)($b['is_active'] ?? 0), 'model' => $b['model'] ?? '', 'is_default' => (int)($b['is_default'] ?? 0)];
        if (!empty($b['api_key'])) $data['api_key_enc'] = LLMManager::encryptKey($b['api_key']);
        $db->update('ai_providers', $data, ['id' => (int)$b['id']]);
        return ['success' => true];
    }
    return ['error' => 'Method not allowed.'];
}

function adminSettings(array $b, string $method, Auth $auth, Database $db): array {
    $auth->requireAdmin();
    if ($method === 'GET') {
        return ['settings' => $db->fetchAll("SELECT * FROM settings ORDER BY group_name,`key`")];
    }
    if ($method === 'POST') {
        foreach ($b['settings'] ?? [] as $key => $value) {
            $db->update('settings', ['value' => $value], ['key' => $key]);
        }
        return ['success' => true];
    }
    return ['error' => 'Method not allowed.'];
}

function adminStats(Auth $auth, Database $db): array {
    $auth->requireAdmin();
    return [
        'total_users'         => (int)$db->fetchValue("SELECT COUNT(*) FROM users"),
        'verified_users'      => (int)$db->fetchValue("SELECT COUNT(*) FROM users WHERE is_verified=1"),
        'total_conversations' => (int)$db->fetchValue("SELECT COUNT(*) FROM conversations"),
        'total_memories'      => (int)$db->fetchValue("SELECT COUNT(*) FROM memories"),
        'total_reminders'     => (int)$db->fetchValue("SELECT COUNT(*) FROM reminders"),
        'pending_automations' => (int)$db->fetchValue("SELECT COUNT(*) FROM automation_logs WHERE status='pending'"),
        'provider_usage'      => $db->fetchAll("SELECT provider, COUNT(*) as cnt FROM conversations WHERE role='assistant' GROUP BY provider"),
        'recent_users'        => $db->fetchAll("SELECT name,email,created_at FROM users ORDER BY created_at DESC LIMIT 5"),
    ];
}

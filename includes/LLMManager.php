<?php
// ============================================================
// PersonaX v3 — includes/LLMManager.php
// Provider-based LLM architecture: Claude, OpenAI, Gemini, Local
// ============================================================

class LLMManager {
    private Database $db;
    private array $providers;

    public function __construct() {
        $this->db = Database::getInstance();
        $this->providers = $this->loadProviders();
    }

    // ── PUBLIC API ─────────────────────────────────────────

    /**
     * Generate a response for a user message, injecting memory & personality context.
     */
    public function chat(int $userId, string $message, string $sessionKey = ''): array {
        $provider    = $this->getDefaultProvider();
        $personality = $this->getPersonality($userId);
        $memories    = $this->getMemoryContext($userId);
        $history     = $this->getHistory($userId, $sessionKey);
        $systemPrompt = $this->buildSystemPrompt($userId, $personality, $memories);

        // Log user message
        $this->logTurn($userId, $sessionKey, 'user', $message, $provider['slug'], 0, 0);

        try {
            $result = $this->callProvider($provider, $systemPrompt, $history, $message);
        } catch (Throwable $e) {
            // Fallback to next available provider
            error_log('[PersonaX LLM] Primary failed: ' . $e->getMessage());
            $fallback = $this->getFallbackProvider($provider['slug']);
            if ($fallback) {
                try {
                    $result = $this->callProvider($fallback, $systemPrompt, $history, $message);
                    $provider = $fallback;
                } catch (Throwable $e2) {
                    return ['success' => false, 'error' => 'All AI providers are currently unavailable.'];
                }
            } else {
                return ['success' => false, 'error' => 'AI service unavailable.'];
            }
        }

        // Log assistant response
        $this->logTurn($userId, $sessionKey, 'assistant', $result['text'], $provider['slug'],
                        $result['tokens_in'] ?? 0, $result['tokens_out'] ?? 0);

        return ['success' => true, 'text' => $result['text'], 'provider' => $provider['slug']];
    }

    // ── PROVIDER DISPATCH ──────────────────────────────────

    private function callProvider(array $provider, string $system, array $history, string $message): array {
        return match($provider['slug']) {
            'claude' => $this->callClaude($provider, $system, $history, $message),
            'openai' => $this->callOpenAI($provider, $system, $history, $message),
            'gemini' => $this->callGemini($provider, $system, $history, $message),
            'local'  => $this->callLocal($provider, $system, $history, $message),
            default  => throw new RuntimeException("Unknown provider: {$provider['slug']}")
        };
    }

    // ── CLAUDE (ANTHROPIC) ─────────────────────────────────

    private function callClaude(array $p, string $system, array $history, string $message): array {
        $messages = array_map(fn($h) => ['role' => $h['role'], 'content' => $h['content']], $history);
        $messages[] = ['role' => 'user', 'content' => $message];

        $body = json_encode([
            'model'      => $p['model'] ?? 'claude-sonnet-4-6',
            'max_tokens' => 1024,
            'system'     => $system,
            'messages'   => $messages,
        ]);

        $resp = $this->httpPost($p['endpoint'], $body, [
            'x-api-key: '      . $this->decryptKey($p['api_key_enc']),
            'anthropic-version: 2023-06-01',
            'content-type: application/json',
        ]);

        $data = json_decode($resp, true);
        if (isset($data['error'])) throw new RuntimeException($data['error']['message']);

        return [
            'text'       => $data['content'][0]['text'] ?? '',
            'tokens_in'  => $data['usage']['input_tokens']  ?? 0,
            'tokens_out' => $data['usage']['output_tokens'] ?? 0,
        ];
    }

    // ── OPENAI ─────────────────────────────────────────────

    private function callOpenAI(array $p, string $system, array $history, string $message): array {
        $messages = [['role' => 'system', 'content' => $system]];
        foreach ($history as $h) $messages[] = ['role' => $h['role'], 'content' => $h['content']];
        $messages[] = ['role' => 'user', 'content' => $message];

        $body = json_encode([
            'model'      => $p['model'] ?? 'gpt-4o',
            'max_tokens' => 1024,
            'messages'   => $messages,
        ]);

        $resp = $this->httpPost($p['endpoint'], $body, [
            'Authorization: Bearer ' . $this->decryptKey($p['api_key_enc']),
            'Content-Type: application/json',
        ]);

        $data = json_decode($resp, true);
        if (isset($data['error'])) throw new RuntimeException($data['error']['message']);

        return [
            'text'       => $data['choices'][0]['message']['content'] ?? '',
            'tokens_in'  => $data['usage']['prompt_tokens']     ?? 0,
            'tokens_out' => $data['usage']['completion_tokens'] ?? 0,
        ];
    }

    // ── GOOGLE GEMINI ──────────────────────────────────────

    private function callGemini(array $p, string $system, array $history, string $message): array {
        $contents = [];
        foreach ($history as $h) {
            $contents[] = ['role' => $h['role'] === 'assistant' ? 'model' : 'user',
                           'parts' => [['text' => $h['content']]]];
        }
        $contents[] = ['role' => 'user', 'parts' => [['text' => $message]]];

        $model    = $p['model'] ?? 'gemini-1.5-pro';
        $endpoint = rtrim($p['endpoint'], '/') . "/{$model}:generateContent?key=" . $this->decryptKey($p['api_key_enc']);

        $body = json_encode([
            'system_instruction' => ['parts' => [['text' => $system]]],
            'contents'           => $contents,
        ]);

        $resp = $this->httpPost($endpoint, $body, ['Content-Type: application/json']);
        $data = json_decode($resp, true);
        if (isset($data['error'])) throw new RuntimeException($data['error']['message']);

        return [
            'text'       => $data['candidates'][0]['content']['parts'][0]['text'] ?? '',
            'tokens_in'  => $data['usageMetadata']['promptTokenCount']     ?? 0,
            'tokens_out' => $data['usageMetadata']['candidatesTokenCount'] ?? 0,
        ];
    }

    // ── LOCAL LLM (Ollama / LM Studio) ────────────────────

    private function callLocal(array $p, string $system, array $history, string $message): array {
        $messages = [['role' => 'system', 'content' => $system]];
        foreach ($history as $h) $messages[] = ['role' => $h['role'], 'content' => $h['content']];
        $messages[] = ['role' => 'user', 'content' => $message];

        $body = json_encode(['model' => $p['model'] ?? 'llama3', 'messages' => $messages, 'stream' => false]);
        $resp = $this->httpPost($p['endpoint'], $body, ['Content-Type: application/json']);
        $data = json_decode($resp, true);

        return ['text' => $data['message']['content'] ?? $data['choices'][0]['message']['content'] ?? '', 'tokens_in' => 0, 'tokens_out' => 0];
    }

    // ── CONTEXT BUILDERS ───────────────────────────────────

    private function buildSystemPrompt(int $userId, array $personality, string $memories): string {
        $user  = $this->db->fetchOne("SELECT name FROM users WHERE id = ?", [$userId]);
        $name  = $user['name'] ?? 'User';
        $fname = explode(' ', $name)[0];

        $toneMap = [
            'friendly'     => 'warm, supportive, and emotionally intelligent',
            'professional' => 'formal, precise, and professional',
            'casual'       => 'relaxed, witty, and conversational',
            'concise'      => 'brief, direct, and efficient',
        ];
        $tone = $toneMap[$personality['communication'] ?? 'friendly'] ?? $toneMap['friendly'];

        $prompt  = "You are PersonaX, an advanced voice-first AI companion.\n";
        $prompt .= "The user's name is {$fname}. Address them by first name naturally.\n";
        $prompt .= "Your personality is: {$tone}.\n";
        $prompt .= "Formality level: {$personality['formality']}/5. ";
        $prompt .= "Humor level: {$personality['humor']}/5. ";
        $prompt .= "Energy level: {$personality['energy']}/5.\n";
        $prompt .= "Keep responses conversational and under 3 sentences unless the user asks for detail.\n";
        if ($memories) $prompt .= "\nUser's stored memories and preferences:\n{$memories}\n";
        if (!empty($personality['custom_prompt'])) $prompt .= "\nAdditional persona notes: {$personality['custom_prompt']}\n";

        return $prompt;
    }

    private function getMemoryContext(int $userId): string {
        $mems = $this->db->fetchAll(
            "SELECT tag, content FROM memories WHERE user_id = ? ORDER BY created_at DESC LIMIT 30",
            [$userId]
        );
        return implode("\n", array_map(fn($m) => "[{$m['tag']}] {$m['content']}", $mems));
    }

    private function getHistory(int $userId, string $sessionKey): array {
        if (!$sessionKey) return [];
        return $this->db->fetchAll(
            "SELECT role, content FROM conversations WHERE user_id = ? AND session_key = ? ORDER BY created_at ASC LIMIT 20",
            [$userId, $sessionKey]
        );
    }

    private function getPersonality(int $userId): array {
        return $this->db->fetchOne("SELECT * FROM personality_profiles WHERE user_id = ?", [$userId])
               ?? ['communication' => 'friendly', 'formality' => 3, 'humor' => 3, 'energy' => 3];
    }

    // ── DATABASE HELPERS ───────────────────────────────────

    private function logTurn(int $userId, string $sessionKey, string $role, string $content,
                              string $provider, int $in, int $out): void {
        $this->db->insert('conversations', [
            'user_id'     => $userId,
            'session_key' => $sessionKey ?: bin2hex(random_bytes(8)),
            'role'        => $role,
            'content'     => $content,
            'provider'    => $provider,
            'tokens_in'   => $in,
            'tokens_out'  => $out,
        ]);
    }

    private function loadProviders(): array {
        return $this->db->fetchAll("SELECT * FROM ai_providers ORDER BY priority ASC");
    }

    private function getDefaultProvider(): array {
        foreach ($this->providers as $p) {
            if ($p['is_default'] && $p['is_active']) return $p;
        }
        foreach ($this->providers as $p) {
            if ($p['is_active']) return $p;
        }
        throw new RuntimeException('No active AI provider configured.');
    }

    private function getFallbackProvider(string $excludeSlug): ?array {
        foreach ($this->providers as $p) {
            if ($p['is_active'] && $p['slug'] !== $excludeSlug) return $p;
        }
        return null;
    }

    private function decryptKey(?string $encrypted): string {
        if (!$encrypted) return '';
        // AES-256-CBC decryption using APP_KEY
        $data   = base64_decode($encrypted);
        $iv     = substr($data, 0, 16);
        $cipher = substr($data, 16);
        return openssl_decrypt($cipher, 'AES-256-CBC', substr(APP_KEY, 0, 32), 0, $iv);
    }

    public static function encryptKey(string $key): string {
        $iv = random_bytes(16);
        $enc = openssl_encrypt($key, 'AES-256-CBC', substr(APP_KEY, 0, 32), 0, $iv);
        return base64_encode($iv . $enc);
    }

    // ── HTTP ───────────────────────────────────────────────

    private function httpPost(string $url, string $body, array $headers): string {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $body,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        $resp = curl_exec($ch);
        if (curl_errno($ch)) throw new RuntimeException('cURL: ' . curl_error($ch));
        curl_close($ch);
        return $resp;
    }
}

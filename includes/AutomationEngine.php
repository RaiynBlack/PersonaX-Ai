<?php
// ============================================================
// PersonaX v3 — includes/AutomationEngine.php
// Secure command execution with mandatory user approval
// ============================================================

class AutomationEngine {
    private Database $db;
    private int      $userId;

    public function __construct(int $userId) {
        $this->db     = Database::getInstance();
        $this->userId = $userId;
    }

    // ── PARSE INTENT TO COMMAND ────────────────────────────
    public function parseCommand(string $text): ?array {
        $commands = $this->db->fetchAll(
            "SELECT * FROM commands WHERE is_active = 1"
        );
        $lower = strtolower($text);
        foreach ($commands as $cmd) {
            $trigger = strtolower($cmd['trigger_phrase']);
            if (str_contains($lower, $trigger)) {
                return $cmd;
            }
        }
        // Pattern matching for common intents
        return $this->patternMatch($lower);
    }

    private function patternMatch(string $text): ?array {
        $patterns = [
            [
                'regex'       => '/open\s+(https?:\/\/\S+|www\.\S+|\S+\.(com|org|net|io))/i',
                'action_type' => 'open_url',
                'name'        => 'Open URL',
            ],
            [
                'regex'       => '/remind (me )?(to\s+)?(.+)/i',
                'action_type' => 'create_reminder',
                'name'        => 'Create Reminder',
            ],
            [
                'regex'       => '/(generate|create|make|write)\s+(a\s+)?(report|summary)\s+(.+)?/i',
                'action_type' => 'generate_report',
                'name'        => 'Generate Report',
            ],
        ];

        foreach ($patterns as $p) {
            if (preg_match($p['regex'], $text, $m)) {
                return [
                    'id'               => 0,
                    'name'             => $p['name'],
                    'action_type'      => $p['action_type'],
                    'action_data'      => json_encode(['raw' => $text, 'match' => $m]),
                    'requires_approval'=> 1,
                ];
            }
        }
        return null;
    }

    // ── QUEUE AN ACTION (always pending first) ─────────────
    public function queueAction(string $action, ?int $commandId = null): int {
        return $this->db->insert('automation_logs', [
            'user_id'    => $this->userId,
            'command_id' => $commandId,
            'action'     => $action,
            'status'     => 'pending',
        ]);
    }

    // ── APPROVE & EXECUTE ─────────────────────────────────
    public function approve(int $logId): array {
        $log = $this->db->fetchOne(
            "SELECT * FROM automation_logs WHERE id = ? AND user_id = ?",
            [$logId, $this->userId]
        );
        if (!$log) return ['success' => false, 'error' => 'Not found.'];
        if ($log['status'] !== 'pending') return ['success' => false, 'error' => 'Already processed.'];

        $this->db->update('automation_logs',
            ['status' => 'approved', 'approved_at' => date('Y-m-d H:i:s')],
            ['id' => $logId]
        );

        $result = $this->execute($log);
        $this->db->update('automation_logs',
            ['status' => $result['success'] ? 'executed' : 'failed', 'result' => json_encode($result)],
            ['id' => $logId]
        );
        return $result;
    }

    public function deny(int $logId): array {
        $this->db->update('automation_logs', ['status' => 'denied'], ['id' => $logId, 'user_id' => $this->userId]);
        return ['success' => true, 'message' => 'Action denied.'];
    }

    // ── EXECUTE APPROVED ACTION ────────────────────────────
    private function execute(array $log): array {
        $cmd  = $log['command_id'] ? $this->db->fetchOne("SELECT * FROM commands WHERE id = ?", [$log['command_id']]) : null;
        $type = $cmd['action_type'] ?? $this->inferType($log['action']);

        return match($type) {
            'open_url'        => $this->actionOpenUrl($log),
            'create_reminder' => $this->actionCreateReminder($log),
            'generate_report' => $this->actionGenerateReport($log),
            'send_email'      => $this->actionSendEmail($log, $cmd),
            default           => ['success' => false, 'error' => "Unknown action type: $type"],
        };
    }

    // ── ACTION HANDLERS ────────────────────────────────────

    private function actionOpenUrl(array $log): array {
        // Returns a redirect instruction for the frontend
        preg_match('/(https?:\/\/\S+|www\.\S+)/i', $log['action'], $m);
        $url = $m[1] ?? '';
        if (!$url) return ['success' => false, 'error' => 'No URL found.'];
        $url = str_starts_with($url, 'http') ? $url : 'https://' . $url;
        return ['success' => true, 'type' => 'open_url', 'url' => $url,
                'message' => "Opening {$url}"];
    }

    private function actionCreateReminder(array $log): array {
        preg_match('/remind (me )?(to\s+)?(.+)/i', $log['action'], $m);
        $title = $m[3] ?? $log['action'];
        $id = $this->db->insert('reminders', [
            'user_id'   => $this->userId,
            'title'     => htmlspecialchars(trim($title), ENT_QUOTES),
            'remind_at' => null,
            'notes'     => 'Created by automation',
        ]);
        return ['success' => true, 'type' => 'reminder_created', 'reminder_id' => $id,
                'message' => "Reminder created: {$title}"];
    }

    private function actionGenerateReport(array $log): array {
        // Stub — in production, trigger a Python report generator
        $reportName = 'report_' . $this->userId . '_' . date('Ymd_His') . '.txt';
        $path = UPLOAD_DIR . $reportName;
        file_put_contents($path, "PersonaX Report\nGenerated: " . date('Y-m-d H:i:s') . "\nAction: " . $log['action']);
        return ['success' => true, 'type' => 'report_generated', 'file' => $reportName,
                'message' => "Report generated: {$reportName}"];
    }

    private function actionSendEmail(array $log, ?array $cmd): array {
        // Requires SMTP configured
        return ['success' => false, 'error' => 'Email automation requires SMTP configuration.'];
    }

    private function inferType(string $action): string {
        $lower = strtolower($action);
        if (str_contains($lower, 'open') || str_contains($lower, 'http')) return 'open_url';
        if (str_contains($lower, 'remind'))  return 'create_reminder';
        if (str_contains($lower, 'report'))  return 'generate_report';
        return 'custom';
    }

    // ── PENDING QUEUE ─────────────────────────────────────
    public function getPending(): array {
        return $this->db->fetchAll(
            "SELECT * FROM automation_logs WHERE user_id = ? AND status = 'pending' ORDER BY created_at DESC",
            [$this->userId]
        );
    }
}

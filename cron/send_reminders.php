#!/usr/bin/env php
<?php
// ============================================================
// PersonaX v3 — cron/send_reminders.php
// Run every minute: * * * * * php /path/to/personax/cron/send_reminders.php
// ============================================================

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/Database.php';

$db = Database::getInstance();

// Find all due, unnotified reminders
$due = $db->fetchAll(
    "SELECT r.*, u.email, u.name FROM reminders r
     JOIN users u ON u.id = r.user_id
     WHERE r.remind_at <= NOW()
       AND r.is_done    = 0
       AND r.notified   = 0",
);

if (empty($due)) {
    echo "[" . date('Y-m-d H:i:s') . "] No reminders due.\n";
    exit(0);
}

$headers  = "MIME-Version: 1.0\r\n";
$headers .= "Content-type: text/html; charset=utf-8\r\n";
$headers .= "From: PersonaX <" . SMTP_FROM_EMAIL . ">\r\n";

foreach ($due as $rem) {
    echo "[" . date('Y-m-d H:i:s') . "] Notifying {$rem['email']} — {$rem['title']}\n";

    $fname = explode(' ', $rem['name'])[0];
    $html  = <<<HTML
    <div style="font-family:sans-serif;max-width:480px;margin:auto;background:#1a1635;color:white;padding:36px;border-radius:16px;">
      <h2 style="background:linear-gradient(135deg,#00e5ff,#a855f7);-webkit-background-clip:text;-webkit-text-fill-color:transparent;">⏰ PersonaX Reminder</h2>
      <p style="color:rgba(255,255,255,.7);">Hi {$fname},</p>
      <p style="color:rgba(255,255,255,.7);">Your reminder is due:</p>
      <div style="font-size:20px;font-weight:700;color:#00e5ff;padding:16px 0;">{$rem['title']}</div>
      {$( $rem['notes'] ? "<p style='color:rgba(255,255,255,.5);font-size:13px;'>{$rem['notes']}</p>" : '' )}
      <p style="color:rgba(255,255,255,.4);font-size:12px;">This is an automated reminder from PersonaX.</p>
    </div>
    HTML;

    $sent = mail($rem['email'], "⏰ Reminder: {$rem['title']}", $html, $headers);
    if ($sent) {
        $db->update('reminders', ['notified' => 1], ['id' => $rem['id']]);
        $db->insert('email_log', [
            'user_id'  => $rem['user_id'],
            'to_email' => $rem['email'],
            'subject'  => "⏰ Reminder: {$rem['title']}",
            'type'     => 'notification',
            'status'   => 'sent',
            'sent_at'  => date('Y-m-d H:i:s'),
        ]);
        echo "  ✓ Email sent.\n";
    } else {
        echo "  ✗ Email failed.\n";
    }
}

echo "[" . date('Y-m-d H:i:s') . "] Done. Processed " . count($due) . " reminder(s).\n";

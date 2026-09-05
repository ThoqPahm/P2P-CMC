<?php

declare(strict_types=1);
date_default_timezone_set('Asia/Ho_Chi_Minh');

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/ContentModerator.php';
require_once __DIR__ . '/SecretVault.php';
require_once __DIR__ . '/AiProviderManager.php';
require_once __DIR__ . '/WidgetAiAssistant.php';
require_once __DIR__ . '/WidgetChatAssistant.php';

$db = Database::connection();
require_once __DIR__ . '/AmbassadorProgram.php';
AmbassadorProgram::migrate($db);
require_once __DIR__ . '/WorkflowIntegrity.php';
WorkflowIntegrity::migrate($db);
$db->exec("UPDATE users SET is_online=0 WHERE is_online=1 AND (last_seen_at IS NULL OR last_seen_at<datetime('now','-2 minutes'))");

<?php

declare(strict_types=1);

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

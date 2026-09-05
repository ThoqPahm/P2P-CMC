<?php
declare(strict_types=1);
// Only for an explicitly started loopback QA server; never a production entry point.
$qaPath = getenv('PROGRAM_QA_DATABASE');
if (PHP_SAPI !== 'cli-server' || !$qaPath || !str_starts_with($qaPath,'/private/tmp/ch4-qa.')) {
    http_response_code(404);
    exit;
}
$path = parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH);
if (is_string($path) && str_starts_with($path,'/assets/')) { return false; }
require_once __DIR__.'/../app/Database.php';
$qa = new PDO('sqlite:'.$qaPath,null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
$qa->exec('PRAGMA foreign_keys=ON');
(new ReflectionMethod(Database::class,'migrate'))->invoke(null,$qa);
(new ReflectionProperty(Database::class,'connection'))->setValue(null,$qa);
session_name('cmc_program_qa');
session_start();
// Switch between seeded test roles, in this isolated database only.
if (in_array($_GET['qa_role']??'', ['admin','student','ambassador','prospect'],true)) {
    $stmt=$qa->prepare('SELECT id FROM users WHERE role=? LIMIT 1');
    $stmt->execute([$_GET['qa_role']]);
    $_SESSION['user_id']=(int)$stmt->fetchColumn();
}
$allowed=['/index.php'=>'index.php','/program-actions.php'=>'program-actions.php','/actions.php'=>'actions.php','/api.php'=>'api.php'];
if (!isset($allowed[$path])) {
    $_SERVER['SCRIPT_NAME'] = '/index.php';
    require __DIR__.'/../index.php';
} else {
    $_SERVER['SCRIPT_NAME'] = $path;
    require __DIR__.'/../'.$allowed[$path];
}

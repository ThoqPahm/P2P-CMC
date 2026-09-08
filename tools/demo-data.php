<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require __DIR__.'/../app/bootstrap.php';
$file = __DIR__.'/../data/demo-snapshot.json';
$excluded = ['widget_access_tokens','ai_provider_configs','ai_provider_keys','ai_requests','widget_ai_logs','program_audit','chat_access_audit','chat_reviewers','ambassador_fixture_versions'];
$tables = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'")->fetchAll(PDO::FETCH_COLUMN);
$allowed = array_values(array_diff($tables, $excluded));
if (($argv[1] ?? '') === 'export') {
    $data = [];
    foreach ($allowed as $table) {
        $data[$table] = $db->query('SELECT * FROM "'.$table.'"')->fetchAll();
        foreach ($data[$table] as &$row) {
            foreach ($row as $key => &$value) {
                if (preg_match('/password|token|secret|api_key/i', $key)) $value = null;
                if (is_string($value) && preg_match('/AIza[\w-]{20,}|sk-[\w-]{15,}|Bearer\s+\S+/i', $value)) {
                    throw new RuntimeException("Possible credential in $table.$key. Export stopped.");
                }
            }
            unset($value);
            if ($table === 'users') { $row['is_online'] = 0; $row['last_seen_at'] = null; }
            if ($table === 'ui_settings' && preg_match('/key|token|secret|password/i', $row['key'])) throw new RuntimeException('Sensitive setting detected.');
        }
        unset($row);
    }
    file_put_contents($file, json_encode(['version'=>1,'tables'=>$data], JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)."\n");
    echo "Exported public demo snapshot (no passwords, provider keys, tokens or technical logs).\n";
    exit;
}
if (($argv[1] ?? '') !== 'import' || !in_array('--apply', $argv, true)) exit("Usage: php tools/demo-data.php export | import --apply\nImport replaces demo content, preserving AI provider configuration.\n");
$snapshot = json_decode(file_get_contents($file), true, 512, JSON_THROW_ON_ERROR);
if (($snapshot['version'] ?? 0) !== 1 || !is_array($snapshot['tables'] ?? null)) throw new RuntimeException('Invalid snapshot');
foreach ($snapshot['tables'] as $table=>$rows) if (!in_array($table,$allowed,true)) throw new RuntimeException('Disallowed table: '.$table);
$backup = __DIR__.'/../tmp/before-demo-import-'.date('Ymd-His').'-'.bin2hex(random_bytes(3)).'.sqlite';
if (!is_dir(dirname($backup))) mkdir(dirname($backup),0700,true);
$db->exec('VACUUM INTO '.$db->quote($backup));
$passwords = $db->query('SELECT email,password FROM users')->fetchAll(PDO::FETCH_KEY_PAIR);
if (in_array('--check', $argv, true)) {
    $db = new PDO('sqlite:'.$backup, null, null, [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
    $db->exec('PRAGMA foreign_keys=ON');
}
$db->beginTransaction();
try {
    $db->exec('PRAGMA defer_foreign_keys=ON');
    foreach (array_keys($snapshot['tables']) as $table) $db->exec('DELETE FROM "'.$table.'"');
    foreach ($snapshot['tables'] as $table=>$rows) foreach ($rows as $row) {
        $columns = $db->query('PRAGMA table_info("'.$table.'")')->fetchAll();
        $valid = array_column($columns,'name');
        foreach (array_keys($row) as $column) if (!in_array($column,$valid,true)) throw new RuntimeException('Unknown column');
        if ($table==='users') $row['password']=$passwords[$row['email']] ?? password_hash(bin2hex(random_bytes(32)),PASSWORD_DEFAULT);
        if (array_key_exists('public_token',$row)) $row['public_token']=bin2hex(random_bytes(32));
        $sql='INSERT INTO "'.$table.'" ('.implode(',',array_map(fn($c)=>'"'.$c.'"',array_keys($row))).') VALUES ('.implode(',',array_fill(0,count($row),'?')).')';
        $db->prepare($sql)->execute(array_values($row));
    }
    if ($db->query('PRAGMA foreign_key_check')->fetch()) throw new RuntimeException('Snapshot conflicts with retained records; import rolled back.');
    $db->commit();
    echo "Imported. Backup: $backup\nExisting account passwords and AI provider keys retained.\n";
} catch (Throwable $e) { $db->rollBack(); throw $e; }

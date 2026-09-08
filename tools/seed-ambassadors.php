<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
if (!in_array('--apply', $argv, true)) { exit("Usage: php tools/seed-ambassadors.php --apply\nAdds fictional profiles once. Existing data is not overwritten.\n"); }
define('AMBASSADOR_FIXTURE_MANUAL_INSTALL', true);
require __DIR__.'/../app/bootstrap.php';
$profiles=AmbassadorProfiles::samples();
foreach($profiles as $p) { if(!is_file(__DIR__.'/../'.$p['avatar'])) throw new RuntimeException('Missing portrait: '.$p['avatar']); }
$backup=__DIR__.'/../tmp/before-ambassadors-'.date('Ymd-His').'-'.bin2hex(random_bytes(3)).'.sqlite';
if(!is_dir(dirname($backup))) mkdir(dirname($backup),0700,true);
$db->exec('VACUUM INTO '.$db->quote($backup));
echo 'Backup: '.$backup."\n";
echo AmbassadorProfiles::installBundledProfiles($db)." profiles added.\n";

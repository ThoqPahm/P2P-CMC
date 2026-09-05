<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require __DIR__.'/../app/helpers.php';
require __DIR__.'/../app/ChatPrivacy.php';
$db=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
$db->exec("CREATE TABLE users(id INTEGER PRIMARY KEY,name TEXT,role TEXT); CREATE TABLE conversations(id INTEGER PRIMARY KEY,escalation_status TEXT); CREATE TABLE messages(id INTEGER PRIMARY KEY,conversation_id INTEGER,sender_id INTEGER,content TEXT,is_flagged INTEGER); CREATE TABLE program_audit(actor_id INTEGER,entity TEXT,entity_id INTEGER,action TEXT,snapshot TEXT);
INSERT INTO users VALUES(1,'QA','prospect'); INSERT INTO conversations VALUES(1,'none'),(2,'pending');");
for($i=1;$i<=30;$i++) $db->prepare('INSERT INTO messages VALUES(?,1,1,?,0)')->execute([$i,'private-'.$i]);
$checks=0;
function check(bool $ok,string $label):void {if(!$ok)throw new RuntimeException($label);$GLOBALS['checks']++;echo "PASS $label\n";}
putenv('SUPER_ADMIN_EMAILS=owner@example.test');putenv('CHAT_MODERATOR_EMAILS=reviewer@example.test');
check(!ChatPrivacy::allowed(['role'=>'admin','status'=>'active','email'=>'ordinary@example.test']),'ordinary admin denied');
check(ChatPrivacy::allowed(['role'=>'admin','status'=>'active','email'=>'owner@example.test']),'owner assigned');
check(ChatPrivacy::allowed(['role'=>'admin','status'=>'active','email'=>'reviewer@example.test']),'explicit reviewer allowed');
check(!ChatPrivacy::allowed(['role'=>'student','status'=>'active','email'=>'reviewer@example.test']),'role enforced');
check(!ChatPrivacy::allowed(['role'=>'admin','status'=>'inactive','email'=>'owner@example.test']),'inactive reviewer denied');
check(ChatPrivacy::context($db,1)===[] && !ChatPrivacy::pending($db,1),'ordinary chat hidden');
check(ChatPrivacy::pending($db,2) && ChatPrivacy::context($db,2)===[],'escalation grants no chat access');
$db->exec('UPDATE messages SET is_flagged=1 WHERE id=15');
$context=ChatPrivacy::context($db,1);
check(array_column($context,'id')===[13,14,15,16,17],'two neighboring messages only');
ChatPrivacy::audit($db,1,1,'review_opened',array_column($context,'id'));
$audit=$db->query('SELECT snapshot FROM program_audit')->fetchColumn();
check(str_contains($audit,'15') && !str_contains($audit,'private-'),'audit IDs without message content');
$db->exec('UPDATE messages SET is_flagged=0');
check(ChatPrivacy::context($db,1)===[],'restoration revokes context');
$db->exec('UPDATE messages SET is_flagged=1');
check(count(ChatPrivacy::context($db,1))<=25,'context capped');
echo "$checks privacy checks passed.\n";

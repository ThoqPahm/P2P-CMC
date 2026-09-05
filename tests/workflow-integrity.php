<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
date_default_timezone_set('Asia/Ho_Chi_Minh');
require __DIR__.'/../app/Database.php';
require __DIR__.'/../app/AmbassadorProgram.php';
require __DIR__.'/../app/WorkflowIntegrity.php';
$db=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
$db->exec('PRAGMA foreign_keys=ON');
$migrate=new ReflectionMethod(Database::class,'migrate');
$migrate->invoke(null,$db); AmbassadorProgram::migrate($db); WorkflowIntegrity::migrate($db);
$checks=0;
$check=static function(bool $ok,string $label) use (&$checks): void { if(!$ok)throw new RuntimeException('FAIL: '.$label); $checks++; echo "PASS $label\n"; };
$db->exec("UPDATE users SET policy_status='suspended',followers=17 WHERE id=3");
$db->exec("UPDATE ai_knowledge_entries SET content='Edited official content for regression test' WHERE id=1");
$migrate->invoke(null,$db);
$check($db->query('SELECT followers FROM users WHERE id=3')->fetchColumn()==17,'migration does not overwrite profiles');
$check($db->query('SELECT content FROM ai_knowledge_entries WHERE id=1')->fetchColumn()==='Edited official content for regression test','migration does not overwrite knowledge');
$check(!$db->query('SELECT id FROM eligible_ambassadors WHERE id=3')->fetchColumn(),'suspended policy excluded');
$db->exec("UPDATE users SET policy_status='approved' WHERE id=3");
$check((bool)$db->query('SELECT id FROM eligible_ambassadors WHERE id=3')->fetchColumn(),'existing approved ambassador retained');
$db->exec("INSERT INTO ambassador_applications(user_id,motivation,topics,skills,availability,status) VALUES(3,'QA','QA','QA','QA','approved')");
$check(!$db->query('SELECT id FROM eligible_ambassadors WHERE id=3')->fetchColumn(),'registered member requires training');
foreach(array_keys(AmbassadorProgram::MODULES) as $module){$db->prepare('INSERT INTO ambassador_training(user_id,module) VALUES(3,?)')->execute([$module]);}
$check((bool)$db->query('SELECT id FROM eligible_ambassadors WHERE id=3')->fetchColumn(),'trained approved member eligible');
$db->exec("UPDATE ambassador_applications SET participation='paused' WHERE user_id=3");
$check(!$db->query('SELECT id FROM eligible_ambassadors WHERE id=3')->fetchColumn(),'paused member excluded');
$db->exec("INSERT INTO submissions(campaign_id,user_id,content_url) VALUES(1,2,'https://example.test/new')");
$id=(int)$db->lastInsertId(); $submission=['id'=>$id,'user_id'=>2];
$reward=static function(string $status,int $points) use($db,$submission):void { $db->beginTransaction(); WorkflowIntegrity::reconcileReward($db,$submission,$status,$points); $db->commit(); };
$balance=static fn():int=>(int)$db->query("SELECT COALESCE(SUM(CASE WHEN type='credit' THEN points ELSE -points END),0) FROM wallet_transactions WHERE reference_type='submission' AND reference_id=$id")->fetchColumn();
$reward('approved',50); $reward('approved',50);
$check($balance()===50,'repeated approval idempotent');
$check((int)$db->query("SELECT COUNT(*) FROM wallet_transactions WHERE reference_id=$id AND reference_type='submission'")->fetchColumn()===1,'one reward event for repeated approval');
$reward('rejected',50); $check($balance()===0,'withdrawal reverses current award');
$reward('approved',50); $check($balance()===50,'reapproval does not duplicate net reward');
$reward('approved',90); $check($balance()===90,'reward adjustment applies delta only');
WorkflowIntegrity::migrate($db); $reward('approved',90);
$check($balance()===90,'migration does not freeze managed rewards');
$before=(int)$db->query('SELECT COUNT(*) FROM wallet_transactions')->fetchColumn();
$db->beginTransaction();
try{WorkflowIntegrity::reconcileReward($db,['id'=>2,'user_id'=>3],'rejected',0);$blocked=false;}catch(InvalidArgumentException){$blocked=true;}
$db->rollBack();
$check($blocked,'historical reward requires review');
$check((int)$db->query('SELECT COUNT(*) FROM wallet_transactions')->fetchColumn()===$before,'historical ledger untouched');
$db->exec("UPDATE campaigns SET deadline='2000-01-01' WHERE id=1");
try{WorkflowIntegrity::requireOpenCampaign($db,1);$blocked=false;}catch(InvalidArgumentException){$blocked=true;}
$check($blocked,'expired campaign rejected');
$db->prepare("UPDATE campaigns SET deadline=?,status='active' WHERE id=1")->execute([date('Y-m-d')]);
$check(WorkflowIntegrity::requireOpenCampaign($db,1)['id']===1,'deadline day still accepts submissions');
$first=WorkflowIntegrity::quality($db,1);
$db->exec("UPDATE conversations SET rating=5,clarity_rating=5,helpfulness_rating=5 WHERE id=1");
$check(WorkflowIntegrity::quality($db,1)===$first,'user rating does not inflate safety score');
$db->exec('UPDATE messages SET is_flagged=1 WHERE id=1');
$check(WorkflowIntegrity::quality($db,1)<$first,'flagging changes quality deterministically');
$db->exec('UPDATE messages SET is_flagged=0 WHERE id=1');
$check(WorkflowIntegrity::quality($db,1)===$first,'restoring message restores score');
$check(!$db->query('PRAGMA foreign_key_check')->fetch(),'foreign keys valid');
echo "$checks checks passed.\n";

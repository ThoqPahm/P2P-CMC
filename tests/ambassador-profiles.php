<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require __DIR__.'/../app/Database.php';
require __DIR__.'/../app/AmbassadorProgram.php';
require __DIR__.'/../app/WorkflowIntegrity.php';
require __DIR__.'/../app/AmbassadorProfiles.php';
$db=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
$db->exec('PRAGMA foreign_keys=ON');
(new ReflectionMethod(Database::class,'migrate'))->invoke(null,$db);
AmbassadorProgram::migrate($db);WorkflowIntegrity::migrate($db);AmbassadorProfiles::migrate($db);
$checks=0;
function check(bool $ok,string $label):void{if(!$ok)throw new RuntimeException($label);$GLOBALS['checks']++;echo "PASS $label\n";}
$samples=AmbassadorProfiles::samples();
$before=(int)$db->query('SELECT COUNT(*) FROM wallet_transactions')->fetchColumn();
check(count($samples)===12,'12 distinct profiles');
check(AmbassadorProfiles::seed($db,$samples)===12,'seed creates profiles');
check(AmbassadorProfiles::seed($db,$samples)===0,'repeat seed does not duplicate');
check(count(AmbassadorProfiles::directory($db))===15,'directory combines existing and new ambassadors');
check((int)$db->query('SELECT COUNT(*) FROM wallet_transactions')->fetchColumn()===$before,'no invented points');
$id=(int)$db->query('SELECT user_id FROM ambassador_profiles LIMIT 1')->fetchColumn();
$db->prepare("UPDATE ambassador_profiles SET about='Edited by admin' WHERE user_id=?")->execute([$id]);
AmbassadorProfiles::migrate($db);AmbassadorProfiles::seed($db,$samples);
check(AmbassadorProfiles::details($db,$id)['about']==='Edited by admin','edited profiles preserved');
$db->prepare("UPDATE users SET status='inactive' WHERE id=?")->execute([$id]);
check(count(AmbassadorProfiles::directory($db))===14,'eligibility remains enforced');
check(AmbassadorProfiles::avatar('javascript:alert(1)')==='','unsafe image path rejected');
check(AmbassadorProfiles::avatar('assets/img/../../data/a.png')==='','traversal rejected');
check(!array_key_exists('password',AmbassadorProfiles::directory($db)[0]),'public directory excludes secrets');
check(!(bool)$db->query('PRAGMA foreign_key_check')->fetch(),'foreign keys valid');
foreach($samples as $p){
    check(mb_strlen($p['about'])>100 && count(explode('|',$p['topics']))>=3 && $p['advice']!=='','complete '.$p['name']);
}
echo "$checks checks passed.\n";

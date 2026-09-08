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
check(AmbassadorProfiles::studentCodePrefix('Quản trị kinh doanh')==='BBA','business prefix');
check(AmbassadorProfiles::studentCodePrefix('Công nghệ thông tin')==='BIT','IT prefix');
check(AmbassadorProfiles::studentCodePrefix('Khoa học máy tính')==='BCS','computer science prefix');
check(AmbassadorProfiles::studentCodePrefix('Thiết kế đồ họa')==='BGD','graphic design prefix');
check(AmbassadorProfiles::studentCodePrefix('Ngôn ngữ Hàn Quốc')==='BKL','Korean language prefix');
check(AmbassadorProfiles::studentCodePrefix('Ngôn ngữ Nhật')==='BJL','Japanese language prefix');
check(AmbassadorProfiles::entryYearForStudyYear(3)==='23','study year maps to entry year');
check(count(array_unique(array_column($samples,'student_code')))===12,'student codes are unique');
foreach($samples as $p) check(AmbassadorProfiles::isValidStudentCode($p['student_code'],$p['major'],(int)$p['year']),'student code matches '.$p['name']);
check(AmbassadorProfiles::installBundledProfiles($db)===12,'first boot installs bundled profiles');
check(AmbassadorProfiles::installBundledProfiles($db)===0,'next boot does not duplicate profiles');
check(AmbassadorProfiles::seed($db,$samples)===0,'repeat seed does not duplicate');
check((int)$db->query("SELECT COUNT(*) FROM users WHERE role='ambassador' AND student_code GLOB '[A-Z][A-Z][A-Z][0-9][0-9]1[0-9][0-9][0-9]'")->fetchColumn()===15,'all ambassador records use structured codes');
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
$db->prepare('DELETE FROM ambassador_profiles WHERE user_id=?')->execute([$id]);
check(AmbassadorProfiles::installBundledProfiles($db)===0,'completed installation does not restore deleted profile');
check(AmbassadorProfiles::details($db,$id)===[],'admin deletion preserved');
echo "$checks checks passed.\n";

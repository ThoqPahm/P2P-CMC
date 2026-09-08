<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') exit;
// Isolated provider double and in-memory DB: never sends private data or spends API credits.
final class AiProviderManager {
    public static ?array $config = ['provider'=>'test'];
    public static array $result = [];
    public static array $context = [];
    public static function activeConfig(): ?array { return self::$config; }
    public static function requestJson(string $prompt, array $context, array $config, int $tokens): array { self::$context=$context; return self::$result; }
}
require __DIR__.'/../app/Database.php';
require __DIR__.'/../app/AmbassadorProgram.php';
require __DIR__.'/../app/ProgramAiAssistant.php';
require __DIR__.'/../app/WidgetChatAssistant.php';
$db=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
(new ReflectionMethod(Database::class,'migrate'))->invoke(null,$db);
AmbassadorProgram::migrate($db);
$count=0;
$check=static function(bool $ok,string $label) use (&$count):void { if(!$ok)throw new RuntimeException($label); ++$count; echo "PASS $label\n"; };
$reject=static function(callable $fn,string $label) use ($check):void { try {$fn();} catch(InvalidArgumentException $e) {$check(true,$label);return;} $check(false,$label); };
$c=$db->query('SELECT * FROM conversations LIMIT 1')->fetch();
$admin=(int)$db->query("SELECT id FROM users WHERE role='admin' LIMIT 1")->fetchColumn();
$reject(fn()=>ProgramAiAssistant::conversation($db,$admin,(int)$c['id']),'admin cannot send private chat to AI');
$reject(fn()=>ProgramAiAssistant::conversation($db,99999,(int)$c['id']),'other ambassador denied');
$check(ProgramAiAssistant::knowledge($db)===[],'unconfirmed knowledge excluded');
$redacted=ProgramAiAssistant::redact('Email hs@example.com số 0912345678 mã 123456789012');
$check(!str_contains($redacted,'hs@example.com')&&!str_contains($redacted,'0912345678')&&!str_contains($redacted,'123456789012'),'contact identifiers redacted');
AiProviderManager::$result=['summary'=>'Đang cân nhắc ngành học.', 'category'=>'policy','draft'=>'Bạn muốn xác nhận điều kiện cho ngành nào?', 'escalation_note'=>'Xác nhận điều kiện.', 'source_ids'=>[999999]];
$before=(int)$db->query('SELECT COUNT(*) FROM messages')->fetchColumn();
$result=ProgramAiAssistant::conversation($db,(int)$c['ambassador_id'],(int)$c['id']);
$check($result['category']==='policy'&&$result['sources']===[],'classification and unknown source filtering');
$check((int)$db->query('SELECT COUNT(*) FROM messages')->fetchColumn()===$before,'AI draft does not send message');
$check(!isset(AiProviderManager::$context['email']),'minimal context has no account record');
AiProviderManager::$result=['summary'=>'Chưa đủ dữ liệu.', 'actions'=>['Rà soát nguồn hết hạn.'], 'limitations'=>'Không suy ra nhu cầu từng học sinh.'];
ProgramAiAssistant::insights($db);
$check(!isset(AiProviderManager::$context['HISTORY'])&&!str_contains(json_encode(AiProviderManager::$context),'sender_id'),'admin insights aggregate only');
$directions=array_fill(0,3,['title'=>'Một cảnh học thật','format'=>'Video','hook'=>'Điều gì xảy ra trong buổi học?','beats'=>['[Bổ sung trải nghiệm thật]'],'cta'=>'Bạn muốn biết thêm gì?']);
AiProviderManager::$result=['directions'=>$directions,'source_ids'=>[999], 'warnings'=>['Xác nhận chính sách.']];
$generated=ProgramAiAssistant::copilot($db,['title'=>'Chiến dịch','brief'=>'Trải nghiệm học tập'],'Kể chuyện giờ học','Reels','Chân thật');
$check(count($generated['directions'])===3&&$generated['brand_score']===null,'real generation contract without fabricated score');
AiProviderManager::$result=['directions'=>[['title'=>'Incomplete']]];
$reject(fn()=>ProgramAiAssistant::copilot($db,['title'=>'A','brief'=>'B'],'C','Reels','Chân thật'),'malformed provider result rejected');
AiProviderManager::$config=null;
$reject(fn()=>ProgramAiAssistant::copilot($db,['title'=>'A','brief'=>'B'],'C','Reels','Chân thật'),'no key reports unavailable instead of canned AI');
$validate=new ReflectionMethod(WidgetChatAssistant::class,'validateAiResult');
$check($validate->invoke(null,['answer'=>'Bạn thích làm việc với hình ảnh hay con số hơn?','intent'=>'clarify'],[],[],[])!==null,'natural clarification does not require fake citations');
$check($validate->invoke(null,['answer'=>'Mình nghe đây, bạn nói tiếp nhé.','intent'=>'social'],[],[],[])!==null,'social response accepted without keyword rules');
$check($validate->invoke(null,['answer'=>'Học phí là 100 triệu.','intent'=>'general'],[],[],[])===null,'factual answer without source rejected');
$check($validate->invoke(null,['answer'=>'Thông tin có nguồn.','intent'=>'general','source_ids'=>[1]],[['id'=>1]],[],[],true,[])['source_ids']===[1],'clarification rules cannot erase model citations');
echo "$count checks passed.\n";

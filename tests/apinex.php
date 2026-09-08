<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') exit;
require __DIR__.'/../app/Database.php';
require __DIR__.'/../app/AiProviderManager.php';
$db=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$db->exec("CREATE TABLE ai_provider_configs(provider TEXT PRIMARY KEY CHECK(provider IN ('gemini','deepseek','glm','qwen')),api_key_encrypted TEXT); CREATE TABLE ai_provider_keys(id INTEGER PRIMARY KEY AUTOINCREMENT,provider TEXT CHECK(provider IN ('gemini','deepseek','glm','qwen')),api_key_encrypted TEXT); INSERT INTO ai_provider_configs VALUES('gemini','encrypted-test'); INSERT INTO ai_provider_keys VALUES(42,'gemini','encrypted-slot');");
Database::migrateApinex($db);
Database::migrateApinex($db);
if ($db->query("SELECT api_key_encrypted FROM ai_provider_configs WHERE provider='gemini'")->fetchColumn()!=='encrypted-test') throw new RuntimeException('Config lost');
if ($db->query('SELECT api_key_encrypted FROM ai_provider_keys WHERE id=42')->fetchColumn()!=='encrypted-slot') throw new RuntimeException('Slot lost');
$db->exec("INSERT INTO ai_provider_configs VALUES('apinex',''); INSERT INTO ai_provider_keys(provider,api_key_encrypted) VALUES('apinex','test')");
$url=AiProviderManager::normalizeEndpoint('apinex','https://api.apinex.bond/v1/');
if ($url!=='https://api.apinex.bond/v1/chat/completions') throw new RuntimeException('URL not normalized');
$validate=new ReflectionMethod(AiProviderManager::class,'assertEndpoint');
$validate->invoke(null,'apinex',$url);
foreach(['https://evil.example/v1/chat/completions','https://api.apinex.bond.evil.example/v1/chat/completions','http://api.apinex.bond/v1/chat/completions','https://api.apinex.bond:444/v1/chat/completions'] as $bad) {
    try { $validate->invoke(null,'apinex',$bad); } catch(InvalidArgumentException) { continue; }
    throw new RuntimeException('Unsafe endpoint accepted');
}
echo "PASS APINEX registration, idempotent migration, key preservation, URL normalization and host restrictions.\n";
$source=file_get_contents(__DIR__.'/../app/AiProviderManager.php');
if (!str_contains($source, "=== 'apinex' ? 60000 : 15000") || !str_contains($source, "'stream' => false")) throw new RuntimeException('APINEX timeout or non-streaming request missing');
echo "PASS APINEX uses a 60-second timeout and explicit non-streaming responses.\n";
$extract=new ReflectionMethod(AiProviderManager::class,'assistantContent');
if ($extract->invoke(null,['choices'=>[['message'=>['content'=>[['type'=>'text','text'=>'OK']]]]]])!=='OK') throw new RuntimeException('Content parts unsupported');
try { $extract->invoke(null,['choices'=>[['message'=>['content'=>'','reasoning_content'=>'thinking']]]]); throw new RuntimeException('Reasoning-only response accepted'); }
catch (RuntimeException $error) { if (!str_contains($error->getMessage(),'chưa tạo phần trả lời')) throw $error; }
echo "PASS APINEX supports content parts and explains reasoning-only responses.\n";
$budget=new ReflectionMethod(AiProviderManager::class,'testTokenBudget');
if ($budget->invoke(null,'apinex')!==512 || $budget->invoke(null,'gemini')!==128) throw new RuntimeException('Test budget missing');
echo "PASS APINEX test allows enough completion tokens for reasoning models.\n";

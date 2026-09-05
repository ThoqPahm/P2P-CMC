<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$base=$argv[1]??'';
if (!preg_match('~^http://127\.0\.0\.1:\d+$~',$base)) throw new RuntimeException('Loopback QA URL required');
function request(string $url, ?array $post=null, bool $follow=true): array {
    static $curl=null;
    if ($curl===null) { $curl=curl_init(); curl_setopt($curl,CURLOPT_COOKIEFILE,''); }
    curl_setopt_array($curl,[CURLOPT_URL=>$url,CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>15,CURLOPT_HTTPGET=>true,CURLOPT_FOLLOWLOCATION=>$post===null && $follow,CURLOPT_MAXREDIRS=>5]);
    if($post!==null)curl_setopt_array($curl,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>http_build_query($post)]);
    $body=curl_exec($curl); $status=curl_getinfo($curl,CURLINFO_HTTP_CODE);
    if($body===false)throw new RuntimeException(curl_error($curl));
    return [$status,$body];
}
$checks=0;
function check(bool $ok,string $label):void { if(!$ok)throw new RuntimeException('FAIL '.$label);$GLOBALS['checks']++;echo "PASS $label\n"; }
[$status,$html]=request($base.'/index.php?page=widget');
preg_match('/window\.eAmbassadorWidget = (.*);/',$html,$matches);
$config=json_decode($matches[1]??'',true,512,JSON_THROW_ON_ERROR);
$payload=['widget_token'=>$config['token'],'ambassador_id'=>$config['ambassadors'][0]['id'],'name'=>'Guest QA','email'=>'isolation-qa@example.test'];
[$status,$body]=request($base.'/api.php?action=widget_start_chat',$payload);
$first=json_decode($body,true,512,JSON_THROW_ON_ERROR);
check($status===200 && $first['ok'],'first guest thread');
[$status,$body]=request($base.'/api.php?action=widget_start_chat',$payload);
$second=json_decode($body,true,512,JSON_THROW_ON_ERROR);
check($status===200 && $second['ok'],'same email can start separate guest thread');
check($first['conversation_id']!==$second['conversation_id'],'same email never retrieves old thread');
check($first['current_user_id']!==$second['current_user_id'],'unverified email cannot merge guest identities');
$read=['widget_token'=>$config['token'],'conversation_id'=>$first['conversation_id'],'conversation_token'=>$first['conversation_token']];
[$status,$body]=request($base.'/api.php?action=widget_messages&'.http_build_query($read));
check($status===200 && json_decode($body,true)['ok'],'old browser token remains valid');
$read['conversation_token']=$second['conversation_token'];
[$status]=request($base.'/api.php?action=widget_messages&'.http_build_query($read));
check($status===403,'cross-thread token rejected');
[$status]=request($base.'/api.php?action=widget_start_chat');
check($status===405,'GET cannot create thread');
$payload['widget_token']='invalid';
[$status]=request($base.'/api.php?action=widget_start_chat',$payload);
check($status===419,'invalid widget token rejected');
$payload['widget_token']=$config['token'];
$schedule=$payload+['preferred_at'=>date('Y-m-d H:i:s',time()+86400),'conversation_id'=>$first['conversation_id'],'conversation_token'=>$first['conversation_token']];
[$status,$body]=request($base.'/api.php?action=widget_schedule',$schedule);
$appointment=json_decode($body,true,512,JSON_THROW_ON_ERROR);
check($status===200 && $appointment['ok'],'schedule created');
$lookup=['widget_token'=>$config['token'],'appointment_id'=>$appointment['appointment_id'],'appointment_token'=>$appointment['appointment_token']];
[$status,$body]=request($base.'/api.php?action=widget_appointment_status&'.http_build_query($lookup));
check($status===200 && json_decode($body,true)['appointment']['status']==='pending','student can read pending status');
[$status]=request($base.'/api.php?action=widget_appointment_status&'.http_build_query(array_replace($lookup,['appointment_token'=>'invalid'])));
check($status===403,'appointment token enforced');
[$status,$html]=request($base.'/index.php?page=admin-widget&qa_role=admin');
preg_match('/name="csrf_token" value="([^"]+)"/',$html,$match);
[$status]=request($base.'/actions.php?action=update_appointment_status',['csrf_token'=>$match[1]??'','appointment_id'=>$appointment['appointment_id'],'status'=>'confirmed']);
check($status===302,'admin confirms appointment');
[$status,$body]=request($base.'/api.php?action=widget_appointment_status&'.http_build_query($lookup));
check($status===200 && json_decode($body,true)['appointment']['status']==='confirmed','confirmation reaches student lookup');
foreach(['admin-dashboard','admin-campaigns','admin-submissions','admin-ambassadors','admin-performance','admin-widget','admin-moderation','admin-rewards','super-admin','ambassador-program'] as $page) {
    [$status,$html]=request($base.'/index.php?page='.$page.'&qa_role=admin');
    check($status===200 && !preg_match('/(?:Fatal error|Warning:|Parse error|Deprecated:)/',$html),'admin route '.$page);
}
foreach(['student-dashboard','campaigns','my-submissions','my-performance','wallet','copilot','ambassador-program'] as $page) {
    [$status,$html]=request($base.'/index.php?page='.$page.'&qa_role=student');
    check($status===200 && !preg_match('/(?:Fatal error|Warning:|Parse error|Deprecated:)/',$html),'student route '.$page);
}
[$status,$html]=request($base.'/index.php?page=inbox&qa_role=ambassador&conversation=999999');
check($status===200 && !preg_match('/(?:Fatal error|Warning:|Parse error)/',$html),'inbox rejects stale selection without PHP errors');
[$status]=request($base.'/index.php?page=admin-rewards&qa_role=student');
check($status===403,'student cannot open rewards administration');
[$status]=request($base.'/index.php?page=widget',null,false);
check($status===302,'legacy page redirects to clean route');
[$status,$html]=request($base.'/admin/widget?qa_role=admin');
check($status===200 && str_contains($html,'<base href="/">'),'nested route sets asset base');
check(!preg_match('~href="index\.php~',$html),'navigation emits clean URLs');
check(str_contains($html,'/admin/widget?qa_role=admin#widgetAppointments'),'fragment stays on current page');
[$status]=request($base.'/api.php?action=messages&conversation_id='.$first['conversation_id']);
check($status===403,'admin cannot fetch private conversation through messages API');
[$status]=request($base.'/does-not-exist?page=login');
check($status===404,'unknown paths cannot be overridden by query page');
[$status]=request($base.'/admin/diem-thuong?page=login&qa_role=student');
check($status===403,'clean path retains authorization despite query override');
echo "$checks checks passed.\n";

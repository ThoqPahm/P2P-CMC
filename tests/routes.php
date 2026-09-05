<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require __DIR__.'/../app/Routes.php';
$checks=0;
function check(bool $ok, string $label): void { if (!$ok) { throw new RuntimeException($label); } $GLOBALS['checks']++; }
foreach (['/index.php','/portal/index.php'] as $script) {
    $_SERVER['SCRIPT_NAME']=$script;
    foreach (Routes::PAGES as $page=>$alias) {
        $url=Routes::url($page);
        check(Routes::resolve($url)===['page'=>$page], 'Roundtrip '.$page);
        check(Routes::legacy('index.php?page='.$page)===$url, 'Legacy '.$page);
    }
    $inbox=Routes::url('inbox',['conversation'=>12]);
    check(Routes::resolve($inbox)===['page'=>'inbox','conversation'=>'12'],'Inbox ID');
    check(Routes::resolve(Routes::url('admin-moderation',['conversation'=>8]))===['page'=>'admin-moderation','conversation'=>'8'],'Moderation ID');
    foreach (['members','knowledge','quality','reports'] as $tab) {
        check(Routes::resolve(Routes::url('ambassador-program',['tab'=>$tab]))===['page'=>'ambassador-program','tab'=>$tab], 'Program tab');
    }
    check(Routes::legacy('https://example.test/index.php?page=login')==='https://example.test/index.php?page=login','External untouched');
    check(Routes::resolve(Routes::base().'missing')===null,'Unknown 404');
    check(Routes::legacy('index.php?page=campaigns&status=active#list')===Routes::url('campaigns',['status'=>'active']).'#list','Query + fragment');
    check(Routes::html('<a href="index.php?page=inbox&amp;conversation=12">Chat</a>')==='<a href="'.$inbox.'">Chat</a>','HTML link');
    check(Routes::html('<form action="actions.php?action=login">')==='<form action="actions.php?action=login">','POST untouched');
    check(Routes::legacy('index.php?page[]=login')==='index.php?page[]=login','Invalid page type');
}
echo "$checks routing checks passed.\n";

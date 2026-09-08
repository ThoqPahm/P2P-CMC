<?php
declare(strict_types=1);
$root = dirname(__DIR__);
$header = file_get_contents($root.'/includes/header.php');
$css = file_get_contents($root.'/assets/css/admin-records.css');
if (!str_contains($header, "['admin-submissions', 'admin-ambassadors'], true)") || !str_contains($header, 'admin-records.css')) throw new RuntimeException('Styles must be restricted to the two requested pages.');
foreach (['.person-cell > div { display: grid; gap: 5px;', '.clean-table td > form', 'grid-template-columns: repeat(2, minmax(0, 1fr))', '@media (max-width: 700px)'] as $rule) {
    if (!str_contains($css, $rule)) throw new RuntimeException('Missing spacing guard: '.$rule);
}
foreach (['submissions','ambassadors'] as $page) {
    $html = file_get_contents($root.'/pages/admin/'.$page.'.php');
    if (!str_contains($html, 'table-responsive') || !str_contains($html, 'csrf_field()')) throw new RuntimeException('Keep responsive table and protected forms.');
}
echo "PASS scoped admin spacing, stacked identity, responsive review grid and existing forms.\n";

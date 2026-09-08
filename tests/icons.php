<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$root=dirname(__DIR__);
$mapping=json_decode(file_get_contents($root.'/tools/animateicons/mapping.json'),true,512,JSON_THROW_ON_ERROR);
$aliases=array_keys($mapping);
$css=file_get_contents($root.'/assets/icons/animateicons.css');
$missing=[];
foreach(['app','pages','includes','assets/js','assets/css'] as $dir) {
    foreach(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.'/'.$dir,FilesystemIterator::SKIP_DOTS)) as $file) {
        if(!in_array($file->getExtension(),['php','js','css'],true))continue;
        preg_match_all('/\bbi-([a-z0-9-]+)/',file_get_contents($file->getPathname()),$matches);
        foreach($matches[1] as $alias) {
            if(!in_array($alias,$aliases,true))$missing[]=$alias;
        }
    }
}
if($missing)throw new RuntimeException('Unmapped: '.implode(', ',array_unique($missing)));
foreach($aliases as $alias) {
    if(!str_contains($css,'.bi-'.$alias.' ' )&&!str_contains($css,'.bi-'.$alias.','))throw new RuntimeException('Missing selector '.$alias);
}
preg_match_all('/data:image\/svg\+xml,([^"\)]+)/',$css,$assets);
foreach($assets[1] as $asset) {
    $svg=rawurldecode($asset);
    $doc=new DOMDocument();
    if(!$doc->loadXML($svg,LIBXML_NONET)||$doc->documentElement->localName!=='svg')throw new RuntimeException('Invalid SVG');
    if(preg_match('/<script|<foreignObject|onload=|href=/i',$svg))throw new RuntimeException('Unsafe SVG');
}
foreach(['includes/header.php','pages/public/widget.php'] as $entry) {
    $html=file_get_contents($root.'/'.$entry);
    if(str_contains($html,'bootstrap-icons')||str_contains($html,'phosphor.css')||!str_contains($html,'assets/icons/animateicons.css'))throw new RuntimeException('Incorrect asset loading: '.$entry);
}
echo 'PASS: '.count($aliases).' aliases covered; '.count($assets[1])." valid local SVGs; both entry points use AnimateIcons.\n";

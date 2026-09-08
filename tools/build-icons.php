<?php
declare(strict_types=1);
// Asset-only build. No runtime dependency or npm lifecycle scripts are required.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$package = rtrim($argv[1] ?? '', '/');
$metadata = json_decode((string)@file_get_contents($package.'/package.json'), true);
if (($metadata['name']??'') !== '@phosphor-icons/core' || ($metadata['version']??'') !== '2.1.1') {
    throw new RuntimeException('Provide the extracted @phosphor-icons/core@2.1.1 package directory.');
}
$mapping = json_decode(file_get_contents(__DIR__.'/icon-map.json'), true, 512, JSON_THROW_ON_ERROR);
$css = "/* Generated from @phosphor-icons/core 2.1.1 (MIT). Do not hand-edit paths.\n * Rebuild: php tools/build-icons.php /path/to/extracted/package\n * Legacy bi-* classes are semantic adapters; no Bootstrap icon font is loaded. */\n";
$used = [];
foreach ($mapping as $name=>$aliases) {
    $selectors = [];
    foreach ($aliases as $alias) {
        if (isset($used[$alias])) { throw new RuntimeException('Duplicate icon alias: '.$alias); }
        $used[$alias] = true;
        $selectors[] = '.bi-'.$alias;
    }
    if ($name === 'list') { $selectors[] = '.navbar-toggler-icon'; }
    if ($name === 'x') { $selectors[] = '.btn-close'; }
    $css .= implode(",\n", $selectors)." {\n";
    foreach (['regular','duotone'] as $weight) {
        $path = $package.'/assets/'.$weight.'/'.$name.($weight==='regular'?'':'-'.$weight).'.svg';
        $svg = @file_get_contents($path);
        if (!$svg || !str_starts_with($svg,'<svg ') || preg_match('/<script|<foreignObject|onload=|href=/i',$svg)) {
            throw new RuntimeException('Missing or invalid trusted asset: '.$path);
        }
        $css .= '  --icon-'.$weight.': url("data:image/svg+xml,'.rawurlencode(trim($svg)).'");'."\n";
    }
    $css .= "}\n";
}
$dir = dirname(__DIR__).'/assets/icons';
if (!is_dir($dir)) { mkdir($dir,0755,true); }
file_put_contents($dir.'/phosphor.css',$css);
copy($package.'/LICENSE',$dir.'/LICENSE');
echo count($used).' semantic aliases, '.count($mapping).' shapes, '.strlen($css)." bytes, bundled locally.\n";

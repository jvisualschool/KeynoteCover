<?php
declare(strict_types=1);
require __DIR__.'/bootstrap.php';
if (PHP_SAPI!=='cli') exit;
umask(0077);
$cutoff=time()-604800;
foreach (glob(KC_DATA.'/jobs/*/credential.enc')?:[] as $f) if (is_file($f)&&!is_link($f)&&filemtime($f)<time()-1200) unlink($f);
foreach (glob(KC_DATA.'/jobs/*',GLOB_ONLYDIR)?:[] as $dir) {
    if (!preg_match('/^[a-f0-9]{32}$/',basename($dir))) continue;
    $state=$dir.'/state.json';
    if (filemtime(is_file($state)?$state:$dir)>$cutoff) continue;
    $lock=fopen($dir.'/worker.lock','c');
    if (!$lock || !flock($lock,LOCK_EX|LOCK_NB)) continue;
    // A failed archive must never make a completed cover disappear on cleanup.
    try {
        $s=is_file($state)?json_decode((string)file_get_contents($state),true):[];
        foreach($s['images']??[] as $n=>$image) if (!empty($image['gallery_public'])) kc_archive_cover($s,$n);
    } catch(Throwable) { flock($lock,LOCK_UN);fclose($lock);continue; }
    foreach (glob($dir.'/*')?:[] as $f) if (is_file($f)&&!is_link($f)) unlink($f);
    flock($lock,LOCK_UN);fclose($lock);@rmdir($dir);
}
foreach (glob(KC_DATA.'/sessions/sess_*')?:[] as $f) if (is_file($f)&&!is_link($f)&&filemtime($f)<time()-KC_KEY_TTL) unlink($f);

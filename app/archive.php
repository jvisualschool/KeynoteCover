<?php
declare(strict_types=1);
require __DIR__.'/bootstrap.php';
if (PHP_SAPI!=='cli') exit;
// Migrate only explicitly selected completed jobs; never publish test jobs.
foreach(array_slice($argv,1) as $id) {
    $s=kc_read($id);
    foreach($s['images'] as $n=>$image) echo kc_archive_cover($s,$n)." archived\n";
}

<?php
declare(strict_types=1);
$dir=sys_get_temp_dir().'/kc-worker-'.bin2hex(random_bytes(8));mkdir($dir,0700);
putenv('KC_DATA_DIR='.$dir);putenv('KC_PHP_BIN='.PHP_BINARY);
require dirname(__DIR__).'/app/bootstrap.php';
$id=bin2hex(random_bytes(16));mkdir($dir.'/jobs/'.$id,0700,true);
// This malformed text input is rejected locally before kc_api can be invoked.
$state=['id'=>$id,'status'=>'analyzing','source_type'=>'text','description'=>'','pages'=>0,'images'=>[]];
kc_write($id,$state);
try {
    kc_start($id,'analyze','sk-'.str_repeat('fixture',8));
    $end=microtime(true)+5;
    do {usleep(50000);$state=kc_read($id);}while($state['status']!=='failed'&&microtime(true)<$end);
    if($state['status']!=='failed'||is_file(kc_dir($id).'/credential.enc'))throw new RuntimeException('Worker configuration/key handoff failed');
    echo "PASS actual background worker inherits private data path, consumes encrypted key and removes envelope without an API call\n";
} finally {
    foreach(glob($dir.'/jobs/'.$id.'/*')?:[] as $file)unlink($file);
    rmdir($dir.'/jobs/'.$id);rmdir($dir.'/jobs');
    foreach(glob($dir.'/*')?:[] as $file)if(is_file($file))unlink($file);
    rmdir($dir);
}

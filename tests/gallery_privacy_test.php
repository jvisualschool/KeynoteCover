<?php
declare(strict_types=1);
$root=sys_get_temp_dir().'/kc-privacy-'.bin2hex(random_bytes(8));mkdir($root,0700);
putenv('KC_DATA_DIR='.$root);
require dirname(__DIR__).'/app/bootstrap.php';
$id=bin2hex(random_bytes(16));mkdir($root.'/jobs/'.$id,0700,true);
$im=imagecreatetruecolor(64,36);imagepng($im,kc_dir($id).'/fixture.png');imagedestroy($im);
$image=['file'=>'fixture.png','created_at'=>time(),'width'=>64,'height'=>36,'options'=>['space'=>'left','mood'=>'light','notes'=>''],'text_checked'=>true,'prompt'=>'Synthetic fixture'];
$state=['id'=>$id,'owner'=>'fixture-owner','filename'=>'fixture.pdf','pages'=>1,'created_at'=>time(),'status'=>'complete','gallery_enabled'=>true,'images'=>[$image+['gallery_public'=>false],$image+['gallery_public'=>true]],'analysis'=>['title'=>'Fixture','summary'=>'Fixture','keywords'=>['fixture'],'visual_direction'=>'Fixture']];
try {
    try{kc_archive_cover($state,0);throw new LogicException('Private image published');}
    catch(RuntimeException $e){if($e->getCode()!==403)throw $e;}
    kc_write($id,$state);touch(kc_dir($id).'/state.json',time()-604801);
    $cmd='KC_DATA_DIR='.escapeshellarg($root).' '.escapeshellarg(PHP_BINARY).' '.escapeshellarg(dirname(__DIR__).'/app/cleanup.php');
    exec($cmd,$output,$exit);
    if($exit!==0||is_dir(kc_dir($id))||is_dir(kc_cover_dir($id.'-0'))||!is_file(kc_cover_dir($id.'-1').'/record.json'))throw new RuntimeException('Cleanup changed per-image publication consent');
    if(isset(kc_cover($id.'-1')['owner']))throw new RuntimeException('Public record leaked owner');
    echo "PASS cleanup publishes only the explicitly opted-in image and never an earlier private variant\n";
} finally {
    $iterator=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST);
    foreach($iterator as $item){if($item->isDir())rmdir($item->getPathname());else unlink($item->getPathname());}
    rmdir($root);
}

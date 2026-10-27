<?php
declare(strict_types=1);
$dir=sys_get_temp_dir().'/kc-keys-'.bin2hex(random_bytes(8));
mkdir($dir,0700);putenv('KC_DATA_DIR='.$dir);
require dirname(__DIR__).'/app/bootstrap.php';
$checks=0;
function verify(bool $value,string $name): void {global $checks;if(!$value)throw new RuntimeException($name);$checks++;}
try {
    $key='sk-'.str_repeat('fixture',8);
    foreach([null, [], '', "sk-invalid\r\nInjected: value", str_repeat('x',300)] as $bad) {
        try {kc_validate_key($bad);throw new LogicException('Invalid key accepted');}
        catch(RuntimeException $e){verify($e->getCode()===400,'Reject malformed keys and header injection');}
    }
    $sealed=kc_seal_key($key,time()+60);
    verify(!str_contains($sealed,$key),'Encrypted envelope does not expose key');
    verify(kc_open_key($sealed)===$key,'Encrypted key round trip');
    verify((fileperms($dir.'/credential.key')&0777)===0600,'Vault file private');
    foreach([substr($sealed,0,-8).'AAAAAAAA',kc_seal_key($key,time()-1),'invalid'] as $bad) {
        try {kc_open_key($bad);throw new LogicException('Unsafe envelope accepted');}
        catch(RuntimeException $e){verify($e->getCode()===409,'Tampering and expiry fail closed');}
    }
    $_SESSION=['openai_key'=>$sealed,'key_expires'=>time()+60];
    verify(kc_key_ready()&&kc_user_key()===$key,'Current session can use own key');
    $_SESSION=[];verify(!kc_key_ready(),'Different session gets no key');
    $id=bin2hex(random_bytes(16));mkdir($dir.'/jobs/'.$id,0700,true);
    kc_lease_key($id,$key);
    $path=kc_dir($id).'/credential.enc';
    verify(!str_contains(file_get_contents($path),$key),'Worker envelope encrypted');
    verify((fileperms($path)&0777)===0600,'Worker envelope private');
    verify(kc_take_key($id)===$key&&!is_file($path),'Worker consumes and removes envelope');
    try {kc_take_key($id);throw new LogicException('Lease reused');}
    catch(RuntimeException $e){verify($e->getCode()===409,'Lease cannot be reused');}
    verify(!array_key_exists('owner',kc_public(['owner'=>'private','ip_hash'=>'private','id'=>$id])),'Public state hides owner');
    echo "All $checks credential security checks passed\n";
} finally {
    foreach(glob($dir.'/jobs/*/*')?:[] as $p)unlink($p);
    foreach(glob($dir.'/jobs/*',GLOB_ONLYDIR)?:[] as $p)rmdir($p);
    if(is_dir($dir.'/jobs'))rmdir($dir.'/jobs');
    foreach(glob($dir.'/*')?:[] as $p)if(is_file($p))unlink($p);
    rmdir($dir);
}

<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/bootstrap.php';
kc_session();
try {
    $id=(string)($_GET['id']??''); $s=kc_owned($id,$_SESSION['owner']); session_write_close();
    $kind=$_GET['kind']??'image';
    if ($kind==='preview') { $path=kc_dir($id).'/preview.jpg'; $mime='image/jpeg'; }
    elseif ($kind==='pdf') { $path=kc_dir($id).'/source.pdf'; $mime='application/pdf'; }
    elseif ($kind==='image') {
        $n=filter_var($_GET['n']??0,FILTER_VALIDATE_INT);
        if ($n===false || !isset($s['images'][$n])) throw new RuntimeException('이미지가 없습니다.',404);
        $path=kc_dir($id).'/'.$s['images'][$n]['file']; $mime='image/png';
    } else throw new RuntimeException('파일이 없습니다.',404);
    if (!is_file($path)) throw new RuntimeException('파일이 없습니다.',404);
    header('Content-Type: '.$mime); header('Content-Length: '.filesize($path));
    if ($kind==='pdf') header('Content-Security-Policy: sandbox');
    if (isset($_GET['download'])) header('Content-Disposition: attachment; filename="keycover-'.date('Ymd',$s['created_at']).'-'.((int)($_GET['n']??0)+1).'.png"');
    readfile($path);
} catch(Throwable $e) { kc_json(['error'=>'파일을 열 수 없습니다.'],in_array($e->getCode(),[400,403,404],true)?$e->getCode():500); }

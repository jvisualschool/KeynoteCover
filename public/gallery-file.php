<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/bootstrap.php';
kc_headers();
try {
    if (!in_array($_SERVER['REQUEST_METHOD']??'',['GET','HEAD'],true)) throw new RuntimeException('GET 요청이 필요합니다.',405);
    $r=kc_cover((string)($_GET['id']??''));
    $thumb=isset($_GET['thumb']); $path=kc_cover_dir($r['id']).($thumb?'/thumbnail.jpg':'/image.png');
    if (!is_file($path)) throw new RuntimeException('표지를 찾을 수 없습니다.',404);
    header('Content-Type: '.($thumb?'image/jpeg':'image/png'));
    header('Content-Length: '.filesize($path));
    if (isset($_GET['download'])&&!$thumb) header('Content-Disposition: attachment; filename="keycover-'.$r['id'].'.png"');
    if (($_SERVER['REQUEST_METHOD']??'')!=='HEAD') readfile($path);
} catch(Throwable $e) { kc_json(['error'=>'표지를 열 수 없습니다.'],in_array($e->getCode(),[400,404,405],true)?$e->getCode():500); }

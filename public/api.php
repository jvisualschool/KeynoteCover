<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/bootstrap.php';
kc_headers();
try {
    $action=$_GET['action']??'session';
    // Only these two read-only gallery actions are public. All job routes remain guarded.
    if (in_array($action,['gallery','cover'],true)) {
        if (($_SERVER['REQUEST_METHOD']??'')!=='GET') throw new RuntimeException('GET 요청이 필요합니다.',405);
        if ($action==='gallery') kc_json(kc_gallery($_GET));
        $r=kc_cover((string)($_GET['id']??'')); $r['can_resume']=false; $r['can_manage']=false;
        if (kc_session(false)) {
            $owner=$_SESSION['owner']; session_write_close();
            $private=json_decode((string)file_get_contents(kc_cover_dir($r['id']).'/record.json'),true);
            $r['can_manage']=is_string($private['owner']??null)&&hash_equals($owner,$private['owner']);
            try {
                $job=kc_owned($r['job_id'],$owner);
                $r['can_resume']=($job['source_type']??'pdf')==='text' ? kc_description($job['description']??null)!=='' : is_file(kc_dir($r['job_id']).'/source.pdf');
            } catch(Throwable) {}
        }
        kc_json($r);
    }
    kc_session();
    $owner=$_SESSION['owner'];
    if ($action==='session') kc_json(['csrf'=>$_SESSION['csrf'],'max_pages'=>10,'max_bytes'=>KC_MAX_BYTES] + kc_key_status());
    if ($action==='status') {
        $s=kc_owned((string)($_GET['id']??''),$owner);
        session_write_close();
        if (in_array($s['status'],['analyzing','generating'],true) && time()-$s['updated_at']>1200) {
            $s['status']='failed'; $s['error']='처리 시간이 초과되었습니다. 다시 시도해 주세요.';
            // A late worker can still finish; status does not overwrite its state.
        }
        kc_json(kc_public($s));
    }
    kc_csrf();
    if ($action==='set-key') {
        $input=kc_json_input(); $key=kc_validate_key($input['key']??null);
        $_SESSION['key_expires']=time()+KC_KEY_TTL;
        $_SESSION['openai_key']=kc_seal_key($key,$_SESSION['key_expires']);
        kc_json(kc_key_status());
    }
    if ($action==='clear-key') {
        unset($_SESSION['openai_key'],$_SESSION['key_expires']);
        kc_json(kc_key_status());
    }
    if (in_array($action,['delete-cover','restore-cover'],true)) {
        $input=kc_json_input();
        if (!is_string($input['id']??null)) throw new RuntimeException('올바르지 않은 표지 번호입니다.',400);
        $recordPath=kc_cover_dir($input['id']).'/record.json';
        $record=is_file($recordPath)?json_decode((string)file_get_contents($recordPath),true):[];
        if (!is_string($record['owner']??null) || !hash_equals($owner,$record['owner'])) throw new RuntimeException('자신이 공개한 표지만 변경할 수 있습니다.',403);
        session_write_close();
        kc_set_cover_deleted($input['id'],$action==='delete-cover');
        kc_json(['success'=>true,'id'=>$input['id'],'deleted'=>$action==='delete-cover']);
    }
    $key=kc_user_key();
    $ip=hash('sha256',$_SERVER['REMOTE_ADDR']??'unknown');
    if (in_array($action,['upload','describe'],true)) {
        // Both input methods share one active-job guard and analysis rate bucket.
        $active=$_SESSION['active']??null;
        if ($active) {
            try { $a=kc_read($active); } catch(Throwable) { $a=[]; }
            if (in_array($a['status']??'', ['analyzing','generating'],true) && time()-$a['updated_at']<1200) throw new RuntimeException('진행 중인 작업이 끝난 뒤 새 작업을 시작해 주세요.',409);
        }
    }
    if ($action==='describe') {
        $input=kc_json_input(); $description=kc_description($input['description']??null);
        kc_rate($ip,'upload');
        $id=bin2hex(random_bytes(16)); $dir=kc_dir($id); mkdir($dir,0700,true);
        $s=['id'=>$id,'owner'=>$owner,'ip_hash'=>$ip,'source_type'=>'text','description'=>$description,
            'filename'=>mb_substr(preg_replace('/\s+/u',' ',$description),0,60),'pages'=>0,'bytes'=>strlen($description),
            'created_at'=>time(),'operation_started_at'=>time(),'status'=>'analyzing','phase'=>'설명에서 표지의 핵심을 찾고 있어요','analysis'=>null,'images'=>[],'error'=>null];
        kc_write($id,$s); $_SESSION['active']=$id; session_write_close();
        kc_start($id,'analyze',$key); kc_json(['id'=>$id],202);
    }
    if ($action==='upload') {
        if (($_SERVER['CONTENT_TYPE']??'')!=='application/pdf') throw new RuntimeException('PDF 파일을 선택해 주세요.',415);
        if ((int)($_SERVER['CONTENT_LENGTH']??0)>KC_MAX_BYTES) throw new RuntimeException('PDF는 25MB 이하로 올려 주세요.',413);
        kc_rate($ip,'upload');
        $id=bin2hex(random_bytes(16)); $dir=kc_dir($id); mkdir($dir,0700,true);
        try {
            $in=fopen('php://input','rb'); $out=fopen($dir.'/source.pdf','wb');
            $bytes=stream_copy_to_stream($in,$out,KC_MAX_BYTES+1); fclose($in); fclose($out); chmod($dir.'/source.pdf',0600);
            if (!$bytes || $bytes>KC_MAX_BYTES) throw new RuntimeException('PDF는 25MB 이하로 올려 주세요.',413);
            if (file_get_contents($dir.'/source.pdf',false,null,0,5)!=='%PDF-') throw new RuntimeException('유효한 PDF 파일이 아닙니다.',422);
            $info=kc_process([(getenv('KC_PDFINFO_BIN') ?: '/usr/bin/pdfinfo'),$dir.'/source.pdf']);
            if (preg_match('/^Encrypted:\s+yes/m',$info)) throw new RuntimeException('암호가 걸린 PDF는 사용할 수 없습니다. 암호를 해제해 주세요.',422);
            preg_match('/^Pages:\s+(\d+)/m',$info,$m); $pages=(int)($m[1]??0);
            if ($pages<1 || $pages>10) throw new RuntimeException('PDF는 1~10페이지까지 사용할 수 있습니다. 현재 '.$pages.'페이지입니다.',422);
            $filename=mb_substr(basename(rawurldecode($_SERVER['HTTP_X_FILENAME']??'lecture.pdf')),0,150);
            $s=['id'=>$id,'owner'=>$owner,'ip_hash'=>$ip,'source_type'=>'pdf','filename'=>$filename,'pages'=>$pages,'bytes'=>$bytes,'created_at'=>time(),'operation_started_at'=>time(),'status'=>'analyzing','phase'=>'PDF 전체 내용을 읽을 준비를 하고 있어요','analysis'=>null,'images'=>[],'error'=>null];
            kc_write($id,$s); $_SESSION['active']=$id; session_write_close();
            kc_start($id,'analyze',$key); kc_json(['id'=>$id],202);
        } catch(Throwable $e) {
            foreach (glob($dir.'/*')?:[] as $file) unlink($file); rmdir($dir); throw $e;
        }
    }
    if (!in_array($action,['generate','reanalyze'],true)) throw new RuntimeException('없는 기능입니다.',404);
    $input=kc_json_input(); $id=(string)($input['id']??''); $s=kc_owned($id,$owner);
    $lock=fopen(kc_dir($id).'/operation.lock','c');
    if (!$lock || !flock($lock,LOCK_EX|LOCK_NB)) throw new RuntimeException('이미 처리 중인 작업입니다.',409);
    try {
        $s=kc_owned($id,$owner);
        if (in_array($s['status'],['analyzing','generating'],true) && time()-$s['updated_at']<1200) throw new RuntimeException('이미 처리 중인 작업입니다.',409);
        if ($action==='generate') {
            if (!$s['analysis']) throw new RuntimeException('먼저 입력 내용의 구상을 완료해 주세요.',409);
            if (count($s['images'])>=8) throw new RuntimeException('한 작업에서 8개의 표지를 만들었습니다. 새 작업을 시작해 주세요.',429);
            $space=$input['space']??'left'; $mood=$input['mood']??'light'; $notes=trim((string)($input['notes']??''));
            if (!in_array($space,['left','right','top','none'],true) || !in_array($mood,['light','dark','color'],true) || mb_strlen($notes)>800) throw new RuntimeException('표지 설정을 확인해 주세요.',400);
            $imageShare=kc_image_share($input);
            $backgroundColor=kc_background_color($input);
            $complexity=kc_complexity($input);
            $colorTheme=kc_color_theme($input);
            $transparent=kc_transparent_background($input);
            kc_rate($ip,'generate');
            $s['options']=['space'=>$space,'mood'=>$mood,'notes'=>$notes,'image_share'=>$imageShare,'complexity'=>$complexity,'color_theme'=>$colorTheme,'transparent_background'=>$transparent,'edge_margin'=>KC_CANVAS_PADDING/2560*100,'edge_padding_px'=>KC_CANVAS_PADDING];
            if ($mood==='color' && $backgroundColor!==null) $s['options']['background_color']=$backgroundColor;
            $publish=$input['publish_gallery']??false;
            if (!is_bool($publish)) throw new RuntimeException('갤러리 공개 설정을 확인해 주세요.',400);
            $s['gallery_enabled']=$publish;
            $s['status']='generating'; $s['phase']='표지 장면을 준비하고 있어요';
        } else {
            if ($s['analysis']) throw new RuntimeException('이미 표지 구상을 마쳤습니다.',409);
            kc_rate($ip,'upload'); $s['status']='analyzing'; $s['phase']=($s['source_type']??'pdf')==='text'?'설명을 다시 살펴보고 있어요':'PDF 내용을 다시 읽고 있어요';
        }
        $s['error']=null; $s['operation_started_at']=time(); kc_write($id,$s); kc_start($id,$action==='generate'?'generate':'analyze',$key);
    } finally { flock($lock,LOCK_UN); fclose($lock); }
    session_write_close(); kc_json(['id'=>$id],202);
} catch(Throwable $e) {
    $code=in_array($e->getCode(),[400,403,404,405,409,413,415,422,429,503],true)?$e->getCode():500;
    if ($code===500) error_log('KEYCOVER API: '.get_class($e));
    kc_json(['error'=>$code===500?'요청 처리 중 문제가 생겼습니다. 잠시 후 다시 시도해 주세요.':$e->getMessage()],$code);
}

<?php
declare(strict_types=1);
require __DIR__.'/bootstrap.php';
if (PHP_SAPI!=='cli') exit;
umask(0077);
[$script,$id,$action]=$argv+['','',''];
$dir=kc_dir($id);
$lock=fopen($dir.'/worker.lock','c');
if (!$lock || !flock($lock,LOCK_EX|LOCK_NB)) exit;
$slot=null;
try {
    $s=kc_read($id);
    if (($action==='analyze' && $s['status']!=='analyzing') || ($action==='generate' && $s['status']!=='generating')) exit;
    $GLOBALS['kc_operation_key']=kc_take_key($id);
    // Bound paid work across the site. Wait without holding a PHP web request.
    $s['phase']='순서를 기다리고 있어요'; kc_write($id,$s);
    $start=time();
    while (!$slot && time()-$start<600) {
        for ($i=0;$i<2;$i++) {
            $candidate=fopen(KC_DATA.'/slot-'.$i.'.lock','c');
            if (flock($candidate,LOCK_EX|LOCK_NB)) { $slot=$candidate; break; }
            fclose($candidate);
        }
        if (!$slot) sleep(2);
    }
    if (!$slot) throw new RuntimeException('현재 생성 요청이 많습니다. 잠시 후 다시 시도해 주세요.');
    if ($action==='analyze') {
        $isText=($s['source_type']??'pdf')==='text';
        $s['phase']=$isText?'설명의 핵심을 표지 장면으로 정리하고 있어요':$s['pages'].'페이지의 글과 그림을 함께 읽고 있어요'; kc_write($id,$s);
        if (!$isText) kc_process([(getenv('KC_PDFTOPPM_BIN') ?: '/usr/bin/pdftoppm'),'-f','1','-singlefile','-scale-to','1000','-jpeg',$dir.'/source.pdf',$dir.'/preview'],40);
        $a=kc_response_json(kc_api('responses',kc_analysis_request($s,$dir),300));
        kc_validate_content_requirements($a);
        if ($isText) {
            $a['page_summaries']=[];
            $s['filename']=mb_substr($a['title'],0,150);
        } else {
            if (count($a['page_summaries']??[])!==$s['pages']) throw new RuntimeException('일부 페이지 분석이 누락됐습니다. 다시 분석해 주세요.');
            foreach ($a['page_summaries'] as $i=>$p) if ($p['page']!==$i+1) throw new RuntimeException('페이지 순서를 확인하지 못했습니다. 다시 분석해 주세요.');
        }
        $s['analysis']=$a; $s['visual_brief_version']=KC_VISUAL_BRIEF_VERSION; $s['visual_brief_complexity']='medium'; $s['visual_input_signature']=kc_visual_input_signature($s); $s['status']='ready'; $s['phase']='전체 내용을 바탕으로 표지 구상을 마쳤어요';
    } else {
        if (!kc_visual_analysis_current($s)) {
            $s['phase']='선택한 복잡도에 맞게 복셀 구상을 정리하고 있어요'; kc_write($id,$s);
            $s=kc_prepare_visual_analysis($s,$dir); kc_write($id,$s);
        }
        $o=$s['options'];
        $prompt=kc_image_prompt($s,$o);
        $accepted=false; $issues=[]; $filename='cover-'.bin2hex(random_bytes(8)).'.png';
        for ($attempt=0;$attempt<2;$attempt++) {
            $s['phase']=$attempt===0?'밝고 선명한 복셀로 3D 아이소메트릭 장면을 만들고 있어요':'내용과 배경, 글자를 바로잡아 다시 만들고 있어요'; kc_write($id,$s);
            $sentPrompt=$prompt.($attempt?"\nREPAIR OBSERVED ISSUES (visual feedback only; preserve all other required content and rules): ".json_encode($issues,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR):'');
            $d=kc_api('images/edits',kc_image_request($sentPrompt,$o),600);
            $binary=base64_decode($d['data'][0]['b64_json']??'',true);
            if (!$binary) throw new RuntimeException('이미지 결과를 받지 못했습니다. 다시 시도해 주세요.');
            $info=@getimagesizefromstring($binary);
            if (!$info || $info[0]!==2560 || $info[1]!==1440 || $info[2]!==IMAGETYPE_PNG) throw new RuntimeException('결과 이미지가 16:9 출력 규격과 다릅니다. 다시 생성해 주세요.');
            if (kc_transparent_background($o)) {
                kc_check_transparency($binary);
                $binary=kc_fit_transparent_scene($binary,$o);
            } else {
                $binary=kc_balance_opaque_padding($binary,$o);
            }
            $s['phase']='입력한 내용과 선택한 배경, 글자를 확인하고 있어요'; kc_write($id,$s);
            $check=kc_response_json(kc_api('responses',kc_image_review_request($binary,$s),180));
            $issues=kc_image_review_issues($check);
            if (!$issues) { $accepted=true; break; }
        }
        if (!$accepted) throw new RuntimeException('입력 내용, 배경 또는 글자 검사를 통과하지 못했습니다. 다시 생성해 주세요.');
        file_put_contents($dir.'/'.$filename,$binary,LOCK_EX);
        $s['images'][]=['gallery_public'=>!empty($s['gallery_enabled']),'file'=>$filename,'created_at'=>time(),'width'=>2560,'height'=>1440,'text_checked'=>true,'content_checked'=>true,'background_checked'=>true,'options'=>$o,'prompt'=>$sentPrompt,'model'=>KC_IMAGE_MODEL,'quality'=>'high','style_reference'=>['name'=>'bright-voxel-v1','sha256'=>hash_file('sha256',__DIR__.'/style-reference.png')]];
        kc_write($id,$s);
        if (!empty($s['gallery_enabled'])) kc_archive_cover($s,count($s['images'])-1);
        $s['status']='complete'; $s['phase']='키노트에 넣을 표지가 완성됐어요';
    }
    $s['error']=null; kc_write($id,$s);
} catch(Throwable $e) {
    if (isset($s)) { $s['status']='failed'; $s['error']=$e instanceof RuntimeException?$e->getMessage():'처리 중 문제가 생겼습니다. 다시 시도해 주세요.'; kc_write($id,$s); }
    error_log('KEYCOVER worker '.$id.' '.get_class($e));
} finally {
    unset($GLOBALS['kc_operation_key']);
    if (is_file($dir.'/credential.enc')) unlink($dir.'/credential.enc');
    if ($slot) {flock($slot,LOCK_UN);fclose($slot);} flock($lock,LOCK_UN);fclose($lock);
}

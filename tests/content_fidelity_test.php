<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/cover.php';
const KC_TEXT_MODEL='test-model';
$calls=[];$valid=true;$count=0;
$requirements=[
 ['source_detail'=>'사운드 입력','visual_depiction'=>'A distinct audio waveform asset card.'],
 ['source_detail'=>'배경음악 입력','visual_depiction'=>'A separate music asset card beside a keyboard.'],
 ['source_detail'=>'이미지 입력','visual_depiction'=>'A stack of still-image thumbnail cards.'],
 ['source_detail'=>'클립영상 입력','visual_depiction'=>'A separate strip of successive moving-image frames.'],
 ['source_detail'=>'재료들이 합쳐져 완성 영상이 됨','visual_depiction'=>'Four distinct input paths converge through an assembly surface into a finished video display.']
];
function kc_api(string $endpoint,array $payload,int $timeout): array {global $calls,$requirements,$valid;$calls[]=$payload;return ['palette'=>['ivory','electric blue','cyan','yellow'],'visual_direction'=>'사운드, 배경음악, 이미지, 클립영상을 따로 놓고 하나의 완성 영상으로 연결합니다.','scene_en'=>'All inputs visibly converge into one finished video.','content_requirements'=>$valid?$requirements:[]];}
function kc_response_json(array $r): array {return $r;}
function fidelity(bool $ok,string $label): void {global $count;if(!$ok)throw new RuntimeException($label);$count++;}
$original='AI로 영상 만들기 클래스. 사운드+배경음악+이미지+클립영상이 모여 멋진 동영상이 만들어진다.';
$state=['source_type'=>'text','description'=>$original,'analysis'=>['title'=>'영상','summary'=>'SHORT LOSSY SUMMARY','keywords'=>['영상'],'palette'=>['teal'],'scene_en'=>'OLD_SIMPLIFIED_SCENE','visual_direction'=>'OLD_SIMPLIFIED_SCENE'],'images'=>[['prompt'=>'exact previous prompt']],'options'=>['space'=>'right','mood'=>'color','background_color'=>'#34579D','image_share'=>50,'edge_margin'=>6,'complexity'=>'low','notes'=>'사운드 파일, 이미지 파일 추가']];
$before=$state;$state=kc_prepare_visual_analysis($state);
$input=json_decode($calls[0]['input'],true);
fidelity($input['original_description']===$original,'Rewrite receives complete original input, not just summary');
fidelity($input['additional_user_requests']===$state['options']['notes'],'Additional content requests reach planning');
fidelity(!str_contains($calls[0]['input'],'OLD_SIMPLIFIED_SCENE'),'Old simplified art does not seed replacement');
fidelity($state['images']===$before['images']&&$state['analysis']['summary']===$before['analysis']['summary'],'Existing images and facts retained');
fidelity(kc_prepare_visual_analysis($state)===$state&&count($calls)===1,'No rewrite while source and notes unchanged');
$changed=$state;$changed['options']['notes'].=' 세 장의 사진';fidelity(!kc_visual_analysis_current($changed),'Changed notes invalidate brief');kc_prepare_visual_analysis($changed);fidelity(count($calls)===2,'Changed notes rebuild plan');
$changed=$state;$changed['description'].=' 두 명의 학습자';fidelity(!kc_visual_analysis_current($changed),'Changed source invalidates brief');
$changed=$state;$changed['visual_brief_version']=3;fidelity(!kc_visual_analysis_current($changed),'Version 3 count-limited brief is stale');
foreach(['low','medium','high'] as $complexity){
 $o=array_replace($state['options'],['complexity'=>$complexity]);$prompt=kc_image_prompt($state,$o);$rules=kc_visual_rules($complexity);
 foreach($requirements as $item)fidelity(str_contains($prompt,$item['source_detail'])&&str_contains($prompt,$item['visual_depiction']),'All inputs and assembly reach every complexity prompt');
 foreach(['at most three','TWO supporting','THREE to FIVE','SIX to TEN','Choose ONE main idea','at most 240','100-220'] as $forbidden)fidelity(!str_contains($rules,$forbidden),'No lossy quota or word cap');
 fidelity(str_contains($prompt,'Preserve EVERY requested content element and relationship')&&str_contains($prompt,'No content may be omitted'),'Details never override content');
 fidelity(!str_contains($prompt,$original),'Private verbatim source is not published in gallery prompt');
}
$review=kc_image_review_request('fake-png-bytes',$state);$reference=json_decode($review['input'][0]['content'][0]['text'],true);
fidelity($reference['original_description']===$original&&$reference['content_requirements']===$requirements,'Private image review compares original source and checklist');
fidelity(kc_image_review_issues(['visible_text'=>false,'missing_content'=>[],'background_issues'=>[]])===[],'Complete text-free image accepted');
fidelity(kc_image_review_issues(['visible_text'=>false,'missing_content'=>['Background music is absent'],'background_issues'=>[]])===['Background music is absent'],'Content omissions block completion');
fidelity(count(kc_image_review_issues(['visible_text'=>true,'missing_content'=>['No convergence shown'],'background_issues'=>[]]))===2,'Text and relation failures both reach retry');
foreach([[],['visible_text'=>false],['visible_text'=>false,'missing_content'=>['']],['visible_text'=>'false','missing_content'=>[]]] as $bad){try{kc_image_review_issues($bad);throw new LogicException('Invalid review accepted');}catch(RuntimeException $e){fidelity(true,'Malformed review cannot pass');}}
$valid=false;try{kc_prepare_visual_analysis($before);throw new LogicException('Missing checklist accepted');}catch(RuntimeException $e){fidelity($before['analysis']['scene_en']==='OLD_SIMPLIFIED_SCENE','Missing checklist leaves caller unchanged');}$valid=true;
$dir=sys_get_temp_dir().'/kc-fidelity-'.bin2hex(random_bytes(6));mkdir($dir);copy(__DIR__.'/fixtures/lecture-3.pdf',$dir.'/source.pdf');
try{
 $pdf=$before;unset($pdf['description']);$pdf['source_type']='pdf';$pdf['pages']=3;
 $request=kc_analysis_request($pdf,$dir);fidelity(in_array('content_requirements',$request['text']['format']['schema']['required'],true),'New PDF analysis requires coverage checklist');
 kc_prepare_visual_analysis($pdf,$dir);$last=$calls[array_key_last($calls)];
 fidelity($last['input'][0]['content'][0]['file_data']==='data:application/pdf;base64,'.base64_encode(file_get_contents($dir.'/source.pdf')),'PDF rewrite rereads original document');
}finally{unlink($dir.'/source.pdf');rmdir($dir);}
echo "All $count content fidelity checks passed\n";

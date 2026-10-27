<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/cover.php';
const KC_TEXT_MODEL='test-model';
$calls=[];$count=0;
function kc_api(string $endpoint,array $payload,int $timeout): array {
    global $calls;$calls[]=$payload;
    return ['palette'=>['ivory','electric blue','cyan','yellow'],'visual_direction'=>'컴퓨터를 서버에 연결합니다.','scene_en'=>'An ivory computer connects to a navy server.',
        'content_requirements'=>[['source_detail'=>'서버 구축','visual_depiction'=>'An ivory computer connects to a navy server.']]];
}
function kc_response_json(array $r): array {return $r;}
function backgroundCheck(bool $ok,string $label): void {global $count;if(!$ok)throw new RuntimeException($label);$count++;}
$state=['source_type'=>'text','description'=>'컴퓨터로 서버 구축','analysis'=>['title'=>'서버 구축','summary'=>'컴퓨터로 서버를 구축합니다.','keywords'=>['서버'],'palette'=>['warm ivory','navy'],
    'scene_en'=>'Create a low-complexity voxel scene on a warm ivory background.','visual_direction'=>'아이보리 배경 위에 서버를 만듭니다.',
    'content_requirements'=>[['source_detail'=>'서버 구축','visual_depiction'=>'A computer connects to a server.']]],
    'options'=>['space'=>'right','mood'=>'dark','image_share'=>50,'edge_margin'=>6,'complexity'=>'low','transparent_background'=>false,'notes'=>'An ivory backdrop'],
    'images'=>[['options'=>['mood'=>'dark'],'prompt'=>'exact original ivory prompt']],'visual_brief_version'=>4,'visual_brief_complexity'=>'low'];
$state['visual_input_signature']=kc_visual_input_signature($state);
backgroundCheck(!kc_visual_analysis_current($state),'Previous version with ivory backdrop must be rebuilt');
$updated=kc_prepare_visual_analysis($state);
backgroundCheck($updated['images']===$state['images'],'Historical prompts/images stay intact');
backgroundCheck(!str_contains($calls[0]['input'],'ivory background'),'Old conflicting art brief excluded from replanning');
backgroundCheck(str_contains($calls[0]['instructions'],'BACKGROUND IS CONTROLLED SEPARATELY'),'Replanning keeps scene background-neutral');
backgroundCheck(str_contains(kc_analysis_request($state,'/unused')['instructions'],'BACKGROUND IS CONTROLLED SEPARATELY'),'Initial analysis also keeps scene background-neutral');
backgroundCheck(str_contains($updated['analysis']['scene_en'],'ivory computer'),'Physical subject colors are preserved');
foreach(['dark','light','color'] as $mood)foreach([false,true] as $transparent)foreach(['low','medium','high'] as $complexity){
    $o=array_replace($state['options'],['mood'=>$mood,'transparent_background'=>$transparent,'complexity'=>$complexity,'background_color'=>'#12ABCD']);
    $s=array_replace($state,['options'=>$o]);$prompt=kc_image_prompt($s,$o);$rule=kc_background_prompt($o);
    backgroundCheck(str_contains($rule,'overrides ALL conflicting'),'All selected backgrounds have explicit precedence');
    backgroundCheck(strpos($prompt,'AUTHORITATIVE USER BACKGROUND SETTING:')>strpos($prompt,'ADDITIONAL USER REQUESTS:'),'Selected background has final priority after source and notes');
    backgroundCheck(str_contains($prompt,'subject materials only, never background'),'Palette cannot assign backdrop');
    $request=kc_image_review_request('fixture',$s);$ref=json_decode($request['input'][0]['content'][0]['text'],true);
    backgroundCheck($ref['selected_background']===$rule,'Review receives the exact selected background rule');
    backgroundCheck(in_array('background_issues',$request['text']['format']['schema']['required'],true),'Background check cannot be omitted from review');
    if($transparent){
        backgroundCheck(str_contains($rule,'true RGBA PNG')&&!str_contains($rule,'#12ABCD')&&!str_contains($rule,'#121B35'),'Transparency wins over preview color');
        backgroundCheck(str_contains($request['instructions'],'viewer default white/black transparency display'),'Review does not mistake transparent display for actual background');
    }else{
        $expected=['dark'=>'#121B35','light'=>'#F6F5F1','color'=>'#12ABCD'][$mood];
        backgroundCheck(str_contains($rule,$expected),'Each opaque mood uses its selected color');
    }
}
$accepted=['visible_text'=>false,'missing_content'=>[],'background_issues'=>[]];
backgroundCheck(kc_image_review_issues($accepted)===[],'All checks must pass for completion');
$wrong=$accepted;$wrong['background_issues']=['Replace the ivory background with the selected dark navy background.'];
backgroundCheck(kc_image_review_issues($wrong)===$wrong['background_issues'],'Wrong background alone triggers repair');
$wrong['visible_text']=true;$wrong['missing_content']=['Missing sound file'];
backgroundCheck(count(kc_image_review_issues($wrong))===3,'Background, writing and content feedback all survive');
foreach([null,false,'', [''],[1],['bad'=>'wrong']] as $bad){
    $review=$accepted;$review['background_issues']=$bad;
    try{kc_image_review_issues($review);throw new LogicException('Malformed background result accepted');}
    catch(RuntimeException $e){backgroundCheck(true,'Malformed background check fails closed');}
}
$missing=$accepted;unset($missing['background_issues']);
try{kc_image_review_issues($missing);throw new LogicException('Absent background review accepted');}catch(RuntimeException $e){backgroundCheck(true,'Absent background review fails closed');}
echo "All $count background selection checks passed\n";

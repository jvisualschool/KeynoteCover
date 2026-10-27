<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/cover.php';
const KC_TEXT_MODEL='test-text';const KC_IMAGE_MODEL='test-image';
$calls=[];$count=0;
function kc_api(string $endpoint,array $payload,int $timeout): array {
    global $calls;$calls[]=$payload;
    return ['palette'=>['ivory','electric blue','cyan','yellow'],'visual_direction'=>'밝은 컴퓨터와 빨간 서버를 연결합니다.','scene_en'=>'A luminous ivory computer connects to the explicitly requested red server.',
        'content_requirements'=>[['source_detail'=>'빨간 서버 연결','visual_depiction'=>'An ivory computer connected to a red server.']]];
}
function kc_response_json(array $r):array{return $r;}
function styleCheck(bool $ok,string $message):void{global $count;if(!$ok)throw new RuntimeException($message);$count++;}
$state=['source_type'=>'text','description'=>'빨간 서버에 연결하는 컴퓨터','analysis'=>['title'=>'서버','summary'=>'서버 연결','keywords'=>['서버'],'palette'=>['OLD_DUSTY_PALETTE'],'scene_en'=>'OLD_DARK_SCENE','content_requirements'=>[['source_detail'=>'navy','visual_depiction'=>'OLD_NAVY_REQUIREMENT']]],'options'=>['notes'=>'서버는 빨강','mood'=>'dark','space'=>'right','complexity'=>'low','image_share'=>50,'edge_margin'=>7.8125,'transparent_background'=>false],'visual_brief_version'=>5,'visual_brief_complexity'=>'low','images'=>[['prompt'=>'old exact prompt']]];
$state['visual_input_signature']=kc_visual_input_signature($state);
styleCheck(!kc_visual_analysis_current($state),'Muted version 5 is stale');
$updated=kc_prepare_visual_analysis($state);$input=json_decode($calls[0]['input'],true);
styleCheck(!str_contains($calls[0]['input'],'OLD_'),'Inferred palette and old art do not seed the new scene');
styleCheck($input['original_description']===$state['description']&&$input['additional_user_requests']===$state['options']['notes'],'Explicit requested colors remain in original input');
styleCheck($updated['analysis']['palette']===['ivory','electric blue','cyan','yellow'],'Fresh palette replaces inferred old palette');
styleCheck($updated['images']===$state['images']&&$updated['options']===$state['options'],'Saved images and user settings are preserved');
styleCheck(kc_prepare_visual_analysis($updated)===$updated&&count($calls)===1,'No repeated planning call for unchanged style');
foreach(['light','dark','color'] as $mood)foreach(['low','medium','high'] as $complexity)foreach([false,true] as $transparent){
    $o=array_replace($updated['options'],['mood'=>$mood,'complexity'=>$complexity,'transparent_background'=>$transparent,'background_color'=>'#AA3366']);
    $prompt=kc_image_prompt($updated,$o);$request=kc_image_request($prompt,$o);
    styleCheck(str_contains($prompt,kc_voxel_style())&&str_contains($prompt,'STYLE REFERENCE ONLY'),'Each mode uses the same style reference role');
    styleCheck(!str_contains($prompt,'high surface roughness')&&!str_contains($prompt,'deep, rich matte materials'),'No obsolete dull material instructions');
    styleCheck(str_contains($prompt,'red server')&&str_contains($prompt,'Preserve colors explicitly required'),'Content and explicit colors survive style application');
    styleCheck(str_contains($prompt,'at least 200 pixels')&&str_contains($prompt,KC_ISOMETRIC_CAMERA_RULE),'Padding and orthographic projection preserved');
    styleCheck($request['image'] instanceof CURLFile&&hash_file('sha256',$request['image']->getFilename())===hash_file('sha256',dirname(__DIR__).'/app/style-reference.png'),'Actual reference file is attached');
    styleCheck($request['background']===($transparent?'transparent':'opaque')&&$request['output_format']==='png'&&$request['size']==='2560x1440','Reference workflow keeps PNG, transparency and dimensions');
    if($mood==='dark')styleCheck(str_contains($prompt,'Do not darken or desaturate the subject'),'Dark backdrop cannot dim the subject');
}
echo "All $count bright style checks passed\n";

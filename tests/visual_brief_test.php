<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/cover.php';
const KC_TEXT_MODEL='test-text-model';
$calls=[];
$reply=['content_requirements'=>[['source_detail'=>'서버 배포','visual_depiction'=>'A server and connected laptop.']],'palette'=>['ivory','electric blue','cyan','yellow'],'visual_direction'=>'작은 무광 복셀 서버 옆에 화면이 비어 있는 노트북 하나를 놓습니다. 정돈된 받침과 부드러운 빛으로 간결하게 표현합니다.','scene_en'=>'A matte voxel server beside one laptop with a blank screen, viewed with an orthographic isometric camera.'];
function kc_api(string $endpoint,array $payload,int $timeout): array {
    global $calls,$reply;$calls[]=[$endpoint,$payload];return $reply;
}
function kc_response_json(array $response): array {return $response;}
function checkBrief(bool $ok,string $why): void {if(!$ok)throw new RuntimeException($why);}
$state=['id'=>'test','status'=>'generating','analysis'=>['title'=>'클라우드 입문','summary'=>'서버에 웹 앱을 배포하는 강의','keywords'=>['서버','배포'],'palette'=>['navy','cream'],
    'visual_direction'=>'OLD_REJECTED_KOREAN','scene_en'=>'OLD_REJECTED_ENGLISH','page_summaries'=>[['page'=>1,'topic'=>'웹 앱 배포','visual_motif'=>'OLD_REJECTED_MOTIF']]],
    'images'=>[['prompt'=>'exact historical prompt','file'=>'previous.png']],'options'=>['space'=>'left','mood'=>'dark','notes'=>'keep notes']];
$before=$state;$new=kc_prepare_visual_analysis($state);
checkBrief(count($calls)===1&&$calls[0][0]==='responses','Only one text request is allowed');
$input=json_decode($calls[0][1]['input'],true);
checkBrief(!str_contains($calls[0][1]['input'],'OLD_REJECTED'),'Old rejected concepts leaked into the rewrite');
checkBrief($input['page_topics']===[['page'=>1,'topic'=>'웹 앱 배포']],'Full lecture topics lost');
checkBrief($new['analysis']['title']===$before['analysis']['title']&&$new['analysis']['page_summaries']===$before['analysis']['page_summaries'],'Analysis facts changed');
checkBrief($new['images']===$before['images']&&$new['options']===$before['options']&&$state===$before,'Historical images, settings or caller state changed');
checkBrief($new['visual_brief_version']===KC_VISUAL_BRIEF_VERSION&&$new['analysis']['scene_en']===$reply['scene_en'],'New scene not applied');
checkBrief(kc_prepare_visual_analysis($new)===$new&&count($calls)===1,'Current scene caused another paid call');
foreach(['left','right','top','none'] as $space)foreach(['light','dark','color'] as $mood){
    $prompt=kc_image_prompt($new,['space'=>$space,'mood'=>$mood,'notes'=>'']);
    checkBrief(str_contains($prompt,$reply['scene_en'])&&!str_contains($prompt,'OLD_REJECTED'),'Image prompt reused rejected scene');
    checkBrief(!str_contains($prompt,'cinematic rim lighting')&&!str_contains($prompt,'polished studio lighting'),'Lighting conflicts with matte surface');
}
$reply=['visual_direction'=>'','scene_en'=>''];
try {kc_prepare_visual_analysis($state);throw new LogicException('Empty rewrite accepted');}
catch(RuntimeException $e){checkBrief($state===$before,'Failed rewrite mutated original state');}
echo "PASS: stale scene refreshed once, no image API, rejected concepts excluded, PDF facts/history/settings preserved, failed rewrite safe, 12 composition/light combinations\n";

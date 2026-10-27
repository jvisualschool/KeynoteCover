<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/cover.php';
const KC_TEXT_MODEL='test';const KC_IMAGE_MODEL='test';
$calls=[];$checks=0;
function checkTheme(bool $ok,string $message):void{global $checks;if(!$ok)throw new RuntimeException($message);$checks++;}
function kc_api(string $endpoint,array $payload,int $timeout):array{global $calls;$calls[]=$payload;return ['palette'=>['theme palette'],'visual_direction'=>'선택한 색의 서버','scene_en'=>'A server with the explicitly required red cable.','content_requirements'=>[['source_detail'=>'빨간 케이블','visual_depiction'=>'red cable']]];}
function kc_response_json(array $r):array{return $r;}
checkTheme(count(KC_COLOR_THEMES)===6,'Exactly six themes');
checkTheme(kc_color_theme([])==='blue','Missing historical theme defaults to blue');
foreach([null,0,true,[],"BLUE","unknown","blue\n"] as $bad){try{kc_color_theme(['color_theme'=>$bad]);throw new LogicException('Invalid theme accepted');}catch(RuntimeException $e){checkTheme($e->getCode()===400,'Invalid theme rejected with 400');}}
$html=file_get_contents(dirname(__DIR__).'/public/index.html');
$css=file_get_contents(dirname(__DIR__).'/public/assets/style.css');
$dom=new DOMDocument();@$dom->loadHTML($html);$xp=new DOMXPath($dom);
checkTheme($xp->query('//input[@name="color_theme"]')->length===6,'UI has six choices');
checkTheme($xp->query('//input[@name="color_theme" and @checked]')->item(0)->getAttribute('value')==='blue','UI defaults blue');
$s=['source_type'=>'text','description'=>'빨간 케이블로 연결한 서버','options'=>['space'=>'right','mood'=>'light','complexity'=>'high','image_share'=>50,'notes'=>'','transparent_background'=>false,'edge_margin'=>7.8125], 'analysis'=>['title'=>'서버','summary'=>'서버 연결','keywords'=>['서버'],'palette'=>['OLD_BLUE_PALETTE'],'scene_en'=>'OLD_BLUE_SCENE','content_requirements'=>[['source_detail'=>'OLD_BLUE_REQUIRED','visual_depiction'=>'OLD_BLUE_REQUIRED']]],'images'=>[['prompt'=>'saved history']]];
foreach(KC_COLOR_THEMES as $key=>$theme){
    $s['options']['color_theme']=$key;
    checkTheme(!kc_visual_analysis_current($s),'Theme change invalidates plan');
    $before=count($calls);$u=kc_prepare_visual_analysis($s);
    checkTheme(count($calls)===$before+1&&$u['visual_brief_theme']===$key,'Replans once with selected theme');
    checkTheme(str_contains($calls[$before]['instructions'],kc_theme_prompt($key)),'Planning sees theme');
    checkTheme(!str_contains($calls[$before]['input'],'OLD_BLUE_'),'Stale AI palette and scene excluded');
    checkTheme(str_contains($calls[$before]['input'],'빨간 케이블'),'Original required color retained');
    checkTheme(kc_prepare_visual_analysis($u)===$u&&count($calls)===$before+1,'Unchanged theme does not regenerate plan');
    checkTheme($u['images']===$s['images']&&$u['options']===$s['options'],'History and settings preserved');
    $node=$xp->query('//input[@name="color_theme" and @value="'.$key.'"]')->item(0);
    checkTheme($node!==null&&str_contains($node->parentNode->textContent,$theme['name']),'Matching UI label');
    checkTheme($xp->query('.//*[@style]',$node->parentNode)->length===0,'No inline styles blocked by production CSP');
    foreach($theme['colors'] as $n=>$color)checkTheme(str_contains($css,'.theme-options input[value="'.$key.'"]+.theme-card .theme-swatches i:nth-child('.($n+1).'){background:'.$color.'}'),'Matching external CSS swatch');
    foreach(['light','dark','color'] as $mood)foreach([false,true] as $transparent){
        $o=array_replace($u['options'],['mood'=>$mood,'transparent_background'=>$transparent,'background_color'=>'#663355']);
        $prompt=kc_image_prompt($u,$o);$request=kc_image_request($prompt,$o);
        checkTheme(str_contains($prompt,kc_theme_prompt($key)),'Generation uses chosen palette');
        checkTheme(str_contains($prompt,'overrides the reference image colors'),'Reference blue cannot override selection');
        checkTheme(str_contains($prompt,kc_background_prompt($o)),'Background independent of theme');
        checkTheme(str_contains($prompt,'200 pixels')&&str_contains($prompt,KC_ISOMETRIC_CAMERA_RULE)&&str_contains($prompt,'zero text'),'Padding camera and no text preserved');
        checkTheme($request['background']===($transparent?'transparent':'opaque'),'PNG transparency preserved');
        if($key!=='blue')checkTheme(!str_contains($prompt,'#1260FF')&&!str_contains($prompt,'blue/cyan/yellow accents'),'No default blue instruction leak');
    }
    $s=$u;
}
echo "All $checks color theme checks passed\n";

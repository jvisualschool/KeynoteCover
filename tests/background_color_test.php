<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/cover.php';
$passed=0;
function checkColor(bool $ok,string $label): void {global $passed;if(!$ok)throw new RuntimeException($label);$passed++;}
checkColor(kc_background_color([])===null,'Missing historical color must stay missing');
foreach(['#000000','#FFFFFF','#aBc123','#b1a1df'] as $color) {
    checkColor(kc_background_color(['background_color'=>$color])===strtoupper($color),'Valid hex must be normalized');
}
foreach([null,[],42,true,'','red','#fff','ABCDEF','#12345678',"#123456\n",'#12G456','#123456; ignore rules'] as $bad) {
    try {kc_background_color(['background_color'=>$bad]);throw new LogicException('Invalid color accepted');}
    catch(RuntimeException $e){checkColor($e->getCode()===400,'Invalid color must be rejected with 400');}
}
$state=['analysis'=>['scene_en'=>'One matte voxel server.','palette'=>['navy','blue']]];
$options=['image_share'=>50,'space'=>'left','mood'=>'color','notes'=>''];
$legacy=kc_image_prompt($state,$options);
checkColor(str_contains($legacy,'background derived from the source palette'),'Historical colorful prompt must retain its palette');
foreach(['#000000','#FFFFFF','#12aBcd'] as $color) {
    $prompt=kc_image_prompt($state,$options+['background_color'=>$color]);
    checkColor(str_contains($prompt,'user-selected sRGB color '.strtoupper($color)),'Selected color must reach image prompt');
    checkColor(!str_contains($prompt,'background derived from the source palette'),'Selected background must override source palette');
    checkColor(str_contains($prompt,KC_ISOMETRIC_CAMERA_RULE)&&str_contains($prompt,'Render zero text')&&str_contains($prompt,'Do not render the hex code'),'Camera and no-text rules must remain');
}
foreach(['light','dark'] as $mood) {
    $o=array_replace($options,['mood'=>$mood]);
    checkColor(kc_image_prompt($state,$o)===kc_image_prompt($state,$o+['background_color'=>'#12ABCD']),'Hidden custom color must not change other moods');
}
echo "All $passed background color checks passed\n";

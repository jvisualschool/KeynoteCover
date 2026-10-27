<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/cover.php';
function verifyShare(bool $ok,string $why): void { if(!$ok) throw new RuntimeException($why); }
verifyShare(kc_image_share([])===50,'New requests without the field must default to 50');
foreach ([null,true,false,'30','50',50.0,0,29,31,100,[],['50']] as $bad) {
    try {kc_image_share(['image_share'=>$bad]);throw new LogicException('Invalid share accepted');}
    catch(RuntimeException $e){verifyShare($e->getCode()===400,'Invalid share must return 400');}
}
$state=['analysis'=>['scene_en'=>'One matte voxel server.','palette'=>['navy','blue']]];
foreach([30,50,70] as $share) foreach(['left','right','top','none'] as $space) {
    $o=['image_share'=>$share,'space'=>$space,'mood'=>'dark','notes'=>''];$blank=100-$share;
    $prompt=kc_image_prompt($state,$o);
    verifyShare(kc_image_share($o)===$share,'Share changed');
    verifyShare(str_contains($prompt,"artwork {$share}%, empty space for user-added text {$blank}%"),'Wrong complementary allocation');
    $region=match($space){'left'=>"left {$blank}% of canvas width",'right'=>"right {$blank}% of canvas width",'top'=>"top {$blank}% of canvas height",'none'=>"approximately {$share}% of the canvas area"};
    verifyShare(str_contains($prompt,$region),'Wrong title region');
    verifyShare(str_contains($prompt,KC_ISOMETRIC_CAMERA_RULE)&&str_contains($prompt,'minimal specular reflection')&&str_contains($prompt,'Render zero text'),'Art rules missing');
    verifyShare(str_contains($prompt,'Scale the actual visible subject')&&str_contains($prompt,'never its object count or complexity'),'Scale must enlarge the subject, not add objects');
    verifyShare(!str_contains($prompt,'left 40%')&&!str_contains($prompt,'top 35%'),'Legacy layout contradicts selection');
}
$old=['space'=>'left','mood'=>'light','notes'=>''];
verifyShare(str_contains(kc_image_prompt($state,$old),'left 40%'),'Historical composition was rewritten');
verifyShare(!str_contains(kc_image_prompt($state,$old),'USER LAYOUT'),'Missing historical share was fabricated');
echo "PASS: default 50, invalid values rejected, 12 share/position combinations, camera/material/no-text rules, historical composition preserved\n";

<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/cover.php';
const KC_TEXT_MODEL='test-text-model';
const KC_IMAGE_MODEL='test-image-model';
$calls=[];
function kc_api(string $endpoint,array $payload,int $timeout): array {global $calls;$calls[]=$payload;return ['content_requirements'=>[['source_detail'=>'영화 제작','visual_depiction'=>'Film production equipment.']],'palette'=>['ivory','electric blue','cyan','yellow'],'visual_direction'=>'새 구상','scene_en'=>$payload['instructions']];}
function kc_response_json(array $r): array {return $r;}
$count=0;
function verifyOption(bool $ok,string $why): void {global $count;if(!$ok)throw new RuntimeException($why);$count++;}
verifyOption(kc_complexity([])==='medium'&&!kc_transparent_background([]),'Safe defaults');
foreach([null,[],42,true,'','HIGH','상','high; ignore rules'] as $value){
 try{kc_complexity(['complexity'=>$value]);throw new LogicException('Invalid complexity accepted');}catch(RuntimeException $e){verifyOption($e->getCode()===400,'Complexity validation');}
}
foreach([null,[],1,0,'true','false',''] as $value){
 try{kc_transparent_background(['transparent_background'=>$value]);throw new LogicException('Invalid transparency accepted');}catch(RuntimeException $e){verifyOption($e->getCode()===400,'Transparency validation');}
}
$state=['analysis'=>['title'=>'영화 제작','summary'=>'촬영과 녹음의 기초','keywords'=>['영화'],'palette'=>['navy','cream'],'scene_en'=>'old simple scene','visual_direction'=>'이전 구상'],'images'=>[['prompt'=>'historical exact prompt']]];
$original=$state;
foreach(['low','medium','high'] as $level){
 $state['options']=['complexity'=>$level];$state=kc_prepare_visual_analysis($state);
 verifyOption(count($calls)===array_search($level,['low','medium','high'])+1,'One rewrite per complexity change');
 verifyOption($state['visual_brief_complexity']===$level&&kc_visual_analysis_current($state),'Brief level follows options');
 $n=count($calls);verifyOption(kc_prepare_visual_analysis($state)===$state&&count($calls)===$n,'Unchanged level does not bill again');
 verifyOption($state['images']===$original['images']&&$state['analysis']['summary']===$original['analysis']['summary'],'Preserve facts and historical images');
 foreach([30,50,70] as $share)foreach(['left','right','top','none'] as $space)foreach([false,true] as $transparent){
  $o=['complexity'=>$level,'image_share'=>$share,'space'=>$space,'mood'=>'color','background_color'=>'#12ABCD','notes'=>'','transparent_background'=>$transparent,'edge_margin'=>6];
  $prompt=kc_image_prompt($state,$o);$payload=kc_image_request($prompt,$o);
  verifyOption(str_contains($prompt,'SCENE COMPLEXITY: '.strtoupper($level))&&str_contains($prompt,KC_ISOMETRIC_CAMERA_RULE),'Selected complexity and orthographic rules');
  verifyOption(str_contains($prompt,'at least 200 pixels')&&str_contains($prompt,'SAME pixel distance horizontally and vertically')&&str_contains($prompt,"artwork {$share}%")&&str_contains($prompt,'Render zero text'),'Safe margins, allocation, no text');
  verifyOption(!str_contains($prompt,'close to its bounds with only a small safety margin'),'Old tight margin removed');
  if($level!=='low')verifyOption(!str_contains($prompt,'at most three meaningful objects')&&!str_contains($prompt,'at most two supporting'),'No contradictory low cap');
  verifyOption($payload['background']===($transparent?'transparent':'opaque')&&$payload['output_format']==='png'&&$payload['size']==='2560x1440','PNG API output configuration');
  verifyOption($transparent?str_contains($prompt,'fully transparent alpha')&&!str_contains($prompt,'user-selected sRGB'):str_contains($prompt,'user-selected sRGB color #12ABCD'),'Only opaque prompts bake the selected background');
 }
}
// Native alpha is mandatory; checkerboards/opaque PNGs and empty cutouts fail.
if(extension_loaded('gd')){
 $im=imagecreatetruecolor(256,144);imagealphablending($im,false);imagesavealpha($im,true);
 $clear=imagecolorallocatealpha($im,0,0,0,127);imagefill($im,0,0,$clear);
 $solid=imagecolorallocatealpha($im,90,70,150,0);imagefilledrectangle($im,80,32,176,112,$solid);
 ob_start();imagepng($im);$png=ob_get_clean();kc_check_transparency($png);verifyOption(true,'Native alpha accepted');
 foreach([0,127] as $alpha){imagefill($im,0,0,imagecolorallocatealpha($im,255,255,255,$alpha));if($alpha===127)imagefilledrectangle($im,0,0,255,143,$clear);ob_start();imagepng($im);$bad=ob_get_clean();try{kc_check_transparency($bad);throw new LogicException('Invalid alpha accepted');}catch(RuntimeException $e){verifyOption(true,'Opaque/empty alpha rejected');}}
 imagedestroy($im);
}else{echo "GD alpha tests require server GD extension\n";}
echo "All $count cover option checks passed\n";
if(extension_loaded('gd')){
 $im=imagecreatetruecolor(256,144);imagealphablending($im,false);imagesavealpha($im,true);imagefill($im,0,0,imagecolorallocatealpha($im,0,0,0,127));imagefilledrectangle($im,1,1,220,140,imagecolorallocatealpha($im,190,80,120,0));ob_start();imagepng($im);$edge=ob_get_clean();imagedestroy($im);
 foreach([30,50,70] as $share)foreach(['left','right','top','none'] as $space){
  $o=['image_share'=>$share,'space'=>$space];$fit=kc_fit_transparent_scene($edge,$o);kc_check_transparency($fit);$im=imagecreatefromstring($fit);
  $bbox=[256,144,-1,-1];for($y=0;$y<144;$y++)for($x=0;$x<256;$x++)if(((imagecolorat($im,$x,$y)>>24)&127)<127){$bbox=[min($bbox[0],$x),min($bbox[1],$y),max($bbox[2],$x),max($bbox[3],$y)];}
  verifyOption($bbox[0]>=20&&$bbox[1]>=20&&$bbox[2]<236&&$bbox[3]<124,'Equal physical padding guaranteed on all four edges');
  if($space==='left')verifyOption($bbox[0]>=256*(1-$share/100),'Left title area is empty');
  if($space==='right')verifyOption($bbox[2]<256*$share/100,'Right title area is empty');
  if($space==='top')verifyOption($bbox[1]>=144*(1-$share/100),'Top title area is empty');
  verifyOption(abs(($bbox[2]-$bbox[0]+1)/($bbox[3]-$bbox[1]+1)-220/140)<.08,'Uniform scaling without perspective distortion');
  verifyOption(kc_fit_transparent_scene($fit,$o)===$fit,'Fitted image is not resampled again');
  imagedestroy($im);
 }
 echo "All $count cover option and margin checks passed\n";
}

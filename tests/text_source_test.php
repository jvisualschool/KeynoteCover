<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/cover.php';
const KC_TEXT_MODEL='test-model';
$passed=0;
function checkText(bool $ok,string $name): void {global $passed;if(!$ok)throw new RuntimeException($name);$passed++;echo "PASS $name\n";}
foreach([null,[],false,42,''," \n\t",str_repeat('가',2001),"\xFF"] as $invalid) {
    try {kc_description($invalid);throw new LogicException('Invalid description accepted');}
    catch(RuntimeException $e) {checkText($e->getCode()===400,'Invalid description rejected');}
}
checkText(kc_description("　 봄 🌸 　")==='봄 🌸','Unicode spaces trimmed');
checkText(mb_strlen(kc_description(str_repeat('가',2000)))===2000,'2000 Korean characters accepted');
$brief='초보자를 위한 클라우드 서버 강의. 작은 서버 한 대.';
$text=kc_analysis_request(['source_type'=>'text','description'=>$brief,'pages'=>0],'/does-not-exist');
$content=$text['input'][0]['content'];
checkText(count($content)===1&&$content[0]===['type'=>'input_text','text'=>$brief],'Text analysis needs no file');
checkText(!isset($text['text']['format']['schema']['properties']['page_summaries']),'Text schema does not fabricate pages');
checkText(str_contains($text['instructions'],KC_ISOMETRIC_CAMERA_RULE)&&str_contains($text['instructions'],kc_voxel_style()),'Strict camera and preferred bright voxel style');
checkText(str_contains($text['instructions'],'SCENE COMPLEXITY: MEDIUM')&&str_contains($text['instructions'],'never include product names'),'Default medium scene and no lettering rules');
$fixtureDir=sys_get_temp_dir().'/keycover-text-'.bin2hex(random_bytes(6));mkdir($fixtureDir,0700);
copy(__DIR__.'/fixtures/lecture-3.pdf',$fixtureDir.'/source.pdf');
$pdf=kc_analysis_request(['pages'=>3],$fixtureDir);
unlink($fixtureDir.'/source.pdf');rmdir($fixtureDir);
checkText(isset($pdf['text']['format']['schema']['properties']['page_summaries']),'Legacy PDF still requires per-page coverage');
checkText($pdf['input'][0]['content'][0]['type']==='input_file'&&str_contains($pdf['instructions'],'Read ALL 3 pages'),'Legacy PDF is still attached and fully analyzed');
echo "All $passed text source checks passed\n";

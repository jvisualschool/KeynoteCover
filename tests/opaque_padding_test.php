<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/cover.php';
$count=0;
function paddingCheck(bool $ok,string $message): void {global $count;if(!$ok)throw new RuntimeException($message);$count++;}
function pngBytes($im): string {ob_start();imagepng($im);return ob_get_clean();}
function visibleBounds($im): array {
    $w=imagesx($im);$h=imagesy($im);$b=[$w,$h,-1,-1];
    for($y=0;$y<$h;$y++)for($x=0;$x<$w;$x++){
        $c=imagecolorat($im,$x,$y);$ref=imagecolorat($im,$w-1,$y);
        if(max(abs((($c>>16)&255)-(($ref>>16)&255)),abs((($c>>8)&255)-(($ref>>8)&255)),abs(($c&255)-($ref&255)))>8)$b=[min($b[0],$x),min($b[1],$y),max($b[2],$x),max($b[3],$y)];
    }return $b;
}
foreach([[6,20,150,123],[6,14,150,132],[0,0,180,143],[30,30,210,113]] as [$l,$t,$r,$b]){
    $im=imagecreatetruecolor(256,144);
    for($y=0;$y<144;$y++)for($x=0;$x<256;$x++)imagesetpixel($im,$x,$y,imagecolorallocate($im,18+(int)floor($x/64),27,53+(int)floor($y/24)));
    $color=imagecolorallocate($im,80,140,190);imagefilledrectangle($im,$l,$t,$r,$b,$color);
    $original=pngBytes($im);imagedestroy($im);
    $fit=kc_balance_opaque_padding($original,['space'=>'right']);$im=imagecreatefromstring($fit);[$x0,$y0,$x1,$y1]=visibleBounds($im);
    paddingCheck(imagesx($im)===256&&imagesy($im)===144,'Output stays 16:9 with the same dimensions');
    paddingCheck($x0>=20&&$y0>=20&&$x1<236&&$y1<124,'Physical padding is present on every edge');
    paddingCheck($x0>=($y0+143-$y1)/2-2,'Left clearance is at least the vertical clearance');
    paddingCheck(abs($y0-(143-$y1))<=2,'Top and bottom clearances stay balanced');
    paddingCheck(abs(($x1-$x0+1)/($y1-$y0+1)-($r-$l+1)/($b-$t+1))<.06,'Scene aspect ratio is preserved');
    paddingCheck(kc_balance_opaque_padding($fit,['space'=>'right'])===$fit,'Already fitted result is not resampled again');
    paddingCheck(kc_balance_opaque_padding($original,['space'=>'left'])===$original,'Other title layouts remain unchanged');
    $smooth=true;
    for($y=$y1+2;$y<144;$y++)for($x=0;$x<256;$x++){
        $a=imagecolorat($im,$x,$y-1);$b=imagecolorat($im,$x,$y);
        if(max(abs((($a>>16)&255)-(($b>>16)&255)),abs((($a>>8)&255)-(($b>>8)&255)),abs(($a&255)-($b&255)))>1)$smooth=false;
    }
    paddingCheck($smooth,'Extended background has no horizontal seam below the artwork');
    imagedestroy($im);
}
echo "All $count opaque padding checks passed\n";

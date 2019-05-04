<?php
/*

layout:
----------
CPU: 199% 3700MHz 200C 3600rpm
RAM:  16384 used / 16384 free

GPU: 199%   200C   3600rpm
VRAM: 16384 used / 16384 free
----------

text: size: 30x5
font size: 16x24
picture size: 480 (480 max) x 120 (320 max)

*/

// parameters

$screenW = 480;
$screenH = 320;
$borderX = 0;
$borderY = 8;
$spaceY = 8;


// global var with their default vaues
$image = 0;
$colorForeground = 0;
$colorBackground = 0;
$font = 0;
$fontW = 0;
$fontH = 0;

class image {
	var $screenW;
	var $screenH;
	var $borderX;
	var $borderY;
	var $spaceY;

	var $colorForeground = 0;
	var $colorBackground = 0;
	var $font = 0;
	var $fontW = 0;
	var $fontH = 0;

	function _constructor($spec_screenW, $spec_screenH, $spec_borderX, $spec_borderY, $spec_$spaceY) {
		$screenW = $spec_screenW;
		$screenH = $spec_screenH;
		$borderX = $spec_borderX;
		$borderY = $spec_borderY;
		$spaceY = $spec_$spaceY;
	}

}

function drawTextString($textX, $textY, $text) {
	global $borderX, $borderY, $colorForeground, $font;
	global $fontW, $fontH, $spaceY, $image;

	$x = $textX * $fontW + $borderX;
	$y = $textY * ($spaceY + $fontH) + $borderY;
	imagestring($image, $font, $x, $y, $text, $colorForeground);
}

function getMonitoredValues() {
}

function renderImage() {
	global $image, $colorForeground, $colorBackground;
	global $screenW, $screenH;
	global $font, $fontW, $fontH;

	$image = imagecreatetruecolor($screenW, $screenH);

	$colorForeground = imagecolorallocate($image, 96, 255, 0);
	$colorBackground = imagecolorallocate($image, 0, 0, 0);
	imagefilledrectangle($image, 0, 0, $screenW, $screenH, $colorBackground);

	// Load custom font from http://www.danceswithferrets.org/lab/gdfs/
	$font = imageloadfont("HomBoldB_16x24_LE.gdf");
	$fontW = imagefontwidth($font);
	$fontH = imagefontheight($font);

	drawTextString(0, 0, "CPU: 199% 3700MHz 200C 3600rpm");
	drawTextString(0, 1, "RAM:  16384 used / 16384 free");
	drawTextString(0, 3, "GPU: 199%   200C   3600rpm");
	drawTextString(0, 4, "VRAM: 16384 used / 16384 free");

}

function _main() {
	image = new image(480, 320, 0, 8, 8);

	getMonitoredValues();
	renderImage();

	header('Content-type: image/png');
	imagepng($image);
	imagedestroy($image);
}

_main();

?> 

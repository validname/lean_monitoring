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

class Image {
	public $image;
	private $screenW;
	private $screenH;
	private $borderX;
	private $borderY;
	private $spaceY;

	private $colorForeground;
	private $colorBackground;
	private $font;
	private $fontW;
	private $fontH;

	function __construct($spec_screenW, $spec_screenH, $spec_borderX, $spec_borderY, $spec_spaceY) {
		$this->screenW = $spec_screenW;
		$this->screenH = $spec_screenH;
		$this->borderX = $spec_borderX;
		$this->borderY = $spec_borderY;
		$this->spaceY = $spec_spaceY;
	}

	function drawTextString($textX, $textY, $text) {
		$x = $textX * $this->fontW + $this->borderX;
		$y = $textY * ($this->spaceY + $this->fontH) + $this->borderY;
		imagestring($this->image, $this->font, $x, $y, $text, $this->colorForeground);
	}

	public function setFont($path) {
		$this->font = imageloadfont($path);
		$this->fontW = imagefontwidth($this->font);
		$this->fontH = imagefontheight($this->font);
	}

	public function renderImage() {
		$this->image = imagecreatetruecolor($this->screenW, $this->screenH);

		$this->colorForeground = imagecolorallocate($this->image, 96, 255, 0);
		$this->colorBackground = imagecolorallocate($this->image, 0, 0, 0);
		imagefilledrectangle($this->image, 0, 0, $this->screenW, $this->screenH, $this->colorBackground);

		$this::drawTextString(0, 0, "CPU: 199% 3700MHz 200C 3600rpm");
		self::drawTextString(0, 1, "RAM:  16384 used / 16384 free");
		self::drawTextString(0, 3, "GPU: 199%   200C   3600rpm");
		self::drawTextString(0, 4, "VRAM: 16384 used / 16384 free");
	}

	public function outputImage() {
		header('Content-type: image/png');
		imagepng($this->image);
		imagedestroy($this->image);
	}
}

function getMonitoredValues() {
}

function _main() {
	$Image = new Image(480, 320, 0, 8, 8);

	// Load custom font from http://www.danceswithferrets.org/lab/gdfs/
	$Image->setFont("HomBoldB_16x24_LE.gdf");

	getMonitoredValues();

	$Image->renderImage();
	$Image->outputImage();
}

_main();

?> 

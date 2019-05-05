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
$config = array();
$config['screenW'] = 480;
$config['screenH'] = 320;
$config['borderX'] = 0;
$config['borderY'] = 8;
$config['spaceY'] = 8;

// font from http://www.danceswithferrets.org/lab/gdfs/
$config['font'] = "/usr/share/system_usage/HomBoldB_16x24_LE.gdf";

$config['outputFile'] = '/mnt/tmpfs/system_usage.png';

$config['generateInterval'] = 10;

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

	private $outputImage;

	private $textArray = array();

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

	public function addText($textX, $textY, $text) {
		$this->textArray[] = array( 'x' => $textX, 'y' => $textY, 'text' => $text );
	}

	public function renderImage() {
		$this->image = imagecreatetruecolor($this->screenW, $this->screenH);

		$this->colorForeground = imagecolorallocate($this->image, 96, 255, 0);
		$this->colorBackground = imagecolorallocate($this->image, 0, 0, 0);
		imagefilledrectangle($this->image, 0, 0, $this->screenW, $this->screenH, $this->colorBackground);

		foreach ($this->textArray as $textElement) {
			$this::drawTextString($textElement['x'], $textElement['y'], $textElement['text']);
		}
	}

	public function setOutputImage($path) {
		$this->outputImage = $path;
	}

	public function outputImage() {
		header('Content-type: image/png');
		imagepng($this->image, $this->outputImage);
		imagedestroy($this->image);
	}
}

function getMonitoredValues() {
}

function _main() {
	global $config;

	$Image = new Image($config['screenW'], $config['screenH'], $config['borderX'], $config['borderY'], $config['spaceY']);

	$Image->setFont($config['font']);
	$Image->setOutputImage($config['outputFile']);

	while (1) {
		getMonitoredValues();
		$Image->addText(0, 0, "CPU: 199% 3700MHz 200C 3600rpm");
		$Image->addText(0, 1, "RAM:  16384 used / 16384 free");
		$Image->addText(0, 3, "GPU: 199%   200C   3600rpm");
		$Image->addText(0, 4, "VRAM: 16384 used / 16384 free");

		$Image->renderImage();
		$Image->outputImage();

		sleep($config['generateInterval']);
	}
}

_main();

?> 

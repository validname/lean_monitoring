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

$cpu_stats = array();

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

		$this->image = imagecreatetruecolor($this->screenW, $this->screenH);

		$this->colorForeground = imagecolorallocate($this->image, 96, 255, 0);
		$this->colorBackground = imagecolorallocate($this->image, 0, 0, 0);

		imagealphablending( $this->image, true);
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
		imagefilledrectangle($this->image, 0, 0, $this->screenW, $this->screenH, $this->colorBackground);

		foreach ($this->textArray as $textElement) {
			$this::drawTextString($textElement['x'], $textElement['y'], $textElement['text']);
		}
		unset($this->textArray);
	}

	public function setOutputImage($path) {
		$this->outputImage = $path;
	}

	public function outputImage() {
		imagepng($this->image, $this->outputImage);
	}
}

function searchRegexInArray($textArray, $string, $startIndex=0) {
	$arrayLength = count($textArray);

	$found = false;
	for ($index = $startIndex; $index < $arrayLength; $index++) {
		if (preg_match($string, $textArray[$index])) {
			$found = true;
			break;
		}
	}
	if ($found == true) {
		return $index;
	} else {
		return -1;
	}
}

function readFileIntoArray($path) {
	$file_array = file($path);

	foreach ($file_array as $line_num => $line) {
		if (strpos($line, "\n", -1)) {
			$file_array[$line_num] = rtrim($line);
		}
	}

	return $file_array;
}

function splitStringByBlanks($string) {
	return preg_split("/[[:blank:]]+/", $string);
}

function getCPUValues() {
	// "CPU: 199% 3700MHz 200C 3600rpm";
	global $cpu_stats;
	global $config;

	// 1. CPU usage ($cpu_usage_text, 4 chars width)
	$fileArray = readFileIntoArray("/proc/stat");
	$statsIndex = searchRegexInArray($fileArray, "/^cpu[[:blank:]]/");

	$statsArray = splitStringByBlanks($fileArray[$statsIndex]);

	$idle = $statsArray[4];
	$total = 0;
	for ($index = 1; $index < count($statsArray); $index++) {
		$total += $statsArray[$index];
	}

	if (isset($cpu_stats['idle']) && isset($cpu_stats['total']) ) {
		$idle_diff = $cpu_stats['idle'] - $idle;
		$total_diff = $cpu_stats['total'] - $total;

		if ( $total_diff != 0 ) {
			$cpu_usage = (1 - $idle_diff / $total_diff) * 100;
			$cpu_usage_text = substr(sprintf("%f", $cpu_usage), 0, 3);

			if (strpos($cpu_usage_text, ".", -1)) {
				$cpu_usage_text = " ".substr($cpu_usage_text, 0, -1);
			}

			$cpu_usage_text = $cpu_usage_text . '%';
		} else {
			$cpu_usage_text = "?/0%";
		}
	} else {
		$cpu_usage_text = "---%";
	}

	$cpu_stats['idle'] = $idle;
	$cpu_stats['total'] = $total;

	// 2. CPU frequence ($cpu_freq_text, 7 chars width)
	$cpu_freq_text = "3700MHz";

	// 3. CPU temperature ($cpu_temperature_text, 4 chars width)
	$cpu_temperature_text = "200C";

	// 4. CPU fan speed ($cpu_fan_text, 7 chars width)
	$cpu_fan_text = "3600rpm";

	$return_text = "CPU: ".$cpu_usage_text." ".$cpu_freq_text." ".$cpu_temperature_text." ".$cpu_fan_text;

	var_dump($return_text);
	return $return_text;
}

function getRAMValues() {
	// cat /proc/meminfo | egrep '^(MemTotal|MemFree|Buffers|Cached|Shmem):'
	// Used: MemTotal-MemFree-Buffers-Cached+Shmem
	return "RAM: 16384 f / 16384 a / 16384 u";
}

function getGPUValues() {
	// nvidia-smi -q | egrep -A 1 '^[[:blank:]]*Utilization' | grep Gpu
	// nvidia-smi -q | egrep 'GPU Current Temp'
	// nvidia-smi -q | egrep 'Fan'
	return "GPU: 199%  200C F:100%";
}

function getVRAMalues() {
	// nvidia-smi -q | grep -A 3 'FB Memory Usage' | egrep 'Used|Free'
	return "VRAM: 16384 used / 16384 free";
}

function _main() {
	global $config;

	$Image = new Image($config['screenW'], $config['screenH'], $config['borderX'], $config['borderY'], $config['spaceY']);

	$Image->setFont($config['font']);
	$Image->setOutputImage($config['outputFile']);

	while (1) {
		$Image->addText(0, 0, getCPUValues());

		$Image->addText(0, 1, getRAMValues());

		$Image->addText(0, 3, getGPUValues());

		$Image->addText(0, 4, getVRAMalues());

		$Image->renderImage();
		$Image->outputImage();

		sleep($config['generateInterval']);
	}
}

_main();

?> 

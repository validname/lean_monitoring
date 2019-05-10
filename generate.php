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
$config['borderY'] = 16;
$config['spaceY'] = 8;

// font from http://www.danceswithferrets.org/lab/gdfs/
$config['font'] = "/usr/share/system_usage/HomBoldB_16x24_LE.gdf";

$config['outputFile'] = '/mnt/tmpfs/system_usage.png';

$config["cpuThreads"] = 1;
$config["cpuTemperatureSensorPath"] = "/sys/class/hwmon/hwmon1/temp1_input";
$config["cpuFANSensorPath"] = "/sys/class/hwmon/hwmon2/fan1_input";

$config['generateInterval'] = 5;

$cpuStats = array();

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
	$fileArray = file($path);

	foreach ($fileArray as $lineNum => $line) {
		if (strpos($line, "\n", -1)) {
			$fileArray[$lineNum] = rtrim($line);
		}
	}

	return $fileArray;
}

function splitStringByBlanks($string) {
	return preg_split("/[[:blank:]]+/", $string);
}

function getCPUThreads() {
	$topologyString = rtrim(file_get_contents("/sys/devices/system/cpu/cpu0/topology/thread_siblings_list"));

	// split list if it's exist
	$topologyArray = explode(",", $topologyString);
	$threads = 0;

	foreach ($topologyArray as $tmpIndex => $listElement) {
		if (strpos($listElement, "-") !== false) {
			// it's a range
			list($first,$last) = explode("-", $listElement);
			for ( $tmpIndex2 = $first; $tmpIndex2 <= $last; $tmpIndex2++ ) {
				$threads++;
			}
		} else {
			// just a single core
			$threads++;
		}
	}

	return $threads;
}


function getCPUValues() {
	// "CPU: 199% 3700MHz 200C 3600rpm";
	global $cpuStats;
	global $config;

	// 1. CPU usage ($cpuUsageText, 4 chars width)
	$fileArray = readFileIntoArray("/proc/stat");
	$statsIndex = searchRegexInArray($fileArray, "/^cpu[[:blank:]]/");

	$statsArray = splitStringByBlanks($fileArray[$statsIndex]);

	$idle = $statsArray[4];
	$total = 0;
	for ($index = 1; $index < count($statsArray); $index++) {
		$total += $statsArray[$index];
	}

	if (isset($cpuStats['idle']) && isset($cpuStats['total']) ) {
		$idleDiff = $cpuStats['idle'] - $idle;
		$totalDiff = $cpuStats['total'] - $total;

		if ( $totalDiff != 0 ) {
			$cpuUsage = (1 - $idleDiff / $totalDiff) * 100;
			// correct using threads
			$cpuUsage *= $config["cpuThreads"];
			$cpuUsageText = substr(sprintf("%f", $cpuUsage), 0, 3);

			if (strpos($cpuUsageText, ".", -1)) {
				$cpuUsageText = " ".substr($cpuUsageText, 0, -1);
			}

			$cpuUsageText = $cpuUsageText . '%';
		} else {
			$cpuUsageText = "?/0%";
		}
	} else {
		$cpuUsageText = "---%";
	}

	$cpuStats['idle'] = $idle;
	$cpuStats['total'] = $total;

	// 2. CPU frequence ($cpuFrequenceText, 7 chars width)
	// get only on first core, i don't know how to consider all of them
	$cpuFrequence = rtrim(file_get_contents("/sys/devices/system/cpu/cpu0/cpufreq/scaling_cur_freq"));

	$cpuFrequenceText = sprintf("%4dMHz", $cpuFrequence/1000);

	// 3. CPU temperature ($cpuTemperatureText, 4 chars width)
	$cpuTemperature = rtrim(file_get_contents($config["cpuTemperatureSensorPath"]));
	$cpuTemperatureText = sprintf("%3dC", $cpuTemperature/1000);

	// 4. CPU fan speed ($cpuFANText, 7 chars width)
	$cpuFAN = rtrim(file_get_contents($config["cpuFANSensorPath"]));
	$cpuFANText = sprintf("%4drpm", $cpuFAN);

	$returnText = "CPU: ".$cpuUsageText." ".$cpuFrequenceText." ".$cpuTemperatureText." ".$cpuFANText;

	var_dump($returnText);
	return $returnText;
}

function getRAMValues() {
	$memoryInfoFileArray = readFileIntoArray("/proc/meminfo");

	$memoryInfoArray = array( 'MemTotal' => 0, 'MemFree' => 0, 'MemAvailable' => 0, 'Buffers' => 0, 'Cached' => 0, 'Shmem' => 0 );

	$returnText = "RAM: ???";
	$gotError = false;
	foreach( $memoryInfoArray as $statName => $tmpValue ) {
		$arrayIndex = searchRegexInArray($memoryInfoFileArray, "/^" . $statName . ":/");
		if ( $arrayIndex === -1 ) {
			$gotError = true;
			break;
		}
		$statValueArray = splitStringByBlanks($memoryInfoFileArray[$arrayIndex]);
		$memoryInfoArray[$statName] = $statValueArray[1];
	}

	if ( !$gotError ) {
		$memoryFree = $memoryInfoArray["MemFree"] / 1024;
		$memoryAvailable = $memoryInfoArray["MemAvailable"] / 1024;
		$memoryUsed = ($memoryInfoArray["MemTotal"] - $memoryInfoArray["MemFree"] - $memoryInfoArray["Buffers"] - $memoryInfoArray["Cached"] + $memoryInfoArray["Shmem"]) / 1024;

		$returnText = sprintf("RAM: %d f / %d a / %d u", $memoryFree, $memoryAvailable, $memoryUsed);
	}

	var_dump($returnText);
	return $returnText;
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

	$config["cpuThreads"] = getCPUThreads();

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

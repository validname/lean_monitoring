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

// can be overriden by command line
$config['debug'] = false;
$config['dryRun'] = false;
$config['runOnce'] = false;
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

class Cache {
	private $value;
	private $TTL;
	private $timestamp;

	private	$getFunctionReference;

	function __construct($getFunctionReference, $TTL) {
		$this->getFunctionReference = $getFunctionReference;
		$this->TTL = $TTL;
		$this->value = false;
		$this->timestamp = time();
	}

	public function getValue() {
		$currentTimestamp = time();

		if( $this->TTL == 0 || ($currentTimestamp-$this->timestamp) > $this->TTL || $this->value === false ) {
			$this->value = call_user_func($this->getFunctionReference);
			$this->timestamp = $currentTimestamp;
		}

		return $this->value;
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
	return preg_split("/[[:blank:]]+/", trim($string));
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

	if($config['debug']) {
		var_dump($returnText);
	}
	return $returnText;
}

function getRAMValues() {
	global $config;

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

	if($config['debug']) {
		var_dump($returnText);
	}
	return $returnText;
}

function getGPUAllInfo() {
	$returnArray = array();
	$returnCode = 0;

	exec('nvidia-smi -q 2>&1', $returnArray, $returnCode);

	if( $returnCode > 0) {
		echo "getGPUAllInfo(): got error ".$returnCode." while executing command.".PHP_EOL;
		$output = implode(PHP_EOL, $returnArray);
		echo "getGPUAllInfo(): output was: '".$output."'".PHP_EOL;

		// we can return false, but cache will be always invalid and this command will runned each time. It make cache useless in this case.
		return "";
	} else {
		return $returnArray;
	}
}

function getGPUValues($cachedGPUInfo) {
	global $config;

	$returnText = "";
	$GPUInfo = $cachedGPUInfo->getValue();

	if($GPUInfo === false || $GPUInfo == "") {
		$returnText = "GPU: (no info)";
	} else {
		// 1. GPU utilizattion
		$gpuUtilization = 0;
		$gotError = false;

		$foundIndex = searchRegexInArray($GPUInfo, "/^[[:blank:]]*Utilization/i");
		if( $foundIndex === -1 ) {
			$gotError = true;
		} else {
			$foundIndex = searchRegexInArray($GPUInfo, "/^[[:blank:]]*GPU/i", $foundIndex);
			if( $foundIndex === -1 ) {
				$gotError = true;
			} else {
				$statsArray = splitStringByBlanks($GPUInfo[$foundIndex]);
				$gpuUtilization = $statsArray[2];
			}
		}

		if($gotError) {
			$gpuUtilizationText = "???";
		} else {
			$gpuUtilizationText = sprintf("%3d%%", $gpuUtilization);
		}

		// 2. GPU temperature
		$gpuTemperature = 0;
		$gotError = false;

		$foundIndex = searchRegexInArray($GPUInfo, "/^[[:blank:]]*GPU Current Temp/i");
		if( $foundIndex === -1 ) {
			$gpuTemperatureText = "???C";
		} else {
			$statsArray = splitStringByBlanks($GPUInfo[$foundIndex]);
			$gpuTemperature = $statsArray[4];

			$gpuTemperatureText = sprintf("%3dC", $gpuTemperature);
		}

		// 3. GPU FAN speed in %
		$gpuFANSpeed = 0;
		$gotError = false;

		$foundIndex = searchRegexInArray($GPUInfo, "/^[[:blank:]]*Fan/i");
		if( $foundIndex === -1 ) {
			$gpuFANText = "???%";
		} else {
			$statsArray = splitStringByBlanks($GPUInfo[$foundIndex]);
			$gpuFANSpeed = $statsArray[3];

			$gpuFANText = sprintf("F:%3d%%", $gpuFANSpeed);
		}

		$returnText = "GPU: ".$gpuUtilizationText."  ".$gpuTemperatureText." ".$gpuFANText;
	}

	if($config['debug']) {
		var_dump($returnText);
	}
	return $returnText;
}

function getVRAMalues($cachedGPUInfo) {
	global $config;

	$returnText = "";
	$GPUInfo = $cachedGPUInfo->getValue();

	if($GPUInfo === false || $GPUInfo == "") {
		$returnText = "VRAM: (no info)";
	} else {

		$foundIndexCommon = searchRegexInArray($GPUInfo, "/^[[:blank:]]*FB Memory Usage/i");
		if( $foundIndexCommon === -1 ) {
			$gotError = true;
		} else {

			$foundIndex = searchRegexInArray($GPUInfo, "/^[[:blank:]]*Used/i", $foundIndexCommon);
			if( $foundIndex === -1 || ($foundIndex-$foundIndexCommon)>3 ) {
				$vramUsedText = "???";
			} else {
				$statsArray = splitStringByBlanks($GPUInfo[$foundIndex]);
				$vramUsed = $statsArray[2];

				$vramUsedText = sprintf("%5d used", $vramUsed);
			}

			$foundIndex = searchRegexInArray($GPUInfo, "/^[[:blank:]]*Free/i", $foundIndexCommon);
			if( $foundIndex === -1 || ($foundIndex-$foundIndexCommon)>3 ) {
				$vramFreeText = "???";
			} else {
				$statsArray = splitStringByBlanks($GPUInfo[$foundIndex]);
				$vramFree = $statsArray[2];

				$vramFreeText = sprintf("%5d free", $vramFree);
			}
		}
		$returnText = "VRAM: ".$vramFreeText." / ".$vramUsedText;
	}

	if($config['debug']) {
		var_dump($returnText);
	}
	return $returnText;
}

function parseCommandLineOption() {
	global $config;

	$longOptions = array(
		"help",
		"debug",
		"dry-run",
		"once",
		"interval:"
	);
	$arguments = getopt("hdroi:", $longOptions);

	foreach ($arguments as $argumentName => $argumentValue) {
		switch ($argumentName) {
			case 'h':
			case 'help':
				echo <<< ENDOFTEXT
Command line arguments:
	-h | --help          this help
	-d | --debug         output all text to stdout
	-r | --dry-run       don't generate image
	-o | --once          run once instead of ifinite loop
	-i N | --interval N  interval in seconds for image generating
ENDOFTEXT;
				echo PHP_EOL;
				exit(0);
				break;

			case 'd':
			case 'debug':
				$config['debug'] = true;
				break;

			case 'r':
			case 'dry-run':
				$config['dryRun'] = true;
				break;

			case 'o':
			case 'once':
				$config['runOnce'] = true;
				break;

			case 'i':
			case 'interval':
				if($argumentValue > 0) {
					$config['generateInterval'] = $argumentValue;
				}
				break;
		}
	}
}

function _main() {
	global $config;

	parseCommandLineOption();

	$config["cpuThreads"] = getCPUThreads();

	$Image = new Image($config['screenW'], $config['screenH'], $config['borderX'], $config['borderY'], $config['spaceY']);

	$Image->setFont($config['font']);
	$Image->setOutputImage($config['outputFile']);

	$cacheTTL = $config['generateInterval']-1;
	if ( $cacheTTL<0 ) {
		$cacheTTL = 0;
	}

	$cachedGPUInfo = new Cache('getGPUAllInfo', $cacheTTL);

	while (1) {
		$Image->addText(0, 0, getCPUValues());

		$Image->addText(0, 1, getRAMValues());

		$Image->addText(0, 3, getGPUValues($cachedGPUInfo));

		$Image->addText(0, 4, getVRAMalues($cachedGPUInfo));

		if(!$config['dryRun']) {
			$Image->renderImage();
			$Image->outputImage();
		}

		if($config['runOnce']) {
			break;
		}

		sleep($config['generateInterval']);
	}
}

_main();

?>

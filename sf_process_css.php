<?php

require_once "sf.php";

function sf_process_css($data, $relative="./", $cdn) {
	if(substr($data,0,4) !== "/*CS") {
		$data = "/*CSS*/$data";
	}
	if(substr($relative,-1) != "/") $relative .= "/";
	
	$lastpos = 0;
	for(;;) {
		$pos = strpos($data, "url(", $lastpos);
		if($pos === false) {
			break;
		} else {
			$closing = strpos($data,")",$pos+2);
			if($closing === false) return $data;
			
			$ori = substr($data,$pos+4,$closing-$pos-4);// extract url part
				// get rid of quotes
			if((substr($ori,0,1) == "'" && substr($ori,-1) == "'") || (substr($ori,0,1) == "\"" && substr($ori,-1) == "\"")) {
				$ori = substr($ori,1,strlen($ori)-2);
			}
				// get rid of hashes
			if(($hashpos=strpos($ori,"#")) !== false) $ori = substr($ori,0, $hashpos);
				// get rid of trailing question marks
			if(substr($ori,-1) == "?") $ori = substr($ori,0,strlen($ori)-1);
			
			if(substr($ori,0,5) == "data:") {
				// leave data url as is
			} else {
				// apply url path if no protocol specified
				if(substr($ori,0,1) == "/") {
					// leading slashes
					if(substr($relative,0,7) == "http://" || substr($relative,0,8) == "https://") {
						$hostpart = strpos($relative,"/",8);
						if($hostpart !== false) {
							$ori = substr($relative,0,$hostpart) . $ori;
						}
					} else {
						// local urls .. try to find it in the fs, probably won't work.
					}
				} else if(substr($ori,0,7) != "http://" && substr($ori,0,8) != "https://") {
					$ori = $relative . $ori;
				}
				
				if(substr($data,$pos-8,8) == "@import ") {
					// handle css imports
					$idata = file_get_contents($ori);
					if($idata) {
						$hash = sf_push(sf_process_css($idata, dirname($ori), $cdn));
					} else {
						$hash = false;
					}
				} else {
					// handle other media
					$hash = sf_push_file($ori);
				}
				if($hash) {
					// replace if upload successful
					$data = substr($data,0,$pos+4) . "'$hash'" . substr($data,$closing);
				}
			}
		
			$lastpos = $pos + 3;
		}
	}
	return $data;
}


if(basename($_SERVER["PHP_SELF"]) == "sf_process_css.php" && isset($_SERVER["argv"])) {
	$argv = $_SERVER["argv"];
	if(isset($argv[1]) && ($argv[1] == "--help" || $argv[1] == "-h")) {
		echo "Usage: ".$argv[0]." [-s] [input file] [relative path] [cdn url]\nUse -s to use https:// in URLs\nLeave relative path to use dirname() of the input file or current if unknown\n\n";
		exit(1);
	}
	$sf_debug = true;
	sf_cache_load($_SERVER['HOME'] . "/.sfphp_cache");
	array_shift($argv);

	if($argv[0] == "-s") {
		$sf_generate_ssl = true;
		array_shift($argv);
	}
	
	if(isset($argv[0])) {
		echo sf_process_css(file_get_contents($argv[0] == "-" ? "php://stdin" : $argv[0]), isset($argv[1]) ? $argv[1] : dirname($argv[0]),isset($argv[2]) ? $argv[2] : "http://my-cdn.at");
	} else {
		echo sf_process_css(file_get_contents("php://stdin"));
	}

	sf_cache_save($_SERVER['HOME'] . "/.sfphp_cache");
	exit(0);
}

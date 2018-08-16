<?php if(!function_exists("sf_hash")) {

global $sf_hashlut, $sf_cache, $sf_debug;
$sf_hashlut = "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789.-";
$sf_cache = Array("files"=>Array(), "known"=>Array(), "dirty"=>false);
$sf_generate_ssl = false;
if(!isset($sf_debug)) $sf_debug = false;

function sf_format_hash($src) {
	global $sf_hashlut;
	$bytes = array_values(unpack('C*', $src));
	$bytes[32] = 0; $bytes[33] = 0; $bytes[34] = 0;
	$dest = "                                           ";
	for($s=0,$d=0;$s<33;$s+=3,$d+=4) {
		$dest[$d+0] = $sf_hashlut[
			 (($bytes[$s+0] & 0xC0) >> 2) | (($bytes[$s+1] & 0xC0) >> 4) | (($bytes[$s+2] & 0xC0) >> 6)
		];
		$dest[$d+1] = $sf_hashlut[$bytes[$s+0] & 0x3F];
		$dest[$d+2] = $sf_hashlut[$bytes[$s+1] & 0x3F];
		$dest[$d+3] = $sf_hashlut[$bytes[$s+2] & 0x3F];
	}
	return substr($dest,0,43);
}

function sf_unformat_hash($src) {
	global $sf_hashlut;
	if(strlen($src) < 43) {
		return false;
	}
	$src .= chr(0);
	$dest = "                                ";
	for($s=0,$d=0;$s<44;$s+=4,$d+=3) {
		$last = strpos($sf_hashlut, $src[$s+0]);
		$dest[$d+0] = chr(strpos($sf_hashlut, $src[$s+1]) | (($last & 0x30) << 2));
		$dest[$d+1] = chr(strpos($sf_hashlut, $src[$s+2]) | (($last & 0x0C) << 4));
		$dest[$d+2] = chr(strpos($sf_hashlut, $src[$s+3]) | (($last & 0x03) << 6));
	}
	return $dest;
}

function sf_hash($data) {
	return hash('sha256', $data, TRUE);
}

function sf_cache_load($file) {
	global $sf_cache, $sf_debug;
	if(file_exists($file)) {
		$sf_cache = json_decode(file_get_contents($file), true);
		if($sf_debug) echo "sf_cache_load: Loaded cache from $file\n";
	}
}

function sf_cache_save($file) {
	global $sf_cache, $sf_debug;
	if($sf_cache["dirty"]) {
		$sf_cache["dirty"] = false;
		file_put_contents($file, json_encode($sf_cache));
		if($sf_debug) echo "sf_cache_save: Saved cache to $file\n";
	}
}

function sf_apply_ssl($url) {
	global $sf_generate_ssl;
	return $sf_generate_ssl ? str_replace("http://", "https://", $url) : $url;
}

function sf_push_file($file, $check=true, $url="http://my-cdn.at/") {
	global $sf_cache, $sf_debug;
	if(strpos($file,"://") === false) {
		if(!file_exists($file)) {
			if($sf_debug) echo "sf_push_file: File $file doesn't exist - not pusing\n";
			return false;
		}
		$idx = "$url#$file@".filemtime($file);
		if(isset($sf_cache["files"][$idx])) {
			if($sf_debug) echo "sf_push_file: Found $idx in cache. Returning: " . $sf_cache["files"][$idx] . "\n";
			return sf_apply_ssl($sf_cache["files"][$idx]);
		}
		$result = sf_push(file_get_contents($file),$check,$url);
		if($result) {
			if($sf_debug) echo "sf_push_file: Added $result to file cache $idx\n";
			$sf_cache["files"][$idx] = $result;
			$sf_cache["dirty"] = true;
		}
		return sf_apply_ssl($result);
	}
	if($sf_debug) echo "sf_push_file: $file is not local, not using cache\n";
	return sf_push(file_get_contents($file),$check,$url);
}

function sf_push($data, $check=true, $url="http://my-cdn.at/") {
	if($data === "") {
		if($sf_debug) echo "sf_push: Not pushing empty string\n";
		return false;
	}
	
	global $sf_cache, $sf_debug;
	if(substr($url,-1) != "/") $url .= "/";
	$hash = sf_format_hash(sf_hash($data));
	$fileurl = $url . $hash;
	
	if($sf_debug) echo "sf_push: size=".strlen($data).", hash=$hash, check=".($check?"1":"0")."\n"; 

	if(isset($sf_cache["known"][$fileurl])) {
		if($sf_debug) echo "sf_push: Found hash in cache. Returning OK\n";
		return sf_apply_ssl($fileurl);
	}
	
	
	if($check) {
		$ch = curl_init($fileurl);
		curl_setopt($ch, CURLOPT_NOBODY, true);
		curl_setopt($ch, CURLOPT_HEADER, 1);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		$result = curl_exec($ch);
		if($result == false){
			if($sf_debug) {
				echo "sf_push: HEAD check failed: ".curl_error($ch).", will upload\n";
			}
			curl_close($ch);
		} else {
			curl_close($ch);
			if(substr($result, 9,3) == "200") {
				$sf_cache["known"][$fileurl] = true;
				$sf_cache["dirty"] = true;
				if($sf_debug) {
					echo "sf_push: Check returned OK, will not upload: ".str_replace("\r","",str_replace("\n","\\n",$result))."\n";
				}
				return sf_apply_ssl($fileurl);
			}
			if($sf_debug) {
				echo "sf_push: Check returned error, trying to upload: ".str_replace("\r","",str_replace("\n","\\n",$result))."\n";
			}
		}
	}
	
	if($sf_debug) { echo "sf_push: starting upload\n"; }
	
	$ch = curl_init($url);
	curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Length: ' . strlen($data)));
	curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
	curl_setopt($ch, CURLOPT_POSTFIELDS,$data);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	$result = curl_exec($ch);
	curl_close($ch);
	
	if($result) {
		if($sf_debug) {
			echo "sf_push: Upload OK - ".str_replace("\r","",str_replace("\n","\\n",$result))."\n";
		}
		$sf_cache["known"][$fileurl] = true;
		$sf_cache["dirty"] = true;
		return sf_apply_ssl($fileurl);
	} else {		
		if($sf_debug) {
			echo "sf_push: Upload failed: ".curl_error($ch)."\n";
		}
		return false;
	}
}







} // End.


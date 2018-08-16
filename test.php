<?php
include "sf.php";
$sf_debug = true;

//echo sf_format_hash(sf_hash(file_get_contents("/etc/fstab"))) . "\n";


//$test = "HdLCmjnwporxMDf1Vb9yI-Celz8yPLeHU2QhSjY0UiN";
//$fmt = sf_unformat_hash($test);
//echo "'$test'\n'" . sf_format_hash($fmt) . "'\n";

//echo "push result: '" . sf_push(file_get_contents("/etc/issue"),true,"http://my-cdn.at",true);

sf_cache_load("sf_cache.json");

echo "push result: '" . sf_push_file("/etc/fstab"). "'\n";
//print_r($sf_cache);
echo "push result: '" . sf_push_file("/etc/fstab"). "'\n";
echo "push result: '" . sf_push(file_get_contents("/etc/fstab")). "'\n";

sf_cache_save("sf_cache.json");


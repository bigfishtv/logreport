<?php

chdir(__DIR__);

$configFile = 'config.php';

// create config file on first run
if (!file_exists($configFile)) {
    copy($configFile . '.default', $configFile);
}

$config = require($configFile);

$defaults = array(
	'dir' => './',
	'match' => '/(error_log)|(\.log)$/i',
	'cacheFile' => './logreport.cache',
);

$config += $defaults;

// read cache
if (file_exists($config['cacheFile'])) {
	$cache = unserialize(file_get_contents($config['cacheFile']));
} else {
	$cache = array();
}

// start a fresh cache
$freshCache = array();

// find files
$directory = new RecursiveDirectoryIterator($config['dir']);
$flattened = new RecursiveIteratorIterator($directory);
$files = new RegexIterator($flattened, $config['match']);

// iterate through files
foreach ($files as $file) {
	$path = $file->getRealPath();
	$bytes = filesize($path);
	$cachedBytes = isset($cache[$path]) ? $cache[$path] : 0;
    // check if bytes have changed
    if ($cachedBytes != $bytes) {
    	// if file is smaller than what it was, then we want the entire file
    	if ($bytes < $cachedBytes) {
    		$cachedBytes = 0;
    	}
    	// open file and get contents
    	$handle = fopen($path, 'r');
    	fseek($handle, $cachedBytes);
    	$wantBytes = $bytes - $cachedBytes;
    	$contents = fread($handle, $wantBytes);
    	fclose($handle);
    	// push contents of file to output, showing '...'
    	// if it looks like we didn't get all the text
    	echo $path . PHP_EOL .
    		$contents . (strlen($contents) < ($wantBytes) ? '...' : '') .
    		PHP_EOL . PHP_EOL;
    }
    $freshCache[$path] = $bytes;
}

// write cache
file_put_contents($config['cacheFile'], serialize($freshCache));
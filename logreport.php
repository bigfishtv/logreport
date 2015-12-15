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

// script start time
$time = -microtime(true);

// find files
$directory = new RecursiveDirectoryIterator($config['dir']);
$flattened = new RecursiveIteratorIterator(new IgnoreFilterIterator($directory));
$files = new RegexIterator($flattened, $config['match']);

$fileChangedCount = 0;

// iterate through files
foreach ($files as $file) {
	$path = $file->getRealPath();
	$bytes = filesize($path);
	$cachedBytes = isset($cache[$path]) ? $cache[$path] : 0;
    // check if bytes have changed
    if ($cachedBytes != $bytes && $bytes > 0) {
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
    	echo decoratedPath($path) . PHP_EOL .
    		$contents . (strlen($contents) < ($wantBytes) ? '...' : '') .
    		PHP_EOL . PHP_EOL;
        $fileChangedCount++;
    }
    $freshCache[$path] = $bytes;
}

// write cache
file_put_contents($config['cacheFile'], serialize($freshCache));

// script end time only if log files changed
if ($fileChangedCount) {
    $time += microtime(true);
    echo decoratedPath('Execution duration: ' . sprintf('%f', $time));
}

function decoratedPath($path) {
    $line = str_repeat('-', strlen($path)) . PHP_EOL;
    return $line . $path . PHP_EOL . $line;
}

class IgnoreFilterIterator extends RecursiveFilterIterator {
    
    public static $FILTERS = array(
        '.svn',
        '.git',
        'node_modules',
        'vendor',
    );

    public function accept() {
        return !in_array(
            $this->current()->getFilename(),
            self::$FILTERS,
            true
        );
    }

}
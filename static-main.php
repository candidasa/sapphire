<?php
/**
 * This file is designed to be the new 'server' of sites using StaticPublisher.
 * to use this, you need to modify your .htaccess to point all requests to
 * static-main.php, rather than main.php. This file also allows for using
 * static publisher with the subsites module.
 *
 * If you are using StaticPublisher+Subsites, set the following in _config.php:
 *   FilesystemPublisher::$domain_based_caching = true;
 * and added main site host mapping in subsites/host-map.php after everytime a new subsite is created or modified 
 * 
 * If you are not using subsites, the host-map.php file will not exist (it is
 * automatically generated by the Subsites module) and the cache will default
 * to no subdirectory.
 */

$cacheEnabled = true;
$cacheDebug = false;
$cacheBaseDir = '../cache/'; // Should point to the same folder as FilesystemPublisher->destFolder

// Optional settings for FilesystemPublisher::$domain_based_mapping=TRUE
$hostmapLocation = '../subsites/host-map.php'; 
$homepageMapLocation = '../assets/_homepage-map.php';

if (
	$cacheEnabled 
	&& empty($_COOKIE['bypassStaticCache'])
	// No GET params other than cache relevant config is passed (e.g. "?stage=Stage"),
	// which would mean that we have to bypass the cache
	&& count(array_diff(array_keys($_GET), array('url', 'cacheSubdir'))) == 0
	// Request is not POST (which would have to be handled dynamically)
	&& count($_POST) == 0
) {
	// Define system paths (copied from Core.php)
	if(!defined('BASE_PATH')) {
		// Assuming that this file is sapphire/static-main.php we can then determine the base path
		define('BASE_PATH', rtrim(dirname(dirname(__FILE__))), DIRECTORY_SEPARATOR);
	}
	if(!defined('BASE_URL')) {
		// Determine the base URL by comparing SCRIPT_NAME to SCRIPT_FILENAME and getting common elements
		$path = realpath($_SERVER['SCRIPT_FILENAME']);
		if(substr($path, 0, strlen(BASE_PATH)) == BASE_PATH) {
			$urlSegmentToRemove = substr($path, strlen(BASE_PATH));
			if(substr($_SERVER['SCRIPT_NAME'], -strlen($urlSegmentToRemove)) == $urlSegmentToRemove) {
				$baseURL = substr($_SERVER['SCRIPT_NAME'], 0, -strlen($urlSegmentToRemove));
				define('BASE_URL', rtrim($baseURL, DIRECTORY_SEPARATOR));
			}
		}
	}
	
	$url = $_GET['url'];
	// Remove base folders from the URL if webroot is hosted in a subfolder
	if (substr(strtolower($url), 0, strlen(BASE_URL)) == strtolower(BASE_URL)) {
		$url = substr($url, strlen(BASE_URL));
	}
	
	$host = str_replace('www.', '', $_SERVER['HTTP_HOST']);
	
	// Custom cache dir for debugging purposes
	if (isset($_GET['cacheSubdir']) && !preg_match('/[^a-zA-Z0-9\-_]/', $_GET['cacheSubdir'])) {
		$cacheDir = $_GET['cacheSubdir'].'/';
	} 
	// Custom mapping through PHP file (assumed FilesystemPublisher::$domain_based_mapping=TRUE)
	else if (file_exists($hostmapLocation)) {
		include_once $hostmapLocation;
		$subsiteHostmap['default'] = isset($subsiteHostmap['default']) ? $subsiteHostmap['default'] : '';
		$cacheDir = (isset($subsiteHostmap[$host]) ? $subsiteHostmap[$host] : $subsiteHostmap['default']) . '/';
	}
	// No subfolder (for FilesystemPublisher::$domain_based_mapping=FALSE)
	else {
		$cacheDir = '';
	}

	// Look for the file in the cachedir
	$file = trim($url, '/');
	$file = $file ? $file : 'index';

	// Route to the 'correct' index file (if applicable)
	if ($file == 'index' && file_exists($homepageMapLocation)) {
		include_once $homepageMapLocation;
		$file = isset($homepageMap[$_SERVER['HTTP_HOST']]) ? $homepageMap[$_SERVER['HTTP_HOST']] : $file;
	}
	
	// Find file by extension (either *.html or *.php)
	$file = preg_replace('/[^a-zA-Z0-9\/\-_]/si', '-', $file);

	if (file_exists($cacheBaseDir . $cacheDir . $file . '.html')) {
		header('X-SilverStripe-Cache: hit at '.@date('r'));
		echo file_get_contents($cacheBaseDir . $cacheDir . $file . '.html');
		if ($cacheDebug) echo "<h1>File was cached</h1>";
	} elseif (file_exists($cacheBaseDir . $cacheDir . $file . '.php')) {
		header('X-SilverStripe-Cache: hit at '.@date('r'));
		include_once $cacheBaseDir . $cacheDir . $file . '.php';
		if ($cacheDebug) echo "<h1>File was cached</h1>";
	} else {
		header('X-SilverStripe-Cache: miss at '.@date('r') . ' on ' . $cacheDir . $file);
		// No cache hit... fallback to dynamic routing
		include 'main.php';
		if ($cacheDebug) echo "<h1>File was NOT cached</h1>";
	}
} else {
	// Fall back to dynamic generation via normal routing if caching has been explicitly disabled
	include 'main.php';
}

?>
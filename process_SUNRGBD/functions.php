<?php

// get single file from directory (most of our directories only contain one file)
function getFileFromDir($dir, $ext = '*') {
	$files = scandir($dir);
	foreach ($files as $fileName) {
		if ($fileName == '.' || $fileName == '..') { continue; }
		if ($ext == '*') {
			return $dir . $fileName;
		} else if (pathinfo($fileName, PATHINFO_EXTENSION) == $ext) {
			return $dir . $fileName;
		}
	}
	return false;
}

// annotations contain x and y coordinates in two arrays, we interlace them to create the format used in imagepolygon
function makePoly($obj) {
	$ret = array();
	
	$x = $obj['x'];
	$y = $obj['y'];
	
	for ($i = 0; $i < count($x); $i ++) {
		$ret[] = $x[$i];
		$ret[] = $y[$i];
	}
	
	return $ret;
}






?>
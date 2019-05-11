<?php

/* YOLO image label renderer */

/*
header('Content-type: text/plain');

if (isset($_GET['path'])) {
	echo file_get_contents(base64_decode($_GET['path']));
}
*/


/* WARNING: THIS FILE DOES NOT CONTAIN PATH SANITIZATION, ONLY USE ON LOCAL WEBSERVER! */

require_once("functions.php");


$dir = "SUNRGBD_data/obj";
$idx = intval($_GET['idx']) * 2; // txt and jpeg for each
if (!isset($_GET['path'])) {
	$files = scandir($dir . "/");
	
	$i = 0;
	foreach ($files as $fileName) {
		if ($fileName == '.' || $fileName == '..') { continue; }
		
		$p = $dir . "\\" . $fileName;
		if (!is_dir($p)) {
			if ($i == $idx) {
				$entityPath = explode(".", $p)[0];
				break;
			}
			$i ++;
		}
	}
} else {
	$entityPath = base64_decode($_GET['path']); // $dataEntities[rand(0, count($dataEntities))] . "\\";
}



$annotation = explode("\n", file_get_contents($entityPath . ".txt"));


header('Content-type: image/png', true);
$img = imagecreatefromjpeg($entityPath . ".jpg");
$col_poly = imagecolorallocate($img, 255, 0, 0);

foreach ($annotation as $obj) {
	$objData = explode(" ", $obj);
	if (count($objData) < 2) { continue; }
	$x = $objData[1] * imagesx($img);
	$y = $objData[2] * imagesy($img);
	$w = $objData[3] * imagesx($img);
	$h = $objData[4] * imagesy($img);
	$x1 = $x - $w / 2;
	$y1 = $y - $h / 2;
	
	$x2 = $x + $w / 2;
	$y2 = $y + $h / 2;
	$points = [$x1, $y1, $x2, $y1, $x2, $y2, $x1, $y2];
	
	imagepolygon($img, $points, 4, $col_poly);
	// 
}
imagestring($img, 5, 10, 10, $entityPath, $col_poly);

imagepng($img);
imagedestroy($img);






?>
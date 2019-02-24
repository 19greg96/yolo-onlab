<?php

/* SUNRGBD image label renderer */

/*
header('Content-type: text/plain');

if (isset($_GET['path'])) {
	echo file_get_contents(base64_decode($_GET['path']));
}
*/


/* WARNING: THIS FILE DOES NOT CONTAIN PATH SANITIZATION, ONLY USE ON LOCAL WEBSERVER! */

require_once("functions.php");

$entityPath = base64_decode($_GET['path']); // $dataEntities[rand(0, count($dataEntities))] . "\\";
$imagePath = $entityPath . "image\\";
$annotationPath = $entityPath . "annotation2D3D\\";

$annotation = json_decode(file_get_contents(getFileFromDir($annotationPath, 'json')), true);


header('Content-type: image/png', true);
$img = imagecreatefromjpeg(getFileFromDir($imagePath, 'jpg'));
$col_poly = imagecolorallocate($img, 255, 0, 0);

$objects = $annotation['frames'][0]['polygon'];
foreach ($objects as $obj) {
	$points = makePoly($obj);
	
	imagepolygon($img, $points, count($obj['x']), $col_poly);
	imagestring($img, 5, $obj['x'][0], $obj['y'][0], $annotation['objects'][$obj['object']]['name'], $col_poly);
}

imagepng($img);
imagedestroy($img);






?>
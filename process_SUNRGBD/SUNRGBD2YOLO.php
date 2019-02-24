<?php

/* SUNRGBD to YOLO converter */
/*

SUNRGBD from http://rgbd.cs.princeton.edu/

*/


require_once("functions.php");

set_time_limit(0);
//header('Content-type: text/plain');

$path = "E:\\projekts\\datasets\\SUNRGBD\\";

// Load entity paths
$paths = array();
$paths[] = $path;
$dataEntityPaths = array();
while (count($paths)) {
	$dir = array_pop($paths);
	$files = scandir($dir);
	
	if (in_array('scene.txt', $files, false)) {
		$dataEntityPaths[] = $dir;
		continue;
	}
	foreach ($files as $fileName) {
		if ($fileName == '.' || $fileName == '..') { continue; }
		
		$p = $dir . "\\" . $fileName;
		if (is_dir($p)) {
			$paths[] = $p;
			//echo $p . "\n";
		}
	}
}


echo "Num images: " . count($dataEntityPaths) . "<br/>";
$joinCategories = array();
$joinCategories['chair'] = array('chair', 'saucer_chair', 'chairs', 'sofa_chair', 'sofa', 'stool', 'bench', 'chair:occluded', 'chair:truncated', 'stack_of_chairs', 'sofa:truncated', 'sofa:occluded', 'child_chair', 'piano_bench', 'sofa-chair', 'pianobench', 'high_chair', 'baby_chair', 'recliner', 'lounge_chair', 'massage_chair', 'seat', 'couch', 'rocking_chair', 'chair:specail', 'chrar', 'chair\\', 'cvhair', 'chair_wirh_desk', 'ottoman'); // chair:bad_annotation, chr
$joinCategories['table'] = array('table', 'side_table', 'desk', 'coffee_table', 'table:truncated', 'dining_table', 'table:occluded', 'endtable', 'end_table', 'kitchen_counter', 'counter', 'cart', 'rack', 'podium', 'desk:truncated', 'desk:occluded', 'ebdtable', 'centertable', 'side_table:occluded', 'side_table:truncated', 'buffettable', 'long_office_table', 'bar_table', 'coffee-table', 'cooffee_table'); // sofa_table, deak
$joinCategories['cabinet/shelf'] = array('shelf', 'cabinet', 'bookshelf', 'night_stand', 'nightstand:truncated', 'drawer', 'dresser', 'dresser:truncated', 'dresser:occluded', 'kitchen_cabinet', 'file_cabinet', 'fridge', 'frige', 'mini_refrigerator', 'cubby', 'cupboard', 'nightstand:occluded', 'locker', 'magazine_rack', 'chest', 'bookstand', 'closet', 'hanging_cabinet', 'hangingcabinet', 'steelcabinet', 'lockers', 'drawers', 'mini_shelf', 'mini_drawers'); // bookahelf
$joinCategories['other'] = array('door', 'bed', 'box', 'monitor', 'garbage_bin', 'recycle_bin', 'computer', 'cpu', 'tv', 'toilet', 'printer', 'laptop', 'microwave', 'stove', 'bathtub', 'bed:truncated', 'tv_stand', 'plant');
$joinCategoryNames = array_keys($joinCategories);
$categoryThreshold = 100;
$displayNumExamples = 8;
$onlyDisplayUsedCategories = true;

$usedCategories = array('chair', 'table', 'cabinet/shelf', 'other', 'person');
$onlabCategories = array('chair', 'table', 'cabinet/shelf', 'animal', 'person', 'other');
$outputPath = 'data/';

function createYOLOAnnotationForEntity($i, $entityPath, $imgDir, $annotation, $joinCategories, $onlabCategories, $outputPath) {
	$joinCategoryNames = array_keys($joinCategories);
	$imagePath = false;
	if (file_exists($entityPath . 'fullres/')) {
		$imagePath = getFileFromDir($entityPath . 'fullres/', 'jpg');
	}
	if (!$imagePath) {
		$imagePath = getFileFromDir($imgDir, 'jpg');
	}
	/*
	<object-class> <x_center> <y_center> <width> <height>
	
	Where:
	<object-class> - integer object number from 0 to (classes-1)
	<x_center> <y_center> <width> <height> - float values relative to width and height of image, it can be equal from (0.0 to 1.0]
	
	*/
	//print_r($annotation);
	$annotationLines = '';
	if (isset($annotation['frames'][0]['polygon'])) {
		list($imageWidth, $imageHeight, $imageType, $imageAttr) = getimagesize($imagePath);
		$objects = $annotation['frames'][0]['polygon'];
		foreach ($objects as $obj) {
			$key = $annotation['objects'][$obj['object']]['name'];
			
			for ($j = 0; $j < count($joinCategories); $j++) {
				if (in_array($key, $joinCategories[$joinCategoryNames[$j]], true)) {
					$key = $joinCategoryNames[$j];
				}
			}
			
			//print_r($obj);
			
			$categoryIdx = array_search($key, $onlabCategories);
			if ($categoryIdx !== false) {
				$objectClass = $categoryIdx + 1;
				
				$x0 = min($obj['x']) / $imageWidth;
				$x1 = max($obj['x']) / $imageWidth;
				
				if ($x0 < 0) { $x0 = 0; }
				if ($x0 > 1) { $x0 = 1; }
				
				if ($x1 < 0) { $x1 = 0; }
				if ($x1 > 1) { $x1 = 1; }
				
				$y0 = min($obj['y']) / $imageHeight;
				$y1 = max($obj['y']) / $imageHeight;
				
				if ($y0 < 0) { $y0 = 0; }
				if ($y0 > 1) { $y0 = 1; }
				
				if ($y1 < 0) { $y1 = 0; }
				if ($y1 > 1) { $y1 = 1; }
				
				$xCenter = ($x0 + ($x1 - $x0) / 2);
				$yCenter = ($y0 + ($y1 - $y0) / 2);
				$width = ($x1 - $x0);
				$height = ($y1 - $y0);
				
				if ($width <= 0) { return; }
				if ($height <= 0) { return; }
				//assert($width > 0, 'width underflow - ' . base64_encode($entityPath) . ' - ' . $entityPath);
				//assert($height > 0, 'height underflow - ' . base64_encode($entityPath) . ' - ' . $entityPath);
				
				assert($xCenter > 0 && $xCenter <= 1, 'center x overflow - ' . base64_encode($entityPath) . ' - ' . $entityPath);
				assert($yCenter > 0 && $yCenter <= 1, 'center y overflow - ' . base64_encode($entityPath) . ' - ' . $entityPath);
				
				$annotationLines .= $objectClass . ' ' . $xCenter . ' ' . $yCenter . ' ' . $width . ' ' . $height . "\n";
			}
		}
	}
	file_put_contents($outputPath . 'img' . $i . '.txt', $annotationLines);
	copy($imagePath, $outputPath . 'img' . $i . '.jpg');
	// exit();
}

// Format data
$objectCategories = array();
$trainFileNames = '';
mkdir($outputPath);
mkdir($outputPath . 'obj/');
for ($i = 0; $i < count($dataEntityPaths); $i ++) {
	$entityPath = $dataEntityPaths[$i] . "\\";
	$imagePath = $entityPath . "image\\";
	$annotationPath = $entityPath . "annotation2D3D\\";
	if (!file_exists($annotationPath)) { continue; }
	if (getFileFromDir($annotationPath, 'json') === false) { continue; }
	
	$annotation = json_decode(file_get_contents(getFileFromDir($annotationPath, 'json')), true);
	createYOLOAnnotationForEntity($i, $entityPath, $imagePath, $annotation, $joinCategories, $onlabCategories, $outputPath . 'obj/');
	$trainFileNames .= $outputPath . 'obj/img' . $i . ".jpg\n";
	
	if (isset($annotation['frames'][0]['polygon'])) {
		$objects = $annotation['frames'][0]['polygon'];
		foreach ($objects as $obj) {
			$key = $annotation['objects'][$obj['object']]['name'];
			
			for ($j = 0; $j < count($joinCategories); $j++) {
				if (in_array($key, $joinCategories[$joinCategoryNames[$j]], true)) {
					$key = $joinCategoryNames[$j];
				}
			}
			
			if (!isset($objectCategories[$key])) {
				$objectCategories[$key] = array('entities' => array());
			}
			$objectCategories[$key]['entities'][] = array('entityPath' => $entityPath);
		}
	}
}
file_put_contents($outputPath . 'train.txt', $trainFileNames);

// Display example images
arsort($objectCategories);
foreach ($objectCategories as $catName => $category) {
	if ($onlyDisplayUsedCategories) {
		if (!in_array($catName, $usedCategories)) {
			continue;
		}
	}
	if (count($category['entities']) < $categoryThreshold) {
		continue;
	}
	echo '"' . $catName . '" count: ' . count($category['entities']) . ' ';
	for ($i = 0; $i < $displayNumExamples && $i < count($category['entities']); $i ++) {
		echo '<img width=200 src="get_image.php?path=' . base64_encode($category['entities'][rand(0, count($category['entities']) - 1)]['entityPath']) . '"/>';
	}
	echo '<br/>';
}

/*

Categories used from VOC:
8 - cat				-> animal
9 - chair			-> chair
10 - cow			-> animal
11 - diningtable	-> table
12 - dog			-> animal
13 - horse			-> animal
15 - person			-> person
16 - pottedplant	-> other
17 - sheep			-> animal
18 - sofa			-> chair
20 - tvmonitor		-> other

VOC categories:
1 - aeroplane
2 - bicycle
3 - bird
4 - boat
5 - bottle
6 - bus
7 - car
8 - cat
9 - chair
10 - cow
11 - diningtable
12 - dog
13 - horse
14 - motorbike
15 - person
16 - pottedplant
17 - sheep
18 - sofa
19 - train
20 - tvmonitor


Onlab categories:
1 - Chair
2 - Table
3 - Cabinet/Shelf
4 - Animal
5 - Person
6 - Other



*/







?>
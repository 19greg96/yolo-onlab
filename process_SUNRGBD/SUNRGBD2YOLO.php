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
// $joinCategories['chair'] = array('chair', 'saucer_chair', 'chairs', 'sofa_chair', 'sofa', 'stool', 'bench', 'chair:occluded', 'chair:truncated', 'stack_of_chairs', 'sofa:truncated', 'sofa:occluded', 'child_chair', 'piano_bench', 'sofa-chair', 'pianobench', 'high_chair', 'baby_chair', 'recliner', 'lounge_chair', 'massage_chair', 'seat', 'couch', 'rocking_chair', 'chair:specail', 'chrar', 'chair\\', 'cvhair', 'chair_wirh_desk', 'ottoman'); // chair:bad_annotation, chr
// $joinCategories['table'] = array('table', 'side_table', 'desk', 'coffee_table', 'table:truncated', 'dining_table', 'table:occluded', 'endtable', 'end_table', 'kitchen_counter', 'counter', 'cart', 'rack', 'podium', 'desk:truncated', 'desk:occluded', 'ebdtable', 'centertable', 'side_table:occluded', 'side_table:truncated', 'buffettable', 'long_office_table', 'bar_table', 'coffee-table', 'cooffee_table'); // sofa_table, deak
// $joinCategories['cabinet/shelf'] = array('shelf', 'cabinet', 'bookshelf', 'night_stand', 'nightstand:truncated', 'drawer', 'dresser', 'dresser:truncated', 'dresser:occluded', 'kitchen_cabinet', 'file_cabinet', 'fridge', 'frige', 'mini_refrigerator', 'cubby', 'cupboard', 'nightstand:occluded', 'locker', 'magazine_rack', 'chest', 'bookstand', 'closet', 'hanging_cabinet', 'hangingcabinet', 'steelcabinet', 'lockers', 'drawers', 'mini_shelf', 'mini_drawers'); // bookahelf
// $joinCategories['other'] = array('door', 'bed', 'box', 'monitor', 'garbage_bin', 'recycle_bin', 'computer', 'cpu', 'tv', 'toilet', 'printer', 'laptop', 'microwave', 'stove', 'bathtub', 'bed:truncated', 'tv_stand', 'plant');
$joinCategoryNames = array_keys($joinCategories);
$allUnknownIsOther = true; // when true, every object with unknown category is assigned other. When false, these entries are discarded.

$categoryThreshold = 100;
$displayNumExamples = 8;
$onlyDisplayUsedCategories = true;
$trainSplit = 0.9;

$usedCategories = array('chair', 'table', 'cabinet/shelf', 'other', 'person');
$onlabCategories = array('chair', 'table', 'cabinet/shelf', 'animal', 'person', 'other');
$outputPath = 'SUNRGBD_data/';

function createYOLOAnnotationForEntity($i, $entityPath, $imgDir, $annotation, $joinCategories, $onlabCategories, $outputPath, $allUnknownIsOther) {
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
			if ($categoryIdx === false && $allUnknownIsOther) {
				$categoryIdx = array_search('other', $onlabCategories);
			}
			if ($categoryIdx !== false) {
				
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
				
				if ($width <= 0) { return false; }
				if ($height <= 0) { return false; }
				//assert($width > 0, 'width underflow - ' . base64_encode($entityPath) . ' - ' . $entityPath);
				//assert($height > 0, 'height underflow - ' . base64_encode($entityPath) . ' - ' . $entityPath);
				
				assert($xCenter > 0 && $xCenter <= 1, 'center x overflow - ' . base64_encode($entityPath) . ' - ' . $entityPath);
				assert($yCenter > 0 && $yCenter <= 1, 'center y overflow - ' . base64_encode($entityPath) . ' - ' . $entityPath);
				
				$annotationLines .= $categoryIdx . ' ' . $xCenter . ' ' . $yCenter . ' ' . $width . ' ' . $height . "\n";
			}
		}
	}
	file_put_contents($outputPath . 'img' . $i . '.txt', $annotationLines);
	copy($imagePath, $outputPath . 'img' . $i . '.jpg');
	// exit();
	return true;
}

// Format data
$objectCategories = array();
$trainFileNames = '';
$testFileNames = '';
$numTrainFiles = round(count($dataEntityPaths) * $trainSplit);
mkdir($outputPath);
mkdir($outputPath . 'obj/');
shuffle($dataEntityPaths);
for ($i = 0; $i < count($dataEntityPaths); $i ++) {
	$entityPath = $dataEntityPaths[$i] . "\\";
	$imagePath = $entityPath . "image\\";
	$annotationPath = $entityPath . "annotation2D3D\\";
	if (!file_exists($annotationPath)) { continue; }
	if (getFileFromDir($annotationPath, 'json') === false) { continue; }
	
	$annotation = json_decode(file_get_contents(getFileFromDir($annotationPath, 'json')), true);
	$fileCreateOK = createYOLOAnnotationForEntity($i, $entityPath, $imagePath, $annotation, $joinCategories, $onlabCategories, $outputPath . 'obj/', $allUnknownIsOther);
	if (!$fileCreateOK) {
		continue;
	}
	if ($i < $numTrainFiles) {
		$trainFileNames .= $outputPath . 'obj/img' . $i . ".jpg\n";
	} else {
		$testFileNames .= $outputPath . 'obj/img' . $i . ".jpg\n";
	}
	
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
file_put_contents($outputPath . 'train_onlab.txt', $trainFileNames);
file_put_contents($outputPath . 'test_onlab.txt', $testFileNames);

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
7 - cat				-> animal
8 - chair			-> chair
9 - cow			-> animal
10 - diningtable	-> table
11 - dog			-> animal
12 - horse			-> animal
14 - person			-> person
15 - pottedplant	-> other
16 - sheep			-> animal
17 - sofa			-> chair
19 - tvmonitor		-> other

VOC categories:
0 - aeroplane
1 - bicycle
2 - bird
3 - boat
4 - bottle
5 - bus
6 - car
7 - cat
8 - chair
9 - cow
10 - diningtable
11 - dog
12 - horse
13 - motorbike
14 - person
15 - pottedplant
16 - sheep
17 - sofa
18 - train
19 - tvmonitor


Onlab categories:
0 - Chair
1 - Table
2 - Cabinet/Shelf
3 - Animal
4 - Person
5 - Other




*/







?>
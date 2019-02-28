<?php

set_time_limit(0);
//header('Content-type: text/plain');
header( 'Content-type: text/html; charset=utf-8' );

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


$path_VOC = "E:\\projekts\\datasets\\VOC\\voc\\";
$path_SUNRGBD = "SUNRGBD_data/";
$outputPath = "data/";

mkdir($outputPath);
mkdir($outputPath . 'obj/');

$addOtherCategory = true; // when false, ignores all objects with label "Other"
$VOC_to_ONLAB_categories = array();
$VOC_to_ONLAB_categories[0] = 5; // 0 - aeroplane		=>		5 - other
$VOC_to_ONLAB_categories[1] = 5; // 1 - bicycle			=>		5 - other
$VOC_to_ONLAB_categories[2] = 5; // 2 - bird			=>		5 - other
$VOC_to_ONLAB_categories[3] = 5; // 3 - boat			=>		5 - other
$VOC_to_ONLAB_categories[4] = 5; // 4 - bottle			=>		5 - other
$VOC_to_ONLAB_categories[5] = 5; // 5 - bus				=>		5 - other
$VOC_to_ONLAB_categories[6] = 5; // 6 - car				=>		5 - other
$VOC_to_ONLAB_categories[7] = 3; // 7 - cat				=>		3 - Animal
$VOC_to_ONLAB_categories[8] = 0; // 8 - chair			=>		0 - Chair
$VOC_to_ONLAB_categories[9] = 3; // 9 - cow				=>		3 - Animal
$VOC_to_ONLAB_categories[10] = 1; // 10 - diningtable	=>		1 - Table
$VOC_to_ONLAB_categories[11] = 3; // 11 - dog			=>		3 - Animal
$VOC_to_ONLAB_categories[12] = 3; // 12 - horse			=>		3 - Animal
$VOC_to_ONLAB_categories[13] = 5; // 13 - motorbike		=>		5 - other
$VOC_to_ONLAB_categories[14] = 4; // 14 - person		=>		4 - Person
$VOC_to_ONLAB_categories[15] = 5; // 15 - pottedplant	=>		5 - other
$VOC_to_ONLAB_categories[16] = 3; // 16 - sheep			=>		3 - Animal
$VOC_to_ONLAB_categories[17] = 0; // 17 - sofa			=>		0 - Chair
$VOC_to_ONLAB_categories[18] = 5; // 18 - train			=>		5 - other
$VOC_to_ONLAB_categories[19] = 5; // 19 - tvmonitor		=>		5 - other

$trainSplit = 0.95;


$test_voc = explode("\n", file_get_contents($path_VOC . 'test_voc.txt'));
$train_voc = explode("\n", file_get_contents($path_VOC . 'train_voc.txt'));
$voc_data = array_merge($test_voc, $train_voc);
$numData = count($voc_data);
echo "Num voc data: " . $numData . "<br/>";

$nExistingFiles = 0;
$trainFileNames = array();
$testFileNames = array();
$numTrainFiles = round($numData * $trainSplit);
shuffle($voc_data);
for ($i = 0; $i < $numData; $i ++) {
	$imagePath = $voc_data[$i];
	if (!strlen($imagePath)) {
		continue;
	}
	$imageName = end(explode("/", $imagePath));
	$imageBaseName = explode(".", $imageName);
	$imageBaseName = $imageBaseName[0];
	$imagePath = $path_VOC . "obj\\" . $imageName;
	if (file_exists($imagePath)) {
		$imageBasePath = explode(".", $imagePath);
		$imageBasePath = $imageBasePath[0];
		$imageLabels = explode("\n", file_get_contents($imageBasePath . ".txt"));
		$newLabels = "";
		foreach ($imageLabels as $objTxt) {
			if (!strlen($objTxt)) {
				continue;
			}
			$obj = explode(" ", $objTxt);
			$oldCategoryIdx = intval(array_shift($obj)); // category Idx is first column, remove it from obj, because we will be adding new cat idx.
			$newCategoryIdx = $VOC_to_ONLAB_categories[$oldCategoryIdx];
			if ($newCategoryIdx == 5 && !$addOtherCategory) { // new category would be other, but we don't want to add those
				continue;
			}
			$newLabels .= $newCategoryIdx . ' ' . implode(' ', $obj) . "\n";
		}
		file_put_contents($outputPath . 'obj/voc_' . $imageBaseName . '.txt', $newLabels);
		copy($imagePath, $outputPath . 'obj/voc_' . $imageBaseName . '.jpg');
		
		if ($i < $numTrainFiles) {
			$trainFileNames[] = $outputPath . 'obj/voc_' . $imageBaseName . ".jpg";
		} else {
			$testFileNames[] = $outputPath . 'obj/voc_' . $imageBaseName . ".jpg";
		}
		
		$nExistingFiles ++;
	} else {
		echo "Not found: " . $imagePath . "<br/>";
	}
	
	if (($i & 127) === 127) {
		echo $i . ' / ' . $numData . "<br/>";
		flush();
		ob_flush();
	}
}
echo "Num existing images: " . $nExistingFiles . "<br/>";


$test_SUNRGBD = explode("\n", file_get_contents($path_SUNRGBD . 'test_onlab.txt'));
$train_SUNRGBD = explode("\n", file_get_contents($path_SUNRGBD . 'train_onlab.txt'));
$SUNRGBD_data = array_merge($test_SUNRGBD, $train_SUNRGBD);
$numData = count($SUNRGBD_data);

echo "Num SUNRGBD data: " . $numData . "<br/>";
$numTrainFiles = round($numData * $trainSplit);
shuffle($SUNRGBD_data);
for ($i = 0; $i < $numData; $i ++) {
	$imagePath = $SUNRGBD_data[$i];
	if (!strlen($imagePath)) {
		continue;
	}
	$imageName = end(explode("/", $imagePath));
	$imageBaseName = explode(".", $imageName);
	$imageBaseName = $imageBaseName[0];
	$imagePath = $path_SUNRGBD . "obj\\" . $imageName;
	if (file_exists($imagePath)) {
		$imageBasePath = explode(".", $imagePath);
		$imageBasePath = $imageBasePath[0];
		
		if ($i < $numTrainFiles) {
			$trainFileNames[] = $outputPath . 'obj/SUNRGBD_' . $imageBaseName . ".jpg";
		} else {
			$testFileNames[] = $outputPath . 'obj/SUNRGBD_' . $imageBaseName . ".jpg";
		}
		copy($imageBasePath . ".txt", $outputPath . 'obj/SUNRGBD_' . $imageBaseName . '.txt');
		copy($imagePath, $outputPath . 'obj/SUNRGBD_' . $imageBaseName . '.jpg');
	} else {
		echo "Not found: " . $imagePath . "<br/>";
	}
	
	if (($i & 127) === 127) {
		echo $i . ' / ' . $numData . "<br/>";
		flush();
		ob_flush();
	}
}



shuffle($trainFileNames);
shuffle($testFileNames);
file_put_contents($outputPath . 'train_onlab.txt', implode("\n", $trainFileNames));
file_put_contents($outputPath . 'test_onlab.txt', implode("\n", $testFileNames));



?>
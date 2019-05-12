<?php

$dir = "VOC_SUNRGBD_4/obj";
$idx = intval($_GET['idx']) * 2; // txt and jpeg for each
$paths = array();
if (!isset($_GET['path'])) {
	$files = scandir($dir . "/");
	
	$i = 0;
	foreach ($files as $fileName) {
		if ($fileName == '.' || $fileName == '..') { continue; }
		
		$p = $dir . "\\" . $fileName;
		if (!is_dir($p)) {
			if ($i > $idx && ($i & 1)) {
				$paths[] = explode(".", $p)[0];
				if ($i > $idx + 100) {
					break;
				}
			}
			$i ++;
		}
	}
}

for ($i = 0; $i < 40; $i ++) {
	?><img src="http://localhost/onlab/onlab/process_SUNRGBD/get_image_yolo.php?idx=0&path=<?php echo base64_encode($paths[$i]); ?>" /><?php
}


?>
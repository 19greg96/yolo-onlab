
rem darknet.exe detector test data/voc.data cfg/yolov2-voc.cfg yolo-voc.weights 009460.jpg


darknet.exe detector test cfg/onlab_nocat.data cfg/yolo3-tiny-onlab_nocat_test.cfg backup/yolo3-tiny-onlab_nocat_8000.weights -i 0 -thresh 0.24 test_office.jpg


rem Visual studio command arguments:
 detector test cfg/onlab_nocat.data cfg/yolo3-tiny-onlab_nocat_test.cfg backup/yolo3-tiny-onlab_nocat_8000.weights -i 0 -thresh 0.24 test_office2.jpg



pause
#include "stdafx.h"

#include <fstream>
#include <iostream>
#include <cstdlib>
#include <math.h>
#include "imagefactory.h"

using namespace cv;
using namespace cv::dnn;
using namespace std;

String modelConfiguration = "MobileNetSSD_test.prototxt";
String modelBinary = "MobileNetSSD_test.caffemodel";
dnn::Net net;

String outPath = "";
Size inVideoSize = Size(WIDTH, HEIGHT);
Size cropSize;
float min_confidence = 0.7;
const size_t inWidth = 300;
const size_t inHeight = 300;
const float WHRatio = inWidth / (float)inHeight;
const float inScaleFactor = 0.007843f;
const float meanVal = 127.5;

const char* classNames[] = { "background",
"aeroplane", "bicycle", "bird", "boat",
"bottle", "bus", "car", "cat", "chair",
"cow", "diningtable", "dog", "horse",
"motorbike", "person", "pottedplant",
"sheep", "sofa", "train", "tvmonitor" };

Size labelSize;
String label; // label in the rect of recognition
static vector<String> labels; // store all labels

int numObject; // object number of recognition

// declearition of functions
void setObjectNums(int);
void setLabels(vector<String>);

void ImageFactory::NetInit()
{
	net = readNetFromCaffe(modelConfiguration, modelBinary);

	net.setPreferableTarget(DNN_TARGET_CPU);
	if (inVideoSize.width / (float)inVideoSize.height > WHRatio)
	{
		cropSize = Size(static_cast<int>(inVideoSize.height * WHRatio),
			inVideoSize.height);
	}
	else
	{
		cropSize = Size(inVideoSize.width,
			static_cast<int>(inVideoSize.width / WHRatio));
	}
}

vector<vector<int>> ImageFactory::recognition(Mat frame)
{
	Rect crop(Point((inVideoSize.width - cropSize.width) / 2,
		(inVideoSize.height - cropSize.height) / 2),
		cropSize);

	//! [Prepare blob]
	Mat inputBlob = blobFromImage(frame, inScaleFactor,
		Size(inWidth, inHeight), meanVal, false); //Convert Mat to batch of images
													//! [Prepare blob]
													//! [Set input blob]
	net.setInput(inputBlob, "data"); //set the network input
										//! [Set input blob]

										//! [Make forward pass]
	Mat detection = net.forward("detection_out"); //compute output
													//! [Make forward pass]

	vector<double> layersTimings;
	double freq = getTickFrequency() / 1000;
	double time = net.getPerfProfile(layersTimings) / freq;

	Mat detectionMat(detection.size[2], detection.size[3], CV_32F, detection.ptr<float>());

	vector<vector<int>> pos(detectionMat.rows, vector<int>(4));
	vector<String> labels(detectionMat.rows);

	setObjectNums(detectionMat.rows);

	frame = frame(crop);

	ostringstream ss;	

	// print information
	/*
	ss << "FPS: " << 1000 / time << " ; time: " << time << " ms";
	putText(frame, ss.str(), Point(20, 20), 0, 0.5, Scalar(0, 0, 255));
	cout << "Inference time, ms: " << time << endl;
	*/
	float confidenceThreshold = min_confidence;

	for (int i = 0; i < detectionMat.rows; i++)
	{
		float confidence = detectionMat.at<float>(i, 2);

		if (confidence > confidenceThreshold)
		{
			size_t objectClass = (size_t)(detectionMat.at<float>(i, 1));

			int xLeftBottom = static_cast<int>(detectionMat.at<float>(i, 3) * frame.cols);
			int yLeftBottom = static_cast<int>(detectionMat.at<float>(i, 4) * frame.rows);
			int xRightTop = static_cast<int>(detectionMat.at<float>(i, 5) * frame.cols);
			int yRightTop = static_cast<int>(detectionMat.at<float>(i, 6) * frame.rows);

			pos[i][0] = xLeftBottom;
			pos[i][1] = yLeftBottom;
			pos[i][2] = xRightTop;
			pos[i][3] = yRightTop;

			ss.str("");
			ss << confidence;
			String conf(ss.str());

			/*Rect object((int)xLeftBottom, (int)yLeftBottom,
				(int)(xRightTop - xLeftBottom),
				(int)(yRightTop - yLeftBottom));
			rectangle(frame, object, Scalar(0, 255, 0));*/
			label = String(classNames[objectClass]) + ": " + conf;
			
			labels[i] = label;
			/*
			int baseLine = 0;
			Size labelSize = getTextSize(label, FONT_HERSHEY_SIMPLEX, 0.5, 1, &baseLine);

			rectangle(frame, Rect(Point(xLeftBottom, yLeftBottom - labelSize.height),
				Size(labelSize.width, labelSize.height + baseLine)),
				Scalar(255, 255, 255), CV_FILLED);
			putText(frame, label, Point(xLeftBottom, yLeftBottom),
				FONT_HERSHEY_SIMPLEX, 0.5, Scalar(0, 0, 0));*/
		}
	}
	setLabels(labels);
	return pos;
	
}

void setObjectNums(int a)
{
	numObject = a;
}
int ImageFactory::getObjectNums()
{
	return numObject;
}
void setLabels(vector<String> a)
{
	labels = a;
}
vector<String> ImageFactory::getLabels()
{
	return labels;
}
int ImageFactory::isDangerous(Point center, Point s, int d)
{
	int dangerRadius = abs(center.x - s.x);
	if (dangerRadius < d)
	{
		return 1;
	}
	else return 0;
}
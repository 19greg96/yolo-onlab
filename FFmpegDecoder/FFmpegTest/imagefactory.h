#pragma once
#include "IncludeOpencv.h"

using namespace cv;
using namespace std;
class ImageFactory
{
public:
	
	void NetInit();
	vector<vector<int>> recognition(Mat);

	int getObjectNums();
	vector<String> getLabels();

	int isDangerous(Point, Point, int);
};
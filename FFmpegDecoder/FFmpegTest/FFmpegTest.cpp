#include "stdafx.h"
#include <stdio.h>
#include <math.h>
#include "ForwardPicToAnotherPort.cpp"
#include <omp.h>
#include <thread>
#include <Windows.h>
#include "imagefactory.h"

extern "C" {
#define HAVE_STRUCT_TIMESPEC
#include "darknet.h"
}

using namespace std;
using namespace cv;

char bitmap[WIDTH * HEIGHT * CHANNELS];

typedef struct {
	String name;
	box boundingBox;
	float probability;
	int objectClass;
} namedDetection;

typedef struct {
	namedDetection current;
	namedDetection previous;
	float iou;
} namedDetectionPair;

bool sortNamedDetections(namedDetection& a, namedDetection& b) {
	return (a.probability < b.probability);
}
bool sortNamedDetectionPairs(namedDetectionPair& a, namedDetectionPair& b) {
	return (a.iou < b.iou);
}

static bool isDetecting = 0;
static int framesPerDetection = 1;
static int numBytes; // frame buffer size
// Use double buffer to store matrix data, so we can call dll functions whenever we want
// idk if this is necessarry
static Mat* currentMatrix;
static Mat aMatrix;
static Mat bMatrix;

// Darknet (yolo) variables
network *netMain;
metadata metaMain;
image darknet_image;
float detection_threshold = 0.1f; // to filter detections from nn
float display_threshold = 0.2f; // 20% to filter processed detections
float pair_IOU_threashold = 0.5f; // minimum IOU between two detections on two frames to consider detection.
float hier_thresh = 0.5f; // 50%
float nms = 0.45f; // https://arxiv.org/abs/1704.04503

extern "C" {
	__declspec(dllexport) int GetBufferSize() {
		return numBytes;
	}
	__declspec(dllexport) char* GetFrameBuffer() {
		char* buff = (char*)CoTaskMemAlloc(numBytes);
		memcpy(buff, (*currentMatrix).data, numBytes);
		return buff;
	}
}

int d = 80; // dangers region

// From box.h
float overlap(float x1, float w1, float x2, float w2)
{
	float l1 = x1 - w1 / 2;
	float l2 = x2 - w2 / 2;
	float left = l1 > l2 ? l1 : l2;
	float r1 = x1 + w1 / 2;
	float r2 = x2 + w2 / 2;
	float right = r1 < r2 ? r1 : r2;
	return right - left;
}

box box_avarage(box a, box b)
{
	box ret;
	ret.x = (a.x + b.x) / 2;
	ret.y = (a.y + b.y) / 2;
	ret.w = (a.w + b.w) / 2;
	ret.h = (a.h + b.h) / 2;
	return ret;
}

float box_intersection(box a, box b)
{
	float w = overlap(a.x, a.w, b.x, b.w);
	float h = overlap(a.y, a.h, b.y, b.h);
	if (w < 0 || h < 0) return 0;
	float area = w * h;
	return area;
}

float box_union(box a, box b)
{
	float i = box_intersection(a, b);
	float u = a.w*a.h + b.w*b.h - i;
	return u;
}

float box_iou(box a, box b)
{
	return box_intersection(a, b) / box_union(a, b);
}
// end from box.h

vector<namedDetection> curr_frame_detections;
vector<namedDetection> last_frame_detections;
// draw recognition result
void recogFrame(Mat &frame)
{
	isDetecting = 1;
	vector<namedDetection> tmpDets;
	
	// frame_rgb = cv2.cvtColor(frame_read, cv2.COLOR_BGR2RGB)
	//frame_resized = resize(frame, (network_width(netMain), network_height(netMain)), INTER_LINEAR);
	Mat frame_resized(network_height(netMain), network_width(netMain), CV_8UC3, bitmap);
	resize(frame, frame_resized, frame_resized.size(), 0, 0, INTER_LINEAR);
	

	// pos = imf.recognition(frame);
	copy_image_from_bytes(darknet_image, (char *)frame_resized.data);
	// detections = detect_image(netMain, metaMain, darknet_image, 0.25);
	network_predict_image(netMain, darknet_image);
	int numDetections;
	detection *dets = get_network_boxes(netMain, darknet_image.w, darknet_image.h, detection_threshold, hier_thresh, NULL, 0, &numDetections, 0);

	do_nms_sort(dets, numDetections, metaMain.classes, nms);

	namedDetection d;
	for (int i = 0; i < numDetections; i ++) {
		d.name = "";
		d.probability = dets[i].prob[0]; // highest probability
		d.objectClass = 0; // TODO: the cycle below doesn't filter based on probability
		for (int j = 0; j < metaMain.classes; j++) {
			d.boundingBox = dets[i].bbox;
			d.name += std::to_string(dets[i].prob[j] * 100) + "%, ";//metaMain.names[j];
			tmpDets.push_back(d);
		}
	}
	std::sort(tmpDets.begin(), tmpDets.end(), sortNamedDetections);
	/*
	detection *get_network_boxes(network *net, int w, int h, float thresh, float hier, int *map, int relative, int *num, int letter)
	void free_detections(detection *dets, int n)
	*/

	free_detections(dets, numDetections);
	
	//int num = imf.getObjectNums();

	//labels = imf.getLabels();
	curr_frame_detections = tmpDets;
	isDetecting = 0;
}
void drawFrame(Mat &frame)
{
	
	int x, y, w, h;
	vector<namedDetection> show_detections;
	// show_detections = curr_frame_detections; // working: only show current frame detections
	
	// work in progress: evaluate two frames of data
	vector<namedDetectionPair> detection_pairs;
	for (int i = 0; i < curr_frame_detections.size(); i ++) {
		for (int j = 0; j < last_frame_detections.size(); j++) {
			float pairIOU = box_iou(curr_frame_detections[i].boundingBox, last_frame_detections[j].boundingBox);
			if (pairIOU > pair_IOU_threashold343333333333333333333333333zu) {
				namedDetectionPair currPair;
				currPair.current = curr_frame_detections[i];
				currPair.previous = last_frame_detections[j];
				currPair.iou = pairIOU;
				detection_pairs.push_back(currPair);
			}
		}
	}
	std::sort(detection_pairs.begin(), detection_pairs.end(), sortNamedDetectionPairs);
	show_detections.clear();
	for (int i = 0; i < detection_pairs.size(); i ++) {
		float avgProbability = (detection_pairs[i].current.probability + detection_pairs[i].previous.probability) / 2.0f;
		namedDetection tmp;

		tmp.probability = avgProbability;
		tmp.name = detection_pairs[i].current.name + detection_pairs[i].previous.name;
		tmp.boundingBox = box_avarage(detection_pairs[i].current.boundingBox, detection_pairs[i].previous.boundingBox);

		show_detections.push_back(tmp);
	}


	
	for (int i = 0; i < show_detections.size(); i++) {
		if (show_detections[i].probability > display_threshold) {
			w = show_detections[i].boundingBox.w * (float)WIDTH / (float)network_width(netMain);
			h = show_detections[i].boundingBox.h * (float)HEIGHT / (float)network_height(netMain);

			x = (show_detections[i].boundingBox.x - show_detections[i].boundingBox.w / 2.0f) * (float)WIDTH / (float)network_width(netMain);
			y = (show_detections[i].boundingBox.y - show_detections[i].boundingBox.h / 2.0f) * (float)HEIGHT / (float)network_height(netMain);

			Rect object(x, y, w, h);

			rectangle(frame, object, Scalar(0, 255, 0, 0.5), 1);

			int labelTextHeight = 0;
			String label = show_detections[i].name; // std::to_string(detections[i].probability * 100) + "% " + 
			Size labelSize = getTextSize(label, FONT_HERSHEY_SIMPLEX, 1, 1, &labelTextHeight);

			rectangle(frame, Rect(Point(x, y - labelSize.height), Size(labelSize.width, labelSize.height + labelTextHeight)), Scalar(255, 255, 255), CV_FILLED);
			putText(frame, label, Point(x, y), FONT_HERSHEY_SIMPLEX, 1, Scalar(0, 0, 0), 2);
		}
	}

	last_frame_detections = curr_frame_detections; // or
	// last_frame_detections = show_detections;
}


int main() {
	// netMain = darknet.load_net_custom(configPath.encode("ascii"), weightPath.encode("ascii"), 0, 1)
	//netMain = load_network_custom("../yolo-onlab-master/darknet-master/cfg/yolo3-tiny-onlab_nocat_test.cfg", "../yolo-onlab-master/darknet-master/backup/yolo3-tiny-onlab_nocat_8000.weights", 0, 1);
	//metaMain = get_metadata("../yolo-onlab-master/darknet-master/cfg/onlab_nocat.data");

	netMain = load_network_custom("../yolo-onlab-master/darknet-master/cfg/yolo3-tiny-onlab_nocat_test.cfg", "../yolo-onlab-master/darknet-master/backup/yolo3-tiny-onlab_nocat_8000.weights", 0, 1);
	metaMain = get_metadata("../yolo-onlab-master/darknet-master/cfg/onlab_nocat.data");


	// TODO: object names
	// Create an image we reuse for each detect:
	darknet_image = make_image(network_width(netMain), network_height(netMain), 3);



	// Path of the video file or url of video stream
	//const char path[] = "tcp://192.168.0.199:8080?listen";
	const char path[] = "tcp://192.168.0.199:8080?listen";
	//const char path[] = "tcp://192.168.157.17:8888?listen";
	//char path[] = "udp://192.168.137.1:8888/test.mjpeg";
	//char path[] = "C://Users//bmw/zrb//SA//FFmpegTest//FFmpegTest//vtest.mp4";
	// Initalizing these to NULL prevents segfaults!
	AVFormatContext   *pFormatCtx = NULL;
	int               i, videoStream, numFrames;
	AVCodecContext    *pCodecCtxOrig = NULL;
	AVCodecContext    *pCodecCtx = NULL;
	AVCodec           *pCodec = NULL;
	AVFrame           *pFrame = NULL;
	AVFrame           *pFrameRGB = NULL;
	AVPacket          packet;
	int               frameFinished;
	uint8_t           *buffer = NULL;
	struct SwsContext *sws_ctx = NULL;

	// Register all formats and codecs
	av_register_all();
	avformat_network_init();
	frameFinished = 0;
	// Open video file
	if (avformat_open_input(&pFormatCtx, path, NULL, NULL) != 0) {
		cout << "Could not open file" << endl;
		return -1; // Couldn't open file
	}

	// Retrieve stream information
	if (avformat_find_stream_info(pFormatCtx, NULL) < 0) {
		cout << "Could not find stream info" << endl;
		return -1; // Couldn't find stream information
	}

	// Dump information about file onto standard error
	av_dump_format(pFormatCtx, 0, path, 0);


	// Find the first video stream
	videoStream = -1;
	for (i = 0; i < pFormatCtx->nb_streams; i++)
		if (pFormatCtx->streams[i]->codecpar->codec_type == AVMEDIA_TYPE_VIDEO) {
			videoStream = i;
			break;
		}
	if (videoStream == -1) {
		cout << "Could not find a video stream" << endl;
		return -1; // Didn't find a video stream
	}

	// Get a pointer to the codec context for the video stream
	pCodecCtx = avcodec_alloc_context3(NULL);
	avcodec_parameters_to_context(pCodecCtx, pFormatCtx->streams[videoStream]->codecpar);


	// Find the decoder for the video stream
	pCodec = avcodec_find_decoder(pCodecCtx->codec_id);
	if (pCodec == NULL) {
		fprintf(stderr, "Unsupported codec!\n");
		return -1; // Codec not found
	}

	// Open codec
	if (avcodec_open2(pCodecCtx, pCodec, NULL) < 0)
		return -1; // Could not open codec

				   // Allocate video frame
	pFrame = av_frame_alloc();

	// Allocate an AVFrame structure
	pFrameRGB = av_frame_alloc();
	if (pFrameRGB == NULL)
		return -1;
	// Determine required buffer size and allocate buffer
	numBytes = av_image_get_buffer_size(AV_PIX_FMT_RGB24, pCodecCtx->width, pCodecCtx->height, 1);
	buffer = (uint8_t *)av_malloc(numBytes * sizeof(uint8_t));

	// Assign appropriate parts of buffer to image planes in pFrameRGB
	// Note that pFrameRGB is an AVFrame, but AVFrame is a superset
	// of AVPicture

	av_image_fill_arrays(pFrameRGB->data, pFrameRGB->linesize, buffer, AV_PIX_FMT_RGB24, pCodecCtx->width, pCodecCtx->height, 1);

	// initialize SWS context for software scaling
	sws_ctx = sws_getContext(pCodecCtx->width,
		pCodecCtx->height,
		pCodecCtx->pix_fmt,
		pCodecCtx->width,
		pCodecCtx->height,
		AV_PIX_FMT_RGB24,
		SWS_BILINEAR,
		NULL,
		NULL,
		NULL
	);

	ForwardPicToAnotherPort fptap;
	// initialize the connection to Server
	fptap.init_Socket();

	i = 0;
	numFrames = 0;

	while (av_read_frame(pFormatCtx, &packet) >= 0) {

		// Is this a packet from the video stream?
		if (packet.stream_index == videoStream) {
			// Decode video frame
			//avcodec_decode_video2(pCodecCtx, pFrame, &frameFinished, &packet);
			avcodec_send_packet(pCodecCtx, &packet);
			frameFinished = avcodec_receive_frame(pCodecCtx, pFrame);

			// Did we get a video frame?
			if (!frameFinished) {
				i++;
				numFrames++;
				// Convert the image from its native format to RGB
				sws_scale(sws_ctx, (uint8_t const * const *)pFrame->data,
					pFrame->linesize, 0, pCodecCtx->height,
					pFrameRGB->data, pFrameRGB->linesize);
				
				memcpy(bitmap, pFrameRGB->data[0], WIDTH * HEIGHT * CHANNELS);

				Mat outgoingImageMat;
				Mat incomingImageMat(pCodecCtx->height, pCodecCtx->width, CV_8UC3, bitmap);
				// image recognition

				Mat rotatedImageMat;
				cv::rotate(incomingImageMat, rotatedImageMat, ROTATE_180);
				/*
				Mat frame_rotated(network_height(netMain), network_width(netMain), CV_8UC3, bitmap);
				cv::rotate(frame_resized, frame_rotated, ROTATE_180);
				*/
				if (!isDetecting && i % framesPerDetection==0)
				{
					Mat colorFixedImageMat;
					cv::cvtColor(rotatedImageMat, colorFixedImageMat, cv::COLOR_BGR2RGB);
					isDetecting = 1;
					try {
						cv::imwrite("image_" + std::to_string(numFrames) + ".jpg", colorFixedImageMat);
					}
					catch (runtime_error& ex) {
						fprintf(stderr, "Exception converting image to PNG format: %s\n", ex.what());
						return 1;
					}
					thread *t = new thread(recogFrame, colorFixedImageMat);
					i = 0;
					t->detach();
				}
				//cvtColor(mat, mat, COLOR_BGR2RGB);
				drawFrame(rotatedImageMat); // draw detections over camera frame
				//imshow("test", mat);
				//waitKey(1);

				cv::rotate(rotatedImageMat, outgoingImageMat, ROTATE_180);
				fptap.sendPicData(outgoingImageMat.data, numBytes, pCodecCtx->width, pCodecCtx->height, i);
				
				/*
				fptap.sendPicData(pFrameRGB->data[0], numBytes, pCodecCtx->width,
					pCodecCtx->height, i);*/
			}

		}

		av_packet_unref(&packet);
	}

	fptap.end_Socket();

	// Free the RGB image
	av_free(buffer);
	av_frame_free(&pFrameRGB);

	// Free the YUV frame
	av_frame_free(&pFrame);

	// Close the codecs
	avcodec_close(pCodecCtx);
	avcodec_close(pCodecCtxOrig);

	// Close the video file
	avformat_close_input(&pFormatCtx);

	return 0;
}
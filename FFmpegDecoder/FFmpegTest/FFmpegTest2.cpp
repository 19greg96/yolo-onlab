#include "stdafx.h"
#include <stdio.h>
#include <math.h>
#include "ForwardPicToAnotherPort.cpp"
#include <omp.h>
#include <thread>
#include "imagefactory.h"



using namespace std;
using namespace cv;

char bitmap[WIDTH * HEIGHT * CHANNELS];

static bool isDetecting = 0;
static vector<vector<int>> position;
static vector<String> labels;
static int numObjects = 0;

int d = 80; // dangers region

// draw recognition result
void recogFrame(ImageFactory imf, Mat &frame)
{
	isDetecting = 1;
	vector<vector<int>> pos;
	pos = imf.recognition(frame);
	
	int num = imf.getObjectNums();

	labels = imf.getLabels();
	
	position = pos;
	numObjects = num;
	isDetecting = 0;
}
void drawFrame(Mat &frame, ImageFactory imf, vector<vector<int>> p, vector<String> labels, int objectNums)
{
	int xLeftBottom;
	int yLeftBottom;
	int xRightTop;
	int yRightTop;

	if (objectNums != 0)
	{
		int dangerRegionX = WIDTH/2;
		int dangerRegionY = 450;
		int isDangerous;
		// #pragma omp parallel for
		for (int i = 0; i < objectNums; i++)
		{
			xLeftBottom = (WIDTH - HEIGHT) / 2 + p[i][0];
			yLeftBottom = p[i][1];
			xRightTop = (WIDTH - HEIGHT) / 2 + p[i][2];
			yRightTop = p[i][3];

			int centerX = (xRightTop + xLeftBottom) / 2;
			int centerY = (yRightTop + yLeftBottom) / 2;

			isDangerous = imf.isDangerous(Point(centerX, centerY), Point(dangerRegionX, dangerRegionY), d);

			Rect object((int)xLeftBottom, (int)yLeftBottom,
				(int)(xRightTop - xLeftBottom),
				(int)(yRightTop - yLeftBottom));
			if (isDangerous)
			{
				rectangle(frame, object, Scalar(255, 0, 0), 2);
			}
			else
			{
				rectangle(frame, object, Scalar(0, 255, 0), 2);
			}
			
			int baseLine = 0;
			Size labelSize = getTextSize(labels[i], FONT_HERSHEY_SIMPLEX, 1, 1, &baseLine);

			rectangle(frame, Rect(Point(xLeftBottom, yLeftBottom - labelSize.height),
				Size(labelSize.width, labelSize.height + baseLine)),
				Scalar(255, 255, 255), CV_FILLED);
			putText(frame, labels[i], Point(xLeftBottom, yLeftBottom),
				FONT_HERSHEY_SIMPLEX, 1, Scalar(0, 0, 0),2);
		}
	}
}

int main() {
    while(true){
		try{
			ImageFactory imf;
			imf.NetInit();
			//imf.recognition();

			// Path of the video file or url of video stream
			const char path[] = "tcp://192.168.0.199:8080?listen";
			//const char path[] = "tcp://192.168.157.17:8888?listen";
			//char path[] = "udp://192.168.137.1:8888/test.mjpeg";
			//char path[] = "C://Users//bmw/zrb//SA//FFmpegTest//FFmpegTest//vtest.mp4";
			// Initalizing these to NULL prevents segfaults!
			AVFormatContext   *pFormatCtx = NULL;
			int               i, videoStream;
			AVCodecContext    *pCodecCtxOrig = NULL;
			AVCodecContext    *pCodecCtx = NULL;
			AVCodec           *pCodec = NULL;
			AVFrame           *pFrame = NULL;
			AVFrame           *pFrameRGB = NULL;
			AVPacket          packet;
			int               frameFinished;
			int               numBytes;
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
			numBytes = av_image_get_buffer_size(AV_PIX_FMT_RGB24, pCodecCtx->width,
				pCodecCtx->height, 1);
			buffer = (uint8_t *)av_malloc(numBytes * sizeof(uint8_t));

			// Assign appropriate parts of buffer to image planes in pFrameRGB
			// Note that pFrameRGB is an AVFrame, but AVFrame is a superset
			// of AVPicture

			av_image_fill_arrays(pFrameRGB->data, pFrameRGB->linesize, buffer, AV_PIX_FMT_RGB24,
				pCodecCtx->width, pCodecCtx->height, 1);

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

			i = 0;

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
						// Convert the image from its native format to RGB
						sws_scale(sws_ctx, (uint8_t const * const *)pFrame->data,
							pFrame->linesize, 0, pCodecCtx->height,
							pFrameRGB->data, pFrameRGB->linesize);
						
						memcpy(bitmap, pFrameRGB->data[0], WIDTH * HEIGHT * CHANNELS);

						Mat mat(pCodecCtx->height, pCodecCtx->width, CV_8UC3, bitmap);
						// image recognition

						if (!isDetecting && i%15==0)
						{
							isDetecting = 1;
							thread *t = new thread(recogFrame, imf, mat);
							i = 0;
							t->detach();
						}
						//cvtColor(mat, mat, COLOR_BGR2RGB);
						drawFrame(mat, imf, position, labels, numObjects);
						//imshow("test", mat);
						//waitKey(1);

						fptap.sendPicData(mat.data, numBytes, pCodecCtx->width,
						pCodecCtx->height);
						
						/*
						fptap.sendPicData(pFrameRGB->data[0], numBytes, pCodecCtx->width,
							pCodecCtx->height, i);*/
					}

				}

				av_packet_unref(&packet);
			}

		}
		catch{
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
		}
	}
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
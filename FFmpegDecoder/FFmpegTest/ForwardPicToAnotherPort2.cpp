#include "stdafx.h"
#include <WinSock2.h>
#include <iostream>
#include <Ws2tcpip.h>
#pragma comment(lib, "ws2_32.lib")  //load ws2_32.dll

using namespace std;

class ForwardPicToAnotherPort {

public:
UdpClient udpClient = gcnew UdpClient;

    void sendPicDataUDP(uint8_t *pFrame, long int bufSize, int width, int height, int iFrame){
        buffer = new char[bufSize];		
        memcpy(buffer, pFrame, bufSize);
        udpClient->Send( buffer, buffer->Length, "127.0.0.1", 8888 );
    }
};
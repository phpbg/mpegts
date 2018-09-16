<?php

/**
 * MIT License
 *
 * Copyright (c) 2018 Samuel CHEMLA
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
 * SOFTWARE.
 */

namespace PhpBg\MpegTs\Tests;

use PhpBg\MpegTs\Packetizer;

class PacketizerTest extends TestCase
{
    private function getPacketizer(): Packetizer
    {
        $packetizer = new Packetizer();
        $packetizer->consecutivePacketsBeforeLock = 5;
        return $packetizer;
    }

    public function testReadWriteByteByByte()
    {
        $filename = $this->getTestFile("10_mpegts_packets.ts");
        $handle = fopen($filename, "rb");

        $packetizer = $this->getPacketizer();
        $dataEventCount = 0;
        $packetTotalSize = 0;
        $errorCount = 0;
        $packetizer->on('data', function ($data) use (&$dataEventCount, &$packetTotalSize) {
            $dataEventCount++;
            $packetTotalSize += strlen($data);
        });
        $packetizer->on('error', function () use (&$errorCount) {
            $errorCount++;
        });
        while (!feof($handle)) {
            // Byte by byte read
            $read = fread($handle, 2);
            if (false === $read) {
                throw new \Exception();
            }
            $packetizer->write($read);
        }
        fclose($handle);

        $this->assertSame(0, $errorCount);
        $this->assertSame(5, $dataEventCount); //1 event with 6 packets for lock, then 4 remaining events with one packet
        $this->assertSame(10 * 188, $packetTotalSize);
    }

    public function testReadWriteSingleShot()
    {
        $filename = $this->getTestFile("10_mpegts_packets.ts");

        $packetizer = $this->getPacketizer();
        $dataEventCount = 0;
        $packetTotalSize = 0;
        $errorCount = 0;
        $packetizer->on('data', function ($data) use (&$dataEventCount, &$packetTotalSize) {
            $dataEventCount++;
            $packetTotalSize += strlen($data);
        });
        $packetizer->on('error', function () use (&$errorCount) {
            $errorCount++;
        });
        $packetizer->write(file_get_contents($filename));


        $this->assertSame(0, $errorCount);
        $this->assertSame(1, $dataEventCount);
        $this->assertSame(10 * 188, $packetTotalSize);
    }

    public function testReadLongRun()
    {
        $filename = $this->getTestFile("10_mpegts_packets.ts");

        $packetizer = $this->getPacketizer();
        $dataEventCount = 0;
        $packetTotalSize = 0;
        $errorCount = 0;
        $packetizer->on('data', function ($data) use (&$dataEventCount, &$packetTotalSize) {
            $dataEventCount++;
            $packetTotalSize += strlen($data);
        });
        $packetizer->on('error', function () use (&$errorCount) {
            $errorCount++;
        });
        $data = file_get_contents($filename);
        $iterations = 100;
        for ($i = 0; $i < $iterations; $i++) {
            $packetizer->write($data);
        }

        $this->assertSame(0, $errorCount);
        $this->assertSame($iterations, $dataEventCount); //100 events containing 10 packets each
        $this->assertSame($iterations * 10 * 188, $packetTotalSize);
    }

    public function testReadWriteWithFewErrorsOneShot()
    {
        $filename = $this->getTestFile("10_mpegts_packets.ts");
        $data = file_get_contents($filename);
        $data = str_repeat("0", 10) . $data . str_repeat("0", 20);

        $packetizer = $this->getPacketizer();
        $dataEventCount = 0;
        $packetTotalSize = 0;
        $errorCount = 0;
        $bytesDiscarded = 0;
        $packetizer->on('data', function ($data) use (&$dataEventCount, &$packetTotalSize) {
            $dataEventCount++;
            $packetTotalSize += strlen($data);
        });
        $packetizer->on('error', function ($exception) use (&$errorCount, &$bytesDiscarded) {
            $errorCount++;
            $bytesDiscarded += $exception->bytesDiscarded;
        });
        $packetizer->write($data);

        $this->assertSame(1, $errorCount);
        $this->assertSame(10, $bytesDiscarded);
        $this->assertSame(1, $dataEventCount); //1 event with 10 packets
        $this->assertSame(10 * 188, $packetTotalSize);
    }

    public function testReadWriteWithMoreThan188ErrorsOneShot()
    {

        $packetizer = $this->getPacketizer();
        $dataEventCount = 0;
        $packetTotalSize = 0;
        $errorCount = 0;
        $bytesDiscarded = 0;
        $packetizer->on('data', function ($data) use (&$dataEventCount, &$packetTotalSize) {
            $dataEventCount++;
            $packetTotalSize += strlen($data);
        });
        $packetizer->on('error', function ($exception) use (&$errorCount, &$bytesDiscarded) {
            $errorCount++;
            $bytesDiscarded += $exception->bytesDiscarded;
        });


        $data = str_repeat("0", 188 * 100 * $packetizer->consecutivePacketsBeforeLock);
        $packetizer->write($data);

        $this->assertSame(0, $dataEventCount);
        $this->assertSame(1, $errorCount);
        $this->assertSame(strlen($data) - 188 * $packetizer->consecutivePacketsBeforeLock, $bytesDiscarded);
    }


    public function testReadWriteWithFewErrors()
    {
        $filename = $this->getTestFile("10_mpegts_packets.ts");
        $data = file_get_contents($filename);
        $data = str_repeat("0", 10) . $data . str_repeat("0", 20);

        $packetizer = $this->getPacketizer();
        $dataEventCount = 0;
        $packetTotalSize = 0;
        $errorCount = 0;
        $bytesDiscarded = 0;
        $packetizer->on('data', function ($data) use (&$dataEventCount, &$packetTotalSize) {
            $dataEventCount++;
            $packetTotalSize += strlen($data);
        });
        $packetizer->on('error', function ($exception) use (&$errorCount, &$bytesDiscarded) {
            $errorCount++;
            $bytesDiscarded += $exception->bytesDiscarded;
        });
        for ($i = 0; $i < strlen($data); $i++) {
            $packetizer->write($data[$i]);
        }

        $this->assertSame(1, $errorCount);
        $this->assertSame(10, $bytesDiscarded);
        $this->assertSame(6, $dataEventCount); //1 event with 5 packets for lock, 5 events remaining
        $this->assertSame(10 * 188, $packetTotalSize);
    }

    public function testReadWriteWithManyErrors()
    {
        $packetizer = $this->getPacketizer();
        $dataEventCount = 0;
        $packetTotalSize = 0;
        $errorCount = 0;
        $bytesDiscarded = 0;
        $packetizer->on('data', function ($data) use (&$dataEventCount, &$packetTotalSize) {
            $dataEventCount++;
            $packetTotalSize += strlen($data);
        });
        $packetizer->on('error', function ($exception) use (&$errorCount, &$bytesDiscarded) {
            $errorCount++;
            $bytesDiscarded += $exception->bytesDiscarded;
        });
        $nbToDiscard = ($packetizer->consecutivePacketsBeforeLock + 1) * 188;
        for ($i = 0; $i < $nbToDiscard; $i++) {
            $packetizer->write('0');
        }
        $filename = $this->getTestFile("10_mpegts_packets.ts");
        $packetizer->write(file_get_contents($filename));

        $this->assertSame(2, $errorCount);
        $this->assertSame($nbToDiscard, $bytesDiscarded);
        $this->assertSame(1, $dataEventCount);
        $this->assertSame(10 * 188, $packetTotalSize);
    }

    public function testRecoverAfterLockLost()
    {
        $filename = $this->getTestFile("10_mpegts_packets.ts");
        $data = file_get_contents($filename);
        $nbToDiscard = 188 * 100;
        $data = $data . str_repeat("0", $nbToDiscard) . $data;

        $packetizer = $this->getPacketizer();
        $dataEventCount = 0;
        $packetTotalSize = 0;
        $errorCount = 0;
        $bytesDiscarded = 0;
        $packetizer->on('data', function ($data) use (&$dataEventCount, &$packetTotalSize) {
            $dataEventCount++;
            $packetTotalSize += strlen($data);
        });
        $packetizer->on('error', function ($exception) use (&$errorCount, &$bytesDiscarded) {
            $errorCount++;
            $bytesDiscarded += $exception->bytesDiscarded;
        });
        $packetizer->write($data);

        $this->assertSame(1, $errorCount);
        $this->assertSame($nbToDiscard, $bytesDiscarded);
        $this->assertSame(2, $dataEventCount);
        $this->assertSame(2 * 10 * 188, $packetTotalSize);
    }

    public function testThroughput() {
        $filename = $this->getTestFile("10_mpegts_packets.ts");
        $data = file_get_contents($filename);
        $bigData = str_repeat($data, 10);
        $bigDataLen = strlen($bigData);
        $this->assertSame(10*188*10, $bigDataLen);
        $packetizer = $this->getPacketizer();

        $totalLen = 0;

        $start = microtime(true);
        for ($i=0;$i<1000000;$i++) {
            $packetizer->write($bigData);
            $totalLen += $bigDataLen;
        }
        $stop = microtime(true);
        $duration_s = $stop - $start;
        $mb = $totalLen/1000000;
        $throughput = $mb/$duration_s;

        echo "Thgoughput on this system: {$throughput}MB/s (using chunks of {$bigDataLen}bytes)";
    }
}
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

use PhpBg\MpegTs\Parser;
use PhpBg\MpegTs\Pid;

class ParserTest extends TestCase
{

    public function testParsePes()
    {
        $data = file_get_contents($this->getTestFile('16_mpegts_eit_packets.ts'));
        $this->assertNotFalse($data);

        $parser = new Parser();
        $parser->addPidFilter(Pid::EIT_ST_CIT());

        $parser->on('error', function ($e) {
            $this->assertTrue(false);
        });
        $pes = '';
        $parser->on('pes', function ($pid, $data) use (&$pes) {
            $pes .= $data;
            $this->assertSame(Pid::EIT_ST_CIT()->getValue(), $pid);
        });
        $parser->on('ts', function ($pid, $data) {
            $this->assertTrue(false, 'Data should have been filtered out');
        });
        $parser->write($data);

        $this->assertSame(15 * (188 - 4), strlen($pes));
    }

    public function testParsePesAll()
    {
        $data = file_get_contents($this->getTestFile('16_mpegts_eit_packets.ts'));
        $this->assertNotFalse($data);

        $parser = new Parser();
        $parser->filterAllPids = true;

        $parser->on('error', function ($e) {
            $this->assertTrue(false);
        });
        $pes = '';
        $parser->on('pes', function ($pid, $data) use (&$pes) {
            $pes .= $data;
            $this->assertSame(Pid::EIT_ST_CIT()->getValue(), $pid);
        });
        $parser->on('ts', function ($pid, $data) {
            $this->assertTrue(false, 'Data should have been filtered out');
        });
        $parser->write($data);

        $this->assertSame(15 * (188 - 4), strlen($pes));
    }

    public function testParsePesNoFilter()
    {
        $data = file_get_contents($this->getTestFile('16_mpegts_eit_packets.ts'));
        $this->assertNotFalse($data);

        $parser = new Parser();

        $parser->on('error', function ($e) {
            $this->assertTrue(false);
        });
        $parser->on('pes', function ($pid, $data) {
            $this->assertTrue(false, 'Data should have been filtered out');
        });
        $parser->on('ts', function ($pid, $data) {
            $this->assertTrue(false, 'Data should have been filtered out');
        });
        $parser->write($data);
    }

    public function testParsePesBadFilter()
    {
        $data = file_get_contents($this->getTestFile('16_mpegts_eit_packets.ts'));
        $this->assertNotFalse($data);

        $parser = new Parser();
        $parser->addPidFilter(Pid::PAT());

        $parser->on('error', function ($e) {
            $this->assertTrue(false);
        });
        $parser->on('pes', function ($pid, $data) {
            $this->assertTrue(false, 'Data should have been filtered out');
        });
        $parser->on('ts', function ($pid, $data) {
            $this->assertTrue(false, 'Data should have been filtered out');
        });
        $parser->write($data);
    }

    public function testParseWithAdaptationField()
    {
        $data = file_get_contents($this->getTestFile('18_mpegts_packets_with_adaptation_field.ts'));
        $this->assertNotFalse($data);

        $parser = new Parser();
        $pidFilter = 0x000002da;
        $parser->addPidFilter(new Pid($pidFilter));

        $parser->on('error', function ($e) {
            $this->assertTrue(false);
        });
        $pes = '';
        $parser->on('pes', function ($pid, $data) use (&$pes, $pidFilter) {
            $pes .= $data;
            $this->assertSame($pidFilter, $pid);
        });
        $parser->on('ts', function ($pid, $data) {
            $this->assertTrue(false, 'Data should have been filtered out');
        });
        $parser->write($data);

        // Please use wireshark to parse sample file and get PES packet size
        // packets contain adaptation fields so size is hard to calculate
        $this->assertSame(3086, strlen($pes));
    }

    public function testPassthroughAll()
    {
        $data = file_get_contents($this->getTestFile('16_mpegts_eit_packets.ts'));
        $this->assertNotFalse($data);

        $parser = new Parser();
        $parser->passthroughAllPids = true;

        $parser->on('error', function ($e) {
            $this->assertTrue(false);
        });
        $parser->on('pes', function ($pid, $data) use (&$pes) {
            $this->assertTrue(false, 'Data should have been filtered out');
        });
        $ts = '';
        $parser->on('ts', function ($pid, $data) use (&$ts) {
            $ts .= $data;
        });
        $parser->write($data);

        $this->assertSame(16 * 188, strlen($ts));
    }

    public function testPassthroughSome()
    {
        $data = file_get_contents($this->getTestFile('16_mpegts_eit_packets.ts'));
        $this->assertNotFalse($data);

        $parser = new Parser();
        $parser->addPidPassthrough(Pid::EIT_ST_CIT());

        $parser->on('error', function ($e) {
            $this->assertTrue(false);
        });
        $parser->on('pes', function ($pid, $data) use (&$pes) {
            $this->assertTrue(false, 'Data should have been filtered out');
        });
        $ts = '';
        $parser->on('ts', function ($pid, $data) use (&$ts) {
            $ts .= $data;
            $this->assertSame(Pid::EIT_ST_CIT, $pid);
        });
        $parser->write($data);

        $this->assertSame(16 * 188, strlen($ts));
    }

    public function testPassthroughWrong()
    {
        $data = file_get_contents($this->getTestFile('16_mpegts_eit_packets.ts'));
        $this->assertNotFalse($data);

        $parser = new Parser();
        $parser->addPidPassthrough(Pid::PAT());

        $parser->on('error', function ($e) {
            $this->assertTrue(false);
        });
        $parser->on('pes', function ($pid, $data) use (&$pes) {
            $this->assertTrue(false, 'Data should have been filtered out');
        });
        $parser->on('ts', function ($pid, $data) {
            $this->assertTrue(false, 'Data should have been filtered out');
        });
        $parser->write($data);
    }
}
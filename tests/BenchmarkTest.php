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

use PHPUnit\Framework\TestCase;

/**
 * Class BenchmarkTest
 *
 * This class intends to provide reference benchmarks
 */
class BenchmarkTest extends TestCase
{
    private $longData;

    private $tsPacket;

    protected function setUp()
    {
        $this->longData = str_repeat('G', 65000);
        $this->tsPacket = str_repeat('G', 188);
    }

    public function testSubstrVsArray()
    {
        $this->benchVs("Test substring call vs 1x direct array access for 1 char", [$this, 'arrayAccess'], [$this, 'substr'], 1000000);
    }

    public function testSubstr4VsArray4()
    {
        $this->benchVs("Test substring call vs 4x direct array access for 4 char", [$this, 'arrayAccess4'], [$this, 'substr4'], 1000000);
    }

    public function testPassByReferenceReadonly()
    {
        $this->benchVs("Test passing big strings by copy vs reference (readonly)", [$this, 'passStringReadonly'], [$this, 'passStringPointerReadonly'], 1000000);
    }

    public function testMultipleUnpack()
    {
        $this->benchVs("Test 4 x unpack() vs unpack(4x)", [$this, 'unpackFour'], [$this, 'fourUnpack'], 1000000);
    }

    public function testPassByReferenceReadWrite()
    {
        $this->benchVs("Test passing big strings by copy vs reference (readwrite)", [$this, 'passStringReadWrite'], [$this, 'passStringPointerReadWrite'], 1000000);
    }

    public function testCallUserFunc()
    {
        $this->benchVs("Test call_user_func vs call_user_func_array", [$this, 'callUserFunc'], [$this, 'callUserFuncArray'], 500000);
    }

    public function testCallDirect()
    {
        $this->benchVs("Test call_user_func vs direct call", [$this, 'callUserFunc'], [$this, 'callDirect'], 500000);
    }

    public function testCallVar()
    {
        $this->benchVs("Test call_user_func vs call var", [$this, 'callUserFunc'], [$this, 'callVar'], 500000);
    }

    public function testStrConcat()
    {
        $this->benchVs("Test string concatenation", [$this, 'concatStr'], [$this, 'implodeStr'], 500000);
    }

    private function benchVs(string $desc, callable $callable1, callable $callable2, int $iterations)
    {
        $results = [
            $this->getCallableName($callable1) => $this->bench($callable1, $iterations),
            $this->getCallableName($callable2) => $this->bench($callable2, $iterations),
        ];
        asort($results);
        $keys = array_keys($results);
        $slowerKey = array_pop($keys);
        $fasterKey = array_pop($keys);
        $rate = round(100 * $results[$slowerKey] / $results[$fasterKey]) - 100;
        echo "$desc\n";
        echo "{$fasterKey} ({$results[$fasterKey]}ms) faster than {$slowerKey} ({$results[$slowerKey]}ms +{$rate}%)\n";
        echo "\n";

        // Avoid PHPUnit complaining
        $this->assertTrue(true);
    }


    private function getCallableName(callable $callable): string
    {
        if (is_string($callable)) {
            return trim($callable);
        } else if (is_array($callable)) {
            if (is_object($callable[0])) {
                return sprintf("%s::%s", get_class($callable[0]), trim($callable[1]));
            } else {
                return sprintf("%s::%s", trim($callable[0]), trim($callable[1]));
            }
        } else if ($callable instanceof \Closure) {
            return 'closure';
        } else {
            return 'unknown';
        }
    }

    private function bench(callable $callable, int $iterations): int
    {
        $start = microtime(true);
        for ($i = 0; $i < $iterations; $i++) {
            call_user_func($callable);
        }
        $stop = microtime(true);
        $durationMs = round(($stop - $start) * 1000);
        return $durationMs;
    }

    private function substr()
    {
        //Check result to make sure it is read
        if (substr($this->longData, 0, 1) !== 'G') {
            throw new \Exception();
        }
    }

    private function arrayAccess()
    {
        //Check result to make sure it is read
        if ($this->longData[0] !== 'G') {
            throw new \Exception();
        }
    }

    private function substr4()
    {
        //Check result to make sure it is read
        if (substr($this->longData, 0, 4) !== 'GGGG') {
            throw new \Exception();
        }
    }

    private function arrayAccess4()
    {
        //Check result to make sure it is read
        if ($this->longData[0] . $this->longData[1] . $this->longData[2] . $this->longData[3] !== 'GGGG') {
            throw new \Exception();
        }
    }

    private function passStringReadonly()
    {
        $data = str_repeat('0', 65000);
        return $this->_passString($data, true);
    }

    private function passStringReadWrite()
    {
        $data = str_repeat('0', 65000);
        return $this->_passString($data, false);
    }

    private function _passString(string $data, bool $readonly)
    {
        if (!$readonly) {
            $data .= 'yo';
        }
        if (strlen($data) < 4) {
            throw new \Exception();
        }
        $binStr = substr($data, 0, 4);
        $unpackedArray = unpack('N', $binStr);
        if ($data[5] === 'G') {
            throw new \Exception();
        }
        return $unpackedArray[1];
    }

    private function passStringPointerReadonly()
    {
        $data = str_repeat('0', 65000);
        return $this->_passStringPointer($data, true);
    }

    private function passStringPointerReadWrite()
    {
        $data = str_repeat('0', 65000);
        return $this->_passStringPointer($data, false);
    }

    private function _passStringPointer(string &$data, bool $readonly)
    {
        if (!$readonly) {
            $data .= 'yo';
        }
        if (strlen($data) < 4) {
            throw new \Exception();
        }
        $binStr = substr($data, 0, 4);
        $unpackedArray = unpack('N', $binStr);
        if ($data[5] === 'G') {
            throw new \Exception();
        }
        return $unpackedArray[1];
    }

    private function unpackFour()
    {
        $data = '0000';
        $headBinArray = unpack('c4', $data);
        $a = $headBinArray[1];
        $b = $headBinArray[2];
        $c = $headBinArray[3];
        $d = $headBinArray[4];
    }

    private function fourUnpack()
    {
        $data = '0000';
        $a = unpack('c', $data[0])[1];
        $b = unpack('c', $data[1])[1];
        $c = unpack('c', $data[2])[1];
        $d = unpack('c', $data[3])[1];
    }

    private function callbackFunc(string $arg1, string $arg2, int $arg3)
    {
        $this->assertSame(1, $arg3);
    }

    private function callUserFunc()
    {
        $arg1 = $this->longData;
        $arg2 = 'foo';
        $arg3 = 1;
        call_user_func([$this, 'callbackFunc'], $arg1, $arg2, $arg3);
    }

    private function callUserFuncArray()
    {
        $arg1 = $this->longData;
        $arg2 = 'foo';
        $arg3 = 1;
        call_user_func_array([$this, 'callbackFunc'], [$arg1, $arg2, $arg3]);
    }

    private function callVar()
    {
        $func = 'callbackFunc';
        $arg1 = $this->longData;
        $arg2 = 'foo';
        $arg3 = 1;
        $this->$func($arg1, $arg2, $arg3);
    }

    private function callDirect()
    {
        $arg1 = $this->longData;
        $arg2 = 'foo';
        $arg3 = 1;
        $this->callbackFunc($arg1, $arg2, $arg3);
    }

    private function concatStr()
    {
        $data = '';
        for ($i = 0; $i < 30; $i++) {
            $data .= $this->tsPacket;
        }
        return $data;
    }

    private function implodeStr()
    {
        $data = [];
        for ($i = 0; $i < 30; $i++) {
            $data[] = $this->tsPacket;
        }
        return implode('', $data);
    }
}
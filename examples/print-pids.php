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

require_once __DIR__ . '/../vendor/autoload.php';

// Check args and open files
if (count($argv) !== 2) {
    echo "Print all PIDs present in TS file\r\n";
    echo "Usage: print-pids <infile>\r\n";
    return;
}
if (!is_file($argv[1])) {
    echo "$argv[1] is not a file\r\n";
    return;
}
$inFileHandle = fopen($argv[1], 'rb');
if ($inFileHandle === false) {
    throw new Exception();
}

// Prepare parser
$parser = new \PhpBg\MpegTs\Parser();
$parser->passthroughAllPids = true;
$tsStats = [];
$tsCounter = 0;
$parser->on('error', function ($e) {
    echo "TS parser error: {$e->getMessage()}\n";
});
$parser->on('ts', function ($pid, $data) use (&$tsStats, &$tsCounter) {
    // Count TS packets
    $tsStats[$pid] = isset($tsStats[$pid]) ? $tsStats[$pid] + 1 : 1;
    $tsCounter++;
});

// Prepare packetizer
$packetizer = new \PhpBg\MpegTs\Packetizer();
$packetizer->on('error', function ($e) {
    echo "TS packetizer error: {$e->getMessage()}\n";
});
$packetizer->on('data', function ($data) use ($parser) {
    $parser->write($data);
});

// Read file and write packets
while (!feof($inFileHandle)) {
    $data = fread($inFileHandle, 1880);
    if (false === $data) {
        throw new Exception("Unable to read");
    }
    $packetizer->write($data);
}

fclose($inFileHandle);

echo "Done\r\n";
echo "{$tsCounter} MPEG TS packets read\r\n";
ksort($tsStats);
foreach ($tsStats as $pid => $count) {
    echo sprintf("PID: %04d (0x%04x)\t%d packets\r\n", $pid, $pid, $count);
}
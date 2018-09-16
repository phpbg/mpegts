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

namespace PhpBg\MpegTs;

use Evenement\EventEmitter;

/**
 * Class Packetizer
 *
 * The packetizer can be written with arbitrary length chunks of data.
 * It will emit proper chunks of MPEG TS packets.
 * Use it if your data source is subject to packet loss or if it doesn't guarantee you full MPEG TS packet chunks.
 *
 * Events:
 *   error event:
 *     The `error` event will be emitted when an error occurs, usually while parsing data.
 *     The event receives a single `Exception` argument for the error instance.
 *
 *   data event:
 *     The `data` event will be emitted when MPEG TS packets are available
 *     The event receives a single arguments whih is a binary string containing one or more MPEG TS packets
 *
 * @package PhpBg\MpegTs
 */
class Packetizer extends EventEmitter
{
    protected $buffer = null;
    public $consecutivePacketsBeforeLock = 5;
    protected $locked = false;


    public function write($data)
    {
        //Buffer is set to null when empty
        //This allows handling incoming data directly without string concat operation
        if ($this->buffer === null) {
            $this->buffer = $data;
        } else {
            $this->buffer .= $data;
        }

        $this->drain();
    }

    protected function drain()
    {
        if (!$this->locked) {
            // Try to lock
            $bufferLen = strlen($this->buffer);
            if ($bufferLen < ($this->consecutivePacketsBeforeLock + 1) * 188) {
                return;
            }
            $firstPacketOffset = $this->findFirstPacketOffset();

            // We were not able to lock, discard as much data as we can
            if ($firstPacketOffset === false) {
                $offsetToKeep = $bufferLen - $this->consecutivePacketsBeforeLock * 188;
                $this->buffer = substr($this->buffer, $offsetToKeep);
                $exception = new DiscardException($offsetToKeep);
                $this->emit('error', [$exception]);
                return;
            }

            // We were able to lock. Discard data if needed
            $this->locked = true;
            if ($firstPacketOffset !== 0) {
                $this->buffer = substr($this->buffer, $firstPacketOffset);
                $exception = new DiscardException($firstPacketOffset);
                $this->emit('error', [$exception]);
            }
        }

        // After this point we'll be locked...
        if (!$this->locked) {
            return;
        }

        // ... and we'll have at least one packet
        $bufferLen = strlen($this->buffer);
        if ($bufferLen < 188) {
            return;
        }

        // Find the biggest chunk we can emit...
        $pointer = 0;
        while ($pointer + 188 <= $bufferLen) {
            if ($this->buffer[$pointer] !== 'G') {
                $this->locked = false;
                break;
            }

            $pointer += 188;
        }

        // ... and emit it
        $bufferLen = $bufferLen - $pointer;
        if ($bufferLen === 0) {
            $this->emit('data', [$this->buffer]);
            $this->buffer = null;
        } else {
            $this->emit('data', [substr($this->buffer, 0, $pointer)]);
            $this->buffer = substr($this->buffer, $pointer);
            if ($bufferLen >= 188) {
                $this->drain();
            }
        }
    }

    /**
     * Find all occurrences of a needle in a string
     *
     * @param string $haystack The string to search in
     * @param mixed $needle
     * @return array
     */
    protected function findall(string $haystack, $needle): array
    {
        $lastPos = 0;
        $positions = [];
        while (($lastPos = strpos($haystack, $needle, $lastPos)) !== false) {
            $positions[] = $lastPos;
            $lastPos = $lastPos + 1;
        }
        return $positions;
    }

    /**
     * Find the offset of the first MPEG TS packet in the current buffer
     *
     * @return bool|int
     */
    protected function findFirstPacketOffset()
    {
        // This is quite ugly because it will parse the entire buffer whatever it's size is
        // TODO Rewrite this in a more clever way
        $positions = $this->findall($this->buffer, 'G');
        while (count($positions) > 0) {
            $position = array_shift($positions);
            for ($i = 1; $i <= $this->consecutivePacketsBeforeLock; $i++) {
                if (!in_array($position + 188 * $i, $positions)) {
                    break;
                }
                return $position;
            }
        }
        return false;
    }
}
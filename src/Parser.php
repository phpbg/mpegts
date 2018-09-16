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
 * Class Parser
 *
 * The parser can receive chunks of MPEGTS packets and will emit
 *   * complete PES packets if they match a filter (by PID)
 *   * raw TS packets if they match a passthrough filter (by PID)
 *
 * error event:
 *     The `error` event will be emitted when an error occurs, usually while parsing data
 *     The event receives a single `Exception` argument for the error instance.
 * pes event:
 *     The `pes` event will be emitted when a PES packet (or PSI data packet) is available
 *     The event receives two arguments (pid, packet) for incoming PES/PSI packets (binary string)
 * ts event:
 *     The ts event will be emitted when TS packets match passthrough rules
 *     The event receives two arguments (pid, packet) for incoming TS packets (188 bytes TS packets, as binary string)
 *     TODO Decide wether we output single TS packets (easier but less performant) or multiple of 188 bytes TS packets
 *
 */
class Parser extends EventEmitter
{
    /**
     * @var array Array of PIDs to pass to ts event
     */
    protected $passthroughPids = [];

    /**
     * @var bool Set to true to pass all PIDs through. All packets will be present in the ts event
     */
    public $passthroughAllPids = false;

    /**
     * @var array Array of PIDs to pass to pes event
     */
    protected $filterPids = [];

    /**
     * @var bool Set true to pass all TS packets to pes event
     */
    public $filterAllPids = false;

    protected $pesBuffers = [];

    public function write($data)
    {
        $pointer = 0;
        $len = strlen($data);
        while ($pointer < $len) {
            try {
                $this->parse($data, $pointer);
            } catch (\Exception $e) {
                $this->emit('error', [$e]);
            }
            $pointer += 188;
        }
        if ($pointer !== $len) {
            $this->emit('error', [new Exception("Unexpected data length. Please write multiple of entire 188 bytes MPEG TS packets")]);
        }
    }

    /**
     * Add a PID to passthrough filter.
     * Packets belonging to this PID will be present in the ts event
     * @param Pid $pid
     */
    public function addPidPassthrough(Pid $pid)
    {
        $this->passthroughPids[$pid->getValue()] = true;
    }

    /**
     * Remove a PID from passthrough filter.
     * @see Parser::addPidPassthrough()
     * @param Pid $pid
     */
    public function removePidPassthrough(Pid $pid)
    {
        unset($this->passthroughPids[$pid->getValue()]);
    }

    /**
     * Add a PID to parse filter
     * @param Pid $pid
     */
    public function addPidFilter(Pid $pid)
    {
        $this->filterPids[$pid->getValue()] = true;
    }

    /**
     * Remove a PID from parse filter
     * @param Pid $pid
     */
    public function removePidFilter(Pid $pid)
    {
        unset($this->filterPids[$pid->getValue()]);
    }

    /**
     * @param string &$data
     * @param int $pointer
     * @throws Exception
     */
    protected function parse(string $data, int $pointer)
    {
        //Parse head
        $headStr = substr($data, $pointer, 4);
        $headBinArray = unpack('N', $headStr);
        $headBin = $headBinArray[1];

        // Bit pattern of 0x47 (ASCII char 'G')
        $sync = ($headBin >> 24) & 0xff;
        if ($sync !== 0x47) {
            throw new Exception("Invalid packet sync byte");
        }

        // Set when a demodulator can't correct errors from FEC data; indicating the packet is corrupt.[7]
        $transportErrorIndicator = ($headBin >> 23) & 0x01;
        if ($transportErrorIndicator === 0x01) {
            throw new Exception("Transport error indicator is set");
        }

        // Passthrough PIDs
        $pid = ($headBin >> 8) & 0x1fff;
        if (isset($this->passthroughPids[$pid]) || $this->passthroughAllPids) {
            $this->emit('ts', [$pid, substr($data, $pointer, 188)]);
        }

        // Filter unwanted PIDs
        if (!isset($this->filterPids[$pid]) && !$this->filterAllPids) {
            return;
        }

        $transportScramblingControl = ($headBin & 0xc0) >> 6;
        if ($transportScramblingControl != 0x00) {
            throw new Exception("Packet is scrambled");
        }

        //Move pointer forward after head parsing & after eventual passthrough
        $pointer += 4;

        $adaptationField = ($headBin >> 4) & 0x03;
        $hasPayload = ($adaptationField & 0b01) === 0b01;

        //Adaptation field payload is ignored (yet), we only care about payload (yet)
        if (!$hasPayload) {
            return;
        }

        $hasAdaptationField = ($adaptationField & 0b10) === 0b10;
        if ($hasAdaptationField) {
            $adaptationFieldLength = unpack('C', $data[$pointer])[1];
            $pointer += 1;
            //payload length is 188 - 4 header bytes - 1 byte of adaptation field length - adaptation field length
            $payload = substr($data, $pointer + $adaptationFieldLength, 184 - 1 - $adaptationFieldLength);
        } else {
            //payload length is 188 - 4 header bytes
            $payload = substr($data, $pointer, 184);
        }

        // Sequence number of payload packets (0x00 to 0x0F) within each stream (except PID 8191) Incremented per-PID, only when a payload flag is set.
        $continuityCounter = ($headBin & 0xf);

        // Set when a PES, PSI, or DVB-MIP packet begins immediately following the header.
        $payloadUnitStartIndicator = ($headBin & 0x400000) >> 22;
        if ($payloadUnitStartIndicator === 0x1) {
            // Flush buffer if available
            if (isset($this->pesBuffers[$pid])) {
                $this->emit('pes', [$pid, $this->pesBuffers[$pid]->data]);
            }
            // Reinit buffer
            $buffer = new PesBuffer();
            $buffer->continuityCounter = $continuityCounter;
            $buffer->data = '';
            $this->pesBuffers[$pid] = $buffer;
        } else if ($hasPayload) {
            if (!isset($this->pesBuffers[$pid])) {
                throw new Exception("Orphan packet");
            }

            // Check CC
            if ($continuityCounter !== (($this->pesBuffers[$pid]->continuityCounter + 1) % 16)) {
                throw new Exception("Unexpected discontinuity");
            }
            $this->pesBuffers[$pid]->continuityCounter = $continuityCounter;
        }

        // Push payload to buffer
        if ($hasPayload) {
            $this->pesBuffers[$pid]->data .= $payload;
        }
    }
}
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

use MyCLabs\Enum\Enum;

/**
 * Class Pid (Packet Identifier)
 *
 * @see Final draft ETSI EN 300 468 V1.13.1 (2012-04), 5.1.3 Coding of PID and table_id fields
 * @see https://en.wikipedia.org/wiki/MPEG_transport_stream#Packet_Identifier_(PID)
 */
class Pid extends Enum
{
    const PAT = 0x0000;
    const CAT = 0x0001;
    const TSDT = 0x0002;
    const NIT_ST = 0x0010;
    const SDT_BAT_ST = 0x0011;
    const EIT_ST_CIT = 0x0012;
    const RST_ST = 0x0013;
    const TDT_TOT_ST = 0x0014;
    const NETWORK_SYNC = 0x0015;
    const RNT = 0x0016;
    const INBAND_SIG = 0x001c;
    const MEASUREMENT = 0x001d;
    const DIT = 0x001e;
    const SIT = 0x001f;
    const NULL_PACKET = 0x1fff;

    /**
     * Pid constructor that allows Pids on any value
     * @param int $value
     */
    public function __construct(int $value)
    {
        $this->value = $value;
    }
}
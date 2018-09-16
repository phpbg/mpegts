# PhpBg\MPEGTS

This is a pure PHP MPEG TS library, developed with performance in mind.

It provides tools for :
* Finding MPEG TS packets 
* Converting MPEG TS packets to PES packets
* Filtering MPEG TS packets by PID

https://en.wikipedia.org/wiki/MPEG_transport_stream

# Requirements
* PHP7+

Installation on ubuntu 16.04:

    sudo apt install php7.0-cli

Additional you can install xdebug for development purposes:

    sudo apt install php-xdebug


# Examples

See `examples/` folder

# Tests
To run unit tests launch:

    php vendor/phpunit/phpunit/phpunit -c phpunit.xml
    
NB: to report code coverage add `--coverage-text` but keep in mind that launching with code coverage increase greatly the time required for tests to run (thus do not reflect real use case compute time)

## Memo for creating TS sample files
1. Open a TS file with wireshark
2. Export a PCAP file containing only required packets
3. `tshark -x -r test.pcap | sed -n 's/^[0-9a-f]*\s\(\(\s[0-9a-f][0-9a-f]\)\{1,16\}\).*$/\1/p' > test.hex`
4. `xxd -r -p test.hex test.bin`

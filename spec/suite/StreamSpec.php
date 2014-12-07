<?php
namespace storage\stream\spec\suite;

use dir\Dir;
use storage\stream\Stream;
use storage\stream\StreamException;

use kahlan\plugin\Monkey;

describe("Stream", function() {

    beforeEach(function() {
        $this->modes = [
            'r'  => ['r'],
            'r+' => ['r', 'a+', 'c+', 'r+', 'w+', 'x+'],
            'w'  => ['a', 'c', 'w', 'x'],
            'w+' => ['a', 'c', 'w', 'x', 'a+', 'c+', 'r+', 'w+', 'x+']
        ];
    });

    beforeEach(function() {
        $this->temp = Dir::tempnam(sys_get_temp_dir(), 'spec');
        $this->filename = tempnam($this->temp, 'foo');
    });

    afterEach(function() {
        Dir::remove($this->temp, ['recursive' => true]);
    });

    describe("->resource()", function() {

        it("throws an exception if the passed resource is not a valid resource", function() {

            $closure = function() {
                $stream = new Stream('not a resource');
                $stream->resource();
            };

            expect($closure)->toThrow(new StreamException('A Stream object requires a stream resource as constructor argument'));

        });

        it("returns the resource", function() {

            $handle = fopen('php://temp', 'r+');
            $stream = new Stream($handle);
            expect($stream->resource())->toBe($handle);

        });

    });

    describe("->meta()", function() {

        it("returns the meta data", function() {
            $stream = new Stream(fopen('php://temp', 'r+'));
            $meta = $stream->meta();

            expect($meta['uri'])->toBe('php://temp');
            expect($meta['wrapper_type'])->toBe('PHP');
            expect($meta['stream_type'])->toBe('TEMP');
            expect($meta['mode'])->toBe('w+b');
            expect($meta['unread_bytes'])->toBe(0);
            expect($meta['seekable'])->toBe(true);
        });

        it("returns a specitic meta data entry", function() {
            $stream = new Stream(fopen('php://temp', 'r+'));
            expect($stream->meta('stream_type'))->toBe('TEMP');
        });

        it("returns `null` for unexisting meta data entry", function() {
            $stream = new Stream(fopen('php://temp', 'r+'));
            expect($stream->meta('unexisting'))->toBe(null);
        });

    });

    describe("->isLocal()", function() {

        it("returns `true` if the stream is a local stream", function() {
            $stream = new Stream(fopen('php://temp', 'r+'));
            expect($stream->isLocal())->toBe(true);
        });

    });

    describe("->readable()", function() {

        it("returns `true` if the stream is readable", function() {

            foreach ($this->modes['r+'] as $mode) {
                if (strpos($mode, 'x') !== false) {
                    $this->filename .= 'bar';
                }
                $stream = new Stream(fopen('file://' . $this->filename, $mode));
                expect($stream->readable())->toBe(true);
                $stream->close();
            };

        });

        it("returns `false` if the stream is readable", function() {

            foreach ($this->modes['w'] as $mode) {
                if (strpos($mode, 'x') !== false) {
                    $this->filename .= 'bar';
                }
                $stream = new Stream(fopen('file://' . $this->filename, $mode));
                expect($stream->readable())->toBe(false);
                $stream->close();
            };

        });

    });

    describe("->writeable()", function() {

        it("returns `true` if the stream is writable", function() {

            foreach ($this->modes['w+'] as $mode) {
                if (strpos($mode, 'x') !== false) {
                    $this->filename .= 'bar';
                }
                $stream = new Stream(fopen('file://' . $this->filename, $mode));
                expect($stream->writable())->toBe(true);
                $stream->close();
            };

        });

        it("returns `false` if the stream is writable", function() {

            $stream = new Stream(fopen('file://' . $this->filename, 'r'));
            expect($stream->writable())->toBe(false);
            $stream->close();

        });

    });

    describe("->seekable()", function() {

        it("returns `true` if the stream is seekable", function() {

            $stream = new Stream(fopen('file://' . $this->filename, 'r'));
            expect($stream->seekable())->toBe(true);
            $stream->close();

        });

        it("returns `false` if the stream is not seekable", function() {

            $stream = new Stream(fopen('php://output', 'r'));
            expect($stream->seekable())->toBe(false);

        });

    });

    describe("->valid()", function() {

        it("returns `true` if the stream is valid", function() {
            $stream = new Stream(fopen('php://output', 'r'));
            expect($stream->valid())->toBe(true);
        });

        it("returns `true` if the stream is not valid", function() {
            $stream = new Stream(fopen('php://output', 'r'));
            $stream->close();
            expect($stream->valid())->toBe(false);
        });

    });

    describe("->bufferSize()", function() {

        it("changes the buffer size to 4096 by default", function() {
            $stream = new Stream(fopen('php://temp', 'r+'));
            expect($stream->bufferSize())->toBe(4096);

            $stream->bufferSize(100);
            expect($stream->bufferSize())->toBe(100);
        });

    });

    describe("->read()", function() {

        it("throws an exception if the stream is invalid", function() {

            $closure = function() {
                $stream = new Stream(fopen('php://temp', 'r+'));
                $stream->close();
                $stream->read();
            };

            expect($closure)->toThrow(new StreamException('Cannot read from a closed stream'));
        });

        it("throws an exception on a non readable stream", function() {

            $closure = function() {
                $stream = new Stream(fopen('file://' . $this->filename, 'w'));
                $stream->read();
            };

            expect($closure)->toThrow(new StreamException("~Cannot read on a non-readable stream~"));
        });

        it("throws an exception if an error occured", function() {

            Monkey::patch('fread', function() {
                return false;
            });

            $closure = function() {
                $stream = new Stream(fopen('php://temp', 'r+'));
                $stream->read();
            };

            expect($closure)->toThrow(new StreamException('Cannot read stream'));
        });

        it("reads data from the stream", function() {

            $handle = fopen('php://temp', 'r+');
            fwrite($handle, 'foo');
            rewind($handle);
            $stream = new Stream($handle);
            expect($stream->read())->toBe('foo');
            expect($stream->valid())->toBe(true);

        });

        it("reads data from the stream regardless of buffer size", function() {

            $handle = fopen('php://temp', 'r+');
            fwrite($handle, 'foo');
            rewind($handle);
            $stream = new Stream($handle);
            $stream->bufferSize(1);
            expect($stream->read())->toBe('f');
            expect($stream->valid())->toBe(true);

        });

        it("reads only a specified number of character", function() {

            $handle = fopen('php://temp', 'r+');
            fwrite($handle, 'foo');
            rewind($handle);
            $stream = new Stream($handle);
            expect($stream->read(2))->toBe('fo');
            expect($stream->valid())->toBe(true);

        });

        it("reads continuously", function() {

            $handle = fopen('php://temp', 'r+');
            fwrite($handle, 'foo bar');
            rewind($handle);
            $stream = new Stream($handle);
            expect($stream->read(2))->toBe('fo');
            expect($stream->read(2))->toBe('o ');
            expect($stream->read())->toBe('bar');
            expect($stream->valid())->toBe(true);

        });

        it("returns `false` at the end of the stream", function() {

            $handle = fopen('php://temp', 'r+');
            fwrite($handle, 'foo');
            rewind($handle);
            $stream = new Stream($handle);
            expect($stream->read(3))->toBe('foo');
            expect($stream->read())->toBe(false);
            expect($stream->valid())->toBe(true);

        });

    });

    describe("->getLine()", function() {

        it("throws an exception if the stream is invalid", function() {

            $closure = function() {
                $stream = new Stream(fopen('php://temp', 'r+'));
                $stream->close();
                $stream->getLine();
            };

            expect($closure)->toThrow(new StreamException('Cannot read from a closed stream'));
        });

        it("throws an exception on a non readable stream", function() {

            $closure = function() {
                $stream = new Stream(fopen('file://' . $this->filename, 'w'));
                $stream->getLine();
            };

            expect($closure)->toThrow(new StreamException("~Cannot read on a non-readable stream~"));
        });

        it("reads data from the stream", function() {

            $handle = fopen('php://temp', 'r+');
            fwrite($handle, 'foo');
            rewind($handle);
            $stream = new Stream($handle);
            expect($stream->getLine())->toBe('foo');
            expect($stream->valid())->toBe(true);

        });

        it("reads data from the stream regardless of buffer size", function() {

            $handle = fopen('php://temp', 'r+');
            fwrite($handle, 'foo');
            rewind($handle);
            $stream = new Stream($handle);
            $stream->bufferSize(1);
            expect($stream->getLine())->toBe('f');
            expect($stream->valid())->toBe(true);

        });

        it("reads only a specified number of character", function() {

            $handle = fopen('php://temp', 'r+');
            fwrite($handle, "foo\nbar");
            rewind($handle);
            $stream = new Stream($handle);
            expect($stream->getLine(2))->toBe('fo');
            expect($stream->valid())->toBe(true);

        });

        it("stops at new line", function() {

            $handle = fopen('php://temp', 'r+');
            fwrite($handle, "foo\nbar");
            rewind($handle);
            $stream = new Stream($handle);
            expect($stream->getLine())->toBe('foo');
            expect($stream->valid())->toBe(true);

        });

        it("stops at a custom character", function() {

            $handle = fopen('php://temp', 'r+');
            fwrite($handle, "foobar");
            rewind($handle);
            $stream = new Stream($handle);
            expect($stream->getLine(null, 'b'))->toBe('foo');
            expect($stream->valid())->toBe(true);

        });

        it("reads continuously", function() {

            $handle = fopen('php://temp', 'r+');
            fwrite($handle, "foo\nbar");
            rewind($handle);
            $stream = new Stream($handle);
            expect($stream->getLine())->toBe('foo');
            expect($stream->getLine())->toBe('bar');
            expect($stream->valid())->toBe(true);

        });

        it("returns `false` when the stream is empty", function() {

            $handle = fopen('php://temp', 'r+');
            fwrite($handle, 'foo');
            rewind($handle);
            $stream = new Stream($handle);
            expect($stream->getLine(3))->toBe('foo');
            expect($stream->getLine())->toBe(false);
            expect($stream->valid())->toBe(true);

        });

    });

    describe("->seek()", function() {

        it("throws an exception if the stream is invalid", function() {

            $closure = function() {
                $stream = new Stream(fopen('php://temp', 'r+'));
                $stream->close();
                $stream->seek(3);
            };

            expect($closure)->toThrow(new StreamException('Cannot seek on a closed stream'));
        });

        it("throws an exception if the stream is invalid", function() {

            $closure = function() {
                $stream = new Stream(fopen('php://output', 'r'));
                $stream->seek(3);
            };

            expect($closure)->toThrow(new StreamException('Cannot seek on a non-seekable stream'));
        });

        it("seeks to a specified position", function() {

            $handle = fopen('file://' . $this->filename, 'w+');
            fwrite($handle, 'foobar');
            rewind($handle);
            $stream = new Stream($handle);
            $stream->seek(3);
            expect($stream->read(3))->toBe('bar');
            expect($stream->valid())->toBe(true);

        });

    });

    describe("->offset()", function() {

        it("seeks to a specified position", function() {

            $handle = fopen('file://' . $this->filename, 'w+');
            fwrite($handle, 'foobar');
            rewind($handle);
            $stream = new Stream($handle);
            $stream->seek(3);
            expect($stream->offset())->toBe(3);

        });

    });

    describe("->eof()", function() {

        it("throws an exception if the stream is invalid", function() {

            $closure = function() {
                $stream = new Stream(fopen('php://temp', 'r+'));
                $stream->close();
                $stream->eof();
            };

            expect($closure)->toThrow(new StreamException('Cannot read from a closed stream'));
        });

        it("returns `false` if the end of the stream has not been reached", function() {

            $handle = fopen('php://temp', 'r+');
            fwrite($handle, 'foobar');
            rewind($handle);
            $stream = new Stream($handle);
            expect($stream->eof())->toBe(false);

        });

        it("returns `true` if the end of the stream has been reached", function() {

            $handle = fopen('php://temp', 'r+');
            fwrite($handle, 'foobar');
            rewind($handle);
            $stream = new Stream($handle);
            $stream->read();
            expect($stream->eof())->toBe(true);

        });

    });

    describe("->content()", function() {

        it("reads the remaining data from the stream.", function() {

            $handle = fopen('php://temp', 'r+');
            fwrite($handle, 'foobar');
            rewind($handle);
            $stream = new Stream($handle);
            $stream->bufferSize(1);
            expect($stream->content())->toBe('foobar');
            expect($stream->valid())->toBe(true);

        });

    });

    describe("->write()", function() {

        it("throws an exception if the stream is invalid", function() {

            $closure = function() {
                $stream = new Stream(fopen('php://temp', 'w+'));
                $stream->close();
                $stream->write('foo');
            };

            expect($closure)->toThrow(new StreamException('Cannot write on a closed stream'));
        });

        it("throws an exception on a non writable stream", function() {

            $closure = function() {
                $stream = new Stream(fopen('php://temp', 'r'));
                $stream->write('foo');
            };

            expect($closure)->toThrow(new StreamException("~Cannot write on a non-writable stream~"));
        });

        it("writes data to the stream", function() {

            $handle = fopen('php://temp', 'w+');
            $stream = new Stream($handle);
            $actual = $stream->write('foo');
            expect($actual)->toBe(3);

            $stream->rewind();
            expect($stream->read())->toBe('foo');
            expect($stream->valid())->toBe(true);

        });

        it("writes only a specified number of character", function() {

            $handle = fopen('php://temp', 'w+');
            $stream = new Stream($handle);
            $actual = $stream->write('foo', 2);
            expect($actual)->toBe(2);

            $stream->rewind();
            expect($stream->read())->toBe('fo');
            expect($stream->valid())->toBe(true);

        });

    });

    describe("->pipe()", function() {

        it("pipes on stream to another", function() {

            $handle1 = fopen('php://temp', 'w+');
            fwrite($handle1, 'foobar');
            rewind($handle1);
            $stream1 = new Stream($handle1);
            $handle2 = fopen('php://temp', 'w+');
            $stream2 = new Stream($handle2);
            $actual = $stream1->pipe($stream2);
            expect($actual)->toBe(6);

            $stream2->rewind();
            expect($stream2->read())->toBe('foobar');

        });

    });

    describe("close()", function() {

        it("closes the stream", function() {

            $stream = new Stream(fopen('php://temp', 'r+'));
            expect($stream->close())->toBe(true);
            expect($stream->valid())->toBe(false);
            expect($stream->close())->toBe(false);

        });

    });

});

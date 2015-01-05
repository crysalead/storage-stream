<?php
namespace storage\stream\spec\suite;

use dir\Dir;
use storage\stream\Stream;
use storage\stream\StreamException;

use kahlan\plugin\Stub;

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
        stream_wrapper_register('lorem', 'storage\stream\spec\mock\LoremWrapper');
    });

    afterEach(function() {
        Dir::remove($this->temp, ['recursive' => true]);
        stream_wrapper_unregister('lorem');
    });

    describe("->__construct()", function() {

        it("creates a stream from a string", function() {

            $stream = new Stream(['data' => 'HelloWorld']);
            expect($stream->read(5))->toBe('Hello');
            expect($stream->read())->toBe('World');
            expect($stream->valid())->toBe(true);
            expect($stream->eof())->toBe(true);
            $stream->close();

        });

    });

    describe("->resource()", function() {

        it("throws an exception if the passed resource is not a valid resource", function() {

            $closure = function() {
                $stream = new Stream(['resource' => []]);
                Stub::on($stream)->method('valid')->andReturn(false);
                $stream->resource();
            };

            expect($closure)->toThrow(new StreamException('Invalid resource'));

        });


        it("returns the resource", function() {

            $handle = fopen('php://temp', 'r+');
            $stream = new Stream(['data' => $handle]);
            expect($stream->resource())->toBe($handle);
            $stream->close();

        });

    });

    describe("->meta()", function() {

        it("returns the meta data", function() {

            $stream = new Stream(['data' => fopen('php://temp', 'r+')]);
            $meta = $stream->meta();

            expect($meta['uri'])->toBe('php://temp');
            expect($meta['wrapper_type'])->toBe('PHP');
            expect($meta['stream_type'])->toBe('TEMP');
            expect($stream->readable())->toBe(true);
            expect($stream->writable())->toBe(true);
            expect($meta['unread_bytes'])->toBe(0);
            expect($meta['seekable'])->toBe(true);
            $stream->close();

        });

        it("returns a specitic meta data entry", function() {

            $stream = new Stream(['data' => fopen('php://temp', 'r+')]);
            expect($stream->meta('stream_type'))->toBe('TEMP');
            $stream->close();

        });

        it("returns `null` for unexisting meta data entry", function() {

            $stream = new Stream(['data' => fopen('php://temp', 'r+')]);
            expect($stream->meta('unexisting'))->toBe(null);
            $stream->close();

        });

    });

    describe("->isLocal()", function() {

        it("returns `true` if the stream is a local stream", function() {

            $stream = new Stream(['data' => fopen('php://temp', 'r+')]);
            expect($stream->isLocal())->toBe(true);
            $stream->close();

        });

    });

    describe("->readable()", function() {

        it("returns `true` if the stream is readable", function() {

            foreach ($this->modes['r+'] as $mode) {
                if (strpos($mode, 'x') !== false) {
                    $this->filename .= 'bar';
                }
                $stream = new Stream(['data' => fopen('file://' . $this->filename, $mode)]);
                expect($stream->readable())->toBe(true);
                $stream->close();
            };

        });

        it("returns `false` if the stream is readable", function() {

            foreach ($this->modes['w'] as $mode) {
                if (strpos($mode, 'x') !== false) {
                    $this->filename .= 'bar';
                }
                $stream = new Stream(['data' => fopen('file://' . $this->filename, $mode)]);
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
                $stream = new Stream(['data' => fopen('file://' . $this->filename, $mode)]);
                expect($stream->writable())->toBe(true);
                $stream->close();
            };

        });

        it("returns `false` if the stream is writable", function() {

            $stream = new Stream(['data' => fopen('file://' . $this->filename, 'r')]);
            expect($stream->writable())->toBe(false);
            $stream->close();

        });

    });

    describe("->seekable()", function() {

        it("returns `true` if the stream is seekable", function() {

            $stream = new Stream(['data' => fopen('file://' . $this->filename, 'r')]);
            expect($stream->seekable())->toBe(true);
            $stream->close();

        });

        it("returns `false` if the stream is not seekable", function() {

            $stream = new Stream(['data' => fopen('php://output', 'r')]);
            expect($stream->seekable())->toBe(false);
            $stream->close();

        });

    });

    describe("->bufferSize()", function() {

        it("changes the buffer size to 4096 by default", function() {
            $stream = new Stream(['data' => fopen('php://temp', 'r+')]);
            expect($stream->bufferSize())->toBe(4096);

            $stream->bufferSize(100);
            expect($stream->bufferSize())->toBe(100);
            $stream->close();

        });

    });

    describe("->read()", function() {

        it("throws an exception if the stream is invalid", function() {

            $closure = function() {
                $stream = new Stream(['data' => fopen('php://temp', 'r+')]);
                $stream->close();
                $stream->read();
            };

            expect($closure)->toThrow(new StreamException('Cannot read from a closed stream'));
        });

        it("throws an exception on a non readable stream", function() {

            $closure = function() {
                $stream = new Stream(['data' => fopen('file://' . $this->filename, 'w')]);
                $stream->read();
            };

            expect($closure)->toThrow(new StreamException("~Cannot read on a non-readable stream~"));
        });

        it("reads data from the stream", function() {

            $handle = fopen('php://temp', 'r+');
            fwrite($handle, 'foo');
            rewind($handle);
            $stream = new Stream(['data' => $handle]);
            expect($stream->read())->toBe('foo');
            expect($stream->valid())->toBe(true);
            $stream->close();

        });

        it("reads data from the stream regardless of buffer size", function() {

            $handle = fopen('php://temp', 'r+');
            fwrite($handle, 'foo');
            rewind($handle);
            $stream = new Stream(['data' => $handle]);
            $stream->bufferSize(1);
            expect($stream->read())->toBe('f');
            expect($stream->valid())->toBe(true);
            $stream->close();

        });

        it("reads only a specified number of character", function() {

            $handle = fopen('php://temp', 'r+');
            fwrite($handle, 'foo');
            rewind($handle);
            $stream = new Stream(['data' => $handle]);
            expect($stream->read(2))->toBe('fo');
            expect($stream->valid())->toBe(true);
            $stream->close();

        });

        it("reads continuously", function() {

            $handle = fopen('php://temp', 'r+');
            fwrite($handle, 'foo bar');
            rewind($handle);
            $stream = new Stream(['data' => $handle]);
            expect($stream->read(2))->toBe('fo');
            expect($stream->read(2))->toBe('o ');
            expect($stream->read())->toBe('bar');
            expect($stream->valid())->toBe(true);
            $stream->close();

        });

        it("returns `false` at the end of the stream", function() {

            $handle = fopen('php://temp', 'r+');
            fwrite($handle, 'foo');
            rewind($handle);
            $stream = new Stream(['data' => $handle]);
            expect($stream->read(3))->toBe('foo');
            expect($stream->read())->toBe('');
            expect($stream->valid())->toBe(true);
            $stream->close();

        });

    });

    describe("->getLine()", function() {

        it("throws an exception if the stream is invalid", function() {

            $closure = function() {
                $stream = new Stream(['data' => fopen('php://temp', 'r+')]);
                $stream->close();
                $stream->getLine();
            };

            expect($closure)->toThrow(new StreamException('Cannot read from a closed stream'));
        });

        it("throws an exception on a non readable stream", function() {

            $closure = function() {
                $stream = new Stream(['data' => fopen('file://' . $this->filename, 'w')]);
                $stream->getLine();
            };

            expect($closure)->toThrow(new StreamException("~Cannot read on a non-readable stream~"));
        });

        it("reads data from the stream", function() {

            $handle = fopen('php://temp', 'r+');
            fwrite($handle, 'foo');
            rewind($handle);
            $stream = new Stream(['data' => $handle]);
            expect($stream->getLine())->toBe('foo');
            expect($stream->valid())->toBe(true);
            $stream->close();

        });

        it("reads data from the stream regardless of buffer size", function() {

            $handle = fopen('php://temp', 'r+');
            fwrite($handle, 'foo');
            rewind($handle);
            $stream = new Stream(['data' => $handle]);
            $stream->bufferSize(1);
            expect($stream->getLine())->toBe('f');
            expect($stream->valid())->toBe(true);
            $stream->close();

        });

        it("checks buffer size is equal to 4096 by default", function() {

            $stream = new Stream(['data' => fopen('lorem://localhost', 'w+')]);
            expect(strlen($stream->read()))->toBe(4096);
            $stream->close();

        });

        it("can reads more than the buffer size limit when explicitly defined", function() {

            $stream = new Stream(['data' => fopen('lorem://localhost', 'w+')]);
            expect(strlen($stream->read(8192)))->toBe(8192);
            $stream->close();

        });

        it("reads only a specified number of character", function() {

            $handle = fopen('php://temp', 'r+');
            fwrite($handle, "foo\nbar");
            rewind($handle);
            $stream = new Stream(['data' => $handle]);
            expect($stream->getLine(2))->toBe('fo');
            expect($stream->valid())->toBe(true);
            $stream->close();

        });

        it("stops at new line", function() {

            $handle = fopen('php://temp', 'r+');
            fwrite($handle, "foo\nbar");
            rewind($handle);
            $stream = new Stream(['data' => $handle]);
            expect($stream->getLine())->toBe('foo');
            expect($stream->valid())->toBe(true);
            $stream->close();

        });

        it("stops at a custom character", function() {

            $handle = fopen('php://temp', 'r+');
            fwrite($handle, "foobar");
            rewind($handle);
            $stream = new Stream(['data' => $handle]);
            expect($stream->getLine(null, 'b'))->toBe('foo');
            expect($stream->valid())->toBe(true);
            $stream->close();

        });

        it("reads continuously", function() {

            $handle = fopen('php://temp', 'r+');
            fwrite($handle, "foo\nbar");
            rewind($handle);
            $stream = new Stream(['data' => $handle]);
            expect($stream->getLine())->toBe('foo');
            expect($stream->getLine())->toBe('bar');
            expect($stream->valid())->toBe(true);
            $stream->close();

        });

        it("returns `false` when the stream is empty", function() {

            $handle = fopen('php://temp', 'r+');
            fwrite($handle, 'foo');
            rewind($handle);
            $stream = new Stream(['data' => $handle]);
            expect($stream->getLine(3))->toBe('foo');
            expect($stream->getLine())->toBe('');
            expect($stream->valid())->toBe(true);
            $stream->close();

        });

    });

    describe("->write()", function() {

        it("throws an exception if the stream is invalid", function() {

            $closure = function() {
                $stream = new Stream(['data' => fopen('php://temp', 'w+')]);
                $stream->close();
                $stream->write('foo');
            };

            expect($closure)->toThrow(new StreamException('Cannot write on a closed stream'));
        });

        it("throws an exception on a non writable stream", function() {

            $closure = function() {
                $stream = new Stream(['data' => fopen('php://temp', 'r')]);
                $stream->write('foo');
            };

            expect($closure)->toThrow(new StreamException("~Cannot write on a non-writable stream~"));
        });

        it("writes data to the stream", function() {

            $handle = fopen('php://temp', 'w+');
            $stream = new Stream(['data' => $handle]);
            $actual = $stream->write('foo');
            expect($actual)->toBe(3);

            $stream->rewind();
            expect($stream->read())->toBe('foo');
            expect($stream->valid())->toBe(true);
            $stream->close();

        });

        it("writes only a specified number of character", function() {

            $handle = fopen('php://temp', 'w+');
            $stream = new Stream(['data' => $handle]);
            $actual = $stream->write('foo', 2);
            expect($actual)->toBe(2);

            $stream->rewind();
            expect($stream->read())->toBe('fo');
            expect($stream->valid())->toBe(true);
            $stream->close();

        });

    });

    describe("->pipe()", function() {

        it("pipes on stream to another", function() {

            $handle1 = fopen('php://temp', 'w+');
            fwrite($handle1, 'foobar');
            rewind($handle1);
            $stream1 = new Stream(['data' => $handle1]);
            $handle2 = fopen('php://temp', 'w+');
            $stream2 = new Stream(['data' => $handle2]);
            $actual = $stream1->pipe($stream2);
            expect($actual)->toBe(6);
            $stream1->close();

            $stream2->rewind();
            expect($stream2->read())->toBe('foobar');
            $stream2->close();

        });

    });

    describe("->flush()", function() {

        it("reads the remaining data from the stream", function() {

            $handle = fopen('php://temp', 'r+');
            fwrite($handle, 'foobar');
            rewind($handle);
            $stream = new Stream(['data' => $handle]);
            $stream->bufferSize(1);
            expect($stream->read(3))->toBe('foo');
            expect($stream->flush())->toBe('bar');
            expect($stream->valid())->toBe(true);
            $stream->close();

        });

    });

    describe("->timeout()", function() {

        it("throws an exception if the stream is invalid", function() {

            $closure = function() {
                $stream = new Stream(['data' => []]);
                Stub::on($stream)->method('valid')->andReturn(false);
                $stream->timeout(5000);
            };

            expect($closure)->toThrow(new StreamException('Invalid stream resource, unable to set a timeout on it'));
        });

        it("sets a timeout", function() {

            $stream = new Stream(['data' => fopen('lorem://localhost', 'w+')]);
            $stream->timeout(5000);
            expect($stream->timeout())->toBe(5000);
            $stream->close();

        });

    });

    describe("->seek()", function() {

        it("throws an exception if the stream is invalid", function() {

            $closure = function() {
                $stream = new Stream(['data' => fopen('php://temp', 'r+')]);
                $stream->close();
                $stream->seek(3);
            };

            expect($closure)->toThrow(new StreamException('Cannot seek on a closed stream'));
        });

        it("throws an exception if the stream is invalid", function() {

            $closure = function() {
                $stream = new Stream(['data' => fopen('php://output', 'r')]);
                $stream->seek(3);
            };

            expect($closure)->toThrow(new StreamException('Cannot seek on a non-seekable stream'));
        });

        it("seeks to a specified position", function() {

            $handle = fopen('file://' . $this->filename, 'w+');
            fwrite($handle, 'foobar');
            rewind($handle);
            $stream = new Stream(['data' => $handle]);
            $stream->seek(3);
            expect($stream->read(3))->toBe('bar');
            expect($stream->valid())->toBe(true);
            $stream->close();

        });

    });

    describe("->offset()", function() {

        it("seeks to a specified position", function() {

            $handle = fopen('file://' . $this->filename, 'w+');
            fwrite($handle, 'foobar');
            rewind($handle);
            $stream = new Stream(['data' => $handle]);
            $stream->seek(3);
            expect($stream->offset())->toBe(3);
            $stream->close();

        });

    });

    describe("->valid()", function() {

        it("returns `true` if the stream is valid", function() {
            $stream = new Stream(['data' => fopen('php://output', 'r')]);
            expect($stream->valid())->toBe(true);
            $stream->close();
        });

        it("returns `true` if the stream is not valid", function() {
            $stream = new Stream(['data' => fopen('php://output', 'r')]);
            $stream->close();
            expect($stream->valid())->toBe(false);
        });

    });


    describe("->eof()", function() {

        it("throws an exception if the stream is invalid", function() {

            $closure = function() {
                $stream = new Stream(['data' => fopen('php://temp', 'r+')]);
                $stream->close();
                $stream->eof();
            };

            expect($closure)->toThrow(new StreamException('Cannot read from a closed stream'));
        });

        it("returns `false` if the end of the stream has not been reached", function() {

            $handle = fopen('php://temp', 'r+');
            fwrite($handle, 'foobar');
            rewind($handle);
            $stream = new Stream(['data' => $handle]);
            expect($stream->eof())->toBe(false);
            $stream->close();

        });

        it("returns `true` if the end of the stream has been reached", function() {

            $handle = fopen('php://temp', 'r+');
            fwrite($handle, 'foobar');
            rewind($handle);
            $stream = new Stream(['data' => $handle]);
            $stream->read();
            expect($stream->eof())->toBe(true);
            $stream->close();

        });

    });

    describe("->__toString()", function() {

        it("reads the remaining data from the stream.", function() {

            $handle = fopen('php://temp', 'r+');
            fwrite($handle, 'foobar');
            rewind($handle);
            $stream = new Stream(['data' => $handle]);
            $stream->bufferSize(1);
            expect((string) $stream)->toBe('foobar');
            $stream->close();

        });

    });

    describe("->close()", function() {

        it("closes the stream", function() {

            $stream = new Stream(['data' => fopen('php://temp', 'r+')]);
            expect($stream->close())->toBe(true);
            expect($stream->valid())->toBe(false);
            expect($stream->close())->toBe(false);

        });

    });

    describe("->size()", function() {

        it("returns -1 if the stream in not seekable.", function() {

            $handle = fopen('php://output', 'r');
            $stream = new Stream(['data' => $handle]);

            expect($stream->size())->toBe(-1);
            $stream->close();

        });

        it("returns the size of the stream.", function() {

            $handle = fopen('php://temp', 'r+');
            fwrite($handle, 'foobar');
            $stream = new Stream(['data' => $handle]);

            expect($stream->size())->toBe(6);
            rewind($handle);
            expect($stream->size())->toBe(6);
            $stream->close();
        });

    });

    describe("->mime()", function() {

        it("returns the default mime", function() {

            $stream = new Stream();
            expect($stream->mime())->toBe('application/octet-stream');
            $stream->close();

        });

        it("returns the default mime for empty data", function() {

            $stream = new Stream(['mime' => true]);
            expect($stream->mime())->toBe('application/octet-stream');
            $stream->close();

        });

        it("returns the setted mime", function() {

            $stream = new Stream(['mime' => 'application/json']);
            expect($stream->mime())->toBe('application/json');
            $stream->close();

        });

        it("returns the default mime when the stream is not readable", function() {

            $stream = new Stream(['data' => fopen('php://temp', 'w')]);
            expect($stream->mime())->toBe('application/octet-stream');
            $stream->close();

        });

        it("returns the default mime when the stream is not seekable", function() {

            $stream = new Stream(['data' => fopen('php://output', 'r')]);
            expect($stream->mime())->toBe('application/octet-stream');
            $stream->close();

        });

        it("returns the plain text mime with a simple string", function() {

            $stream = new Stream([
                'data' => 'HelloWorld',
                'mime' => true
            ]);
            expect($stream->mime())->toBe('text/plain');
            $stream->close();

        });

        it("lazily autodetect the mime", function() {

            $stream = new Stream(['data' => 'HelloWorld']);
            expect($stream->mime())->toBe('application/octet-stream');
            $stream->mime(true);
            expect($stream->mime())->toBe('text/plain');
            $stream->close();

        });

        it("lazily sets the mime", function() {

            $stream = new Stream(['data' => 'HelloWorld']);
            expect($stream->mime())->toBe('application/octet-stream');
            $stream->mime('text/plain');
            expect($stream->mime())->toBe('text/plain');
            $stream->close();

        });

        it("keeps the stream position inchanged even with mime autodetection enabled", function() {

            $stream = new Stream([
                'data' => 'HelloWorld',
                'mime' => true
            ]);
            expect($stream->read())->toBe('HelloWorld');
            $stream->close();

        });

        it("returns the plain text mime", function() {

            $stream = new Stream([
                'data' => fopen('spec/fixture/helloworld.txt', 'r+'),
                'mime' => true
            ]);
            expect($stream->mime())->toBe('text/plain');
            $stream->close();

        });

        it("returns the odt mime", function() {

            $stream = new Stream([
                'data' => fopen('spec/fixture/helloworld.odt', 'r+'),
                'mime' => true
            ]);
            expect($stream->mime())->toBe('application/vnd.oasis.opendocument.text');
            $stream->close();

        });

        it("returns the gzip mime", function() {

            $stream = new Stream([
                'data' => fopen('spec/fixture/helloworld.txt.gz', 'r+'),
                'mime' => true
            ]);
            expect($stream->mime())->toBe('application/x-gzip');
            $stream->close();

        });

        it("returns the tar mime", function() {

            $stream = new Stream([
                'data' => fopen('spec/fixture/helloworld.tar', 'r+'),
                'mime' => true
            ]);
            expect($stream->mime())->toBe('application/x-tar');
            $stream->close();

        });

        it("returns the jpg mime", function() {

            $stream = new Stream([
                'data' => fopen('spec/fixture/favicon.jpg', 'r+'),
                'mime' => true
            ]);
            expect($stream->mime())->toBe('image/jpeg');
            $stream->close();

        });

        it("returns the png mime", function() {

            $stream = new Stream([
                'data' => fopen('spec/fixture/favicon.png', 'r+'),
                'mime' => true
            ]);
            expect($stream->mime())->toBe('image/png');
            $stream->close();

        });

        it("returns the gif mime", function() {

            $stream = new Stream([
                'data' => fopen('spec/fixture/favicon.gif', 'r+'),
                'mime' => true
            ]);
            expect($stream->mime())->toBe('image/gif');
            $stream->close();

        });

        it("returns the wav mime", function() {

            $stream = new Stream([
                'data' => fopen('spec/fixture/sound.wav', 'r+'),
                'mime' => true
            ]);
            expect($stream->mime())->toBe('audio/x-wav');
            $stream->close();

        });

    });

});

<?php
namespace Lead\Storage\Stream\Spec\Suite;

use RuntimeException;
use InvalidArgumentException;
use Lead\Dir\Dir;
use Lead\Storage\Stream\Stream;

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
        stream_wrapper_register('lorem', 'Lead\Storage\Stream\Spec\Mock\LoremWrapper');
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
            skipIf(!defined('HHVM_VERSION')); // Skip for PHP since https://bugs.php.net/bug.php?id=68948
            expect($stream->eof())->toBe(true);
            $stream->close();

        });

        it("creates a strema from a filename", function() {

            $stream = new Stream([
                'filename' => 'spec/Fixture/helloworld.txt',
                'mode'     => 'r+',
                'mime'     => true
            ]);
            expect($stream->mime())->toBe('text/plain');
            expect($stream->read())->toBe("Hello World!\n");
            $stream->close();

        });

        it("allows a non array as constructor", function() {

            $stream = new Stream('HelloWorld');
            expect($stream->read(5))->toBe('Hello');
            expect($stream->read())->toBe('World');
            expect($stream->valid())->toBe(true);
            skipIf(!defined('HHVM_VERSION')); // Skip for PHP since https://bugs.php.net/bug.php?id=68948
            expect($stream->eof())->toBe(true);
            $stream->close();

        });

        it("rewinds a stream according its start position", function() {

            $stream = new Stream([
                'data'  => 'foo bar baz',
                'start' => 4,
                'limit' => 3
            ]);

            expect($stream->tell())->toBe(4);
            $stream->close();

        });

        it("throws an exception if start is defined on a non seekable stream", function() {

            $closure = function() {
                allow(Stream::class)->toReceive('isSeekable')->andReturn(false);

                $stream = new Stream([
                    'data'  => 'foo bar baz',
                    'start' => 4,
                    'limit' => 3
                ]);
            };

            expect($closure)->toThrow(new RuntimeException("The `'start'` option can't be used with non seekable streams."));

        });

        it("throws an exception when both `'data` and `'filename'` are defined", function() {

            $closure = function() {
                new Stream(['data' => 'hello', 'filename' => 'myfile.tmp']);
            };

            expect($closure)->toThrow(new InvalidArgumentException("The `'data'` or `'filename'` option must be defined."));

        });

    });

    describe("->resource()", function() {

        it("throws an exception if the passed resource is not a valid resource", function() {

            $closure = function() {
                $stream = new Stream(['data' => []]);
                allow($stream)->toReceive('valid')->andReturn(false);
                $stream->resource();
            };

            expect($closure)->toThrow(new RuntimeException('Invalid resource.'));

        });

        it("returns the resource", function() {

            $handle = fopen('php://temp', 'r+');
            $stream = new Stream(['data' => $handle]);
            expect($stream->resource())->toBe($handle);
            $stream->close();

        });

    });

    describe("->start()", function() {

        it("get/sets the offset start", function() {
            $stream = new Stream(['data' => 'foo bar']);
            expect($stream->start())->toBe(0);

            expect($stream->start(4))->toBe(4);
            expect($stream->start())->toBe(4);
            $stream->close();

        });

    });

    describe("->limit()", function() {

        it("get/sets the stream limit", function() {
            $stream = new Stream(['data' => 'foo bar']);
            expect($stream->limit())->toBe(null);

            expect($stream->limit(3))->toBe(3);
            expect($stream->limit())->toBe(3);
            $stream->close();

        });

    });

    describe("->range()", function() {

        it("get/sets the range", function() {
            $stream = new Stream(['data' => 'foo bar']);
            expect($stream->range())->toBe('0-');

            expect($stream->range('3-'))->toBe('3-');
            expect($stream->start())->toBe(3);
            expect($stream->limit())->toBe(null);

            expect($stream->range('1-3'))->toBe('1-3');
            expect($stream->start())->toBe(1);
            expect($stream->limit())->toBe(2);

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
            expect($stream->isReadable())->toBe(true);
            expect($stream->isWritable())->toBe(true);
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

    describe("->isReadable()", function() {

        it("returns `true` if the stream is readable", function() {

            foreach ($this->modes['r+'] as $mode) {
                if (strpos($mode, 'x') !== false) {
                    $this->filename .= 'bar';
                }
                $stream = new Stream(['data' => fopen('file://' . $this->filename, $mode)]);
                expect($stream->isReadable())->toBe(true);
                $stream->close();
            };

        });

        it("returns `false` if the stream is readable", function() {

            foreach ($this->modes['w'] as $mode) {
                if (strpos($mode, 'x') !== false) {
                    $this->filename .= 'bar';
                }
                $stream = new Stream(['data' => fopen('file://' . $this->filename, $mode)]);
                expect($stream->isReadable())->toBe(false);
                $stream->close();
            };

        });

    });

    describe("->isWritable()", function() {

        it("returns `true` if the stream is writable", function() {

            foreach ($this->modes['w+'] as $mode) {
                if (strpos($mode, 'x') !== false) {
                    $this->filename .= 'bar';
                }
                $stream = new Stream(['data' => fopen('file://' . $this->filename, $mode)]);
                expect($stream->isWritable())->toBe(true);
                $stream->close();
            };

        });

        it("returns `false` if the stream is not writable", function() {

            $stream = new Stream(['data' => fopen('file://' . $this->filename, 'r')]);
            expect($stream->isWritable())->toBe(false);
            $stream->close();

        });

    });

    describe("->isSeekable()", function() {

        it("returns `true` if the stream is seekable", function() {

            $stream = new Stream(['data' => fopen('file://' . $this->filename, 'r')]);
            expect($stream->isSeekable())->toBe(true);
            $stream->close();

        });

        it("returns `false` if the stream is not seekable", function() {

            $stream = new Stream(['data' => fopen('php://output', 'r')]);
            expect($stream->isSeekable())->toBe(false);
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

            expect($closure)->toThrow(new RuntimeException('Cannot read from a closed stream.'));
        });

        it("throws an exception on a non readable stream", function() {

            $stream = new Stream(['data' => fopen('file://' . $this->filename, 'w')]);

            $closure = function() use ($stream) {
                $stream->read();
            };
            expect($closure)->toThrow(new RuntimeException("~Cannot read on a non-readable stream~"));
            $stream->close();

        });

        it("reads data from the stream", function() {

            $stream = new Stream(['data' => 'foo']);
            expect($stream->read())->toBe('foo');
            expect($stream->valid())->toBe(true);
            $stream->close();

        });

        it("reads data from the stream regardless of buffer size", function() {

            $stream = new Stream(['data' => 'foo']);
            $stream->bufferSize(1);
            expect($stream->read())->toBe('f');
            expect($stream->valid())->toBe(true);
            $stream->close();

        });

        it("reads only a specified number of character", function() {

            $stream = new Stream(['data' => 'foo']);
            expect($stream->read(2))->toBe('fo');
            expect($stream->valid())->toBe(true);
            $stream->close();

        });

        it("reads continuously", function() {

            $stream = new Stream(['data' => 'foo bar']);
            expect($stream->read(2))->toBe('fo');
            expect($stream->read(2))->toBe('o ');
            expect($stream->read())->toBe('bar');
            expect($stream->valid())->toBe(true);
            $stream->close();

        });

        it("reads according a setted range", function() {

            $stream = new Stream([
                'data'  => 'foo bar baz',
                'start' => 4,
                'limit' => 3
            ]);
            expect($stream->read())->toBe('bar');
            expect($stream->read())->toBe('');
            expect($stream->valid())->toBe(true);
            expect($stream->eof())->toBe(true);
            $stream->close();

        });

        it("returns `false` at the end of the stream", function() {

            $stream = new Stream(['data' => 'foo']);
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

            expect($closure)->toThrow(new RuntimeException('Cannot read from a closed stream.'));
        });

        it("throws an exception on a non readable stream", function() {

            $stream = new Stream(['data' => fopen('file://' . $this->filename, 'w')]);

            $closure = function() use ($stream) {
                $stream->getLine();
            };

            expect($closure)->toThrow(new RuntimeException("~Cannot read on a non-readable stream~"));
            $stream->close();

        });

        it("reads data from the stream", function() {

            $stream = new Stream(['data' => 'foo']);
            expect($stream->getLine())->toBe('foo');
            expect($stream->valid())->toBe(true);
            $stream->close();

        });

        it("reads data from the stream regardless of buffer size", function() {

            $stream = new Stream(['data' => 'foo']);
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

            $stream = new Stream(['data' => "foo\nbar"]);
            expect($stream->getLine(2))->toBe('fo');
            expect($stream->valid())->toBe(true);
            $stream->close();

        });

        it("stops at new line", function() {

            $stream = new Stream(['data' => "foo\nbar"]);
            expect($stream->getLine())->toBe('foo');
            expect($stream->valid())->toBe(true);
            $stream->close();

        });

        it("stops at a custom character", function() {

            $stream = new Stream(['data' => "foobar"]);
            expect($stream->getLine(null, 'b'))->toBe('foo');
            expect($stream->valid())->toBe(true);
            $stream->close();

        });

        it("reads continuously", function() {

            $stream = new Stream(['data' => "foo\nbar"]);
            expect($stream->getLine())->toBe('foo');
            expect($stream->getLine())->toBe('bar');
            expect($stream->valid())->toBe(true);
            $stream->close();

        });

        it("reads according a setted range", function() {

            $stream = new Stream([
                'data'  => "foo\nbar\nbaz",
                'start' => 4,
                'limit' => 3
            ]);
            expect($stream->getLine())->toBe('bar');
            expect($stream->getLine())->toBe('');
            expect($stream->valid())->toBe(true);
            $stream->close();

        });

        it("returns `false` when the stream is empty", function() {

            $stream = new Stream(['data' => 'foo']);
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

            expect($closure)->toThrow(new RuntimeException('Cannot write on a closed stream.'));
        });

        it("throws an exception on a non writable stream", function() {

            $stream = new Stream(['data' => fopen('php://temp', 'r')]);

            $closure = function() use ($stream) {
                $stream->write('foo');
            };

            expect($closure)->toThrow(new RuntimeException("~Cannot write on a non-writable stream~"));
            $stream->close();

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

        it("overwrites data", function() {

            $stream = new Stream(['data' => 'foobar']);
            $actual = $stream->write('baz');
            expect($actual)->toBe(3);
            expect((string) $stream)->toBe('bazbar');
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

    describe("->append()", function() {

        it("appends data to the stream", function() {

            $stream = new Stream(['data' => 'foobar']);
            $actual = $stream->append('baz');
            expect($actual)->toBe(3);
            expect((string) $stream)->toBe('foobarbaz');
            $stream->close();

        });

    });

    describe("->push()", function() {

        it("throws an exception if the stream is invalid", function() {

            $closure = function() {
                $stream = new Stream(['data' => fopen('php://temp', 'w+')]);
                $stream->close();
                $stream->push('foo');
            };

            expect($closure)->toThrow(new RuntimeException('Cannot write on a closed stream.'));
        });

        it("throws an exception on a non writable stream", function() {

            $stream = new Stream(['data' => fopen('php://temp', 'r')]);

            $closure = function() use ($stream) {
                $stream->push('foo');
            };

            expect($closure)->toThrow(new RuntimeException("~Cannot write on a non-writable stream~"));
            $stream->close();

        });

        it("writes data to the stream", function() {

            $handle = fopen('php://temp', 'w+');
            $stream = new Stream(['data' => $handle]);
            $actual = $stream->push('foo');
            expect($actual)->toBe(3);

            expect($stream->read())->toBe('foo');
            expect($stream->valid())->toBe(true);
            $stream->close();

        });

        it("writes only a specified number of character", function() {

            $handle = fopen('php://temp', 'w+');
            $stream = new Stream(['data' => $handle]);
            $actual = $stream->push('foo', 2);
            expect($actual)->toBe(2);

            expect($stream->read())->toBe('fo');
            expect($stream->valid())->toBe(true);
            $stream->close();

        });

    });

    describe("->pipe()", function() {

        it("pipes on stream to another", function() {

            $stream1 = new Stream(['data' => 'foobar']);
            $handle2 = fopen('php://temp', 'w+');
            $stream2 = new Stream();
            $actual = $stream1->pipe($stream2);
            expect($actual)->toBe(6);
            $stream1->close();

            expect($stream2->read())->toBe('foobar');
            $stream2->close();

        });

    });

    describe("->flush()", function() {

        it("reads the remaining data from the stream", function() {

            $stream = new Stream(['data' => 'foobar']);
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
                allow($stream)->toReceive('valid')->andReturn(false);
                $stream->timeout(5000);
            };

            expect($closure)->toThrow(new RuntimeException('Invalid stream resource, unable to set a timeout on it.'));
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

            expect($closure)->toThrow(new RuntimeException('Cannot seek on a closed stream.'));
        });

        it("throws an exception if the stream is invalid", function() {

            $stream = new Stream(['data' => fopen('php://output', 'r')]);

            $closure = function() use ($stream) {
                $stream->seek(3);
            };

            expect($closure)->toThrow(new RuntimeException('Cannot seek on a non-seekable stream.'));
            $stream->close();

        });

        it("seeks to a specified position", function() {

            $stream = new Stream(['data' => 'foobar']);
            $stream->seek(3);
            expect($stream->read(3))->toBe('bar');
            expect($stream->valid())->toBe(true);
            $stream->close();

        });

    });

    describe("->rewind()", function() {

        it("rewinds a stream", function() {

            $stream = new Stream(['data' => 'foo bar']);

            expect($stream->read())->toBe('foo bar');
            expect($stream->read())->toBe('');

            expect($stream->rewind())->toBe(0);
            expect($stream->read())->toBe('foo bar');
            $stream->close();

        });

        it("rewinds a stream according the range constraint", function() {

            $stream = new Stream([
                'data'  => 'foo bar baz',
                'start' => 4,
                'limit' => 3
            ]);

            expect($stream->read())->toBe('bar');
            expect($stream->read())->toBe('');

            expect($stream->rewind())->toBe(4);
            expect($stream->read())->toBe('bar');
            $stream->close();

        });

    });

    describe("->begin()", function() {

        it("aliases rewind()", function() {

            $stream = new Stream(['data' => 'foo bar']);

            expect($stream)->toReceive('rewind');

            $stream->begin();
            $stream->close();

        });

    });

    describe("->end()", function() {

        it("seeks to the end of the stream", function() {

            $stream = new Stream(['data' => 'foo bar baz']);

            expect($stream->end())->toBe(11);
            expect($stream->read())->toBe('');
            expect($stream->eof())->toBe(true);

            $stream->close();

        });

        it("seeks to the end of the stream according the range constraint", function() {

            $stream = new Stream([
                'data'  => 'foo bar baz',
                'start' => 4,
                'limit' => 3
            ]);

            expect($stream->end())->toBe(7);
            expect($stream->read())->toBe('');
            expect($stream->eof())->toBe(true);

            $stream->close();

        });

    });

    describe("->tell()", function() {

        it("seeks to a specified position", function() {

            $stream = new Stream(['data' => 'foobar']);
            $stream->seek(3);
            expect($stream->tell())->toBe(3);
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

            expect($closure)->toThrow(new RuntimeException('Cannot read from a closed stream.'));
        });

        it("returns `false` if the end of the stream has not been reached", function() {

            $stream = new Stream(['data' => 'foobar']);
            expect($stream->eof())->toBe(false);
            $stream->close();

        });

        it("returns `true` if the end of the stream has been reached", function() {

            $stream = new Stream(['data' => 'foobar']);
            $stream->read();
            skipIf(!defined('HHVM_VERSION')); // Skip for PHP since https://bugs.php.net/bug.php?id=68948
            expect($stream->eof())->toBe(true);
            $stream->close();

        });

    });

    describe("->toString()", function() {

        it("reads the remaining data from the stream", function() {

            $stream = new Stream(['data' => 'foobar']);
            $stream->bufferSize(1);
            expect((string) $stream)->toBe('foobar');
            $stream->close();

        });

        it("allows seekable stream to be read multiple times", function() {

            $stream = new Stream(['data' => 'foobar']);
            $stream->bufferSize(1);
            expect((string) $stream)->toBe('foobar');
            expect((string) $stream)->toBe('foobar');
            expect((string) $stream)->toBe('foobar');
            $stream->close();

        });

        it("restore seekable stream to the current offset", function() {

            $stream = new Stream(['data' => 'foobar']);
            $stream->bufferSize(1);
            expect($stream->read(3))->toBe('foo');
            expect((string) $stream)->toBe('foobar');
            expect((string) $stream)->toBe('foobar');
            expect((string) $stream)->toBe('foobar');
            expect($stream->read(3))->toBe('bar');
            $stream->close();

        });

        it("just flushes unseekable stream", function() {

            $stream = new Stream(['data' => 'foobar']);

            allow($stream)->toReceive('isSeekable')->andReturn(false);

            expect((string) $stream)->toBe('foobar');
            expect((string) $stream)->toBe('');
            $stream->close();

        });

    });

    describe("->__toString()", function() {

        it("delegates to `->toString()`", function() {

            $stream = new Stream(['data' => 'foobar']);

            expect($stream)->toReceive('toString');

            (string) $stream;

        });

    });

    describe("->detaches()", function() {

        it("detaches the stream", function() {

            $file = fopen('php://output', 'r');
            $stream = new Stream(['data' => $file]);
            expect($stream->detach())->toBe($file);
            expect($stream->valid())->toBe(false);
            expect($stream->close())->toBe(true);

        });

    });

    describe("->close()", function() {

        it("closes the stream", function() {

            $stream = new Stream();
            expect($stream->close())->toBe(true);
            expect($stream->valid())->toBe(false);
            expect($stream->close())->toBe(true);

        });

    });

    describe("->length()", function() {

        it("returns manualy setted length by default.", function() {

            $stream = new Stream([
                'data'   => 'foobar',
                'length' => 12
            ]);

            expect($stream->length())->toBe(12);
            $stream->rewind();

            expect($stream->length())->toBe(12);
            $stream->close();
        });

        it("returns the length of the stream.", function() {

            $stream = new Stream(['data' => 'foobar']);

            expect($stream->length())->toBe(6);
            $stream->rewind();

            expect($stream->length())->toBe(6);
            $stream->close();
        });

        it("returns the range limit when set.", function() {

            $stream = new Stream(['data' => 'foobar']);

            expect($stream->length())->toBe(6);
            expect($stream->range('1-3'))->toBe('1-3');
            expect($stream->length())->toBe(2);

            expect($stream->range('0-'))->toBe('0-');
            expect($stream->length())->toBe(6);

        });

        it("returns `null` if the stream in not seekable.", function() {

            $handle = fopen('php://output', 'r');
            $stream = new Stream(['data' => $handle]);

            expect($stream->length())->toBe(null);
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

        it("lazily sets the default mime", function() {

            $stream = new Stream(['data' => 'HelloWorld', 'mime' => true]);
            expect($stream->mime())->toBe('text/plain');
            $stream->mime(null);
            expect($stream->mime())->toBe('application/octet-stream');
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
                'data' => fopen('spec/Fixture/helloworld.txt', 'r+'),
                'mime' => true
            ]);
            expect($stream->mime())->toBe('text/plain');
            $stream->close();

        });

        it("returns the odt mime", function() {

            $stream = new Stream([
                'data' => fopen('spec/Fixture/helloworld.odt', 'r+'),
                'mime' => true
            ]);
            expect($stream->mime())->toBe('application/vnd.oasis.opendocument.text');
            $stream->close();

        });

        it("returns the gzip mime", function() {

            $stream = new Stream([
                'data' => fopen('spec/Fixture/helloworld.txt.gz', 'r+'),
                'mime' => true
            ]);
            expect($stream->mime())->toBe('application/x-gzip');
            $stream->close();

        });

        it("returns the tar mime", function() {

            $stream = new Stream([
                'data' => fopen('spec/Fixture/helloworld.tar', 'r+'),
                'mime' => true
            ]);
            expect($stream->mime())->toBe('application/x-tar');
            $stream->close();

        });

        it("returns the jpg mime", function() {

            $stream = new Stream([
                'data' => fopen('spec/Fixture/favicon.jpg', 'r+'),
                'mime' => true
            ]);
            expect($stream->mime())->toBe('image/jpeg');
            $stream->close();

        });

        it("returns the png mime", function() {

            $stream = new Stream([
                'data' => fopen('spec/Fixture/favicon.png', 'r+'),
                'mime' => true
            ]);
            expect($stream->mime())->toBe('image/png');
            $stream->close();

        });

        it("returns the gif mime", function() {

            $stream = new Stream([
                'data' => fopen('spec/Fixture/favicon.gif', 'r+'),
                'mime' => true
            ]);
            skipIf(version_compare(PHP_VERSION, '5.6.6', '<')); // Skip for PHP since https://bugs.php.net/bug.php?id=67647
            expect($stream->mime())->toBe('image/gif');
            $stream->close();

        });

        it("returns the wav mime", function() {

            $stream = new Stream([
                'data' => fopen('spec/Fixture/sound.wav', 'r+'),
                'mime' => true
            ]);
            expect($stream->mime())->toBe('audio/x-wav');
            $stream->close();

        });

    });

    /**
     * PSR-7 interoperability aliases
     */
    describe("->getContents()", function() {

        it("delegates to `->flush()`", function() {

            $stream = new Stream(['data' => 'foobar']);

            expect($stream)->toReceive('flush');

            $stream->getContents();

        });

    });

    describe("->getSize()", function() {

        it("delegates to `->length()`", function() {

            $stream = new Stream(['data' => 'foobar']);

            expect($stream)->toReceive('length');

            $stream->getSize();

        });

    });

    describe("->getMetadata()", function() {

        it("delegates to `->meta()`", function() {

            $stream = new Stream(['data' => 'foobar']);

            expect($stream)->toReceive('meta')->with('mode');

            $stream->getMetadata('mode');

        });

    });

    describe("->__clone()", function() {

        it("clones a stream", function() {

            $stream1 = new Stream(['data' => 'foobar']);
            $stream2 = clone $stream1;

            $stream2->append('baz');
            expect((string) $stream1)->toBe('foobar');
            expect((string) $stream2)->toBe('foobarbaz');

            $stream1->close();
            $stream2->close();

        });

        it("clones a stream based on filename", function() {

            $stream1 = new Stream(['filename' => 'spec/Fixture/helloworld.txt', 'mode' => 'r+']);
            $stream2 = clone $stream1;

            expect($stream1->flush())->toBe("Hello World!\n");
            expect($stream2->flush())->toBe("Hello World!\n");

            $stream1->close();
            $stream2->close();

        });

        it("throws an exception if the stream is not seekable", function() {

            $stream = new Stream(['data' => 'foobar']);

            $closure = function() use ($stream) {
                allow(Stream::class)->toReceive('isSeekable')->andReturn(false);
                clone $stream;
            };

            $stream->close();

            expect($closure)->toThrow(new RuntimeException('Cannot clone a non seekable stream.'));
        });

    });

});

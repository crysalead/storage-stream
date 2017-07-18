<?php
namespace Lead\Storage\Stream\Spec\Suite;

use RuntimeException;
use InvalidArgumentException;
use Lead\Storage\Stream\Stream;
use Lead\Storage\Stream\MultiStream;

describe("MultiStream", function() {

    describe("->__construct()", function() {

        it("asserts the stream is not writable", function() {

            $multiStream = new MultiStream();
            expect($multiStream->isWritable())->toBe(false);
            expect($multiStream->isSeekable())->toBe(true);
            expect($multiStream->isReadable())->toBe(true);

            $multiStream->close();

        });

    });

    describe("->meta()", function() {

        it("returns an empty array", function() {

            $multiStream = new MultiStream();
            expect($multiStream->meta())->toBe([]);

            $multiStream->close();

        });

    });

    describe("->add()", function() {

        it("throws an exception if the stream is not readable", function() {

            $multiStream = new MultiStream();

            $closure = function() use ($multiStream) {
                $stream = new Stream();
                allow($stream)->toReceive('isReadable')->andReturn(false);
                $multiStream->add($stream);
            };

            expect($closure)->toThrow(new InvalidArgumentException("Cannot append on a non readable stream."));
            $multiStream->close();

        });

    });

    describe("->has()", function() {

        it("checks if a stream exists", function() {

            $multiStream = new MultiStream();
            $multiStream->add('Hello');
            expect($multiStream->has(0))->toBe(true);
            expect($multiStream->has(1))->toBe(false);
            $multiStream->close();

        });

    });

    describe("->get()", function() {

        it("returns a specific stream", function() {

            $multiStream = new MultiStream();
            $multiStream->add('a');
            $multiStream->add('b');
            $multiStream->add('c');
            expect($multiStream->get(0)->toString())->toBe('a');
            expect($multiStream->get(1)->toString())->toBe('b');
            expect($multiStream->get(2)->toString())->toBe('c');
            $multiStream->close();

        });

        it("throws an exception when a stream index doesn't exists", function() {

            $multiStream = new MultiStream(['mime' => 'multipart/form-data']);

            $closure = function() use ($multiStream) {
                $multiStream->get(0);
            };

            expect($closure)->toThrow(new InvalidArgumentException("Unexisting stream index `0`."));
            $multiStream->close();

        });

    });

    describe("->remove()", function() {

        it("returns a specific stream", function() {

            $multiStream = new MultiStream();
            $multiStream->add('a');
            $multiStream->add('b');
            $multiStream->add('c');
            expect($multiStream->remove(1)->toString())->toBe('b');
            expect($multiStream->toString())->toBe('ac');
            $multiStream->close();

        });

        it("throws an exception when a stream index doesn't exists", function() {

            $multiStream = new MultiStream(['mime' => 'multipart/form-data']);

            $closure = function() use ($multiStream) {
                $multiStream->remove(0);
            };

            expect($closure)->toThrow(new InvalidArgumentException("Unexisting stream index `0`."));
            $multiStream->close();

        });

    });

    describe("->read()", function() {

        it("can read from multiple streams", function() {

            $multiStream = new MultiStream();

            $multiStream->add(new Stream(['data' => 'foo']));
            $multiStream->add(new Stream(['data' => 'bar']));
            $multiStream->add(new Stream(['data' => 'baz']));

            expect($multiStream->eof())->toBe(false);
            expect($multiStream->tell())->toBe(0);

            expect($multiStream->read(3))->toBe('foo');
            expect($multiStream->read(3))->toBe('bar');
            expect($multiStream->read(3))->toBe('baz');

            expect($multiStream->read(1))->toBe('');
            expect($multiStream->eof())->toBe(true);

            expect($multiStream->tell())->toBe(9);

            expect($multiStream->toString())->toBe('foobarbaz');

            $multiStream->close();

        });

    });

    describe("->write()", function() {

        it("throws an exception when no stream exists", function() {

            $multiStream = new MultiStream(['mime' => 'multipart/form-data']);

            $closure = function() use ($multiStream) {
                $multiStream->write('hello');
            };

            expect($closure)->toThrow(new RuntimeException("The stream container is empty no write operation is possible."));
            $multiStream->close();

        });

    });

    describe("->append()", function() {

        it("appends to the last stream", function() {

            $multiStream = new MultiStream(['mime' => 'multipart/form-data']);

            $multiStream->add('a');
            $multiStream->add('b');
            $multiStream->add('c');
            $multiStream->append('hello');

            expect($multiStream->toString())->toBe('abchello');
            $multiStream->close();

        });

    });

    describe("->seek()", function() {

        it("seeks to position by reading", function() {

            $multiStream = new MultiStream();

            $multiStream->add(new Stream(['data' => 'foo']));
            $multiStream->add(new Stream(['data' => 'bar']));
            $multiStream->add(new Stream(['data' => 'baz']));

            $multiStream->seek(3);
            expect($multiStream->tell())->toBe(3);
            expect($multiStream->read(3))->toBe('bar');

            $multiStream->seek(6);
            expect($multiStream->tell())->toBe(6);
            expect($multiStream->read(3))->toBe('baz');

            $multiStream->close();

        });

        it("seeks to the end", function() {

            $multiStream = new MultiStream();

            $multiStream->add(new Stream(['data' => 'foo']));
            $multiStream->add(new Stream(['data' => 'bar']));
            $multiStream->add(new Stream(['data' => 'baz']));

            $multiStream->seek(0, SEEK_END);
            expect($multiStream->write('z'))->toBe(1);
            expect($multiStream->toString())->toBe('foobarbazz');

            $multiStream->close();

        });

        it("throws an exception for invalid fseek value", function() {
            $multiStream = new MultiStream();
            $closure = function() use ($multiStream) {
                $multiStream->add('');
                $multiStream->seek(10, SEEK_END);
            };
            expect($closure)->toThrow(new InvalidArgumentException("This seek operation is not supported on a multi stream container."));
            $multiStream->close();
        });

    });

    describe("->end()", function() {

        it("throws an exception when no stream exists", function() {

            $multiStream = new MultiStream(['mime' => 'multipart/form-data']);

            $closure = function() use ($multiStream) {
                $multiStream->end();
            };

            expect($closure)->toThrow(new RuntimeException("The stream container is empty no seek operation is possible."));
            $multiStream->close();

        });

    });

    describe("->toString()", function() {

        it("returns an empty string when empty", function() {

            $multiStream = new MultiStream();
            expect($multiStream->toString())->toBe('');

            $multiStream->close();

        });

    });

    describe("->length()", function() {

        it("can determine size of multiple streams", function() {

            $multiStream = new MultiStream();

            $multiStream->add(new Stream(['data' => 'foo']));
            $multiStream->add(new Stream(['data' => 'bar']));
            $multiStream->add(new Stream(['data' => 'baz']));

            expect($multiStream->length())->toBe(9);

            $multiStream->close();

        });

        it("returns `null` if one stream is not seekable", function() {

            $multiStream = new MultiStream();

            $multiStream->add(new Stream(['data' => 'foo']));
            $multiStream->add(new Stream(['data' => 'bar']));

            $stream = new Stream(['data' => 'baz']);
            allow($stream)->toReceive('isSeekable')->andReturn(false);
            $multiStream->add($stream);

            expect($multiStream->length())->toBe(null);

            $multiStream->close();

        });

    });

    describe("->detach()", function() {

        it("detaches without streams", function() {

            $multiStream = new MultiStream();
            $multiStream->detach();

            expect($multiStream->length())->toBe(0);
            expect($multiStream->eof())->toBe(true);
            expect($multiStream->isReadable())->toBe(true);
            expect((string) $multiStream)->toBe('');
            expect($multiStream->isSeekable())->toBe(true);
            expect($multiStream->isWritable())->toBe(false);

            $multiStream->close();

        });

        it("detaches all streams", function() {

            $handle1 = fopen('php://temp', 'r');
            $handle2 = fopen('php://temp', 'r');

            $multiStream = new MultiStream();

            $multiStream->add(new Stream(['data' => $handle1]));
            $multiStream->add(new Stream(['data' => $handle2]));

            $multiStream->detach();

            expect($multiStream->length())->toBe(0);
            expect($multiStream->eof())->toBe(true);
            expect($multiStream->isReadable())->toBe(true);
            expect((string) $multiStream)->toBe('');
            expect($multiStream->isSeekable())->toBe(true);
            expect($multiStream->isWritable())->toBe(false);

            expect(is_resource($handle1))->toBe(true);
            expect(is_resource($handle2))->toBe(true);
            fclose($handle1);
            fclose($handle2);

            $multiStream->close();

        });

    });

    describe("->close()", function() {

        it("closes all streams", function() {

            $handle1 = fopen('php://temp', 'r');
            $handle2 = fopen('php://temp', 'r');

            $multiStream = new MultiStream();

            $multiStream->add(new Stream(['data' => $handle1]));
            $multiStream->add(new Stream(['data' => $handle2]));

            $multiStream->close();

            expect($multiStream->length())->toBe(0);
            expect($multiStream->eof())->toBe(true);
            expect($multiStream->isReadable())->toBe(true);
            expect((string) $multiStream)->toBe('');
            expect($multiStream->isSeekable())->toBe(true);
            expect($multiStream->isWritable())->toBe(false);

            expect(is_resource($handle1))->toBe(false);
            expect(is_resource($handle2))->toBe(false);

        });

    });

});

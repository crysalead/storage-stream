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

        it("throws an exception when multiple stream exists", function() {

            $multiStream = new MultiStream(['mime' => 'multipart/form-data']);

            $closure = function() use ($multiStream) {
                $multiStream->add('');
                $multiStream->add('');
                $multiStream->write('hello');
            };

            expect($closure)->toThrow(new RuntimeException("The stream container contain multiple stream so no write operation on the container is possible."));
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

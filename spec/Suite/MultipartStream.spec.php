<?php
namespace Lead\Storage\Stream\Spec\Suite;

use RuntimeException;
use InvalidArgumentException;
use Lead\Storage\Stream\Stream;
use Lead\Storage\Stream\MultipartStream;

describe("MultipartStream", function() {

    describe("->__construct()", function() {

        it("asserts the stream is not writable", function() {

            $multipartStream = new MultipartStream();
            expect($multipartStream->isWritable())->toBe(false);
            expect($multipartStream->isSeekable())->toBe(true);
            expect($multipartStream->isReadable())->toBe(true);
            expect($multipartStream->boundary())->not->toBeEmpty();

        });

        it("supports custom boundary", function() {

            $multipartStream = new MultipartStream(['boundary' => 'foo']);
            expect($multipartStream->boundary())->toBe('foo');

        });

    });

    describe("->meta()", function() {

        it("returns an empty array", function() {

            $multipartStream = new MultipartStream();
            expect($multipartStream->meta())->toBe([]);

        });

    });

    describe("->add()", function() {

        it("overwrites mime", function() {

            $multipartStream = new MultipartStream(['boundary' => 'boundary']);
            $multipartStream->add(new Stream(['data' => 'bar']), ['name' => 'foo', 'mime' => 'image/png']);

            $expected = <<<EOD
--boundary\r
Content-Disposition: form-data; name="foo"\r
Content-Type: image/png\r
Content-Length: 3\r
\r
bar\r
--boundary--\r

EOD;
            expect($multipartStream->toString())->toBe($expected);

        });

        it("add custom headers", function() {

            $multipartStream = new MultipartStream(['boundary' => 'boundary']);
            $multipartStream->add(new Stream(['data' => 'bar']), ['name' => 'foo', 'headers' => [
                'x-foo: "bar"'
            ]]);

            $expected = <<<EOD
--boundary\r
x-foo: "bar"\r
Content-Disposition: form-data; name="foo"\r
Content-Type: text/plain\r
Content-Length: 3\r
\r
bar\r
--boundary--\r

EOD;
            expect($multipartStream->toString())->toBe($expected);

        });

        it("overwrites disposition", function() {

            $multipartStream = new MultipartStream(['boundary' => 'boundary']);
            $multipartStream->add(new Stream(['data' => 'bar']), ['name' => 'foo', 'disposition' => 'attachment']);

            $expected = <<<EOD
--boundary\r
Content-Disposition: attachment; name="foo"\r
Content-Type: text/plain\r
Content-Length: 3\r
\r
bar\r
--boundary--\r

EOD;
            expect($multipartStream->toString())->toBe($expected);

        });

        it("throws an exception if the `'name'` option is empty", function() {

            $closure = function() {
                $multipartStream = new MultipartStream();
                $stream = new Stream();
                $multipartStream->add($stream);
            };

            expect($closure)->toThrow(new InvalidArgumentException("The `'name'` option is required."));

        });

    });

    describe("->read()", function() {

        it("serializes fields", function() {

            $multipartStream = new MultipartStream(['boundary' => 'boundary']);

            $multipartStream->add(new Stream(['data' => 'bar']), ['name' => 'foo']);
            $multipartStream->add(new Stream(['data' => 'bam']), ['name' => 'baz']);

            $expected = <<<EOD
--boundary\r
Content-Disposition: form-data; name="foo"\r
Content-Type: text/plain\r
Content-Length: 3\r
\r\nbar\r
--boundary\r
Content-Disposition: form-data; name="baz"\r
Content-Type: text/plain\r
Content-Length: 3\r
\r
bam\r
--boundary--\r

EOD;
            expect($multipartStream->toString())->toBe($expected);

        });

        it("serializes non string fields", function() {

            $multipartStream = new MultipartStream(['boundary' => 'boundary']);

            $multipartStream->add(new Stream(['data' => 1]), ['name' => 'int']);
            $multipartStream->add(new Stream(['data' => false]), ['name' => 'bool1']);
            $multipartStream->add(new Stream(['data' => true]), ['name' => 'bool2']);
            $multipartStream->add(new Stream(['data' => 1.1]), ['name' => 'float']);

            $expected = <<<EOD
--boundary\r
Content-Disposition: form-data; name="int"\r
Content-Type: application/octet-stream\r
Content-Length: 1\r
\r
1\r
--boundary\r
Content-Disposition: form-data; name="bool1"\r
Content-Type: application/octet-stream\r
\r
\r
--boundary\r
Content-Disposition: form-data; name="bool2"\r
Content-Type: application/octet-stream\r
Content-Length: 1\r
\r
1\r
--boundary\r
Content-Disposition: form-data; name="float"\r
Content-Type: text/plain\r
Content-Length: 3\r
\r
1.1\r
--boundary--\r

EOD;

            expect($multipartStream->toString())->toBe($expected);

        });

    });

    describe("->write()", function() {

        it("throws an exception on write", function() {

            $closure = function() {
                $multipartStream = new MultipartStream();
                $multipartStream->write('hello');
            };

            expect($closure)->toThrow(new RuntimeException("`MultiStream` instances are not writable."));

        });

    });

    describe("->toString()", function() {

        it("returns an empty string when empty", function() {

            $multipartStream = new MultipartStream();
            expect($multipartStream->toString())->toBe('--' . $multipartStream->boundary() . "--\r\n");

        });

    });

});

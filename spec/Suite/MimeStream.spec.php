<?php
namespace Lead\Storage\Stream\Spec\Suite;

use RuntimeException;
use InvalidArgumentException;
use Lead\Storage\Stream\Stream;
use Lead\Storage\Stream\MimeStream;

describe("MimeStream", function() {

    describe("->__construct()", function() {

        it("asserts the stream is not writable", function() {

            $mimeStream = new MimeStream();
            expect($mimeStream->isWritable())->toBe(false);
            expect($mimeStream->isSeekable())->toBe(true);
            expect($mimeStream->isReadable())->toBe(true);
            expect($mimeStream->boundary())->not->toBeEmpty();

            $mimeStream->close();

        });

        it("supports custom boundary", function() {

            $mimeStream = new MimeStream(['boundary' => 'foo']);
            expect($mimeStream->boundary())->toBe('foo');
            $mimeStream->close();

        });

    });

    describe("->meta()", function() {

        it("returns an empty array", function() {

            $mimeStream = new MimeStream();
            expect($mimeStream->meta())->toBe([]);

            $mimeStream->close();

        });

    });

    describe("->add()", function() {

        it("overwrites mime", function() {

            $mimeStream = new MimeStream(['boundary' => 'boundary']);
            $mimeStream->add(new Stream(['data' => 'bar']), [
                'name'        => 'foo',
                'disposition' => 'inline',
                'mime'        => 'image/png'
            ]);

            $expected = <<<EOD
Content-Type: multipart/mixed;\r
\tboundary="boundary"\r
\r
--boundary\r
Content-Disposition: inline; name="foo"\r
Content-Type: image/png\r
Content-Transfer-Encoding: base64\r
\r
YmFy\r
--boundary--\r

EOD;
            expect($mimeStream->toString())->toBe($expected);

            $mimeStream->close();

        });

        it("add custom headers", function() {

            $mimeStream = new MimeStream(['boundary' => 'boundary']);
            $mimeStream->add(new Stream(['data' => 'bar']), [
                'name'        => 'foo',
                'disposition' => 'form-data',
                'headers'     => [
                    'x-foo: "bar"'
                ]
            ]);

            $expected = <<<EOD
Content-Type: multipart/mixed;\r
\tboundary="boundary"\r
\r
--boundary\r
x-foo: "bar"\r
Content-Disposition: form-data; name="foo"\r
Content-Type: text/plain; charset=utf-8\r
Content-Transfer-Encoding: quoted-printable\r
\r
bar\r
--boundary--\r

EOD;
            expect($mimeStream->toString())->toBe($expected);

            $mimeStream->close();

        });

        it("overwrites disposition", function() {

            $mimeStream = new MimeStream(['boundary' => 'boundary']);
            $mimeStream->add(new Stream(['data' => 'bar']), ['name' => 'foo', 'disposition' => 'attachment']);

            $expected = <<<EOD
Content-Type: multipart/mixed;\r
\tboundary="boundary"\r
\r
--boundary\r
Content-Disposition: attachment; name="foo"\r
Content-Type: text/plain; charset=utf-8\r
Content-Transfer-Encoding: quoted-printable\r
\r
bar\r
--boundary--\r

EOD;
            expect($mimeStream->toString())->toBe($expected);

            $mimeStream->close();

        });

        it("throws an exception if the `'name'` option is empty", function() {

            $mimeStream = new MimeStream();

            $closure = function() use ($mimeStream) {
                $stream = new Stream();
                $mimeStream->add($stream);
            };

            expect($closure)->toThrow(new InvalidArgumentException("The `'name'` option is required."));
            $mimeStream->close();

        });

    });

    describe("->read()", function() {

        it("serializes fields", function() {
            $mimeStream = new MimeStream(['boundary' => 'boundary']);

            $mimeStream->add(new Stream(['data' => 'bar']), [
                'name' => 'foo',
                'disposition' => 'form-data'
            ]);
            $mimeStream->add(new Stream(['data' => 'bam']), [
                'name' => 'baz',
                'disposition' => 'form-data'
            ]);

            $expected = <<<EOD
Content-Type: multipart/mixed;\r
\tboundary="boundary"\r
\r
--boundary\r
Content-Disposition: form-data; name="foo"\r
Content-Type: text/plain; charset=utf-8\r
Content-Transfer-Encoding: quoted-printable\r
\r\nbar\r
--boundary\r
Content-Disposition: form-data; name="baz"\r
Content-Type: text/plain; charset=utf-8\r
Content-Transfer-Encoding: quoted-printable\r
\r
bam\r
--boundary--\r

EOD;
            expect($mimeStream->toString())->toBe($expected);

            $mimeStream->close();

        });

        it("serializes non string fields", function() {

            $mimeStream = new MimeStream(['boundary' => 'boundary']);

            $mimeStream->add(new Stream(['data' => 1]), ['name' => 'int','disposition' => 'form-data']);
            $mimeStream->add(new Stream(['data' => false]), ['name' => 'bool1','disposition' => 'form-data']);
            $mimeStream->add(new Stream(['data' => true]), ['name' => 'bool2', 'disposition' => 'form-data']);
            $mimeStream->add(new Stream(['data' => 1.1]), ['name' => 'float','disposition' => 'form-data']);

            $expected = <<<EOD
Content-Type: multipart/mixed;\r
\tboundary="boundary"\r
\r
--boundary\r
Content-Disposition: form-data; name="int"\r
Content-Type: application/octet-stream\r
Content-Transfer-Encoding: base64\r
\r
MQ==\r
--boundary\r
Content-Disposition: form-data; name="bool1"\r
Content-Type: application/octet-stream\r
Content-Transfer-Encoding: base64\r
\r
\r
--boundary\r
Content-Disposition: form-data; name="bool2"\r
Content-Type: application/octet-stream\r
Content-Transfer-Encoding: base64\r
\r
MQ==\r
--boundary\r
Content-Disposition: form-data; name="float"\r
Content-Type: text/plain; charset=utf-8\r
Content-Transfer-Encoding: quoted-printable\r
\r
1.1\r
--boundary--\r

EOD;

            expect($mimeStream->toString())->toBe($expected);

            $mimeStream->close();

        });

    });

    describe("->read()", function() {

        it("throws an exception on read", function() {

            $mimeStream = new MimeStream();

            $closure = function() use ($mimeStream) {
                $mimeStream->read('hello');
            };

            expect($closure)->toThrow(new RuntimeException("`MultiStream` instances cannot be read byte per byte."));
            $mimeStream->close();

        });

    });

    describe("->write()", function() {

        it("throws an exception on write", function() {

            $mimeStream = new MimeStream();

            $closure = function() use ($mimeStream) {
                $mimeStream->write('hello');
            };

            expect($closure)->toThrow(new RuntimeException("`MultiStream` instances are not writable."));
            $mimeStream->close();

        });

    });

    describe("->length()", function() {

        it("throws an exception when called", function() {

            $mimeStream = new MimeStream();

            $closure = function() use ($mimeStream) {
                $mimeStream->length();
            };

            expect($closure)->toThrow(new RuntimeException("Cannot extract `MultiStream` length."));
            $mimeStream->close();

        });

    });

    describe("->toString()", function() {

        it("returns an empty string when empty", function() {

            $mimeStream = new MimeStream();
            expect($mimeStream->toString())->toBe("Content-Type: multipart/mixed;\r\n\tboundary=\"" . $mimeStream->boundary() . "\"\r\n\r\n--" . $mimeStream->boundary() . "--\r\n");

            $mimeStream->close();

        });

    });

});

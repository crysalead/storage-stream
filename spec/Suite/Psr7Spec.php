<?php
namespace Lead\Storage\Stream\Spec\Suite;

use Lead\Storage\Stream\Stream;

describe("Stream", function() {

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

});
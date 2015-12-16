# Stream - Object-Oriented API for PHP streams

[![Build Status](https://travis-ci.org/crysalead/storage-stream.png?branch=master)](https://travis-ci.org/crysalead/storage-stream)
[![Code Coverage](https://scrutinizer-ci.com/g/crysalead/storage-stream/badges/coverage.png?b=master)](https://scrutinizer-ci.com/g/crysalead/storage-stream/)

Object-Oriented API for PHP streams (PSR-7 compatible).

## Installation

```sh
composer require crysalead/storage-stream
```

## Example

```php
use Lead\Storage\Stream;

$stream = new Stream(fopen('smiley.png', 'r'));
$image = '';
while (!$stream->eof()) {
  $image .= $stream->read();
}

echo $stream->mime(); // 'image/png'
```

## Pipe Example
```php
use Lead\Storage\Stream;

$stream1 = new Stream("Hello");
$stream2 = new Stream("xxxxxWorld");

// copy the contents from the first stream to the second one
$stream1->pipe($stream2);

echo (string) $stream2; // 'HelloWorld'
```

### Acknowledgements

Original implementation: [Francois Zaninotto](https://github.com/fzaninotto/Streamer).
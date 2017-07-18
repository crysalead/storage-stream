<?php
namespace Lead\Storage\Stream;

use Exception;
use RuntimeException;
use InvalidArgumentException;

/**
 * Reads from multiple streams, one after the other.
 */
class MultiStream implements \Psr\Http\Message\StreamInterface
{
    use Psr7\StreamTrait;

    /**
     * The buffer size.
     *
     * @var integer
     */
    protected $_bufferSize = 4096;

    /**
     * Streams to read consecutively
     *
     * @var array
     */
    protected $_streams = [];

    /**
     * Indicate if the stream is seekable
     *
     * @var boolean
     */
    protected $_seekable = true;

    /**
     * The current stream.
     *
     * @return integer
     */
    protected $_current = 0;

    /**
     * The current position in the current stream.
     *
     * @return integer
     */
    protected $_offset = 0;

    /**
     * The constructor
     *
     * @param array $config The configuration array. Possibles values are:
     *                      -`bufferSize` _interger: number of bytes to read on read by defaults.
     */
    public function __construct($config = [])
    {
        $defaults = [
            'data' => null,
            'bufferSize' => 4096
        ];
        $config += $defaults;
        $this->_bufferSize = $config['bufferSize'];
        if (isset($config['data'])) {
            $this->add($config['data']);
        }
    }

    /**
     * Check if a stream is readable.
     *
     * @return boolean
     */
    public function isReadable()
    {
        return true;
    }

    /**
     * Check if a stream is writable.
     *
     * @return boolean
     */
    public function isWritable()
    {
        return false;
    }

    /**
     * Checks if a stream is seekable.
     *
     * @return boolean
     */
    public function isSeekable()
    {
        return $this->_seekable;
    }

    /**
     * Get/set the stream mime.
     *
     * @param  mixed  $mime The mime string to set or `true` to autodetect the mime.
     * @return string       The mime.
     */
    public function mime($mime = null)
    {
        throw new Exception("Cannot set or get a mime on a multi stream container.");
    }

    /**
     * Get stream meta data.
     *
     * @param  string $key A specific meta data or `null` to get all meta data.
     *
     * @return mixed
     */
    public function meta($key = null)
    {
        return $key ? null : [];
    }

    /**
     * Add a stream
     *
     * @param object $stream Stream to append.
     *
     * @throws InvalidArgumentException if the stream is not readable
     */
    public function add($stream)
    {
        if (is_scalar($stream) || is_resource($stream)) {
            $stream = new Stream(['data' => $stream]);
        }

        if (!$stream->isReadable()) {
            throw new InvalidArgumentException("Cannot append on a non readable stream.");
        }

        // The stream is only seekable if all streams are seekable
        if (!$stream->isSeekable()) {
            $this->_seekable = false;
        }

        $this->_streams[] = $stream;
        return $this;
    }

    /**
     * Get the position of the file pointer
     *
     * @return integer
     */
    public function tell()
    {
        return $this->_offset;
    }

    /**
     * Attempts to seek to the given position. Only supports SEEK_SET.
     *
     * {@inheritdoc}
     */
    public function seek($offset, $whence = SEEK_SET)
    {
        if (!$this->_seekable) {
            throw new RuntimeException("Cannot seek on non seekable stream.");
        }
        if ($whence !== SEEK_SET) {
            if (!$offset && $whence === SEEK_END) {
                return $this->end();
            }
            throw new InvalidArgumentException("This seek operation is not supported on a multi stream container.");
        }
        $this->_offset = $this->_current = 0;

        foreach ($this->_streams as $i => $stream) {
            $stream->rewind();
        }

        while ($this->_offset < $offset && !$this->eof()) {
            $result = $this->read(min(8096, $offset - $this->_offset));
            if ($result === '') {
                break;
            }
        }
    }

    /**
     * Move the file pointer to the beginning of the stream.
     *
     * @return Boolean
     */
    public function rewind()
    {
        $this->seek(0);
    }

    /**
     * Alias of rewind().
     *
     * @return Boolean
     */
    public function begin()
    {
        return $this->rewind();
    }

    /**
     * Seek to the end of the stream.
     *
     * @return Boolean
     */
    public function end()
    {
        if (!$this->_seekable) {
            throw new RuntimeException("Cannot seek on non seekable stream.");
        }
        if (!count($this->_streams)) {
            throw new RuntimeException('The stream container is empty no seek operation is possible.');
        }
        $this->_current = count($this->_streams) - 1;
        $stream = $this->_streams[$this->_current];
        $stream->seek(0, SEEK_END);
    }

    /**
     * Read data from the stream.
     * Binary-safe.
     *
     * @param  integer $length Maximum number of bytes to read (default to buffer size).
     * @return string          The data.
     */
    public function read($length = null)
    {
        $buffer = '';
        $total = count($this->_streams) - 1;
        $remaining = (integer) ($length === null ? $this->_bufferSize : $length);
        $progressToNext = false;

        while ($remaining > 0) {

            // Progress to the next stream if needed.
            if ($progressToNext || $this->_streams[$this->_current]->eof()) {
                $progressToNext = false;
                if ($this->_current === $total) {
                    break;
                }
                $this->_current++;
            }

            $result = $this->_streams[$this->_current]->read($remaining);

            if (!$result) {
                $progressToNext = true;
                continue;
            }

            $buffer .= $result;
            $remaining = $length - strlen($buffer);
        }

        $this->_offset += strlen($buffer);
        return $buffer;
    }

    /**
     * Write data to the stream.
     *
     * @param  string  $string The string that is to be written.
     * @param  integer $length If the length argument is given, writing will stop after length bytes have
     *                         been written or the end of string if reached, whichever comes first.
     * @return integer         Number of bytes written
     */
    public function write($string, $length = null)
    {
        if (!count($this->_streams)) {
            throw new RuntimeException('The stream container is empty no write operation is possible.');
        }
        $stream = $this->_streams[$this->_current];
        return $stream->write($string, $length);
    }

    /**
     * Append data to the stream.
     *
     * @param  string  $string The string that is to be written.
     * @param  integer $length If the length argument is given, writing will stop after length bytes have
     *                         been written or the end of string if reached, whichever comes first.
     * @return integer         Number of bytes written
     */
    public function append($string, $length = null)
    {
        $this->end();
        return $this->write($string, $length);
    }

    /**
     * Tries to calculate the size by adding the size of each stream.
     *
     * If any of the streams do not return a valid number, then the size of the
     * append stream cannot be determined and null is returned.
     *
     */
    public function length()
    {
        $length = 0;

        foreach ($this->_streams as $stream) {
            $len = $stream->length();
            if ($len === null) {
                return;
            }
            $length += $len;
        }
        return $length;
    }

    /**
     * Check if a stream exists
     *
     * @param  integer $index An index.
     * @return boolean
     */
    public function has($index)
    {
        return isset($this->_streams[$index]);
    }

    /**
     * Return a contained stream.
     *
     * @param  integer $index An index.
     * @return object
     */
    public function get($index)
    {
        if (!isset($this->_streams[$index])) {
            throw new InvalidArgumentException("Unexisting stream index `{$index}`.");
        }
        return $this->_streams[$index];
    }

    /**
     * Remove a stream.
     *
     * @param  integer $index An index.
     * @return object         The removed stream.
     */
    public function remove($index)
    {
        if (!isset($this->_streams[$index])) {
            throw new InvalidArgumentException("Unexisting stream index `{$index}`.");
        }
        $stream = $this->_streams[$index];
        unset($this->_streams[$index]);
        $this->_streams = array_values($this->_streams);
        return $stream;
    }

    /**
     * Return the number of contained streams.
     *
     * @return integer
     */
    public function count()
    {
        return count($this->_streams);
    }

    /**
     * Check if the stream is valid.
     *
     * @return Boolean
     */
    public function valid()
    {
        return true;
    }

    /**
     * Checks for EOF.
     *
     * @return boolean
     */
    public function eof()
    {
        return !$this->_streams || ($this->_current >= count($this->_streams) - 1 && $this->_streams[$this->_current]->eof());
    }

    /**
     * Return the remaining data from the stream.
     *
     * @return string
     */
    public function flush()
    {
        $buffer = '';
        while (!$this->eof()) {
            $buffer .= $this->read($this->_bufferSize);
        }
        return $buffer;
    }

    /**
     * Returns the remaining data from the stream (same as flush).
     *
     * @return string
     */
    public function toString()
    {
        if (!$this->isSeekable()) {
            return $this->flush();
        }
        $this->rewind();
        return $this->flush();
    }

    /**
     * Detaches each attached stream.
     *
     * Returns null as it's not clear which underlying stream resource to return.
     */
    public function detach()
    {
        $this->_offset = $this->_current = 0;
        $this->_seekable = true;

        foreach ($this->_streams as $stream) {
            $stream->detach();
        }

        $this->_streams = [];
    }

    /**
     * Close each streams.
     */
    public function close()
    {
        $this->_offset = $this->_current = 0;
        $this->_seekable = true;

        foreach ($this->_streams as $stream) {
            $stream->close();
        }

        $this->_streams = [];
    }

    /**
     * Closes each streams.
     */
    public function __destruct()
    {
        $this->close();
    }

    /**
     * Clones each streams.
     */
    public function __clone()
    {
        foreach ($this->_streams as $key => $value) {
            $this->_streams[$key] = clone $value;
        }
    }
}

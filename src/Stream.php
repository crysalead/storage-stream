<?php
namespace storage\stream;

class Stream
{
    /**
     * The stream resource.
     *
     * @var resource
     */
    protected $_resource;

    /**
     * The buffer size.
     *
     * @var integer
     */
    protected $_bufferSize = 4096;

    /**
     * The constructor
     *
     * @param resource $resource The stream resource.
     * @param array    $config   The configuration array.
     */
    public function __construct($resource, $config = [])
    {
        $defaults = [
            'bufferSize' => 4096
        ];
        $config += $defaults;

        $this->_resource = $resource;
        $this->_bufferSize = $config['bufferSize'];
    }

    /**
     * Gets the resource handler.
     *
     * @return resource
     */
    public function resource()
    {
        if (!is_resource($this->_resource)) {
            throw new StreamException('A Stream object requires a stream resource as constructor argument');
        }
        return $this->_resource;
    }

    /**
     * Get steam meta data.
     *
     * @param  string $key A specific meta data or `null` to get all meta data.
     *                     Possibles values are:
     *                     `'uri'`          _string_ : the URI/filename associated with this stream.
     *                     `'mode'`         _string_ : the type of access required for this stream.
     *                     `'wrapper_type'` _string_ : the protocol wrapper implementation layered over the stream.
     *                     `'stream_type'`  _string_ : the underlying implementation of the stream.
     *                     `'unread_bytes'` _integer_: the number of bytes contained in the PHP's own internal buffer.
     *                     `'seekable'`     _boolean_: `true` means the current stream can be seeked.
     *                     `'eof'`          _boolean_: `true` means the stream has reached end-of-file.
     *                     `'blocked'`      _boolean_: `true` means the stream is in blocking IO mode.
     *                     `'timed_out'`    _boolean_: `true` means stream timed out on the last read call.
     * @return mixed
     */
    public function meta($key = null)
    {
        $meta = stream_get_meta_data($this->_resource);

        if ($key) {
            return isset($meta[$key]) ? $meta[$key] : null;
        }
        return $meta;
    }

    /**
     * Checks if a stream is a local stream.
     *
     * @return boolean
     */
    public function isLocal()
    {
        return stream_is_local($this->_resource);
    }

    /**
     * Checks if a stream is readable.
     *
     * @return boolean
     */
    public function readable()
    {
        $mode = $this->meta('mode');
        return $mode[0] === 'r' || strpos($mode, '+');
    }

    protected function _readable()
    {
        if (!$this->valid()) {
            throw new StreamException('Cannot read from a closed stream');
        }
        if (!$this->readable()) {
            $mode = $this->meta('mode');
            throw new StreamException("Cannot read on a non-readable stream (mode is `'{$mode}'`)");
        }
    }

    /**
     * Checks if a stream is writable.
     *
     * @return boolean
     */
    public function writable()
    {
        $mode = $this->meta('mode');
        return $mode[0] !== 'r' || strpos($mode, '+');
    }

    protected function _writable()
    {
        if (!$this->valid()) {
            throw new StreamException('Cannot write on a closed stream');
        }
        if (!$this->writable()) {
            $mode = $this->meta('mode');
            throw new StreamException("Cannot write on a non-writable stream (mode is `'{$mode}'`)");
        }
    }

    /**
     * Checks if a stream is seekable.
     *
     * @return boolean
     */
    public function seekable()
    {
        return $this->meta('seekable');
    }

    protected function _seekable()
    {
        if (!$this->valid()) {
            throw new StreamException('Cannot seek on a closed stream');
        }
        if (!$this->seekable()) {
            throw new StreamException('Cannot seek on a non-seekable stream');
        }
    }

    /**
     * @return Boolean
     */
    public function valid()
    {
        return !!$this->_resource;
    }

    /**
     * Gets/sets the buffer size.
     *
     * @param  integer $bufferSize The buffer size to set or `null` to get the current buffer size.
     * @return integer             The buffer size.
     */
    public function bufferSize($bufferSize = null)
    {
        if ($bufferSize === null) {
            return $this->_bufferSize;
        }
        return $this->_bufferSize = $bufferSize;
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
        $this->_readable();
        if (null == $length) {
            $length = $this->_bufferSize;
        }
        $result = fread($this->_resource, $length);
        if ($result === false) {
            throw new StreamException('Cannot read stream');
        }

        return $result !== '' ? $result : false;
    }

    /**
     * Reads one line from the stream.
     *
     * @param  integer $length Maximum number of bytes to read (default to buffer size).
     * @param  string  $ending Line ending to stop at (default to "\n").
     * @return string          The data.
     */
    public function getLine($length = null, $ending = "\n")
    {
        $this->_readable();
        if (null == $length) {
            $length = $this->_bufferSize;
        }
        $result = stream_get_line($this->_resource, $length, $ending);
        return $result !== '' ? $result : false;
    }

    /**
     * Reads the remaining data from the stream.
     *
     * @return string The readed content.
     */
    public function content()
    {
        $this->_readable();
        return stream_get_contents($this->_resource);
    }

    /**
     * Checks for EOF.
     *
     * @return boolean
     */
    public function eof()
    {
        $this->_readable();
        return feof($this->_resource);
    }

    /**
     * Writes data to the stream.
     *
     * @param  string  $string The string that is to be written.
     * @param  integer $length If the length argument is given, writing will stop after length bytes have
     *                         been written or the end of string if reached, whichever comes first.
     * @return integer         Number of bytes written
     */
    public function write($string, $length = null)
    {
        $this->_writable();
        if (null === $length) {
            $result = fwrite($this->_resource, $string);
        } else {
            $result = fwrite($this->_resource, $string, $length);
        }
        return $result;
    }

    /**
     * Reads the content of this stream and write it to another stream.
     *
     * @param  instance $stream The destination stream to write to
     * @return integer          The number of copied bytes
     */
    public function pipe($stream)
    {
        return stream_copy_to_stream($this->resource(), $stream->resource());
    }

    /**
     * Gets the position of the file pointer
     *
     * @return integer
     */
    public function offset()
    {
        $this->_seekable();
        return ftell($this->_resource);
    }

    /**
     * Seeks on the stream.
     *
     * @param integer $offset The offset.
     * @param integer $whence Accepted values are:
     *                        - SEEK_SET - Set position equal to $offset bytes.
     *                        - SEEK_CUR - Set position to current location plus $offset.
     *                        - SEEK_END - Set position to end-of-file plus $offset.
     */
    public function seek($offset, $whence = SEEK_SET)
    {
        $this->_seekable();
        return fseek($this->_resource, $offset, $whence);
    }

    /**
     * Moves the file pointer to the beginning of the stream
     */
    public function rewind()
    {
        $this->_seekable();
        return rewind($this->_resource);
    }

    /**
     * Closes the stream
     */
    public function close()
    {
        if (!is_resource($this->_resource)) {
            return false;
        }
        if ($result = fclose($this->_resource)) {
            $this->_resource = null;
        }
        return $result;
    }

    /**
     * Closes the stream
     */
    public function __destruct()
    {
        $this->close();
    }
}

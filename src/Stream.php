<?php
namespace storage\stream;

class Stream
{
    /**
     * The stream resource.
     *
     * @var resource
     */
    protected $_resource = null;

    /**
     * The mime info.
     *
     * @var string
     */
    protected $_mime = null;

    /**
     * The buffer size.
     *
     * @var integer
     */
    protected $_bufferSize = 4096;

    /**
     * The timeout in microseconds
     *
     * @var integer
     */
    protected $_timeout = -1;

    /**
     * The constructor
     *
     * @param array $config The configuration array. Possibles values are:
     *                      -`resource` _resource_: a resource.
     *                      -`file`     _string_  : a file path.
     *                      -`mode`     _string_  : the mode parameter (used with `file` only).
     *                      -`data`     _string_  : a data string.
     *                      -`mime`     _mixed_   : a mime string or `true` for an auto detection.
     *                      `resource`, `file` & `data` are mutually exclusive.
     */
    public function __construct($config = [])
    {
        $defaults = [
            'resource'   => null,
            'file'       => null,
            'mode'       => 'r+',
            'data'       => '',
            'bufferSize' => 4096,
            'mime'       => null
        ];
        $config += $defaults;

        if ($config['resource'] !== null) {
            $resource = $config['resource'];
        } elseif ($config['file'] !== null) {
            $resource = fopen($config['file'], $config['mode']);
        } else {
            $stream = fopen('php://temp', 'r+');
            if ($config['data']) {
                fwrite($stream, $config['data']);
                rewind($stream);
            }
            $resource = $stream;
        }

        $this->_resource = $resource;
        $this->_bufferSize = $config['bufferSize'];
        $this->_mime = $this->_getMime($config['mime']);
    }

    /**
     * Mime detector.
     * Concat the first 1024 bytes + the last 4 bytes of readable & seekable streams
     * to detext the mime info.
     *
     * @param  string $mime The mime type detection. Possible values are:
     *                      -`true`    : auto detect the mime.
     *                      - a string : don't detect the mime and use the passed string instead.
     *                      -`false`   : don't detect the mime.
     * @return string       The detected mime.
     */
    protected function _getMime($mime)
    {
        if (is_string($mime)) {
            return $mime;
        }
        if (!$mime || !$this->seekable() || !$this->readable()) {
            return 'application/octet-stream';
        }
        $finfo = finfo_open(FILEINFO_MIME_TYPE);

        $begin = ftell($this->_resource);
        fseek($this->_resource, 0, SEEK_END);
        $end = ftell($this->_resource);

        $size = min($end - $begin, 4);
        if ($size === 0) {
            return 'application/octet-stream';
        }

        fseek($this->_resource, $size, SEEK_SET);
        $signature = fread($this->_resource, $size);

        $size = min($end - $begin, 1024);
        fseek($this->_resource, $begin, SEEK_SET);
        $signature = fread($this->_resource, $size) . $signature;
        fseek($this->_resource, $begin, SEEK_SET);

        return finfo_buffer($finfo, $signature);
    }

    /**
     * Gets the resource handler.
     *
     * @return resource
     */
    public function resource()
    {
        if (!$this->valid()) {
            throw new StreamException('Invalid resource');
        }
        return $this->_resource;
    }

    public function mime()
    {
        return $this->_mime;
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
        return $result === false ? '' : $result;
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
        return $result === false ? '' : $result;
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
     * Returns the remaining data from the stream.
     *
     * @return string
     */
    public function flush()
    {
        $this->_readable();
        return stream_get_contents($this->_resource);
    }

    /**
     * Set timeout period on a stream.
     *
     * @param integer $delay The timeout delay in microseconds.
     */
    public function timeout($delay = null) {
        if ($delay === null) {
            return $this->_timeout;
        }
        if (!$this->valid()) {
            throw new StreamException("Invalid stream resource, unable to set a timeout on it");
        }
        $this->_timeout = $delay;
        return stream_set_timeout($this->_resource, 0, $delay);
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
     * Moves the file pointer to the beginning of the stream.
     *
     * @return Boolean
     */
    public function rewind()
    {
        $this->_seekable();
        return rewind($this->_resource);
    }

    /**
     * Checks if the stream is valid.
     *
     * @return Boolean
     */
    public function valid()
    {
        return !!$this->_resource && is_resource($this->_resource);
    }

    /**
     * Returns the stream size.
     *
     * @return integer
     */
    public function size()
    {
        if (!$this->seekable()) {
            return -1;
        }

        $start = ftell($this->_resource);

        fseek($this->_resource, 0, SEEK_SET);
        $begin = ftell($this->_resource);

        fseek($this->_resource, 0, SEEK_END);
        $end = ftell($this->_resource);

        fseek($this->_resource, $start, SEEK_SET);
        return $end - $begin;
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
     * Returns the remaining data from the stream (same as flush).
     *
     * @return string
     */
    public function __toString() {
        return $this->flush();
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

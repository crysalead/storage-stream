<?php
namespace Lead\Storage\Stream;

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
     * The start offset from start.
     *
     * @var integer
     */
    protected $_start = 0;

    /**
     * The range limit.
     *
     * @var integer
     */
    protected $_limit = null;

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
     *                      -`data`       _mixed_  : a data string or a stream resource.
     *                      -`mime`       _mixed_  : a mime string or `true` for an auto detection.
     *                      -`bufferSize` _interger: number of bytes to read on read by defaults.
     */
    public function __construct($config = [])
    {
        $defaults = [
            'data'       => '',
            'mime'       => null,
            'start'      => 0,
            'limit'      => null,
            'bufferSize' => 4096
        ];
        $config += $defaults;

        if (is_resource($config['data'])) {
            $resource = $config['data'];
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
        $this->_start = $config['start'];
        $this->_limit = $config['limit'];
        $this->_mime = $this->_getMime($config['mime']);
        if ($this->_start > 0) {
            $this->rewind();
        }
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
            throw new StreamException('Invalid resource.');
        }
        return $this->_resource;
    }

    /**
     * Gets/sets the starting offset.
     *
     * @param  integer $start The offset to set.
     * @return string         The setted offset.
     */
    public function start($start = null, $autoseek = true)
    {
        if (func_num_args() === 0) {
            return $this->_start;
        }
        $this->_start = $start;

        if ($autoseek) {
            $this->rewind();
        }

        return $this->_start;
    }

    /**
     * Gets/sets the stream range limit.
     *
     * @param  integer $limit The limit to set.
     * @return string          The setted limit.
     */
    public function limit($limit = null)
    {
        if (func_num_args() === 0) {
            return $this->_limit;
        }
        return $this->_limit = $limit;
    }

    /**
     * Gets/sets the range.
     *
     * @param  integer $range The range to set.
     * @return string         The setted range.
     */
    public function range($range = null)
    {
        if (func_num_args() === 1) {
            $values = explode('-', $range);
            $this->_start = (integer) $values[0];
            $this->_limit = $values[1] !== '' ? $values[1] - $values[0] : null;
        }
        return $this->_start . '-' . ($this->_limit ? $this->_start + $this->_limit : '');
    }

    /**
     * Gets/sets the stream mime.
     *
     * @param  mixed  $mime The mime string to set or `true` to autodetect the mime.
     * @return string       The mime.
     */
    public function mime($mime = null)
    {
        if (func_num_args() === 0) {
            return $this->_mime;
        }
        return $this->_mime = $this->_getMime($mime);
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

    /**
     * Throws an exception if a stream is not readable.
     */
    protected function _readable()
    {
        if (!$this->valid()) {
            throw new StreamException('Cannot read from a closed stream.');
        }
        if (!$this->readable()) {
            $mode = $this->meta('mode');
            throw new StreamException("Cannot read on a non-readable stream (mode is `'{$mode}'`).");
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

    /**
     * Throws an exception if a stream is not writable.
     */
    protected function _writable()
    {
        if (!$this->valid()) {
            throw new StreamException('Cannot write on a closed stream.');
        }
        if (!$this->writable()) {
            $mode = $this->meta('mode');
            throw new StreamException("Cannot write on a non-writable stream (mode is `'{$mode}'`).");
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

    /**
     * Throws an exception if a stream is not seekable.
     */
    protected function _seekable()
    {
        if (!$this->valid()) {
            throw new StreamException('Cannot seek on a closed stream.');
        }
        if (!$this->seekable()) {
            throw new StreamException('Cannot seek on a non-seekable stream.');
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
        $length = $this->_bufferSize($length);
        if ($length <= 0) {
            return '';
        }
        $result = fread($this->_resource, $length);
        return $result === false ? '' : $result;
    }

    /**
     * Determines the buffer size to read.
     *
     * @param  integer $length Maximum number of bytes to read (default to buffer size).
     * @return integer         The allowed size.
     */
    protected function _bufferSize($length)
    {
        if ($this->_limit !== null) {
            $position = $this->offset();
            $max = $this->_start + $this->_limit;
            $length = $max - $position;
        }
        if ($length === null) {
            $length = $this->_bufferSize;
        }
        return $length;
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
        $length = $this->_bufferSize($length);
        if ($length <= 0) {
            return '';
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
     * Pushes data to the stream. The difference with `write()` is that the position of the file pointer still unchanged.
     *
     * @param  string  $string The string that is to be written.
     * @param  integer $length If the length argument is given, writing will stop after length bytes have
     *                         been written or the end of string if reached, whichever comes first.
     * @return integer         Number of bytes written
     */
    public function push($string, $length = null)
    {
        $this->_writable();
        $offset = $this->offset();
        if (null === $length) {
            $result = fwrite($this->_resource, $string);
        } else {
            $result = fwrite($this->_resource, $string, $length);
        }
        $this->seek($offset);
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
        $result = stream_copy_to_stream($this->resource(), $stream->resource());
        $stream->rewind();
        return $result;
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
            throw new StreamException("Invalid stream resource, unable to set a timeout on it.");
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
        fseek($this->_resource, $offset, $whence);
        return ftell($this->_resource);
    }

    /**
     * Moves the file pointer to the beginning of the stream.
     *
     * @return Boolean
     */
    public function rewind()
    {
        return $this->seek($this->_start);
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
     * Seeks to the end of the stream.
     *
     * @return Boolean
     */
    public function end()
    {
        if ($this->_limit === null) {
            return $this->seek(0, SEEK_END);
        } else {
            return $this->seek($this->_start + $this->_limit);
        }
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

        $old = $this->offset();

        $begin = $this->rewind();
        $end = $this->end();

        $this->seek($old);
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
        if ($this->_limit === null) {
            return feof($this->_resource);
        }
        $position = $this->offset();
        $max = $this->_start + $this->_limit;
        return $position >= $max;
    }

    /**
     * Returns the remaining data from the stream (same as flush).
     *
     * @return string
     */
    public function __toString() {
        if (!$this->seekable()) {
            return $this->flush();
        }
        $old = $this->offset();
        $result = $this->flush();
        $this->seek($old);
        return $result;
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

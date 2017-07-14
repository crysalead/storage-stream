<?php
namespace Lead\Storage\Stream;

use RuntimeException;
use InvalidArgumentException;

class Stream implements \Psr\Http\Message\StreamInterface
{
    use Psr7\StreamTrait;

    /**
     * The stream resource.
     *
     * @var resource
     */
    protected $_resource = null;

    /**
     * The filename of the resource
     *
     * @var string
     */
    protected $_filename = null;

    /**
     * The open mode of the filename resource;
     *
     * @var string
     */
    protected $_mode = 'r+';

    /**
     * The mime info.
     *
     * @var string
     */
    protected $_mime = null;

    /**
     * The charset info.
     *
     * @var string
     */
    protected $_charset = null;

    /**
     * The encoding
     *
     * @var string
     */
    protected $_encoding = null;

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
     * The stream length.
     *
     * @var integer
     */
    protected $_length = null;

    /**
     * The timeout in microseconds
     *
     * @var integer
     */
    protected $_timeout = -1;

    /**
     * End user custom option array
     *
     * @var array
     */
    protected $_options = [];

    /**
     * The constructor
     *
     * @param array $config The configuration array. Possibles values are:
     *                      -`data`       _mixed_  : a data string or a stream resource.
     *                      -`filename`   _mixed_  : a filename.
     *                      -`mode`       _string_ : the type of access required for this stream.
     *                      -`mime`       _mixed_  : a mime string or `true` for an auto detection.
     *                      -`bufferSize` _interger: number of bytes to read on read by defaults.
     */
    public function __construct($config = [])
    {
        $defaults = [
            'data'       => '',
            'filename'   => null,
            'mode'       => 'r+',
            'mime'       => null,
            'charset'    => null,
            'encoding'   => false,
            'start'      => 0,
            'limit'      => null,
            'length'     => null,
            'bufferSize' => 4096,
            'options'    => []
        ];
        if (!is_array($config)) {
            $config = ['data' => $config];
        }
        $config += $defaults;

        $this->_initResource($config);
        $this->_length = $config['length'];

        $this->bufferSize($config['bufferSize']);
        $this->start($config['start'], false);
        $this->limit($config['limit']);
        $this->mime($config['mime']);
        $this->charset($config['charset']);
        $this->encoding($config['encoding']);
        $this->options($config['options']);

        if ($this->_start > 0) {
            if(!$this->isSeekable()) {
                throw new RuntimeException("The `'start'` option can't be used with non seekable streams.");
            }
            $this->rewind();
        }
    }

    /**
     * Init the stream resource.
     *
     * @param array $config The constructor configuration array.
     */
    protected function _initResource($config)
    {
        if (!empty($config['data']) && !empty($config['filename'])) {
            throw new InvalidArgumentException("The `'data'` or `'filename'` option must be defined.");
        }
        if ($config['filename']) {
            $this->_filename = $config['filename'];
            if ($this->_filename === 'php://input') {
                $this->_mode = 'r';
            }
            $this->_resource = fopen($this->_filename, $this->_mode);
            return;
        }
        if (is_resource($config['data'])) {
            $this->_resource = $config['data'];
            return;
        } elseif (is_scalar($config['data'])) {
            $this->_resource = fopen('php://temp', 'r+');
            fwrite($this->_resource, (string) $config['data']);
            rewind($this->_resource);
        }
    }

    /**
     * Get the resource handler.
     *
     * @return resource
     */
    public function resource()
    {
        if (!$this->valid()) {
            throw new RuntimeException('Invalid resource.');
        }
        return $this->_resource;
    }

    /**
     * Get/set the starting offset.
     *
     * @param  integer $start The offset to set.
     * @return integer        The setted offset.
     */
    public function start($start = null, $autoseek = true)
    {
        if (!func_num_args()) {
            return $this->_start;
        }
        $this->_start = (int) $start;

        if ($autoseek) {
            $this->rewind();
        }
        return $this;
    }

    /**
     * Get/set the stream range limit.
     *
     * @param  integer      $limit The limit to set.
     * @return integer|null        The setted limit.
     */
    public function limit($limit = null)
    {
        if (!func_num_args()) {
            return $this->_limit;
        }
        $this->_limit = $limit;
        return $this;
    }

    /**
     * Get the stream range length.
     */
    public function length()
    {
        if ($this->_limit !== null) {
            return $this->_limit;
        }
        if ($this->_length !== null) {
            return $this->_length;
        }
        if ($this->isSeekable()) {
            $old = $this->tell();

            $begin = $this->rewind();
            $end = $this->end();

            $this->seek($old);
            return $end - $begin;
        }
    }

    /**
     * Get/set the range.
     *
     * @param  integer $range The range to set.
     * @return string         The setted range.
     */
    public function range($range = null)
    {
        if (!func_num_args()) {
            return $this->_start . '-' . ($this->_limit ? $this->_start + $this->_limit : '');
        }
        $values = explode('-', $range);
        $this->_start = (int) $values[0];
        $this->_limit = $values[1] !== '' ? $values[1] - $values[0] : null;
        return $this;
    }

    /**
     * Get/set the stream mime.
     *
     * @param  mixed  $mime The mime string to set or `true` to autodetect the mime.
     * @return string       The mime.
     */
    public function mime($mime = null)
    {
        if (!func_num_args()) {
            return $this->_mime;
        }
        return $this->_mime = static::getMime($this, $mime);
    }

    /**
     * Get/set the charset.
     *
     * @param  string $charset
     * @return string           The charset.
     */
    public function charset($charset = null)
    {
        if (!func_num_args()) {
            return $this->_charset;
        }
        $this->_charset = $charset;
        return $this;
    }

    /**
     * Get/set the encoding.
     *
     * @param  string $encoding
     * @return string           The encoding.
     */
    public function encoding($encoding = null)
    {
        if (!func_num_args()) {
            return $this->_encoding;
        }
        $this->_encoding = $encoding;
        return $this;
    }

    /**
     * Get/set end user options.
     *
     * @param  array  $options The end user options to set.
     * @return string          The setted end user options.
     */
    public function options($options = [])
    {
        if (!func_num_args()) {
            return $this->_options;
        }
        $this->_options = $options ?: [];
        return $this;
    }

    /**
     * Get stream meta data.
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
        if ($this->_resource === null && $this->_filename) {
            $this->_resource = fopen($this->_filename, $this->_mode);
        }
        if (!$this->valid()) {
            throw new RuntimeException('Invalid resource.');
        }
        $meta = stream_get_meta_data($this->_resource);

        if ($key) {
            return isset($meta[$key]) ? $meta[$key] : null;
        }
        return $meta;
    }

    /**
     * Check if a stream is a local stream.
     *
     * @return boolean
     */
    public function isLocal()
    {
        return stream_is_local($this->_resource);
    }

    /**
     * Check if a stream is readable.
     *
     * @return boolean
     */
    public function isReadable()
    {
        $mode = $this->meta('mode');
        return $mode[0] === 'r' || strpos($mode, '+');
    }

    /**
     * Throw an exception if a stream is not readable.
     *
     * @param boolean $bytePerByte Check if the stream is readable byte per byte.
     */
    protected function _ensureReadable($bytePerByte = true)
    {
        if ($bytePerByte && $this->_encoding) {
            throw new RuntimeException('Stream with encoding cannot be read byte per byte.');
        }
        if (!$this->valid()) {
            throw new RuntimeException('Cannot read from a closed stream.');
        }
        if (!$this->isReadable()) {
            $mode = $this->meta('mode');
            throw new RuntimeException("Cannot read on a non-readable stream (mode is `'{$mode}'`).");
        }
    }

    /**
     * Check if a stream is writable.
     *
     * @return boolean
     */
    public function isWritable()
    {
        $mode = $this->meta('mode');
        return $mode[0] !== 'r' || strpos($mode, '+');
    }

    /**
     * Throw an exception if a stream is not writable.
     */
    protected function _ensureWritable()
    {
        if (!$this->valid()) {
            throw new RuntimeException('Cannot write on a closed stream.');
        }
        if (!$this->isWritable()) {
            $mode = $this->meta('mode');
            throw new RuntimeException("Cannot write on a non-writable stream (mode is `'{$mode}'`).");
        }
    }

    /**
     * Checks if a stream is seekable.
     *
     * @return boolean
     */
    public function isSeekable()
    {
        return $this->meta('seekable');
    }

    /**
     * Throw an exception if a stream is not seekable.
     */
    protected function _ensureSeekable()
    {
        if (!$this->valid()) {
            throw new RuntimeException('Cannot seek on a closed stream.');
        }
        if (!$this->isSeekable()) {
            throw new RuntimeException('Cannot seek on a non-seekable stream.');
        }
    }

    /**
     * Get/set the buffer size.
     *
     * @param  integer $bufferSize The buffer size to set or `null` to get the current buffer size.
     * @return integer             The buffer size.
     */
    public function bufferSize($bufferSize = null)
    {
        if (!func_num_args($bufferSize)) {
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
        $this->_ensureReadable();
        $length = $this->_bufferSize($length);
        if ($length <= 0) {
            return '';
        }
        $result = fread($this->_resource, $length);
        return $result === false ? '' : $result;
    }

    /**
     * Determine the buffer size to read.
     *
     * @param  integer $length Maximum number of bytes to read (default to buffer size).
     * @return integer         The allowed size.
     */
    protected function _bufferSize($length)
    {
        if ($this->_limit !== null) {
            $position = $this->tell();
            $max = $this->_start + $this->_limit;
            $length = $max - $position;
        }
        if ($length === null) {
            $length = $this->_bufferSize;
        }
        return $length;
    }

    /**
     * Read one line from the stream.
     *
     * @param  integer $length Maximum number of bytes to read (default to buffer size).
     * @param  string  $ending Line ending to stop at (default to "\n").
     * @return string          The data.
     */
    public function getLine($length = null, $ending = "\n")
    {
        $this->_ensureReadable();
        $length = $this->_bufferSize($length);
        if ($length <= 0) {
            return '';
        }
        $result = stream_get_line($this->_resource, $length, $ending);
        return $result === false ? '' : $result;
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
        $this->_ensureWritable();
        if (null === $length) {
            $result = fwrite($this->_resource, $string);
        } else {
            $result = fwrite($this->_resource, $string, $length);
        }
        return $result;
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
     * Push data to the stream. The difference with `write()` is that the position of the file pointer still unchanged.
     *
     * @param  string  $string The string that is to be written.
     * @param  integer $length If the length argument is given, writing will stop after length bytes have
     *                         been written or the end of string if reached, whichever comes first.
     * @return integer         Number of bytes written
     */
    public function push($string, $length = null)
    {
        $this->_ensureWritable();
        $offset = $this->tell();
        if (null === $length) {
            $result = fwrite($this->_resource, $string);
        } else {
            $result = fwrite($this->_resource, $string, $length);
        }
        $this->seek($offset);
        return $result;
    }

    /**
     * Read the content of this stream and write it to another stream.
     *
     * @param  instance $stream The destination stream to write to
     * @return integer          The number of copied bytes
     */
    public function pipe($stream)
    {
        $offset = $stream->tell();
        $result = stream_copy_to_stream($this->resource(), $stream->resource());
        if ($stream->isSeekable()) {
            $stream->seek($offset);
        }
        return $result;
    }

    /**
     * Return the remaining data from the stream.
     *
     * @param  string $encode Indicate if the returned data must ben encoded or not
     * @return string
     */
    public function flush($encode = true)
    {
        $this->_ensureReadable(false);
        $content = stream_get_contents($this->_resource);
        return $encode && $this->_encoding ? static::encode($content, $this->_encoding) : $content;
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
            throw new RuntimeException("Invalid stream resource, unable to set a timeout on it.");
        }
        $this->_timeout = $delay;
        return stream_set_timeout($this->_resource, 0, $delay);
    }

    /**
     * Get the position of the file pointer
     *
     * @return integer
     */
    public function tell()
    {
        if ($this->_resource === null && $this->_filename) {
            $this->_resource = fopen($this->_filename, $this->_mode);
        }
        return ftell($this->_resource);
    }

    /**
     * Seek on the stream.
     *
     * @param integer $offset The offset.
     * @param integer $whence Accepted values are:
     *                        - SEEK_SET - Set position equal to $offset bytes.
     *                        - SEEK_CUR - Set position to current location plus $offset.
     *                        - SEEK_END - Set position to end-of-file plus $offset.
     */
    public function seek($offset, $whence = SEEK_SET)
    {
        if ($this->_filename === 'php://input' && $this->eof() && !$offset && $whence === SEEK_SET) {
            $this->close();
            $this->_resource = fopen($this->_filename, 'r');
        }
        $this->_ensureSeekable();
        fseek($this->_resource, $offset, $whence);
        return ftell($this->_resource);
    }

    /**
     * Move the file pointer to the beginning of the stream.
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
     * Seek to the end of the stream.
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
     * Check if the stream is valid.
     *
     * @return Boolean
     */
    public function valid()
    {
        if ($this->_resource === null && $this->_filename) {
            $this->_resource = fopen($this->_filename, $this->_mode);
        }
        return !!$this->_resource && is_resource($this->_resource);
    }

    /**
     * Checks for EOF.
     *
     * @return boolean
     */
    public function eof()
    {
        $this->_ensureReadable();
        if ($this->_limit === null) {
            return feof($this->_resource);
        }
        $position = $this->tell();
        $max = $this->_start + $this->_limit;
        return $position >= $max;
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
        $old = $this->tell();
        $this->rewind();
        $result = $this->flush();
        $this->seek($old);
        return $result;
    }

    /**
     * Detaches the stream
     */
    public function detach()
    {
        $resource = $this->_resource;
        $this->_resource = null;
        return $resource;
    }

    /**
     * Closes the stream
     */
    public function close()
    {
        $resource = $this->detach();
        if (!is_resource($resource)) {
            return true;
        }
        return fclose($resource);
    }

    /**
     * Closes the stream
     */
    public function __destruct()
    {
        $this->close();
    }

    /**
     * Mime detector.
     * Concat the first 1024 bytes + the last 4 bytes of readable & seekable streams
     * to detext the mime info.
     *
     * @param string $stream The stream to extract mime value from.
     * @param string $mime   The mime type detection. Possible values are:
     *                       -`true`    : auto detect the mime.
     *                       - a string : don't detect the mime and use the passed string instead.
     *                       -`false`   : don't detect the mime.
     * @return string        The detected mime.
     */
    public static function getMime($stream, $mime)
    {
        if (is_string($mime)) {
            return $mime;
        }
        if (!$mime || !$stream->isSeekable() || !$stream->isReadable()) {
            return 'application/octet-stream';
        }
        $finfo = finfo_open(FILEINFO_MIME_TYPE);

        $old = $stream->tell();
        $stream->rewind();
        $end = $stream->end();

        $size = min($end - 0, 4);
        if ($size === 0) {
            return 'application/octet-stream';
        }

        $stream->seek($size, SEEK_SET);
        $signature = $stream->read($size, false);

        $size = min($end - 0, 1024);
        $stream->rewind();
        $signature = $stream->read($size, false) . $signature;
        $stream->seek($old, SEEK_SET);

        return finfo_buffer($finfo, $signature);
    }

    /**
     * Clones the message.
     */
    public function __clone()
    {
        if ($this->_filename !== null) {
            $this->_resource = fopen($this->_filename, $this->_mode);
            return;
        }
        if (!$this->isSeekable()) {
            throw new RuntimeException("Cannot clone a non seekable stream.");
        }
        $resource = fopen('php://temp', 'r+');
        fwrite($resource, (string) $this);
        rewind($resource);
        $this->_resource = $resource;
    }

    /**
     * Encoding method.
     *
     * Note: Not relying on `stream_filter_append()` since not stable.
     *
     * @param  string $body     The message to encode.
     * @param  string $encoding The encoding.
     * @return string
     */
    public static function encode($body, $encoding, $le = "\r\n")
    {
        switch ($encoding) {
            case 'quoted-printable':
                $body = quoted_printable_encode($body);
                break;
            case 'base64':
                $body = rtrim(chunk_split(base64_encode($body), 76, $le));
                break;
            case '7bit':
                $body = preg_replace('~[\x80-\xFF]+~', '', $body);
            case '8bit':
                $body = str_replace(["\x00", "\r"], '', $body);
                $body = str_replace("\n", "\r\n", $body);
                if (preg_match('~^(.{' . 1000 . ',})~m', $body)) {
                    throw new RuntimeException("A line with more that 1000 characters has been detected, cannot use `'{$encoding}'` encoding.");
                }
                break;
            case 'binary':
                break;
            default:
                throw new InvalidArgumentException("Unsupported encoding `'{$encoding}'`.");
                break;
        }
        return $body;
    }
}

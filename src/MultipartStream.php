<?php
namespace Lead\Storage\Stream;

use RuntimeException;
use InvalidArgumentException;

class MultipartStream extends MultiStream
{
    use Psr7\StreamTrait;

    /**
     * The multipart boundary value
     *
     * @var string
     */
    protected $_boundary = null;

    /**
     * The constructor
     *
     * @param array $config The configuration array. Possibles values are:
     *                      -`boundary` _string_ : an optional boundary value.
     */
    public function __construct($config = [])
    {
        $defaults = [
            'boundary' => null
        ];
        $config += $defaults;
        $this->_boundary = isset($config['boundary']) ? $config['boundary'] : sha1(uniqid('', true));
        parent::__construct($config);
    }

    /**
     * Get the boundary
     *
     * @return string
     */
    public function boundary()
    {
        return $this->_boundary;
    }

    /**
     * Get/set the stream mime.
     *
     * @param  mixed  $mime The mime string to set or `true` to autodetect the mime.
     * @return string       The mime.
     */
    public function mime($mime = null)
    {
        if (func_num_args() === 0) {
            return $this->_mime;
        }
        return $this->_mime = $mime ?: 'multipart/mixed';
    }

    /**
     * Add a stream
     *
     * @param object $stream Stream to append.
     *
     * @throws InvalidArgumentException if the stream is not readable
     */
    public function add($stream, $options = [])
    {
        $defaults = [
            'id'          => null,
            'name'        => null,
            'filename'    => null,
            'description' => null,
            'location'    => null,
            'language'    => null,
            'length'      => false,
            'disposition' => null, // 'form-data' or for emails 'attachement' or 'inline' can be usefull
            'mime'        => true,
            'encoding'    => null,
            'charset'     => null,
            'headers'     => []
        ];

        $options += $defaults;

        if (!$stream instanceof Stream) {
            $stream = new Stream(['data' => $stream, 'options' => $options]);
        } else {
            $stream->options($options);
        }

        if (!isset($options['name'])) {
            throw new InvalidArgumentException("The `'name'` option is required.");
        }

        parent::add($stream);

        return $stream;
    }

    /**
     * Write data to the stream.
     *
     * @param  string  $string The string that is to be written.
     * @param  integer $length If the length argument is given, writing will stop after length bytes have
     *                         been written or the end of string if reached, whichever comes first.
     * @return integer         Number of bytes written
     */
    public function read($string, $length = null)
    {
        throw new RuntimeException('`MultiStream` instances cannot be read byte per byte.');
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
        throw new RuntimeException('Cannot extract `MultiStream` length.');
    }

    /**
     * Return the remaining data from the stream.
     *
     * @return string
     */
    public function flush()
    {
        $buffer = '';
        foreach ($this->_streams as $stream) {
            $buffer .= '--' . $this->boundary() . "\r\n";
            $content = '';
            while (!$stream->eof()) {
                $content .= $stream->read($this->_bufferSize);
            }
            $options = $stream->options();

            if (!empty($options['mime'])) {
                $options['mime'] = Stream::getMime($stream, $options['mime']);
                if (empty($options['charset']) && preg_match('~^text/~', $options['mime'])) {
                    $options['charset'] = 'utf-8';
                }
            }

            if (empty($options['encoding']) && !empty($options['mime'])) {
                $options['encoding'] = preg_match('~^text/~', $options['mime']) ? 'quoted-printable' : 'base64';
            }

            $encoded = !empty($options['encoding']) ? static::encode($content, $options['encoding']) : $content;
            $headers = $this->_headers($options, strlen($encoded));
            $buffer .= join("\r\n", $headers) . "\r\n\r\n";
            $buffer .= $encoded . "\r\n";
        }
        return $buffer . '--' . $this->boundary() . "--\r\n";
    }

    /**
     * Extract headers form streams options.
     *
     * @param  array  $options The stream end user options.
     * @param  string $length  The length of the encoded stream.
     * @return array
     */
    protected function _headers($options, $length)
    {
        $headers = !empty($options['headers']) ? $options['headers'] : [];

        if (!empty($options['disposition'])) {
            $parts = [$options['disposition'], "name=\"{$options['name']}\""];
            if (!empty($options['filename'])) {
                $parts[] = "filename=\"{$options['filename']}\"";
            }
            $headers[] = "Content-Disposition: " . join('; ', $parts);
        }

        if (!empty($options['id'])) {
            $headers[] = 'Content-ID: ' . $options['id'];
        }

        if (!empty($options['mime'])) {
            $charset = !empty($options['charset']) ? '; charset=' . $options['charset'] : '';
            $headers[] = 'Content-Type: ' . $options['mime'] . $charset;
        }

        if (!empty($options['encoding'])) {
            $headers[] = 'Content-Transfer-Encoding: ' . $options['encoding'];
        }

        if (!empty($options['length'])) {
            $headers[] = 'Content-Length: ' . $length;
        }

        if (!empty($options['description'])) {
            $headers[] = 'Content-Description: ' . $options['description'];
        }

        if (!empty($options['location'])) {
            $headers[] = 'Content-Location: ' . $options['location'];
        }

        if (!empty($options['language'])) {
            $headers[] = 'Content-Language: ' . $options['language'];
        }
        return $headers;
    }

    /**
     * Encoding method
     *
     * @param  string $body     The message to encode.
     * @param  string $encoding The encoding.
     * @return string
     */
    public static function encode($body, $encoding)
    {
        switch ($encoding) {
            case 'quoted-printable':
                $body = quoted_printable_encode($body);
                break;
            case 'base64':
                $body = rtrim(chunk_split(base64_encode($body), 76, "\r\n"));
                break;
            case '7bit':
                $body = preg_replace('~[\x80-\xFF]+~', '', $body);
            case '8bit':
                $body = str_replace(["\x00", "\r"], '', $body);
                $body = str_replace("\n", "\r\n", $body);
                break;
            default:
                throw new InvalidArgumentException("Unsupported encoding `'{$encoding}'`.");
                break;
        }
        return $body;
    }
}

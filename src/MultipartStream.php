<?php
namespace Lead\Storage\Stream;

use RuntimeException;
use InvalidArgumentException;

class MultipartStream extends MultiStream
{
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
     * Get/set the boundary
     *
     * @param  string $boundary The boundary
     * @return string
     */
    public function boundary($boundary = null)
    {
        if (!func_num_args()) {
            return $this->_boundary;
        }
        $this->_boundary = $boundary;
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
            'disposition' => null,
            'mime'        => true,
            'encoding'    => null,
            'charset'     => null,
            'headers'     => []
        ];

        $options += $defaults;

        $encoding = $options['encoding'];
        $mime = $options['mime'];
        $charset = $options['charset'];

        foreach (['mime', 'charset', 'encoding'] as $name) {
            unset($options[$name]);
        }

        if (!$stream instanceof Stream) {
            $stream = new Stream([
                'data'     => $stream,
                'mime'     => $mime,
                'charset'  => $charset,
                'encoding' => $encoding,
                'options'  => $options
            ]);
        } else {
            foreach (['mime', 'charset', 'encoding'] as $name) {
                if (!empty(${$name})) {
                    $stream->{$name}(${$name});
                }
            }
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

            $mime = $stream->mime();
            $charset = $stream->charset();
            $options = $stream->options();

            if ($mime && !$charset && preg_match('~^text/~', $mime)) {
                $charset = 'utf-8';
            }

            if ($mime && !$stream->encoding()) {
                $stream->encoding(preg_match('~^text/~', $mime) ? 'quoted-printable' : 'base64');
            }

            $content = (string) $stream;
            $headers = $this->_headers($options, $mime, $charset, $stream->encoding(), strlen($content));
            $buffer .= join("\r\n", $headers) . "\r\n\r\n";
            $buffer .= $content . "\r\n";
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
    protected function _headers($options, $mime, $charset, $encoding, $length)
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

        if (!empty($mime)) {
            $charset = $charset ? '; charset=' . $charset : '';
            $headers[] = 'Content-Type: ' . $mime . $charset;
        }

        if (!empty($encoding)) {
            $headers[] = 'Content-Transfer-Encoding: ' . $encoding;
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
}

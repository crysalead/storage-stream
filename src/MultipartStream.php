<?php
namespace Lead\Storage\Stream;

use InvalidArgumentException;

class MultipartStream extends MultiStream
{
    use Psr7\StreamTrait;

    /**
     * The multipart boundary value
     *
     * @var string
     */
    protected $_boundary;

    /**
     * Indicate if the last stream must be keept at the last position.
     *
     * @return boolean
     */
    protected $_keepLast = true;

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
        parent::add('--' . $this->boundary() . "--\r\n");
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
            'name'        => null,
            'filename'    => null,
            'length'      => null,
            'disposition' => 'form-data', // For emails 'attachement' or 'inline' can be usefull
            'mime'        => true
        ];

        $options += $defaults;

        if (!$stream instanceof Stream) {
            $stream = new Stream(['data' => $stream]);
        }

        if (!isset($options['name'])) {
            throw new InvalidArgumentException("The `'name'` option is required.");
        }

        $mime = Stream::getMime($stream, $options['mime']);
        $length = isset($options['length']) ? $options['length'] : $stream->length();

        $parts = [$options['disposition'], "name=\"{$options['name']}\""];
        if (isset($options['filename'])) {
            $parts[] = "filename=\"{$options['filename']}\"";
        }

        $headers[] = "Content-Disposition: " . join('; ', $parts);
        $headers[] = "Content-Type: {$mime}";
        if ($length) {
            $headers[] = 'Content-Length: ' . $length;
        }

        parent::add('--' . $this->boundary() . "\r\n");
        parent::add(join("\r\n", $headers) . "\r\n\r\n");
        parent::add($stream);
        parent::add("\r\n");

        return $this;
    }
}

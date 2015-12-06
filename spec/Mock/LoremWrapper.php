<?php
namespace Lead\Storage\Stream\Spec\Mock;

class LoremWrapper
{
    public $context;

    protected $_offset = 0;

    protected $_timeout = -1;

    protected $_timedOut = false;

    protected $_blocking = false;

    protected $_eof = false;

    public function stream_open($path, $mode, $options, &$opened_path)
    {
        return true;
    }

    public function stream_read($count)
    {
        $this->_offset += $count;
        return str_pad('', $count, 'Lorem ipsum dolor sit amet, consectetur adipiscing elit. ');
    }

    public function stream_write($data)
    {
        return strlen($data);
    }

    public function stream_flush()
    {
        $this->_eof = true;
        return $this->stream_read(56);
    }

    public function stream_tell()
    {
        return $this->_offset;
    }

    public function stream_set_option($option , $arg1 , $arg2)
    {
        switch ($option) {
            case STREAM_OPTION_BLOCKING:
                $this->_blocking = $arg1;
            break;
            case STREAM_OPTION_READ_TIMEOUT:
                $this->_timeout = $arg1 * 1000 + $arg2;
            break;
            case STREAM_OPTION_WRITE_BUFFER:
            break;
        }
    }

    public function stream_eof()
    {
        return $this->_eof;
    }

    public function stream_close() {
    }

}

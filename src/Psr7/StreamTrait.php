<?php
namespace Lead\Storage\Stream\Psr7;

trait StreamTrait
{
    /**
     * PSR-7 aliases
     */
    public function getSize()
    {
        return $this->length();
    }

    public function getContents()
    {
        return $this->flush();
    }

    public function getMetadata($key = null)
    {
        return $this->meta($key);
    }
}

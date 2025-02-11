<?php
namespace Lead\Storage\Stream\Psr7;

/**
 * PSR-7 Stream interoperability trait
 */
trait StreamTrait
{
    public function getSize(): ?int
    {
        return $this->length();
    }

    public function getContents(): string
    {
        return $this->flush();
    }

    public function getMetadata($key = null)
    {
        return $this->meta($key);
    }

    /**
     * Returns the remaining data from the stream (same as flush).
     *
     * @return string
     */
    public function __toString()
    {
        return $this->toString();
    }
}

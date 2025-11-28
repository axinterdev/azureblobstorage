<?php

namespace AxInter\AzureBlobStorage\Exceptions;

class InvalidStreamException extends AzureBlobStorageException
{
    public function __construct(string $message = "Invalid stream resource provided", int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}

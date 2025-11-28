<?php

namespace AxInter\AzureBlobStorage\Exceptions;

class InvalidConnectionStringException extends AzureBlobStorageException
{
    public function __construct(string $message = "Invalid connection string format", int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}

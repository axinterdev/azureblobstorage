<?php

namespace AxInter\AzureBlobStorage\Exceptions;

class AuthenticationException extends AzureBlobStorageException
{
    public function __construct(string $message = "Authentication failed", int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}

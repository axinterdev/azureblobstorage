<?php

namespace AxInter\AzureBlobStorage\Exceptions;

class BlobNotFoundException extends AzureBlobStorageException
{
    public function __construct(string $path, int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct("Blob not found: {$path}", $code, $previous);
    }
}

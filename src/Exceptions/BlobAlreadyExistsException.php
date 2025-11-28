<?php

namespace AxInter\AzureBlobStorage\Exceptions;

class BlobAlreadyExistsException extends AzureBlobStorageException
{
    public function __construct(string $path, int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct("Blob already exists: {$path}", $code, $previous);
    }
}

<?php

namespace AxInter\AzureBlobStorage\Exceptions;

class ContainerNotFoundException extends AzureBlobStorageException
{
    public function __construct(string $container, int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct("Container not found: {$container}", $code, $previous);
    }
}

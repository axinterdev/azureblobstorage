<?php

namespace AxInter\AzureBlobStorage\Exceptions;

class VersionNotFoundException extends AzureBlobStorageException
{
    public function __construct(string $path, string $versionId, int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct("Version {$versionId} not found for blob: {$path}", $code, $previous);
    }
}

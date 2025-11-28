<?php

namespace AxInter\AzureBlobStorage;

use AxInter\AzureBlobStorage\Exceptions\InvalidConnectionStringException;

class Config implements Interfaces\ConfigInterface
{
    public string $connectionString;
    public string $accountName;
    public string $accountKey;
    public string $blobEndpoint;
    public string $protocol;

    public function __construct(string $connectionString)
    {
        $this->connectionString = $connectionString;
        $this->parseConnectionString();
    }

    private function parseConnectionString(): void
    {
        $parts = [];
        foreach (explode(';', $this->connectionString) as $segment) {
            if (empty($segment)) {
                continue;
            }
            [$key, $value] = explode('=', $segment, 2);
            $parts[trim($key)] = trim($value);
        }

        $this->accountName = $parts['AccountName'] ?? throw new InvalidConnectionStringException('AccountName is required in connection string');
        $this->accountKey = $parts['AccountKey'] ?? throw new InvalidConnectionStringException('AccountKey is required in connection string');
        
        $this->protocol = $parts['DefaultEndpointsProtocol'] ?? 'https';
        $this->blobEndpoint = $parts['BlobEndpoint'] ?? "{$this->protocol}://{$this->accountName}.blob.core.windows.net";
    }

    public function getAccountName(): string
    {
        return $this->accountName;
    }

    public function getAccountKey(): string
    {
        return $this->accountKey;
    }

    public function getBlobEndpoint(): string
    {
        return $this->blobEndpoint;
    }

    public function getProtocol(): string
    {
        return $this->protocol;
    }
}

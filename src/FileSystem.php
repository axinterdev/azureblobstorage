<?php

namespace Axinter\AzureBlobStorage;

use AxInter\AzureBlobStorage\Exceptions\AzureBlobStorageException;
use AxInter\AzureBlobStorage\Exceptions\BlobNotFoundException;
use AxInter\AzureBlobStorage\Exceptions\ContainerNotFoundException;
use AxInter\AzureBlobStorage\Exceptions\InvalidStreamException;
use AxInter\AzureBlobStorage\Exceptions\VersionNotFoundException;
use AxInter\AzureBlobStorage\Interfaces\FileSystemInterface;
use AzureOss\Storage\Common\Auth\StorageSharedKeyCredential;
use AzureOss\Storage\Common\Middleware\AddSharedKeyAuthorizationHeaderMiddleware;
use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\GuzzleException;

class FileSystem implements FileSystemInterface
{
    private Client $client;
    private Config $config;
    private string $container;

    public function __construct(Config $config, string $container)
    {
        $this->config = $config;
        $this->container = $container;

        // Use azure-oss authentication middleware
        $credential = new StorageSharedKeyCredential(
            $config->getAccountName(),
            $config->getAccountKey()
        );

        $stack = HandlerStack::create();
        $stack->push(new AddSharedKeyAuthorizationHeaderMiddleware($credential));

        $this->client = new Client([
            'base_uri' => rtrim($config->getBlobEndpoint(), '/') . '/',
            'handler' => $stack,
            'timeout' => 30,
        ]);
    }

    public function write(string $path, string $contents): bool
    {
        try {
            // For Azurite: Implement custom versioning by copying existing blob before overwriting
            if ($this->exists($path)) {
                $timestamp = time();
                $versionPath = ".versions/{$path}/" . $timestamp;

                // Copy current version to version history
                try {
                    $this->copy($path, $versionPath);
                } catch (\Exception $e) {
                    // If versioning copy fails, continue with write
                }
            }

            $url = "{$this->container}/{$path}";

            $this->client->put($url, [
                'headers' => [
                    'x-ms-date' => gmdate('D, d M Y H:i:s T'),
                    'x-ms-version' => '2021-08-06',
                    'x-ms-blob-type' => 'BlockBlob',
                ],
                'body' => $contents,
            ]);

            return true;
        } catch (GuzzleException $e) {
            return false;
        }
    }

    public function push(string $path, string $contents): bool
    {
        return $this->write($path, $contents);
    }

    public function get(string $path): ?string
    {
        try {
            $url = "{$this->container}/{$path}";
            $headers = $this->getStandardHeaders();

            $response = $this->client->get($url, [
                'headers' => $headers,
            ]);

            return $response->getBody()->getContents();
        } catch (ClientException $e) {
            if ($e->getResponse()->getStatusCode() === 404) {
                throw new BlobNotFoundException($path, 0, $e);
            }
            throw new AzureBlobStorageException($e->getMessage(), $e->getCode(), $e);
        } catch (GuzzleException $e) {
            throw new AzureBlobStorageException($e->getMessage(), $e->getCode(), $e);
        }
    }

    public function read(string $path): ?string
    {
        return $this->get($path);
    }

    public function delete(string $path): bool
    {
        try {
            $url = "{$this->container}/{$path}";
            $headers = $this->getStandardHeaders();

            $this->client->delete($url, [
                'headers' => $headers,
            ]);

            return true;
        } catch (GuzzleException $e) {
            return false;
        }
    }

    public function exists(string $path): bool
    {
        try {
            $url = "{$this->container}/{$path}";

            $this->client->head($url, [
                'headers' => $this->getStandardHeaders(),
            ]);

            return true;
        } catch (ClientException $e) {
            if ($e->getResponse()->getStatusCode() === 404) {
                return false;
            }
            throw $e;
        } catch (GuzzleException $e) {
            return false;
        }
    }

    public function fileExists(string $path): bool
    {
        return $this->exists($path);
    }

    public function list(string $prefix = ''): array
    {
        try {
            $url = "{$this->container}";
            $query = [
                'restype' => 'container',
                'comp' => 'list',
            ];

            if ($prefix) {
                $query['prefix'] = $prefix;
            }

            $headers = $this->getStandardHeaders();

            $response = $this->client->get($url, [
                'headers' => $headers,
                'query' => $query,
            ]);

            $xml = simplexml_load_string($response->getBody()->getContents());
            $files = [];

            if (isset($xml->Blobs->Blob)) {
                foreach ($xml->Blobs->Blob as $blob) {
                    $files[] = [
                        'name' => (string) $blob->Name,
                        'size' => (int) $blob->Properties->{'Content-Length'},
                        'last_modified' => (string) $blob->Properties->{'Last-Modified'},
                        'content_type' => (string) $blob->Properties->{'Content-Type'},
                    ];
                }
            }

            return $files;
        } catch (GuzzleException $e) {
            return [];
        }
    }

    public function copy(string $source, string $destination): bool
    {
        try {
            $sourceUrl = "{$this->config->getBlobEndpoint()}/{$this->container}/{$source}";
            $destUrl = "{$this->container}/{$destination}";

            $headers = array_merge($this->getStandardHeaders(), [
                'x-ms-copy-source' => $sourceUrl,
            ]);

            $this->client->put($destUrl, [
                'headers' => $headers,
            ]);

            return true;
        } catch (GuzzleException $e) {
            return false;
        }
    }

    public function move(string $source, string $destination): bool
    {
        if ($this->copy($source, $destination)) {
            return $this->delete($source);
        }
        return false;
    }

    public function getUrl(string $path): string
    {
        return "{$this->config->getBlobEndpoint()}/{$this->container}/{$path}";
    }

    public function getSize(string $path): ?int
    {
        try {
            $url = "{$this->container}/{$path}";
            $headers = $this->getStandardHeaders();

            $response = $this->client->head($url, [
                'headers' => $headers,
            ]);

            return (int) $response->getHeader('Content-Length')[0] ?? null;
        } catch (GuzzleException $e) {
            return null;
        }
    }

    public function getMimeType(string $path): ?string
    {
        try {
            $url = "{$this->container}/{$path}";
            $headers = $this->getStandardHeaders();

            $response = $this->client->head($url, [
                'headers' => $headers,
            ]);

            return $response->getHeader('Content-Type')[0] ?? null;
        } catch (GuzzleException $e) {
            return null;
        }
    }

    public function writeStream(string $path, $resource): bool
    {
        if (!is_resource($resource)) {
            throw new InvalidStreamException();
        }

        try {
            $contents = stream_get_contents($resource);
            return $this->write($path, $contents);
        } catch (\Exception $e) {
            throw new AzureBlobStorageException("Failed to write stream: {$e->getMessage()}", 0, $e);
        }
    }

    public function readStream(string $path)
    {
        try {
            $url = "{$this->container}/{$path}";
            $headers = $this->getStandardHeaders();

            $response = $this->client->get($url, [
                'headers' => $headers,
                'stream' => true,
            ]);

            return $response->getBody()->detach();
        } catch (GuzzleException $e) {
            return null;
        }
    }

    public function setMetadata(string $path, array $metadata): bool
    {
        try {
            $url = "{$this->container}/{$path}";
            $query = ['comp' => 'metadata'];

            $metadataHeaders = [];
            foreach ($metadata as $key => $value) {
                $metadataHeaders["x-ms-meta-{$key}"] = $value;
            }

            $headers = $this->getStandardHeaders();

            $this->client->put($url, [
                'headers' => $headers,
                'query' => $query,
            ]);

            return true;
        } catch (GuzzleException $e) {
            return false;
        }
    }

    public function getMetadata(string $path): ?array
    {
        try {
            $url = "{$this->container}/{$path}";

            $response = $this->client->head($url, [
                'headers' => $this->getStandardHeaders(),
            ]);

            $metadata = [];
            foreach ($response->getHeaders() as $key => $values) {
                if (str_starts_with(strtolower($key), 'x-ms-meta-')) {
                    $metaKey = substr($key, 10);
                    $metadata[$metaKey] = $values[0] ?? null;
                }
            }

            return $metadata;
        } catch (GuzzleException $e) {
            return null;
        }
    }

    public function createContainer(): bool
    {
        try {
            $url = "{$this->container}";
            $query = ['restype' => 'container'];
            $headers = $this->getStandardHeaders();

            $this->client->put($url, [
                'headers' => $headers,
                'query' => $query,
            ]);

            return true;
        } catch (GuzzleException $e) {
            return false;
        }
    }

    public function deleteContainer(): bool
    {
        try {
            $url = "{$this->container}";
            $query = ['restype' => 'container'];
            $headers = $this->getStandardHeaders();

            $this->client->delete($url, [
                'headers' => $headers,
                'query' => $query,
            ]);

            return true;
        } catch (GuzzleException $e) {
            return false;
        }
    }

    public function containerExists(): bool
    {
        try {
            $url = "{$this->container}";
            $query = ['restype' => 'container'];
            $headers = $this->getStandardHeaders();

            $this->client->head($url, [
                'headers' => $headers,
                'query' => $query,
            ]);

            return true;
        } catch (GuzzleException $e) {
            return false;
        }
    }

    public function listVersions(string $path): array
    {
        try {
            // For Azurite: Use custom versioning from .versions folder
            $versionPrefix = ".versions/{$path}/";

            $url = "{$this->container}";
            $query = [
                'restype' => 'container',
                'comp' => 'list',
                'prefix' => $versionPrefix,
            ];

            $response = $this->client->get($url, [
                'headers' => $this->getStandardHeaders(),
                'query' => $query,
            ]);

            $xmlContent = $response->getBody()->getContents();
            $xml = simplexml_load_string($xmlContent);
            $versions = [];

            // Add current version
            if ($this->exists($path)) {
                try {
                    $metadata = $this->getMetadata($path);
                    $size = $this->getSize($path);
                    $mimeType = $this->getMimeType($path);

                    $versions[] = [
                        'version_id' => $metadata['version-timestamp'] ?? 'current',
                        'is_current_version' => true,
                        'last_modified' => date('D, d M Y H:i:s T'),
                        'size' => $size ?? 0,
                        'content_type' => $mimeType ?? 'application/octet-stream',
                    ];
                } catch (\Exception $e) {
                    // Ignore errors getting current version
                }
            }

            // Add historical versions
            if (isset($xml->Blobs->Blob)) {
                foreach ($xml->Blobs->Blob as $blob) {
                    $blobName = (string) $blob->Name;
                    // Extract timestamp from path like .versions/file.txt/1732709123
                    if (preg_match('#\.versions/.+/(\d+)$#', $blobName, $matches)) {
                        $timestamp = $matches[1];
                        $versions[] = [
                            'version_id' => $timestamp,
                            'is_current_version' => false,
                            'last_modified' => date('D, d M Y H:i:s T', (int)$timestamp),
                            'size' => (int) $blob->Properties->{'Content-Length'},
                            'content_type' => (string) $blob->Properties->{'Content-Type'},
                        ];
                    }
                }
            }

            // Sort by timestamp descending (newest first)
            usort($versions, function ($a, $b) {
                $aTime = is_numeric($a['version_id']) ? (int)$a['version_id'] : time();
                $bTime = is_numeric($b['version_id']) ? (int)$b['version_id'] : time();
                return $bTime - $aTime;
            });

            return $versions;
        } catch (GuzzleException $e) {
            return [];
        }
    }

    public function getVersion(string $path, string $versionId): ?string
    {
        try {
            // For custom versioning: Get content from versioned blob
            // If version is "current", get from main path
            if ($versionId === 'current') {
                return $this->get($path);
            }

            $versionPath = ".versions/{$path}/{$versionId}";

            if (!$this->exists($versionPath)) {
                throw new VersionNotFoundException($path, $versionId);
            }

            return $this->get($versionPath);
        } catch (VersionNotFoundException $e) {
            throw $e;
        } catch (ClientException $e) {
            if ($e->getResponse()->getStatusCode() === 404) {
                throw new VersionNotFoundException($path, $versionId, 0, $e);
            }
            throw new AzureBlobStorageException($e->getMessage(), $e->getCode(), $e);
        } catch (GuzzleException $e) {
            throw new AzureBlobStorageException($e->getMessage(), $e->getCode(), $e);
        }
    }

    public function deleteVersion(string $path, string $versionId): bool
    {
        try {
            // For custom versioning: Delete the versioned blob
            // Don't allow deleting "current" version directly
            if ($versionId === 'current') {
                return false;
            }

            $versionPath = ".versions/{$path}/{$versionId}";

            if (!$this->exists($versionPath)) {
                return false;
            }

            return $this->delete($versionPath);
        } catch (GuzzleException $e) {
            return false;
        }
    }

    public function restoreVersion(string $path, string $versionId): bool
    {
        try {
            // For custom versioning: Restore is same as promote
            // Get the version content and write it (which creates a new version)
            $content = $this->getVersion($path, $versionId);

            if ($content === null) {
                return false;
            }

            return $this->write($path, $content);
        } catch (\Exception $e) {
            return false;
        }
    }

    public function promoteVersion(string $path, string $versionId): bool
    {
        try {
            // For custom versioning: Promote is same as restore
            // Get the version content and write it (which creates a new version)
            $content = $this->getVersion($path, $versionId);

            if ($content === null) {
                return false;
            }

            return $this->write($path, $content);
        } catch (\Exception $e) {
            return false;
        }
    }

    private function getStandardHeaders(): array
    {
        return [
            'x-ms-date' => gmdate('D, d M Y H:i:s T'),
            'x-ms-version' => '2021-08-06',
        ];
    }

    private function getAuthHeaders(string $method, string $url, array $additionalHeaders = [], array $query = []): array
    {
        $date = gmdate('D, d M Y H:i:s T');
        $headers = array_merge([
            'x-ms-date' => $date,
            'x-ms-version' => '2021-08-06',
        ], $additionalHeaders);

        $canonicalizedHeaders = $this->buildCanonicalizedHeaders($headers);
        $canonicalizedResource = $this->buildCanonicalizedResource($url, $query);

        // Get Content-Length from headers (will be empty string if not set)
        $contentLength = $headers['Content-Length'] ?? '';
        // Azure requires empty string if Content-Length is 0
        if ($contentLength === '0' || $contentLength === 0) {
            $contentLength = '';
        }
        $contentType = $headers['Content-Type'] ?? '';

        $stringToSign = implode("\n", [
            $method,
            '',
            '',
            $contentLength,
            '',
            $contentType,
            '',
            '',
            '',
            '',
            '',
            '',
            $canonicalizedHeaders,
            $canonicalizedResource,
        ]);

        $signature = base64_encode(hash_hmac('sha256', $stringToSign, base64_decode($this->config->getAccountKey()), true));

    }

    private function buildCanonicalizedHeaders(array $headers): string
    {
        $canonicalized = [];
        foreach ($headers as $key => $value) {
            $key = strtolower($key);
            if (str_starts_with($key, 'x-ms-')) {
                $canonicalized[$key] = "$key:$value";
            }
        }
        ksort($canonicalized);
        return implode("\n", $canonicalized);
    }

    private function buildCanonicalizedResource(string $url, array $query = []): string
    {
        $resource = "/{$this->config->getAccountName()}/{$url}";

        if (!empty($query)) {
            ksort($query);
            foreach ($query as $key => $value) {
                $resource .= "\n" . strtolower($key) . ':' . $value;
            }
        }

        return $resource;
    }
}

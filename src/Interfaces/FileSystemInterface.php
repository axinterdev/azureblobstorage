<?php

namespace AxInter\AzureBlobStorage\Interfaces;

interface FileSystemInterface
{
    public function write(string $path, string $contents): bool;
    public function get(string $path): ?string;
    public function delete(string $path): bool;
    public function exists(string $path): bool;
    public function list(string $prefix = ''): array;
    public function copy(string $source, string $destination): bool;
    public function move(string $source, string $destination): bool;
    public function getUrl(string $path): string;
    public function getSize(string $path): ?int;
    public function getMimeType(string $path): ?string;
    public function writeStream(string $path, $resource): bool;
    public function readStream(string $path);
    public function setMetadata(string $path, array $metadata): bool;
    public function getMetadata(string $path): ?array;
    public function createContainer(): bool;
    public function deleteContainer(): bool;
    public function containerExists(): bool;
    public function listVersions(string $path): array;
    public function getVersion(string $path, string $versionId): ?string;
    public function deleteVersion(string $path, string $versionId): bool;
    public function restoreVersion(string $path, string $versionId): bool;
    public function promoteVersion(string $path, string $versionId): bool;
}

<?php

namespace AxInter\AzureBlobStorage\Interfaces;

interface ConfigInterface
{
    public function getAccountName(): string;
    public function getAccountKey(): string;
    public function getBlobEndpoint(): string;
    public function getProtocol(): string;
}

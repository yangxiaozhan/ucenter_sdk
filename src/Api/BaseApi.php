<?php

declare(strict_types=1);

namespace UCenter\Sdk\Api;

use UCenter\Sdk\UCenterClient;

abstract class BaseApi
{
    protected UCenterClient $client;

    public function __construct(UCenterClient $client)
    {
        $this->client = $client;
    }
}

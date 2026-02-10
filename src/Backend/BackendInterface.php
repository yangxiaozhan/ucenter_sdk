<?php

declare(strict_types=1);

namespace UCenter\Sdk\Backend;

/**
 * 后端请求接口：HTTP 或直连数据库
 */
interface BackendInterface
{
    /**
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    public function request(string $path, array $params = []): array;
}

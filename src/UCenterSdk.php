<?php

declare(strict_types=1);

namespace UCenter\Sdk;

/**
 * UCenter PHP SDK 入口
 *
 * 使用示例：
 * <code>
 * $client = new \UCenter\Sdk\UCenterClient(
 *     'https://uc.example.com',
 *     'your_appid',
 *     'your_secret'
 * );
 * $uid = $client->user()->register('testuser', 'password', 'test@example.com');
 * $user = $client->user()->getUser('testuser');
 * </code>
 */
final class UCenterSdk
{
    public static function client(string $baseUrl, string $appId, string $secret): UCenterClient
    {
        return new UCenterClient($baseUrl, $appId, $secret);
    }
}

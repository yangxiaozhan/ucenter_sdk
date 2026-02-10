<?php

declare(strict_types=1);

namespace UCenter\Sdk\Jwt;

use UCenter\Sdk\Exception\UCenterException;

/**
 * JWT 颁发与验证（HS256）
 * 用于为登录用户颁发 token，并提供验证接口。
 */
class JwtToken
{
    private const ALG = 'HS256';

    private string $secret;

    private int $defaultTtl;

    private string $issuer;

    private string $audience;

    /**
     * @param string $secret       签名密钥，请使用足够长的随机字符串
     * @param int    $defaultTtl   默认 token 有效秒数，默认 7200（2 小时）
     * @param string $issuer       签发者标识，可选
     * @param string $audience     受众标识，可选
     */
    public function __construct(string $secret, int $defaultTtl = 7200, string $issuer = '', string $audience = '')
    {
        $this->secret = $secret;
        $this->defaultTtl = $defaultTtl;
        $this->issuer = $issuer;
        $this->audience = $audience;
    }

    /**
     * 为登录用户颁发 token
     *
     * @param array $payload 业务载荷，建议至少包含 sub（用户 id）、username 等
     * @param int|null $ttlSeconds 有效秒数，null 使用默认
     * @return string JWT 字符串
     */
    public function issue(array $payload, ?int $ttlSeconds = null): string
    {
        $ttl = $ttlSeconds ?? $this->defaultTtl;
        $now = time();
        $claims = array_merge($payload, [
            'iat' => $now,
            'nbf' => $now,
            'exp' => $now + $ttl,
        ]);
        if ($this->issuer !== '') {
            $claims['iss'] = $this->issuer;
        }
        if ($this->audience !== '') {
            $claims['aud'] = $this->audience;
        }
        $header = ['typ' => 'JWT', 'alg' => self::ALG];
        $headerB64 = $this->base64UrlEncode(json_encode($header));
        $payloadB64 = $this->base64UrlEncode(json_encode($claims));
        $signature = $this->sign($headerB64 . '.' . $payloadB64);
        return $headerB64 . '.' . $payloadB64 . '.' . $signature;
    }

    /**
     * 验证 token 并返回载荷
     *
     * @param string $jwt JWT 字符串（通常来自 Authorization: Bearer xxx）
     * @return array 解码后的 payload，含 sub、username、iat、exp 等
     * @throws UCenterException token 无效、过期或签名错误
     */
    public function verify(string $jwt): array
    {
        $jwt = trim($jwt);
        if ($jwt === '') {
            throw new UCenterException('token 为空');
        }

        $parts = explode('.', $jwt);
        if (count($parts) !== 3) {
            throw new UCenterException('token 格式错误');
        }

        [$headerB64, $payloadB64, $signatureB64] = $parts;

        $signature = $this->sign($headerB64 . '.' . $payloadB64);
        if (!hash_equals($this->base64UrlEncode($signature), $signatureB64)) {
            throw new UCenterException('token 签名无效');
        }

        $payloadJson = $this->base64UrlDecode($payloadB64);
        if ($payloadJson === false) {
            throw new UCenterException('token payload 解码失败');
        }

        $payload = json_decode($payloadJson, true);
        if (!is_array($payload)) {
            throw new UCenterException('token payload 无效');
        }

        $now = time();
        if (isset($payload['nbf']) && (int) $payload['nbf'] > $now) {
            throw new UCenterException('token 尚未生效');
        }
        if (isset($payload['exp']) && (int) $payload['exp'] < $now) {
            throw new UCenterException('token 已过期');
        }

        return $payload;
    }

    /**
     * 从请求头或参数中解析 token 并验证（兼容 Authorization: Bearer xxx 与 query/body 中的 token）
     */
    public function verifyFromRequest(array $server = [], array $get = [], array $post = []): array
    {
        $jwt = null;
        $auth = $server['HTTP_AUTHORIZATION'] ?? $server['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
        if (preg_match('/^\s*Bearer\s+(\S+)\s*$/i', $auth, $m)) {
            $jwt = $m[1];
        }
        if ($jwt === null) {
            $jwt = $get['token'] ?? $get['access_token'] ?? $post['token'] ?? $post['access_token'] ?? '';
        }
        return $this->verify($jwt);
    }

    private function sign(string $data): string
    {
        return hash_hmac('sha256', $data, $this->secret, true);
    }

    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    /**
     * @return string|false
     */
    private function base64UrlDecode(string $data)
    {
        $remainder = strlen($data) % 4;
        if ($remainder) {
            $data .= str_repeat('=', 4 - $remainder);
        }
        $decoded = base64_decode(strtr($data, '-_', '+/'), true);
        return $decoded;
    }
}

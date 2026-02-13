<?php

declare(strict_types=1);

namespace UCenter\Sdk;

use UCenter\Sdk\Api\CreditApi;
use UCenter\Sdk\Api\FriendApi;
use UCenter\Sdk\Api\PmApi;
use UCenter\Sdk\Api\TagApi;
use UCenter\Sdk\Api\UserApi;
use UCenter\Sdk\Backend\BackendInterface;
use UCenter\Sdk\Backend\DatabaseBackend;
use UCenter\Sdk\Binding\BindingStoreInterface;
use UCenter\Sdk\Binding\DatabaseBindingStore;
use UCenter\Sdk\Exception\UCenterException;
use UCenter\Sdk\Jwt\JwtToken;

/**
 * UCenter 客户端
 * - HTTP 模式：baseUrl + appId + secret，请求远程 UCenter API
 * - 直连数据库模式：仅需数据库配置，不暴露接口，直接操作 MySQL（fromDatabase）
 */
class UCenterClient
{
    private string $baseUrl;

    private string $appId;

    private string $secret;

    private ?string $token = null;

    private ?int $tokenExpiresAt = null;

    private int $timeout = 10;

    /** @var callable|null 可注入 HTTP 客户端 */
    private $httpClient;

    /** @var BackendInterface|null 直连数据库时使用，不走 HTTP */
    private ?BackendInterface $backend = null;

    /** @var BindingStoreInterface|null 混合模式：主逻辑 UCenter HTTP，绑定关系存本地 DB */
    private ?BindingStoreInterface $bindingStore = null;

    /** @var JwtToken|null 登录成功时用于签发用户 access_token（JWT） */
    private ?JwtToken $jwtToken = null;

    public function __construct(string $baseUrl, string $appId, string $secret)
    {
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->appId = $appId;
        $this->secret = $secret;
    }

    /**
     * 直连数据库，无需配置 baseUrl/appId/secret，不暴露 HTTP 接口
     * @param array|PDO $config 数据库配置 array(host, dbname, username, password, port?, charset?) 或 PDO 实例
     */
    public static function fromDatabase($config): self
    {
        $client = new self('', '', '');
        $client->backend = new DatabaseBackend($config);
        return $client;
    }

    /**
     * 混合模式：主逻辑走 UCenter（HTTP），绑定关系存本地 DB（仅 uc_bindings 表）
     * 需先执行 server/schema.sql 中的 uc_bindings 建表
     */
    public static function withBindingStore(string $baseUrl, string $appId, string $secret, $dbConfig): self
    {
        $client = new self($baseUrl, $appId, $secret);
        $client->bindingStore = new DatabaseBindingStore($dbConfig);
        return $client;
    }

    public function getBindingStore(): ?BindingStoreInterface
    {
        return $this->bindingStore;
    }

    /**
     * 设置 JWT 签发器，登录成功时将签发用户 access_token 并写入返回结果
     */
    public function setJwtToken(JwtToken $jwtToken): self
    {
        $this->jwtToken = $jwtToken;
        return $this;
    }

    public function getJwtToken(): ?JwtToken
    {
        return $this->jwtToken;
    }

    public function getBaseUrl(): string
    {
        return $this->baseUrl;
    }

    public function getAppId(): string
    {
        return $this->appId;
    }

    public function setTimeout(int $seconds): self
    {
        $this->timeout = $seconds;
        return $this;
    }

    /**
     * 设置自定义 HTTP 客户端
     * callable(string $url, array $headers, string $body): array 返回 ['body' => string, 'http_code' => int]
     */
    public function setHttpClient(callable $client): self
    {
        $this->httpClient = $client;
        return $this;
    }

    /**
     * 生成签名：与文档一致，对 hex 串做 base64
     * PHP: base64_encode(hash('sha256', $nonce.$t.$secret))
     */
    public function buildSign(string $nonce, int $t): string
    {
        $str = $nonce . (string) $t . $this->secret;
        $hashHex = hash('sha256', $str);
        return base64_encode($hashHex);
    }

    /**
     * 获取 Token（带缓存与续签）
     * /api/?/token 接口，续签时可在请求参数中携带旧 token
     */
    public function getToken(bool $forceRefresh = false): string
    {
        if (!$forceRefresh && $this->token !== null && $this->tokenExpiresAt !== null && time() < $this->tokenExpiresAt - 60) {
            return $this->token;
        }

        $params = [];
        if ($this->token !== null) {
            $params['token'] = $this->token;
        }

        $res = $this->request('token', $params, false);
        if (empty($res['token'])) {
            throw new UCenterException('获取 token 失败', 0, null, $res);
        }
        $this->token = (string) $res['token'];
        $this->tokenExpiresAt = isset($res['expires_in']) ? (int) $res['expires_in'] : (time() + 7200);
        return $this->token;
    }

    /**
     * 发起 API 请求
     * @param string $path 如 user/register, user/login, token
     * @param array $params 业务参数
     * @param bool $needToken 是否需要在 Header 中携带 token（/token 接口为 false）
     * @return array 接口返回的数组
     */
    public function request(string $path, array $params = [], bool $needToken = true): array
    {
        if ($this->backend !== null) {
            return $this->backend->request($path, $params);
        }

        $url = $this->baseUrl . '/api/?/' . $path;
        $nonce = bin2hex(random_bytes(16));
        $t = time();
        $headers = [
            'appid' => $this->appId,
            'nonce' => $nonce,
            't' => (string) $t,
            'sign' => $this->buildSign($nonce, $t),
        ];
        if ($needToken) {
            $headers['token'] = $this->getToken();
        }

        $body = http_build_query($params, '', '&', PHP_QUERY_RFC3986);

        $result = $this->sendRequest($url, $headers, $body);
        $rawBody = trim($result['body']);
        $decoded = json_decode($rawBody, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            // 空 body 视为成功、返回 0（如 getcredit 无积分时）
            if ($rawBody === '') {
                return ['ret' => 0];
            }
            // 部分接口返回纯数字（如 getcredit 返回积分值）
            if (preg_match('/^-?\d+$/', $rawBody)) {
                return ['ret' => (int) $rawBody];
            }
            throw new UCenterException('接口返回非 JSON: ' . substr($result['body'], 0, 200), $result['http_code'] ?? 0, null, null);
        }
        if (($result['http_code'] ?? 0) >= 400) {
            throw new UCenterException($decoded['message'] ?? '请求失败', $result['http_code'] ?? 0, null, $decoded);
        }
        return is_array($decoded) ? $decoded : [];
    }

    /**
     * 发起请求并返回原始响应体（不解析 JSON）
     * 用于 synLogin/synLogout 等返回 HTML 的接口
     */
    public function requestRaw(string $path, array $params = [], bool $needToken = true): string
    {
        if ($this->backend !== null) {
            throw new UCenterException('直连数据库模式不支持 requestRaw（如 synLogin/synLogout）', 0);
        }

        $url = $this->baseUrl . '/api/?/' . $path;
        $nonce = bin2hex(random_bytes(16));
        $t = time();
        $headers = [
            'appid' => $this->appId,
            'nonce' => $nonce,
            't' => (string) $t,
            'sign' => $this->buildSign($nonce, $t),
        ];
        if ($needToken) {
            $headers['token'] = $this->getToken();
        }
        $body = http_build_query($params, '', '&', PHP_QUERY_RFC3986);
        $result = $this->sendRequest($url, $headers, $body);
        return (string) $result['body'];
    }

    private function sendRequest(string $url, array $headerParams, string $body): array
    {
        if ($this->httpClient !== null) {
            $headers = [];
            foreach ($headerParams as $k => $v) {
                $headers[] = $k . ': ' . $v;
            }
            return ($this->httpClient)($url, $headers, $body);
        }

        $ch = curl_init($url);
        if ($ch === false) {
            throw new UCenterException('cURL 初始化失败');
        }
        $headerLines = [];
        foreach ($headerParams as $k => $v) {
            $headerLines[] = $k . ': ' . $v;
        }
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_HTTPHEADER => array_merge(['Content-Type: application/x-www-form-urlencoded'], $headerLines),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        $response = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);
        if ($response === false) {
            throw new UCenterException('请求失败: ' . $err);
        }
        return ['body' => (string) $response, 'http_code' => $httpCode];
    }

    public function user(): UserApi
    {
        return new UserApi($this);
    }

    public function pm(): PmApi
    {
        return new PmApi($this);
    }

    public function friend(): FriendApi
    {
        return new FriendApi($this);
    }

    public function credit(): CreditApi
    {
        return new CreditApi($this);
    }

    public function tag(): TagApi
    {
        return new TagApi($this);
    }
}

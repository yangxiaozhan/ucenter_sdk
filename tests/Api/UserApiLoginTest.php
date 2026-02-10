<?php

declare(strict_types=1);

namespace UCenter\Sdk\Tests\Api;

use PHPUnit\Framework\TestCase;
use UCenter\Sdk\Api\UserApi;
use UCenter\Sdk\Exception\UCenterException;
use UCenter\Sdk\Jwt\JwtToken;
use UCenter\Sdk\UCenterClient;

/**
 * 用户登录相关测试
 */
class UserApiLoginTest extends TestCase
{
    /**
     * 创建 mock 的 UCenterClient，可配置 user/login 返回值
     */
    private function createClientWithLoginResponse(array $loginResponse): UCenterClient
    {
        $client = $this->createMock(UCenterClient::class);
        $client->method('getBindingStore')->willReturn(null);
        $client->method('request')->willReturnCallback(function (string $path, array $params = [], bool $needToken = true) use ($loginResponse) {
            if ($path === 'token') {
                return ['token' => 'test-token', 'expires_in' => time() + 7200];
            }
            if ($path === 'user/login') {
                return $loginResponse;
            }
            return [];
        });
        return $client;
    }

    /** 用户名+密码登录成功：返回 status 为 uid */
    public function testLoginByUsernameAndPasswordSuccess(): void
    {
        $uid = 10001;
        $client = $this->createClientWithLoginResponse([
            'data' => [
                'uid' => (string) $uid,
                'status' => $uid,
                'username' => 'testuser',
                'email' => 'test@example.com',
            ],
        ]);
        $api = new UserApi($client);
        $result = $api->login('testuser', 'password123');
        $this->assertIsArray($result);
        $this->assertSame($uid, $result['status']);
        $this->assertSame('testuser', $result['username'] ?? null);
        $this->assertSame('test@example.com', $result['email'] ?? null);
    }

    /** 已设置 JwtToken 时，登录成功返回 access_token（JWT） */
    public function testLoginSuccessReturnsAccessTokenWhenJwtSet(): void
    {
        $uid = 10001;
        $loginResponse = [
            'data' => [
                'uid' => (string) $uid,
                'status' => $uid,
                'username' => 'testuser',
                'email' => 'test@example.com',
            ],
        ];
        $jwt = new JwtToken('test-secret-for-login', 7200);
        $client = new UCenterClient('https://test.example.com', 'app1', 'secret1');
        $client->setJwtToken($jwt);
        $client->setHttpClient(function (string $url, array $headers, string $body) use ($loginResponse) {
            if (strpos($url, 'token') !== false) {
                return ['body' => json_encode(['token' => 't', 'expires_in' => time() + 7200]), 'http_code' => 200];
            }
            if (strpos($url, 'user/login') !== false) {
                return ['body' => json_encode($loginResponse), 'http_code' => 200];
            }
            return ['body' => '{}', 'http_code' => 200];
        });
        $api = new UserApi($client);
        $result = $api->login('testuser', 'password123');
        $this->assertArrayHasKey('access_token', $result);
        $token = $result['access_token'];
        $this->assertNotEmpty($token);
        $parts = explode('.', $token);
        $this->assertCount(3, $parts, 'access_token 应为 JWT 三段式');
        $payloadJson = base64_decode(strtr($parts[1], '-_', '+/') . str_repeat('=', (4 - strlen($parts[1]) % 4) % 4));
        $payload = json_decode($payloadJson, true);
        $this->assertSame((string) $uid, $payload['sub'] ?? null);
        $this->assertSame('testuser', $payload['username'] ?? null);
    }

    /** 接口直接返回 uid 时，login 应将其作为 status */
    public function testLoginSuccessWhenResponseHasUidWithoutStatus(): void
    {
        $uid = 10002;
        $client = $this->createClientWithLoginResponse([
            'data' => [
                'uid' => (string) $uid,
                'username' => 'u2',
                'email' => 'u2@example.com',
            ],
        ]);
        $api = new UserApi($client);
        $result = $api->login('u2', 'pass');
        $this->assertSame($uid, $result['status']);
    }

    /** 用户不存在：status = -1 */
    public function testLoginUserNotFound(): void
    {
        $client = $this->createClientWithLoginResponse([
            'data' => ['status' => -1],
        ]);
        $api = new UserApi($client);
        $result = $api->login('nonexistent', 'any');
        $this->assertSame(-1, $result['status']);
    }

    /** 密码错误：status = -2 */
    public function testLoginWrongPassword(): void
    {
        $client = $this->createClientWithLoginResponse([
            'data' => ['status' => -2],
        ]);
        $api = new UserApi($client);
        $result = $api->login('testuser', 'wrongpassword');
        $this->assertSame(-2, $result['status']);
    }

    /** 安全提问错误：status = -3 */
    public function testLoginSecurityQuestionWrong(): void
    {
        $client = $this->createClientWithLoginResponse([
            'data' => ['status' => -3],
        ]);
        $api = new UserApi($client);
        $result = $api->login('testuser', 'pass', 0, true, 1, 'wronganswer');
        $this->assertSame(-3, $result['status']);
    }

    /** 按 uid 登录时 isuid 参数传递 */
    public function testLoginByUidPassesIsuid(): void
    {
        $requestParams = null;
        $client = $this->createMock(UCenterClient::class);
        $client->method('getBindingStore')->willReturn(null);
        $client->method('request')->willReturnCallback(function (string $path, array $params = []) use (&$requestParams) {
            if ($path === 'token') {
                return ['token' => 't', 'expires_in' => time() + 7200];
            }
            if ($path === 'user/login') {
                $requestParams = $params;
                return ['data' => ['status' => 100, 'username' => 'u', 'email' => 'u@e.com']];
            }
            return [];
        });
        $api = new UserApi($client);
        $api->login('100', 'pass', 1);
        $this->assertNotNull($requestParams);
        $this->assertSame(1, $requestParams['isuid']);
        $this->assertSame('100', $requestParams['username']);
    }

    /**
     * generateCredentials：同一 type+identifier 生成确定性的 username/email/password
     */
    public function testGenerateCredentialsDeterministic(): void
    {
        $client = $this->createMock(UCenterClient::class);
        $api = new UserApi($client);
        $type = UserApi::LOGIN_TYPE_PHONE;
        $identifier = '13800138000';
        $one = $api->generateCredentials($type, $identifier);
        $two = $api->generateCredentials($type, $identifier);
        $this->assertSame($one['username'], $two['username']);
        $this->assertSame($one['email'], $two['email']);
        $this->assertSame($one['password'], $two['password']);
    }

    /** generateCredentials：username 为 32 位 md5，邮箱带指定域名 */
    public function testGenerateCredentialsFormat(): void
    {
        $client = $this->createMock(UCenterClient::class);
        $api = new UserApi($client);
        $cred = $api->generateCredentials(UserApi::LOGIN_TYPE_PHONE, '13800138000', 'mydomain.com');
        $this->assertMatchesRegularExpression('/^[a-f0-9]{32}$/', $cred['username']);
        $this->assertStringEndsWith('@mydomain.com', $cred['email']);
        $this->assertSame(16, strlen($cred['password']));
    }

    /** generateCredentials：email_domain 为空时使用默认域名 */
    public function testGenerateCredentialsDefaultDomain(): void
    {
        $client = $this->createMock(UCenterClient::class);
        $api = new UserApi($client);
        $cred = $api->generateCredentials(UserApi::LOGIN_TYPE_PHONE, '13800138000', null);
        $this->assertStringContainsString('jiuzhoufeiyi', $cred['email']);
    }

    /** 第三方类型+标识登录：已存在用户时直接返回用户信息（无 BindingStore） */
    public function testLoginByPhoneIdentifierWhenUserExists(): void
    {
        $uid = 10003;
        $client = $this->createClientWithLoginResponse([
            'data' => [
                'uid' => (string) $uid,
                'status' => $uid,
                'username' => 'phone_13800138000',
                'email' => 'u@jiuzhoufeiyi',
            ],
        ]);
        $api = new UserApi($client);
        $result = $api->login(UserApi::LOGIN_TYPE_PHONE, '13800138000');
        $this->assertSame($uid, $result['status']);
        $this->assertArrayHasKey('username', $result);
    }

    /** 第三方类型+标识登录：未注册时自动注册再登录（mock register + login 成功） */
    public function testLoginByPhoneIdentifierAutoRegister(): void
    {
        $loginCalls = 0;
        $client = $this->createMock(UCenterClient::class);
        $client->method('getBindingStore')->willReturn(null);
        $client->method('request')->willReturnCallback(function (string $path, array $params = []) use (&$loginCalls) {
            if ($path === 'token') {
                return ['token' => 't', 'expires_in' => time() + 7200];
            }
            if ($path === 'user/login') {
                $loginCalls++;
                // 第一次登录：用户不存在 -1；第二次（注册后）：成功
                if ($loginCalls === 1) {
                    return ['data' => ['status' => -1]];
                }
                return ['data' => ['status' => 10004, 'username' => 'u', 'email' => 'u@e.com']];
            }
            if ($path === 'user/register') {
                return ['ret' => 10004];
            }
            if ($path === 'user/update_profile') {
                return ['ret' => 0];
            }
            return [];
        });
        $api = new UserApi($client);
        $result = $api->login(UserApi::LOGIN_TYPE_PHONE, '13900139000');
        $this->assertSame(10004, $result['status']);
        $this->assertSame(2, $loginCalls);
    }

    /** 第三方自动注册失败（register 返回负数）应抛 UCenterException */
    public function testLoginByTypeIdentifierRegisterFailsThrows(): void
    {
        $client = $this->createMock(UCenterClient::class);
        $client->method('getBindingStore')->willReturn(null);
        $client->method('request')->willReturnCallback(function (string $path) {
            if ($path === 'token') {
                return ['token' => 't', 'expires_in' => time() + 7200];
            }
            if ($path === 'user/login') {
                return ['data' => ['status' => -1]];
            }
            if ($path === 'user/register') {
                return ['ret' => -3]; // 用户名已存在等
            }
            return [];
        });
        $api = new UserApi($client);
        $this->expectException(UCenterException::class);
        $this->expectExceptionMessage('第三方自动注册失败');
        $api->login(UserApi::LOGIN_TYPE_PHONE, '13900139001');
    }
}

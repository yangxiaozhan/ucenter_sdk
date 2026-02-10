<?php

declare(strict_types=1);

namespace UCenter\Sdk\Backend;

use PDO;

/**
 * 直连 MySQL 后端，不暴露 HTTP 接口；仅支持 user/register、user/login、user/get_user、user/edit
 * 与 server/schema.sql 表结构一致
 */
class DatabaseBackend implements BackendInterface
{
    private PDO $pdo;

    private const PREFIX_TO_FIELD = [
        'phone_' => 'phone',
        'wechat_' => 'wechat_unionid',
        'weibo_' => 'weibo_openid',
        'qq_' => 'qq_union_id',
    ];

    public static function generatedPassword(string $type, string $identifier): string
    {
        return substr(bin2hex(hash('sha256', $type . '|' . $identifier, true)), 0, 16);
    }

    public static function parseIdentifierUsername(string $username): ?array
    {
        foreach (self::PREFIX_TO_FIELD as $prefix => $field) {
            if (strpos($username, $prefix) === 0) {
                $identifier = substr($username, strlen($prefix));
                $type = $field === 'qq_union_id' ? 'qq_unionid' : $field;
                return ['type' => $type, 'field' => $field, 'identifier' => $identifier];
            }
        }
        return null;
    }

    public function __construct($pdoOrConfig)
    {
        if ($pdoOrConfig instanceof PDO) {
            $this->pdo = $pdoOrConfig;
        } else {
            $c = $pdoOrConfig;
            $dsn = sprintf(
                'mysql:host=%s;port=%s;dbname=%s;charset=%s',
                $c['host'] ?? '127.0.0.1',
                $c['port'] ?? 3306,
                $c['dbname'] ?? $c['database'] ?? '',
                $c['charset'] ?? 'utf8mb4'
            );
            $this->pdo = new PDO($dsn, $c['username'] ?? $c['user'] ?? '', $c['password'] ?? '', [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
        }
    }

    public function request(string $path, array $params = []): array
    {
        switch ($path) {
            case 'user/register':
                return ['ret' => $this->register($params)];
            case 'user/login':
                return $this->login($params);
            case 'user/get_user':
                return ['data' => $this->getUser($params)];
            case 'user/edit':
                return ['ret' => $this->edit($params)];
            default:
                throw new \UCenter\Sdk\Exception\UCenterException('直连数据库模式仅支持 user/register、user/login、user/get_user、user/edit', 0);
        }
    }

    private function register(array $p): int
    {
        $username = trim((string) ($p['username'] ?? ''));
        $email = trim((string) ($p['email'] ?? ''));
        if ($username === '' || strlen($username) > 50) {
            return -1;
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return -4;
        }
        $hash = password_hash((string) ($p['password'] ?? ''), PASSWORD_DEFAULT);
        $regip = $p['regip'] ?? '';
        try {
            $stmt = $this->pdo->prepare('INSERT INTO uc_users (username, password, email, regip) VALUES (?, ?, ?, ?)');
            $stmt->execute([$username, $hash, $email, $regip ?: null]);
            return (int) $this->pdo->lastInsertId();
        } catch (\PDOException $e) {
            if ($e->getCode() == 23000) {
                if (strpos($e->getMessage(), 'uk_username') !== false) return -3;
                if (strpos($e->getMessage(), 'uk_email') !== false) return -6;
            }
            throw $e;
        }
    }

    private function login(array $p): array
    {
        $username = (string) ($p['username'] ?? '');
        $password = (string) ($p['password'] ?? '');
        $isuid = (int) ($p['isuid'] ?? 0);
        $user = null;
        $parsed = self::parseIdentifierUsername($username);

        if ($parsed !== null) {
            $field = $parsed['field'];
            $identifier = $parsed['identifier'];
            $type = $parsed['type'];
            $stmt = $this->pdo->prepare("SELECT uid, username, password, email FROM uc_users WHERE {$field} = ? LIMIT 1");
            $stmt->execute([$identifier]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row !== false) {
                if (password_verify($password, $row['password']) || $password === self::generatedPassword($type, $identifier)) {
                    $user = $row;
                }
            }
        }

        if ($user === null) {
            if ($isuid === 1) {
                $stmt = $this->pdo->prepare('SELECT uid, username, password, email FROM uc_users WHERE uid = ? LIMIT 1');
                $stmt->execute([$username]);
            } elseif ($isuid === 2) {
                $stmt = $this->pdo->prepare('SELECT uid, username, password, email FROM uc_users WHERE email = ? LIMIT 1');
                $stmt->execute([$username]);
            } else {
                $stmt = $this->pdo->prepare('SELECT uid, username, password, email FROM uc_users WHERE username = ? LIMIT 1');
                $stmt->execute([$username]);
            }
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row !== false && password_verify($password, $row['password'])) {
                $user = $row;
            }
        }

        if ($user === null) {
            $stmt = $this->pdo->prepare('SELECT 1 FROM uc_users WHERE username = ? LIMIT 1');
            $stmt->execute([$username]);
            return ['status' => $stmt->fetch() ? -2 : -1, 'username' => '', 'email' => ''];
        }

        return [
            'status' => (int) $user['uid'],
            'uid' => (int) $user['uid'],
            'username' => $user['username'],
            'email' => $user['email'],
        ];
    }

    private function getUser(array $p): array
    {
        $username = (string) ($p['username'] ?? '');
        $isuid = !empty($p['isuid']);
        if ($isuid) {
            $stmt = $this->pdo->prepare('SELECT * FROM uc_users WHERE uid = ? LIMIT 1');
            $stmt->execute([$username]);
        } else {
            $stmt = $this->pdo->prepare('SELECT * FROM uc_users WHERE username = ? LIMIT 1');
            $stmt->execute([$username]);
        }
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row === false) {
            return ['uid' => 0, 'username' => '', 'email' => ''];
        }
        unset($row['password']);
        return $row;
    }

    private function edit(array $p): int
    {
        $username = (string) ($p['username'] ?? '');
        $isuid = !empty($p['isuid']);
        $user = $this->getUser(['username' => $username, 'isuid' => $isuid]);
        if (empty($user['uid'])) {
            return -1;
        }
        $uid = (int) $user['uid'];
        $updates = [];
        $params = [];

        if (!empty($p['ignoreoldpw']) || ($p['ignoreoldpw'] ?? '') === '1') {
            if (isset($p['newpw']) && $p['newpw'] !== '') {
                $updates[] = 'password = ?';
                $params[] = password_hash($p['newpw'], PASSWORD_DEFAULT);
            }
            if (isset($p['email']) && $p['email'] !== '') {
                $updates[] = 'email = ?';
                $params[] = $p['email'];
            }
        } else {
            $stmt = $this->pdo->prepare('SELECT password FROM uc_users WHERE uid = ?');
            $stmt->execute([$uid]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row || !password_verify((string) ($p['oldpw'] ?? ''), $row['password'])) {
                return -2;
            }
            if (isset($p['newpw']) && $p['newpw'] !== '') {
                $updates[] = 'password = ?';
                $params[] = password_hash($p['newpw'], PASSWORD_DEFAULT);
            }
            if (isset($p['email']) && $p['email'] !== '') {
                $updates[] = 'email = ?';
                $params[] = $p['email'];
            }
        }

        $allowed = ['phone', 'wechat_openid', 'wechat_unionid', 'qq_union_id', 'weibo_openid', 'nickname', 'avatar', 'douyin_openid', 'is_member'];
        foreach ($allowed as $key) {
            if (array_key_exists($key, $p)) {
                $updates[] = "`{$key}` = ?";
                $params[] = $p[$key] === '' ? null : $p[$key];
            }
        }

        if (empty($updates)) {
            return 0;
        }
        $params[] = $uid;
        $this->pdo->prepare('UPDATE uc_users SET ' . implode(', ', $updates) . ' WHERE uid = ?')->execute($params);
        return 1;
    }
}

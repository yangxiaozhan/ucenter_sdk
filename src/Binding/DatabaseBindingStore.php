<?php

declare(strict_types=1);

namespace UCenter\Sdk\Binding;

use PDO;

/**
 * 绑定关系存本地 MySQL 表 uc_bindings，主逻辑仍走 UCenter HTTP
 */
class DatabaseBindingStore implements BindingStoreInterface
{
    private PDO $pdo;

    /** 类型 -> 存储用字段名（与 LOGIN_TYPE_FIELD 一致） */
    private const TYPE_FIELD = [
        'phone' => 'phone',
        'wechat_unionid' => 'wechat_unionid',
        'weibo_openid' => 'weibo_openid',
        'qq_unionid' => 'qq_union_id',
    ];

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

    public function add(int $uid, string $type, string $identifier): void
    {
        $field = self::TYPE_FIELD[$type] ?? $type;
        $this->pdo->prepare('DELETE FROM uc_bindings WHERE type = ? AND identifier = ?')->execute([$field, $identifier]);
        $stmt = $this->pdo->prepare('UPDATE uc_bindings SET identifier = ?, deleted_at = NULL WHERE uid = ? AND type = ?');
        $stmt->execute([$identifier, $uid, $field]);
        if ($stmt->rowCount() === 0) {
            $this->pdo->prepare('INSERT INTO uc_bindings (uid, type, identifier) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE identifier = VALUES(identifier), deleted_at = NULL')->execute([$uid, $field, $identifier]);
        }
    }

    /**
     * 解绑：软删除，不执行 DELETE，仅将 deleted_at 置为当前时间
     */
    public function remove(int $uid, string $type): void
    {
        $field = self::TYPE_FIELD[$type] ?? $type;
        $this->pdo->prepare('UPDATE uc_bindings SET deleted_at = NOW() WHERE uid = ? AND type = ? AND deleted_at IS NULL')->execute([$uid, $field]);
    }

    public function getByUid(int $uid): array
    {
        $stmt = $this->pdo->prepare('SELECT type, identifier FROM uc_bindings WHERE uid = ? AND deleted_at IS NULL');
        $stmt->execute([$uid]);
        $out = ['phone' => '', 'wechat_unionid' => '', 'weibo_openid' => '', 'qq_union_id' => ''];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $out[$row['type']] = $row['identifier'];
        }
        return $out;
    }

    public function findUid(string $type, string $identifier): ?int
    {
        $field = self::TYPE_FIELD[$type] ?? $type;
        $stmt = $this->pdo->prepare('SELECT uid FROM uc_bindings WHERE type = ? AND identifier = ? AND deleted_at IS NULL LIMIT 1');
        $stmt->execute([$field, $identifier]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? (int) $row['uid'] : null;
    }
}

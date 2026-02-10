<?php

declare(strict_types=1);

namespace UCenter\Sdk\Binding;

/**
 * 绑定关系存储（仅存 类型+标识 -> uid），主逻辑仍走 UCenter
 */
interface BindingStoreInterface
{
    /** 添加绑定：uid + type + identifier */
    public function add(int $uid, string $type, string $identifier): void;

    /** 解除某类型的绑定 */
    public function remove(int $uid, string $type): void;

    /** 获取该 uid 下所有绑定，返回 [ type => identifier, ... ] */
    public function getByUid(int $uid): array;

    /** 根据类型+标识查 uid，未找到返回 null */
    public function findUid(string $type, string $identifier): ?int;
}

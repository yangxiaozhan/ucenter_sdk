<?php

declare(strict_types=1);

namespace UCenter\Sdk\Api;

/**
 * 好友接口
 * @see document/html/friend.htm
 */
class FriendApi extends BaseApi
{
    /**
     * 添加好友
     */
    public function add(int $uid, int $friendid, string $comment = ''): bool
    {
        $ret = $this->client->request('friend/add', [
            'uid' => $uid,
            'friendid' => $friendid,
            'comment' => $comment,
        ]);
        return (int) ($ret['ret'] ?? 0) === 1;
    }

    /**
     * 删除好友
     */
    public function delete(int $uid, int $friendid): bool
    {
        $ret = $this->client->request('friend/delete', [
            'uid' => $uid,
            'friendid' => $friendid,
        ]);
        return (int) ($ret['ret'] ?? 0) === 1;
    }

    /**
     * 调用其它好友接口（具体见 document/html/friend.htm）
     */
    public function call(string $action, array $params = []): array
    {
        return $this->client->request('friend/' . $action, $params);
    }
}

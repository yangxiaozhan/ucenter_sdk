<?php

declare(strict_types=1);

namespace UCenter\Sdk\Api;

/**
 * 短消息接口
 * @see document/html/pm.htm
 */
class PmApi extends BaseApi
{
    /**
     * 检查新短消息
     * @param int $uid 用户 ID
     * @param int $more 0=只返回未读数，1=未读数+最后时间，2=更多，3=含最后消息内容等
     */
    public function checkNewPm(int $uid, int $more = 0): array
    {
        return $this->client->request('pm/check_newpm', ['uid' => $uid, 'more' => $more]);
    }

    /**
     * 发送短消息
     */
    public function send(int $fromUid, string $toUsername, string $subject, string $message, bool $replyPmId = false, bool $isUsername = true): int
    {
        $params = [
            'fromuid' => $fromUid,
            'msgto' => $toUsername,
            'subject' => $subject,
            'message' => $message,
            'replypmid' => $replyPmId ? 1 : 0,
            'isusername' => $isUsername ? 1 : 0,
        ];
        $ret = $this->client->request('pm/send', $params);
        return (int) ($ret['msgid'] ?? $ret['ret'] ?? 0);
    }

    /**
     * 删除短消息
     * @param int $uid 用户 ID
     * @param int|int[] $pmIds 消息 ID 或 ID 数组
     */
    public function delete(int $uid, $pmIds): int
    {
        $params = [
            'uid' => $uid,
            'pmids' => is_array($pmIds) ? $pmIds : [$pmIds],
        ];
        $ret = $this->client->request('pm/delete', $params);
        return (int) ($ret['ret'] ?? 0);
    }

    /**
     * 调用其它短消息接口（具体参数见 document/html/pm.htm）
     */
    public function call(string $action, array $params = []): array
    {
        return $this->client->request('pm/' . $action, $params);
    }
}

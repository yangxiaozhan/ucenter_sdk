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
     * 以系统管理员（uid=1）身份向指定用户发送站内信
     * @param int    $toUid   收件人用户 ID
     * @param string $subject 标题
     * @param string $message 内容
     * @param int    $fromUid 发件人 uid，默认 1（管理员）
     * @return int 成功返回消息 ID，失败返回 0 或负数（见文档）
     */
    public function sendFromSystem(int $toUid, string $subject, string $message, int $fromUid = 1): int
    {
        return $this->send($fromUid, (string) $toUid, $subject, $message, false, false);
    }

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
     * 发送短消息（与 document 一致：pm/sendpm，replypid、instantly）
     * 常见错误码：-1 会话上限，-2 发送间隔，-3 非好友，-4 注册天数限制，-6 忽略列表，-8 不能给自己发，-9 收件人为空；服务端自定义如 -114 多为「非好友不能发」等
     */
    public function send(int $fromUid, string $toUsername, string $subject, string $message, bool $replyPmId = false, bool $isUsername = true): int
    {
        $params = [
            'fromuid' => $fromUid,
            'msgto' => $toUsername,
            'subject' => $subject,
            'message' => $message,
            'instantly' => 1,
            'replypid' => $replyPmId ? 1 : 0,
            'isusername' => $isUsername ? 1 : 0,
        ];
        $ret = $this->client->request('pm/sendpm', $params);
        return (int) ($ret['msgid'] ?? $ret['ret'] ?? 0);
    }

    /**
     * 获取用户短消息列表（收件箱/发件箱/新件箱）
     * @param int    $uid      用户 ID
     * @param int    $page     页码，从 1 开始
     * @param int    $pagesize 每页条数
     * @param string $folder   newbox=新件箱，inbox=收件箱，outbox=发件箱
     * @param string $filter   过滤，默认 newpm（未读）
     * @param int    $msglen   截取内容长度，0 不截取
     * @return array{count: int, data: array}
     */
    public function getList(int $uid, int $page = 1, int $pagesize = 10, string $folder = 'inbox', string $filter = 'newpm', int $msglen = 0): array
    {
        $ret = $this->client->request('pm/ls', [
            'uid' => $uid,
            'page' => $page,
            'pagesize' => $pagesize,
            'folder' => $folder,
            'filter' => $filter,
            'msglen' => $msglen,
        ]);
        $data = $ret['data'] ?? [];
        $count = (int) ($ret['count'] ?? $ret['total'] ?? $ret['totalnum'] ?? $ret['num'] ?? 0);
        if ($count <= 0 && !empty($data)) {
            $count = count($data);
        }
        return [
            'count' => $count,
            'data' => $data,
        ];
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

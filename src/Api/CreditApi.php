<?php

declare(strict_types=1);

namespace UCenter\Sdk\Api;

/**
 * 积分接口
 * @see document/html/credit.htm
 */
class CreditApi extends BaseApi
{
    /**
     * 积分兑换请求
     */
    public function request(int $uid, int $from, int $to, int $toappid, int $amount): bool
    {
        $ret = $this->client->request('credit/request', [
            'uid' => $uid,
            'from' => $from,
            'to' => $to,
            'toappid' => $toappid,
            'amount' => $amount,
        ]);
        return (bool) ($ret['ret'] ?? $ret);
    }
}

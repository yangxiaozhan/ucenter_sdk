<?php

declare(strict_types=1);

namespace UCenter\Sdk\Wechat;

use UCenter\Sdk\Exception\UCenterException;

/**
 * 微信公众号模板消息发送
 * 需服务号 + 已审核通过的模板。获取 access_token 后调用模板消息接口。
 *
 * @see https://developers.weixin.qq.com/doc/offiaccount/Message_Management/Template_Message_Interface.html
 */
class WechatTemplateMessage
{
    private string $accountId;

    private string $appId;

    private string $appSecret;

    private ?string $accessToken = null;

    private ?int $accessTokenExpiresAt = 0;

    private int $timeout = 10;

    private const TOKEN_URL = 'https://api.weixin.qq.com/cgi-bin/token';
    private const SEND_URL = 'https://api.weixin.qq.com/cgi-bin/message/template/send';
    private const DEFAULT_COLOR = '#173177';

    /**
     * @param string $accountId  公众号 id（用于标识，如多公众号场景）
     * @param string $appId     公众号 appid
     * @param string $appSecret 公众号密钥（key / AppSecret）
     */
    public function __construct(string $accountId, string $appId, string $appSecret)
    {
        $this->accountId = $accountId;
        $this->appId = $appId;
        $this->appSecret = $appSecret;
    }

    public function getAccountId(): string
    {
        return $this->accountId;
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
     * 获取 access_token（带缓存，提前 5 分钟刷新）
     */
    public function getAccessToken(): string
    {
        if ($this->accessToken !== null && time() < $this->accessTokenExpiresAt - 300) {
            return $this->accessToken;
        }

        $url = self::TOKEN_URL . '?grant_type=client_credential&appid=' . urlencode($this->appId) . '&secret=' . urlencode($this->appSecret);
        $body = $this->httpGet($url);
        $res = json_decode($body, true);
        if (empty($res['access_token'])) {
            $err = $res['errmsg'] ?? $res['errcode'] ?? $body;
            throw new UCenterException('获取微信 access_token 失败: ' . (is_string($err) ? $err : json_encode($err)));
        }
        $this->accessToken = (string) $res['access_token'];
        $this->accessTokenExpiresAt = time() + (int) ($res['expires_in'] ?? 7200);
        return $this->accessToken;
    }

    /**
     * 发送模板消息
     *
     * @param string $openId     接收者 openid（服务号下用户 openid）
     * @param string $templateId 模板 id（公众平台已审核通过的模板）
     * @param array  $data       模板参数。键为模板占位符名（如 first、keyword1、remark），值为字符串或 ["value" => "xxx", "color" => "#173177"]
     * @param string $url        可选，点击模板消息跳转的 URL
     * @param array  $miniprogram 可选，跳转小程序 ["appid"=>"","pagepath"=>""]
     * @return array 微信返回，含 errcode、errmsg、msgid
     */
    public function send(string $openId, string $templateId, array $data, string $url = '', array $miniprogram = []): array
    {
        $token = $this->getAccessToken();
        $payload = [
            'touser' => $openId,
            'template_id' => $templateId,
            'data' => $this->normalizeData($data),
        ];
        if ($url !== '') {
            $payload['url'] = $url;
        }
        if (!empty($miniprogram['appid']) && isset($miniprogram['pagepath'])) {
            $payload['miniprogram'] = [
                'appid' => $miniprogram['appid'],
                'pagepath' => $miniprogram['pagepath'],
            ];
        }

        $apiUrl = self::SEND_URL . '?access_token=' . urlencode($token);
        $body = $this->httpPost($apiUrl, $payload);
        $res = json_decode($body, true);
        if ($res === null) {
            throw new UCenterException('微信模板消息接口返回异常: ' . substr($body, 0, 200));
        }
        return $res;
    }

    /**
     * 将参数转为微信 data 格式：每个键对应 {"value":"xxx","color":"#173177"}
     */
    private function normalizeData(array $data): array
    {
        $out = [];
        foreach ($data as $key => $val) {
            if (is_array($val) && array_key_exists('value', $val)) {
                $out[$key] = [
                    'value' => (string) $val['value'],
                    'color' => (string) ($val['color'] ?? self::DEFAULT_COLOR),
                ];
            } else {
                $out[$key] = [
                    'value' => (string) $val,
                    'color' => self::DEFAULT_COLOR,
                ];
            }
        }
        return $out;
    }

    private function httpGet(string $url): string
    {
        $ch = curl_init($url);
        if ($ch === false) {
            throw new UCenterException('cURL 初始化失败');
        }
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        $body = curl_exec($ch);
        $err = curl_error($ch);
        curl_close($ch);
        if ($body === false) {
            throw new UCenterException('请求失败: ' . $err);
        }
        return (string) $body;
    }

    private function httpPost(string $url, array $payload): string
    {
        $ch = curl_init($url);
        if ($ch === false) {
            throw new UCenterException('cURL 初始化失败');
        }
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $json,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        $body = curl_exec($ch);
        $err = curl_error($ch);
        curl_close($ch);
        if ($body === false) {
            throw new UCenterException('请求失败: ' . $err);
        }
        return (string) $body;
    }
}

<?php

declare(strict_types=1);

namespace UCenter\Sdk\Sms;

use UCenter\Sdk\Exception\UCenterException;

/**
 * 腾讯云短信发送封装
 * 依赖腾讯云短信 API（SendSms），需安装：composer require tencentcloud/sms（仅短信库，无需全产品 SDK）
 *
 * @see https://cloud.tencent.com/document/product/382/55981
 */
class TencentCloudSms
{
    private string $secretId;

    private string $secretKey;

    private string $smsSdkAppId;

    private string $signName;

    private string $region;

    private const DEFAULT_REGION = 'ap-guangzhou';

    public function __construct(
        string $secretId,
        string $secretKey,
        string $smsSdkAppId,
        string $signName = '',
        string $region = self::DEFAULT_REGION
    ) {
        $this->secretId = $secretId;
        $this->secretKey = $secretKey;
        $this->smsSdkAppId = $smsSdkAppId;
        $this->signName = $signName;
        $this->region = $region;
    }

    /**
     * 发送短信
     *
     * @param string[] $phoneNumbers 手机号列表，国内支持 11 位或 +86 前缀，会自动规范为 E.164
     * @param string $templateId 已审核通过的模板 ID
     * @param string[] $templateParamSet 模板参数，与模板变量个数一致
     * @param string $sessionContext 用户 session，可选，原样返回
     * @return array SendStatusSet，每项含 Code、Message、PhoneNumber、SerialNo 等
     * @throws UCenterException 未安装腾讯云 SDK 或调用失败
     */
    public function send(
        array $phoneNumbers,
        string $templateId,
        array $templateParamSet = [],
        string $sessionContext = ''
    ): array {
        $this->ensureSdkLoaded();

        $numbers = array_map([$this, 'normalizePhoneNumber'], $phoneNumbers);
        $numbers = array_values(array_unique($numbers));

        if (empty($numbers)) {
            throw new UCenterException('手机号列表不能为空');
        }

        if (count($numbers) > 200) {
            throw new UCenterException('单次请求最多 200 个手机号');
        }

        $credential = new \TencentCloud\Common\Credential($this->secretId, $this->secretKey);
        $client = new \TencentCloud\Sms\V20210111\SmsClient($credential, $this->region);

        $req = new \TencentCloud\Sms\V20210111\Models\SendSmsRequest();
        $req->PhoneNumberSet = $numbers;
        $req->SmsSdkAppId = $this->smsSdkAppId;
        $req->TemplateId = $templateId;
        $req->SignName = $this->signName;
        $req->TemplateParamSet = $templateParamSet;
        if ($sessionContext !== '') {
            $req->SessionContext = $sessionContext;
        }

        try {
            $resp = $client->SendSms($req);
        } catch (\Throwable $e) {
            throw new UCenterException('腾讯云短信发送失败: ' . $e->getMessage(), (int) $e->getCode(), $e);
        }

        $set = $resp->SendStatusSet ?? [];
        $list = [];
        foreach ($set as $item) {
            $list[] = [
                'Code' => $item->Code ?? '',
                'Message' => $item->Message ?? '',
                'PhoneNumber' => $item->PhoneNumber ?? '',
                'SerialNo' => $item->SerialNo ?? '',
                'Fee' => $item->Fee ?? 0,
                'SessionContext' => $item->SessionContext ?? '',
                'IsoCode' => $item->IsoCode ?? '',
            ];
        }
        return $list;
    }

    /**
     * 发送单条短信（便捷方法）
     *
     * @param string $phoneNumber 手机号
     * @param string $templateId 模板 ID
     * @param string[] $templateParamSet 模板参数
     * @return array 单条发送结果，含 Code、Message、SerialNo 等
     */
    public function sendOne(string $phoneNumber, string $templateId, array $templateParamSet = []): array
    {
        $set = $this->send([$phoneNumber], $templateId, $templateParamSet);
        return $set[0] ?? ['Code' => 'Unknown', 'Message' => '无返回'];
    }

    /**
     * 规范手机号为 E.164（国内 11 位补 +86）
     */
    public function normalizePhoneNumber(string $phone): string
    {
        $phone = trim($phone);
        if ($phone === '') {
            return '';
        }
        if (strpos($phone, '+') === 0) {
            return $phone;
        }
        $digits = preg_replace('/\D/', '', $phone);
        if (strlen($digits) === 11 && in_array(substr($digits, 0, 1), ['1'], true)) {
            return '+86' . $digits;
        }
        if (strlen($digits) === 13 && substr($digits, 0, 2) === '86') {
            return '+' . $digits;
        }
        return $phone;
    }

    private function ensureSdkLoaded(): void
    {
        if (!class_exists(\TencentCloud\Sms\V20210111\SmsClient::class)) {
            throw new UCenterException(
                '使用短信功能请先安装腾讯云短信库：composer require tencentcloud/sms'
            );
        }
    }
}

<?php

/**
 * UCenter SDK 演示配置示例
 * 复制为 config.php 并填写真实配置后运行: php demo/demo.php
 */
return [
    'base_url' => getenv('UCENTER_BASE_URL') ?: 'https://uc.example.com',
    'app_id'   => getenv('UCENTER_APP_ID') ?: 'your_appid',
    'secret'   => getenv('UCENTER_SECRET') ?: 'your_secret',

    // 短信（腾讯云，可选）：需安装 composer require tencentcloud/sms
    'sms' => [
        'secret_id'       => getenv('TENCENT_SMS_SECRET_ID') ?: '',
        'secret_key'      => getenv('TENCENT_SMS_SECRET_KEY') ?: '',
        'sms_sdk_app_id'  => getenv('TENCENT_SMS_APP_ID') ?: '',
        'sign_name'       => getenv('TENCENT_SMS_SIGN_NAME') ?: '',
        'region'          => getenv('TENCENT_SMS_REGION') ?: 'ap-guangzhou',
        'template_id'     => getenv('TENCENT_SMS_TEMPLATE_ID') ?: '',  // 可选，demo 测试发送时填写
        'test_phone'      => getenv('TENCENT_SMS_TEST_PHONE') ?: '',   // 可选，demo 测试发送目标号
        'template_param_set' => [],  // 可选，模板参数，如 ['123456']
    ],

    // 微信模板消息（可选）：公众号 id、appid、key、模板 id、openid、参数
    'wechat' => [
        'account_id'  => getenv('WECHAT_ACCOUNT_ID') ?: '',
        'app_id'      => getenv('WECHAT_APP_ID') ?: '',
        'app_secret'  => getenv('WECHAT_APP_SECRET') ?: '',
        'template_id' => getenv('WECHAT_TEMPLATE_ID') ?: '',  // 可选，demo 测试时填写
        'test_openid' => getenv('WECHAT_TEST_OPENID') ?: '',  // 可选，demo 测试接收者 openid
    ],
];

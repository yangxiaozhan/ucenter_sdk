<?php

/**
 * UCenter PHP SDK 接口测试 Demo
 *
 * 使用前请任选其一：
 * 1. 复制 config.example.php 为 config.php，填写 base_url / app_id / secret
 * 2. 或设置环境变量 UCENTER_BASE_URL、UCENTER_APP_ID、UCENTER_SECRET
 *
 * 运行: 在项目根目录执行 php demo/demo.php
 */

error_reporting(E_ALL);
ini_set('display_errors', '1');

$demoRoot = dirname(__DIR__);
require $demoRoot . '/vendor/autoload.php';

use UCenter\Sdk\UCenterClient;
use UCenter\Sdk\Exception\UCenterException;

// 加载配置
$configFile = __DIR__ . '/config.php';
if (!is_file($configFile)) {
    $config = [
        'base_url' => getenv('UCENTER_BASE_URL') ?: '',
        'app_id'   => getenv('UCENTER_APP_ID') ?: '',
        'secret'   => getenv('UCENTER_SECRET') ?: '',
    ];
} else {
    $config = require $configFile;
}

if (empty($config['base_url']) || empty($config['app_id']) || empty($config['secret'])) {
    echo "请先配置 UCenter：复制 demo/config.example.php 为 demo/config.php 并填写 base_url、app_id、secret，或设置环境变量。\n";
    exit(1);
}

$client = new UCenterClient($config['base_url'], $config['app_id'], $config['secret']);
$client->setTimeout(15);

$testUsername = 'sdk_demo_user_' . substr(uniqid(), -6);
$testPassword = 'DemoPass123';
$testEmail = 'sdk_demo_' . substr(uniqid(), -6) . '@example.com';

function section(string $title): void
{
    echo "\n" . str_repeat('=', 60) . "\n";
    echo "  " . $title . "\n";
    echo str_repeat('=', 60) . "\n";
}

function ok(string $name, $result = null): void
{
    echo "  [OK] {$name}";
    if ($result !== null) {
        if (is_scalar($result)) {
            echo " => " . (string) $result;
        } else {
            echo " => " . json_encode($result, JSON_UNESCAPED_UNICODE);
        }
    }
    echo "\n";
}

function fail(string $name, string $message): void
{
    echo "  [FAIL] {$name}: {$message}\n";
}

// ==================== Token ====================
section('Token（自动获取）');
try {
    $token = $client->getToken();
    ok('getToken', ['token_length' => strlen($token)]);
} catch (Throwable $e) {
    fail('getToken', $e->getMessage());
    if ($e instanceof UCenterException && $e->getResponse()) {
        echo "    响应: " . json_encode($e->getResponse(), JSON_UNESCAPED_UNICODE) . "\n";
    }
    echo "\n后续接口依赖 Token，请检查 base_url / app_id / secret 是否正确。\n";
    exit(1);
}

// ==================== 用户接口 ====================
section('用户接口 User');

$registeredUid = 0;
try {
    $uid = $client->user()->register($testUsername, $testPassword, $testEmail);
    if ($uid > 0) {
        $registeredUid = $uid;
        ok('register', "uid={$uid}");
    } else {
        fail('register', "返回 {$uid}（可能用户名已存在或规则限制）");
    }
} catch (Throwable $e) {
    fail('register', $e->getMessage());
}

if ($registeredUid > 0) {
    try {
        $res = $client->user()->login($testUsername, $testPassword);
        $status = $res['status'] ?? -999;
        if ($status > 0) {
            ok('login', ['uid' => $status, 'username' => $res['username'] ?? '']);
        } else {
            fail('login', "status={$status}");
        }
    } catch (Throwable $e) {
        fail('login', $e->getMessage());
    }

    try {
        $user = $client->user()->getUser($testUsername, false);
        ok('getUser(按用户名)', $user);
    } catch (Throwable $e) {
        fail('getUser(按用户名)', $e->getMessage());
    }

    try {
        $user = $client->user()->getUser((string) $registeredUid, true);
        ok('getUser(按 uid)', $user);
    } catch (Throwable $e) {
        fail('getUser(按 uid)', $e->getMessage());
    }
}

try {
    $ret = $client->user()->checkUsername('not_exist_user_xyz_' . time());
    ok('checkUsername(不存在的用户名)', "ret={$ret} (1=可用)");
} catch (Throwable $e) {
    fail('checkUsername', $e->getMessage());
}

try {
    $ret = $client->user()->checkEmail('newmail_' . time() . '@example.com');
    ok('checkEmail(新邮箱)', "ret={$ret} (1=可用)");
} catch (Throwable $e) {
    fail('checkEmail', $e->getMessage());
}

try {
    $auth = $client->user()->authorize('https://yourapp.com/callback');
    ok('authorize', isset($auth['url']) ? ['has_url' => true] : $auth);
} catch (Throwable $e) {
    fail('authorize', $e->getMessage());
}

try {
    $html = $client->user()->synLogin($registeredUid > 0 ? $registeredUid : 1);
    ok('synLogin', ['html_length' => strlen($html)]);
} catch (Throwable $e) {
    fail('synLogin', $e->getMessage());
}

try {
    $html = $client->user()->synLogout();
    ok('synLogout', ['html_length' => strlen($html)]);
} catch (Throwable $e) {
    fail('synLogout', $e->getMessage());
}

try {
    $list = $client->user()->getProtected();
    ok('getProtected', is_array($list) ? ['count' => count($list)] : $list);
} catch (Throwable $e) {
    fail('getProtected', $e->getMessage());
}

try {
    $credit = $client->user()->getCredit(1, $registeredUid > 0 ? $registeredUid : 1, 1);
    ok('getCredit', $credit);
} catch (Throwable $e) {
    fail('getCredit', $e->getMessage());
}

// 短消息在删除测试用户前调用（需要有效 uid/用户名）
$testUid = $registeredUid > 0 ? $registeredUid : 1;
section('短消息接口 Pm');
try {
    $res = $client->pm()->checkNewPm($testUid, 0);
    ok('checkNewPm', $res);
} catch (Throwable $e) {
    fail('checkNewPm', $e->getMessage());
}
try {
    $msgId = $client->pm()->send($testUid, $testUsername, 'SDK Test', 'Demo message');
    ok('send', $msgId > 0 ? "msgid={$msgId}" : $msgId);
} catch (Throwable $e) {
    fail('send', $e->getMessage());
}

// ==================== 更新用户字段 ====================
section('更新用户字段 User Profile');

if ($registeredUid > 0) {
    try {
        $ret = $client->user()->updateProfile($testUsername, [
            'phone' => '13800138000',
            'nickname' => 'SDK测试昵称',
            'avatar' => 'https://example.com/avatar/demo.jpg',
            'wechat_unionid' => 'demo_unionid_' . substr(uniqid(), -6),
            'wechat_openid' => 'demo_openid_' . substr(uniqid(), -6),
        ]);
        ok('updateProfile(phone,nickname,avatar,wechat)', "ret={$ret}");
    } catch (Throwable $e) {
        fail('updateProfile', $e->getMessage());
    }

    try {
        $ret = $client->user()->setPhone($testUsername, '13900139000');
        ok('setPhone', "ret={$ret}");
    } catch (Throwable $e) {
        fail('setPhone', $e->getMessage());
    }

    try {
        $ret = $client->user()->setNickname($testUsername, 'Demo昵称');
        ok('setNickname', "ret={$ret}");
    } catch (Throwable $e) {
        fail('setNickname', $e->getMessage());
    }

    try {
        $ret = $client->user()->setAvatar($testUsername, 'https://cdn.example.com/uc/avatar.jpg');
        ok('setAvatar(URL)', "ret={$ret}");
    } catch (Throwable $e) {
        fail('setAvatar', $e->getMessage());
    }

    try {
        $ret = $client->user()->setWechatOpenid($testUsername, 'wx_openid_demo');
        ok('setWechatOpenid', "ret={$ret}");
    } catch (Throwable $e) {
        fail('setWechatOpenid', $e->getMessage());
    }

    try {
        $ret = $client->user()->setWechatUnionid($testUsername, 'wx_unionid_demo');
        ok('setWechatUnionid', "ret={$ret}");
    } catch (Throwable $e) {
        fail('setWechatUnionid', $e->getMessage());
    }

    try {
        $user = $client->user()->getUser($testUsername);
        $hasFields = isset($user['uid']) && (array_key_exists('phone', $user) || array_key_exists('nickname', $user) || array_key_exists('avatar', $user));
        ok('getUser(验证扩展字段)', $hasFields ? ['uid' => $user['uid'] ?? 0, 'phone' => $user['phone'] ?? null, 'nickname' => $user['nickname'] ?? null] : $user);
    } catch (Throwable $e) {
        fail('getUser(验证扩展字段)', $e->getMessage());
    }

    try {
        $ret = $client->user()->edit($testUsername, $testPassword, '', '', true, 0, '', '13700137000', null, null, null, null, 'edit昵称', 'https://edit-avatar.jpg', null, null, false);
        ok('edit(扩展字段)', "ret={$ret}");
    } catch (Throwable $e) {
        fail('edit(扩展字段)', $e->getMessage());
    }

    try {
        $ret = $client->user()->deleteAvatar($registeredUid);
        ok('deleteAvatar', "ret={$ret}");
    } catch (Throwable $e) {
        fail('deleteAvatar', $e->getMessage());
    }
} else {
    echo "  [skip] 无测试用户，跳过更新用户字段测试\n";
}

if ($registeredUid > 0) {
    try {
        $ret = $client->user()->edit($testUsername, $testPassword, '', '', true);
        ok('edit(忽略旧密码)', "ret={$ret}");
    } catch (Throwable $e) {
        fail('edit', $e->getMessage());
    }

    try {
        $ret = $client->user()->delete($registeredUid);
        ok('delete 测试用户', "ret={$ret}");
    } catch (Throwable $e) {
        fail('delete', $e->getMessage());
    }
}

// ==================== 好友接口 ====================
section('好友接口 Friend');

try {
    $ok = $client->friend()->add(1, 2, 'demo comment');
    ok('add(1, 2)', $ok ? 'true' : 'false');
} catch (Throwable $e) {
    fail('add', $e->getMessage());
}

try {
    $ok = $client->friend()->delete(1, 2);
    ok('delete(1, 2)', $ok ? 'true' : 'false');
} catch (Throwable $e) {
    fail('delete', $e->getMessage());
}

// ==================== 积分接口 ====================
section('积分接口 Credit');

try {
    $ok = $client->credit()->request(1, 1, 2, 1, 10);
    ok('request(积分兑换)', $ok ? 'true' : 'false');
} catch (Throwable $e) {
    fail('request', $e->getMessage());
}

// ==================== 标签接口 ====================
section('标签接口 Tag');

try {
    $data = $client->tag()->getTag('test', []);
    ok('getTag', is_array($data) ? ['count' => count($data)] : $data);
} catch (Throwable $e) {
    fail('getTag', $e->getMessage());
}

// ==================== 直接 request ====================
section('直接 request');

try {
    $res = $client->request('user/get_user', ['username' => 'admin', 'isuid' => 0]);
    ok('request(user/get_user)', array_key_exists('uid', $res) ? $res : $res);
} catch (Throwable $e) {
    fail('request(user/get_user)', $e->getMessage());
}

// ==================== 短信发送（腾讯云） ====================
section('短信发送（腾讯云）');

$smsConfig = $config['sms'] ?? [];
$smsReady = !empty($smsConfig['secret_id']) && !empty($smsConfig['secret_key']) && !empty($smsConfig['sms_sdk_app_id']) && !empty($smsConfig['sign_name']);

if (!class_exists(\TencentCloud\Sms\V20210111\SmsClient::class)) {
    ok('腾讯云短信库', '未安装，跳过。安装: composer require tencentcloud/sms');
} else {
    try {
        $sms = new \UCenter\Sdk\Sms\TencentCloudSms(
            $smsConfig['secret_id'] ?? 'x',
            $smsConfig['secret_key'] ?? 'x',
            $smsConfig['sms_sdk_app_id'] ?? '0',
            $smsConfig['sign_name'] ?? '',
            $smsConfig['region'] ?? 'ap-guangzhou'
        );
        ok('TencentCloudSms 初始化', 'ok');
    } catch (Throwable $e) {
        fail('TencentCloudSms', $e->getMessage());
    }
    if ($smsReady && !empty($smsConfig['template_id']) && !empty($smsConfig['test_phone'])) {
        try {
            $sms = new \UCenter\Sdk\Sms\TencentCloudSms(
                $smsConfig['secret_id'],
                $smsConfig['secret_key'],
                $smsConfig['sms_sdk_app_id'],
                $smsConfig['sign_name'],
                $smsConfig['region'] ?? 'ap-guangzhou'
            );
            $result = $sms->sendOne($smsConfig['test_phone'], $smsConfig['template_id'], $smsConfig['template_param_set'] ?? []);
            ok('sendOne', ($result['Code'] ?? '') === 'Ok' ? 'Ok' : $result);
        } catch (Throwable $e) {
            fail('sendOne', $e->getMessage());
        }
    } else {
        ok('sendOne', '跳过。在 config 中配置 sms.template_id 与 sms.test_phone 可测试真实发送');
    }
}

// ==================== 微信模板消息 ====================
section('微信模板消息');

$wechatConfig = $config['wechat'] ?? [];
$wechatReady = !empty($wechatConfig['account_id']) && !empty($wechatConfig['app_id']) && !empty($wechatConfig['app_secret']);

if ($wechatReady) {
    try {
        $wechat = new \UCenter\Sdk\Wechat\WechatTemplateMessage(
            $wechatConfig['account_id'],
            $wechatConfig['app_id'],
            $wechatConfig['app_secret']
        );
        ok('WechatTemplateMessage 初始化', 'ok');
    } catch (Throwable $e) {
        fail('WechatTemplateMessage 初始化', $e->getMessage());
    }
    if (!empty($wechatConfig['template_id']) && !empty($wechatConfig['test_openid'])) {
        try {
            $wechat = new \UCenter\Sdk\Wechat\WechatTemplateMessage(
                $wechatConfig['account_id'],
                $wechatConfig['app_id'],
                $wechatConfig['app_secret']
            );
            $res = $wechat->send(
                $wechatConfig['test_openid'],
                $wechatConfig['template_id'],
                [
                    "thing2"=>"这是一条测试消息",
                    "const6"=>"已通过",
                    "time7"=>"2026-02-10 10:00:00"
                ]
            );
            ok('send 模板消息', ($res['errcode'] ?? -1) === 0 ? 'ok msgid=' . ($res['msgid'] ?? '') : $res);
        } catch (Throwable $e) {
            fail('send 模板消息', $e->getMessage());
        }
    } else {
        ok('send 模板消息', '跳过。在 config 中配置 wechat.template_id 与 wechat.test_openid 可测试真实发送');
    }
} else {
    ok('微信模板消息', '未配置 wechat（account_id、app_id、app_secret），跳过');
}

echo "\n" . str_repeat('=', 60) . "\n";
echo "  Demo 执行完毕。请根据上述 [OK]/[FAIL] 检查各接口是否可用。\n";
echo str_repeat('=', 60) . "\n\n";

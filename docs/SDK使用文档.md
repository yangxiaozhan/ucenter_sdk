# UCenter PHP SDK 使用文档

本文档面向接入 UCenter 用户中心的第三方应用开发者，介绍如何使用 PHP SDK 完成用户注册、登录、资料管理、短消息、好友、积分等能力对接。

---

## 目录

- [一、简介](#一简介)
- [二、环境要求与安装](#二环境要求与安装)
- [三、快速开始](#三快速开始)
- [四、用户接口](#四用户接口)
- [五、短消息接口](#五短消息接口)
- [六、好友接口](#六好友接口)
- [七、积分与标签](#七积分与标签)
- [八、短信发送（腾讯云）](#八短信发送腾讯云)
- [九、微信模板消息](#九微信模板消息)
- [十、错误处理](#十错误处理)
- [十一、附录](#十一附录)

---

## 一、简介

### 1.1 什么是 UCenter

UCenter（用户中心）提供统一的用户账号体系。第三方应用通过 API 接入后，可实现：

- **统一账号**：用户一次注册，多应用通用
- **一站式登录**：支持账号密码登录、授权登录、同步登录/退出
- **统一资料**：用户名、邮箱、手机号、头像、微信/QQ 等第三方绑定
- **扩展能力**：短消息、好友、积分、标签等

### 1.2 SDK 能力

本 SDK 封装了 UCenter 的 HTTP API，自动处理：

- **鉴权**：Header 签名（appid、nonce、时间戳、token、sign）
- **Token**：自动获取与续签，无需手动维护
- **请求/响应**：统一请求格式与返回结构解析

您只需配置 UCenter 地址、应用 ID、密钥，即可调用用户、短消息、好友、积分、标签等接口。

---

## 二、环境要求与安装

### 2.1 环境要求

| 项目     | 要求        |
|----------|-------------|
| PHP 版本 | >= 7.4      |
| 扩展     | ext-json    |

### 2.2 安装

通过 Composer 安装（推荐）：

```bash
composer require ucenter/sdk-php
```

或下载源码后在项目根目录执行：

```bash
composer install
```

### 2.3 获取接入凭证

在 UCenter 管理后台创建应用后，可获得：

- **应用 ID（appid）**：应用唯一标识
- **密钥（secret）**：用于请求签名，请妥善保管

---

## 三、快速开始

### 3.1 初始化客户端

```php
<?php
use UCenter\Sdk\UCenterClient;

$client = new UCenterClient(
    'https://uc.example.com',   // UCenter 根地址（不要带末尾斜杠）
    'your_appid',               // 应用 ID
    'your_secret'               // 密钥
);

// 可选：设置请求超时时间（秒），默认 10
$client->setTimeout(15);
```

### 3.2 最小示例：注册并登录

```php
// 用户注册
$uid = $client->user()->register('myuser', 'MyPass123', 'user@example.com');
if ($uid > 0) {
    echo "注册成功，用户 ID：{$uid}\n";
} else {
    echo "注册失败，错误码：{$uid}\n";  // 负数表示失败原因，见下文错误码
}

// 用户登录
$res = $client->user()->login('myuser', 'MyPass123');
if (($res['status'] ?? 0) > 0) {
    echo "登录成功，UID：{$res['status']}，用户名：{$res['username']}\n";
} else {
    echo "登录失败\n";
}

// 获取用户信息
$user = $client->user()->getUser('myuser');
print_r($user);  // 含 uid, username, email, phone, avatar, nickname 等
```

---

## 四、用户接口

### 4.1 用户注册

```php
$uid = $client->user()->register(
    'username',           // 用户名
    'password',           // 密码
    'user@example.com',   // 邮箱
    0,                    // 安全提问索引（可选，默认 0）
    '',                   // 安全提问答案（可选）
    ''                    // 注册 IP（可选）
);
```

**返回值**：`int`  
- 大于 0：注册成功，返回用户 ID  
- 负数：失败，含义如下  

| 返回值 | 含义           |
|--------|----------------|
| -1     | 用户名不合法   |
| -2     | 包含不允许注册的词语 |
| -3     | 用户名已存在   |
| -4     | Email 格式有误 |
| -5     | Email 不允许注册 |
| -6     | 该 Email 已被注册 |

---

### 4.2 用户登录

```php
$res = $client->user()->login(
    'username',   // 用户名 / 用户 ID / 用户 Email
    'password',   // 密码
    0,            // 是否按 uid 登录：0 用户名，1 uid，2 email
    false,        // 是否验证安全提问
    0,            // 安全提问索引
    ''            // 安全提问答案
);
```

**返回值**：`array`  
- `status` > 0：登录成功，值为用户 ID；同时可能有 `username`、`email` 等  
- `status` 为负：失败  

| status | 含义         |
|--------|--------------|
| -1     | 用户不存在或已删除 |
| -2     | 密码错误     |
| -3     | 安全提问错误 |

---

### 4.3 获取用户信息

```php
// 按用户名获取
$user = $client->user()->getUser('username');

// 按用户 ID 获取
$user = $client->user()->getUser('65', true);  // 第二参数 isuid = true
```

**返回值**：`array`，扁平用户信息。用户不存在时 `uid` 为 0。常见字段：

| 字段            | 说明         |
|-----------------|--------------|
| uid             | 用户 ID      |
| username        | 用户名       |
| email           | 邮箱         |
| phone           | 手机号       |
| nickname        | 昵称         |
| avatar          | 头像 URL     |
| wechat_unionid  | 微信 unionid |
| wechat_openid   | 微信 openid  |
| qq_union_id     | QQ union id  |
| weibo_openid    | 微博 openid  |
| douyin_openid   | 抖音 openid  |
| is_member       | 联盟会员     |
| created_at      | 创建时间     |
| updated_at      | 更新时间     |

（实际字段以 UCenter 管理后台配置为准。）

---

### 4.4 更新用户资料

#### 方式一：完整编辑（可改密码/邮箱，需旧密码或忽略）

```php
$ret = $client->user()->edit(
    'username',      // 用户名
    '',              // 旧密码（不修改密码可空）
    '',              // 新密码（不修改可空）
    '',              // 新邮箱（不修改可空）
    true,             // 是否忽略旧密码：true 则不需要验证旧密码
    0,                // 安全提问索引
    '',               // 安全提问答案
    '13800138000',    // 手机号（可选，null 表示不修改）
    null,             // 微信 openid（可选）
    null,             // 微信 unionid（可选）
    null,             // qq_union_id（可选）
    null,             // weibo_openid（可选）
    '昵称',           // 昵称（可选）
    'https://...',    // 头像 URL（可选，由前置应用上传，此处仅记录）
    null,             // douyin_openid（可选）
    null,             // is_member（可选）
    false             // 是否按 uid 查找（username 传 uid 时设为 true）
);
// $ret：1 成功，0 无修改，负数见文档
```

#### 方式二：仅更新扩展资料（无需旧密码）

适用于只更新手机号、头像、昵称、第三方绑定等，不涉及密码/邮箱：

```php
$ret = $client->user()->updateProfile('username', [
    'phone'          => '13800138000',
    'nickname'       => '昵称',
    'avatar'         => 'https://cdn.example.com/avatar.jpg',  // 头像为 URL 字符串
    'wechat_openid'  => 'openid_xxx',
    'wechat_unionid' => 'unionid_xxx',
    'qq_union_id'    => '',
    'weibo_openid'   => '',
    'douyin_openid'  => '',
    'is_member'      => 1,
]);
// 按 uid 更新：updateProfile('65', [...], true);
```

#### 便捷方法（单字段）

```php
$client->user()->setPhone('username', '13800138000');
$client->user()->setAvatar('username', 'https://example.com/avatar.jpg');  // 头像仅记录 URL
$client->user()->setWechatOpenid('username', 'openid_xxx');
$client->user()->setWechatUnionid('username', 'unionid_xxx');
$client->user()->setNickname('username', '昵称');
$client->user()->deleteAvatar($uid);   // 清空头像 URL 记录
```

---

### 4.5 删除用户

```php
$client->user()->delete(123);           // 删除单个用户（uid）
$client->user()->delete([123, 456]);    // 批量删除
```

**返回值**：`int`，1 成功，0 失败。

---

### 4.6 授权登录（OAuth 式）

适用于「在 UCenter 授权页登录后跳回业务方」的场景：

```php
// 1. 获取授权页地址，引导用户跳转
$auth = $client->user()->authorize('https://yourapp.com/callback');
header('Location: ' . $auth['url']);  // 或 $auth['data']['url']，以实际返回为准

// 2. 用户在 UCenter 登录成功后，会带着 code 回调到 callback
// 3. 在回调页用 code 换取用户信息
$info = $client->user()->checkCode($_GET['code']);
if (($info['ret'] ?? -1) === 0) {
    $uid      = $info['uid'];
    $username = $info['username'];
    // 完成业务侧登录
}
```

---

### 4.7 同步登录 / 同步退出

用于多应用间「一处登录，处处登录」或「一处退出，处处退出」：

```php
// 同步登录：返回需输出到页面的 HTML（通常为 script 等）
$html = $client->user()->synLogin($uid);
echo $html;

// 同步退出
$html = $client->user()->synLogout();
echo $html;
```

请在该页面完整输出返回的 HTML，并在其执行完成后再跳转，以保证各应用 Cookie 正确设置。

---

### 4.8 校验与保护用户

```php
// 检查用户名是否可用
$ret = $client->user()->checkUsername('newuser');  // 1 可用，-1/-2/-3 不可用

// 检查邮箱是否可用
$ret = $client->user()->checkEmail('new@example.com');  // 1 可用，-4/-5/-6 不可用

// 获取受保护用户列表
$list = $client->user()->getProtected();
```

---

### 4.9 积分（用户维度）

```php
$credit = $client->user()->getCredit($appid, $uid, $creditId);
// 返回该用户在该应用下的指定积分值
```

---

## 五、短消息接口

```php
// 检查是否有新短消息
$res = $client->pm()->checkNewPm($uid, $more);
// $more：0 仅未读数，1 未读数+最后时间，2/3 更多信息

// 发送短消息
$msgId = $client->pm()->send($fromUid, $toUsername, '标题', '内容');

// 删除短消息
$client->pm()->delete($uid, $pmId);        // 单条
$client->pm()->delete($uid, [1, 2, 3]);   // 多条

// 其它短消息接口（列表等）
$res = $client->pm()->call('list', ['uid' => $uid, ...]);
```

---

## 六、好友接口

```php
// 添加好友
$ok = $client->friend()->add($uid, $friendUid, '备注');

// 删除好友
$ok = $client->friend()->delete($uid, $friendUid);

// 其它好友接口
$res = $client->friend()->call('ls', ['uid' => $uid, ...]);
```

---

## 七、积分与标签

### 7.1 积分兑换

```php
$ok = $client->credit()->request($uid, $fromCreditId, $toCreditId, $toAppid, $amount);
```

### 7.2 标签

```php
$data = $client->tag()->getTag('tagname', [/* 应用 ID => 返回条数 */]);
```

---

## 八、短信发送（腾讯云）

本 SDK 提供腾讯云短信发送封装，使用前需安装腾讯云短信库（tencentcloud/sms，仅短信无需全产品 SDK）：

```bash
composer require tencentcloud/sms
```

### 8.1 初始化

在 [腾讯云 API 密钥](https://console.cloud.tencent.com/cam/capi) 获取 SecretId、SecretKey；在 [短信控制台](https://console.cloud.tencent.com/smsv2) 创建应用获取 SdkAppId、申请签名与模板。

```php
use UCenter\Sdk\Sms\TencentCloudSms;

$sms = new TencentCloudSms(
    'your_secret_id',      // 腾讯云 SecretId
    'your_secret_key',     // 腾讯云 SecretKey
    '1400006666',          // 短信 SdkAppId
    '您的签名',             // 短信签名内容（国内短信必填）
    'ap-guangzhou'         // 地域，默认 ap-guangzhou
);
```

### 8.2 发送单条短信

```php
$result = $sms->sendOne('13800138000', '1110', ['123456']);
// 模板 ID 1110，模板参数（如验证码）为 ['123456']
if (($result['Code'] ?? '') === 'Ok') {
    echo '发送成功，SerialNo：' . ($result['SerialNo'] ?? '');
} else {
    echo '发送失败：' . ($result['Message'] ?? '');
}
```

### 8.3 批量发送

```php
$results = $sms->send(
    ['13800138000', '13900139000'],
    '1110',
    ['验证码']   // 模板参数，与模板变量个数一致
);
foreach ($results as $r) {
    echo $r['PhoneNumber'] . ' ' . ($r['Code'] ?? '') . ' ' . ($r['Message'] ?? '') . "\n";
}
```

### 8.4 手机号格式

国内 11 位手机号会自动补 `+86`（E.164）。也可直接传 `+8613800138000`。单次最多 200 个号码。

### 8.5 错误处理

未安装腾讯云短信库或调用失败会抛出 `UCenter\Sdk\Exception\UCenterException`，消息中会包含原因（如「请先安装 tencentcloud/sms」或腾讯云返回错误信息）。

---

## 九、微信模板消息

本 SDK 提供微信公众号模板消息发送封装，需使用**服务号**及公众平台已审核通过的**模板**。

### 9.1 初始化

需在微信公众平台获取：公众号 appid、公众号密钥（AppSecret/key）。多公众号场景可传入公众号 id 用于区分。

```php
use UCenter\Sdk\Wechat\WechatTemplateMessage;

$wechat = new WechatTemplateMessage(
    'account_1',       // 公众号 id（可选，多公众号时用于标识）
    'wx_appid',        // 公众号 appid
    'app_secret_key'   // 公众号密钥 key / AppSecret
);

$wechat->setTimeout(15);  // 可选，请求超时秒数
```

### 9.2 发送模板消息

```php
$res = $wechat->send(
    'user_openid',     // 接收者 openid（服务号下用户 openid）
    'TEMPLATE_ID',     // 模板 id（公众平台已审核的模板）
    [                  // 模板参数，键为模板占位符名（如 first、keyword1、remark）
        'first' => '您好，订单状态更新',
        'keyword1' => '202308150001',
        'keyword2' => '已发货',
        'remark' => '感谢使用',
    ],
    'https://example.com/order/123',  // 可选，点击消息跳转 URL
    []   // 可选，跳转小程序：["appid"=>"小程序appid","pagepath"=>"页面路径"]
);
```

**参数说明**：

| 参数 | 类型 | 说明 |
|------|------|------|
| openId | string | 接收者 openid（服务号 openid，非小程序 openid） |
| templateId | string | 公众平台已审核的模板 id |
| data | array | 模板数据，键与模板占位符一致。值为字符串或 `["value"=>"xxx","color"=>"#173177"]` |
| url | string | 可选，点击后跳转的 URL |
| miniprogram | array | 可选，`appid` + `pagepath` 跳转小程序 |

**返回值**：`errcode` 为 0 表示成功，`msgid` 为消息 id；非 0 为失败，`errmsg` 为错误信息。

### 9.3 获取 access_token

如需单独获取 access_token（类内部会自动缓存并在发送时使用）：

```php
$token = $wechat->getAccessToken();
```

### 9.4 错误处理

获取 token 失败或发送失败会抛出 `UCenter\Sdk\Exception\UCenterException`，消息中会包含微信返回的错误信息。

---

## 十、错误处理

### 10.1 异常类

请求失败或接口返回错误时，SDK 会抛出 `UCenter\Sdk\Exception\UCenterException`：

```php
use UCenter\Sdk\UCenterClient;
use UCenter\Sdk\Exception\UCenterException;

try {
    $uid = $client->user()->register('user', 'pass', 'a@b.com');
} catch (UCenterException $e) {
    echo $e->getMessage();           // 异常信息
    echo $e->getCode();              // HTTP 状态码或 0
    $resp = $e->getResponse();       // 接口返回的数组（可能含 ret、msg 等）
    if ($resp) {
        print_r($resp);
    }
}
```

### 10.2 业务错误码

部分接口通过返回值表示业务结果（如注册、登录、编辑），负数表示失败，具体含义见各接口说明表格。  
HTTP 或签名等错误通过异常抛出，可通过 `getResponse()` 查看服务端返回内容。

---

## 十一、附录

### 11.1 直接调用未封装的接口

若服务端新增接口或 SDK 暂未封装，可直接发起请求：

```php
$result = $client->request('user/get_user', ['username' => 'test', 'isuid' => 0]);
// $result 为解析后的数组
```

### 11.2 自定义 HTTP 客户端

如需使用 Guzzle 等库，可注入自定义客户端：

```php
$client->setHttpClient(function (string $url, array $headers, string $body): array {
    // 发送 POST 请求，$headers 为 ['Header-Name: value', ...]
    // 返回 ['body' => 响应体字符串, 'http_code' => HTTP 状态码]
});
```

### 11.3 版本与兼容

- SDK 面向 UCenter 2 及以上版本 API。  
- 用户资料扩展字段（如 phone、avatar、wechat_unionid 等）以实际 UCenter 管理后台配置为准；未配置的字段可能不存在或为 null。  
- 头像字段为 URL 字符串，由前置应用上传后写入，本 SDK 仅做 URL 记录与清空（deleteAvatar）。

---

**文档版本**：随 SDK 发布更新。如有疑问，请联系 UCenter 提供方或查阅 UCenter 官方接口文档。

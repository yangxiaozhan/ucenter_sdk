# ucenter_sdk

PHP SDK for UCenter 用户中心，供第三方应用通过 API 访问同一用户中心（UCenter），实现用户注册、登录、资料、短消息、好友、积分、标签等能力。

接口规范基于项目内 `document/` 目录中的 UCenter 接口开发手册（UCenter 2 及以上版本）。

**对外使用文档**：[docs/SDK使用文档.md](docs/SDK使用文档.md) — 面向第三方开发者的完整使用说明与接口说明。

## 环境要求

- PHP >= 7.4
- ext-json

## 安装

```bash
composer require ucenter/sdk-php
```

或在本仓库根目录执行：

```bash
composer install
```

## 配置

在 UCenter 管理后台创建应用后，可获得 **应用 ID (appid)** 和 **密钥 (secret)**。请求时需在 Header 中携带 `appid`、`nonce`、`t`、`token`、`sign`，SDK 会自动处理签名与 Token 获取/续签。

## 使用说明

### 初始化客户端

```php
use UCenter\Sdk\UCenterClient;

$client = new UCenterClient(
    'https://uc.example.com',   // UCenter 根地址，不要带末尾斜杠
    'your_appid',
    'your_secret'
);

// 可选：设置请求超时（秒）
$client->setTimeout(15);
```

### 用户接口

```php
// 用户注册
$uid = $client->user()->register('username', 'password', 'user@example.com');
// $uid > 0 为成功（返回用户 ID），负数见文档错误码

// 用户登录
$res = $client->user()->login('username', 'password');
// $res['status'] > 0 为成功（用户 ID），含 username、email 等

// 为登录用户颁发 JWT token（需配置 JWT 密钥）
use UCenter\Sdk\Jwt\JwtToken;
$jwt = new JwtToken('your_jwt_secret', 7200);
$token = $jwt->issue(['sub' => (string)$res['status'], 'username' => $res['username'] ?? '']);
// 验证 token
$payload = $jwt->verify($token);  // 含 sub、username、iat、exp 等

// 获取用户信息（含扩展字段：phone, wechat_unionid, nickname, avatar, qq_union_id, weibo_openid, douyin_openid, is_member 等）
$user = $client->user()->getUser('username');        // 按用户名
$user = $client->user()->getUser('123', true);       // 按 uid（第二参数 isuid=true）
// $user 为扁平数组，含 uid, username, email, phone, wechat_unionid, avatar, nickname 等

// 更新资料（需旧密码，或 ignoreoldpw=true）；可传扩展字段
$client->user()->edit('username', $oldpw, $newpw, $email, false, 0, '', $phone, $wechat_openid, $wechat_unionid, ...);

// 仅更新扩展资料（无需旧密码）
$client->user()->updateProfile('username', ['phone' => '13800138000', 'nickname' => '昵称', 'avatar' => 'https://...', 'wechat_unionid' => 'xxx']);
$client->user()->setPhone('username', '13800138000');
$client->user()->setAvatar('username', 'https://example.com/avatar.jpg');   // 头像为 URL 字符串，由前置应用上传后传入
$client->user()->setWechatOpenid('username', 'openid_xxx');
$client->user()->setWechatUnionid('username', 'unionid_xxx');
$client->user()->setNickname('username', '昵称');
$client->user()->deleteAvatar($uid);   // 清空头像 URL 记录

// 删除用户
$client->user()->delete(123);           // 单个 uid
$client->user()->delete([123, 456]);   // 多个 uid

// 授权登录：获取授权页地址
$auth = $client->user()->authorize('https://yourapp.com/callback');
// 跳转 $auth['url']，回调里用 code 调 checkCode

// 验证回调 code，换取用户
$info = $client->user()->checkCode($_GET['code']);
// $info['ret']==0 时，$info['uid'], $info['username'] 可用

// 同步登录 / 同步退出（返回需输出到页面的 HTML）
$html = $client->user()->synLogin($uid);
$html = $client->user()->synLogout();

// 检查用户名、邮箱是否可用
$client->user()->checkUsername('newuser');
$client->user()->checkEmail('new@example.com');
```

### 短消息、好友、积分、标签

```php
// 短消息
$client->pm()->checkNewPm($uid, $more);
$client->pm()->send($fromUid, $toUsername, $subject, $message);
$client->pm()->call('list', [...]);   // 其它接口见 document/html/pm.htm

// 好友
$client->friend()->add($uid, $friendid, $comment);
$client->friend()->delete($uid, $friendid);
$client->friend()->call('ls', [...]);

// 积分
$client->credit()->request($uid, $from, $to, $toappid, $amount);

// 标签
$client->tag()->getTag($tagname, $nums);
```

### 短信发送（腾讯云）

本 SDK 提供腾讯云短信发送封装，需单独安装腾讯云短信库（仅短信，无需全产品 SDK）：

```bash
composer require tencentcloud/sms
```


```php
use UCenter\Sdk\Sms\TencentCloudSms;

$sms = new TencentCloudSms(
    'your_secret_id',      // 腾讯云 API 密钥 SecretId
    'your_secret_key',     // 腾讯云 API 密钥 SecretKey
    '1400006666',          // 短信 SdkAppId（控制台应用管理）
    '您的签名',             // 短信签名内容（国内必填）
    'ap-guangzhou'         // 地域，默认 ap-guangzhou
);

// 发送单条
$result = $sms->sendOne('13800138000', '1110', ['123456']);  // 模板 ID、模板参数
// $result['Code'] === 'Ok' 表示成功

// 批量发送
$results = $sms->send(['13800138000', '13900139000'], '1110', ['验证码']);
```

详见 [docs/SDK使用文档.md](docs/SDK使用文档.md) 中「短信发送」章节。

### 微信模板消息

发送微信公众号模板消息（服务号 + 已审核模板）：

```php
use UCenter\Sdk\Wechat\WechatTemplateMessage;

$wechat = new WechatTemplateMessage(
    'account_1',       // 公众号 id（多公众号时用于区分）
    'wx_appid',       // 公众号 appid
    'app_secret_key'  // 公众号密钥 key / AppSecret
);

$res = $wechat->send(
    'user_openid',           // 接收者 openid
    'TEMPLATE_ID',           // 模板 id（公众平台已审核）
    [                        // 模板参数（键为模板占位符名）
        'first' => '您好，订单状态更新',
        'keyword1' => '202308150001',
        'keyword2' => '已发货',
        'remark' => '感谢使用',
    ],
    'https://example.com/order/123',  // 可选，点击跳转 URL
    []   // 可选，跳转小程序 ["appid"=>"","pagepath"=>""]
);
// $res['errcode'] === 0 表示成功，$res['msgid'] 为消息 id
```

详见 [docs/SDK使用文档.md](docs/SDK使用文档.md) 中「微信模板消息」章节。

### 直接请求任意接口

```php
$result = $client->request('user/get_user', ['username' => 'test', 'isuid' => 0]);
```

### 自定义 HTTP 客户端

若需使用 Guzzle 等，可注入自定义客户端：

```php
$client->setHttpClient(function (string $url, array $headers, string $body): array {
    // 发送请求，返回 ['body' => string, 'http_code' => int]
});
```

## 测试所有接口（Demo）

项目提供 `demo/demo.php` 用于一次性测试各模块接口是否可用。

1. 复制配置并填写 UCenter 地址与密钥：
   ```bash
   cp demo/config.example.php demo/config.php
   # 编辑 demo/config.php，填写 base_url、app_id、secret
   ```
2. 或在当前环境设置环境变量：`UCENTER_BASE_URL`、`UCENTER_APP_ID`、`UCENTER_SECRET`。
3. 在项目根目录执行：
   ```bash
   composer install
   php demo/demo.php
   ```
4. 根据输出的 `[OK]` / `[FAIL]` 查看各接口调用结果。Demo 会创建并删除一个测试用户，其它接口为只读或使用固定参数，可按需修改 `demo/demo.php`。

## 文档与错误码

- 全局参数、签名、Token：`document/html/base.htm`
- 用户：`document/html/user.htm`
- 短消息：`document/html/pm.htm`
- 好友：`document/html/friend.htm`
- 积分：`document/html/credit.htm`
- 标签：`document/html/tag.htm`

接口返回错误时 SDK 会抛出 `UCenter\Sdk\Exception\UCenterException`，可通过 `getResponse()` 获取原始返回。

## 软件架构

```
src/
├── UCenterClient.php      # 客户端：签名、Token、HTTP 请求
├── UCenterSdk.php         # 入口便捷方法
├── Api/
│   ├── BaseApi.php
│   ├── UserApi.php        # 用户注册/登录/获取/编辑/删除/同步登录等
│   ├── PmApi.php          # 短消息
│   ├── FriendApi.php      # 好友
│   ├── CreditApi.php      # 积分
│   └── TagApi.php         # 标签
└── Exception/
    └── UCenterException.php
```

## 参与贡献

1. Fork 本仓库  
2. 新建 Feat_xxx 分支  
3. 提交代码  
4. 新建 Pull Request  

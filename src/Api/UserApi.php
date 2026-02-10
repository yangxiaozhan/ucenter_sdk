<?php

declare(strict_types=1);

namespace UCenter\Sdk\Api;

/**
 * 用户接口
 * @see document/html/user.htm
 */
class UserApi extends BaseApi
{
    /**
     * 用户注册
     * @return int 大于 0 为用户 ID；负数见文档（-1 用户名不合法，-2 不允许词语，-3 用户名已存在，-4/-5/-6 Email 相关）
     */
    public function register(string $username, string $password, string $email, int $questionid = 0, string $answer = '', string $regip = ''): int
    {
        $params = [
            'username' => $username,
            'password' => $password,
            'email' => $email,
            'questionid' => $questionid,
            'answer' => $answer,
            'regip' => $regip,
        ];
        $ret = $this->client->request('user/register', $params);
        return (int) ($ret['ret'] ?? 0);
    }

    /**
     * 用户登录
     * @return array{status: int, username?: string, email?: string} status>0 为 uid，-1 用户不存在，-2 密码错，-3 安全提问错
     */
    public function login(string $username, string $password, int $isuid = 0, bool $checkques = false, int $questionid = 0, string $answer = ''): array
    {
        $params = [
            'username' => $username,
            'password' => $password,
            'isuid' => $isuid,
            'checkques' => $checkques ? 1 : 0,
            'questionid' => $questionid,
            'answer' => $answer,
        ];
        $res = $this->client->request('user/login', $params);
        // 兼容服务端 { ret, data: { uid/status, username, email } } 结构
        $data = $res['data'] ?? $res;
        if (isset($data['uid']) && !isset($data['status'])) {
            $data['status'] = (int) $data['uid'];
        }
        return $data;
    }

    /**
     * 用户授权登录 - 获取授权登录地址
     * @return array{url: string}
     */
    public function authorize(string $callback): array
    {
        return $this->client->request('user/authorize', ['callback' => $callback]);
    }

    /**
     * 验证回调 - 用 authorize 返回的 code 换用户信息
     * @return array{ret: int, uid?: int, username?: string} ret=0 成功，-1 code 错误，-2 用户不存在
     */
    public function checkCode(string $code): array
    {
        return $this->client->request('user/check_code', ['code' => $code]);
    }

    /**
     * 获取用户数据（含扩展字段：phone, wechat_unionid, qq_union_id, weibo_openid, nickname, avatar, douyin_openid, is_member 等）
     * @return array 用户不存在时 uid 为 0，成功时返回扁平用户信息（含管理后台配置的扩展字段）
     */
    public function getUser(string $username, bool $isuid = false): array
    {
        $params = ['username' => $username, 'isuid' => $isuid ? 1 : 0];
        $res = $this->client->request('user/get_user', $params);
        return $res['data'] ?? $res;
    }

    /**
     * 更新用户资料（含扩展字段）
     * @param string $username 用户名
     * @param string $oldpw 旧密码
     * @param string $newpw 新密码，不修改传空
     * @param string $email Email，不修改传空
     * @param bool $ignoreoldpw 是否忽略旧密码
     * @param int $questionid 安全提问索引
     * @param string $answer 安全提问答案
     * @param string|null $phone 手机号 varchar(11)
     * @param string|null $wechat_openid 微信 openid
     * @param string|null $wechat_unionid 微信 unionid
     * @param string|null $qq_union_id QQ union id
     * @param string|null $weibo_openid 微博 openid
     * @param string|null $nickname 昵称
     * @param string|null $avatar 头像 URL（由前置应用上传，此处仅记录 URL 字符串）
     * @param string|null $douyin_openid 抖音 openid
     * @param int|null $is_member 联盟会员 0/1
     * @param bool $isuid 是否按 uid 查找用户（username 传 uid 时设为 true）
     * @return int 1 成功，0 无修改，负数见文档
     */
    public function edit(
        string $username,
        string $oldpw = '',
        string $newpw = '',
        string $email = '',
        bool $ignoreoldpw = false,
        int $questionid = 0,
        string $answer = '',
        ?string $phone = null,
        ?string $wechat_openid = null,
        ?string $wechat_unionid = null,
        ?string $qq_union_id = null,
        ?string $weibo_openid = null,
        ?string $nickname = null,
        ?string $avatar = null,
        ?string $douyin_openid = null,
        ?int $is_member = null,
        bool $isuid = false
    ): int {
        $params = [
            'username' => $username,
            'isuid' => $isuid ? 1 : 0,
            'oldpw' => $oldpw,
            'newpw' => $newpw,
            'email' => $email,
            'ignoreoldpw' => $ignoreoldpw ? 1 : 0,
            'questionid' => $questionid,
            'answer' => $answer,
        ];
        $extended = [
            'phone' => $phone,
            'wechat_openid' => $wechat_openid,
            'wechat_unionid' => $wechat_unionid,
            'qq_union_id' => $qq_union_id,
            'weibo_openid' => $weibo_openid,
            'nickname' => $nickname,
            'avatar' => $avatar,
            'douyin_openid' => $douyin_openid,
            'is_member' => $is_member,
        ];
        foreach ($extended as $key => $value) {
            if ($value !== null) {
                $params[$key] = $value;
            }
        }
        $ret = $this->client->request('user/edit', $params);
        return (int) ($ret['ret'] ?? 0);
    }

    /**
     * 仅更新扩展资料（手机号、微信、昵称、头像等），不修改密码/邮箱，无需旧密码
     * @param string $username 用户名或 uid 字符串
     * @param array<string, mixed> $fields 要更新的字段，如 ['phone'=>'13800138000','wechat_unionid'=>'xxx','nickname'=>'昵称','avatar'=>'https://...','wechat_openid'=>'xxx','qq_union_id'=>'','weibo_openid'=>'','douyin_openid'=>'','is_member'=>1]
     * @param bool $isuid 是否按 uid 查找用户
     */
    public function updateProfile(string $username, array $fields, bool $isuid = false): int
    {
        $params = [
            'username' => $username,
            'oldpw' => '',
            'newpw' => '',
            'email' => '',
            'ignoreoldpw' => 1,
            'questionid' => 0,
            'answer' => '',
        ];
        $allowed = [
            'phone', 'wechat_openid', 'wechat_unionid', 'qq_union_id', 'weibo_openid',
            'nickname', 'avatar', 'douyin_openid', 'is_member',
        ];
        foreach ($allowed as $key) {
            if (array_key_exists($key, $fields)) {
                $params[$key] = $fields[$key];
            }
        }
        $params['isuid'] = $isuid ? 1 : 0;
        $ret = $this->client->request('user/edit', $params);
        return (int) ($ret['ret'] ?? 0);
    }

    /**
     * 设置用户手机号
     */
    public function setPhone(string $username, string $phone, bool $isuid = false): int
    {
        return $this->updateProfile($username, ['phone' => $phone], $isuid);
    }

    /**
     * 设置用户头像 URL（由前置应用负责上传，此处仅记录 URL）
     */
    public function setAvatar(string $username, string $avatarUrl, bool $isuid = false): int
    {
        return $this->updateProfile($username, ['avatar' => $avatarUrl], $isuid);
    }

    /**
     * 设置微信 openid
     */
    public function setWechatOpenid(string $username, string $wechatOpenid, bool $isuid = false): int
    {
        return $this->updateProfile($username, ['wechat_openid' => $wechatOpenid], $isuid);
    }

    /**
     * 设置微信 unionid
     */
    public function setWechatUnionid(string $username, string $wechatUnionid, bool $isuid = false): int
    {
        return $this->updateProfile($username, ['wechat_unionid' => $wechatUnionid], $isuid);
    }

    /**
     * 设置昵称
     */
    public function setNickname(string $username, string $nickname, bool $isuid = false): int
    {
        return $this->updateProfile($username, ['nickname' => $nickname], $isuid);
    }

    /**
     * 删除用户头像（清空头像 URL 记录）
     * @param int $uid 用户 ID
     * @return int ret 1 成功，0 失败
     */
    public function deleteAvatar(int $uid): int
    {
        $ret = $this->client->request('user/deleteavatar', ['uid' => $uid]);
        return (int) ($ret['ret'] ?? 0);
    }

    /**
     * 删除用户
     * @param int|int[] $uid 用户 ID 或 ID 数组
     * @return int 1 成功，0 失败
     */
    public function delete($uid): int
    {
        $params = is_array($uid) ? ['uid' => $uid] : ['uid' => $uid];
        $ret = $this->client->request('user/delete', $params);
        return (int) ($ret['ret'] ?? 0);
    }

    /**
     * 同步登录 - 返回需输出到页面的 HTML（服务端返回 HTML 非 JSON）
     */
    public function synLogin(int $uid): string
    {
        return $this->client->requestRaw('user/synlogin', ['uid' => $uid]);
    }

    /**
     * 同步退出 - 返回需输出到页面的 HTML（服务端返回 HTML 非 JSON）
     */
    public function synLogout(): string
    {
        return $this->client->requestRaw('user/synlogout', []);
    }

    /**
     * 检查 Email
     * @return int 1 成功，-4 格式有误，-5 不允许，-6 已被注册
     */
    public function checkEmail(string $email): int
    {
        $ret = $this->client->request('user/check_email', ['email' => $email]);
        return (int) ($ret['ret'] ?? 0);
    }

    /**
     * 检查用户名
     * @return int 1 成功，-1 不合法，-2 包含不允许词语，-3 已存在
     */
    public function checkUsername(string $username): int
    {
        $ret = $this->client->request('user/check_username', ['username' => $username]);
        return (int) ($ret['ret'] ?? 0);
    }

    /**
     * 添加保护用户
     * @param string|string[] $username
     */
    public function addProtected($username, string $admin): int
    {
        $params = [
            'username' => is_array($username) ? $username : [$username],
            'admin' => $admin,
        ];
        $ret = $this->client->request('user/addprotected', $params);
        return (int) ($ret['ret'] ?? -1);
    }

    /**
     * 删除保护用户
     * @param string|string[] $username
     */
    public function deleteProtected($username): int
    {
        $params = ['username' => is_array($username) ? $username : [$username]];
        $ret = $this->client->request('user/deleteprotected', $params);
        return (int) ($ret['ret'] ?? -1);
    }

    /**
     * 获取受保护用户列表
     */
    public function getProtected(): array
    {
        $ret = $this->client->request('user/getprotected', []);
        return is_array($ret) ? $ret : [];
    }

    /**
     * 合并重名用户到 UCenter
     */
    public function merge(string $oldusername, string $newusername, int $uid, string $password, string $email): int
    {
        $ret = $this->client->request('user/merge', [
            'oldusername' => $oldusername,
            'newusername' => $newusername,
            'uid' => $uid,
            'password' => $password,
            'email' => $email,
        ]);
        return (int) ($ret['ret'] ?? 0);
    }

    /**
     * 移除重名用户记录
     */
    public function mergeRemove(string $username): void
    {
        $this->client->request('user/merge_remove', ['username' => $username]);
    }

    /**
     * 获取指定应用的指定用户积分
     */
    public function getCredit(int $appid, int $uid, int $credit): int
    {
        $ret = $this->client->request('user/getcredit', [
            'appid' => $appid,
            'uid' => $uid,
            'credit' => $credit,
        ]);
        return (int) ($ret['credit'] ?? $ret['ret'] ?? 0);
    }
}

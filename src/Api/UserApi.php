<?php

declare(strict_types=1);

namespace UCenter\Sdk\Api;

/**
 * 用户接口
 * @see document/html/user.htm
 */
class UserApi extends BaseApi
{
    /** 第三方登录类型：手机号 */
    public const LOGIN_TYPE_PHONE = 'phone';
    /** 第三方登录类型：微信 unionid */
    public const LOGIN_TYPE_WECHAT_UNIONID = 'wechat_unionid';
    /** 第三方登录类型：微博 openid */
    public const LOGIN_TYPE_WEIBO_OPENID = 'weibo_openid';
    /** 第三方登录类型：QQ unionid */
    public const LOGIN_TYPE_QQ_UNIONID = 'qq_unionid';

    /** 支持的第三方登录类型及其对应的用户扩展字段名 */
    private const LOGIN_TYPE_FIELD = [
        self::LOGIN_TYPE_PHONE => 'phone',
        self::LOGIN_TYPE_WECHAT_UNIONID => 'wechat_unionid',
        self::LOGIN_TYPE_WEIBO_OPENID => 'weibo_openid',
        self::LOGIN_TYPE_QQ_UNIONID => 'qq_union_id',
    ];

    /** 类型前缀，用于生成默认用户名 */
    private const LOGIN_TYPE_PREFIX = [
        self::LOGIN_TYPE_PHONE => 'phone_',
        self::LOGIN_TYPE_WECHAT_UNIONID => 'wechat_',
        self::LOGIN_TYPE_WEIBO_OPENID => 'weibo_',
        self::LOGIN_TYPE_QQ_UNIONID => 'qq_',
    ];

    /** 默认用户名最大长度（UCenter 常见限制） */
    private const USERNAME_MAX_LEN = 50;

    /** 默认邮箱域名（用于生成虚拟邮箱） */
    private const DEFAULT_EMAIL_DOMAIN = 'jiuzhoufeiyi.com';

    /** 系统管理员 UID，新用户注册后自动与此用户互为好友，站内信可由此身份发送 */
    public const SYSTEM_ADMIN_UID = 1;

    /**
     * 用户注册
     * 注册成功后自动与 uid=1 的管理员互为好友（仅 HTTP/混合模式生效，直连 DB 模式无好友接口则忽略）。
     * 若传入 bindType/bindIdentifier，注册成功后会写入绑定关系（并更新用户扩展字段），与「类型+标识登录」逻辑一致。
     *
     * @param string      $username       用户名
     * @param string      $password      密码
     * @param string      $email         邮箱
     * @param int         $questionid     安全提问
     * @param string      $answer        安全提问答案
     * @param string      $regip         注册 IP
     * @param string|null $bindType      可选。注册成功后写入绑定的类型：LOGIN_TYPE_PHONE | LOGIN_TYPE_WECHAT_UNIONID | LOGIN_TYPE_WEIBO_OPENID | LOGIN_TYPE_QQ_UNIONID
     * @param string|null $bindIdentifier 可选。与 bindType 成对使用，绑定的标识（手机号/unionid/openid）
     * @return int 大于 0 为用户 ID；负数见文档（-1 用户名不合法，-2 不允许词语，-3 用户名已存在，-4/-5/-6 Email 相关）
     */
    public function register(string $username, string $password, string $email, int $questionid = 0, string $answer = '', string $regip = '', ?string $bindType = null, ?string $bindIdentifier = null): int
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
        $uid = (int) ($ret['ret'] ?? 0);
        if ($uid > 0 && $uid !== self::SYSTEM_ADMIN_UID) {
            try {
                $this->client->friend()->add($uid, self::SYSTEM_ADMIN_UID, '');
                $this->client->friend()->add(self::SYSTEM_ADMIN_UID, $uid, '');
            } catch (\Throwable $e) {
                // 直连 DB 等无好友接口时忽略
            }
        }
        if ($uid > 0 && $bindType !== null && $bindIdentifier !== null && $bindType !== '' && $bindIdentifier !== '') {
            $field = self::LOGIN_TYPE_FIELD[$bindType] ?? null;
            if ($field !== null) {
                $this->updateProfile($username, [$field => $bindIdentifier], false);
                $store = $this->client->getBindingStore();
                if ($store !== null) {
                    $store->add($uid, $bindType, $bindIdentifier);
                }
            }
        }
        return $uid;
    }

    /**
     * 用户登录
     * - 传用户名+密码：login('username', 'password')，行为与原有一致。
     * - 传类型+标识：第一参数为 LOGIN_TYPE_PHONE | LOGIN_TYPE_WECHAT_UNIONID | LOGIN_TYPE_WEIBO_OPENID | LOGIN_TYPE_QQ_UNIONID，
     *   第二参数为标识（手机号/unionid/openid）。若启用绑定存储，会先检查绑定关系再决定用哪个账号登录，避免因注册方式不同导致登录失败；
     *   未注册则按类型+标识生成默认用户名/邮箱/密码并自动注册后再登录；$options 可传 email_domain、extra_profile。
     *
     * @param string $usernameOrType   用户名，或第三方登录类型常量
     * @param string $passwordOrIdentifier 密码，或第三方标识（手机号/unionid/openid）
     * @param int    $isuid            是否按 uid 登录（仅用户名+密码时有效）
     * @param bool   $checkques        是否验证安全提问
     * @param int    $questionid       安全提问索引
     * @param string $answer           安全提问答案
     * @param array  $options          类型+标识登录时有效：email_domain、extra_profile
     * @return array{status: int, username?: string, email?: string, access_token?: string} status>0 为 uid；若已设置 JwtToken 则含 access_token
     */
    public function login(string $usernameOrType, string $passwordOrIdentifier, int $isuid = 0, bool $checkques = false, int $questionid = 0, string $answer = '', array $options = []): array
    {
        if (isset(self::LOGIN_TYPE_FIELD[$usernameOrType])) {
            return $this->loginByTypeAndIdentifier($usernameOrType, $passwordOrIdentifier, $options);
        }

        $params = [
            'username' => $usernameOrType,
            'password' => $passwordOrIdentifier,
            'isuid' => $isuid,
            'checkques' => $checkques ? 1 : 0,
            'questionid' => $questionid,
            'answer' => $answer,
        ];
        $res = $this->client->request('user/login', $params);
        $data = $res['data'] ?? $res;
        if (isset($data['uid']) && !isset($data['status'])) {
            $data['status'] = (int) $data['uid'];
        }
        return $this->appendAccessTokenIfSuccess($data);
    }

    /**
     * 按「类型 + 标识」生成统一的默认用户名、邮箱、密码（用于第三方登录/自动注册）
     *
     * @param string|null $emailDomain 虚拟邮箱域名，null 则使用 uc.local
     * @return array{username: string, email: string, password: string}
     */
    public function generateCredentials(string $type, string $identifier, ?string $emailDomain = null): array
    {
        $prefix = self::LOGIN_TYPE_PREFIX[$type] ?? ($type . '_');
        $safe = preg_replace('/[^a-zA-Z0-9_]/', '_', $identifier);
        $rawUsername = $prefix . $safe;
        $username = md5(mb_substr($rawUsername, 0, self::USERNAME_MAX_LEN));

        $domain = $emailDomain !== null && $emailDomain !== '' ? $emailDomain : self::DEFAULT_EMAIL_DOMAIN;
        $email = substr($username,8,16) . '@' . $domain;
        if ($email === '@' || $domain === '') {
            $email = $username . '@' . self::DEFAULT_EMAIL_DOMAIN;
        }

        $password = substr(bin2hex(hash('sha256', $type . '|' . $identifier, true)), 0, 16);

        return ['username' => $username, 'email' => $email, 'password' => $password];
    }

    /**
     * 类型+标识登录（未注册则自动注册），供 login() 内部调用
     * 若启用绑定存储：先检查绑定关系（先查本地绑定表得 uid，再走 UCenter get_user），
     * 避免因注册方式不同导致同一标识对应不同账号而登录失败；否则走 UCenter login/register
     */
    private function loginByTypeAndIdentifier(string $type, string $identifier, array $options): array
    {
        $store = $this->client->getBindingStore();
        if ($store !== null) {
            $uid = $store->findUid($type, $identifier);
            if ($uid !== null) {
                $user = $this->getUser((string) $uid, true);
                if (!empty($user['uid'])) {
                    $data = [
                        'status' => (int) $user['uid'],
                        'uid' => (int) $user['uid'],
                        'username' => $user['username'] ?? '',
                        'email' => $user['email'] ?? '',
                    ];
                    return $this->appendAccessTokenIfSuccess($data);
                }
            }
        }

        $emailDomain = $options['email_domain'] ?? null;
        $extraProfile = $options['extra_profile'] ?? [];

        $cred = $this->generateCredentials($type, $identifier, $emailDomain);
        // print_r($cred);
        $res = $this->doLogin($cred['username'], $cred['password']);

        $status = (int) ($res['status'] ?? 0);
        if ($status > 0) {
            if ($store !== null) {
                $store->add($status, $type, $identifier);
            }
            return $this->appendAccessTokenIfSuccess($res);
        }

        if ($status === -1) {
            $uid = $this->register($cred['username'], $cred['password'], $cred['email']);
            if ($uid <= 0) {
                throw new \UCenter\Sdk\Exception\UCenterException(
                    '第三方自动注册失败，ret=' . $uid . '（可能用户名/邮箱已被占用）',
                    0,
                    null,
                    ['ret' => $uid]
                );
            }
            if ($cred['email'] !== '') {
                $user = $this->getUser((string) $uid, true);
                if (empty($user['email']) || trim((string) ($user['email'] ?? '')) === '') {
                    $this->edit($cred['username'], '', '', $cred['email'], true, 0, '', null, null, null, null, null, null, null, null, null, false);
                }
            }
            $fieldName = self::LOGIN_TYPE_FIELD[$type];
            $this->updateProfile($cred['username'], array_merge([$fieldName => $identifier], $extraProfile));
            if ($store !== null) {
                $store->add($uid, $type, $identifier);
            }
            $res = $this->doLogin($cred['username'], $cred['password']);
            return $this->appendAccessTokenIfSuccess($res);
        }

        if ($status === -2) {
            throw new \UCenter\Sdk\Exception\UCenterException(
                '该标识已绑定其他账号，无法使用统一生成的密码登录（请使用原账号密码或找回密码）',
                -2,
                null,
                $res
            );
        }

        throw new \UCenter\Sdk\Exception\UCenterException(
            '登录失败，status=' . $status,
            $status,
            null,
            $res
        );
    }

    /**
     * 登录成功时若已配置 JwtToken，则签发 access_token 并写入返回数组
     * @param array $data 登录接口返回的数据（含 status/uid/username/email）
     * @return array 原数据，成功且配置了 JWT 时增加 access_token 键
     */
    private function appendAccessTokenIfSuccess(array $data): array
    {
        $jwt = $this->client->getJwtToken();
        if ($jwt === null) {
            return $data;
        }
        $uid = (int) ($data['status'] ?? $data['uid'] ?? 0);
        if ($uid <= 0) {
            return $data;
        }
        $payload = [
            'sub' => (string) $uid,
            'username' => $data['username'] ?? '',
        ];
        $data['access_token'] = $jwt->issue($payload);
        return $data;
    }

    /** 实际请求 user/login 接口（避免 login 递归） */
    private function doLogin(string $username, string $password): array
    {
        $res = $this->client->request('user/login', [
            'username' => $username,
            'password' => $password,
            'isuid' => 0,
            'checkques' => 0,
            'questionid' => 0,
            'answer' => '',
        ]);
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
     * 将手机号/微信/微博/QQ 绑定到当前账号；绑定关系会写入绑定存储（若启用），
     * 绑定后可用该标识通过 login(类型, 标识) 登录同一账号，与登录时「先检查绑定」逻辑一致。
     * （需后端支持：按标识查用户或绑定时同步创建同 uid 的标识账号，否则仅写入扩展字段）
     *
     * @param string $username   用户名或 uid 字符串
     * @param string $type       LOGIN_TYPE_PHONE | LOGIN_TYPE_WECHAT_UNIONID | LOGIN_TYPE_WEIBO_OPENID | LOGIN_TYPE_QQ_UNIONID
     * @param string $identifier 要绑定的标识（手机号、unionid、openid）
     * @param bool   $isuid      是否按 uid 查找用户
     * @return int 1 成功，0 无修改，负数见文档
     */
    public function bind(string $username, string $type, string $identifier, bool $isuid = false): int
    {
        $field = self::LOGIN_TYPE_FIELD[$type] ?? null;
        if ($field === null) {
            throw new \InvalidArgumentException('不支持的绑定类型: ' . $type . '，支持: phone, wechat_unionid, weibo_openid, qq_unionid');
        }
        $store = $this->client->getBindingStore();
        $ret = $this->updateProfile($username, [$field => $identifier], $isuid);
        $localWritten = false;
        if ($store !== null) {
            $user = $this->getUser($username, $isuid);
            if (!empty($user['uid'])) {
                $store->add((int) $user['uid'], $type, $identifier);
                $localWritten = true;
            }
        }
        if ($ret > 0) {
            return $ret;
        }
        if ($localWritten) {
            return 1;
        }
        return $ret;
    }

    /**
     * 解除当前账号对某种登录方式的绑定：先从绑定存储中移除该类型的绑定关系，再清空 UCenter 对应扩展字段。
     * 与注册/绑定一样，解绑也会对绑定关系做操作，保证登录时「先检查绑定」结果一致。
     *
     * @param string $username 用户名或 uid 字符串
     * @param string $type      LOGIN_TYPE_PHONE | LOGIN_TYPE_WECHAT_UNIONID | LOGIN_TYPE_WEIBO_OPENID | LOGIN_TYPE_QQ_UNIONID
     * @param bool   $isuid     是否按 uid 查找用户
     * @return int 1 成功，0 无修改，负数见文档
     */
    public function unbind(string $username, string $type, bool $isuid = false): int
    {
        $field = self::LOGIN_TYPE_FIELD[$type] ?? null;
        if ($field === null) {
            throw new \InvalidArgumentException('不支持的绑定类型: ' . $type . '，支持: phone, wechat_unionid, weibo_openid, qq_unionid');
        }
        $store = $this->client->getBindingStore();
        $localRemoved = false;
        if ($store !== null) {
            $user = $this->getUser($username, $isuid);
            if (!empty($user['uid'])) {
                $store->remove((int) $user['uid'], $type);
                $localRemoved = true;
            }
        }
        $ret = $this->updateProfile($username, [$field => ''], $isuid);
        if ($ret > 0) {
            return $ret;
        }
        if ($localRemoved) {
            return 1;
        }
        return $ret;
    }

    /**
     * 获取当前账号已绑定的登录方式（手机号、微信、微博、QQ）
     *
     * @param string $username 用户名或 uid 字符串
     * @param bool   $isuid    是否按 uid 查找用户
     * @return array{phone: string, wechat_unionid: string, weibo_openid: string, qq_union_id: string} 未绑定的为空字符串
     */
    public function getBindings(string $username, bool $isuid = false): array
    {
        $store = $this->client->getBindingStore();
        if ($store !== null) {
            $user = $this->getUser($username, $isuid);
            if (!empty($user['uid'])) {
                return $store->getByUid((int) $user['uid']);
            }
            return ['phone' => '', 'wechat_unionid' => '', 'weibo_openid' => '', 'qq_union_id' => ''];
        }
        $user = $this->getUser($username, $isuid);
        return [
            'phone' => (string) ($user['phone'] ?? ''),
            'wechat_unionid' => (string) ($user['wechat_unionid'] ?? ''),
            'weibo_openid' => (string) ($user['weibo_openid'] ?? ''),
            'qq_union_id' => (string) ($user['qq_union_id'] ?? ''),
        ];
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

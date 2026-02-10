# 数据库建表（直连 / 混合模式）

## 直连数据库模式

后端**不暴露接口**，业务直接通过 SDK 操作数据库即可。

### 1. 建表

创建数据库后执行：

```bash
mysql -u root -p 数据库名 < server/schema.sql
```

或登录 MySQL 后 `source server/schema.sql`。

### 2. 配置与使用

- **环境变量**：`UC_DB_HOST`、`UC_DB_PORT`、`UC_DB_NAME`、`UC_DB_USER`、`UC_DB_PASS`
- 业务中：`$client = UCenterClient::fromDatabase($config);`

直连模式仅支持用户相关：`register`、`login`、`get_user`、`edit`。不支持 token、synLogin、pm、friend、credit、tag 等需远程接口的能力。

---

## 混合模式（主逻辑 UCenter，绑定关系存本地）

主逻辑（注册/登录/获取用户/编辑）走 **UCenter 接口**；仅**绑定关系**（手机/微信/微博/QQ ↔ 用户）存本地表 `uc_bindings`。**不需要** `uc_users` 表。

1. 建表：执行 `server/schema_bindings_only.sql`（仅建 `uc_bindings`）或只执行 `schema.sql` 里的 `uc_bindings` 部分。
2. 初始化：`UCenterClient::withBindingStore($baseUrl, $appId, $secret, $dbConfig)`。
3. bind/unbind/getBindings 及「类型+标识」登录会读写本地 `uc_bindings`，其余请求走 UCenter。

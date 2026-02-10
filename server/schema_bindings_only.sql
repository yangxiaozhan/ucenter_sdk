-- 仅混合模式（withBindingStore）用：主逻辑走 UCenter，本地只存绑定关系，无需 uc_users
-- 执行: mysql -u root -p 数据库名 < server/schema_bindings_only.sql

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS `uc_bindings` (
  `uid` int unsigned NOT NULL COMMENT '用户 ID（与 UCenter 一致）',
  `type` varchar(32) NOT NULL COMMENT 'phone|wechat_unionid|weibo_openid|qq_union_id',
  `identifier` varchar(128) NOT NULL COMMENT '手机号/unionid/openid',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`uid`, `type`),
  UNIQUE KEY `uk_type_identifier` (`type`, `identifier`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='绑定关系（混合模式）';

-- 说明：
-- - 直连模式（fromDatabase）：需要 uc_users，可选 uc_bindings
-- - 混合模式（withBindingStore，主逻辑 UCenter）：只需 uc_bindings，无需 uc_users
-- 仅混合模式时可只执行下方 uc_bindings 部分，或使用 server/schema_bindings_only.sql
-- MySQL 5.7+ / 8.0

SET NAMES utf8mb4;

-- ========== 以下为直连模式用（fromDatabase 时需要）==========
CREATE TABLE IF NOT EXISTS `uc_users` (
  `uid` int unsigned NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL COMMENT '用户名',
  `password` varchar(255) NOT NULL COMMENT '密码哈希',
  `email` varchar(100) NOT NULL,
  `regip` varchar(45) DEFAULT NULL,
  `regdate` datetime DEFAULT CURRENT_TIMESTAMP,
  `phone` varchar(20) DEFAULT NULL COMMENT '手机号（绑定）',
  `wechat_unionid` varchar(64) DEFAULT NULL COMMENT '微信 unionid（绑定）',
  `wechat_openid` varchar(64) DEFAULT NULL,
  `weibo_openid` varchar(64) DEFAULT NULL COMMENT '微博 openid（绑定）',
  `qq_union_id` varchar(64) DEFAULT NULL COMMENT 'QQ unionid（绑定）',
  `nickname` varchar(64) DEFAULT NULL,
  `avatar` varchar(512) DEFAULT NULL COMMENT '头像 URL',
  `douyin_openid` varchar(64) DEFAULT NULL,
  `is_member` tinyint unsigned DEFAULT 0,
  PRIMARY KEY (`uid`),
  UNIQUE KEY `uk_username` (`username`),
  UNIQUE KEY `uk_email` (`email`),
  KEY `idx_phone` (`phone`),
  KEY `idx_wechat_unionid` (`wechat_unionid`),
  KEY `idx_weibo_openid` (`weibo_openid`),
  KEY `idx_qq_union_id` (`qq_union_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='用户表（直连模式）';

-- ========== 以下为混合模式用（withBindingStore 时只需此表）==========
CREATE TABLE IF NOT EXISTS `uc_bindings` (
  `uid` int unsigned NOT NULL COMMENT '用户 ID（与 UCenter 一致）',
  `type` varchar(32) NOT NULL COMMENT 'phone|wechat_unionid|weibo_openid|qq_union_id',
  `identifier` varchar(128) NOT NULL COMMENT '手机号/unionid/openid',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`uid`, `type`),
  UNIQUE KEY `uk_type_identifier` (`type`, `identifier`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='绑定关系（混合模式）';

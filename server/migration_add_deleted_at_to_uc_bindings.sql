-- 为 uc_bindings 增加软删除字段（解绑不再 DELETE，仅设置 deleted_at）
-- 已有表执行: mysql -u root -p 数据库名 < server/migration_add_deleted_at_to_uc_bindings.sql

SET NAMES utf8mb4;

ALTER TABLE `uc_bindings`
  ADD COLUMN `deleted_at` datetime DEFAULT NULL COMMENT '解绑时为软删除时间，NULL 表示有效' AFTER `created_at`;

<?php

/**
 * 直连数据库时仅需这一处配置（复制为 config.php 或直接在业务中写数组即可）
 * 无需 baseUrl、appId、secret，不暴露任何 HTTP 接口
 */
return [
    'host'     => getenv('UC_DB_HOST') ?: '127.0.0.1',
    'port'     => (int) (getenv('UC_DB_PORT') ?: 3306),
    'dbname'   => getenv('UC_DB_NAME') ?: 'ucenter',
    'username' => getenv('UC_DB_USER') ?: 'root',
    'password' => getenv('UC_DB_PASS') ?: '',
    'charset'  => 'utf8mb4',
];

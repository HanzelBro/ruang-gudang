<?php
declare(strict_types=1);

return [
    'url' => getenv('DATABASE_URL_UNPOOLED') ?: (getenv('DATABASE_URL') ?: ''),
    'host' => getenv('RG_DB_HOST') ?: '127.0.0.1',
    'port' => getenv('RG_DB_PORT') ?: '3306',
    'database' => getenv('RG_DB_NAME') ?: 'db_logistik_gudang',
    'username' => getenv('RG_DB_USER') ?: 'root',
    'password' => getenv('RG_DB_PASSWORD') ?: '',
    'timezone' => 'Asia/Jakarta',
];

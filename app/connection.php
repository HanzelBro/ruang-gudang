<?php
declare(strict_types=1);

function postgres(): bool
{
    global $config;
    return $config['url'] !== '';
}

function connectDatabase(): PDO
{
    global $config;
    $user = $config['username'];
    $password = $config['password'];
    if (postgres()) {
        $url = parse_url($config['url']);
        if (!$url || !isset($url['host'], $url['path'], $url['user'])) throw new RuntimeException('DATABASE_URL tidak valid.');
        $dsn = sprintf('pgsql:host=%s;port=%d;dbname=%s;sslmode=require;connect_timeout=10', $url['host'], $url['port'] ?? 5432, rawurldecode(ltrim($url['path'], '/')));
        $endpoint = explode('.', $url['host'])[0];
        if (preg_match('/^ep-[a-z0-9-]+$/D',$endpoint)) $dsn .= ";options='endpoint=".preg_replace('/-pooler$/','',$endpoint)."'";
        $user = rawurldecode($url['user']);
        $password = rawurldecode($url['pass'] ?? '');
    } else {
        $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $config['host'], $config['port'], $config['database']);
    }
    $pdo = new PDO($dsn, $user, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::ATTR_TIMEOUT => 10,
    ]);
    $pdo->exec(postgres() ? "SET TIME ZONE 'Asia/Jakarta'" : "SET time_zone = '+07:00'");
    $pdo->exec(postgres() ? 'SET SESSION CHARACTERISTICS AS TRANSACTION ISOLATION LEVEL READ COMMITTED' : 'SET SESSION TRANSACTION ISOLATION LEVEL READ COMMITTED');
    return $pdo;
}

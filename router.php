<?php
declare(strict_types=1);
$path = rawurldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/');
if (str_starts_with($path, '/assets/') && !str_contains($path, '..') && preg_match('/\.(css|js|svg|woff2)$/D',$path) && is_file(__DIR__.$path)) return false;
if ($path === '/' || $path === '/index.php') { require __DIR__.'/index.php'; return true; }
http_response_code(404);
header('Content-Type: text/plain; charset=utf-8');
echo 'Halaman tidak ditemukan.';

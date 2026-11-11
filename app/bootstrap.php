<?php
declare(strict_types=1);

$config = require dirname(__DIR__) . '/config.php';
date_default_timezone_set($config['timezone']);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
require __DIR__.'/connection.php';
if (postgres()) {
    require __DIR__.'/session.php';
    try { session_set_save_handler(new DatabaseSession(connectDatabase()), true); }
    catch (PDOException $ex) { error_log('Database session unavailable.'); session_save_path(sys_get_temp_dir()); }
}
session_name('ruang_gudang');
session_start(['cookie_httponly' => true, 'cookie_samesite' => 'Lax', 'cookie_secure' => (bool)getenv('VERCEL'), 'use_strict_mode' => true]);
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: same-origin');
header("Content-Security-Policy: default-src 'self'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; script-src 'self'; base-uri 'self'; form-action 'self'; frame-ancestors 'self'");
header('Cache-Control: no-store');
$_SESSION['csrf'] ??= bin2hex(random_bytes(32));

function db(): PDO
{
    global $config;
    static $pdo;
    if (!$pdo) {
        $pdo = connectDatabase();
    }
    return $pdo;
}

function query(string $sql, array $params = []): PDOStatement
{
    if (postgres()) {
        $sql = str_replace("GROUP_CONCAT(r.kode_rak SEPARATOR ', ')", "STRING_AGG(r.kode_rak, ', ')", $sql);
        $sql = str_replace('LOCK IN SHARE MODE', 'FOR SHARE', $sql);
        $sql = preg_replace('/\bLIKE\b/', 'ILIKE', $sql);
    }
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt;
}
function rows(string $sql, array $params = []): array { return query($sql, $params)->fetchAll(); }
function row(string $sql, array $params = []): ?array { return query($sql, $params)->fetch() ?: null; }
function scalar(string $sql, array $params = []): mixed { return query($sql, $params)->fetchColumn(); }
function e(mixed $value): string { return htmlspecialchars((string)($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function url(string $page = 'dashboard', array $args = []): string { return 'index.php?' . http_build_query(['page' => $page] + $args); }
function redirect(string $page, array $args = []): never { header('Location: ' . url($page, $args), true, 303); exit; }
function num(mixed $n, int $places = 0): string { return number_format((float)$n, $places, ',', '.'); }
function kg(mixed $n): string { return num($n, 2) . ' kg'; }
function stamp(?string $v): string { return $v ? date('d M Y · H:i', strtotime($v)) : '—'; }
function input(string $name, string $default = ''): string { return isset($_POST[$name]) && is_string($_POST[$name]) ? trim($_POST[$name]) : $default; }
function param(string $name, string $default = ''): string { return isset($_GET[$name]) && is_string($_GET[$name]) ? trim($_GET[$name]) : $default; }
function csrf(): string { return '<input type="hidden" name="csrf" value="' . e($_SESSION['csrf']) . '">'; }
function fail(string $message): never { throw new DomainException($message); }
function required(string $key, string $label, int $max): string
{
    $v = input($key);
    if ($v === '' || mb_strlen($v) > $max) fail("$label wajib diisi, maksimal $max karakter.");
    return $v;
}
function cents(string $n): int
{
    if (!preg_match('/^\d{1,4}(?:\.\d{1,2})?$/D', $n)) fail('Gunakan angka positif dengan maksimal dua angka desimal (maksimal 9.999,99).');
    [$whole, $fraction] = array_pad(explode('.', $n), 2, '');
    return ((int)$whole * 100) + (int)str_pad($fraction, 2, '0');
}
function positiveDecimal(string $key): string
{
    $n = str_replace(',', '.', input($key));
    if (cents($n) <= 0) fail('Berat dan kapasitas harus lebih besar dari nol.');
    return $n;
}
function transaction(callable $action): mixed
{
    db()->beginTransaction();
    try { $result = $action(); db()->commit(); return $result; }
    catch (Throwable $ex) { if (db()->inTransaction()) db()->rollBack(); throw $ex; }
}
function categories(): array
{
    return array_values(array_unique(array_merge(['Dokumen', 'Elektronik', 'Pakaian', 'Makanan Kering', 'Pecah Belah', 'Kosmetik'], array_column(rows('SELECT DISTINCT kategori FROM paket ORDER BY kategori'), 'kategori'))));
}
function packageRows(string $where = '1=1', array $params = []): array
{
    return rows("SELECT p.*, (SELECT GROUP_CONCAT(r.kode_rak SEPARATOR ', ') FROM alokasi_rak a JOIN rak_gudang r USING(id_rak) WHERE a.no_resi=p.no_resi AND a.status_alokasi='Aktif') AS lokasi FROM paket p WHERE $where ORDER BY p.no_resi DESC", $params);
}
function rackRows(): array
{
    return rows("SELECT r.*, COUNT(a.id_alokasi) AS jumlah_paket, COALESCE(SUM(p.berat_kg),0) AS berat_aktual FROM rak_gudang r LEFT JOIN alokasi_rak a ON a.id_rak=r.id_rak AND a.status_alokasi='Aktif' LEFT JOIN paket p ON p.no_resi=a.no_resi GROUP BY r.id_rak ORDER BY r.kode_rak");
}
function reportRange(): array
{
    $start = param('dari'); $end = param('sampai');
    foreach ([$start, $end] as $value) {
        if ($value !== '') {
            $d = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
            if (!$d || $d->format('Y-m-d') !== $value) fail('Tanggal laporan tidak valid.');
        }
    }
    if ($start && $end && $start > $end) fail('Tanggal mulai harus sebelum tanggal akhir.');
    return [$start, $end];
}
function periodWhere(string $column, array $range): array
{
    [$start, $end] = $range; $where = '1=1'; $bind = [];
    if ($start) { $where .= " AND $column >= ?"; $bind[] = $start . ' 00:00:00'; }
    if ($end) { $where .= " AND $column < ?"; $bind[] = (new DateTimeImmutable($end))->modify('+1 day')->format('Y-m-d'); }
    return [$where, $bind];
}
function reportEvents(array $range): array
{
    [$wi, $bi] = periodWhere('a.waktu_masuk', $range);
    [$wo, $bo] = periodWhere('g.waktu_kirim', $range);
    return rows("SELECT 'Inbound' AS jenis,a.waktu_masuk AS waktu,p.no_resi,p.nama_penerima,p.kota_tujuan,p.kategori,p.berat_kg,r.kode_rak AS lokasi,t.nama_petugas,'' AS nama_kurir,'' AS no_kendaraan,a.status_alokasi AS status FROM alokasi_rak a JOIN paket p USING(no_resi) JOIN rak_gudang r USING(id_rak) JOIN petugas t ON t.id_petugas=a.id_petugas WHERE $wi UNION ALL SELECT 'Outbound',g.waktu_kirim,p.no_resi,p.nama_penerima,p.kota_tujuan,p.kategori,p.berat_kg,'—',t.nama_petugas,g.nama_kurir,g.no_kendaraan,g.status_kirim FROM pengiriman g JOIN paket p USING(no_resi) JOIN petugas t ON t.id_petugas=g.id_petugas WHERE $wo ORDER BY waktu DESC", [...$bi, ...$bo]);
}

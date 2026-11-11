<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$config = require dirname(__DIR__).'/config.php';
require dirname(__DIR__).'/app/connection.php';
if (!postgres()) throw new RuntimeException('DATABASE_URL wajib diatur di environment.');
$tables = ['petugas'=>'id_petugas','rak_gudang'=>'id_rak','paket'=>'no_resi','alokasi_rak'=>'id_alokasi','pengiriman'=>'id_pengiriman'];
$source = new PDO(sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',$config['host'],$config['port'],$config['database']),$config['username'],$config['password'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
$source->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
$source->beginTransaction();
$data=[];
foreach ($tables as $table=>$pk) $data[$table]=$source->query("SELECT * FROM $table ORDER BY $pk")->fetchAll();
$source->commit();
$backup=$argv[1]??'';
if (!$backup || is_file($backup)) throw new RuntimeException('Berikan path backup baru di luar direktori publik.');
if (file_put_contents($backup,json_encode($data,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR))===false) throw new RuntimeException('Backup gagal.');
$target=connectDatabase();
$target->beginTransaction();
try {
    if ($target->query("SELECT to_regclass('public.paket')")->fetchColumn()) throw new RuntimeException('Database tujuan sudah berisi tabel. Migrasi dibatalkan.');
    $target->exec(file_get_contents(dirname(__DIR__).'/database/postgres.sql'));
    foreach ($tables as $table=>$pk) {
        foreach ($data[$table] as $record) {
            $columns=implode(',',array_keys($record));
            $bind=implode(',',array_fill(0,count($record),'?'));
            $target->prepare("INSERT INTO $table($columns) VALUES($bind)")->execute(array_values($record));
        }
        if ($pk!=='no_resi') $target->query("SELECT setval(pg_get_serial_sequence('$table','$pk'),GREATEST(COALESCE((SELECT MAX($pk) FROM $table),0),1),(SELECT COUNT(*)>0 FROM $table))");
        $actual=$target->query("SELECT * FROM $table ORDER BY $pk")->fetchAll();
        if ($actual!=$data[$table]) throw new RuntimeException("Data $table tidak sesuai sumber.");
        echo "$table: ".count($actual)." baris sesuai sumber\n";
    }
    $bad=$target->query("SELECT COUNT(*) FROM rak_gudang r WHERE kapasitas_terpakai_kg<>(SELECT COALESCE(SUM(p.berat_kg),0) FROM alokasi_rak a JOIN paket p USING(no_resi) WHERE a.id_rak=r.id_rak AND a.status_alokasi='Aktif') OR kapasitas_terpakai_kg>kapasitas_maks_kg")->fetchColumn();
    if ($bad) throw new RuntimeException('Kapasitas sumber tidak konsisten; migrasi dibatalkan.');
    $target->exec(file_get_contents(dirname(__DIR__).'/database/postgres-triggers.sql'));
    $target->commit();
    echo "Migrasi selesai. Data MySQL sumber tidak diubah.\n";
} catch (Throwable $ex) {
    $target->rollBack();
    throw $ex;
}

<?php
declare(strict_types=1);

function lockedPackage(string $resi): array
{
    return row('SELECT * FROM paket WHERE no_resi=? FOR UPDATE', [$resi]) ?? fail('Paket tidak ditemukan.');
}
function activeAllocations(string $resi): array
{
    return rows("SELECT * FROM alokasi_rak WHERE no_resi=? AND status_alokasi='Aktif'", [$resi]);
}
function requireNoShipment(string $resi): void
{
    if (scalar('SELECT COUNT(*) FROM pengiriman WHERE no_resi=?', [$resi])) fail('Paket ini sudah mempunyai catatan pengiriman.');
}
function checkedRack(int $id): array
{
    $rack = row('SELECT * FROM rak_gudang WHERE id_rak=? FOR UPDATE', [$id]) ?? fail('Rak tidak ditemukan.');
    $actual = scalar("SELECT COALESCE(SUM(p.berat_kg),0) FROM alokasi_rak a JOIN paket p USING(no_resi) WHERE a.id_rak=? AND a.status_alokasi='Aktif'", [$id]);
    if (cents((string)$actual) !== cents($rack['kapasitas_terpakai_kg']) || cents($rack['kapasitas_terpakai_kg']) > cents($rack['kapasitas_maks_kg'])) fail('Kapasitas tercatat pada ' . $rack['kode_rak'] . ' tidak sesuai dengan alokasi aktif. Periksa data rak sebelum melanjutkan.');
    $bad = scalar("SELECT COUNT(*) FROM alokasi_rak a JOIN paket p USING(no_resi) WHERE a.id_rak=? AND a.status_alokasi='Aktif' AND (p.status_paket NOT IN ('Tersimpan','Siap Kirim') OR EXISTS(SELECT 1 FROM pengiriman g WHERE g.no_resi=a.no_resi) OR (SELECT COUNT(*) FROM alokasi_rak b WHERE b.no_resi=a.no_resi AND b.status_alokasi='Aktif')<>1)", [$id]);
    if ($bad) fail('Ada alokasi tidak konsisten pada ' . $rack['kode_rak'] . '. Periksa riwayat paket terlebih dahulu.');
    return $rack;
}
function requireSpace(array $rack, string $weight): void
{
    if (cents($rack['kapasitas_terpakai_kg']) + cents($weight) > cents($rack['kapasitas_maks_kg'])) fail('Kapasitas ' . $rack['kode_rak'] . ' tidak mencukupi. Pilih rak lain.');
}
function requireStaff(string $role): int
{
    $id = (int)input('id_petugas');
    $staff = row('SELECT * FROM petugas WHERE id_petugas=? LOCK IN SHARE MODE', [$id]);
    if (!$staff || !in_array($staff['role_petugas'], ['Admin', $role], true)) fail('Pilih petugas dengan peran yang sesuai.');
    return $id;
}
function requireTrigger(string $name): void
{
    if (postgres()) {
        if (!scalar('SELECT COUNT(*) FROM information_schema.triggers WHERE trigger_schema=current_schema() AND trigger_name=?', [$name])) fail('Otomatisasi database belum tersedia. Periksa trigger sebelum mencatat transaksi.');
        return;
    }
    if (!scalar('SELECT COUNT(*) FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA=DATABASE() AND TRIGGER_NAME=?', [$name])) fail('Otomatisasi database belum tersedia. Periksa trigger sebelum mencatat transaksi.');
}
function operate(string $action): string
{
    return transaction(function () use ($action): string {
        switch ($action) {
            case 'paket_save':
                $editing = input('original') !== '';
                $resi = $editing ? input('original') : required('no_resi', 'Nomor resi', 30);
                $p = $editing ? lockedPackage($resi) : null;
                $data = [required('nama_pengirim','Nama pengirim',100), required('nama_penerima','Nama penerima',100), required('kota_tujuan','Kota tujuan',50), positiveDecimal('berat_kg'), required('kategori','Kategori',50)];
                if (!in_array($data[4], categories(), true)) fail('Kategori paket tidak tersedia.');
                if ($editing) {
                    $history = scalar('SELECT (SELECT COUNT(*) FROM alokasi_rak WHERE no_resi=?) + (SELECT COUNT(*) FROM pengiriman WHERE no_resi=?)', [$resi,$resi]);
                    if ($history && cents($data[3]) !== cents($p['berat_kg'])) fail('Berat paket tidak dapat diubah setelah memiliki riwayat transaksi.');
                    query('UPDATE paket SET nama_pengirim=?,nama_penerima=?,kota_tujuan=?,berat_kg=?,kategori=? WHERE no_resi=?', [...$data,$resi]);
                } else {
                    query("INSERT INTO paket(no_resi,nama_pengirim,nama_penerima,kota_tujuan,berat_kg,kategori,status_paket) VALUES(?,?,?,?,?,?,'Diterima')", [$resi,...$data]);
                }
                return $editing ? 'Perubahan paket berhasil disimpan.' : "Paket $resi berhasil diterima. Lanjutkan ke penempatan rak.";
            case 'paket_delete':
                $p = lockedPackage(input('no_resi'));
                if ($p['status_paket'] !== 'Diterima') fail('Hanya paket baru tanpa riwayat yang dapat dihapus.');
                query('DELETE FROM paket WHERE no_resi=?', [$p['no_resi']]);
                return 'Paket berhasil dihapus.';
            case 'rak_save':
                $id = (int)input('id_rak');
                $code = required('kode_rak','Kode rak',20); $zone = required('zona','Zona',20); $max = positiveDecimal('kapasitas_maks_kg');
                if ($id) {
                    $rack = checkedRack($id);
                    if (cents($max) < cents($rack['kapasitas_terpakai_kg'])) fail('Kapasitas maksimal tidak boleh lebih kecil dari beban terpakai.');
                    query('UPDATE rak_gudang SET kode_rak=?,zona=?,kapasitas_maks_kg=? WHERE id_rak=?', [$code,$zone,$max,$id]);
                } else query('INSERT INTO rak_gudang(kode_rak,zona,kapasitas_maks_kg) VALUES(?,?,?)', [$code,$zone,$max]);
                return 'Data rak berhasil disimpan.';
            case 'rak_delete':
                $rack = checkedRack((int)input('id_rak'));
                query('DELETE FROM rak_gudang WHERE id_rak=?', [$rack['id_rak']]);
                return 'Rak berhasil dihapus.';
            case 'petugas_save':
                $id=(int)input('id_petugas'); $name=required('nama_petugas','Nama petugas',100); $phone=required('no_telepon','Nomor telepon',20); $role=required('role_petugas','Peran',30);
                if (!in_array($role,['Admin','Inbound Checker','Outbound Sorter'],true)) fail('Peran petugas tidak valid.');
                if (!preg_match('/^[+0-9 ()-]{6,20}$/D',$phone)) fail('Format nomor telepon tidak valid.');
                if ($id) {
                    if (!row('SELECT id_petugas FROM petugas WHERE id_petugas=? FOR UPDATE',[$id])) fail('Petugas tidak ditemukan.');
                    query('UPDATE petugas SET nama_petugas=?,role_petugas=?,no_telepon=? WHERE id_petugas=?',[$name,$role,$phone,$id]);
                } else query('INSERT INTO petugas(nama_petugas,role_petugas,no_telepon) VALUES(?,?,?)',[$name,$role,$phone]);
                return 'Data petugas berhasil disimpan.';
            case 'petugas_delete':
                query('DELETE FROM petugas WHERE id_petugas=?',[(int)input('id_petugas')]);
                return 'Petugas berhasil dihapus.';
            case 'alokasi':
            case 'pindah':
                requireTrigger('trg_after_alokasi_rak_insert');
                $p=lockedPackage(input('no_resi')); requireNoShipment($p['no_resi']);
                $active=activeAllocations($p['no_resi']); $dest=(int)input('id_rak');
                if ($action==='alokasi' && ($p['status_paket']!=='Diterima' || count($active)!==0)) fail('Paket harus berstatus Diterima dan belum dialokasikan.');
                if ($action==='pindah' && ($p['status_paket']!=='Tersimpan' || count($active)!==1)) fail('Pemindahan memerlukan paket Tersimpan dengan tepat satu alokasi aktif.');
                $old=$active[0] ?? null;
                if ($old && (int)$old['id_rak']===$dest) fail('Rak tujuan harus berbeda dari rak asal.');
                $ids=array_unique([$dest,...($old ? [(int)$old['id_rak']] : [])]); sort($ids); $racks=[];
                foreach ($ids as $id) $racks[$id]=checkedRack($id);
                requireSpace($racks[$dest],$p['berat_kg']);
                $staff=requireStaff('Inbound Checker');
                if ($old) {
                    query("UPDATE alokasi_rak SET status_alokasi='Selesai' WHERE id_alokasi=?",[$old['id_alokasi']]);
                    query('UPDATE rak_gudang SET kapasitas_terpakai_kg=kapasitas_terpakai_kg-? WHERE id_rak=?',[$p['berat_kg'],$old['id_rak']]);
                }
                query("INSERT INTO alokasi_rak(no_resi,id_rak,id_petugas,waktu_masuk,status_alokasi) VALUES(?,?,?,?,'Aktif')",[$p['no_resi'],$dest,$staff,date('Y-m-d H:i:s')]);
                return $old ? 'Paket berhasil dipindahkan. Kapasitas kedua rak sudah diperbarui.' : 'Paket berhasil ditempatkan. Kapasitas rak sudah diperbarui.';
            case 'siap':
                $p=lockedPackage(input('no_resi')); requireNoShipment($p['no_resi']); $active=activeAllocations($p['no_resi']);
                if ($p['status_paket']!=='Tersimpan' || count($active)!==1) fail('Paket harus Tersimpan dengan tepat satu alokasi aktif.');
                checkedRack((int)$active[0]['id_rak']);
                query("UPDATE paket SET status_paket='Siap Kirim' WHERE no_resi=?",[$p['no_resi']]);
                return 'Paket ditandai siap kirim.';
            case 'kirim':
                requireTrigger('trg_after_pengiriman_insert');
                $p=lockedPackage(input('no_resi')); requireNoShipment($p['no_resi']); $active=activeAllocations($p['no_resi']);
                if ($p['status_paket']!=='Siap Kirim' || count($active)!==1) fail('Pengiriman memerlukan paket Siap Kirim dengan tepat satu alokasi aktif.');
                checkedRack((int)$active[0]['id_rak']); $staff=requireStaff('Outbound Sorter');
                $courier=required('nama_kurir','Nama kurir',100); $vehicle=required('no_kendaraan','Nomor kendaraan',20);
                $raw=input('waktu_kirim'); $date=DateTimeImmutable::createFromFormat('!Y-m-d\TH:i',$raw);
                if (!$date || $date->format('Y-m-d\TH:i')!==$raw) fail('Waktu keberangkatan tidak valid.');
                if ($date->format('Y-m-d H:i:s') < substr($active[0]['waktu_masuk'],0,16).':00') fail('Waktu pengiriman tidak boleh mendahului penempatan terakhir.');
                query("INSERT INTO pengiriman(no_resi,id_petugas,nama_kurir,no_kendaraan,waktu_kirim,status_kirim) VALUES(?,?,?,?,?,'Dalam Perjalanan')",[$p['no_resi'],$staff,$courier,$vehicle,$date->format('Y-m-d H:i:s')]);
                return 'Pengiriman berhasil dicatat. Paket sudah keluar dari rak.';
            case 'tiba':
                $p=lockedPackage(input('no_resi'));
                $shipment=row('SELECT * FROM pengiriman WHERE no_resi=? FOR UPDATE',[$p['no_resi']]);
                if (!$shipment || $p['status_paket']!=='Terkirim' || activeAllocations($p['no_resi'])) fail('Data pengiriman tidak konsisten. Periksa riwayat paket.');
                if ($shipment['status_kirim']!=='Dalam Perjalanan') fail('Paket sudah ditandai tiba di tujuan.');
                query("UPDATE pengiriman SET status_kirim='Terkirim' WHERE id_pengiriman=?",[$shipment['id_pengiriman']]);
                return 'Paket berhasil ditandai tiba di tujuan.';
            default: fail('Tindakan tidak tersedia.');
        }
    });
}

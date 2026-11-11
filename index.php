<?php
declare(strict_types=1);
require __DIR__.'/app/bootstrap.php';
require __DIR__.'/app/operations.php';
require __DIR__.'/app/ui.php';
$pages=['dashboard'=>'Ringkasan','paket'=>'Paket','inbound'=>'Penempatan','outbound'=>'Pengiriman','rak'=>'Rak Gudang','petugas'=>'Petugas','laporan'=>'Laporan'];
$page=param('page','dashboard');
if (!isset($pages[$page])) { http_response_code(404); $page='not-found'; }

if ($_SERVER['REQUEST_METHOD']==='POST') {
    $return=input('return','dashboard'); if (!isset($pages[$return])) $return='dashboard';
    try {
        if (!hash_equals($_SESSION['csrf'],input('csrf'))) fail('Sesi formulir telah berubah. Muat ulang halaman lalu coba kembali.');
        $message=operate(input('action'));
        $_SESSION['flash']=['success',$message];
    } catch (DomainException $ex) { $_SESSION['flash']=['error',$ex->getMessage()]; $_SESSION['old_input']=$_POST; }
    catch (PDOException $ex) {
        error_log($ex->getMessage());
        $code=(int)($ex->errorInfo[1]??0);
        if (postgres()) $code = match($ex->getCode()) {'23505'=>1062,'23503'=>1451,'23514','22P02','22003'=>3819,'40P01','40001'=>1213,default=>$code};
        $message=match($code) {1062=>'Nomor resi atau kode rak sudah digunakan. Gunakan identitas yang berbeda.',1451=>'Data tidak dapat dihapus karena masih memiliki riwayat transaksi.',1452=>'Data terkait tidak tersedia. Muat ulang dan pilih kembali.',3819,4025=>'Data tidak memenuhi aturan database. Perubahan sudah dibatalkan; periksa data sebelum mencoba kembali.',1205,1213=>'Data sedang diproses oleh transaksi lain. Silakan coba kembali.',default=>'Data belum berhasil disimpan. Coba kembali atau periksa konfigurasi database.'};
        $_SESSION['flash']=['error',$message]; $_SESSION['old_input']=$_POST;
    }
    $args = [];
    if (isset($_SESSION['old_input'])) {
        if (input('action') === 'paket_save' && input('original') !== '') $args['resi'] = input('original');
        if (input('action') === 'rak_save' && input('id_rak') !== '') $args['edit'] = input('id_rak');
        if (input('action') === 'petugas_save' && input('id_petugas') !== '') $args['edit'] = input('id_petugas');
    }
    redirect($return, $args);
}
$flash=$_SESSION['flash']??null; unset($_SESSION['flash']);
$old=$_SESSION['old_input']??null; unset($_SESSION['old_input']);
$dbError=false;
try { db(); } catch (PDOException $ex) { error_log($ex->getMessage()); $dbError=true; http_response_code(503); }

if ($page==='laporan' && param('export')==='csv' && !$dbError) {
    try {
        $events=reportEvents(reportRange());
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="ruang-gudang-laporan.csv"');
        $out=fopen('php://output','w'); fwrite($out,"\xEF\xBB\xBF");
        fputcsv($out,['Aktivitas','Waktu','Nomor resi','Penerima','Kota tujuan','Kategori','Berat (kg)','Rak','Petugas','Kurir','Kendaraan','Status'],',','"','');
        foreach ($events as $event) {
            if ($event['jenis']==='Outbound' && in_array($event['status'],['Terkirim','Selesai'])) $event['status']='Tiba di tujuan';
            $safe=array_map(fn($v)=>preg_match('/^[\s]*[=+@-]/u',(string)$v)?"'".$v:$v,array_values($event));
            fputcsv($out,$safe,',','"','');
        }
        fclose($out); exit;
    } catch (DomainException $ex) { $flash=['error',$ex->getMessage()]; }
}
ob_start();
try {
    if ($dbError) {
        heading('KONEKSI','Database belum terhubung','Periksa layanan database, lalu muat ulang halaman ini.');
        echo '<section class="panel connection-state">'.icon('rack').'<h2>Ruang Gudang siap, database belum tersedia.</h2><p>Pastikan layanan database berjalan dan konfigurasi koneksi sudah sesuai.</p>'.linkButton('Coba hubungkan',url(),'arrow').'</section>';
    } elseif ($page==='not-found') { heading('404','Halaman tidak ditemukan','Gunakan menu untuk melanjutkan.'); }
    else require __DIR__.'/app/pages/'.$page.'.php';
} catch (DomainException $ex) { echo '<div class="alert error">'.icon('alert').e($ex->getMessage()).'</div>'; }
catch (PDOException $ex) { error_log($ex->getMessage()); http_response_code(503); echo '<div class="alert error">'.icon('alert').'Data belum dapat ditampilkan. Periksa koneksi dan struktur database.</div>'; }
$content=ob_get_clean();
$navIcons=['dashboard'=>'grid','paket'=>'box','rak'=>'rack','inbound'=>'in','outbound'=>'out','petugas'=>'users','laporan'=>'chart'];
$styleVersion = substr(hash_file('sha256',__DIR__.'/assets/style.css'),0,12);
$scriptVersion = substr(hash_file('sha256',__DIR__.'/assets/app.js'),0,12);
?>
<!doctype html>
<html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="theme-color" content="#12263b"><meta name="description" content="Ruang Gudang — Kelola paket, penyimpanan, dan pengiriman dalam satu ruang kerja."><title><?=e($pages[$page]??'Halaman tidak ditemukan')?> · Ruang Gudang</title><link rel="icon" href="assets/favicon.svg" type="image/svg+xml"><link rel="stylesheet" href="assets/style.css?v=<?=e($styleVersion)?>"><script defer src="assets/app.js?v=<?=e($scriptVersion)?>"></script></head>
<body>
<a class="skip-link" href="#main">Lewati ke konten</a>
<div class="sidebar-shade" data-sidebar-close></div>
<aside class="sidebar" id="sidebar">
    <button type="button" class="icon-button sidebar-close" data-sidebar-close aria-label="Tutup menu"><?=icon('close')?></button>
    <a class="brand" href="<?=e(url())?>"><span class="brand-mark"><?=icon('box')?></span><span>Ruang Gudang<small>Pengelolaan logistik</small></span></a>
    <div class="nav-caption">OPERASIONAL</div>
    <nav aria-label="Menu utama"><?php foreach($pages as $key=>$label): if($key==='rak') echo '<div class="nav-caption nav-divider">DATA GUDANG</div>'; ?><a href="<?=e(url($key))?>" class="nav-link <?=$page===$key?'active':''?>" <?=$page===$key?'aria-current="page"':''?>><?=icon($navIcons[$key])?><span><?=e($label)?></span></a><?php endforeach;?></nav>
    <div class="sidebar-bottom"><p class="sidebar-guide">Catat paket di menu Paket, tempatkan ke rak, lalu proses pengirimannya.</p></div>
</aside>
<div class="main-shell">
    <header class="topbar"><div class="breadcrumb"><button class="icon-button mobile-menu" aria-label="Buka menu" aria-controls="sidebar" aria-expanded="false" data-sidebar-toggle><?=icon('menu')?></button><strong><?=e($pages[$page]??'Halaman')?></strong></div><div class="topbar-right"><form class="global-search" method="get"><input type="hidden" name="page" value="paket"><?=icon('search')?><input type="search" name="q" placeholder="Cari resi atau penerima" aria-label="Cari paket"><button type="submit" class="search-submit">Cari</button></form></div></header>
    <main id="main"><?php if($flash):?><div class="alert <?=e($flash[0])?>" role="alert"><?=icon($flash[0]==='success'?'check':'alert')?><span><?=e($flash[1])?></span><button class="icon-button" data-dismiss aria-label="Tutup notifikasi"><?=icon('close')?></button></div><?php endif;?><?=$content?></main>
    <footer><span>© <?=date('Y')?> Ruang Gudang</span><span><i class="live-dot <?=$dbError?'offline':''?>"></i><?=$dbError?'Database belum terhubung':(postgres()?'Terhubung ke database cloud':'Terhubung ke gudang lokal')?></span></footer>
</div>
<?php if($old):?><div id="old-input" hidden data-values="<?=e(json_encode($old,JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE))?>"></div><?php endif;?>
<dialog id="confirm-dialog" class="confirm-dialog"><div class="dialog-head"><div><h2>Konfirmasi tindakan</h2><p id="confirm-message"></p></div></div><div class="dialog-footer"><button class="button secondary" data-close>Batal</button><button class="button primary" id="confirm-submit">Ya, lanjutkan</button></div></dialog>
</body></html>

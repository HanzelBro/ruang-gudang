<?php
$counts=array_fill_keys(['Diterima','Tersimpan','Siap Kirim','Terkirim'],0);
foreach(rows('SELECT status_paket,COUNT(*) n FROM paket GROUP BY status_paket') as $item) $counts[$item['status_paket']]=(int)$item['n'];
$capacity=row('SELECT COALESCE(SUM(kapasitas_maks_kg),0) maks,COALESCE(SUM(kapasitas_terpakai_kg),0) terpakai,COUNT(*) jumlah FROM rak_gudang');
$util=$capacity['maks']>0?(float)$capacity['terpakai']/(float)$capacity['maks']*100:0;
$moving=(int)scalar("SELECT COUNT(*) FROM pengiriman WHERE status_kirim='Dalam Perjalanan'");
$stored=$counts['Tersimpan']+$counts['Siap Kirim'];
heading('','Ringkasan gudang','Lihat status paket dan lanjutkan pekerjaan yang perlu diproses.',modalButton('Terima paket','paket-form'));
$statusItems=[
    ['Diterima','Belum ditempatkan','Pilih rak penyimpanan'],
    ['Tersimpan','Tersimpan di rak','Menunggu persiapan kirim'],
    ['Siap Kirim','Siap dikirim','Sudah bisa diserahkan ke kurir'],
    ['Terkirim','Sudah keluar gudang','Dalam perjalanan atau sudah tiba'],
];
?>
<div class="status-summary" aria-label="Jumlah paket menurut status">
<?php foreach($statusItems as [$status,$label,$note]): ?>
    <a class="status-summary-item" href="<?=e(url('paket',['status'=>$status]))?>">
        <span><?=e($label)?></span><strong><?=num($counts[$status])?></strong><small><?=e($note)?></small>
    </a>
<?php endforeach; ?>
</div>
<div class="simple-dashboard-grid">
    <section class="panel task-panel">
        <div class="panel-head"><h2>Pekerjaan berikutnya</h2></div>
        <a class="task-row" href="<?=e(url('inbound'))?>">
            <span class="task-step" aria-hidden="true">1</span>
            <span class="task-copy"><strong>Tempatkan paket ke rak</strong><small><?=$counts['Diterima']?> paket menunggu penempatan</small></span>
            <?=icon('chevron')?>
        </a>
        <a class="task-row" href="<?=e(url('outbound'))?>">
            <span class="task-step" aria-hidden="true">2</span>
            <span class="task-copy"><strong>Siapkan dan kirim paket</strong><small><?=$counts['Tersimpan']?> paket tersimpan · <?=$counts['Siap Kirim']?> paket siap kirim</small></span>
            <?=icon('chevron')?>
        </a>
        <p class="task-help">Paket baru dicatat melalui tombol Terima paket.</p>
    </section>
    <section class="panel simple-capacity">
        <div class="panel-head"><h2>Kapasitas rak</h2><a class="text-link" href="<?=e(url('rak'))?>">Lihat rak</a></div>
        <div class="simple-capacity-body">
            <div class="capacity-total"><strong><?=num($util,1)?>%</strong><span>terpakai</span></div>
            <div class="meter" role="progressbar" aria-label="Kapasitas rak terpakai" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?=e((string)min(100,max(0,$util)))?>" aria-valuetext="<?=e(kg($capacity['terpakai']).' dari '.kg($capacity['maks']))?>"><span style="width:<?=min(100,max(0,$util))?>%"></span></div>
            <p><?=kg($capacity['terpakai'])?> dari <?=kg($capacity['maks'])?></p>
            <p class="muted">Sisa <?=kg((float)$capacity['maks']-(float)$capacity['terpakai'])?> di <?=$capacity['jumlah']?> rak.</p>
        </div>
    </section>
</div>
<section class="panel recent-panel">
    <div class="panel-head"><h2>Aktivitas terbaru</h2><a class="text-link" href="<?=e(url('laporan'))?>">Lihat laporan</a></div>
    <?php $events=array_slice(reportEvents(['','']),0,4); if(!$events): emptyState('Belum ada aktivitas','Aktivitas muncul setelah paket ditempatkan atau dikirim.'); else: ?>
    <ul class="recent-events">
        <?php foreach($events as $event): ?>
        <li><div><a class="resi" href="<?=e(url('paket',['resi'=>$event['no_resi']]))?>"><?=e($event['no_resi'])?></a><span><?=e($event['jenis']==='Inbound'?'Ditempatkan di '.$event['lokasi']:'Diserahkan ke '.$event['nama_kurir'])?></span></div><time><?=e(stamp($event['waktu']))?></time></li>
        <?php endforeach; ?>
    </ul>
    <?php endif; ?>
</section>
<details class="panel dashboard-details">
    <summary>Grafik dan daftar paket di gudang <span>Lihat rincian</span></summary>
    <div class="dashboard-details-body">
        <p class="summary-note"><?=num(array_sum($counts))?> paket tercatat · <?=num($moving)?> dalam perjalanan · <?=num($stored)?> tersimpan atau siap kirim.</p>
        <?php
        $last=scalar('SELECT MAX(tanggal) FROM (SELECT MAX(waktu_masuk) tanggal FROM alokasi_rak UNION ALL SELECT MAX(waktu_kirim) FROM pengiriman) dates')?:date('Y-m-d');
        $end=new DateTimeImmutable(substr($last,0,10)); $start=$end->modify('-6 days');
        $in=array_column(rows('SELECT DATE(waktu_masuk) d,COUNT(*) n FROM alokasi_rak WHERE waktu_masuk>=? AND waktu_masuk<? GROUP BY DATE(waktu_masuk)',[$start->format('Y-m-d'),$end->modify('+1 day')->format('Y-m-d')]),'n','d');
        $out=array_column(rows('SELECT DATE(waktu_kirim) d,COUNT(*) n FROM pengiriman WHERE waktu_kirim>=? AND waktu_kirim<? GROUP BY DATE(waktu_kirim)',[$start->format('Y-m-d'),$end->modify('+1 day')->format('Y-m-d')]),'n','d');
        $max=max([4,...array_values($in),...array_values($out)]); $max=(int)(ceil($max/4)*4);
        ?>
        <div class="panel-head"><div><h2>Penempatan dan pengiriman</h2><p>7 hari hingga aktivitas terakhir: <?=e($start->format('d M').' sampai '.$end->format('d M Y'))?></p></div></div>
        <div class="chart-summary"><div><i class="legend-dot orange-bg"></i>Penempatan <strong><?=num(array_sum($in))?></strong></div><div><i class="legend-dot navy-bg"></i>Pengiriman <strong><?=num(array_sum($out))?></strong></div></div>
        <div class="chart-container"><svg viewBox="0 0 700 230" role="img" aria-label="Grafik penempatan dan pengiriman dari <?=e($start->format('d M Y'))?> hingga <?=e($end->format('d M Y'))?>">
            <?php for($i=0;$i<=4;$i++): $y=190-$i*40; ?>
            <line x1="40" y1="<?=$y?>" x2="680" y2="<?=$y?>" stroke="#e9edf1" stroke-dasharray="4 5"/><text x="28" y="<?=$y+4?>" text-anchor="end"><?=$max/4*$i?></text>
            <?php endfor; ?>
            <?php for($i=0;$i<7;$i++): $day=$start->modify("+$i days"); $a=(int)($in[$day->format('Y-m-d')]??0); $b=(int)($out[$day->format('Y-m-d')]??0); $x=65+$i*90; ?>
            <rect x="<?=$x?>" y="<?=190-$a/$max*160?>" width="24" height="<?=$a/$max*160?>" rx="3" fill="#b94b19"><title><?=e($day->format('d M'))?> · Penempatan: <?=$a?></title></rect><rect x="<?=$x+29?>" y="<?=190-$b/$max*160?>" width="24" height="<?=$b/$max*160?>" rx="3" fill="#233e56"><title><?=e($day->format('d M'))?> · Pengiriman: <?=$b?></title></rect><text x="<?=$x+26?>" y="217" text-anchor="middle"><?=e($day->format('d M'))?></text>
            <?php endfor; ?>
        </svg></div>
        <p class="summary-note">Penempatan mencakup perpindahan antarrak.</p>
        <div class="panel-head"><h2>Paket di gudang</h2><a class="text-link" href="<?=e(url('paket'))?>">Lihat semua paket</a></div>
        <?php packageTable(array_slice(packageRows("p.status_paket IN ('Tersimpan','Siap Kirim')"),0,5),true); ?>
    </div>
</details>
<?php packageDialog(); ?>

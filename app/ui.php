<?php
declare(strict_types=1);

function icon(string $name, string $class = ''): string
{
    $paths = [
        'box'=>'<path d="m21 8-9 5-9-5m9 5v9M3 8l9-5 9 5v10l-9 5-9-5V8Zm4.5-2.5 9 5"/>',
        'grid'=>'<rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/>',
        'rack'=>'<path d="M4 3v18M20 3v18M4 11h16M4 20h16"/><rect x="7" y="4" width="4" height="7" rx="1"/><rect x="13" y="6" width="4" height="5" rx="1"/><rect x="7" y="14" width="10" height="6" rx="1"/>',
        'in'=>'<path d="M12 3v12m-5-5 5 5 5-5M5 15H3v6h18v-6h-2"/>',
        'out'=>'<path d="M12 15V3m-5 5 5-5 5 5M5 15H3v6h18v-6h-2"/>',
        'truck'=>'<path d="M3 6h11v11H3V6Zm11 4h4l3 4v3h-7"/><circle cx="7" cy="18" r="2"/><circle cx="17" cy="18" r="2"/>',
        'users'=>'<circle cx="9" cy="7" r="3"/><path d="M3 21v-4a6 6 0 0 1 12 0v4m1-17a3 3 0 0 1 0 6m3 11v-4a6 6 0 0 0-2-4"/>',
        'chart'=>'<path d="M4 3v18h17M9 17v-4m5 4V9m5 8V5"/>',
        'search'=>'<circle cx="10.5" cy="10.5" r="6.5"/><path d="m16 16 5 5"/>',
        'plus'=>'<path d="M12 5v14M5 12h14"/>',
        'arrow'=>'<path d="M4 12h16m-6-6 6 6-6 6"/>',
        'chevron'=>'<path d="m9 5 7 7-7 7"/>',
        'download'=>'<path d="M12 3v12m-5-5 5 5 5-5M4 17v4h16v-4"/>',
        'calendar'=>'<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M16 3v4M8 3v4M3 11h18"/>',
        'check'=>'<path d="m5 12 4 4L19 6"/>',
        'clock'=>'<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
        'close'=>'<path d="m6 6 12 12M6 18 18 6"/>',
        'menu'=>'<path d="M4 6h16M4 12h16M4 18h16"/>',
        'edit'=>'<path d="m16 3 5 5-12 12-6 1 1-6L16 3Zm-3 3 5 5"/>',
        'trash'=>'<path d="M3 6h18M9 6V3h6v3M5 6l1 15h12l1-15M10 10v7m4-7v7"/>',
        'pin'=>'<path d="M20 10c0 6-8 12-8 12S4 16 4 10a8 8 0 1 1 16 0Z"/><circle cx="12" cy="10" r="2.5"/>',
        'swap'=>'<path d="M3 7h17m-4-4 4 4-4 4M21 17H4m4-4-4 4 4 4"/>',
        'alert'=>'<path d="m12 3 10 18H2L12 3Zm0 6v5m0 3v.5"/>',
        'shield'=>'<path d="m12 3 8 3v6c0 5-8 9-8 9s-8-4-8-9V6l8-3Z"/><path d="m8 12 3 3 5-6"/>',
        'help'=>'<circle cx="12" cy="12" r="9"/><path d="M9 9a3 3 0 0 1 6 0c0 2-3 2-3 4m0 3v.5"/>',
    ];
    return '<svg class="icon '.e($class).'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'.($paths[$name]??$paths['box']).'</svg>';
}
function badge(string $status, bool $shipping = false): string
{
    $label = $shipping && in_array($status,['Terkirim','Selesai']) ? 'Tiba di tujuan' : $status;
    $colors=['Diterima'=>'blue','Tersimpan'=>'green','Siap Kirim'=>'orange','Terkirim'=>'purple','Dalam Perjalanan'=>'blue','Aktif'=>'green','Selesai'=>'gray','Admin'=>'purple','Inbound Checker'=>'green','Outbound Sorter'=>'orange'];
    return '<span class="badge '.($colors[$status]??'gray').'"><i></i>'.e($label).'</span>';
}
function heading(string $eyebrow, string $title, string $description, string $actions=''): void
{
    echo '<div class="page-heading"><div>'.($eyebrow!==''?'<div class="eyebrow">'.e($eyebrow).'</div>':'').'<h1>'.e($title).'</h1><p>'.e($description).'</p></div><div class="heading-actions">'.$actions.'</div></div>';
}
function linkButton(string $text,string $href,string $ico='plus',string $class='primary'): string
{
    return '<a class="button '.e($class).'" href="'.e($href).'">'.icon($ico).e($text).'</a>';
}
function modalButton(string $text,string $id,string $ico='plus',string $class='primary'): string
{
    return '<button class="button '.e($class).'" data-open="'.e($id).'">'.icon($ico).e($text).'</button>';
}
function emptyState(string $title,string $detail='Coba ubah filter atau tambahkan data baru.'): void
{
    echo '<div class="empty-state">'.icon('box').'<h3>'.e($title).'</h3><p>'.e($detail).'</p></div>';
}
function stats(array $items): void
{
    echo '<div class="stats">';
    foreach ($items as [$label,$value,$note,$ico,$color]) echo '<article class="stat"><div class="stat-top"><span>'.e($label).'</span><span class="stat-icon '.e($color).'">'.icon($ico).'</span></div><strong>'.e($value).'</strong><div class="stat-note">'.e($note).'</div></article>';
    echo '</div>';
}
function field(string $name,string $label,string $value='',string $type='text',bool $required=true,int $max=100,string $extra=''): void
{
    echo '<label class="field"><span>'.e($label).($required?' <b>*</b>':'').'</span><input name="'.e($name).'" type="'.e($type).'" value="'.e($value).'" '.($required?'required ':'').'maxlength="'.$max.'" '.$extra.'></label>';
}
function selectField(string $name,string $label,array $options,string $selected='',string $placeholder='Pilih salah satu'): void
{
    echo '<label class="field"><span>'.e($label).' <b>*</b></span><select name="'.e($name).'" required><option value="">'.e($placeholder).'</option>';
    foreach ($options as $key=>$text) echo '<option value="'.e($key).'" '.((string)$key===$selected?'selected':'').'>'.e($text).'</option>';
    echo '</select></label>';
}
function beginDialog(string $id,string $title,string $description,string $action,string $page): void
{
    echo '<dialog id="'.e($id).'" aria-labelledby="'.e($id).'-title"><div class="dialog-head"><div><h2 id="'.e($id).'-title">'.e($title).'</h2><p>'.e($description).'</p></div><button type="button" class="icon-button" data-close aria-label="Tutup">'.icon('close').'</button></div><form method="post" class="operation-form">'.csrf().'<input type="hidden" name="action" value="'.e($action).'"><input type="hidden" name="return" value="'.e($page).'"><div class="form-grid">';
}
function endDialog(string $submit='Simpan data'): void
{
    echo '</div><div class="dialog-footer"><button type="button" class="button secondary" data-close>Batal</button><button class="button primary" type="submit">'.icon('check').e($submit).'</button></div></form></dialog>';
}
function actionForm(string $action,string $page,array $values,string $label,string $ico='check',bool $confirm=false): string
{
    $html='<form method="post" class="inline-form"'.($confirm?' data-confirm="'.e('Lanjutkan tindakan: '.$label.'?').'"':'').'>'.csrf().'<input type="hidden" name="action" value="'.e($action).'"><input type="hidden" name="return" value="'.e($page).'">';
    foreach ($values as $key=>$value) $html.='<input type="hidden" name="'.e($key).'" value="'.e($value).'">';
    return $html.'<button class="button small '.($ico==='trash'?'danger':'secondary').'" type="submit">'.icon($ico).e($label).'</button></form>';
}
function packageTable(array $packages,bool $compact=false): void
{
    if (!$packages) { emptyState('Belum ada paket untuk ditampilkan'); return; }
    echo '<div class="table-wrap"><table><thead><tr><th>Nomor resi</th><th>Penerima & tujuan</th>'.(!$compact?'<th>Kategori</th>':'').'<th>Berat</th><th>Lokasi rak</th><th>Status</th><th><span class="sr-only">Detail</span></th></tr></thead><tbody>';
    foreach ($packages as $p) {
        echo '<tr><td><a class="resi" href="'.e(url('paket',['resi'=>$p['no_resi']])).'">'.icon('box').e($p['no_resi']).'</a></td><td><strong class="cell-title">'.e($p['nama_penerima']).'</strong><span class="cell-sub">'.e($p['kota_tujuan']).'</span></td>'.(!$compact?'<td>'.e($p['kategori']).'</td>':'').'<td class="numeric">'.kg($p['berat_kg']).'</td><td>'.($p['lokasi']?'<span class="rack-code">'.icon('rack').e($p['lokasi']).'</span>':'<span class="muted">—</span>').'</td><td>'.badge($p['status_paket']).'</td><td><a class="icon-button" href="'.e(url('paket',['resi'=>$p['no_resi']])).'" aria-label="Detail '.e($p['no_resi']).'">'.icon('chevron').'</a></td></tr>';
    }
    echo '</tbody></table></div>';
}
function packageDialog(?array $p=null): void
{
    beginDialog('paket-form',$p?'Edit informasi paket':'Terima paket baru','Catat informasi kiriman sebelum ditempatkan di rak.','paket_save','paket');
    echo '<input type="hidden" name="original" value="'.e($p['no_resi']??'').'">';
    field('no_resi','Nomor resi',$p['no_resi']??'','text',true,30,$p?'readonly':'placeholder="Contoh: EXP021"');
    selectField('kategori','Kategori',array_combine(categories(),categories()),$p['kategori']??'');
    field('nama_pengirim','Nama pengirim',$p['nama_pengirim']??'');
    field('nama_penerima','Nama penerima',$p['nama_penerima']??'');
    field('kota_tujuan','Kota tujuan',$p['kota_tujuan']??'','text',true,50);
    $hasHistory=$p && scalar('SELECT (SELECT COUNT(*) FROM alokasi_rak WHERE no_resi=?) + (SELECT COUNT(*) FROM pengiriman WHERE no_resi=?)',[$p['no_resi'],$p['no_resi']]);
    field('berat_kg','Berat (kg)',$p['berat_kg']??'','number',true,10,'min="0.01" max="9999.99" step="0.01" '.($hasHistory?'readonly':''));
    if ($hasHistory) echo '<p class="form-note">Berat dikunci karena paket sudah memiliki riwayat transaksi.</p>';
    endDialog($p?'Simpan perubahan':'Simpan paket');
}
function staffOptions(string $role): array
{
    $options=[];
    foreach (rows("SELECT * FROM petugas WHERE role_petugas IN ('Admin',?) ORDER BY nama_petugas",[$role]) as $p) $options[$p['id_petugas']]=$p['nama_petugas'].' · '.$p['role_petugas'];
    return $options;
}
function rackOptions(): array
{
    $options=[];
    foreach (rackRows() as $r) $options[$r['id_rak']]=$r['kode_rak'].' · '.$r['zona'].' · sisa '.kg((float)$r['kapasitas_maks_kg']-(float)$r['kapasitas_terpakai_kg']);
    return $options;
}
function allocationDialog(string $kind): void
{
    $move=$kind==='pindah';
    beginDialog($kind.'-form',$move?'Pindahkan paket':'Tempatkan paket ke rak',$move?'Riwayat rak asal tetap tersimpan.':'Pilih rak dengan kapasitas yang cukup.',$kind,'inbound');
    $options=[];
    foreach (packageRows('p.status_paket=?',[$move?'Tersimpan':'Diterima']) as $p) $options[$p['no_resi']]=$p['no_resi'].' · '.$p['nama_penerima'].' · '.kg($p['berat_kg']).($p['lokasi']?' · '.$p['lokasi']:'');
    selectField('no_resi','Paket',$options,param('resi'));
    selectField('id_rak',$move?'Rak tujuan':'Rak penyimpanan',rackOptions());
    selectField('id_petugas','Petugas penempatan',staffOptions('Inbound Checker'));
    echo '<div class="form-hint">'.icon('shield').'<span>Kapasitas dan status paket diperbarui otomatis setelah penempatan berhasil.</span></div>';
    endDialog($move?'Pindahkan paket':'Simpan penempatan');
}

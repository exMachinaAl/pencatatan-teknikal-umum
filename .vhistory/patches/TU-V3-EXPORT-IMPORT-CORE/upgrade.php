<?php
/**
 * TU V3 additive upgrader. Berjalan lokal dari PHP CLI. Tidak mengunduh, tidak menjalankan command shell.
 * Hanya MENAMBAHKAN file PHP/Blade baru dan metadata .trash/.vhistory dalam folder proyek.
 * Tidak menulis/mengubah tabel data, model lama, .env, atau CSS yang sudah ada.
 */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('PHP CLI only'); }
if (version_compare(PHP_VERSION,'8.1','<')) { fwrite(STDERR,"[GAGAL] PHP >= 8.1 dibutuhkan.\n"); exit(1); }
const PATCH = 'TU-V3-EXPORT-IMPORT-CORE';
$inHistory = basename(dirname(__DIR__)) === 'patches' && basename(dirname(dirname(__DIR__))) === '.vhistory';
$project = $inHistory ? dirname(dirname(dirname(__DIR__))) : dirname(__DIR__).DIRECTORY_SEPARATOR.'pencatatan-teknikal-umum-main';
$action = $argv[1] ?? 'check';
function say(string $message): void { echo "[TU V3] {$message}\n"; }
function fail(string $message): never { fwrite(STDERR,"[GAGAL] {$message}\n"); exit(1); }
function makeDir(string $path): void {
    if (is_link($path)) throw new RuntimeException('Symlink folder ditolak: '.$path);
    if (!is_dir($path) && !mkdir($path,0775,true) && !is_dir($path)) throw new RuntimeException('Gagal membuat folder: '.$path);
}
function safePath(string $base,string $rel): string {
    $path=$base;
    foreach(explode('/',$rel) as $segment) {
        if ($segment==='' || $segment==='.' || $segment==='..' || str_contains($segment,':') || str_contains($segment,'\\')) throw new RuntimeException('Path tidak aman.');
        $path.=DIRECTORY_SEPARATOR.$segment;
        if (is_link($path)) throw new RuntimeException('Symlink ditolak: '.$rel);
    }
    if (is_dir($path)) throw new RuntimeException('Target sudah menjadi folder: '.$rel);
    return $path;
}
function loadJson(string $path): array {
    $bytes=@file_get_contents($path);$value=$bytes===false?null:json_decode($bytes,true);
    if (!is_array($value)) throw new RuntimeException('JSON tidak bisa dibaca: '.$path);
    return $value;
}
function saveJson(string $path,array $value): void {
    $json=json_encode($value,JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
    if ($json===false || file_put_contents($path,$json."\n",LOCK_EX)===false) throw new RuntimeException('Gagal menyimpan riwayat.');
}
function paths(string $project): array {
    return [$project.'/.vhistory/patches/'.PATCH,$project.'/.trash/'.PATCH];
}
function preflight(string $project): array {
    if (is_link($project) || !is_dir($project) || !is_file($project.'/artisan')) throw new RuntimeException('Proyek Laravel tujuan tidak ditemukan.');
    $composer=loadJson($project.'/composer.json');
    if (!isset($composer['require']['filament/filament']) || !str_contains((string)$composer['require']['filament/filament'],'3.')) throw new RuntimeException('Proyek harus memakai Filament 3.x, tidak cocok dengan patch ini.');
    $panel=safePath($project,'app/Providers/Filament/AdminPanelProvider.php');
    if (!is_file($panel) || !str_contains((string)file_get_contents($panel),'discoverPages')) throw new RuntimeException('Panel tidak menemukan halaman otomatis (discoverPages).');
    foreach (['.trash','.vhistory'] as $d) {
        $folder=$project.'/'.$d;
        if (is_link($folder) || (file_exists($folder) && !is_dir($folder))) throw new RuntimeException('Folder internal tidak aman: '.$d);
    }
    $manifest=loadJson(__DIR__.'/manifest.json');
    if (($manifest['patch_id']??'')!==PATCH || ($manifest['target_folder']??'')!==basename($project)) throw new RuntimeException('Manifest untuk proyek berbeda.');
    $files=$manifest['files']??[];
    if (!is_array($files) || count($files)!==3) throw new RuntimeException('Jumlah file patch salah.');
    [$history,$backup]=paths($project);
    $stateFile=$history.'/state.json';
    if (is_file($stateFile)) {
        $state=loadJson($stateFile);
        if (($state['status']??'')==='applied') {
            foreach ($files as $rel=>$sha) {
                $target=safePath($project,$rel);
                if (!is_file($target) || hash_file('sha256',$target)!==$sha) throw new RuntimeException('Patch sudah terpasang tetapi file berubah: '.$rel);
            }
            return ['already'=>true,'files'=>$files];
        }
        throw new RuntimeException('Riwayat patch sudah ada (status '.($state['status']??'?').'). Jangan dipasang dua kali atau ditimpa.');
    }
    foreach ($files as $rel=>$sha) {
        $source=safePath(__DIR__.'/payload',$rel);
        $target=safePath($project,$rel);
        if (!is_file($source) || hash_file('sha256',$source)!==$sha) throw new RuntimeException('Checksum file patch tidak cocok: '.$rel);
        if (file_exists($target)) throw new RuntimeException('Ada file lokal dengan nama yang sama: '.$rel.'. Tidak ditimpa.');
        $parent=dirname($target);
        while (!is_dir($parent) && $parent!==dirname($parent)) $parent=dirname($parent);
        if (!is_writable($parent)) throw new RuntimeException('Folder tujuan tidak bisa ditulis: '.$rel);
    }
    if (is_link($history) || is_link($backup) || file_exists($history.'/state.json')) throw new RuntimeException('History/backup tidak bersih.');
    return ['already'=>false,'files'=>$files];
}
function rollback(string $project): void {
    [$history,$trash]=paths($project);
    $state=loadJson($history.'/state.json');
    if (($state['status']??'')==='rolled_back') {say('Patch sudah di-rollback.');return;}
    if (($state['status']??'')!=='applied') throw new RuntimeException('Hanya patch status applied yang bisa di-rollback.');
    foreach ($state['files'] as $rel=>$sha) {
        $target=safePath($project,$rel);
        if (!is_file($target) || hash_file('sha256',$target)!==$sha) throw new RuntimeException('File berubah setelah upgrade: '.$rel.'. Rollback dibatalkan untuk mencegah kehilangan kode lokal.');
    }
    foreach ($state['files'] as $rel=>$sha) {
        if (!unlink(safePath($project,$rel))) throw new RuntimeException('Gagal menghapus file hasil patch: '.$rel);
    }
    $state['status']='rolled_back';$state['rolled_back_at']=date(DATE_ATOM);saveJson($history.'/state.json',$state);
    say('Rollback file baru selesai. Database/data dan seluruh file lama tetap utuh.');
}
try {
    if (!in_array($action,['check','apply','rollback'],true)) throw new RuntimeException('Perintah valid: check, apply, rollback.');
    if ($action==='rollback') { rollback($project);exit(0); }
    if ($inHistory) throw new RuntimeException('Check dan apply harus dijalankan dari folder patch ZIP.');
    $check=preflight($project);
    if ($check['already']) {say('Patch sudah terpasang, tidak ada perubahan.');exit(0);}
    say('Target: '.$project);
    foreach (array_keys($check['files']) as $rel) say('TAMBAH: '.$rel);
    say('TIDAK DISENTUH: Widget/Resources lama, CSS, .env, database, dokumen storage.');
    if (!extension_loaded('zip')) say('PERHATIAN: ekstensi zip PHP CLI belum aktif; fitur XLSX membutuhkan PHP zip di web server. CSV tetap berfungsi.');
    if (!extension_loaded('dom')) say('PERHATIAN: ekstensi dom PHP CLI belum aktif; import XLSX memerlukan PHP dom.');
    if ($action==='check') {say('Pemeriksaan aman. Belum mengubah file.');exit(0);}
    [$history,$trash]=paths($project);
    makeDir($history);makeDir($trash);
    saveJson($trash.'/snapshot.json',['patch_id'=>PATCH,'original_files'=>'absent','note'=>'Patch add-only; tidak ada file lama yang perlu disalin sebagai backup.']);
    $state=['patch_id'=>PATCH,'status'=>'pending','applied_at'=>date(DATE_ATOM),'files'=>$check['files']];
    saveJson($history.'/state.json',$state);
    $installed=[];
    try {
        foreach ($check['files'] as $rel=>$sha) {
            $target=safePath($project,$rel);
            makeDir(dirname($target));
            if (file_exists($target)) throw new RuntimeException('File tiba-tiba muncul saat instalasi: '.$rel);
            if (copy(safePath(__DIR__.'/payload',$rel),$target)!==true || hash_file('sha256',$target)!==$sha) throw new RuntimeException('Penyalinan gagal: '.$rel);
            $installed[]=$rel;
        }
        if (!copy(__FILE__,$history.'/upgrade.php')) throw new RuntimeException('Mesin rollback tidak berhasil disimpan.');
        if (!copy(__DIR__.'/JALANKAN-ROLLBACK.bat',$history.'/JALANKAN-ROLLBACK.bat')) throw new RuntimeException('Launcher rollback tidak berhasil disimpan.');
        $state['status']='applied';saveJson($history.'/state.json',$state);
    } catch (Throwable $error) {
        foreach ($installed as $rel) {
            $target=safePath($project,$rel);
            if (is_file($target) && hash_file('sha256',$target)===$check['files'][$rel]) unlink($target);
        }
        $state['status']='aborted';saveJson($history.'/state.json',$state);
        throw $error;
    }
    say('Upgrade V3 berhasil. Buka sidebar Pengaturan & Utilitas > Export & Import Data.');
    say('Rollback: .vhistory/patches/'.PATCH.'/JALANKAN-ROLLBACK.bat');
} catch (Throwable $error) {fail($error->getMessage());}

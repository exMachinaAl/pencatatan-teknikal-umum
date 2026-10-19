<?php
/** TU V4.0 installer PHP CLI: file baru saja; tanpa shell/download/database migration. */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('PHP CLI only'); }
if (version_compare(PHP_VERSION, '8.1', '<')) { fwrite(STDERR,"PHP >=8.1 wajib.\n"); exit(1); }
const PATCH_ID='TU-V4_0-POLICY-BASELINE';
$inHistory=basename(dirname(__DIR__))==='patches' && basename(dirname(dirname(__DIR__)))==='.vhistory';
$project=$inHistory ? dirname(dirname(dirname(__DIR__))) : dirname(__DIR__).DIRECTORY_SEPARATOR.'pencatatan-teknikal-umum-main';
$action=$argv[1]??'check';
function failNow(string $message): never {fwrite(STDERR,"[GAGAL] $message\n");exit(1);}
function show(string $text): void {echo "[V4.0] $text\n";}
function loadJson(string $file): array {
    $bytes=@file_get_contents($file);
    $data=$bytes===false?null:json_decode($bytes,true);
    if (!is_array($data)) throw new RuntimeException('Gagal membaca JSON: '.$file);
    return $data;
}
function saveJson(string $file,array $data): void {
    $bytes=json_encode($data,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
    if ($bytes===false || @file_put_contents($file,$bytes."\n",LOCK_EX)===false) throw new RuntimeException('Gagal menyimpan metadata: '.$file);
}
function safeFile(string $root,string $rel): string {
    $path=$root;
    foreach (explode('/',$rel) as $part) {
        if (!$part || $part==='.' || $part==='..' || str_contains($part,'\\') || str_contains($part,':')) throw new RuntimeException('Path terlarang: '.$rel);
        $path.=DIRECTORY_SEPARATOR.$part;
        if (is_link($path)) throw new RuntimeException('Symlink ditolak: '.$rel);
    }
    if (is_dir($path)) throw new RuntimeException('Target merupakan folder: '.$rel);
    return $path;
}
function dirMake(string $path): void {
    if (is_link($path) || (file_exists($path) && !is_dir($path))) throw new RuntimeException('Folder berbahaya: '.$path);
    if (!is_dir($path) && !@mkdir($path,0775,true) && !is_dir($path)) throw new RuntimeException('Tidak bisa buat folder: '.$path);
}
function containers(string $project): array {
    foreach (['.trash','.vhistory','.vhistory/patches'] as $rel) {
        $path=$project.'/'.$rel;
        if (is_link($path) || (file_exists($path) && !is_dir($path))) throw new RuntimeException('Folder internal tidak aman: '.$rel);
    }
    return [$project.'/.vhistory/patches/'.PATCH_ID,$project.'/.trash/'.PATCH_ID];
}

/** Periksa versi yang dikunci/terpasang, bukan string constraint Composer secara persis. */
function packageVersion(array $data, string $package): ?string {
    $packages=$data['packages']??$data;
    if (!is_array($packages)) return null;
    foreach ($packages as $row) {
        if (is_array($row) && ($row['name']??'')===$package && is_string($row['version']??null)) {
            return ltrim(trim($row['version']), 'vV');
        }
    }
    return null;
}
function checkFilamentVersion(string $project, array $composer): array {
    $constraint=$composer['require']['filament/filament']??null;
    if (!is_string($constraint) || trim($constraint)==='') {
        throw new RuntimeException('filament/filament tidak ditemukan di composer.json.');
    }
    // Hanya klaim dukungan untuk dependensi Filament 3; tolak konfigurasi major lain.
    $declared=trim($constraint);
    $threeOnly=(bool) preg_match('/^(?:[\^~])?3(?:\.(?:\d+|\*)){0,2}(?:\.\*)?$/',$declared);
    $range3=(bool) preg_match('/^>=\s*3(?:\.\d+)?\s*,?\s*<\s*4(?:\.0)?$/',$declared);
    if (!$threeOnly && !$range3) {
        throw new RuntimeException('composer.json meminta filament/filament "'.$declared.'". Patch V4 ini hanya untuk Filament 3.x (minimal 3.2); tidak mengubah atau memaksa versi Composer.');
    }
    $lockVersion=null;
    if (is_file($project.'/composer.lock')) {
        $lockVersion=packageVersion(loadJson($project.'/composer.lock'),'filament/filament');
    }
    $installedVersion=null;
    if (is_file($project.'/vendor/composer/installed.json')) {
        $installedVersion=packageVersion(loadJson($project.'/vendor/composer/installed.json'),'filament/filament');
    }
    if ($installedVersion && $lockVersion && (int) $installedVersion!==(int) $lockVersion) {
        throw new RuntimeException('Versi Filament di vendor (' . $installedVersion . ') dan composer.lock (' . $lockVersion . ') berbeda major. Periksa Composer; patch tidak dipasang.');
    }
    $version=$installedVersion ?: $lockVersion;
    $source=$installedVersion ? 'vendor/composer/installed.json' : 'composer.lock';
    if (!$version) throw new RuntimeException('Tidak bisa memverifikasi versi Filament. Diperlukan composer.lock atau vendor/composer/installed.json.');
    if (!preg_match('/^\d+\.\d+\.\d+(?:[.-].*)?$/', $version)) {
        throw new RuntimeException('Versi Filament tidak dapat dibaca: '.$version.'.');
    }
    if (version_compare($version,'3.2.0','<') || version_compare($version,'4.0.0','>=')) {
        throw new RuntimeException('Filament yang terdeteksi: '.$version.'. Patch ini hanya diuji berdasarkan kode Filament 3.x mulai 3.2; tidak dipasang.');
    }
    return ['version'=>$version,'constraint'=>$declared,'source'=>$source];
}

function preflight(string $project): array {
    if (is_link($project) || !is_dir($project) || !is_file($project.'/artisan')) throw new RuntimeException('Folder project Laravel tidak ditemukan: '.$project);
    $composer=loadJson($project.'/composer.json');
    $filament=checkFilamentVersion($project,$composer);
    show('Filament terdeteksi: '.$filament['version'].' (sumber: '.$filament['source'].', syarat Composer: '.$filament['constraint'].')');
    if (!is_file($project.'/app/Providers/Filament/AdminPanelProvider.php')) throw new RuntimeException('AdminPanelProvider tidak tersedia.');
    $panel=(string) file_get_contents($project.'/app/Providers/Filament/AdminPanelProvider.php');
    if (!str_contains($panel,'discoverPages')) throw new RuntimeException('Halaman Filament tidak di-discover otomatis.');
    $manifest=loadJson(__DIR__.'/manifest.json');
    if (($manifest['patch_id']??null)!==PATCH_ID || ($manifest['target_folder']??null)!==basename($project)) throw new RuntimeException('Manifest bukan untuk proyek ini.');
    foreach ($manifest['required_files']??[] as $required) {
        if (!is_file(safeFile($project,$required))) throw new RuntimeException('V3/V3.1 belum terpasang: '.$required);
    }
    [$history,$trash]=containers($project);
    if (is_link($history) || is_link($trash)) throw new RuntimeException('History/trash tidak boleh symlink.');
    if (is_file($history.'/state.json')) {
        $state=loadJson($history.'/state.json');
        if (($state['status']??null)!=='applied') throw new RuntimeException('Patch pernah gagal/di-rollback. Riwayat lama harus diperiksa terlebih dahulu.');
        foreach ($state['files']??[] as $rel=>$sha) {
            $target=safeFile($project,$rel);
            if (!is_file($target) || hash_file('sha256',$target)!==$sha) throw new RuntimeException('Patch terpasang tapi file sudah diubah: '.$rel);
        }
        return ['already'=>true,'files'=>$state['files']];
    }
    $files=$manifest['files']??null;
    if (!is_array($files) || count($files)!==13) throw new RuntimeException('Jumlah file V4 tidak sesuai.');
    foreach ($files as $rel=>$sha) {
        if (!is_string($rel) || !preg_match('/^[a-f0-9]{64}$/',$sha)) throw new RuntimeException('Manifest tidak valid.');
        $source=safeFile(__DIR__.'/payload',$rel);$target=safeFile($project,$rel);
        if (!is_file($source) || hash_file('sha256',$source)!==$sha) throw new RuntimeException('Checksum payload salah: '.$rel);
        if (file_exists($target)) throw new RuntimeException('File lokal sudah ada, tidak boleh ditimpa: '.$rel);
    }
    if (file_exists($history) || file_exists($trash)) throw new RuntimeException('Folder riwayat patch sudah ada, hentikan untuk menghindari overwrite.');
    return ['already'=>false,'files'=>$files];
}
function rollback(string $project): void {
    [$history,$trash]=containers($project);
    $state=loadJson($history.'/state.json');
    if (($state['status']??null)==='rolled_back') { show('Sudah di-rollback.'); return; }
    if (($state['status']??null)!=='applied') throw new RuntimeException('Riwayat bukan status applied.');
    foreach ($state['files'] as $rel=>$sha) {
        $target=safeFile($project,$rel);
        if (!is_file($target) || hash_file('sha256',$target)!==$sha) throw new RuntimeException('File berubah sesudah patch, rollback dibatalkan: '.$rel);
    }
    foreach ($state['files'] as $rel=>$sha) {
        if (!@unlink(safeFile($project,$rel))) throw new RuntimeException('Tidak dapat rollback file: '.$rel);
    }
    $state['status']='rolled_back';$state['rolled_back_at']=date(DATE_ATOM);saveJson($history.'/state.json',$state);
    show('Rollback V4 selesai; database dan modul lain tidak disentuh.');
}
try {
    if (!in_array($action,['check','apply','rollback'],true)) throw new RuntimeException('Aksi tidak valid.');
    if ($action==='rollback') {rollback($project);exit(0);}
    if ($inHistory) throw new RuntimeException('Check/apply hanya dari folder ZIP.');
    $result=preflight($project);
    if ($result['already']) {show('Sudah terpasang. Tidak ada file diubah.');exit(0);}
    show('Target: '.$project);
    foreach (array_keys($result['files']) as $name) show('TAMBAH '.$name);
    show('Tidak mengubah .env, database, source PHP lama, CSS atau vendor.');
    if ($action==='check') {show('CEK LULUS, belum ada modifikasi.');exit(0);}
    [$history,$trash]=containers($project);
    dirMake($history);dirMake($trash);
    saveJson($trash.'/snapshot.json',['patch'=>PATCH_ID,'old_files'=>'none (add-only)','note'=>'Tidak ada file sumber lama yang ditimpa.']);
    $state=['patch_id'=>PATCH_ID,'status'=>'pending','installed_at'=>date(DATE_ATOM),'files'=>$result['files']];
    saveJson($history.'/state.json',$state);
    $created=[];
    try {
        foreach ($result['files'] as $rel=>$sha) {
            $source=safeFile(__DIR__.'/payload',$rel);$destination=safeFile($project,$rel);
            dirMake(dirname($destination));
            if (file_exists($destination) || !@copy($source,$destination) || hash_file('sha256',$destination)!==$sha) throw new RuntimeException('Penyalinan gagal: '.$rel);
            $created[]=$rel;
        }
        if (!@copy(__FILE__,$history.'/upgrade.php') || !@copy(__DIR__.'/JALANKAN-ROLLBACK.bat',$history.'/JALANKAN-ROLLBACK.bat')) throw new RuntimeException('Gagal menyimpan mesin rollback internal.');
        $state['status']='applied';saveJson($history.'/state.json',$state);
    } catch (Throwable $e) {
        foreach ($created as $rel) {
            $target=safeFile($project,$rel);
            if (is_file($target) && hash_file('sha256',$target)===$result['files'][$rel]) @unlink($target);
        }
        $state['status']='aborted';saveJson($history.'/state.json',$state);
        throw $e;
    }
    show('Upgrade V4.0 berhasil. Buka Admin > Pengaturan & Utilitas > Ringkasan Hak Akses.');
    show('Jika menu belum muncul, jalankan JALANKAN-REFRESH-CACHE.bat.');
    show('Rollback tersimpan di: .vhistory/patches/'.PATCH_ID.'/JALANKAN-ROLLBACK.bat');
} catch (Throwable $e) {failNow($e->getMessage());}

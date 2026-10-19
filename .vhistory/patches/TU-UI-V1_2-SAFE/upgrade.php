<?php
/**
 * TU UI V1.2 — non-destructive CSS installer, PHP CLI >= 8.1.
 * Does not run database migration, Composer, npm, shell, downloads, or external network.
 * Only edits the Filament head-hook by APPENDING a CSS link, plus a new CSS file.
 * Never edits app/Filament/Widgets/WorkspaceOverviewWidget.php.
 */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Hanya PHP CLI.'); }
if (version_compare(PHP_VERSION, '8.1.0', '<')) { fwrite(STDERR,"[GAGAL] PHP 8.1+ diperlukan.\n"); exit(1); }
const PATCH = 'TU-UI-V1_2-SAFE';
const CSS_REL = 'public/css/tu-ui-v12-safe.css';
const HOOK_REL = 'resources/views/filament/hooks/vite.blade.php';
const HEAD_MARKER = 'TU-UI-V1.2-ADDITIVE-CSS';
$inHistory = basename(dirname(__DIR__)) === 'patches' && basename(dirname(dirname(__DIR__))) === '.vhistory';
$project = $inHistory ? dirname(dirname(dirname(__DIR__))) : dirname(__DIR__) . DIRECTORY_SEPARATOR . 'pencatatan-teknikal-umum-main';
$action = $argv[1] ?? 'check';

function failSafe(string $msg): never { fwrite(STDERR, "[GAGAL] {$msg}\n"); exit(1); }
function logInfo(string $msg): void { echo "[TU UI V1.2] {$msg}\n"; }
function requireDirectory(string $dir): void { if (is_link($dir) || !is_dir($dir)) throw new RuntimeException("Folder tidak aman/tidak ditemukan: {$dir}"); }
function makeDirectory(string $dir): void { if (is_link($dir)) throw new RuntimeException("Symlink ditolak: {$dir}"); if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) throw new RuntimeException("Tidak bisa membuat folder: {$dir}"); }
function checkSafePath(string $base, string $relative): string {
    $segments = explode('/', $relative);
    $path = $base;
    foreach ($segments as $s) {
        if ($s === '' || $s === '.' || $s === '..' || str_contains($s, ':') || str_contains($s, '\\')) throw new RuntimeException('Path tidak aman.');
        $path .= DIRECTORY_SEPARATOR . $s;
        if (is_link($path)) throw new RuntimeException('Symlink target ditolak: '. $relative);
    }
    if (is_dir($path)) throw new RuntimeException('Target adalah folder: '. $relative);
    return $path;
}
function readJson(string $path): array {
    $raw = @file_get_contents($path);
    if ($raw === false) throw new RuntimeException("Tidak bisa membaca {$path}");
    $json = json_decode($raw,true);
    if (!is_array($json) || json_last_error() !== JSON_ERROR_NONE) throw new RuntimeException("JSON tidak valid: {$path}");
    return $json;
}
function saveJson(string $path, array $data): void {
    $raw = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($raw === false || file_put_contents($path, $raw."\n", LOCK_EX) === false) throw new RuntimeException("Tidak bisa menyimpan: {$path}");
}
function writeFile(string $path, string $data): void { if (file_put_contents($path,$data,LOCK_EX) === false) throw new RuntimeException("Gagal menulis: {$path}"); }
function installLink(string $hook): string {
    if (str_contains($hook, HEAD_MARKER)) return $hook;
    $snippet = "\n{{-- ".HEAD_MARKER." --}}\n<link rel=\"stylesheet\" href=\"{{ asset('css/tu-ui-v12-safe.css') }}?v=20261019-12\" />\n";
    return rtrim($hook, "\r\n") . $snippet;
}
function payloadPath(): string { return __DIR__.'/payload/'.CSS_REL; }
function statePath(string $project): string { return $project.'/.vhistory/patches/'.PATCH.'/state.json'; }
function backupsPath(string $project): string { return $project.'/.trash/'.PATCH; }
function preflight(string $project): array {
    requireDirectory($project);
    if (!is_file($project.'/artisan') || !is_file($project.'/composer.json')) throw new RuntimeException('Target bukan root Laravel.');
    $provider = checkSafePath($project,'app/Providers/Filament/AdminPanelProvider.php');
    if (!is_file($provider) || !str_contains((string)file_get_contents($provider), "filament.hooks.vite")) throw new RuntimeException('Render hook Filament tidak ditemukan; pemasangan dihentikan.');
    $hookPath = checkSafePath($project,HOOK_REL);
    $cssPath = checkSafePath($project,CSS_REL);
    if (!is_file($hookPath)) throw new RuntimeException('View hook Filament tidak ditemukan: '.HOOK_REL);
    if (is_file($cssPath) && !is_readable($cssPath)) throw new RuntimeException('Tidak dapat membaca CSS lokal.');
    foreach (['.trash','.vhistory'] as $folder) {
        $p = $project.'/'.$folder;
        if (is_link($p) || (file_exists($p) && !is_dir($p))) throw new RuntimeException('Folder internal tidak aman: '.$folder);
    }
    $manifest = readJson(__DIR__.'/manifest.json');
    if (($manifest['patch_id'] ?? '') !== PATCH || ($manifest['target_folder'] ?? '') !== basename($project) || ($manifest['payload'] ?? '') !== CSS_REL || ($manifest['hook'] ?? '') !== HOOK_REL) throw new RuntimeException('Manifest bukan untuk proyek ini.');
    $payload = payloadPath();
    if (is_link($payload) || !is_file($payload) || hash_file('sha256',$payload) !== ($manifest['sha256'] ?? '')) throw new RuntimeException('Checksum file CSS dalam ZIP tidak valid.');
    $existingCss = is_file($cssPath) ? file_get_contents($cssPath) : null;
    if ($existingCss !== null && hash('sha256',$existingCss) !== $manifest['sha256']) throw new RuntimeException('File CSS V1.2 sudah ada namun berbeda. Tidak ditimpa.');
    $hook = file_get_contents($hookPath);
    if ($hook === false) throw new RuntimeException('Hook tidak bisa dibaca.');
    $next = installLink($hook);
    if (!str_contains($next, "asset('css/tu-ui-v12-safe.css')")) throw new RuntimeException('Link CSS tidak ditemukan dalam hook hasil.');
    $stateFile = statePath($project);
    if (is_file($stateFile)) {
        $state = readJson($stateFile);
        if (($state['status'] ?? '') === 'pending') throw new RuntimeException('Paket sebelumnya masih pending. Jangan timpa backup; perlu pemeriksaan manual.');
        if (($state['status'] ?? '') === 'applied') {
            $expected = $state['new_hashes'] ?? [];
            if (hash('sha256',$hook) === ($expected[HOOK_REL] ?? '') && is_file($cssPath) && hash_file('sha256',$cssPath) === ($expected[CSS_REL] ?? '')) return ['already'=>true];
            throw new RuntimeException('Patch tercatat terpasang, tetapi isi file berubah. Jangan timpa perubahan lokal.');
        }
    }
    return ['already'=>false, 'hook'=>$hook,'next'=>$next,'hook_path'=>$hookPath,'css_path'=>$cssPath,'css_old'=>$existingCss,'css_new'=>(string)file_get_contents($payload),'hook_new_hash'=>hash('sha256',$next),'css_new_hash'=>$manifest['sha256']];
}
function rollback(string $project): void {
    $stateFile = statePath($project);
    if (!is_file($stateFile)) throw new RuntimeException('Tidak ada riwayat V1.2 untuk rollback.');
    $state = readJson($stateFile);
    if (($state['status'] ?? '') === 'rolled_back') { logInfo('V1.2 sudah pernah di-rollback.'); return; }
    if (($state['status'] ?? '') !== 'applied') throw new RuntimeException('Status riwayat bukan applied.');
    $hook = checkSafePath($project,HOOK_REL);
    $css = checkSafePath($project,CSS_REL);
    foreach ([HOOK_REL=>$hook, CSS_REL=>$css] as $rel=>$file) {
        if (!is_file($file) || hash_file('sha256',$file) !== ($state['new_hashes'][$rel] ?? '')) throw new RuntimeException('File berubah setelah pemasangan: '.$rel.'. Rollback otomatis ditolak supaya tidak menghapus perubahan baru.');
    }
    $backup = backupsPath($project);
    $oldHook = $backup.'/hook.blade.php.bak';
    if (!is_file($oldHook) || hash_file('sha256',$oldHook) !== ($state['old_hashes'][HOOK_REL] ?? '')) throw new RuntimeException('Backup hook hilang atau korup.');
    $cssOldExists = (bool)($state['css_old_exists'] ?? false);
    if ($cssOldExists) {
        $oldCss = $backup.'/old-style.css.bak';
        if (!is_file($oldCss) || hash_file('sha256',$oldCss) !== ($state['old_hashes'][CSS_REL] ?? '')) throw new RuntimeException('Backup CSS hilang/korup.');
    }
    writeFile($hook, (string)file_get_contents($oldHook));
    if ($cssOldExists) { writeFile($css, (string)file_get_contents($backup.'/old-style.css.bak')); }
    elseif (!unlink($css)) throw new RuntimeException('Gagal menghapus CSS milik patch.');
    $state['status'] = 'rolled_back'; $state['rolled_back_at'] = date('c');
    saveJson($stateFile,$state);
    logInfo('Rollback berhasil. File PHP widget tidak pernah diubah.');
}

try {
    if (!in_array($action,['check','apply','rollback'],true)) throw new RuntimeException('Gunakan check, apply, atau rollback.');
    if ($action === 'rollback') { rollback($project); exit(0); }
    if ($inHistory) throw new RuntimeException('Jalankan check/apply dari folder ZIP upgrader, bukan .vhistory.');
    $validated = preflight($project);
    if ($validated['already']) { logInfo('V1.2 sudah terpasang. Tidak ada perubahan.'); exit(0); }
    logInfo('Target: '.$project);
    logInfo('AMAN: '.HOOK_REL.' (hanya menambah tautan CSS)');
    logInfo('AMAN: '.CSS_REL.' (file CSS tambahan)');
    logInfo('DITINGGALKAN UTUH: app/Filament/Widgets/WorkspaceOverviewWidget.php');
    logInfo('DITINGGALKAN UTUH: dashboard Blade, .env, database, storage, vendor.');
    if ($action === 'check') { logInfo('Pemeriksaan OK, belum mengubah apa pun.'); exit(0); }
    $backup=backupsPath($project);
    $history=$project.'/.vhistory/patches/'.PATCH;
    if (file_exists($backup) && !is_dir($backup)) throw new RuntimeException('Folder backup konflik.');
    if (is_link($backup) || is_link($history)) throw new RuntimeException('Symlink pada folder backup/history ditolak.');
    makeDirectory($backup); makeDirectory($history);
    $hookBackup=$backup.'/hook.blade.php.bak';
    if (is_file($hookBackup)) throw new RuntimeException('Backup V1.2 sudah ada. Cek status patch sebelum mengulang.');
    if (file_put_contents($hookBackup,$validated['hook'],LOCK_EX) === false) throw new RuntimeException('Gagal membuat backup hook.');
    if ($validated['css_old'] !== null) writeFile($backup.'/old-style.css.bak', $validated['css_old']);
    $state=[
        'patch_id'=>PATCH,'status'=>'pending','applied_at'=>date('c'),
        'old_hashes'=>[HOOK_REL=>hash('sha256',$validated['hook']),CSS_REL=>$validated['css_old']===null ? null : hash('sha256',$validated['css_old'])],
        'new_hashes'=>[HOOK_REL=>$validated['hook_new_hash'],CSS_REL=>$validated['css_new_hash']],
        'css_old_exists'=>$validated['css_old']!==null,
        'backup_dir'=>'.trash/'.PATCH,
    ];
    saveJson($history.'/state.json',$state);
    try {
        writeFile($validated['css_path'],$validated['css_new']);
        if ($validated['hook'] !== $validated['next']) writeFile($validated['hook_path'],$validated['next']);
        if (hash_file('sha256',$validated['css_path']) !== $validated['css_new_hash'] || hash_file('sha256',$validated['hook_path']) !== $validated['hook_new_hash']) throw new RuntimeException('Verifikasi checksum setelah pemasangan gagal.');
        $state['status']='applied';
        saveJson($history.'/state.json',$state);
    } catch (Throwable $e) {
        writeFile($validated['hook_path'],$validated['hook']);
        if ($validated['css_old']!==null) writeFile($validated['css_path'],$validated['css_old']);
        elseif (is_file($validated['css_path'])) @unlink($validated['css_path']);
        $state['status']='aborted'; saveJson($history.'/state.json',$state);
        throw $e;
    }
    if (!copy(__FILE__,$history.'/upgrade.php')) throw new RuntimeException('Rollback engine tidak dapat disalin. File aplikasi sudah terpasang; jangan hapus riwayat.');
    if (!copy(__DIR__.'/JALANKAN-ROLLBACK.bat',$history.'/JALANKAN-ROLLBACK.bat')) throw new RuntimeException('Launcher rollback tidak dapat disalin.');
    logInfo('Berhasil. Backup: .trash/'.PATCH.'; rollback: .vhistory/patches/'.PATCH.'/JALANKAN-ROLLBACK.bat');
    logInfo('Refresh browser Ctrl+F5. Tidak perlu npm build untuk CSS ini.');
} catch (Throwable $e) { failSafe($e->getMessage()); }

@echo off
setlocal EnableExtensions DisableDelayedExpansion
cd /d "%~dp0" || goto :gagal
set "PHP_EXE="
for /f "delims=" %%P in ('where php.exe 2^>nul') do if not defined PHP_EXE set "PHP_EXE=%%P"
if not defined PHP_EXE for /d %%D in ("%SystemDrive%\laragon\bin\php\*") do if not defined PHP_EXE if exist "%%~fD\php.exe" set "PHP_EXE=%%~fD\php.exe"
if not defined PHP_EXE goto :php_hilang
echo === SISTEM TU - ROLLBACK V3.1 ===
choice /C YN /N /M "Hapus file baru V3 tanpa menyentuh database [Y/N]: "
if errorlevel 2 goto :selesai
"%PHP_EXE%" "%~dp0upgrade.php" rollback
if errorlevel 1 goto :gagal
goto :selesai

:php_hilang
echo [GAGAL] PHP CLI tidak ditemukan. Aktifkan Laragon.
:gagal
echo [INFO] File proyek lokal tidak akan dipaksa ditimpa.
pause
exit /b 1
:selesai
pause
exit /b 0

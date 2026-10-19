@echo off
setlocal EnableExtensions DisableDelayedExpansion
cd /d "%~dp0" || goto :gagal
set "PHP_EXE="
for /f "delims=" %%P in ('where php.exe 2^>nul') do if not defined PHP_EXE set "PHP_EXE=%%P"
if not defined PHP_EXE for /d %%D in ("%SystemDrive%\laragon\bin\php\*") do if not defined PHP_EXE if exist "%%~fD\php.exe" set "PHP_EXE=%%~fD\php.exe"
if not defined PHP_EXE goto :gagal
choice /C YN /N /M "Kembalikan file seperti sebelum UI V2.0? [Y/N]: "
if errorlevel 2 goto :batal
"%PHP_EXE%" "%~dp0upgrade.php" rollback
if errorlevel 1 goto :gagal
echo [OK] Rollback berhasil.
:batal
pause
exit /b 0
:gagal
echo [GAGAL] Rollback gagal atau PHP tidak ditemukan. File mungkin berubah setelah upgrade.
pause
exit /b 1

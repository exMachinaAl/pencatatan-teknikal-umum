@echo off
setlocal EnableExtensions DisableDelayedExpansion
cd /d "%~dp0" || goto :error
call :findphp
if not defined PHP_EXE goto :missing
choice /C YN /N /M "Rollback file V4.0? [Y/N]: "
if errorlevel 2 goto :done
"%PHP_EXE%" "%~dp0upgrade.php" rollback
if errorlevel 1 goto :error
goto :done
:findphp
for /f "delims=" %%P in ('where php.exe 2^>nul') do if not defined PHP_EXE set "PHP_EXE=%%P"
if not defined PHP_EXE for /d %%D in ("%SystemDrive%\laragon\bin\php\*") do if not defined PHP_EXE if exist "%%~fD\php.exe" set "PHP_EXE=%%~fD\php.exe"
exit /b
:missing
echo [GAGAL] PHP CLI tidak tersedia.
:error
pause
exit /b 1
:done
pause
exit /b 0

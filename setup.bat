@echo off
chcp 65001 >nul
setlocal
cd /d "%~dp0"
title Fikrlash.uz - o'rnatish

echo.
echo ===============================================
echo   Fikrlash.uz - lokal o'rnatish (bir marta)
echo ===============================================
echo.

call scripts\find-tools.bat

where php >nul 2>nul || goto :nophp
where composer >nul 2>nul || goto :nocomposer
where npm >nul 2>nul || goto :nonode

for /f "delims=" %%P in ('where php') do (echo   PHP:      %%P& goto :php_ok)
:php_ok
for /f "delims=" %%P in ('where npm') do (echo   npm:      %%P& goto :npm_ok)
:npm_ok

set "LOG=%~dp0setup.log"
echo Fikrlash.uz setup %DATE% %TIME% > "%LOG%"

php scripts\setup-local.php
if errorlevel 1 goto :fail_noLog

echo [1/5] PHP paketlari o'rnatilmoqda (composer install, 1-3 daqiqa)...
call composer install --no-interaction --no-progress >> "%LOG%" 2>&1
if errorlevel 1 goto :fail

echo [2/5] Baza va demo ma'lumotlar...
php artisan migrate:fresh --seed --force >> "%LOG%" 2>&1
if errorlevel 1 goto :fail

echo [3/5] Rasmlar papkasi...
if not exist public\storage php artisan storage:link >> "%LOG%" 2>&1

echo [4/5] Frontend paketlari (npm install, 1-3 daqiqa)...
call npm install --no-audit --no-fund >> "%LOG%" 2>&1
if errorlevel 1 goto :fail

echo [5/5] CSS va JS yig'ilmoqda (npm run build)...
call npm run build >> "%LOG%" 2>&1
if errorlevel 1 goto :fail

echo.
echo ===============================================
echo   Tayyor! Endi start.bat ni ishga tushiring.
echo   Admin: +998900000001 / admin12345
echo ===============================================
echo.
pause
exit /b 0

:nophp
echo PHP topilmadi.
echo Laragon o'rnatilgan bo'lsa, u C:\laragon da ekanini tekshiring
echo (boshqa joyda bo'lsa: Laragon menyusi - Tools - Path - Add Laragon to Path).
echo Yoki Laravel Herd o'rnating: herd.laravel.com
pause
exit /b 1

:nocomposer
echo Composer topilmadi (Laragon'da C:\laragon\bin\composer bo'lishi kerak).
pause
exit /b 1

:nonode
echo Node.js (npm) topilmadi. Laragon'da C:\laragon\bin\nodejs bo'lishi kerak, yoki nodejs.org dan LTS o'rnating.
pause
exit /b 1

:fail
echo.
echo XATOLIK. Oxirgi qatorlar (to'liq log: setup.log):
echo -----------------------------------------------
powershell -NoProfile -Command "Get-Content -Path '%LOG%' -Tail 25"
echo -----------------------------------------------
pause
exit /b 1

:fail_noLog
echo.
echo Yuqoridagi muammoni tuzatib, setup.bat ni qayta ishga tushiring.
pause
exit /b 1

@echo off
chcp 65001 >nul
setlocal
cd /d "%~dp0"
title Fikrlash.uz - o'rnatish

echo.
echo ===============================================
echo   Fikrlash.uz - lokal o'rnatish (bir marta)
echo ===============================================

where php >nul 2>nul || goto :nophp
where composer >nul 2>nul || goto :nocomposer
where npm >nul 2>nul || goto :nonode

php scripts\setup-local.php || goto :fail

echo [1/5] PHP paketlari (composer install)...
call composer install --no-interaction || goto :fail

echo [2/5] Baza va demo ma'lumotlar...
php artisan migrate:fresh --seed --force || goto :fail

echo [3/5] Rasmlar papkasi...
php artisan storage:link

echo [4/5] Frontend paketlari (npm install)...
call npm install || goto :fail

echo [5/5] CSS/JS yig'ish (npm run build)...
call npm run build || goto :fail

echo.
echo ===============================================
echo   Tayyor! Endi start.bat ni ishga tushiring.
echo   Admin: +998900000001 / admin12345
echo ===============================================
pause
exit /b 0

:nophp
echo.
echo PHP topilmadi. Eng oson yo'l: Laravel Herd (herd.laravel.com) yoki Laragon (laragon.org) o'rnating,
echo so'ng bu oynani yopib, setup.bat ni qayta ishga tushiring.
pause
exit /b 1

:nocomposer
echo.
echo Composer topilmadi: getcomposer.org/download (Herd va Laragon'da tayyor bo'ladi).
pause
exit /b 1

:nonode
echo.
echo Node.js (npm) topilmadi: nodejs.org dan LTS versiyani o'rnating.
pause
exit /b 1

:fail
echo.
echo Xatolik yuz berdi. Yuqoridagi xabarni o'qing yoki README.md ning "Muammolarni hal qilish" bo'limiga qarang.
pause
exit /b 1

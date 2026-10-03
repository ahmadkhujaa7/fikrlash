@echo off
chcp 65001 >nul
setlocal
cd /d "%~dp0"
title Fikrlash.uz - server (to'xtatish uchun oynani yoping)

call scripts\find-tools.bat

where php >nul 2>nul || (echo PHP topilmadi. Avval setup.bat ni ishga tushiring. & pause & exit /b 1)
if not exist vendor\autoload.php (echo vendor papkasi yo'q - avval setup.bat ni ishga tushiring. & pause & exit /b 1)
if not exist .env (echo .env fayli yo'q - avval setup.bat ni ishga tushiring. & pause & exit /b 1)
if not exist public\build\manifest.json (echo CSS/JS yig'ilmagan - setup.bat ni qayta ishga tushiring. & pause & exit /b 1)

echo.
echo   Fikrlash.uz ishga tushdi:  http://localhost:8000
echo   Admin panel:              http://localhost:8000/admin
echo   Admin:                    +998900000001 / admin12345
echo   SMS kodlari:              storage\logs\laravel.log
echo   To'xtatish: Ctrl+C yoki oynani yoping.
echo.
start "" http://localhost:8000
php artisan serve --host=localhost --port=8000
echo.
echo Server to'xtadi.
pause

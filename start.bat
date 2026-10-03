@echo off
chcp 65001 >nul
cd /d "%~dp0"
title Fikrlash.uz - server (to'xtatish uchun shu oynani yoping)

if not exist vendor\autoload.php (
    echo Avval setup.bat ni ishga tushiring.
    pause
    exit /b 1
)

echo.
echo   Fikrlash.uz ishga tushdi: http://localhost:8000
echo   Admin panel:             http://localhost:8000/admin
echo   SMS kodlari:             storage\logs\laravel.log
echo   To'xtatish: Ctrl+C yoki oynani yoping.
echo.
start "" http://localhost:8000
php artisan serve --host=localhost --port=8000

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

rem Port 8000 band bo'lsa (avvalgi server ochiq qolgan) - ogohlantiramiz.
netstat -ano | findstr /r /c:":8000 .*LISTENING" >nul && (
    echo Port 8000 band: Fikrlash allaqachon ishlayotgan bo'lishi mumkin. Brauzerda http://localhost:8000 ni oching
    echo yoki boshqa server oynasini yopib, start.bat ni qayta ishga tushiring.
    start "" http://localhost:8000
    pause
    exit /b 0
)

echo.
echo   Fikrlash.uz ishga tushmoqda:  http://localhost:8000
echo   Admin panel:                 http://localhost:8000/admin
echo   Admin:                       +998900000001 / admin12345
echo   SMS kodlari:                 storage\logs\laravel.log
echo   To'xtatish: shu oynani yoping (yoki Ctrl+C).
echo.

rem Brauzer server tayyor bo'lgandan keyin ochiladi (avval ochilsa "sahifa topilmadi" chiqadi).
start "" /min powershell -NoProfile -WindowStyle Hidden -Command "for($i=0;$i -lt 40;$i++){try{Invoke-WebRequest 'http://127.0.0.1:8000/up' -UseBasicParsing -TimeoutSec 2 | Out-Null; Start-Process 'http://localhost:8000'; break}catch{Start-Sleep -Milliseconds 500}}"

php artisan serve --host=127.0.0.1 --port=8000
echo.
echo Server to'xtadi. Yuqoridagi xabarni o'qing.
pause

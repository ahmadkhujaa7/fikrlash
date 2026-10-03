@echo off
rem PHP, Composer va Node.js ni topib PATH ga qo'shadi.
rem Laragon/Herd vositalari odatda faqat ularning o'z terminalida PATH da bo'ladi,
rem .bat faylni ikki marta bosganda esa topilmaydi - shuning uchun shu yerda qidiramiz.

if not defined LARAGON_DIR set "LARAGON_DIR=C:\laragon"

where php >nul 2>nul
if errorlevel 1 (
    for /d %%D in ("%LARAGON_DIR%\bin\php\php-8.*") do set "FIKR_PHP=%%~fD"
    if exist "%USERPROFILE%\.config\herd\bin\php.bat" set "FIKR_HERD=%USERPROFILE%\.config\herd\bin"
)
if defined FIKR_PHP set "PATH=%FIKR_PHP%;%PATH%"
if defined FIKR_HERD set "PATH=%FIKR_HERD%;%PATH%"

where composer >nul 2>nul
if errorlevel 1 if exist "%LARAGON_DIR%\bin\composer\composer.bat" set "PATH=%LARAGON_DIR%\bin\composer;%PATH%"

where npm >nul 2>nul
if errorlevel 1 (
    for /d %%D in ("%LARAGON_DIR%\bin\nodejs\node-*") do set "FIKR_NODE=%%~fD"
)
if defined FIKR_NODE set "PATH=%FIKR_NODE%;%PATH%"

exit /b 0

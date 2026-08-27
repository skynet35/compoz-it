@echo off
REM =============================================================
REM  Modèle : Serveur PHP local CompoZ'IT (à adapter à votre PC)
REM  1. Copiez ce fichier →  start_server.bat
REM  2. Editez PHP_PATH si besoin (chemin vers votre php.exe)
REM  3. Double-cliquez pour démarrer !
REM =============================================================

REM --- Chemin vers votre PHP.exe (adaptez si XAMPP/WAMP est ailleurs) ---
set PHP_PATH=C:\xampp\php\php.exe

REM --- Port HTTP du serveur dev ---
set PORT=8000

REM --- On se place dans le même dossier que le .bat (racine du site) ---
cd /d "%~dp0"

echo.
echo =======================================================
echo    Démarrage serveur local CompoZ'IT
echo =======================================================
echo    PHP   : %PHP_PATH%
echo    URL   : http://localhost:%PORT%/
echo    Dossier racine : %cd%
echo =======================================================
echo.
echo  (Appuyez sur Ctrl+C pour arreter le serveur)
echo.

"%PHP_PATH%" -S localhost:%PORT%
pause

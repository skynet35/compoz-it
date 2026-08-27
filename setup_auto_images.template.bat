@echo off
REM =============================================================
REM  Modèle : Configuration de l'assignation auto des images packages
REM  1. Copiez ce fichier →  setup_auto_images.bat
REM  2. Editez PHP_PATH si XAMPP/WAMP n'est pas installé à l'emplacement standard
REM  3. Exécutez en tant qu'Administrateur
REM =============================================================

REM --- Chemin vers votre PHP.exe (adaptez si besoin) ---
set PHP_PATH=C:\xampp\php\php.exe

REM --- Script PHP présent à la racine du site (NE PAS EDITER) ---
set SCRIPT_PATH=%~dp0auto_assign_package_images.php

REM --- Nom de la tâche planifiée Windows ---
set TASK_NAME=CompozIT_AutoAssignPackageImages

REM --- Vérifications ---
if not exist "%PHP_PATH%" (
    echo ERREUR : PHP non trouve a ^<%PHP_PATH%^>
    echo Installez XAMPP/WAMP ou adaptez PHP_PATH dans ce .bat
    pause
    exit /b 1
)
if not exist "%SCRIPT_PATH%" (
    echo ERREUR : Script non trouve a ^<%SCRIPT_PATH%^>
    pause
    exit /b 1
)

echo.
echo =============================================================
echo   Configuration de l'assignation auto des images packages
echo =============================================================
echo   PHP     : %PHP_PATH%
echo   Script  : %SCRIPT_PATH%
echo   Tache   : %TASK_NAME% (quotidienne 02h00)
echo =============================================================
echo.

schtasks /delete /tn "%TASK_NAME%" /f >nul 2>&1
schtasks /create /tn "%TASK_NAME%" /tr "\"%PHP_PATH%\" \"%SCRIPT_PATH%\"" /sc daily /st 02:00 /f

if %errorlevel% equ 0 (
    echo.
    echo [OK] Tache planifiee creee avec succes !
    echo.
    echo Commandes utiles (Invite cmd) :
    echo   - Voir details   : schtasks /query /tn "%TASK_NAME%" /v
    echo   - Lancer MAINTENANT : schtasks /run /tn "%TASK_NAME%"
    echo   - Supprimer      : schtasks /delete /tn "%TASK_NAME%" /f
    echo.
    echo Alternative : utilisez l'interface web package_images_manager.php
) else (
    echo.
    echo [ERREUR] Impossible de creer la tache planifiee.
    echo N'oubliez pas d'executer ce .bat en TANT QU'ADMINISTRATEUR.
)
echo.
pause

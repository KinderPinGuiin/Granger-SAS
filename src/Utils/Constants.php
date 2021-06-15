<?php

namespace App\Utils;

/**
 * Classe contenant les constantes de l'application
 */
class Constants {

    // Racine de l'application
    const ROOT = __DIR__ . "/../../";

    // Dossier Google
    const GOOGLE_FOLDER = self::ROOT . "/google/";

    // ID du dossier racine Google Drive
    const DRIVE_ROOT = "1D3vgBSlyekKEDwEAQ5C4KVYwWOOVnL-g";

    // Nom du dossier de CV
    const CV_FOLDER_NAME = "CV";

    // Nom du dossier lettre de motivation
    const LETTER_FOLDER_NAME = "Lettre de motivation";

    // Nom du fichier du CV
    const CV_FILE_NAME = "CV";

    // Nom du fichier de la lettre de motivation
    const LETTER_FILE_NAME = "Lettre de motivation";

}
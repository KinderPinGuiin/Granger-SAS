# Configuration

Ajoutez un fichier nommé Constants.php dans le dossier *src/Utils/* et collez-y le code suivant :
```php
<?php

namespace App\Utils;

/**
 * Classe contenant les constantes de l'application
 */
class Constants {

    // Racine de l'application
    const ROOT = __DIR__ . "/../../";

    // Dossier Google
    const GOOGLE_FOLDER = self::ROOT . "google/";

    // ID du dossier racine Google Drive
    const DRIVE_ROOT = "Identifiant de votre dossier";

    // Nom du dossier de CV
    const CV_FOLDER_NAME = "CV";

    // Nom du dossier lettre de motivation
    const LETTER_FOLDER_NAME = "Lettre de motivation";

    // Nom du fichier du CV
    const CV_FILE_NAME = "CV";

    // Nom du fichier de la lettre de motivation
    const LETTER_FILE_NAME = "Lettre de motivation";

}
```

Remplacez la ligne 17 ("*Identifiant de votre dossier*") par l'ID de votre dossier Google Drive.

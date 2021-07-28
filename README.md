# Ajout du fichier credentials.json

Ajoutez votre fichier credentials.json dans le dossier *google/*

# Configuration des constantes

Ajoutez un fichier nommé Constants.php dans le dossier *src/Utils/* et collez-y le code suivant :
```php
<?php

namespace App\Utils;

use App\Entity\User;

/**
 * Classe contenant les constantes de l'application
 */
class Constants {

    // Racine de l'application
    const ROOT = __DIR__ . "/../../";

    // Dossier Google
    const GOOGLE_FOLDER = self::ROOT . "google/";

    // Dossier public
    const PUBLIC_FOLDER = self::ROOT . "public/";

    // ID du dossier racine Google Drive
    const ID_DRIVE_ROOT = "1D3vgBSlyekKEDwEAQ5C4KVYwWOOVnL-g";

    // Nom du dossier de CV
    const CV_FOLDER_NAME = "CV";

    // Nom du dossier lettre de motivation
    const LETTER_FOLDER_NAME = "Lettre de motivation";

    // Nom du fichier du CV
    const CV_FILE_NAME = "CV";

    // Nom du fichier de la lettre de motivation
    const LETTER_FILE_NAME = "Lettre de motivation";

    // Image par défaut en cas de 404
    const DEFAULT_IMAGE = self::PUBLIC_FOLDER . "img/images/default.png";

    // Nom du dossier des candidats
    const DRIVE_FOLDER_NAME = "{{ prenom }} {{ nom }} | {{ mail }}";

    /**
     * Créé le nom du dossier d'un utilisateur
     * 
     * @param  User   $user L'utilisateur
     * @return string       Le nom du dossier
     */
    public static function folderName(User $user)
    {
        $folderName = self::DRIVE_FOLDER_NAME;
        $folderName = str_replace(
            ["{{ prenom }}", "{{ nom }}", "{{ mail }}"],
            [$user->getPrenom(), $user->getNom(), $user->getEmail()],
            $folderName
        );
        return $folderName;
    }

    /*
     * Status des utilisateurs
     */
    const DEFAULT_STATUS = "DEFAULT";
    const POSTULATED_STATUS = "POSTULATED";
    const ACCEPTED_STATUS = "ACCEPTED";
    const VERIFICATION_STATUS = "VERIFICATION";
    const DRIVER_STATUS = "DRIVER";

    /**
     * Etapes d'une embauche (Ajout de document)
     */
    const CANDIDAT_STEP = "CANDIDAT";
    const HIRE_STEP = "HIRE";
    const DRIVER_STEP = "DRIVER";

}
```

Remplacez la ligne 22 ("*Identifiant de votre dossier*") par l'ID de votre dossier Google Drive.

# Configuration du fichier .env.local

Créez un fichier **.env.local** à la racine du site web et collez-y le code suivant :

```shell
###> symfony/framework-bundle ###
APP_ENV=dev
APP_SECRET=03b3c2e91608adfe60d4a581ed8fa7de
###< symfony/framework-bundle ###

###> symfony/mailer ###
# MAILER_DSN=smtp://localhost
###< symfony/mailer ###

###> doctrine/doctrine-bundle ###
# Format described at https://www.doctrine-project.org/projects/doctrine-dbal/en/latest/reference/configuration.html#connecting-using-a-url
# IMPORTANT: You MUST configure your server version, either here or in config/packages/doctrine.yaml
#
# DATABASE_URL="sqlite:///%kernel.project_dir%/var/data.db"
DATABASE_URL="mysql://db_user:db_password@127.0.0.1:3306/db_name?serverVersion=5.7"
# DATABASE_URL="postgresql://db_user:db_password@127.0.0.1:5432/db_name?serverVersion=13&charset=utf8"
###< doctrine/doctrine-bundle ###

###> symfony/google-mailer ###
# Gmail SHOULD NOT be used on production, use it in development only.
MAILER_DSN=gmail://USERNAME:PASSWORD@default
###< symfony/google-mailer ###
```

Changez la ligne *DATABASE_URL=...* en remplaçant les informations correspondantes à votre base de données :

- db_user : Le nom d\'utilisateur permettant de se connecter à la base de données
- db_password : Le mot de passe permettant de se connecter à la base de données
- 127.0.0.1:3306 : L'adresse de connexion (Il n'y a normalement pas besoin d'y toucher)
- db_name : Le nom de la base de données à utiliser

Changez également la ligne *MAILER_DSN=...* afin de pouvoir envoyer des mails aux candidats via le site :

- USERNAME : Votre adresse email
- PASSWORD : Votre mot de passe Google **ou** votre mot de passe d\'application (Voir section suivante)

# Configuration du mot de passe des applications

**Cette section est nécessaire si votre compte Google dispose d\'une authentification à double facteur. Dans le cas contraire vous pouvez l'ignorer.**

Rendez-vous sur votre compte Google https://myaccount.google.com/ et cliquer sur la section *Sécurité*, sous *Se connecter à Google*, sélectionnez *Mots de passe des applications*. Séléctionnez une application "Autre" et nommez la "Granger SAS", séléctionnez un appareil (Sans importance) et cliquez sur *Générer*. 

Vous pouvez récupérer le code surligné en jaune et le coller à la place de PASSWORD dans votre fichier **.env.local**.

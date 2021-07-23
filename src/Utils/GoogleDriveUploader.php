<?php

namespace App\Utils;

use App\Utils\Constants;

class GoogleDriveUploader {
    /**
     * @var GoogleDriveManager
     */
    private $driveManager;

    public function __construct()
    {
        $this->driveManager = new GoogleDriveManager(
            Constants::GOOGLE_FOLDER . "credentials.json",
            Constants::ID_DRIVE_ROOT
        );
    }

    /**
     * Upload un fichier pour l'utilisateur demandé
     * 
     * @return bool True si tout s'est bien passé et false sinon
     */
    public function upload($user, $folderName, $fileName, $filePath) {
        try {
            // On clear la pile de dossier
            $this->driveManager->clearFolderStack();
            // On va dans le dossier de l'utilisateur
            $this->driveManager->goTo($user->getDriveID());
            // On vérifie que le dossier d'upload existe
            if (!$this->driveManager->fileExists($folderName, null, true)) {
                // S'il n'existe pas on le créé
                $this->driveManager->createFolder($folderName);
            }
            // On rentre dans le dossier et on upload le fichier
            $this->driveManager->goToName($folderName);
            // Si le fichier existe déjà on le supprime
            $this->deleteDuplicate($fileName);
            $this->driveManager->upload(
                $fileName,
                $filePath
            );

            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Supprime le fichier demandé s'il existe déjà
     * 
     * @param string $name Le nom du fichier
     */
    private function deleteDuplicate(string $name)
    {
        if (count($this->driveManager->relativeList()["files"]) > 0) {
            $this->driveManager->deleteByName($name);
        }
    }
}
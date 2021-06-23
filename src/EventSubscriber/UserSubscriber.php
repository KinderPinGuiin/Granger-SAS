<?php

namespace App\EventSubscriber;

use App\Utils\Constants;
use App\Utils\GoogleDriveManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class UserSubscriber implements EventSubscriberInterface
{

    /**
     * @var User
     */
    private $user;

    /**
     * @var EntityManagerInterface
     */
    private $em;

    public function __construct(Security $security, EntityManagerInterface $em)
    {
        $this->user = $security->getUser();
        $this->em = $em;
    }

    public function onKernelController()
    {
        if ($this->user) {
            // On recréé le dossier de l'utilisateur s'il a été supprimé
            $driveManager = new GoogleDriveManager(
                Constants::GOOGLE_FOLDER . "credentials.json",
                Constants::ID_DRIVE_ROOT
            );
            if (!$driveManager->fileExists(
                null, $this->user->getDriveID(), true
            )) {
                // Si le dossier n'existe pas on le recréé
                $folder = $driveManager->createFolder(
                    $this->user->getPrenom() . " " . $this->user->getNom() 
                    . " | " . $this->user->getEmail()
                );
                $this->user->setDriveID($folder["id"]);
                $this->em->flush();
                $driveManager->goTo($folder["id"]);
                $driveManager->createFolder(Constants::LETTER_FOLDER_NAME);
                $driveManager->createFolder(Constants::CV_FOLDER_NAME);
            }
        }
    }

    public static function getSubscribedEvents()
    {
        return [
            KernelEvents::CONTROLLER => 'onKernelController',
        ];
    }

}
<?php

namespace App\EventSubscriber;

use App\Utils\Constants;
use App\Utils\GoogleDriveManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\Routing\Generator\UrlGenerator;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

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

    /**
     * @var UrlGeneratorInterface
     */
    private $urlGen;

    public function __construct(Security $security, EntityManagerInterface $em, UrlGeneratorInterface $urlGen)
    {
        $this->user = $security->getUser();
        $this->em = $em;
        $this->urlGen = $urlGen;
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

    public function onKernelResponse(ResponseEvent $event)
    {
        $session = $event->getRequest()->getSession();
        $redirect = $session->get("redirect");
        if ($this->user && $redirect !== null) {
            // On redirige l'utilisateur là où il souhaite aller
            $session->set("redirect", null);
            return $event->setResponse(new RedirectResponse(
                $this->urlGen->generate($redirect)
            ));
        }
    }

    public static function getSubscribedEvents()
    {
        return [
            KernelEvents::CONTROLLER => 'onKernelController',
            KernelEvents::RESPONSE => "onKernelResponse"
        ];
    }

}
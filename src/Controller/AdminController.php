<?php

namespace App\Controller;

use App\Utils\Constants;
use App\Utils\GoogleDriveManager;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

/**
 * @Route("/admin", name="admin")
 */
class AdminController extends AbstractController {

    /**
     * @var GoogleDriveManager
     */
    private $driveManager;

    public function __construct()
    {
        $this->driveManager = new GoogleDriveManager(
            Constants::GOOGLE_FOLDER . "credentials.json",
            Constants::DRIVE_ROOT
        );
    }

    /**
     * @Route("/", name="")
     * 
     * @return mixed RedirectResponse ou Response
     */
    public function admin()
    {
        if (!$this->checkAccess()) {
            return $this->redirectToRoute("home");
        }

        return $this->render("admin/index.html.twig");
    }

    /**
     * @Route("/candidatures", name="_candidatures")
     * 
     * @return mixed RedirectResponse ou Response
     */
    public function adminCandidatures()
    {
        if (!$this->checkAccess()) {
            return $this->redirectToRoute("home");
        }

        return $this->render("admin/candidatures.html.twig");
    }

    /**
     * @Route("/candidature/{driveId}", name="_candidature")
     * 
     * @return mixed RedirectResponse ou Response
     */
    public function adminCandidature(string $driveId)
    {
        if (!$this->checkAccess()) {
            return $this->redirectToRoute("home");
        }
        // On cherche le dossier correspondant au driveId
        $dontExist = false;
        $didntUpload = false;
        if (!$this->driveManager->goTo($driveId)) {
            // Si on ne le trouve pas on définit la variable dontExist à true
            $dontExist = true;
        } else {
            // Si on le trouve on vérifie s'il a déjà déposé des fichiers
            $this->driveManager->goToName(Constants::CV_FOLDER_NAME);
            if (empty($this->driveManager->relativeList()["files"])) {
                $didntUpload = true;
            } else {
                dump($this->driveManager->relativeList()["files"][0]);
            }
        }

        return $this->render("admin/candidature.html.twig", [
            "dontExist" => $dontExist,
            "didntUpload" => $didntUpload
        ]);
    }

    /**
     * @Route("/edit", name="_edit")
     * 
     * @return mixed RedirectResponse ou Response
     */
    public function adminEdit()
    {
        if (!$this->checkAccess()) {
            return $this->redirectToRoute("home");
        }

        return $this->render("admin/edit.html.twig");
    }

    /**
     * Redirige l'utilisateur à l'accueil s'il n'est pas autorisé à accéder
     * à la oage d'administration
     * 
     * @return bool true si l'utilisateur a accès et false sinon
     */
    private function checkAccess(): bool
    {
        return !empty($this->getUser()) 
               && in_array("ROLE_ADMIN", $this->getUser()->getRoles());
    }

}
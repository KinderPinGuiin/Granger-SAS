<?php

namespace App\Controller;

use App\Form\UploadType;
use App\Utils\Constants;
use App\Utils\GoogleDriveManager;
use Exception;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class UploadController extends AbstractController
{
    /**
     * @var Request
     */
    private $req;

    /**
     * Indique si des fichiers ont été déposés sur le drive ou non
     * @var bool
     */
    private $isUploaded = false;

    /**
     * Contient les erreurs du formulaire
     * @var array
     */
    private $formErrors = [];

    /**
     * @Route("/upload", name="upload")
     */
    public function index(Request $req): Response
    {
        // Si l'utilisateur n'est pas connecté on le redirige sur l'accueil
        if (empty($this->getUser())) {
            return $this->redirectToRoute("home");
        }
        // On regarde si des fichiers ont été déposés
        $this->req = $req;
        $uploadForm = $this->handleUpload();
        // Si les fichiers ont été déposés on redirige l'utilisateur à l'accueil
        if ($this->isUploaded) {
            return $this->redirectToRoute("home", [
                "message" => "Fichiers déposés avec succès"
            ]);
        }

        return $this->render('upload/index.html.twig', [
            "uploadForm" => $uploadForm->createView(),
            "formErrors" => $this->formErrors
        ]);
    }

    /**
     * Prend en charge le formulaire d'upload et créé des pièces sur le drive
     * si des fichiers ont été déposés. Renvoie le formulaire créé
     * 
     * @return FormInterface
     */
    private function handleUpload(): FormInterface
    {
        $driveManager = new GoogleDriveManager(
            Constants::GOOGLE_FOLDER . "credentials.json",
            Constants::DRIVE_ROOT
        );
        $form = $this->createForm(UploadType::class);
        $form->handleRequest($this->req);
        // On vérifie les données du formulaire
        if ($form->isSubmitted() && $form->isValid()) {
            // Si tout est bon on upload les fichiers sur le drive
            $driveManager->goTo($this->getUser()->getDriveID());
            $driveManager->goToName(Constants::CV_FOLDER_NAME);
            $driveManager->upload(
                "CV", $form->get("cv")->getData()->getPathname()
            );
            $driveManager->back();
            $driveManager->goToName(Constants::LETTRE_FOLDER_NAME);
            try {
                $driveManager->upload(
                    "Lettre", $form->get("lettre")->getData()->getPathname()
                );
            } catch (Exception $e) {
                // En cas d'erreur on l'ajoute au formulaire
                $this->formErrors[] = "Erreur lors du dépôt, veuillez" 
                                      . " réessayer";
                return $form;
            }
            $this->isUploaded = true;
        }
        
        // On renvoie le formulaire
        return $form;
    }
}

<?php

namespace App\Controller;

use DateTime;
use Exception;
use App\Form\UploadType;
use App\Utils\Constants;
use App\Entity\Candidature;
use App\Utils\GoogleDriveUploader;
use App\Repository\CandidatureRepository;
use PhpParser\Node\Stmt\Const_;
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
     * @var GoogledriveUploader
     */
    private $driveUploader;

    /**
     * @var CandidatureRepository
     */
    private $candRepository;

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

    public function __construct(CandidatureRepository $c)
    {
        $this->candRepository = $c;
    }

    /**
     * @Route("/upload", name="upload")
     */
    public function upload(Request $req): Response
    {
        // On vérifie que l'utilisateur peur postuler
        if (
            $this->getUser()->getStatus() !== Constants::DEFAULT_STATUS
        ) {
            return $this->render('upload/index.html.twig', [
                "canUpload" => false 
            ]);
        }
        $commonHandle = $this->commonHandle($req);
        if (isset($commonHandle["error"])) {
            return $commonHandle["error"];
        }
        $form = $commonHandle["form"];
        if ($form->isSubmitted() && $form->isValid()) {
            // On upload
            $this->handleUpload($form);
            // Si les fichiers ont été déposés on redirige l'utilisateur à 
            // l'accueil
            if ($this->isUploaded) {
                // Si le poste est valide on ajoute la candidature en base de 
                // données et on ajoute un rôle à l'utilisateur
                $this->addCandidature();

                // Et on renvoie l'utilisateur sur la page d'accueil
                return $this->redirectToRoute("home", [
                    "message" => "Fichiers déposés avec succès"
                ]);
            }
        }

        return $this->render('upload/index.html.twig', [
            "hasCandidature" => false,
            "uploadForm" => $form->createView(),
            "formErrors" => $this->formErrors,
            "view" => "spontanee"
        ]);
    }

    private function commonHandle(Request $req)
    {
        // Si l'utilisateur n'est pas connecté on le redirige sur la page de
        // connexion
        if (empty($this->getUser())) {
            // On définit les variables de redirection
            $this->get("session")->set("redirect", "upload");
            return [
                "error" => $this->redirectToRoute("login")
            ];
        }
        // Si l'utilisateur a déjà une candidature en cours on lui affiche un message
        if (!empty(
            $this->candRepository
                 ->getNotHandled("user = " . $this->getUser()->getId())
        )) {
            return [ 
                "error" => $this->render('upload/index.html.twig', [
                    "hasCandidature" => true
                ])
            ];
        }
        // On créé et gère le formulaire
        $this->req = $req;
        $form = $this->createForm(UploadType::class);
        $form->handleRequest($this->req);

        return [
            "form" => $form
        ];
    }

    private function addCandidature()
    {
        $candidature = new Candidature();
        $candidature->setUser($this->getUser());
        $candidature->setDate(new DateTime());
        $entityManager = $this->getDoctrine()->getManager();
        $entityManager->persist($candidature);
        $this->getUser()->setStatus(Constants::POSTULATED_STATUS);
        $entityManager->flush();
    }

    /**
     * Prend en charge le formulaire d'upload et créé des pièces sur le drive
     * si des fichiers ont été déposés. Renvoie le formulaire créé
     * 
     * @return FormInterface
     */
    private function handleUpload($form): FormInterface
    {
        $this->driveUploader = new GoogleDriveUploader();
        // On vérifie les données du formulaire
        if ($form->isSubmitted() && $form->isValid()) {
            if (
                !$this->driveUploader->upload(
                    $this->getUser(), 
                    Constants::CV_FOLDER_NAME, 
                    Constants::CV_FILE_NAME, 
                    $form->get("cv")->getData()->getPathname()
                )
                || 
                !$this->driveUploader->upload(
                    $this->getUser(), 
                    Constants::LETTER_FOLDER_NAME, 
                    Constants::LETTER_FILE_NAME, 
                    $form->get("lettre")->getData()->getPathname()
                )
            ) {
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

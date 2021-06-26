<?php

namespace App\Controller;

use DateTime;
use Exception;
use App\Form\UploadType;
use App\Utils\Constants;
use App\Entity\Candidature;
use App\Utils\GoogleDriveManager;
use App\Repository\PosteRepository;
use App\Repository\CandidatureRepository;
use App\Repository\OffreRepository;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;

class UploadController extends AbstractController
{
    /**
     * @var Request
     */
    private $req;

    /**
     * @var GoogleDriveManager
     */
    private $driveManager;

    /**
     * @var CandidatureRepository
     */
    private $candRepository;

    /**
     * @var PosteRepository
     */
    private $posteRepository;

    /**
     * @var OffreRepository
     */
    private $offreRepository;

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

    public function __construct(CandidatureRepository $c, PosteRepository $pRep, OffreRepository $oRep)
    {
        $this->candRepository = $c;
        $this->posteRepository = $pRep;
        $this->offreRepository = $oRep;
    }

    /**
     * @Route("/upload", name="upload")
     */
    public function upload(Request $req): Response
    {
        $commonHandle = $this->commonHandle($req);
        if (isset($commonHandle["error"])) {
            return $commonHandle["error"];
        }
        $form = $commonHandle["form"];
        // Si le poste est invalide on affiche une erreur
        $poste = $this->posteRepository->findBy([
            "slug" => $form->get("poste")->getData()
        ]);
        if ($form->isSubmitted() && empty($poste)) {
            $this->formErrors[] = "Le poste séléctionné est invalide";
        } else {
            // Sinon on regarde si des fichiers ont été déposés
            // Et si oui on les upload
            $this->handleUpload($form);
            // Si les fichiers ont été déposés on redirige l'utilisateur à 
            // l'accueil
            if ($this->isUploaded) {
                // Si le poste est valide on ajoute la candidature en base de 
                // données
                $this->addCandidature($poste[0]);

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

    /**
     * @Route("/upload/{offreID}", name="upload_offre")
     */
    public function uploadOffre(Request $req, string $offreID)
    {
        $commonHandle = $this->commonHandle($req);
        if (isset($commonHandle["error"])) {
            return $commonHandle["error"];
        }
        // Si l'offre n'existe pas on redirige l'utilisateur à la page des 
        // offres
        $offre = $this->offreRepository->findBy(["id" => $offreID]);
        if (empty($offre)) {
            return $this->redirectToRoute("offres");
        }
        $form = $commonHandle["form"];
        // On regarde si des fichiers ont été déposés
        // Et si oui on les upload
        $this->handleUpload($form);
        // Si les fichiers ont été déposés on redirige l'utilisateur à 
        // l'accueil
        if ($this->isUploaded) {
            // Si le poste est valide on ajoute la candidature en base de 
            // données
            $this->addCandidature(null, $offre[0]);

            // Et on renvoie l'utilisateur sur la page d'accueil
            return $this->redirectToRoute("home", [
                "message" => "Fichiers déposés avec succès"
            ]);
        }

        return $this->render('upload/index.html.twig', [
            "hasCandidature" => false,
            "uploadForm" => $form->createView(),
            "formErrors" => $this->formErrors,
            "view" => "offre",
            "offre" => $offre[0]
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

    private function addCandidature($poste = null, $offre = null)
    {
        $candidature = new Candidature();
        $candidature->setUser($this->getUser());
        $candidature->setDate(new DateTime());
        if ($poste) {
            $candidature->setPoste($poste);
        } else {
            $candidature->setOffre($offre);
        }
        $entityManager = $this->getDoctrine()->getManager();
        $entityManager->persist($candidature);
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
        $this->driveManager = new GoogleDriveManager(
            Constants::GOOGLE_FOLDER . "credentials.json",
            Constants::ID_DRIVE_ROOT
        );
        // On vérifie les données du formulaire
        if ($form->isSubmitted() && $form->isValid()) {
            try {
                // Si tout est bon on upload les fichiers sur le drive
                $this->driveManager->goTo($this->getUser()->getDriveID());
                $this->driveManager->goToName(Constants::CV_FOLDER_NAME);
                // Si un CV a déjà été déposé par l'utilisateur on le supprime
                $this->deleteDuplicate(Constants::CV_FILE_NAME);
                $this->driveManager->upload(
                    Constants::CV_FILE_NAME,
                    $form->get("cv")->getData()->getPathname()
                );
                $this->driveManager->back();
                $this->driveManager->goToName(Constants::LETTER_FOLDER_NAME);
                // Si une lettre a déjà été déposée par l'utilisateur on la 
                // supprime
                $this->deleteDuplicate(Constants::LETTER_FOLDER_NAME);
                $this->driveManager->upload(
                    Constants::LETTER_FILE_NAME,
                    $form->get("lettre")->getData()->getPathname()
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

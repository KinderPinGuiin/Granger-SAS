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
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class UploadController extends AbstractController
{
    /**
     * @var Request
     */
    private $req;

    /**
     * @var UrlGeneratorInterface
     */
    private $urlGenerator;

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
     * Indique si des fichiers ont été déposés sur le drive ou non
     * @var bool
     */
    private $isUploaded = false;

    /**
     * Contient les erreurs du formulaire
     * @var array
     */
    private $formErrors = [];

    public function __construct(UrlGeneratorInterface $urlGenerator, CandidatureRepository $c, PosteRepository $pRep)
    {
        $this->urlGenerator = $urlGenerator;
        $this->candRepository = $c;
        $this->posteRepository = $pRep;
    }

    /**
     * @Route("/upload", name="upload")
     */
    public function index(Request $req): Response
    {
        // Si l'utilisateur n'est pas connecté on le redirige sur la page de
        // connexion
        if (empty($this->getUser())) {
            return new RedirectResponse(
                $this->urlGenerator->generate("login") . "?redirect=upload"
            );
        }
        // Si l'utilisateur a déjà une candidature en cours on lui affiche un message
        if (!empty(
            $this->candRepository
                 ->getNotHandled("user = " . $this->getUser()->getId())
        )) {
            return $this->render('upload/index.html.twig', [
                "hasCandidature" => true
            ]);
        }
        // On créé et gère le formulaire
        $this->req = $req;
        $form = $this->createForm(UploadType::class);
        $form->handleRequest($this->req);
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
            "formErrors" => $this->formErrors
        ]);
    }

    private function addCandidature($poste)
    {
        $candidature = new Candidature();
        $candidature->setUser($this->getUser());
        $candidature->setDate(new DateTime());
        $candidature->setPoste($poste);
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
            // Si une lettre a déjà été déposée par l'utilisateur on la supprime
            $this->deleteDuplicate(Constants::LETTER_FOLDER_NAME);
            try {
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

<?php

namespace App\Controller;

use App\Utils\Constants;
use App\Utils\GoogleDriveManager;
use Symfony\Component\Mime\Email;
use App\Repository\UserRepository;
use App\Form\CandidatureHandlingType;
use Doctrine\ORM\EntityManagerInterface;
use App\Repository\CandidatureRepository;
use App\Repository\ContenuRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Mailer\MailerInterface;
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

    /**
     * @var UserRepository
     */
    private $userRepository;

    /**
     * @var CandidatureRepository
     */
    private $candidRepository;

    /**
     * @var ContenuRepository
     */
    private $contentRepository;

    /**
     * @var ObjectManager
     */
    private $em;

    public function __construct(UserRepository $repository, CandidatureRepository $cRepository, ContenuRepository $coRepository, EntityManagerInterface $em)
    {
        $this->driveManager = new GoogleDriveManager(
            Constants::GOOGLE_FOLDER . "credentials.json",
            Constants::ID_DRIVE_ROOT
        );
        $this->userRepository = $repository;
        $this->candidRepository = $cRepository;
        $this->contentRepository = $coRepository;
        $this->em = $em;
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
    public function adminCandidatures(Request $req)
    {
        if (!$this->checkAccess()) {
            return $this->redirectToRoute("home");
        }
        // On liste les candidatures non traitées
        $candidatures = [];
        if ($req->get("show") === "all") {
            $candidatures = $this->candidRepository->findBy([], ["id" => "DESC"]);
        } else {
            $candidatures = $this->candidRepository->getNotHandled();
        }

        return $this->render("admin/candidatures.html.twig", [
            "candidatures" => $candidatures
        ]);
    }

    /**
     * @Route("/candidature/{driveId}", name="_candidature", methods={"GET"})
     * 
     * @return mixed RedirectResponse ou Response
     */
    public function adminCandidature(string $driveId)
    {
        if (!$this->checkAccess()) {
            return $this->redirectToRoute("home");
        }
        // On charge le candidat
        $candidat = $this->userRepository->getByDriveId($driveId);
        dump($candidat, $candidat->getId());
        $candidaturesNonTraitees = $this->candidRepository->getNotHandled(
            "user = " . $candidat->getId()
        );
        if (empty($candidaturesNonTraitees)) {
            // Si l'utilisateur n'a pas de candidature en cours on affiche une 
            // page avec l'historique des candidatures de l'utilisateur
            return $this->render("admin/candidature.html.twig", [
                "view" => "history",
                "candidatures" => $this->candidRepository->findBy(
                    ["user" => $candidat->getId()], ["id" => "DESC"]
                )
            ]);
        }
        // On cherche le dossier correspondant au driveId
        $dontExist = false;
        $didntUpload = false;
        if (!$this->driveManager->goToCheck($driveId)) {
            // Si on ne le trouve pas on définit la variable dontExist à true
            $dontExist = true;
        } else {
            // Si on le trouve on vérifie s'il a déjà déposé des fichiers
            $this->driveManager->goToName(Constants::CV_FOLDER_NAME);
            if (empty($this->driveManager->relativeList()["files"])) {
                $didntUpload = true;
            }
        }
        // On récupère le CV et la lettre de motivation
        $cvLettre = $this->getCVAndLetter($driveId);
        // Création du formulaire de réponse
        $form = $this->createForm(CandidatureHandlingType::class);

        return $this->render("admin/candidature.html.twig", [
            "view" => "candidature",
            "candidat" => $candidat,
            "dontExist" => $dontExist,
            "didntUpload" => $didntUpload,
            "cv" => $cvLettre["cv"],
            "lettre" => $cvLettre["lettre"],
            "form" => $form->createView()
        ]);
    }

    /**
     * @Route(
     *  "/candidature/{driveId}", name="_candidature_mail", methods={"POST"}
     * )
     * 
     * @return mixed RedirectResponse ou Response
     */
    public function handleCandidature(string $driveId, MailerInterface $mailer, Request $req)
    {
        if (!$this->checkAccess()) {
            return $this->redirectToRoute("home");
        }
        // On charge le candidat et sa candidature
        $candidat = $this->userRepository->getByDriveId($driveId);
        $candidature = $this->candidRepository->getNotHandled("user = " . $candidat->getId())[0];
        // On prend en charge le formulaire
        $form = $this->createForm(CandidatureHandlingType::class, $candidature);
        $form->handleRequest($req);
        // On vérifie les données du formulaire
        if ($form->isSubmitted() && $form->isValid()) {
            // On actualise la candidature et on envoie l'email
            $this->em->flush();
            $this->sendMail($mailer, $candidat, $form);
            return $this->redirectToRoute("admin_candidatures");
        }
        // Si elles ne sont pas bonnes on renvoie l'utilisateur sur le 
        // formulaire
        $cvLettre = $this->getCVAndLetter($driveId);

        return $this->render("admin/candidature.html.twig", [
            "candidat" => $candidat,
            "cv" => $cvLettre["cv"],
            "lettre" => $cvLettre["lettre"],
            "form" => $form->createView()
        ]);
    }

    /**
     * Envoie l'email de réponse
     */
    private function sendMail($mailer, $candidat, $form)
    {
        // Rédaction du mail
        $email = (new Email())
            ->from("noreply@grangersas.com")
            ->to($candidat->getEmail())
            ->subject("Votre candidature pour Granger SAS")
            ->html(
                "<h1>Votre candidature chez Granger SAS</h1>" 
                . "<p>" . $form->get("message")->getData() . "</p>");
        // Envoi du mail
        $mailer->send($email);
    }

    private function getCVAndLetter(string $driveId)
    {
        $this->driveManager->clearFolderStack();
        $this->driveManager->goTo($driveId);
        $this->driveManager->goToName(Constants::CV_FOLDER_NAME);
        $cv = $this->driveManager->relativeList()["files"][0];
        $this->driveManager->back();
        $this->driveManager->goToName(Constants::LETTER_FOLDER_NAME);
        $lettre = $this->driveManager->relativeList()["files"][0];

        return ["cv" => $cv, "lettre" => $lettre];
    }

    /**
     * @Route("/edit", name="_edit", methods="GET")
     * 
     * @return mixed RedirectResponse ou Response
     */
    public function adminEdit()
    {
        if (!$this->checkAccess()) {
            return $this->redirectToRoute("home");
        }
        $contenus = $this->contentRepository->findAll();

        return $this->render("admin/edit.html.twig", [
            "homeContent" => $contenus[0]->getContent(),
            "aboutContent" => $contenus[1]->getContent()
        ]);
    }

    /**
     * @Route("/edit", name="_editPOST", methods="POST")
     * 
     * @return mixed RedirectResponse ou Response
     */
    public function editContent()
    {
        // On détermine quelle page doit être modifiée
        $contenu = $this->contentRepository->findBy([
            "page" => array_key_first($_POST)
        ]);
        if (!empty($contenu)) {
            $contenu = $contenu[0];
        }
        // On prépare un tableau d'erreur si besoin
        $error = [];
        switch (array_key_first($_POST)) {
            case "home":
                if (strlen($_POST["home"]) > 255) {
                    // Si la chaine est trop longue on définit une erreur
                    $error["home"] = "Trop de caractères (Maximum : 255)";
                } else {
                    // On actualise le contenu si tout va bien
                    $contenu->setContent($_POST["home"]);
                    $this->em->flush();
                    return $this->redirectToRoute("home");
                }
                break;
            
            case "about":
                // On actualise le contenu
                $contenu->setContent($_POST["about"]);
                $this->em->flush();
                return $this->redirectToRoute("about");
                break;

            default:
                // Si la requête n'est pas valide on définit une erreur
                $error["global"] = "Requête invalide veuillez réessayer";
                break;
        }
        // En cas d'erreur on charge le contenu
        $contenus = $this->contentRepository->findAll();
        
        return $this->render("admin/edit.html.twig", [
            "homeContent" => (
                isset($_POST["home"]) 
                ? $_POST["home"] 
                : $contenus[0]->getContent()
            ),
            "aboutContent" => (
                isset($_POST["about"]) 
                ? $_POST["about"] 
                : $contenus[1]->getContent()
            ),
            "error" => $error
        ]);
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
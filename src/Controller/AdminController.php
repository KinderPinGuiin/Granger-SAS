<?php

namespace App\Controller;

use DateTime;
use App\Entity\Offre;
use App\Entity\Poste;
use App\Form\ImageType;
use App\Utils\Constants;
use App\Form\UpdateOffreType;
use App\Utils\GoogleDriveManager;
use App\Repository\UserRepository;
use App\Repository\OffreRepository;
use App\Repository\PosteRepository;
use App\Form\CandidatureHandlingType;
use App\Repository\ContenuRepository;
use Doctrine\ORM\EntityManagerInterface;
use App\Repository\CandidatureRepository;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
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
     * @var PosteRepository
     */
    private $posteRepository;

    /**
     * @var OffreRepository
     */
    private $offreRepository;

    /**
     * @var ObjectManager
     */
    private $em;

    public function __construct(UserRepository $repository, CandidatureRepository $cRepository, ContenuRepository $coRepository, PosteRepository $pRep, OffreRepository $oRep, EntityManagerInterface $em)
    {
        $this->driveManager = new GoogleDriveManager(
            Constants::GOOGLE_FOLDER . "credentials.json",
            Constants::ID_DRIVE_ROOT
        );
        $this->userRepository = $repository;
        $this->candidRepository = $cRepository;
        $this->contentRepository = $coRepository;
        $this->posteRepository = $pRep;
        $this->offreRepository = $oRep;
        $this->em = $em;
    }

    /**
     * @Route("/", name="")
     * 
     * @return mixed RedirectResponse ou Response
     */
    public function admin(Request $req)
    {
        if (!$this->checkAccess($req)) {
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
        if (!$this->checkAccess($req)) {
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
    public function adminCandidature(string $driveId, Request $req)
    {
        if (!$this->checkAccess($req)) {
            return $this->redirectToRoute("home");
        }
        // On charge le candidat
        $candidat = $this->userRepository->getByDriveId($driveId);
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
                ),
                "candidat" => $candidat
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
            "candidature" => $candidaturesNonTraitees[0],
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
        if (!$this->checkAccess($req)) {
            return $this->redirectToRoute("home");
        }
        // On charge le candidat et sa candidature
        $candidat = $this->userRepository->getByDriveId($driveId);
        $candidature = $this->candidRepository->getNotHandled("user = " . $candidat->getId());
        if (empty($candidature)) {
            return $this->redirectToRoute("admin_candidatures");
        }
        $candidature = $candidature[0];
        // On prend en charge le formulaire
        $form = $this->createForm(CandidatureHandlingType::class, $candidature);
        $form->handleRequest($req);
        // On vérifie les données du formulaire
        if ($form->isSubmitted() && $form->isValid()) {
            // On actualise la candidature et on envoie l'email
            $this->em->flush();
            $this->sendMail($mailer, $candidat, $candidature, $form);
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
    private function sendMail($mailer, $candidat, $candidature, $form)
    {
        // Rédaction du mail
        $email = (new TemplatedEmail())
            ->from("noreply@grangersas.com")
            ->to($candidat->getEmail())
            ->subject("Votre candidature pour Granger SAS")
            ->htmlTemplate("emails/candidature_mail.html.twig")
            ->context([
                "candidat" => $candidat,
                "poste" => $candidature->getPoste()->getName(),
                "message" => nl2br($form->get("message")->getData())
            ]);
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
    public function adminEdit(Request $req)
    {
        if (!$this->checkAccess($req)) {
            return $this->redirectToRoute("home");
        }
        $contenus = $this->contentRepository->findAll();
        $uploadImageForm = $this->createForm(ImageType::class, null, [
            "action" => "/image/upload"
        ]);

        return $this->render("admin/edit.html.twig", [
            "homeContent" => $contenus[0]->getContent(),
            "aboutContent" => $contenus[1]->getContent(),
            "uploadImageForm" => $uploadImageForm->createView()
        ]);
    }

    /**
     * @Route("/edit", name="_edit_POST", methods="POST")
     * 
     * @return mixed RedirectResponse ou Response
     */
    public function editContent(Request $req)
    {
        if (!$this->checkAccess($req)) {
            return $this->redirectToRoute("home");
        }
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
        $uploadImageForm = $this->createForm(ImageType::class, null, [
            "action" => "/image/upload"
        ]);
        
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
            "error" => $error,
            "uploadImageForm" => $uploadImageForm->createView()
        ]);
    }

    /**
     * @Route("/users", name="_users", methods="GET")
     * 
     * @return mixed RedirectResponse ou Response
     */
    public function users(Request $req)
    {
        if (!$this->checkAccess($req)) {
            return $this->redirectToRoute("home");
        }

        return $this->render("admin/users.html.twig", [
            "users" => $this->userRepository->findAll()
        ]);
    }

    /**
     * @Route("/users", name="_users_POST", methods="POST")
     * 
     * @return mixed RedirectResponse ou Response
     */
    public function setUserRole(Request $req)
    {
        if (!$this->checkAccess($req)) {
            return $this->redirectToRoute("home");
        }
        // On récupère l'utilisateur à modifier
        $user = $this->userRepository->findBy(["id" => $_POST["user_id"]])[0];
        // S'il n'existe pas on affiche une erreur
        if ($user === null) {
            return $this->render("admin/users.html.twig", [
                "users" => $this->userRepository->findAll(),
                "error" => "Utilisateur inconnu"
            ]);
        }
        // On définit son nouveau rôle
        switch ($_POST["role"]) {
            case "user":
                $user->setRoles(["ROLE_USER"]);
                break;

            case "editor":
                $user->setRoles(["ROLE_EDITOR"]);
                break;

            case "rh":
                $user->setRoles(["ROLE_RH"]);
                break;

            case "admin":
                $user->setRoles(["ROLE_ADMIN"]);
                break;
            
            default:
                // Si le rôle n'existe pas on affiche une erreur
                return $this->render("admin/users.html.twig", [
                    "users" => $this->userRepository->findAll(),
                    "error" => "Rôle inconnu"
                ]);
                break;
        }
        // On sauvegarde les modifications
        $this->em->flush();

        return $this->render("admin/users.html.twig", [
            "users" => $this->userRepository->findAll()
        ]);
    }

    /**
     * @Route("/postes", name="_postes", methods="GET")
     * 
     * @return mixed RedirectResponse ou Response
     */
    public function postes(Request $req)
    {
        if (!$this->checkAccess($req)) {
            return $this->redirectToRoute("home");
        }
        
        return $this->render("admin/postes.html.twig", [
            "postes" => $this->posteRepository->findAll()
        ]);
    }

    /**
     * @Route("/postes/edit", name="_postes_edit", methods="POST")
     * 
     * @return mixed RedirectResponse ou Response
     */
    public function editPoste(Request $req)
    {
        if (!$this->checkAccess($req)) {
            return $this->redirectToRoute("home");
        }
        // On récupère le poste à modifier
        $poste = $this->posteRepository->findBy(["id" => $_POST["id"]])[0];
        if ($poste === null) {
            // Si le poste n'existe pas on affiche une erreur
            return $this->render("admin/postes.html.twig", [
                "postes" => $this->posteRepository->findAll(),
                "error" => "Poste invalide"
            ]);
        } else if (($posteError = $this->checkPoste($_POST["name"])) !== 0) {
            // On vérifie également les erreurs du nom
            return $this->render("admin/postes.html.twig", [
                "postes" => $this->posteRepository->findAll(),
                "error" => (
                    $posteError == 1 
                    ? "Le poste " . $_POST["name"] . " existe déjà"
                    : "Le nom du poste doit contenir entre 1 et 255 caractères"
                )
            ]);
        }
        // On le modifie
        $poste->setName($_POST["name"]);
        $poste->setSlug(str_replace(" ", "_", strtolower($_POST["name"])));
        $this->em->flush();

        return $this->redirectToRoute("admin_postes");
    }


    /**
     * @Route("/postes/add", name="_postes_add_GET", methods="GET")
     * 
     * @return mixed RedirectResponse ou Response
     */
    public function addPosteRedirect(Request $req)
    {
        if (!$this->checkAccess($req)) {
            return $this->redirectToRoute("home");
        }
        
        // Si cette page est appellée en GET on redirige l'utilisateur
        return $this->redirectToRoute("admin_postes");
    }

    /**
     * @Route("/postes/add", name="_postes_add", methods="POST")
     * 
     * @return mixed RedirectResponse ou Response
     */
    public function addPoste(Request $req)
    {
        if (!$this->checkAccess($req)) {
            return $this->redirectToRoute("home");
        }
        // On vérifie que le poste n'existe pas déjà
        if (($posteError = $this->checkPoste($_POST["name"])) !== 0) {
            return $this->render("admin/postes.html.twig", [
                "postes" => $this->posteRepository->findAll(),
                "error" => (
                    $posteError == 1 
                    ? "Le poste " . $_POST["name"] . " existe déjà"
                    : "Le nom du poste doit contenir entre 1 et 255 caractères"
                )
            ]);
        }
        
        // On créé le poste
        $poste = new Poste();
        $poste->setName($_POST["name"]);
        $poste->setSlug(str_replace(" ", "_", strtolower($_POST["name"])));
        $this->em->persist($poste);
        $this->em->flush();

        return $this->redirectToRoute("admin_postes");
    }

    /**
     * @Route("/postes/delete", name="_postes_delete")
     * 
     * @return mixed RedirectResponse ou Response
     */
    public function deletePoste(Request $req)
    {
        if (!$this->checkAccess($req)) {
            return $this->redirectToRoute("home");
        }
        // Si la suppression est confirmée on execute
        if($req->get("confirm") !== null && $req->get("id") !== null) {
            $poste = $this->posteRepository->findBy(
                ["id" => $req->get("id")]
            )[0];
            if ($poste === null) {
                // Si le poste n'existe pas on affiche une erreur
                return $this->render("admin/postes.html.twig", [
                    "postes" => $this->posteRepository->findAll(),
                    "error" => "Poste invalide"
                ]);
            }
            $this->em->remove($poste);
            $this->em->flush();
            return $this->redirectToRoute("admin_postes");
        } else {
            return $this->render("admin/postes.html.twig", [
                "delete_confirm" => true,
                "poste_id" => $req->get("id")
            ]);
        }
    }

    /**
     * Retourne 0 si le poste passé en paramètre est valide, 1 s'il existe déjà
     * et -1 si sa syntaxe est invalide
     */
    private function checkPoste(string $postName): int
    {
        // On vérifie que le poste n'existe pas déjà
        if (in_array(trim($postName), $this->posteRepository->names())) {
            return 1;
        }
        // On vérifie sa longueur
        if (strlen($postName) > 255 || strlen($postName) == 0) {
            return -1;
        }

        return 0;
    }

    /**
     * @Route("/offres", name="_offres")
     * 
     * @return mixed RedirectResponse ou Response
     */
    public function offresEmploi(Request $req)
    {
        if (!$this->checkAccess($req)) {
            return $this->redirectToRoute("home");
        }

        return $this->render("admin/offres.html.twig", [
            "offres" => $this->offreRepository->findBy([], ["date" => "DESC"])
        ]);
    }

    /**
     * @Route("/offres/add", name="_offres_add")
     * 
     * @return mixed RedirectResponse ou Response
     */
    public function addOffre(Request $req)
    {
        if (!$this->checkAccess($req)) {
            return $this->redirectToRoute("home");
        }
        // On créé l'offre
        $offre = new Offre();
        $offre->setName("Sans nom")
            ->setContent("")
            ->setDate(new DateTime())
            ->setOnline(false);
        $this->em->persist($offre);
        $this->em->flush();

        // Et on redirige sur l'update pour la modifier
        return $this->redirectToRoute("admin_offres_update", [
            "id" => $offre->getId()
        ]);
    }

    /**
     * @Route("/offres/update/{id}", name="_offres_update")
     * 
     * @return mixed RedirectResponse ou Response
     */
    public function updateOffre(Request $req, string $id)
    {
        if (!$this->checkAccess($req)) {
            return $this->redirectToRoute("home");
        }
        // On trouve l'offre
        $offre = $this->offreRepository->findBy(["id" => $id]);
        // Si elle n'existe pas on redirige l'utilisateur
        if (empty($offre)) {
            return $this->render("admin/offres.html.twig", [
                "offres" => $this->offreRepository->findBy([], ["date" => "DESC"])
            ]);
        }
        // On créé le formulaire
        $form = $this->createForm(UpdateOffreType::class, $offre[0]);
        $form->handleRequest($req);
        if ($form->isSubmitted() && $form->isValid()) {
            // Si le formulaire est valide on modifie l'offre
            $offre[0]->setDate(new DateTime());
            $this->em->flush();
            return $this->redirectToRoute("admin_offres");
        }

        return $this->render("admin/offres_update.html.twig", [
            "offre" => $offre[0],
            "updateForm" => $form->createView()
        ]);
    }

    /**
     * @Route("/offres/set-online/{id}", name="_offres_set_online")
     * 
     * @return mixed RedirectResponse ou Response
     */
    public function setOffreOnline(Request $req, string $id)
    {
        if (!$this->checkAccess($req)) {
            return $this->redirectToRoute("home");
        }
        $offre = $this->offreRepository->findBy(["id" => $id]);
        // Si l'offre n'existe pas on renvoie une erreur
        if (empty($offre)) {
            return new JsonResponse(
                ["error" => "Offre inexistante"], 
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
        // On edit l'offre
        $value = $req->get("onlineValue");
        if (!empty($req->get("onlineValue")) && ($value == "true" || $value == "false")) {
            $offre[0]->setOnline($value == "true");
            if ($value == "true") {
                // Si l'offre est (re)mise en ligne on met à jour sa date
                $offre[0]->setDate(new DateTime());
            }
            $this->em->flush();

            return new JsonResponse(
                ["message" => "Statut changé", "value" => $value == "true"],
                Response::HTTP_OK
            );
        }

        return new JsonResponse(
            ["error" => "Données invalides"], 
            Response::HTTP_INTERNAL_SERVER_ERROR
        );
    }

    /**
     * @Route("/offres/delete/{id}", name="_offres_delete")
     * 
     * @return mixed RedirectResponse ou Response
     */
    public function deleteOffre(Request $req, string $id)
    {
        if (!$this->checkAccess($req)) {
            return $this->redirectToRoute("home");
        }
        $offre = $this->offreRepository->findBy(["id" => $id]);
        // On vérifie si l'utilisateur a confirmé et que l'offre existe
        if ($req->get("confirm") == true && !empty($offre)) {
            // Si oui on supprime l'offre
            $this->em->remove($offre[0]);
            $this->em->flush();

            return $this->render("admin/offres.html.twig", [
                "offres" => $this->offreRepository->findBy([], ["date" => "DESC"])
            ]);
        } else if (empty($offre)) {
            // Si l'offre est vide on renvoie sur la page des offres
            return $this->render("admin/offres.html.twig", [
                "offres" => $this->offreRepository->findBy([], ["date" => "DESC"])
            ]);
        }

        // Sinon on affiche la page avec un message de confirmation
        return $this->render("admin/offres.html.twig", [
            "delete" => true,
            "offre" => $offre[0]
        ]);
    }

    /**
     * Retourne false si l'utilisateur n'est pas autorisé à accéder à la page
     * d'administration et true sinon
     * 
     * @param Request $req The request
     * @return bool true si l'utilisateur a accès et false sinon
     */
    private function checkAccess(Request $req): bool
    {
        if (!$this->getUser())
            return false;
        $userRoles = $this->getUser()->getRoles();
        switch ($req->attributes->get("_route")) {
            case "admin_candidatures":
            case "admin_candidature":
            case "admin_candidature_mail":
                return in_array("ROLE_ADMIN", $userRoles)
                    || in_array("ROLE_RH", $userRoles);
            
            case "admin_edit":
            case "admin_edit_POST":
                return in_array("ROLE_ADMIN", $userRoles)
                    || in_array("ROLE_EDITOR", $userRoles);
            
            case "admin_users":
            case "admin_users_POST":
            case "admin_postes":
            case "admin_postes_edit":
            case "admin_postes_add":
            case "admin_postes_add_GET":
            case "admin_postes_delete":
                return in_array("ROLE_ADMIN", $userRoles);
            
            case "admin_offres":
            case "admin_offres_update":
            case "admin_offres_delete":
            case "admin_offres_add":
            case "admin_offres_set_online":
            default:
                return in_array("ROLE_ADMIN", $userRoles)
                    || in_array("ROLE_EDITOR", $userRoles)
                    || in_array("ROLE_RH", $userRoles);
        }
    }

}
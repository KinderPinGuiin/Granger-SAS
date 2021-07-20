<?php

namespace App\Controller;

use App\Form\ImageType;
use App\Utils\Constants;
use App\Utils\GoogleDriveManager;
use App\Repository\UserRepository;
use App\Form\CandidatureHandlingType;
use App\Repository\ContenuRepository;
use Doctrine\ORM\EntityManagerInterface;
use App\Repository\CandidatureRepository;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\HttpFoundation\Response;
use App\Repository\ValidationRequestRepository;
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
     * @var ValidationRequestRepository
     */
    private $vReqRepository;

    /**
     * @var ObjectManager
     */
    private $em;

    public function __construct(UserRepository $repository, CandidatureRepository $cRepository, ContenuRepository $coRepository, ValidationRequestRepository $vRep, EntityManagerInterface $em)
    {
        $this->driveManager = new GoogleDriveManager(
            Constants::GOOGLE_FOLDER . "credentials.json",
            Constants::ID_DRIVE_ROOT
        );
        $this->userRepository = $repository;
        $this->candidRepository = $cRepository;
        $this->contentRepository = $coRepository;
        $this->vReqRepository = $vRep;
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
            "form" => $form->createView(),
            "mailsContent" => $this->contentRepository->getMailsContent()
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
            $this->sendMail(
                $mailer, $candidat->getEmail(), 
                "Votre candidature chez Granger SAS",
                "emails/candidature_mail.html.twig", 
                [
                    "candidat" => $candidat,
                    "message" => nl2br($form->get("message")->getData()),
                    "candidature" => $candidature
                ]
            );
            return $this->redirectToRoute("admin_candidatures");
        }
        // Si elles ne sont pas bonnes on renvoie l'utilisateur sur le 
        // formulaire
        $cvLettre = $this->getCVAndLetter($driveId);

        return $this->render("admin/candidature.html.twig", [
            "view" => "candidature",
            "candidat" => $candidat,
            "cv" => $cvLettre["cv"],
            "lettre" => $cvLettre["lettre"],
            "form" => $form->createView(),
            "candidature" => $candidature,
            "mailsContent" => $this->contentRepository->getMailsContent()
        ]);
    }

    /**
     * Envoie un email
     */
    private function sendMail($mailer, $to, $subject, $template, $context = [])
    {
        // Rédaction du mail
        $email = (new TemplatedEmail())
            ->from("noreply@grangersas.com")
            ->to($to)
            ->subject($subject)
            ->htmlTemplate($template)
            ->context($context);
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
            "acceptMailContent" => $contenus[2]->getContent(),
            "denyMailContent" => $contenus[3]->getContent(),
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

            case "accept_mail":
                // On actualise le contenu
                $contenu->setContent($_POST["accept_mail"]);
                $this->em->flush();
                return $this->redirectToRoute("admin");
                break;

            case "deny_mail":
                // On actualise le contenu
                $contenu->setContent($_POST["deny_mail"]);
                $this->em->flush();
                return $this->redirectToRoute("admin");
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
            "acceptMailContent" => (
                isset($_POST["accept_mail"]) 
                ? $_POST["accept_mail"] 
                : $contenus[2]->getContent()
            ),
            "denyMailContent" => (
                isset($_POST["deny_mail"]) 
                ? $_POST["deny_mail"] 
                : $contenus[3]->getContent()
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
     * @Route("/validations-requests", name="_validations_requests")
     * 
     * @return mixed RedirectResponse ou Response
     */
    public function validationsRequests(Request $req)
    {
        if (!$this->checkAccess($req)) {
            return $this->redirectToRoute("home");
        }
        $requests = $this->vReqRepository->findBy(["accepted" => null]);

        return $this->render("admin/validations_requests.html.twig", [
            "requests" => $requests
        ]);
    }

    /**
     * @Route("/validation-request/{id}", name="_validation_request")
     * 
     * @return mixed RedirectResponse ou Response
     */
    public function validationRequest(Request $req, int $id, MailerInterface $mailer)
    {
        if (!$this->checkAccess($req)) {
            return $this->redirectToRoute("home");
        }
        $request = $this->vReqRepository->findBy(["id" => $id]);
        if (empty($request)) {
            return $this->redirectToRoute("admin_validations_requests");
        }
        $request = $request[0];
        // Si la demande a été traitée on l'actualise dans la BDD
        if ($req->get("accept") !== null || $req->get("deny") != null) {
            $request->setAccepted($req->get("accept") !== null);
            $request->getUser()->setRoles([
                $request->getUser()->getRealRole(), "ROLE_CONDUCTOR"
            ]);
            $this->em->flush();
            // On envoie également un mail
            $this->sendMail(
                $mailer, 
                $request->getUser()->getEmail(),
                "Vérification de votre profil Granger SAS",
                "emails/verification_mail.html.twig",
                ["accepted" => $req->get("accept") !== null]
            );
            return $this->redirectToRoute("admin_validations_requests");
        }

        return $this->render("admin/validation_request.html.twig", [
            "request" => $request
        ]);
    }

    /**
     * @Route("/validation-request/{id}/file/permis", name="_validation_request_permis")
     * 
     * @return mixed RedirectResponse ou Response
     */
    public function validationRequestPermis(Request $req, int $id)
    {
        if (!$this->checkAccess($req)) {
            return $this->redirectToRoute("home");
        }
        $request = $this->vReqRepository->findBy(["id" => $id]);
        if (empty($request)) {
            return $this->redirectToRoute("admin_validations_requests");
        }

        return new Response(
            stream_get_contents($request[0]->getPermis()),
            Response::HTTP_OK, 
            [
                "content-type" => "application/pdf"
            ]
        );
    }

    /**
     * @Route("/validation-request/{id}/file/contrat", name="_validation_request_contrat")
     * 
     * @return mixed RedirectResponse ou Response
     */
    public function validationRequestContrat(Request $req, int $id)
    {
        if (!$this->checkAccess($req)) {
            return $this->redirectToRoute("home");
        }
        $request = $this->vReqRepository->findBy(["id" => $id]);
        if (empty($request)) {
            return $this->redirectToRoute("admin_validations_requests");
        }

        return new Response(
            stream_get_contents($request[0]->getContrat()),
            Response::HTTP_OK, 
            [
                "content-type" => "application/pdf"
            ]
        );
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
            case "admin_validations_requests":
            case "admin_validation_request":
            case "admin_validation_request_permis":
            case "admin_validation_request_contrat":
                return in_array("ROLE_ADMIN", $userRoles)
                    || in_array("ROLE_RH", $userRoles);
            
            case "admin_edit":
            case "admin_edit_POST":
                return in_array("ROLE_ADMIN", $userRoles)
                    || in_array("ROLE_EDITOR", $userRoles);
            
            case "admin_users":
            case "admin_users_POST":
                return in_array("ROLE_ADMIN", $userRoles);

            default:
                return in_array("ROLE_ADMIN", $userRoles)
                    || in_array("ROLE_EDITOR", $userRoles)
                    || in_array("ROLE_RH", $userRoles);
        }
    }

}
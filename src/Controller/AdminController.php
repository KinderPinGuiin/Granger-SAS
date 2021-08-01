<?php

namespace App\Controller;

use App\Form\ImageType;
use App\Utils\Constants;
use App\Entity\Documents;
use App\Form\DenyDocType;
use App\Form\AddDocumentType;
use App\Utils\GoogleDriveManager;
use App\Repository\UserRepository;
use App\Form\CandidatureHandlingType;
use App\Repository\ContenuRepository;
use App\Repository\DocumentsRepository;
use Doctrine\ORM\EntityManagerInterface;
use App\Repository\CandidatureRepository;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\HttpFoundation\Response;
use App\Repository\UploadedDocumentsRepository;
use App\Repository\ValidationRequestRepository;
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
     * @var ValidationRequestRepository
     */
    private $vReqRepository;

    /**
     * @var DocumentsRepository
     */
    private $documentsRepository;

    /**
     * @var UploadedDocumentsRepository
     */
    private $uploadedDocsRepository;

    /**
     * @var ObjectManager
     */
    private $em;

    public function __construct(UserRepository $repository, CandidatureRepository $cRepository, ContenuRepository $coRepository, ValidationRequestRepository $vRep, DocumentsRepository $dRep, UploadedDocumentsRepository $udRep, EntityManagerInterface $em)
    {
        $this->driveManager = new GoogleDriveManager(
            Constants::GOOGLE_FOLDER . "credentials.json",
            Constants::ID_DRIVE_ROOT
        );
        $this->userRepository = $repository;
        $this->candidRepository = $cRepository;
        $this->contentRepository = $coRepository;
        $this->vReqRepository = $vRep;
        $this->documentsRepository = $dRep;
        $this->uploadedDocsRepository = $udRep;
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
            $candidature->getUser()->setStatus(Constants::ACCEPTED_STATUS);
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
                $user->setRealRole("ROLE_USER");
                break;

            case "editor":
                $user->setRealRole("ROLE_EDITOR");
                break;

            case "rh":
                $user->setRealRole("ROLE_RH");
                break;

            case "admin":
                $user->setRealRole("ROLE_ADMIN");
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
        $requests = $this->vReqRepository->getNotHandledRequest();

        return $this->render("admin/validations_requests.html.twig", [
            "requests" => $requests
        ]);
    }

    /**
     * @Route("/documents", name="_documents")
     * 
     * @return mixed RedirectResponse ou Response
     */
    public function documents(Request $req)
    {
        if (!$this->checkAccess($req)) {
            return $this->redirectToRoute("home");
        }

        return $this->render("admin/documents.html.twig", [
            "candidatureDocs" => $this->documentsRepository->findBy(
                ["step" => Constants::CANDIDAT_STEP]
            ),
            "embaucheDocs" => $this->documentsRepository->findBy(
                ["step" => Constants::HIRE_STEP]
            ),
            "driverDocs" => $this->documentsRepository->findBy([
                "step" => Constants::DRIVER_STEP
            ]),
            "addForm" => $this->createForm(AddDocumentType::class)->createView()
        ]);
    }

    /**
     * @Route("/document/add", name="_add_document")
     * 
     * @return mixed RedirectResponse ou Response
     */
    public function addDocument(Request $req)
    {
        if (!$this->checkAccess($req)) {
            return $this->redirectToRoute("home");
        }
        $document = new Documents();
        $form = $this->createForm(AddDocumentType::class, $document);
        $form->handleRequest($req);
        if ($form->isSubmitted() && $form->isValid()) {
            $document->setSlug(
                strtolower(str_replace(" ", "_", $form->get("nom")->getData()))
            );
            $this->em->persist($document);
            $this->em->flush();

            return $this->redirectToRoute("admin_documents");
        }

        return $this->render("admin/documents.html.twig", [
            "candidatureDocs" => $this->documentsRepository->findBy(
                ["step" => Constants::CANDIDAT_STEP]
            ),
            "embaucheDocs" => $this->documentsRepository->findBy(
                ["step" => Constants::HIRE_STEP]
            ),
            "driverDocs" => $this->documentsRepository->findBy([
                "step" => Constants::DRIVER_STEP
            ]),
            "addForm" => $form->createView()
        ]);
    }

    /**
     * @Route("/document/delete/{id}", name="_delete_document")
     * 
     * @return mixed RedirectResponse ou Response
     */
    public function deleteDocument(Request $req, string $id)
    {
        if (!$this->checkAccess($req)) {
            return $this->redirectToRoute("home");
        }
        $document = $this->documentsRepository->findBy(["id" => $id]);
        if (empty($document)) {
            return $this->redirectToRoute("admin_documents");
        }
        $document = $document[0];
        $this->em->remove($document);
        $this->em->flush();

        return $this->redirectToRoute("admin_documents");
    }

    /**
     * @Route("/users-documents", name="_users_documents")
     * 
     * @return mixed RedirectResponse ou Response
     */
    public function usersDocuments(Request $req)
    {
        if (!$this->checkAccess($req)) {
            return $this->redirectToRoute("home");
        }
        // Les utilisateurs qui ont des documents en attente (Certains peuvent
        // être présents plusieurs fois)
        $users = array_map(function($doc) {
            return $doc->getUser();
        }, $this->uploadedDocsRepository->findBy([
            "accepted" => null
        ]));

        return $this->render("admin/users_documents.html.twig", [
            // Utilisateurs ayant des documents non traités
            "users" => array_unique($users)
        ]);
    }

    /**
     * @Route("/user/{id}", name="_user")
     * 
     * @return mixed RedirectResponse ou Response
     */
    public function user(Request $req, string $id)
    {
        if (!$this->checkAccess($req)) {
            return $this->redirectToRoute("home");
        }
        $user = $this->userRepository->findBy(["id" => $id]);
        if (empty($user)) {
            return $this->redirectToRoute("admin");
        }
        $user = $user[0];

        return $this->render("admin/user.html.twig", [
            "canPutDocs" => $user->getStatus() !== Constants::DEFAULT_STATUS,
            "user" => $user,
            "userDocs" => $this->uploadedDocsRepository->getUploadedDocsSlugs(
                $user
            ),
            "candidatDocs" => $this->documentsRepository->findBy([
                "step" => Constants::CANDIDAT_STEP
            ]),
            "hiredDocs" => $this->documentsRepository->findBy([
                "step" => Constants::HIRE_STEP
            ]),
            "driverDocs" => $this->documentsRepository->findBy([
                "step" => Constants::DRIVER_STEP
            ])
        ]);
    }

    /**
     * @Route("/handle-document/{status}/{id}", name="_handle_document")
     * 
     * @return mixed RedirectResponse ou Response
     */
    public function handleDocument(Request $req, string $status, string $id)
    {
        if (!$this->checkAccess($req)) {
            return $this->redirectToRoute("home");
        }
        $doc = $this->uploadedDocsRepository->findBy(["id" => $id]);
        if (empty($doc)) {
            return $this->redirectToRoute("admin_users_documents");
        }
        $doc = $doc[0];
        switch ($status) {
            case "accept":
                $doc->setAccepted(true);
                $this->em->flush();
    
                return $req->get("user_id") !== null ?
                $this->redirectToRoute("admin_user", [
                    "id" => $req->get("user_id")
                ])
                : $this->redirectToRoute("admin_users_documents");

            case "deny":
                $form = $this->createForm(DenyDocType::class, $doc);
                $form->handleRequest($req);
                if ($form->isSubmitted() && $form->isValid()) {
                    $doc->setAccepted(false);
                    $this->em->flush();
    
                    return $req->get("user_id") !== null ?
                    $this->redirectToRoute("admin_user", [
                        "id" => $req->get("user_id")
                    ])
                    : $this->redirectToRoute("admin_users_documents");
                }
    
                return $this->render("admin/handle_document.html.twig", [
                    "addFormComment" => true,
                    "commentForm" => $form->createView()
                ]);

            case "renewal":
                // On supprime le document
                $this->em->remove($doc);
                $this->em->flush();

                return $req->get("user_id") !== null ?
                $this->redirectToRoute("admin_user", [
                    "id" => $req->get("user_id")
                ])
                : $this->redirectToRoute("admin_users_documents");
            
            default:
                // Si le statut n'existe pas on redirige l'utilisateur
                return $this->redirectToRoute("admin_user_documents");
        }
    }

    /**
     * @Route("/send-mail/{docsType}/{userId}", name="_send_docs_mail")
     * 
     * @return mixed RedirectResponse ou Response
     */
    public function sendDocsMail(
        Request $req, string $docsType, string $userId, MailerInterface $mailer
    )
    {
        if (!$this->checkAccess($req)) {
            return $this->redirectToRoute("home");
        }
        $user = $this->userRepository->findBy(["id" => $userId]);
        if (empty($user)) {
            return $this->redirectToRoute("admin_user_documents");
        }
        $this->getMailMessage($docsType, $userId);
        $this->sendMail(
            $mailer, $user[0]->getEmail(), 
            "Du nouveau concernant la validation de vos documents - Granger SAS",
            "emails/documents_mail.html.twig",
            ["message" => $this->getMailMessage($docsType, $userId)]
        );
        
        return $this->redirectToRoute("admin_user", [
            "id" => $userId
        ]);
    }

    /**
     * Renvoie le message de l'email selon le type de document ainsi que leur
     * validité
     * 
     * @param  string $docsType Le type de document
     * @param  string $userId   L'identifiant de l'utilisateur à qui envoyer le 
     *                          mail
     * @return string           Le message de l'email
     */
    private function getMailMessage(string $docsType, string $userId): string
    {
        $documents = null;
        if ($docsType === "VERIFICATION") {
            $documents = $this->documentsRepository->findAll();
        } else {
            $documents = $this->documentsRepository->findBy([
                "step" => $docsType
            ]);
        }
        $uploadedDocs = $this->uploadedDocsRepository->findBy([
            "user" => $userId,
            "document" => $documents
        ]);

        if (count($uploadedDocs) != count($documents)) {
            return 
                "Bonjour,<br/><br/>Nous sommes en attente de"
                . " certaines de vos pièces justificatives. Ceci peut-être dû à"
                . " une demande de renouvellement de la part de notre équipe."
                . " Nous vous demandons par conséquent de bien vouloir les"
                . " les déposer sur votre profil dans les plus brefs délais !"
                . "<br/><br/>Cordialement,<br/>Granger SAS.";
        }
        // On vérifie que les documents sont valides
        $documentsAreValid = true;
        foreach ($uploadedDocs as $doc) {
            if (!$doc->getAccepted()) {
                $documentsAreValid = false;
                break;
            }
        }
        // Et on envoie le message personnalisé selon les cas
        if ($documentsAreValid) {
            $user = $this->userRepository->findBy(["id" => $userId]);
            $message =
                "Bonjour,<br/><br/>Félicitations !"
                . " Toutes vos pièces justificatives ont été acceptées.";
            if (
                $docsType === "VERIFICATION" 
                || $docsType === Constants::DRIVER_STEP
            ) {
                $user[0]->setStatus(Constants::DRIVER_STATUS);
                $message .= 
                    " Votre profil étant désormais vérifié vous êtes"
                    . " officiellement identifié en tant que conducteur sur le"
                    . " site Granger SAS !";
            } else if ($docsType === Constants::HIRE_STEP) {
                $message .= " Il vous reste une dernière étape avant d'être"
                . " officiellement reconnu en tant que conducteur. De nouvelles"
                . " pièces sont à faire valider sur votre profil !";
            } else if ($docsType === Constants::CANDIDAT_STEP) {
                $message .=
                 " Nous vous préviendrons dans les plus brefs délais au sujet"
                 . " de votre candidature.";
            }

            return $message . "<br/><br/>Cordialement,<br/>Granger SAS.";
        }

        return 
            "Bonjour,<br/><br/>Nous sommes navré de vous annoncer que"
            . " certaines de vos pièces justificatives sont invalides."
            . " Vous pouvez cependant corriger ceci en suivant les indications"
            . " apportées à chacune de vos pièces sur votre profil."
            . " Merci de les redéposer dans les plus brefs délais !"
            . " <br/><br/>Cordialement,<br/>Granger SAS.";
    }

    /**
     * @Route("/fire/{id}", name="_fire")
     * 
     * @return mixed RedirectResponse ou Response
     */
    public function fire(Request $req, string $id) {
        if (!$this->checkAccess($req)) {
            return $this->redirectToRoute("home");
        }
        $user = $this->userRepository->findBy(["id" => $id]);
        if (empty($user)) {
            return $this->redirectToRoute("admin_users");
        }
        $user = $user[0];
        if ($req->get("confirm") !== null) {
            $user->setStatus(Constants::DEFAULT_STATUS);
            $this->em->flush();
            return $this->redirectToRoute("admin_users");
        }

        return $this->render("admin/fire.html.twig", [
            "user" => $user
        ]);
    }

    /**
     * @Route("/map", name="_map")
     * 
     * @return mixed RedirectResponse ou Response
     */
    public function map(Request $req)
    {
        if (!$this->checkAccess($req)) {
            return $this->redirectToRoute("home");
        }

        return $this->render("admin/map.html.twig", [
            "api_key" => Constants::MAPS_API_KEY
        ]);
    }

    /**
     * @Route("/map/markers", name="_map_markers")
     * 
     * @return mixed RedirectResponse ou JSONResponse
     */
    public function getMapMarkers(Request $req)
    {
        if (!$this->checkAccess($req)) {
            return $this->redirectToRoute("home");
        }
        // On vérifie qu'il s'agit bien d'une requête AJAX
        if (!$req->isXmlHttpRequest()) {
            return new JsonResponse([
                "error" => "Requête invalide"
            ], Response::HTTP_BAD_REQUEST);
        }
        // On prend la latitude et la longitude de tous les utilisateurs devant
        // apparaitre sur la map
        $markers = [];
        foreach ($this->userRepository->findAll() as $user) {
            $icon = Constants::getMapIcon($user->getStatus());
            if ($icon !== null) {
                $markers[] = [
                    "lat" => $user->getLatitude(),
                    "long" => $user->getLongitude(),
                    "icon" => $icon,
                    "user" => $this->generateUrl("admin_candidature", [
                        "driveId" => $user->getDriveID()
                    ])
                ];
            }
        }

        return new JsonResponse([
            "markers" => $markers
        ], RESPONSE::HTTP_OK);
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
            case "admin_documents":
            case "admin_add_document":
            case "admin_delete_document":
            case "admin_users_documents":
            case "admin_user":
            case "admin_handle_document":
            case "admin_send_docs_mail":
            case "admin_map":
            case "admin_map_markers":
                return in_array("ROLE_ADMIN", $userRoles)
                    || in_array("ROLE_RH", $userRoles);
            
            case "admin_edit":
            case "admin_edit_POST":
                return in_array("ROLE_ADMIN", $userRoles)
                    || in_array("ROLE_EDITOR", $userRoles);
            
            case "admin_users":
            case "admin_users_POST":
            case "admin_fire":
                return in_array("ROLE_ADMIN", $userRoles);

            default:
                return in_array("ROLE_ADMIN", $userRoles)
                    || in_array("ROLE_EDITOR", $userRoles)
                    || in_array("ROLE_RH", $userRoles);
        }
    }

}
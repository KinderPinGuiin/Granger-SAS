<?php

namespace App\Controller;

use App\Entity\Candidature;
use App\Utils\Constants;
use App\Utils\GoogleDriveManager;
use Symfony\Component\Mime\Email;
use App\Form\CandidatureHandlingType;
use App\Repository\CandidatureRepository;
use App\Repository\UserRepository;
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

    public function __construct(UserRepository $repository, CandidatureRepository $cRepository)
    {
        $this->driveManager = new GoogleDriveManager(
            Constants::GOOGLE_FOLDER . "credentials.json",
            Constants::ID_DRIVE_ROOT
        );
        $this->userRepository = $repository;
        $this->candidRepository = $cRepository;
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
        // On liste les candidatures non traitées
        $candidatures = $this->candidRepository->getNotHandled();

        return $this->render("admin/candidatures.html.twig", [
            "candidatures" => $candidatures
        ]);
    }

    /**
     * @Route("/candidature/{driveId}", name="_candidature", methods={"GET"})
     * 
     * @return mixed RedirectResponse ou Response
     */
    public function adminCandidature(string $driveId, Request $request)
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
            }
        }
        // On récupère le CV et la lettre de motivation
        $cvLettre = $this->getCVAndLetter($driveId);
        // Création du formulaire de réponse
        $form = $this->createForm(CandidatureHandlingType::class);

        return $this->render("admin/candidature.html.twig", [
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
        $em = $this->getDoctrine()->getManager();
        $candidat = $this->userRepository->getByDriveId($driveId);
        $candidature = $this->candidRepository->getNotHandled("user = " . $candidat->getId())[0];
        $form = $this->createForm(CandidatureHandlingType::class, $candidature);
        $form->handleRequest($req);
        // On vérifie les données du formulaire
        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();
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
            return $this->redirectToRoute("admin_candidatures");
        }
        // Si elles ne sont pas bonnes on renvoie l'utilisateur sur le 
        // formulaire
        $cvLettre = $this->getCVAndLetter($driveId);

        return $this->render("admin/candidature.html.twig", [
            "cv" => $cvLettre["cv"],
            "lettre" => $cvLettre["lettre"],
            "form" => $form->createView()
        ]);
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
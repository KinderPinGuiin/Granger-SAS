<?php

namespace App\Controller;

use App\Entity\UploadedDocuments;
use App\Entity\ValidationRequest;
use App\Utils\Constants;
use App\Form\UserUpdateType;
use App\Form\ValidationType;
use App\Utils\GoogleDriveManager;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use App\Repository\CandidatureRepository;
use App\Repository\DocumentsRepository;
use App\Utils\GoogleDriveUploader;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * @Route("/profil", name="profil")
 */
class ProfilController extends AbstractController
{

    /**
     * @var UrlGeneratorInterface
     */
    private $urlGenerator;

    /**
     * @var CandidatureRepository
     */
    private $candidatureRepository;

    /**
     * @var DocumentsRepository
     */
    private $documentsRepository;

    /**
     * @var EntityManagerInterface
     */
    private $em;

    public function __construct(UrlGeneratorInterface $urlGenerator, CandidatureRepository $candidatureRepository, DocumentsRepository $dRep, EntityManagerInterface $em)
    {
        $this->urlGenerator = $urlGenerator;
        $this->candidatureRepository = $candidatureRepository;
        $this->documentsRepository = $dRep;
        $this->em = $em;
    }

    /**
     * @Route("/", name="")
     */
    public function index(Request $req, UserPasswordHasherInterface $passwordEncoder): Response
    {
        // Si l'utilisateur n'est pas connecté on le redirige sur la page de
        // connexion
        if (empty($this->getUser())) {
            // On définit les variables de redirection
            $this->get("session")->set("redirect", "profil");
            $this->get("session")->set("logged", false);
            return new RedirectResponse($this->urlGenerator->generate("login"));
        }
        // On récupère les candidatures de l'utilisateur dans l'ordre 
        // décroissant
        $candidatures = $this->candidatureRepository->findBy([
            "user" => $this->getUser()->getId()
        ], ["id" => "DESC"]);
        // On créé le formulaire d'update des informations
        $user = $this->getUser();
        $form = $this->createForm(UserUpdateType::class, $user);
        $form->handleRequest($req);
        if ($form->isSubmitted() && $form->isValid()) {
            // On renomme le dossier drive
            $driveManager = new GoogleDriveManager(
                Constants::GOOGLE_FOLDER . "credentials.json",
                Constants::ID_DRIVE_ROOT
            );
            $driveManager->update($user->getDriveID(), [
                "name" => Constants::folderName($user)
            ]);
            if (!empty($form->get("plainPassword")->getViewData()["first"])) {
                $user->setPassword(
                    $passwordEncoder->hashPassword(
                        $user,
                        $form->get("plainPassword")->getViewData()["first"]
                    )
                );
            }
            $this->em->flush();
        }

        return $this->render('profil/index.html.twig', [
            "candidatures" => $candidatures,
            "user" => $this->getUser(),
            "updateForm" => $form->createView(),
            "documents" => $this->documentsRepository->findBy([
                "step" => Constants::HIRE_STEP
            ])
        ]);
    }

    /**
     * @Route("/delete", name="_delete")
     */
    public function deleteAccount(Request $req, UserRepository $userRepository, EntityManagerInterface $em)
    {
        // Si l'utilisateur n'est pas connecté on le redirige sur l'accueil
        if (!$this->getUser()) {
            return $this->redirectToRoute("home");
        }
        // Si l'utilisateur a confirmé la suppression on supprime son compte
        if ($req->get("accept") === "true") {
            // On récupère l'utilisateur et on le supprime
            $user = $userRepository->findBy(
                ["id" => $this->getUser()->getId()]
            )[0];
            // On invalide la session
            $session = new Session();
            $session->invalidate();
            // On supprime d'abord son dossier drive
            $driveManager = new GoogleDriveManager(
                Constants::GOOGLE_FOLDER . "credentials.json",
                Constants::ID_DRIVE_ROOT
            );
            $driveManager->delete($user->getDriveID());
            $em->remove($user);
            $em->flush();

            return $this->redirectToRoute("home");
        }

        return $this->render("profil/delete.html.twig");
    }

    /**
     * @Route("/upload-documents", name="_upload_documents", methods="POST")
     * 
     * Upload les documents via une requête AJAX
     */
    public function uploadDocuments(Request $req): Response
    {
        // Si l'utilisateur n'est pas autorisé à être ici on renvoie une erreur
        if (!$req->isXmlHttpRequest()) {
            return new JsonResponse([
                "error" => "Requête invalide"
            ], Response::HTTP_BAD_REQUEST);
        }
        if ($this->getUser()->getStatus() !== Constants::ACCEPTED_STATUS) {
            return new JsonResponse([
                "error" => "Vous n'êtes pas en mesure de déposer des pièces" 
                           . " justificatives"
            ], Response::HTTP_FORBIDDEN);
        }
        // On détermine le fichier envoyé
        $documents = $this->documentsRepository->findAll();
        foreach ($documents as $document) {
            if ($req->files->get($document->getSlug()) !== null) {
                // Et on le dépose sur le google drive
                $mime = $req->files->get($document->getSlug())->getMimeType();
                if (
                    $mime == "application/pdf" 
                    || $mime == "application/x-pdf"
                ) {
                    $driveUploader = new GoogleDriveUploader();
                    $uploaded = $driveUploader->upload(
                        $this->getUser(),
                        $document->getNom(),
                        $document->getNom(),
                        $req->files->get($document->getSlug())->getPathName()
                    );
                    if (!$uploaded) {
                        return new JsonResponse([
                            "error" => "Erreur lors du dépôt du fichier, veuillez"
                                       . " réessayer"
                        ], Response::HTTP_BAD_REQUEST);
                    }
                    // On ajoute le document en BDD
                    $uploadedDoc = new UploadedDocuments();
                    $uploadedDoc
                        ->setDocument($document)
                        ->setUser($this->getUser());
                    $this->em->persist($uploadedDoc);
                    $this->em->flush();
                } else {
                    // Si le fichier n'est pas du bon type on renvoie une erreur
                    return new JsonResponse([
                        "error" => "Le type du fichier est invalide. Nous"
                                   . " n'acceptons que les PDF"
                    ], Response::HTTP_BAD_REQUEST);
                }
                return new JsonResponse([
                    "message" => "Fichier déposé avec succès",
                ], Response::HTTP_OK);
            }
        }

        return new JsonResponse([
            "message" => "Fichier invalide"
        ], Response::HTTP_BAD_REQUEST);
    }

    /**
     * @Route("/validation", name="_validation")
     */
    public function validate(Request $req): Response
    {
        // Si l'utilisateur n'est pas connecté on le redirige sur l'accueil
        if (!$this->getUser()) {
            return $this->redirectToRoute("home");
        }
        $form = $this->createForm(ValidationType::class);
        $form->handleRequest($req);
        if ($form->isSubmitted() && $form->isValid()) {
            // On ajoute la demande de validation dans la BDD
            $validation = new ValidationRequest();
            $validation->setUser($this->getUser());
            $this->em->persist($validation);
            $this->em->flush();

            return $this->redirectToRoute("profil");
        }

        return $this->render("profil/validation.html.twig", [
            "form" => $form->createView(),
            "user" => $this->getUser()
        ]);
    }

}

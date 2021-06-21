<?php

namespace App\Controller;

use App\Utils\Constants;
use App\Utils\GoogleDriveManager;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

/**
 * @Route("/profil", name="profil")
 */
class ProfilController extends AbstractController
{

    /**
     * @var UrlGeneratorInterface
     */
    private $urlGenerator;

    public function __construct(UrlGeneratorInterface $urlGenerator)
    {
        $this->urlGenerator = $urlGenerator;
    }

    /**
     * @Route("/", name="")
     */
    public function index(): Response
    {
        // Si l'utilisateur n'est pas connecté on le redirige sur la page de
        // connexion
        if (empty($this->getUser())) {
            return new RedirectResponse(
                $this->urlGenerator->generate("login") . "?redirect=profil"
            );
        }

        return $this->render('profil/index.html.twig', [
            "candidatures" => $this->getUser()->getCandidatures(),
            "user" => $this->getUser()
        ]);
    }

    /**
     * @Route("/delete", name="_delete")
     */
    public function deleteAccunt(Request $req, UserRepository $userRepository, EntityManagerInterface $em)
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

}

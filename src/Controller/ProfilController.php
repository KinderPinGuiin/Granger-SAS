<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class ProfilController extends AbstractController
{

    /**
     * @Route("/profil", name="profil")
     */
    public function index(): Response
    {
        // Si l'utilisateur n'est pas connecté on le redirige sur l'accueil
        if (!$this->getUser()) {
            return $this->redirectToRoute("home");
        }
        // Sinon on récupère ses candidatures
        $candidatures = $this->getUser()->getCandidatures();

        return $this->render('profil/index.html.twig', [
            "candidatures" => $candidatures,
            "user" => $this->getUser()
        ]);
    }

}

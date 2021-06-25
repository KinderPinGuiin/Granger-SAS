<?php

namespace App\Controller;

use App\Repository\OffreRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class OffreController extends AbstractController
{

    /**
     * @var OffreRepository
     */
    private $offreRepository;

    public function __construct(OffreRepository $oRep)
    {
        $this->offreRepository = $oRep;
    }

    /**
     * @Route("/offres", name="offres")
     */
    public function offres(): Response
    {
        return $this->render('offre/offres.html.twig', [
            "offres" => $this->offreRepository->findBy([], ["date" => "DESC"])
        ]);
    }

    /**
     * @Route("/offre/{id}", name="offre")
     */
    public function offre(string $id): Response
    {
        $offre = $this->offreRepository->findBy(["id" => $id]);
        // Si l'offre n'existe pas on redirige l'utilisateur sur la page des 
        // offres
        if (empty($offre)) {
            return $this->redirectToRoute("home");
        }

        return $this->render('offre/offre.html.twig', [
            "offre" => $offre[0]
        ]);
    }

}

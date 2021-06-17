<?php

namespace App\Controller;

use App\Repository\ContenuRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class AboutController extends AbstractController
{
    /**
     * @Route("/about", name="about")
     */
    public function index(ContenuRepository $cRep): Response
    {
        return $this->render('about/index.html.twig', [
            'controller_name' => 'AboutController',
            "content" => $cRep->findBy(["page" => "about"])[0]->getContent()
        ]);
    }
}

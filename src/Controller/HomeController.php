<?php

namespace App\Controller;

use App\Repository\ContenuRepository;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class HomeController extends AbstractController
{
    /**
     * @Route("/", name="home")
     */
    public function index(ContenuRepository $cRep): Response
    {
        return $this->render('home/index.html.twig', [
            'controller_name' => 'HomeController',
            "message" => (isset($_GET["message"])) ? $_GET["message"] : null,
            "content" => $cRep->findBy(["page" => "home"])[0]->getContent()
        ]);
    }
}

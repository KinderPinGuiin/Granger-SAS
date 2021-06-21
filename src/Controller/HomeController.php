<?php

namespace App\Controller;

use App\Utils\Constants;
use App\Utils\GoogleDriveManager;
use App\Repository\ContenuRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class HomeController extends AbstractController
{

    /**
     * @var EntityManagerInterface
     */
    private $em;

    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
    }

    /**
     * @Route("/", name="home")
     */
    public function index(Request $req, ContenuRepository $cRep): Response
    {
        return $this->render('home/index.html.twig', [
            'controller_name' => 'HomeController',
            "message" => (isset($_GET["message"])) ? $_GET["message"] : null,
            "content" => $cRep->findBy(["page" => "home"])[0]->getContent()
        ]);
    }

}

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
        // Si l'utilisateur vient de se connecter on vérifie que son dossier
        // drive est intact
        if ($req->get("logged") === "true" && $this->getUser()) {
            $this->checkDriveFolder();
        }

        return $this->render('home/index.html.twig', [
            'controller_name' => 'HomeController',
            "message" => (isset($_GET["message"])) ? $_GET["message"] : null,
            "content" => $cRep->findBy(["page" => "home"])[0]->getContent()
        ]);
    }

    /**
     * Recréé le dossier drive de l'utilisateur en cas de besoin
     */
    private function checkDriveFolder()
    {
        $driveManager = new GoogleDriveManager(
            Constants::GOOGLE_FOLDER . "credentials.json",
            Constants::ID_DRIVE_ROOT
        );
        $user = $this->getUser();
        if (!$driveManager->fileExists(null, $user->getDriveID(), true)) {
            // Si le dossier n'existe pas on le recréé
            $folder = $driveManager->createFolder(
                $user->getPrenom() . " " . $user->getNom() . " | " 
                . $user->getEmail()
            );
            $user->setDriveID($folder["id"]);
            $this->em->flush();
            $driveManager->goTo($folder["id"]);
            $driveManager->createFolder(Constants::LETTER_FOLDER_NAME);
            $driveManager->createFolder(Constants::CV_FOLDER_NAME);
        }
    }

}

<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\RegistrationFormType;
use Constants;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Utils\GoogleDriveManager;

class RegistrationController extends AbstractController
{
    /**
     * @Route("/register", name="app_register")
     */
    public function register(Request $request, UserPasswordHasherInterface $passwordEncoder): Response
    {
        $user = new User();
        $form = $this->createForm(RegistrationFormType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $user->setPassword(
                $passwordEncoder->hashPassword(
                    $user,
                    $form->get("plainPassword")->getData()
                )
            );
            $user->setRoles(["ROLE_USER"]);
            $this->createDriveFolder($user);

            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->persist($user);
            $entityManager->flush();

            return $this->redirectToRoute('home');
        }

        return $this->render('registration/register.html.twig', [
            'registrationForm' => $form->createView(),
        ]);
    }

    /**
     * Créé un dossier sur le drive pour l'utilisateur et rempli la colonne
     * drive_id
     * 
     * @param User $user L'utilisateur
     */
    private function createDriveFolder($user)
    {
        $driveManager = new GoogleDriveManager(
            Constants::GOOGLE_FOLDER . "credentials.json",
            Constants::DRIVE_ROOT
        );
        $folder = $driveManager->createFolder(
            $user->getPrenom() . " " . $user->getNom() . " | " . $user->getEmail()
        );
        $user->setDriveID($folder["id"]);
        $driveManager->goTo($folder["id"]);
        $driveManager->createFolder(Constants::LETTRE_FOLDER_NAME);
        $driveManager->createFolder(Constants::CV_FOLDER_NAME);
    }
}

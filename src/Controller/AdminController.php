<?php

namespace App\Controller;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

/**
 * @Route("/admin", name="admin")
 */
class AdminController extends AbstractController {

    /**
     * @Route("/", name="")
     * 
     * @return RedirectResponse
     * @return Response
     */
    public function admin(): RedirectResponse
    {
        $this->checkAccess();
        return $this->render("admin/index.html.twig");
    }

    /**
     * @Route("/candidatures", name="_candidatures")
     * 
     * @return RedirectResponse
     * @return Response
     */
    public function adminCandidatures(): RedirectResponse
    {
        $this->checkAccess();
        return $this->render("admin/candidatures.html.twig");
    }

    /**
     * @Route("/edit", name="_edit")
     * 
     * @return RedirectResponse
     * @return Response
     */
    public function adminEdit(): RedirectResponse
    {
        $this->checkAccess();
        return $this->render("admin/edit.html.twig");
    }

    /**
     * Redirige l'utilisateur à l'accueil s'il n'est pas autorisé à accéder
     * à la oage d'administration
     * 
     * @return RedirectResponse
     * @return Response
     */
    private function checkAccess(): RedirectResponse
    {
        /**
         * Si l'utilisateur n'est pas connecté ou s'il n'est pas admin on le
         * redirige
         */
        if (
            empty($this->getUser()) 
            || !in_array("ROLE_ADMIN", $this->getUser()->getRoles())
        ) {
            return $this->redirectToRoute('home');
        }
    }
}
<?php

namespace App\Admin;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

/**
 * @Route("/admin", name="admin")
 */
class AdminController extends AbstractController {
    /**
     * @Route("/", name="")
     */
    public function admin() {
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

        return $this->render("admin/index.html.twig");
    }
}
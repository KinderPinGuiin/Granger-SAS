<?php

namespace App\Controller;

use App\Entity\Image;
use App\Form\ImageType;
use App\Utils\Constants;
use App\Repository\ImageRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class ImagesController extends AbstractController
{

    /**
     * @var EntityManagerInterface $em
     */
    private $em;

    /**
     * @var ImageRepository
     */
    private $imageRepository;

    public function __construct(EntityManagerInterface $em, ImageRepository $rep)
    {
        $this->em = $em;
        $this->imageRepository = $rep;
    }

    /**
     * @Route("/images", name="images")
     */
    public function getAllImages(UrlGeneratorInterface $gen)
    {
        // On récupère toutes les images
        $images = $this->imageRepository->findAll();
        // On construit le JSON
        $imagesJSON = [];
        foreach ($images as $image) {
            $imagesJSON[$image->getId()] = [
                "name" => $image->getName(),
                "alt" => $image->getAlt(),
                "mime" => $image->getMime(),
                "width" => $image->getWidth(),
                "height" => $image->getHeight(),
                "url" => $gen->generate("image", ["id" => $image->getId()])
            ];
        }

        return new JsonResponse($imagesJSON);
    }

    /**
     * @Route("/image/{id}", name="image", requirements={"id"="\d+"})
     */
    public function getImage(string $id): Response
    {
        // On récupère l'image
        $image = $this->imageRepository->findBy(["id" => $id]);
        if (empty($image)) {
            // Si l'image n'est pas trouvée on met une image par défaut
            return new Response(
                file_get_contents(Constants::DEFAULT_IMAGE),
                Response::HTTP_NOT_FOUND, 
                [
                    "content-type" => "image/png"
                ]
            );
        }
        // Sinon on affiche l'image
        return new Response(
            stream_get_contents($image[0]->getContent()),
            Response::HTTP_OK, 
            [
                "content-type" => $image[0]->getMime()
            ]
        );
    }

    /**
     * @Route("/image/upload", name="image_upload_get", methods="GET")
     */
    public function uploadImage_get()
    {
        // Si la page est accédée en GET on redirige l'utilisateur sur l'accueil
        return $this->redirectToRoute("home");
    }

    /**
     * @Route("/image/upload", name="image_upload", methods="POST")
     */
    public function uploadImage(Request $req)
    {
        if (
            empty($this->getUser()) ||
            (
                !in_array("ROLE_ADMIN", $this->getUser()->getRoles())
                && !in_array("ROLE_EDITOR", $this->getUser()->getRoles())
            )
        ) {
            // Si l'utilisateur n'est pas autorisé on le redirige à l'accueil
            return $this->redirectToRoute("home");
        }
        // On créé le formulaire puis on le traite
        $image = new Image();
        $form = $this->createForm(ImageType::class, $image);
        $form->handleRequest($req);
        // On vérifie les informations
        if (!$form->isSubmitted() || !$form->isValid()) {
            // Si une d'entre elle est invalide on renvoie un code 500
            return new JsonResponse([
                "error" => "Fichier trop volumineux ou de mauvais type"
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
        // Si tout est bon on ajoute l'image dans la base de données
        $image->setContent(
            file_get_contents($form->get("content")->getData()->getPathname())
        );
        $image->setMime($form->get("content")->getData()->getMimeType());
        $image->setWidth(
            getimagesize($form->get("content")->getData()->getPathname())[0]
        );
        $image->setHeight(
            getimagesize($form->get("content")->getData()->getPathname())[1]
        );
        $this->em->persist($image);
        $this->em->flush();

        return new JsonResponse(["message" => "Uploadé"], Response::HTTP_OK);
    }

    /**
     * @Route(
     *     "/image/delete/{id}", name="image_delete", requirements={"id"="\d+"}
     * )
     */
    public function deleteImage(string $id)
    {
        if (
            empty($this->getUser()) ||
            (
                !in_array("ROLE_ADMIN", $this->getUser()->getRoles())
                && !in_array("ROLE_EDITOR", $this->getUser()->getRoles())
            )
        ) {
            // Si l'utilisateur n'est pas autorisé on le redirige à l'accueil
            return $this->redirectToRoute("home");
        }
        // On récupère l'image à supprimer
        $image = $this->imageRepository->findBy(["id" => $id]);
        if (empty($image)) {
            // Si l'image n'existe pas on renvoie une erreur
            return new JsonResponse([
                "error" => "Image inexistante"
            ], Response::HTTP_NOT_FOUND);
        }
        // On supprime l'image
        $this->em->remove($image[0]);
        $this->em->flush();

        return new JsonResponse(["message" => "Supprimée"], Response::HTTP_OK);
    }

}

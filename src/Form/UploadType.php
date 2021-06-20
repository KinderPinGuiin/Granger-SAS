<?php

namespace App\Form;

use App\Repository\PosteRepository;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\FileType;

class UploadType extends AbstractType
{
    /**
     * @var PosteRepository
     */
    private $posteRepository;

    public function __construct(PosteRepository $pRep)
    {
        $this->posteRepository = $pRep;
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        // On récupère tous les postes
        $choices = [];
        foreach ($this->posteRepository->findAll() as $poste) {
            $choices[$poste->getName()] = $poste->getSlug();
        }
        // Et on construit le formulaire
        $builder
            ->add("poste", ChoiceType::class, [
                "label" => false,
                "mapped" => false,
                "invalid_message" => "Valeur séléctionnée invalide",
                "choices" => $choices,
                'placeholder' => 'Choisissez un poste',
            ])
            ->add('cv', FileType::class, [
                "label" => "Déposer un CV",
                "mapped" => false,
                "constraints" => [
                    new File([
                        "maxSize" => "3M",
                        "mimeTypes" => [
                            "application/pdf",
                            "application/x-pdf"
                        ],
                        "maxSizeMessage" => "Fichier trop volumineux (3Mo Max.)",
                        "mimeTypesMessage" => "Format invalide"
                    ])
                ]
            ])
            ->add('lettre', FileType::class, [
                "label" => "Déposer une lettre de motivation",
                "mapped" => false,
                "constraints" => [
                    new File([
                        "maxSize" => "3M",
                        "mimeTypes" => [
                            "application/pdf",
                            "application/x-pdf"
                        ],
                        "maxSizeMessage" => "Fichier trop volumineux (3Mo Max.)",
                        "mimeTypesMessage" => "Format invalide"
                    ])
                ]
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([]);
    }
}

<?php

namespace App\Form;

use App\Repository\PosteRepository;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\NotNull;

class UploadType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        // Et on construit le formulaire
        $builder
            ->add('cv', FileType::class, [
                "label" => "Déposer un CV",
                "mapped" => false,
                "constraints" => [
                    new NotBlank(),
                    new NotNull(),
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
                    new NotBlank(),
                    new NotNull(),
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

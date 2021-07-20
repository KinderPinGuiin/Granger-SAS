<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\Validator\Constraints\NotNull;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\FileType;

class ValidationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('permis', FileType::class, [
                "label" => "Déposer votre permis de conduire",
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
            ->add('contrat', FileType::class, [
                "label" => "Déposer votre contrat de travail avec Granger SAS",
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
        $resolver->setDefaults([
            // Configure your form options here
        ]);
    }
}

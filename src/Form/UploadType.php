<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\FileType;

class UploadType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('cv', FileType::class, [
                "label" => "Déposer un CV",
                "mapped" => false,
                "constraints" => [
                    new File([
                        "maxSize" => "3072k",
                        "mimeTypes" => [
                            "application/pdf",
                            "application/x-pdf"
                        ],
                        "mimeTypesMessage" => "Format invalide"
                    ])
                ]
            ])
            ->add('lettre', FileType::class, [
                "label" => "Déposer une lettre de motivation",
                "mapped" => false,
                "constraints" => [
                    new File([
                        "maxSize" => "3072k",
                        "mimeTypes" => [
                            "application/pdf",
                            "application/x-pdf"
                        ],
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

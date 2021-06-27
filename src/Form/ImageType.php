<?php

namespace App\Form;

use App\Entity\Image;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\FileType;

class ImageType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('name', null, [
                "label" => false,
                "attr" => [
                    "placeholder" => "Nom de l'image"
                ]
            ])
            ->add('alt', null, [
                "label" => false,
                "attr" => [
                    "placeholder" => "Description"
                ]
            ])
            ->add('content', FileType::class, [
                "mapped" => false,
                "label" => false,
                "constraints" => [
                    new File([
                        "maxSize" => "3M",
                        "mimeTypes" => "image/*",
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
            'data_class' => Image::class,
        ]);
    }
}

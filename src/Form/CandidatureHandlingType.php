<?php

namespace App\Form;

use App\Entity\Candidature;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;

class CandidatureHandlingType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add("acceptee", ChoiceType::class, [
                "choices" => [
                    "Accepter la candidature" => true,
                    "Refuser la candidature" => false
                ]
            ])
            ->add('message', TextareaType::class, [
                "mapped" => false,
                "label" => false,
                "attr" => [
                    "placeholder" => "Votre message..."
                ],
                "constraints" => [
                    new NotBlank()
                ]
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            "data_class" => Candidature::class
        ]);
    }
}

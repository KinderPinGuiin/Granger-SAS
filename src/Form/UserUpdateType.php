<?php

namespace App\Form;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;

class UserUpdateType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('email', EmailType::class)
            ->add('plainPassword', RepeatedType::class, [
                'mapped' => false,
                "type" => PasswordType::class,
                "invalid_message" => "Les deux mots de passe ne sont pas"
                                     . " identiques",
                "options" => ['attr' => ['autocomplete' => 'new-password']],
                'constraints' => [
                    new Length([
                        'min' => 6,
                        'minMessage' => 'Le mot de passe doit contenir au moins' 
                                        . '6 caractères',
                        // max length allowed by Symfony for security reasons
                        'max' => 4096,
                        "maxMessage" => "Le mot de passe ne doit pas contenir"
                                        . " plus de 4096 caractères"
                    ]),
                ],
            ])
            ->add('nom')
            ->add('prenom')
            ->add('telephone', TelType::class)
            ->add('ville')
        ;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => User::class,
        ]);
    }
}

<?php

namespace App\Form;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Gregwar\CaptchaBundle\Type\CaptchaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints\IsTrue;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\Extension\Core\Type\TelType;

class RegistrationFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('email', EmailType::class)
            ->add('nom')
            ->add('prenom')
            ->add('plainPassword', RepeatedType::class, [
                'mapped' => false,
                "type" => PasswordType::class,
                "invalid_message" => "Les deux mots de passe ne sont pas"
                                     . " identiques",
                "required" => true,
                "options" => ['attr' => ['autocomplete' => 'new-password']],
                'constraints' => [
                    new NotBlank([
                        'message' => 'Vous devez entrer un mot de passe',
                    ]),
                    new Length([
                        'min' => 6,
                        'minMessage' => 'Le mot de passe doit contenir au moins' 
                                        . ' 6 caractères',
                        // max length allowed by Symfony for security reasons
                        'max' => 4096,
                        "maxMessage" => "Le mot de passe ne doit pas contenir"
                                        . " plus de 4096 caractères"
                    ]),
                ],
            ])
            ->add('telephone', TelType::class)
            ->add('ville')
            ->add('agreeTerms', CheckboxType::class, [
                'mapped' => false,
                'constraints' => [
                    new IsTrue([
                        'message' => 'Vous devez accepter les conditions ' .
                                     'd\'utilisation',
                    ]),
                ],
            ])
            ->add('latitude', HiddenType::class, [
                "attr" => [
                    "class" => "latitude" 
                ]
            ])
            ->add('longitude', HiddenType::class, [
                "attr" => [
                    "class" => "longitude" 
                ]
            ])
            ->add('captcha', CaptchaType::class, [
                "mapped" => false,
                "attr" => [
                    "class" => "captcha",
                ],
                "invalid_message" => "Captcha incorrect"
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => User::class,
        ]);
    }
}

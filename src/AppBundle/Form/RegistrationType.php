<?php

namespace AppBundle\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;

class RegistrationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('username')
            ->add('password', 'repeated', array(
                'first_name' => 'password',
                'second_name' => 'confirm_password',
                'type' => 'password',
            ))
            ->add('email')
            ->add('firstName')
            ->add('lastName')
            ;
    }

    /**
     * @param OptionsResolverInterface $resolver
     */
    public function setDefaultOptions(OptionsResolverInterface $resolver)
    {
        $resolver->setDefaults(array(
//            'error_bubbling' => true,
//            'cascade_validation' => true,
            'data_class' => 'AppBundle\Document\User',
            'validation_groups' => array('registration'),
        ));
    }

    public function getName()
    {
        return 'register';
    }
}
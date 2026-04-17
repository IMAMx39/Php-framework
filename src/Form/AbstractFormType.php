<?php

declare(strict_types=1);

namespace Framework\Form;

/**
 * Classe de base pour les types de formulaires réutilisables.
 *
 * Usage :
 *   class ContactFormType extends AbstractFormType
 *   {
 *       public function buildForm(FormBuilder $builder): void
 *       {
 *           $builder
 *               ->add('name',    'text',  ['rules' => 'required|min:2|max:100'])
 *               ->add('email',   'email', ['rules' => 'required|email'])
 *               ->add('message', 'textarea', ['rules' => 'required|min:10']);
 *       }
 *   }
 *
 *   // Dans un contrôleur :
 *   $form = $factory->create(new ContactFormType());
 *   $form->handleRequest($request);
 */
abstract class AbstractFormType
{
    abstract public function buildForm(FormBuilder $builder): void;
}

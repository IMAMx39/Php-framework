<?php

declare(strict_types=1);

namespace Framework\Form;

/**
 * Crée des instances de Form à partir d'un AbstractFormType ou d'un FormBuilder.
 *
 * Enregistrement dans le conteneur :
 *   $container->singleton(FormFactory::class, fn() => new FormFactory());
 *
 * Usage dans un contrôleur :
 *   $form = $this->container->get(FormFactory::class)
 *                           ->create(new LoginFormType());
 */
class FormFactory
{
    /**
     * Crée un Form à partir d'un FormType.
     */
    public function create(AbstractFormType $type): Form
    {
        $builder = new FormBuilder();
        $type->buildForm($builder);

        return $builder->build();
    }

    /**
     * Crée un FormBuilder vide pour une construction ad-hoc.
     */
    public function createBuilder(): FormBuilder
    {
        return new FormBuilder();
    }
}

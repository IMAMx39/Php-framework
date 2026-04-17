<?php

declare(strict_types=1);

namespace Framework\Form;

/**
 * Construit la définition d'un formulaire via une API fluide.
 *
 * Usage dans un FormType :
 *   $builder
 *       ->add('name',     'text',     ['rules' => 'required|min:2'])
 *       ->add('email',    'email',    ['rules' => 'required|email'])
 *       ->add('password', 'password', ['rules' => 'required|min:8'])
 *       ->add('role',     'select',   ['choices' => ['Admin' => 'admin', 'User' => 'user']]);
 *
 * Types de champs disponibles :
 *   text | email | password | number | textarea | select | checkbox | hidden
 */
class FormBuilder
{
    /** @var FormField[] */
    private array $fields = [];

    public function add(string $name, string $type = 'text', array $options = []): static
    {
        $this->fields[$name] = new FormField($name, $type, $options);

        return $this;
    }

    public function remove(string $name): static
    {
        unset($this->fields[$name]);

        return $this;
    }

    public function has(string $name): bool
    {
        return isset($this->fields[$name]);
    }

    /** @return FormField[] */
    public function getFields(): array
    {
        return $this->fields;
    }

    public function build(): Form
    {
        return new Form($this->fields);
    }
}

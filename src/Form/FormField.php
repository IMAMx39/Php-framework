<?php

declare(strict_types=1);

namespace Framework\Form;

/**
 * Représente un champ de formulaire.
 *
 * Options reconnues :
 *   label       string   — libellé affiché (défaut : ucfirst du nom)
 *   required    bool     — rend le champ HTML required (défaut : false)
 *   placeholder string   — attribut placeholder
 *   choices     array    — pour les <select> : ['Libellé' => 'valeur', ...]
 *   rules       string   — règles Validator (ex: 'required|email|max:150')
 *   attr        array    — attributs HTML supplémentaires ['class' => 'form-control']
 *   value       mixed    — valeur par défaut
 */
class FormField
{
    public function __construct(
        public readonly string $name,
        public readonly string $type,
        public readonly array  $options = [],
    ) {}

    public function getLabel(): string
    {
        return $this->options['label'] ?? ucfirst(str_replace('_', ' ', $this->name));
    }

    public function isRequired(): bool
    {
        return (bool) ($this->options['required'] ?? false);
    }

    public function getPlaceholder(): ?string
    {
        return $this->options['placeholder'] ?? null;
    }

    /** @return array<string, string> Libellé → valeur */
    public function getChoices(): array
    {
        return $this->options['choices'] ?? [];
    }

    public function getRules(): ?string
    {
        return $this->options['rules'] ?? null;
    }

    /** @return array<string, string> Attributs HTML supplémentaires */
    public function getAttr(): array
    {
        return $this->options['attr'] ?? [];
    }

    public function getDefaultValue(): mixed
    {
        return $this->options['value'] ?? null;
    }
}

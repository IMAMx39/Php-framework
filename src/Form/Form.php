<?php

declare(strict_types=1);

namespace Framework\Form;

use Framework\Http\Request;
use Framework\Validation\ValidationException;
use Framework\Validation\Validator;

/**
 * Instance de formulaire liée à une requête HTTP.
 *
 * Cycle de vie standard :
 *   $form = $factory->create(new ContactFormType());
 *   $form->handleRequest($request);
 *
 *   if ($form->isSubmitted() && $form->isValid()) {
 *       $data = $form->getData();   // tableau validé
 *       // ... traitement
 *   }
 *
 * Dans un template Twig (avec FormExtension) :
 *   {{ form_start(form, '/contact', 'POST') }}
 *   {{ form_row(form, 'name') }}
 *   {{ form_row(form, 'email') }}
 *   {{ csrf_field() }}
 *   <button type="submit">Envoyer</button>
 *   {{ form_end() }}
 */
class Form
{
    private bool  $submitted = false;
    private array $data      = [];
    /** @var array<string, string[]> */
    private array $errors = [];

    /**
     * @param FormField[] $fields
     */
    public function __construct(private readonly array $fields) {}

    // ------------------------------------------------------------------
    // Liaison avec la requête
    // ------------------------------------------------------------------

    /**
     * Lie les données POST de la requête au formulaire et déclenche la validation.
     */
    public function handleRequest(Request $request): void
    {
        if (!in_array($request->getMethod(), ['POST', 'PUT', 'PATCH'], true)) {
            return;
        }

        $this->submitted = true;
        $this->data      = $request->all();

        $this->runValidation();
    }

    // ------------------------------------------------------------------
    // État
    // ------------------------------------------------------------------

    public function isSubmitted(): bool
    {
        return $this->submitted;
    }

    public function isValid(): bool
    {
        return $this->submitted && empty($this->errors);
    }

    // ------------------------------------------------------------------
    // Données
    // ------------------------------------------------------------------

    /**
     * Retourne les données validées (ou les données brutes si invalide).
     */
    public function getData(): array
    {
        return $this->data;
    }

    /**
     * Retourne la valeur d'un champ soumis, ou la valeur par défaut du champ.
     */
    public function getValue(string $name): mixed
    {
        if ($this->submitted) {
            return $this->data[$name] ?? null;
        }

        return $this->fields[$name]?->getDefaultValue() ?? null;
    }

    // ------------------------------------------------------------------
    // Erreurs
    // ------------------------------------------------------------------

    /**
     * @return array<string, string[]>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * @return string[]
     */
    public function getFieldErrors(string $name): array
    {
        return $this->errors[$name] ?? [];
    }

    public function hasErrors(string $name): bool
    {
        return !empty($this->errors[$name]);
    }

    // ------------------------------------------------------------------
    // Champs
    // ------------------------------------------------------------------

    /** @return FormField[] */
    public function getFields(): array
    {
        return $this->fields;
    }

    public function getField(string $name): ?FormField
    {
        return $this->fields[$name] ?? null;
    }

    // ------------------------------------------------------------------
    // Validation interne
    // ------------------------------------------------------------------

    private function runValidation(): void
    {
        $rules = [];

        foreach ($this->fields as $field) {
            if ($field->getRules() !== null) {
                $rules[$field->name] = $field->getRules();
            }
        }

        if (empty($rules)) {
            return;
        }

        try {
            $this->data = array_merge(
                $this->data,
                Validator::make($this->data, $rules),
            );
        } catch (ValidationException $e) {
            $this->errors = $e->getErrors();
        }
    }
}

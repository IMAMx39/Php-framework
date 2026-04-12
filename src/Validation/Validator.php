<?php

declare(strict_types=1);

namespace Framework\Validation;

/**
 * Validateur de données.
 *
 * Règles disponibles (séparées par |) :
 *   required              — champ obligatoire et non vide
 *   string                — doit être une chaîne
 *   integer               — doit être un entier
 *   numeric               — doit être numérique
 *   boolean               — doit être un booléen
 *   email                 — doit être une adresse email valide
 *   url                   — doit être une URL valide
 *   min:N                 — longueur/valeur minimale
 *   max:N                 — longueur/valeur maximale
 *   between:N,M           — entre N et M (longueur ou valeur)
 *   in:a,b,c              — doit être parmi les valeurs listées
 *   not_in:a,b            — ne doit pas être parmi les valeurs listées
 *   confirmed             — doit correspondre au champ {champ}_confirmation
 *   regex:/pattern/       — doit correspondre à l'expression régulière
 *
 * Exemple :
 *   $data = Validator::make($request->all(), [
 *       'name'                  => 'required|string|min:2|max:100',
 *       'email'                 => 'required|email',
 *       'age'                   => 'required|integer|min:18',
 *       'role'                  => 'required|in:admin,user,moderator',
 *       'password'              => 'required|min:8|confirmed',
 *       'password_confirmation' => 'required',
 *   ]);
 */
class Validator
{
    /** @var array<string, string[]> */
    private array $errors = [];

    private function __construct(
        private readonly array $data,
        private readonly array $rules,
    ) {}

    /**
     * Valide $data selon $rules et retourne les données valides.
     *
     * @param array<string, mixed>  $data
     * @param array<string, string> $rules
     * @return array<string, mixed>
     *
     * @throws ValidationException si la validation échoue.
     */
    public static function make(array $data, array $rules): array
    {
        $instance = new self($data, $rules);

        return $instance->validate();
    }

    // ------------------------------------------------------------------
    // Cœur de la validation
    // ------------------------------------------------------------------

    /**
     * @return array<string, mixed>
     */
    private function validate(): array
    {
        foreach ($this->rules as $field => $ruleString) {
            $value = $this->data[$field] ?? null;
            $rules = array_map('trim', explode('|', $ruleString));

            foreach ($rules as $rule) {
                [$name, $param] = $this->parseRule($rule);

                $this->applyRule($field, $value, $name, $param);
            }
        }

        if (!empty($this->errors)) {
            throw new ValidationException($this->errors);
        }

        // Retourne uniquement les champs déclarés dans les règles
        return array_intersect_key($this->data, $this->rules);
    }

    private function applyRule(string $field, mixed $value, string $rule, ?string $param): void
    {
        // Si le champ est absent et que la règle n'est pas "required", on passe
        if ($rule !== 'required' && ($value === null || $value === '')) {
            return;
        }

        $valid = match ($rule) {
            'required'  => $value !== null && $value !== '' && $value !== [],
            'string'    => is_string($value),
            'integer'   => filter_var($value, FILTER_VALIDATE_INT) !== false,
            'numeric'   => is_numeric($value),
            'boolean'   => in_array($value, [true, false, 0, 1, '0', '1'], true),
            'email'     => filter_var($value, FILTER_VALIDATE_EMAIL) !== false,
            'url'       => filter_var($value, FILTER_VALIDATE_URL) !== false,
            'min'       => $this->validateMin($value, (int) $param),
            'max'       => $this->validateMax($value, (int) $param),
            'between'   => $this->validateBetween($value, $param ?? ''),
            'in'        => in_array((string) $value, explode(',', $param ?? ''), true),
            'not_in'    => !in_array((string) $value, explode(',', $param ?? ''), true),
            'confirmed' => $value === ($this->data["{$field}_confirmation"] ?? null),
            'regex'     => is_string($value) && preg_match($param ?? '//', $value) === 1,
            default     => throw new \InvalidArgumentException("Règle de validation inconnue : « $rule »."),
        };

        if (!$valid) {
            $this->errors[$field][] = $this->message($field, $rule, $param);
        }
    }

    private function validateMin(mixed $value, int $min): bool
    {
        if (is_numeric($value)) {
            return (float) $value >= $min;
        }

        return mb_strlen((string) $value) >= $min;
    }

    private function validateMax(mixed $value, int $max): bool
    {
        if (is_numeric($value)) {
            return (float) $value <= $max;
        }

        return mb_strlen((string) $value) <= $max;
    }

    private function validateBetween(mixed $value, string $param): bool
    {
        [$min, $max] = array_map('intval', explode(',', $param, 2));

        if (is_numeric($value)) {
            return (float) $value >= $min && (float) $value <= $max;
        }

        $len = mb_strlen((string) $value);

        return $len >= $min && $len <= $max;
    }

    // ------------------------------------------------------------------
    // Messages d'erreur
    // ------------------------------------------------------------------

    private function message(string $field, string $rule, ?string $param): string
    {
        $label = ucfirst(str_replace('_', ' ', $field));

        return match ($rule) {
            'required'  => "$label est obligatoire.",
            'string'    => "$label doit être une chaîne de caractères.",
            'integer'   => "$label doit être un nombre entier.",
            'numeric'   => "$label doit être numérique.",
            'boolean'   => "$label doit être un booléen.",
            'email'     => "$label doit être une adresse email valide.",
            'url'       => "$label doit être une URL valide.",
            'min'       => "$label doit contenir au moins $param caractères (ou être ≥ $param).",
            'max'       => "$label ne doit pas dépasser $param caractères (ou être ≤ $param).",
            'between'   => "$label doit être entre " . str_replace(',', ' et ', $param ?? '') . ".",
            'in'        => "$label doit être l'une des valeurs suivantes : $param.",
            'not_in'    => "$label ne peut pas être : $param.",
            'confirmed' => "$label ne correspond pas à la confirmation.",
            'regex'     => "$label a un format invalide.",
            default     => "$label est invalide.",
        };
    }

    // ------------------------------------------------------------------
    // Utilitaires
    // ------------------------------------------------------------------

    /**
     * Parse "min:8" → ['min', '8'] ou "required" → ['required', null].
     *
     * @return array{0: string, 1: string|null}
     */
    private function parseRule(string $rule): array
    {
        if (!str_contains($rule, ':')) {
            return [$rule, null];
        }

        [$name, $param] = explode(':', $rule, 2);

        return [$name, $param];
    }
}

<?php

declare(strict_types=1);

namespace Framework\Template\Extension;

use Framework\Form\Form;
use Framework\Form\FormField;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Extension Twig pour le rendu des formulaires.
 *
 * Fonctions disponibles :
 *   {{ form_start(form, '/action', 'POST') }}  → <form action="..." method="...">
 *   {{ form_end() }}                           → </form>
 *   {{ form_row(form, 'email') }}              → label + widget + erreurs
 *   {{ form_widget(form, 'email') }}           → uniquement le <input>
 *   {{ form_label(form, 'email') }}            → uniquement le <label>
 *   {{ form_errors(form, 'email') }}           → liste des erreurs du champ
 *   {{ form_all_errors(form) }}                → toutes les erreurs du formulaire
 */
class FormExtension extends AbstractExtension
{
    public function getFunctions(): array
    {
        $safe = ['is_safe' => ['html']];

        return [
            new TwigFunction('form_start',      $this->formStart(...),     $safe),
            new TwigFunction('form_end',         $this->formEnd(...),       $safe),
            new TwigFunction('form_row',         $this->formRow(...),       $safe),
            new TwigFunction('form_widget',      $this->formWidget(...),    $safe),
            new TwigFunction('form_label',       $this->formLabel(...),     $safe),
            new TwigFunction('form_errors',      $this->formErrors(...),    $safe),
            new TwigFunction('form_all_errors',  $this->formAllErrors(...), $safe),
        ];
    }

    // ------------------------------------------------------------------
    // Rendu global
    // ------------------------------------------------------------------

    public function formStart(Form $form, string $action = '', string $method = 'POST'): string
    {
        $method = strtoupper($method);
        $a      = htmlspecialchars($action, ENT_QUOTES, 'UTF-8');

        // HTML ne supporte que GET et POST ; PUT/PATCH/DELETE via _method
        if (!in_array($method, ['GET', 'POST'], true)) {
            return sprintf(
                '<form action="%s" method="POST">%s',
                $a,
                sprintf('<input type="hidden" name="_method" value="%s">', $method),
            );
        }

        return sprintf('<form action="%s" method="%s">', $a, $method);
    }

    public function formEnd(): string
    {
        return '</form>';
    }

    // ------------------------------------------------------------------
    // Champ complet (label + widget + erreurs)
    // ------------------------------------------------------------------

    public function formRow(Form $form, string $name): string
    {
        $label   = $this->formLabel($form, $name);
        $widget  = $this->formWidget($form, $name);
        $errors  = $this->formErrors($form, $name);

        return sprintf('<div class="form-group">%s%s%s</div>', $label, $widget, $errors);
    }

    // ------------------------------------------------------------------
    // Composants individuels
    // ------------------------------------------------------------------

    public function formLabel(Form $form, string $name): string
    {
        $field    = $form->getField($name);
        $label    = $field?->getLabel() ?? ucfirst($name);
        $required = $field?->isRequired() ? ' <span class="required">*</span>' : '';

        return sprintf(
            '<label for="%s">%s%s</label>',
            htmlspecialchars($name, ENT_QUOTES, 'UTF-8'),
            htmlspecialchars($label, ENT_QUOTES, 'UTF-8'),
            $required,
        );
    }

    public function formWidget(Form $form, string $name): string
    {
        $field = $form->getField($name);
        $type  = $field?->type ?? 'text';
        $value = $form->getValue($name) ?? $field?->getDefaultValue() ?? '';

        return match ($type) {
            'textarea' => $this->renderTextarea($form, $name, $field, $value),
            'select'   => $this->renderSelect($form, $name, $field, $value),
            'checkbox' => $this->renderCheckbox($form, $name, $field, $value),
            default    => $this->renderInput($type, $name, $field, $value),
        };
    }

    public function formErrors(Form $form, string $name): string
    {
        $errors = $form->getFieldErrors($name);

        if (empty($errors)) {
            return '';
        }

        $items = implode('', array_map(
            fn ($e) => sprintf('<li>%s</li>', htmlspecialchars($e, ENT_QUOTES, 'UTF-8')),
            $errors,
        ));

        return sprintf('<ul class="form-errors">%s</ul>', $items);
    }

    public function formAllErrors(Form $form): string
    {
        $all = [];

        foreach ($form->getErrors() as $errors) {
            array_push($all, ...$errors);
        }

        if (empty($all)) {
            return '';
        }

        $items = implode('', array_map(
            fn ($e) => sprintf('<li>%s</li>', htmlspecialchars($e, ENT_QUOTES, 'UTF-8')),
            $all,
        ));

        return sprintf('<ul class="form-errors form-errors--global">%s</ul>', $items);
    }

    // ------------------------------------------------------------------
    // Widgets internes
    // ------------------------------------------------------------------

    private function renderInput(string $type, string $name, ?FormField $field, mixed $value): string
    {
        $attrs = $this->buildAttrs($name, $field, [
            'type'  => $type === 'password' ? 'password' : $type,
            'name'  => $name,
            'id'    => $name,
            'value' => $type !== 'password' ? (string) $value : '',
        ]);

        return sprintf('<input %s>', $attrs);
    }

    private function renderTextarea(Form $form, string $name, ?FormField $field, mixed $value): string
    {
        $attrs   = $this->buildAttrs($name, $field, ['name' => $name, 'id' => $name]);
        $content = htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');

        return sprintf('<textarea %s>%s</textarea>', $attrs, $content);
    }

    private function renderSelect(Form $form, string $name, ?FormField $field, mixed $value): string
    {
        $attrs   = $this->buildAttrs($name, $field, ['name' => $name, 'id' => $name]);
        $choices = $field?->getChoices() ?? [];
        $options = '';

        foreach ($choices as $label => $val) {
            $selected = (string) $value === (string) $val ? ' selected' : '';
            $options .= sprintf(
                '<option value="%s"%s>%s</option>',
                htmlspecialchars((string) $val, ENT_QUOTES, 'UTF-8'),
                $selected,
                htmlspecialchars((string) $label, ENT_QUOTES, 'UTF-8'),
            );
        }

        return sprintf('<select %s>%s</select>', $attrs, $options);
    }

    private function renderCheckbox(Form $form, string $name, ?FormField $field, mixed $value): string
    {
        $checked = $value ? ' checked' : '';
        $attrs   = $this->buildAttrs($name, $field, [
            'type'  => 'checkbox',
            'name'  => $name,
            'id'    => $name,
            'value' => '1',
        ]);

        return sprintf('<input %s%s>', $attrs, $checked);
    }

    private function buildAttrs(string $name, ?FormField $field, array $base): string
    {
        $extra    = $field?->getAttr() ?? [];
        $required = $field?->isRequired() ? ['required' => true] : [];
        $ph       = $field?->getPlaceholder();
        $phAttr   = $ph !== null ? ['placeholder' => $ph] : [];

        $all = array_merge($base, $phAttr, $extra, $required);

        $parts = [];

        foreach ($all as $attr => $val) {
            if ($val === true) {
                $parts[] = $attr;
            } elseif ($val !== false && $val !== null) {
                $parts[] = sprintf('%s="%s"', $attr, htmlspecialchars((string) $val, ENT_QUOTES, 'UTF-8'));
            }
        }

        return implode(' ', $parts);
    }
}

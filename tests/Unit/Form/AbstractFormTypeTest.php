<?php

declare(strict_types=1);

namespace Tests\Unit\Form;

use Framework\Form\AbstractFormType;
use Framework\Form\FormBuilder;
use Framework\Form\FormFactory;
use Framework\Http\Request;
use PHPUnit\Framework\TestCase;

// ── Fixture ───────────────────────────────────────────────────────────
class RegistrationFormType extends AbstractFormType
{
    public function buildForm(FormBuilder $builder): void
    {
        $builder
            ->add('username', 'text',     ['rules' => 'required|min:3|max:50'])
            ->add('email',    'email',    ['rules' => 'required|email'])
            ->add('password', 'password', ['rules' => 'required|min:8|confirmed'])
            ->add('password_confirmation', 'password', ['rules' => 'required']);
    }
}

class AbstractFormTypeTest extends TestCase
{
    private FormFactory $factory;

    protected function setUp(): void
    {
        $this->factory = new FormFactory();
    }

    public function testFactoryCreatesFormFromType(): void
    {
        $form = $this->factory->create(new RegistrationFormType());

        $this->assertNotNull($form->getField('username'));
        $this->assertNotNull($form->getField('email'));
        $this->assertNotNull($form->getField('password'));
    }

    public function testValidRegistrationData(): void
    {
        $form    = $this->factory->create(new RegistrationFormType());
        $request = new Request([], [
            'username'              => 'johndoe',
            'email'                 => 'john@example.com',
            'password'              => 'supersecret',
            'password_confirmation' => 'supersecret',
        ], ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/'], [], null);

        $form->handleRequest($request);

        $this->assertTrue($form->isSubmitted());
        $this->assertTrue($form->isValid());
    }

    public function testPasswordMismatchIsInvalid(): void
    {
        $form    = $this->factory->create(new RegistrationFormType());
        $request = new Request([], [
            'username'              => 'johndoe',
            'email'                 => 'john@example.com',
            'password'              => 'supersecret',
            'password_confirmation' => 'different',
        ], ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/'], [], null);

        $form->handleRequest($request);

        $this->assertFalse($form->isValid());
        $this->assertTrue($form->hasErrors('password'));
    }

    public function testCreateBuilderReturnsEmptyBuilder(): void
    {
        $builder = $this->factory->createBuilder();

        $this->assertInstanceOf(FormBuilder::class, $builder);
        $this->assertEmpty($builder->getFields());
    }
}

<?php

declare(strict_types=1);

namespace Tests\Unit\Form;

use Framework\Form\FormBuilder;
use Framework\Http\Request;
use PHPUnit\Framework\TestCase;

class FormTest extends TestCase
{
    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    private function makePostRequest(array $body = []): Request
    {
        return new Request([], $body, ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/'], [], null);
    }

    private function makeGetRequest(): Request
    {
        return new Request([], [], ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/'], [], null);
    }

    private function buildLoginForm(): \Framework\Form\Form
    {
        return (new FormBuilder())
            ->add('email',    'email',    ['rules' => 'required|email'])
            ->add('password', 'password', ['rules' => 'required|min:6'])
            ->build();
    }

    // ------------------------------------------------------------------
    // État initial
    // ------------------------------------------------------------------

    public function testFormIsNotSubmittedInitially(): void
    {
        $form = $this->buildLoginForm();

        $this->assertFalse($form->isSubmitted());
        $this->assertFalse($form->isValid());
    }

    // ------------------------------------------------------------------
    // handleRequest — méthodes
    // ------------------------------------------------------------------

    public function testGetRequestDoesNotSubmitForm(): void
    {
        $form = $this->buildLoginForm();
        $form->handleRequest($this->makeGetRequest());

        $this->assertFalse($form->isSubmitted());
    }

    public function testPostRequestSubmitsForm(): void
    {
        $form = $this->buildLoginForm();
        $form->handleRequest($this->makePostRequest(['email' => 'a@b.com', 'password' => 'secret']));

        $this->assertTrue($form->isSubmitted());
    }

    public function testPutRequestSubmitsForm(): void
    {
        $form    = $this->buildLoginForm();
        $request = new Request([], ['email' => 'a@b.com', 'password' => 'secret'], ['REQUEST_METHOD' => 'PUT', 'REQUEST_URI' => '/'], [], null);

        $form->handleRequest($request);

        $this->assertTrue($form->isSubmitted());
    }

    // ------------------------------------------------------------------
    // Validation
    // ------------------------------------------------------------------

    public function testValidDataMakesFormValid(): void
    {
        $form = $this->buildLoginForm();
        $form->handleRequest($this->makePostRequest([
            'email'    => 'user@example.com',
            'password' => 'secret123',
        ]));

        $this->assertTrue($form->isValid());
        $this->assertEmpty($form->getErrors());
    }

    public function testInvalidEmailMakesFormInvalid(): void
    {
        $form = $this->buildLoginForm();
        $form->handleRequest($this->makePostRequest([
            'email'    => 'not-an-email',
            'password' => 'secret123',
        ]));

        $this->assertFalse($form->isValid());
        $this->assertArrayHasKey('email', $form->getErrors());
    }

    public function testPasswordTooShortMakesFormInvalid(): void
    {
        $form = $this->buildLoginForm();
        $form->handleRequest($this->makePostRequest([
            'email'    => 'user@example.com',
            'password' => 'abc',   // 3 chars, non-numeric → échoue min:6
        ]));

        $this->assertFalse($form->isValid());
        $this->assertArrayHasKey('password', $form->getErrors());
    }

    public function testMultipleErrorsAccumulated(): void
    {
        $form = $this->buildLoginForm();
        $form->handleRequest($this->makePostRequest([]));  // tout vide

        $errors = $form->getErrors();
        $this->assertArrayHasKey('email', $errors);
        $this->assertArrayHasKey('password', $errors);
    }

    // ------------------------------------------------------------------
    // Accès aux erreurs par champ
    // ------------------------------------------------------------------

    public function testGetFieldErrors(): void
    {
        $form = $this->buildLoginForm();
        $form->handleRequest($this->makePostRequest(['email' => 'bad', 'password' => 'ok1234']));

        $this->assertNotEmpty($form->getFieldErrors('email'));
        $this->assertEmpty($form->getFieldErrors('password'));
    }

    public function testHasErrors(): void
    {
        $form = $this->buildLoginForm();
        $form->handleRequest($this->makePostRequest(['email' => 'bad', 'password' => 'ok1234']));

        $this->assertTrue($form->hasErrors('email'));
        $this->assertFalse($form->hasErrors('password'));
    }

    // ------------------------------------------------------------------
    // getData / getValue
    // ------------------------------------------------------------------

    public function testGetDataReturnsSubmittedData(): void
    {
        $form = $this->buildLoginForm();
        $form->handleRequest($this->makePostRequest([
            'email'    => 'user@example.com',
            'password' => 'secret123',
        ]));

        $data = $form->getData();
        $this->assertSame('user@example.com', $data['email']);
    }

    public function testGetValueReturnsDefaultBeforeSubmission(): void
    {
        $form = (new FormBuilder())
            ->add('name', 'text', ['value' => 'défaut'])
            ->build();

        $this->assertSame('défaut', $form->getValue('name'));
    }

    public function testGetValueReturnsSubmittedValueAfterHandleRequest(): void
    {
        $form = $this->buildLoginForm();
        $form->handleRequest($this->makePostRequest([
            'email'    => 'user@example.com',
            'password' => 'secret123',
        ]));

        $this->assertSame('user@example.com', $form->getValue('email'));
    }

    // ------------------------------------------------------------------
    // Form sans règles de validation
    // ------------------------------------------------------------------

    public function testFormWithoutRulesIsAlwaysValidWhenSubmitted(): void
    {
        $form = (new FormBuilder())
            ->add('search', 'text')
            ->build();

        $form->handleRequest($this->makePostRequest(['search' => 'anything']));

        $this->assertTrue($form->isSubmitted());
        $this->assertTrue($form->isValid());
    }
}

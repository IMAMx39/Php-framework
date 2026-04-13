<?php

declare(strict_types=1);

namespace Tests\Unit\Validation;

use Framework\Validation\ValidationException;
use Framework\Validation\Validator;
use PHPUnit\Framework\TestCase;

class ValidatorTest extends TestCase
{
    // ------------------------------------------------------------------
    // Passage réussi
    // ------------------------------------------------------------------

    public function testValidDataPassesAndReturnsOnlyDeclaredFields(): void
    {
        $data = ['name' => 'Alice', 'email' => 'alice@example.com', 'extra' => 'ignored'];

        $result = Validator::make($data, ['name' => 'required|string', 'email' => 'required|email']);

        $this->assertSame(['name' => 'Alice', 'email' => 'alice@example.com'], $result);
        $this->assertArrayNotHasKey('extra', $result);
    }

    // ------------------------------------------------------------------
    // required
    // ------------------------------------------------------------------

    public function testRequiredFailsOnMissingField(): void
    {
        $this->expectValidationError('name', fn () =>
            Validator::make([], ['name' => 'required'])
        );
    }

    public function testRequiredFailsOnEmptyString(): void
    {
        $this->expectValidationError('name', fn () =>
            Validator::make(['name' => ''], ['name' => 'required'])
        );
    }

    public function testRequiredPassesOnZero(): void
    {
        $result = Validator::make(['qty' => 0], ['qty' => 'required']);

        $this->assertSame(['qty' => 0], $result);
    }

    // ------------------------------------------------------------------
    // string / integer / numeric / boolean
    // ------------------------------------------------------------------

    public function testStringRuleFailsForInteger(): void
    {
        $this->expectValidationError('name', fn () =>
            Validator::make(['name' => 123], ['name' => 'string'])
        );
    }

    public function testIntegerRulePassesForStringNumber(): void
    {
        $result = Validator::make(['age' => '42'], ['age' => 'integer']);

        $this->assertSame(['age' => '42'], $result);
    }

    public function testIntegerRuleFailsForFloat(): void
    {
        $this->expectValidationError('age', fn () =>
            Validator::make(['age' => '3.14'], ['age' => 'integer'])
        );
    }

    public function testNumericPassesForFloat(): void
    {
        $result = Validator::make(['price' => '9.99'], ['price' => 'numeric']);

        $this->assertSame(['price' => '9.99'], $result);
    }

    public function testBooleanPassesForTrueAndFalse(): void
    {
        Validator::make(['flag' => true],  ['flag' => 'boolean']);
        Validator::make(['flag' => false], ['flag' => 'boolean']);
        Validator::make(['flag' => 1],     ['flag' => 'boolean']);
        Validator::make(['flag' => '0'],   ['flag' => 'boolean']);

        $this->addToAssertionCount(4);
    }

    // ------------------------------------------------------------------
    // email / url
    // ------------------------------------------------------------------

    public function testEmailPassesForValidAddress(): void
    {
        $result = Validator::make(['email' => 'user@example.com'], ['email' => 'email']);

        $this->assertSame('user@example.com', $result['email']);
    }

    public function testEmailFailsForInvalidAddress(): void
    {
        $this->expectValidationError('email', fn () =>
            Validator::make(['email' => 'not-an-email'], ['email' => 'email'])
        );
    }

    public function testUrlPassesForValidUrl(): void
    {
        Validator::make(['url' => 'https://example.com'], ['url' => 'url']);

        $this->addToAssertionCount(1);
    }

    public function testUrlFailsForInvalidUrl(): void
    {
        $this->expectValidationError('url', fn () =>
            Validator::make(['url' => 'not a url'], ['url' => 'url'])
        );
    }

    // ------------------------------------------------------------------
    // min / max / between
    // ------------------------------------------------------------------

    public function testMinFailsWhenStringTooShort(): void
    {
        $this->expectValidationError('name', fn () =>
            Validator::make(['name' => 'ab'], ['name' => 'min:3'])
        );
    }

    public function testMinPassesWhenStringLongEnough(): void
    {
        Validator::make(['name' => 'abc'], ['name' => 'min:3']);

        $this->addToAssertionCount(1);
    }

    public function testMinWorksForNumericValue(): void
    {
        $this->expectValidationError('age', fn () =>
            Validator::make(['age' => '16'], ['age' => 'min:18'])
        );
    }

    public function testMaxFailsWhenStringTooLong(): void
    {
        $this->expectValidationError('name', fn () =>
            Validator::make(['name' => 'toolongname'], ['name' => 'max:5'])
        );
    }

    public function testBetweenPassesWithinRange(): void
    {
        Validator::make(['age' => '25'], ['age' => 'between:18,65']);

        $this->addToAssertionCount(1);
    }

    public function testBetweenFailsOutsideRange(): void
    {
        $this->expectValidationError('age', fn () =>
            Validator::make(['age' => '10'], ['age' => 'between:18,65'])
        );
    }

    // ------------------------------------------------------------------
    // in / not_in
    // ------------------------------------------------------------------

    public function testInPassesForAllowedValue(): void
    {
        Validator::make(['role' => 'admin'], ['role' => 'in:admin,user,moderator']);

        $this->addToAssertionCount(1);
    }

    public function testInFailsForDisallowedValue(): void
    {
        $this->expectValidationError('role', fn () =>
            Validator::make(['role' => 'superadmin'], ['role' => 'in:admin,user'])
        );
    }

    public function testNotInFailsForForbiddenValue(): void
    {
        $this->expectValidationError('status', fn () =>
            Validator::make(['status' => 'banned'], ['status' => 'not_in:banned,deleted'])
        );
    }

    // ------------------------------------------------------------------
    // confirmed
    // ------------------------------------------------------------------

    public function testConfirmedPassesWhenFieldsMatch(): void
    {
        Validator::make(
            ['password' => 'secret', 'password_confirmation' => 'secret'],
            ['password' => 'confirmed', 'password_confirmation' => 'required'],
        );

        $this->addToAssertionCount(1);
    }

    public function testConfirmedFailsWhenFieldsDiffer(): void
    {
        $this->expectValidationError('password', fn () =>
            Validator::make(
                ['password' => 'secret', 'password_confirmation' => 'different'],
                ['password' => 'confirmed', 'password_confirmation' => 'required'],
            )
        );
    }

    // ------------------------------------------------------------------
    // regex
    // ------------------------------------------------------------------

    public function testRegexPassesForMatchingValue(): void
    {
        Validator::make(['code' => 'ABC123'], ['code' => 'regex:/^[A-Z]{3}\d{3}$/']);

        $this->addToAssertionCount(1);
    }

    public function testRegexFailsForNonMatchingValue(): void
    {
        $this->expectValidationError('code', fn () =>
            Validator::make(['code' => 'abc123'], ['code' => 'regex:/^[A-Z]{3}\d{3}$/'])
        );
    }

    // ------------------------------------------------------------------
    // Règle inconnue
    // ------------------------------------------------------------------

    public function testUnknownRuleThrowsInvalidArgument(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        Validator::make(['x' => 'val'], ['x' => 'unknown_rule']);
    }

    // ------------------------------------------------------------------
    // Champs optionnels
    // ------------------------------------------------------------------

    public function testOptionalFieldSkipsRulesWhenAbsent(): void
    {
        // "email" n'est pas dans $data — sans "required" il doit passer
        $result = Validator::make([], ['email' => 'email']);

        $this->assertSame([], $result);
    }

    // ------------------------------------------------------------------
    // Plusieurs erreurs sur un même champ
    // ------------------------------------------------------------------

    public function testMultipleRulesAccumulateErrors(): void
    {
        try {
            Validator::make(['age' => 'notanumber'], ['age' => 'integer|min:18']);
            $this->fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('age', $e->getErrors());
        }
    }

    // ------------------------------------------------------------------
    // Helper
    // ------------------------------------------------------------------

    private function expectValidationError(string $field, callable $callback): void
    {
        try {
            $callback();
            $this->fail("Expected ValidationException for field '$field'.");
        } catch (ValidationException $e) {
            $this->assertArrayHasKey(
                $field,
                $e->getErrors(),
                "Expected error on '$field'. Got errors: " . implode(', ', array_keys($e->getErrors())),
            );
        }
    }
}

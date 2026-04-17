<?php

declare(strict_types=1);

namespace Tests\Unit\Form;

use Framework\Form\FormBuilder;
use Framework\Form\FormField;
use PHPUnit\Framework\TestCase;

class FormBuilderTest extends TestCase
{
    public function testAddField(): void
    {
        $builder = new FormBuilder();
        $builder->add('name', 'text', ['label' => 'Nom']);

        $this->assertTrue($builder->has('name'));
        $this->assertArrayHasKey('name', $builder->getFields());
    }

    public function testAddDefaultTypeIsText(): void
    {
        $builder = new FormBuilder();
        $builder->add('username');

        $field = $builder->getFields()['username'];
        $this->assertSame('text', $field->type);
    }

    public function testAddReturnsStaticForChaining(): void
    {
        $builder = new FormBuilder();

        $result = $builder->add('a')->add('b')->add('c');

        $this->assertSame($builder, $result);
        $this->assertCount(3, $builder->getFields());
    }

    public function testRemoveField(): void
    {
        $builder = new FormBuilder();
        $builder->add('keep')->add('remove');
        $builder->remove('remove');

        $this->assertTrue($builder->has('keep'));
        $this->assertFalse($builder->has('remove'));
    }

    public function testBuildReturnsForm(): void
    {
        $builder = new FormBuilder();
        $builder->add('email', 'email');

        $form = $builder->build();

        $this->assertNotNull($form->getField('email'));
    }
}

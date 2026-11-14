<?php

namespace Jav\ApiTopiaBundle\Tests\Unit;

use Jav\ApiTopiaBundle\GraphQL\ResourceLoader;
use Jav\ApiTopiaBundle\Serializer\PropertyTypeDescriber;
use Jav\ApiTopiaBundle\Serializer\Serializer;
use Jav\ApiTopiaBundle\Tests\GraphQL\Schema\Test1\DTO\Address;
use Jav\ApiTopiaBundle\Tests\GraphQL\Schema\Test1\DTO\Enums\AddressType;
use Jav\ApiTopiaBundle\Tests\Unit\Fixtures\TypedDto;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\PropertyInfo\Type as LegacyType;
use Symfony\Component\TypeInfo\Type;
use Symfony\Component\TypeInfo\Type\NullableType;

/**
 * Runs against whatever Symfony version is installed: legacy PropertyInfo types up to 7.x, TypeInfo from 7.2.
 */
class PropertyTypeDescriberTest extends TestCase
{
    /**
     * @return iterable<string, array{string, array{className: ?string, builtin: ?string, isCollection: bool, nullable: bool}}>
     */
    public static function properties(): iterable
    {
        yield 'int' => ['id', ['className' => null, 'builtin' => 'int', 'isCollection' => false, 'nullable' => false]];
        yield 'nullable string' => ['name', ['className' => null, 'builtin' => 'string', 'isCollection' => false, 'nullable' => true]];
        yield 'bool' => ['flag', ['className' => null, 'builtin' => 'bool', 'isCollection' => false, 'nullable' => false]];
        yield 'nullable object' => ['address', ['className' => Address::class, 'builtin' => 'object', 'isCollection' => false, 'nullable' => true]];
        yield 'list of objects' => ['addresses', ['className' => Address::class, 'builtin' => 'object', 'isCollection' => true, 'nullable' => false]];
        yield 'nullable list of int' => ['numbers', ['className' => null, 'builtin' => 'int', 'isCollection' => true, 'nullable' => true]];
        yield 'enum' => ['type', ['className' => AddressType::class, 'builtin' => 'object', 'isCollection' => false, 'nullable' => false]];
        yield 'attribute output' => ['withOutput', ['className' => Address::class, 'builtin' => 'object', 'isCollection' => false, 'nullable' => true]];
        yield 'attribute collection output' => ['collectionWithOutput', ['className' => Address::class, 'builtin' => 'object', 'isCollection' => true, 'nullable' => true]];
    }

    /**
     * @param array{className: ?string, builtin: ?string, isCollection: bool, nullable: bool} $expected
     */
    #[DataProvider('properties')]
    public function testDescribeWithInstalledSymfony(string $property, array $expected): void
    {
        $extractor = (new Serializer(new ResourceLoader(sys_get_temp_dir())))->getPropertyInfoExtractor();

        $this->assertSame($expected, PropertyTypeDescriber::describe($extractor, TypedDto::class, $property));
    }

    public function testUntypedArrayIsACollectionWithoutValueType(): void
    {
        $extractor = (new Serializer(new ResourceLoader(sys_get_temp_dir())))->getPropertyInfoExtractor();
        $description = PropertyTypeDescriber::describe($extractor, TypedDto::class, 'untyped');

        $this->assertTrue($description['isCollection']);
        $this->assertNull($description['className']);
    }

    public function testUnionPicksFirstMember(): void
    {
        $extractor = (new Serializer(new ResourceLoader(sys_get_temp_dir())))->getPropertyInfoExtractor();
        $description = PropertyTypeDescriber::describe($extractor, TypedDto::class, 'union');

        $this->assertFalse($description['isCollection']);
        $this->assertContains($description['builtin'], ['int', 'string']);
    }

    public function testPropertyWithoutTypeInformation(): void
    {
        $extractor = (new Serializer(new ResourceLoader(sys_get_temp_dir())))->getPropertyInfoExtractor();

        $this->assertNull(PropertyTypeDescriber::describe($extractor, TypedDto::class, 'withoutOutput'));
    }

    public function testDescribeTypeInfo(): void
    {
        if (!class_exists(NullableType::class)) {
            $this->markTestSkipped('symfony/type-info >= 7.2 is not installed.');
        }

        $this->assertSame(
            ['className' => null, 'builtin' => 'bool', 'isCollection' => false, 'nullable' => false],
            PropertyTypeDescriber::describeTypeInfo(Type::false())
        );
        $this->assertSame(
            ['className' => Address::class, 'builtin' => 'object', 'isCollection' => true, 'nullable' => true],
            PropertyTypeDescriber::describeTypeInfo(Type::nullable(Type::list(Type::object(Address::class))))
        );
        $this->assertSame(
            ['className' => null, 'builtin' => null, 'isCollection' => true, 'nullable' => false],
            PropertyTypeDescriber::describeTypeInfo(Type::list())
        );
        $this->assertSame(
            ['className' => null, 'builtin' => null, 'isCollection' => true, 'nullable' => false],
            PropertyTypeDescriber::describeTypeInfo(Type::iterable())
        );
        $this->assertSame(
            ['className' => \ArrayObject::class, 'builtin' => 'object', 'isCollection' => false, 'nullable' => false],
            PropertyTypeDescriber::describeTypeInfo(Type::generic(Type::object(\ArrayObject::class), Type::int()))
        );
    }

    public function testDescribeLegacy(): void
    {
        if (!class_exists(LegacyType::class)) {
            $this->markTestSkipped('Legacy PropertyInfo types were removed in Symfony 8.');
        }

        $this->assertSame(
            ['className' => Address::class, 'builtin' => 'object', 'isCollection' => true, 'nullable' => false],
            PropertyTypeDescriber::describeLegacy(new LegacyType('array', false, null, true, new LegacyType('int'), new LegacyType('object', false, Address::class)))
        );
        $this->assertSame(
            ['className' => null, 'builtin' => null, 'isCollection' => true, 'nullable' => true],
            PropertyTypeDescriber::describeLegacy(new LegacyType('array', true, null, true))
        );
    }
}

<?php

namespace Jav\ApiTopiaBundle\Tests\Unit;

use Jav\ApiTopiaBundle\GraphQL\ResourceLoader;
use Jav\ApiTopiaBundle\Serializer\AttributeTypeExtractor;
use Jav\ApiTopiaBundle\Tests\GraphQL\Schema\Test1\DTO\Address;
use Jav\ApiTopiaBundle\Tests\Unit\Fixtures\TypedDto;
use PHPUnit\Framework\TestCase;
use Symfony\Component\PropertyInfo\Type as LegacyType;
use Symfony\Component\TypeInfo\Type;

class AttributeTypeExtractorTest extends TestCase
{
    private AttributeTypeExtractor $extractor;

    protected function setUp(): void
    {
        $this->extractor = new AttributeTypeExtractor((new ResourceLoader(sys_get_temp_dir()))->getClassMetatadaFactory());
    }

    public function testTypeInfo(): void
    {
        if (!class_exists(Type::class)) {
            $this->markTestSkipped('symfony/type-info is not installed.');
        }

        $this->assertEquals(Type::nullable(Type::object(Address::class)), $this->extractor->getType(TypedDto::class, 'withOutput'));
        $this->assertEquals(Type::nullable(Type::list(Type::object(Address::class))), $this->extractor->getType(TypedDto::class, 'collectionWithOutput'));
        $this->assertNull($this->extractor->getType(TypedDto::class, 'withoutOutput'));
        $this->assertNull($this->extractor->getType(TypedDto::class, 'id'));
        $this->assertNull($this->extractor->getType(TypedDto::class, 'doesNotExist'));
    }

    public function testLegacyTypes(): void
    {
        if (!class_exists(LegacyType::class)) {
            $this->markTestSkipped('Legacy PropertyInfo types were removed in Symfony 8.');
        }

        $item = $this->extractor->getTypes(TypedDto::class, 'withOutput')[0];
        $this->assertSame(Address::class, $item->getClassName());
        $this->assertFalse($item->isCollection());

        $collection = $this->extractor->getTypes(TypedDto::class, 'collectionWithOutput')[0];
        $this->assertTrue($collection->isCollection());
        $this->assertSame(Address::class, $collection->getCollectionValueTypes()[0]->getClassName());

        $this->assertNull($this->extractor->getTypes(TypedDto::class, 'withoutOutput'));
    }

    public function testDescriptions(): void
    {
        $this->assertNull($this->extractor->getShortDescription(TypedDto::class, 'withOutput'));
        $this->assertNull($this->extractor->getLongDescription(TypedDto::class, 'doesNotExist'));
    }
}

<?php

namespace Jav\ApiTopiaBundle\Tests\Unit;

use Jav\ApiTopiaBundle\GraphQL\ResourceLoader;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ResourceLoaderTest extends TestCase
{
    /**
     * @return iterable<string, array{string, array{string, string}|null}>
     */
    public static function declarations(): iterable
    {
        yield 'class' => ["<?php\nnamespace App\\Dto;\n\nfinal class User {}", ['App\\Dto\\User', 'class']];
        yield 'readonly abstract class' => ["<?php\nnamespace App;\nabstract readonly class Base {}", ['App\\Base', 'class']];
        yield 'enum' => ["<?php\nnamespace App;\nenum Status: string { case A = 'a'; }", ['App\\Status', 'enum']];
        yield 'no namespace' => ["<?php\nclass Foo {}", ['Foo', 'class']];
        yield 'interface referencing ::class' => ["<?php\nnamespace App;\n// class Nope\ninterface I { const X = Foo::class; }", null];
        yield 'trait' => ["<?php\nnamespace App;\ntrait T {}", null];
        yield 'anonymous class only' => ["<?php\nnamespace App;\nreturn new class {};", null];
        yield 'class word in a docblock' => ["<?php\nnamespace App;\n/** This class does things */\nclass Real {}", ['App\\Real', 'class']];
    }

    /**
     * @param array{string, string}|null $expected
     */
    #[DataProvider('declarations')]
    public function testFindDeclaration(string $code, ?array $expected): void
    {
        $this->assertSame($expected, ResourceLoader::findDeclaration($code));
    }

    public function testDuplicateShortNamesAreRejected(): void
    {
        $dir = sys_get_temp_dir() . '/apitopia-dup-' . bin2hex(random_bytes(4));
        mkdir("$dir/A", 0o755, true);
        mkdir("$dir/B", 0o755, true);
        $suffix = bin2hex(random_bytes(4));
        file_put_contents("$dir/A/Thing.php", "<?php\nnamespace DupA$suffix;\nclass Thing {}");
        file_put_contents("$dir/B/Thing.php", "<?php\nnamespace DupB$suffix;\nclass Thing {}");
        require "$dir/A/Thing.php";
        require "$dir/B/Thing.php";

        $loader = new ResourceLoader("$dir/cache");

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('would both produce the GraphQL type "Thing"');
        $loader->loadResources('dup', ["$dir/A", "$dir/B"]);
    }
}

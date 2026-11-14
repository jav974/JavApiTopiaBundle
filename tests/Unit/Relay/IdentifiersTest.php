<?php

namespace Jav\ApiTopiaBundle\Tests\Unit\Relay;

use Jav\ApiTopiaBundle\GraphQL\Relay\ArrayCursor;
use Jav\ApiTopiaBundle\GraphQL\Relay\GlobalId;
use Jav\ApiTopiaBundle\GraphQL\Relay\Relay;
use PHPUnit\Framework\TestCase;

/**
 * Formats must stay identical to graphql-relay-js / ivome/graphql-relay-php: identifiers and cursors already handed to clients must keep working.
 */
class IdentifiersTest extends TestCase
{
    public function testGlobalIdFormatIsStable(): void
    {
        $this->assertSame('dGVzdDEuVXNlcjo0Mg==', GlobalId::encode('test1.User', 42));
        $this->assertSame('dGVzdDEuVXNlcjo0Mg==', Relay::toGlobalId('test1.User', '42'));
        $this->assertSame(['type' => 'test1.User', 'id' => '42'], GlobalId::decode('dGVzdDEuVXNlcjo0Mg=='));
    }

    public function testIdsMayContainTheDelimiter(): void
    {
        $this->assertSame(['type' => 'T', 'id' => 'a:b:c'], Relay::fromGlobalId(GlobalId::encode('T', 'a:b:c')));
    }

    public function testInvalidGlobalIds(): void
    {
        $this->assertNull(GlobalId::decode('not base64!'));
        $this->assertNull(GlobalId::decode(base64_encode('no delimiter')));
        $this->assertNull(GlobalId::decode(base64_encode(':missing type')));
    }

    public function testCursorFormatIsStable(): void
    {
        $this->assertSame('YXJyYXljb25uZWN0aW9uOjU=', ArrayCursor::fromOffset(5));
        $this->assertSame('YXJyYXljb25uZWN0aW9uOjU=', Relay::offsetToCursor(5));
        $this->assertSame(5, ArrayCursor::toOffset('YXJyYXljb25uZWN0aW9uOjU='));
        $this->assertSame(0, Relay::cursorToOffset(ArrayCursor::fromOffset(0)));
    }

    public function testInvalidCursors(): void
    {
        $this->assertNull(ArrayCursor::toOffset(null));
        $this->assertNull(ArrayCursor::toOffset(''));
        $this->assertNull(ArrayCursor::toOffset('not base64!'));
        $this->assertNull(ArrayCursor::toOffset(base64_encode('other:5')));
        $this->assertNull(ArrayCursor::toOffset(base64_encode('arrayconnection:-1')));
        $this->assertNull(ArrayCursor::toOffset(base64_encode('arrayconnection:abc')));
    }
}

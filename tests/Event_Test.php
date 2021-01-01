<?php declare(strict_types=1);
/**
 * FluentCache
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */
namespace modethirteen\FluentCache\Tests;

use modethirteen\FluentCache\Event;
use Psr\SimpleCache\CacheInterface;

class Event_Test extends TestCase {

    /**
     * @test
     */
    public function Can_construct_an_event() : void {

        // act
        $event = new Event('foo', 'bar');

        // assert
        static::assertEquals('foo', $event->getMessage());
        static::assertNull($event->getCacheKey());
        static::assertNull($event->getCacheType());
        static::assertNull($event->getCacheException());
        static::assertEquals('bar', $event->getSessionId());
        static::assertFalse($event->isPropagationStopped());
    }

    /**
     * @test
     */
    public function Can_construct_an_event_with_optional_data() : void {

        // arrange
        $cache = $this->newMock(CacheInterface::class);

        // act
        $e = new CacheException();
        $event = (new Event('foo', 'plugh'))

            /** @var CacheInterface $cache */
            ->withCache($cache, 'qux')
            ->withCacheException($e);

        // assert
        static::assertEquals('foo', $event->getMessage());
        static::assertEquals('qux', $event->getCacheKey());
        static::assertStringStartsWith('Mock_CacheInterface_', $event->getCacheType());
        static::assertSame($e, $event->getCacheException());
        static::assertEquals('plugh', $event->getSessionId());
        static::assertFalse($event->isPropagationStopped());
    }

    /**
     * @test
     */
    public function Can_stop_propagation() : void {

        // arrange
        $event = (new Event('foo', 'qux'));

        // act
        $event->stopPropagation();;

        // assert
        static::assertTrue($event->isPropagationStopped());
    }
}

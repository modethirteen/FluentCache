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
use PHPUnit\Framework\TestCase;

class Event_Test extends TestCase {

    /**
     * @test
     */
    public function Can_construct_an_event() : void {

        // act
        $event = new Event('foo');

        // assert
        static::assertEquals('foo', $event->getName());
        static::assertEquals([], $event->getData());
        static::assertFalse($event->isPropagationStopped());
    }

    /**
     * @test
     */
    public function Can_construct_an_event_with_error_without_stopped_propagation() : void {

        // act
        $e = new CacheException();
        $event = (new Event('foo'))
            ->withException($e);

        // assert
        static::assertEquals('foo', $event->getName());
        static::assertEquals(['exception' => $e], $event->getData());
        static::assertFalse($event->isPropagationStopped());
    }

    /**
     * @test
     */
    public function Can_construct_an_event_with_error_with_stopped_propagation() : void {

        // act
        $e = new CacheException();
        $event = (new Event('foo'))
            ->withException($e, true);

        // assert
        static::assertEquals('foo', $event->getName());
        static::assertEquals(['exception' => $e], $event->getData());
        static::assertTrue($event->isPropagationStopped());
    }
}

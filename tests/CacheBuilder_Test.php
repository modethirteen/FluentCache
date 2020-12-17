<?php
/** @noinspection DuplicatedCode */
declare(strict_types=1);
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

use modethirteen\FluentCache\CacheBuilder;
use modethirteen\FluentCache\Event;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\SimpleCache\CacheInterface;
use Psr\SimpleCache\InvalidArgumentException;

class CacheBuilder_Test extends TestCase {

    /**
     * @test
     */
    public function Build() : void {

        // arrange
        $dispatcher = $this->newMock(EventDispatcherInterface::class);
        $dispatcher->expects($this->exactly(3))
            ->method('dispatch')
            ->withConsecutive(
                [static::equalTo(new Event('build:start'))],
                [static::equalTo(new Event('build:stop'))],
                [static::equalTo(new Event('build:validation.pass'))]
            );

        // act
        $result = (new CacheBuilder())
            ->withBuilder(function() : string {
                return 'qux';
            })

            /** @var EventDispatcherInterface $dispatcher */
            ->withEventDispatcher($dispatcher)
            ->get();

        // assert
        static::assertEquals('qux', $result);
    }

    /**
     * @test
     */
    public function Build_failed() : void {

        // arrange
        $dispatcher = $this->newMock(EventDispatcherInterface::class);
        $dispatcher->expects($this->exactly(3))
            ->method('dispatch')
            ->withConsecutive(
                [static::equalTo(new Event('build:start'))],
                [static::equalTo(new Event('build:stop'))],
                [static::equalTo(new Event('build:validation.fail'))]
            );

        // act
        $result = (new CacheBuilder())
            ->withBuilder(function() : string {
                return 'qux';
            })
            ->withBuildValidator(function($result) : bool {

                // assert
                static::assertEquals('qux', $result);
                return false;
            })

            /** @var EventDispatcherInterface $dispatcher */
            ->withEventDispatcher($dispatcher)
            ->get();

        // assert
        static::assertEquals('qux', $result);
    }

    /**
     * @test
     */
    public function Cache_hit() : void {

        // arrange
        $dispatcher = $this->newMock(EventDispatcherInterface::class);
        $dispatcher->expects($this->exactly(2))
            ->method('dispatch')
            ->withConsecutive(
                [static::equalTo(new Event('cache:get:start'))],
                [static::equalTo(new Event('cache:get:stop.hit'))]
            );
        $cache = $this->newMock(CacheInterface::class);

        /** @noinspection PhpParamsInspection */
        $cache->expects($this->once())
            ->method('get')
            ->with(static::equalTo('foo'))
            ->willReturn('bar');

        // act
        $result = (new CacheBuilder())

            /** @var CacheInterface $cache */
            ->withCache($cache, function() : string {
                return 'foo';
            })

            /** @var EventDispatcherInterface $dispatcher */
            ->withEventDispatcher($dispatcher)
            ->get();

        // assert
        static::assertEquals('bar', $result);
    }

    /**
     * @test
     */
    public function Cache_miss_with_build() : void {

        // arrange
        $dispatcher = $this->newMock(EventDispatcherInterface::class);
        $dispatcher->expects($this->exactly(7))
            ->method('dispatch')
            ->withConsecutive(
                [static::equalTo(new Event('cache:get:start'))],
                [static::equalTo(new Event('cache:get:stop.miss'))],
                [static::equalTo(new Event('build:start'))],
                [static::equalTo(new Event('build:stop'))],
                [static::equalTo(new Event('build:validation.pass'))],
                [static::equalTo(new Event('cache:set:start'))],
                [static::equalTo(new Event('cache:set:stop'))]
            );
        $cache = $this->newMock(CacheInterface::class);

        /** @noinspection PhpParamsInspection */
        $cache->expects($this->once())
            ->method('get')
            ->with(static::equalTo('foo'))
            ->willReturn(null);

        /** @noinspection PhpParamsInspection */
        $cache->expects($this->once())
            ->method('set')
            ->with(static::equalTo('foo'), static::equalTo('qux'), static::equalTo(0));

        // act
        $result = (new CacheBuilder())
            ->withBuilder(function() : string {
                return 'qux';
            })

            /** @var CacheInterface $cache */
            ->withCache($cache, function() : string {
                return 'foo';
            })

            /** @var EventDispatcherInterface $dispatcher */
            ->withEventDispatcher($dispatcher)
            ->get();

        // assert
        static::assertEquals('qux', $result);
    }

    /**
     * @test
     */
    public function Cache_miss_with_build_and_ttl() : void {

        // arrange
        $dispatcher = $this->newMock(EventDispatcherInterface::class);
        $dispatcher->expects($this->exactly(7))
            ->method('dispatch')
            ->withConsecutive(
                [static::equalTo(new Event('cache:get:start'))],
                [static::equalTo(new Event('cache:get:stop.miss'))],
                [static::equalTo(new Event('build:start'))],
                [static::equalTo(new Event('build:stop'))],
                [static::equalTo(new Event('build:validation.pass'))],
                [static::equalTo(new Event('cache:set:start'))],
                [static::equalTo(new Event('cache:set:stop'))]
            );
        $cache = $this->newMock(CacheInterface::class);

        /** @noinspection PhpParamsInspection */
        $cache->expects($this->once())
            ->method('get')
            ->with(static::equalTo('foo'))
            ->willReturn(null);

        /** @noinspection PhpParamsInspection */
        $cache->expects($this->once())
            ->method('set')
            ->with(static::equalTo('foo'), static::equalTo('qux'), static::equalTo(1500));

        // act
        $result = (new CacheBuilder())
            ->withBuilder(function() : string {
                return 'qux';
            })

            /** @var CacheInterface $cache */
            ->withCache($cache, function() : string {
                return 'foo';
            })
            ->withCacheLifespanBuilder(function($result) : int {

                // assert
                static::assertEquals('qux', $result);
                return 1500;
            })

            /** @var EventDispatcherInterface $dispatcher */
            ->withEventDispatcher($dispatcher)
            ->get();

        // assert
        static::assertEquals('qux', $result);
    }

    /**
     * @test
     */
    public function Cache_miss_without_build() : void {

        // arrange
        $dispatcher = $this->newMock(EventDispatcherInterface::class);
        $dispatcher->expects($this->exactly(2))
            ->method('dispatch')
            ->withConsecutive(
                [static::equalTo(new Event('cache:get:start'))],
                [static::equalTo(new Event('cache:get:stop.miss'))]
            );
        $cache = $this->newMock(CacheInterface::class);

        /** @noinspection PhpParamsInspection */
        $cache->expects($this->once())
            ->method('get')
            ->with(static::equalTo('foo'))
            ->willReturn(null);

        // act
        $result = (new CacheBuilder())

            /** @var CacheInterface $cache */
            ->withCache($cache, function() : string {
                return 'foo';
            })

            /** @var EventDispatcherInterface $dispatcher */
            ->withEventDispatcher($dispatcher)
            ->get();

        // assert
        static::assertNull($result);
    }

    /**
     * @test
     */
    public function Cache_hit_fails_validation_with_build() : void {

        // arrange
        $dispatcher = $this->newMock(EventDispatcherInterface::class);
        $dispatcher->expects($this->exactly(7))
            ->method('dispatch')
            ->withConsecutive(
                [static::equalTo(new Event('cache:get:start'))],
                [static::equalTo(new Event('cache:get:stop.miss'))],
                [static::equalTo(new Event('build:start'))],
                [static::equalTo(new Event('build:stop'))],
                [static::equalTo(new Event('build:validation.pass'))],
                [static::equalTo(new Event('cache:set:start'))],
                [static::equalTo(new Event('cache:set:stop'))]
            );
        $cache = $this->newMock(CacheInterface::class);

        /** @noinspection PhpParamsInspection */
        $cache->expects($this->once())
            ->method('get')
            ->with(static::equalTo('foo'))
            ->willReturn('fred');

        /** @noinspection PhpParamsInspection */
        $cache->expects($this->once())
            ->method('set')
            ->with(static::equalTo('foo'), static::equalTo('xyzzy'), static::equalTo(0));

        // act
        $result = (new CacheBuilder())
            ->withBuilder(function() : string {
                return 'xyzzy';
            })

            /** @var CacheInterface $cache */
            ->withCache($cache, function() : string {
                return 'foo';
            })
            ->withCacheValidator(function($result) : bool {

                // assert
                static::assertEquals('fred', $result);
                return false;
            })

            /** @var EventDispatcherInterface $dispatcher */
            ->withEventDispatcher($dispatcher)
            ->get();

        // assert
        static::assertEquals('xyzzy', $result);
    }

    /**
     * @test
     */
    public function Handles_cache_get_error() : void {

        // arrange
        $dispatcher = $this->newMock(EventDispatcherInterface::class);
        $dispatcher->expects($this->exactly(3))
            ->method('dispatch')
            ->withConsecutive(
                [static::equalTo(new Event('cache:get:start'))],
                [static::callback(function(Event $event) : bool {
                    if($event->getState() !== 'cache:get:error') {
                        return false;
                    }
                    if(!isset($event->getData()['exception'])) {
                        return false;
                    }
                    if(!($event->getData()['exception'] instanceof InvalidArgumentException)) {
                        return false;
                    }
                    if($event->isPropagationStopped()) {
                        return false;
                    }
                    return true;
                })],
                [static::equalTo(new Event('cache:get:stop.miss'))]
            );
        $cache = $this->newMock(CacheInterface::class);

        /** @noinspection PhpParamsInspection */
        $cache->expects($this->once())
            ->method('get')
            ->with(static::equalTo('foo'))
            ->willThrowException(new CacheException());

        // act
        $result = (new CacheBuilder())

            /** @var CacheInterface $cache */
            ->withCache($cache, function() : string {
                return 'foo';
            })

            /** @var EventDispatcherInterface $dispatcher */
            ->withEventDispatcher($dispatcher)
            ->get();

        // assert
        static::assertNull($result);
    }

    /**
     * @test
     */
    public function Handles_cache_get_error_and_builds() : void {

        // arrange
        $dispatcher = $this->newMock(EventDispatcherInterface::class);
        $dispatcher->expects($this->exactly(8))
            ->method('dispatch')
            ->withConsecutive(
                [static::equalTo(new Event('cache:get:start'))],
                [static::callback(function(Event $event) : bool {
                    if($event->getState() !== 'cache:get:error') {
                        return false;
                    }
                    if(!isset($event->getData()['exception'])) {
                        return false;
                    }
                    if(!($event->getData()['exception'] instanceof InvalidArgumentException)) {
                        return false;
                    }
                    if($event->isPropagationStopped()) {
                        return false;
                    }
                    return true;
                })],
                [static::equalTo(new Event('cache:get:stop.miss'))],
                [static::equalTo(new Event('build:start'))],
                [static::equalTo(new Event('build:stop'))],
                [static::equalTo(new Event('build:validation.pass'))],
                [static::equalTo(new Event('cache:set:start'))],
                [static::equalTo(new Event('cache:set:stop'))]
            );
        $cache = $this->newMock(CacheInterface::class);

        /** @noinspection PhpParamsInspection */
        $cache->expects($this->once())
            ->method('get')
            ->with(static::equalTo('foo'))
            ->willThrowException(new CacheException());

        // act
        $result = (new CacheBuilder())
            ->withBuilder(function() : string {
                return 'plugh';
            })

            /** @var CacheInterface $cache */
            ->withCache($cache, function() : string {
                return 'foo';
            })

            /** @var EventDispatcherInterface $dispatcher */
            ->withEventDispatcher($dispatcher)
            ->get();

        // assert
        static::assertEquals('plugh', $result);
    }

    /**
     * @test
     */
    public function Handles_cache_set_error() : void {

        // arrange
        $dispatcher = $this->newMock(EventDispatcherInterface::class);
        $dispatcher->expects($this->exactly(7))
            ->method('dispatch')
            ->withConsecutive(
                [static::equalTo(new Event('cache:get:start'))],
                [static::equalTo(new Event('cache:get:stop.miss'))],
                [static::equalTo(new Event('build:start'))],
                [static::equalTo(new Event('build:stop'))],
                [static::equalTo(new Event('build:validation.pass'))],
                [static::equalTo(new Event('cache:set:start'))],
                [static::callback(function(Event $event) : bool {
                    if($event->getState() !== 'cache:set:error') {
                        return false;
                    }
                    if(!isset($event->getData()['exception'])) {
                        return false;
                    }
                    if(!($event->getData()['exception'] instanceof InvalidArgumentException)) {
                        return false;
                    }
                    if($event->isPropagationStopped()) {
                        return false;
                    }
                    return true;
                })]
            );
        $cache = $this->newMock(CacheInterface::class);

        /** @noinspection PhpParamsInspection */
        $cache->expects($this->once())
            ->method('get')
            ->with(static::equalTo('foo'))
            ->willReturn(null);

        /** @noinspection PhpParamsInspection */
        $cache->expects($this->once())
            ->method('set')
            ->with(static::equalTo('foo'), static::equalTo('qux'), static::equalTo(0))
            ->willThrowException(new CacheException());

        // act
        $result = (new CacheBuilder())
            ->withBuilder(function() : string {
                return 'qux';
            })

            /** @var CacheInterface $cache */
            ->withCache($cache, function() : string {
                return 'foo';
            })

            /** @var EventDispatcherInterface $dispatcher */
            ->withEventDispatcher($dispatcher)
            ->get();

        // assert
        static::assertEquals('qux', $result);
    }

    /**
     * @param string $class
     * @return MockObject
     */
    private function newMock(string $class) : MockObject {
        return $this->getMockBuilder($class)
            ->setMethods(get_class_methods($class))
            ->disableOriginalConstructor()
            ->getMock();
    }
}

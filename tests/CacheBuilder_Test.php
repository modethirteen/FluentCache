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

use Exception;
use modethirteen\FluentCache\CacheBuilder;
use modethirteen\FluentCache\Event;
use modethirteen\FluentCache\ICacheBuilder;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\SimpleCache\CacheInterface;
use Ramsey\Uuid\UuidFactoryInterface;
use Ramsey\Uuid\UuidInterface;

class CacheBuilder_Test extends TestCase {

    /**
     * @return array
     */
    public static function isLazyDispatcherEnabled_sessionId_Provider() : array {
        return [
            'with lazy dispatcher' => [true, null],
            'without lazy dispatcher' => [false, null],
            'with lazy dispatcher and custom session id' => [true, '456'],
            'without lazy dispatcher and custom session id' => [false, '456']
        ];
    }

    /**
     * @var UuidFactoryInterface
     */
    private $uuidFactory;

    /**
     * @dataProvider isLazyDispatcherEnabled_sessionId_Provider
     * @param bool $isLazyDispatcherEnabled
     * @param string|null $sessionId
     * @test
     */
    public function Build(bool $isLazyDispatcherEnabled, ?string $sessionId) : void {

        // arrange
        $dispatcher = $this->newMock(EventDispatcherInterface::class);
        $dispatcher->expects($this->exactly(2))
            ->method('dispatch')
            ->withConsecutive(
                [static::equalTo(new Event('build:start', $sessionId ?? '123'))],
                [static::equalTo(new Event('build:success', $sessionId ?? '123'))]
            );

        // act
        $builder = (new CacheBuilder($this->uuidFactory))
            ->withBuilder(function() : string {
                return 'qux';
            });

        /** @var EventDispatcherInterface $dispatcher */
        $builder = $isLazyDispatcherEnabled
            ? $builder->withLazyEventDispatcher(function($instance) use ($builder, $dispatcher) : EventDispatcherInterface {
                static::assertInstanceOf(ICacheBuilder::class, $instance);
                return $dispatcher;
            }) : $builder->withEventDispatcher($dispatcher);
        if($sessionId !== null) {
            $builder = $builder->withSessionId($sessionId);
        }
        $result = $builder->get();

        // assert
        static::assertEquals('qux', $result);
    }

    /**
     * @dataProvider isLazyDispatcherEnabled_sessionId_Provider
     * @param bool $isLazyDispatcherEnabled
     * @param string|null $sessionId
     * @test
     */
    public function Build_failed(bool $isLazyDispatcherEnabled, ?string $sessionId) : void {

        // arrange
        $dispatcher = $this->newMock(EventDispatcherInterface::class);
        $dispatcher->expects($this->exactly(2))
            ->method('dispatch')
            ->withConsecutive(
                [static::equalTo(new Event('build:start', $sessionId ?? '123'))],
                [static::equalTo(new Event('build:fail', $sessionId ?? '123'))]
            );

        // act
        $builder = (new CacheBuilder($this->uuidFactory))
            ->withBuilder(function() : string {
                return 'qux';
            })
            ->withBuildValidator(function($result) : bool {

                // assert
                static::assertEquals('qux', $result);
                return false;
            });

         /** @var EventDispatcherInterface $dispatcher */
        $builder = $isLazyDispatcherEnabled
            ? $builder->withLazyEventDispatcher(function($instance) use ($builder, $dispatcher) : EventDispatcherInterface {
                static::assertInstanceOf(ICacheBuilder::class, $instance);
                return $dispatcher;
            }) : $builder->withEventDispatcher($dispatcher);
        if($sessionId !== null) {
            $builder = $builder->withSessionId($sessionId);
        }
        $result = $builder->get();

        // assert
        static::assertEquals('qux', $result);
    }

    /**
     * @test
     * @dataProvider isLazyDispatcherEnabled_sessionId_Provider
     * @param bool $isLazyDispatcherEnabled
     * @param string|null $sessionId
     */
    public function Cache_hit(bool $isLazyDispatcherEnabled, ?string $sessionId) : void {

        // arrange
        $cache = $this->newMock(CacheInterface::class);
        $cache->expects($this->once())
            ->method('get')
            ->with(static::equalTo('foo'))
            ->willReturn('bar');
        $dispatcher = $this->newMock(EventDispatcherInterface::class);

        /** @var CacheInterface $cache */
        $dispatcher->expects($this->exactly(2))
            ->method('dispatch')
            ->withConsecutive(
                [static::equalTo((new Event('cache:get.start', $sessionId ?? '123'))->withCache($cache, 'foo'))],
                [static::equalTo((new Event('cache:get.hit', $sessionId ?? '123'))->withCache($cache, 'foo'))]
            );

        // act
        $builder = (new CacheBuilder($this->uuidFactory))
            ->withCache($cache, function() : string {
                return 'foo';
            });

        /** @var EventDispatcherInterface $dispatcher */
        $builder = $isLazyDispatcherEnabled
            ? $builder->withLazyEventDispatcher(function($instance) use ($builder, $dispatcher) : EventDispatcherInterface {
                static::assertInstanceOf(ICacheBuilder::class, $instance);
                return $dispatcher;
            }) : $builder->withEventDispatcher($dispatcher);
        if($sessionId !== null) {
            $builder = $builder->withSessionId($sessionId);
        }
        $result = $builder->get();

        // assert
        static::assertEquals('bar', $result);
    }

    /**
     * @test
     * @dataProvider isLazyDispatcherEnabled_sessionId_Provider
     * @param bool $isLazyDispatcherEnabled
     * @param string|null $sessionId
     */
    public function Cache_miss_with_build(bool $isLazyDispatcherEnabled, ?string $sessionId) : void {

        // arrange
        $cache = $this->newMock(CacheInterface::class);
        $cache->expects($this->once())
            ->method('get')
            ->with(static::equalTo('foo'))
            ->willReturn(null);
        $cache->expects($this->once())
            ->method('set')
            ->with(static::equalTo('foo'), static::equalTo('qux'), static::equalTo(0))
            ->willReturn(true);
        $dispatcher = $this->newMock(EventDispatcherInterface::class);

        /** @var CacheInterface $cache */
        $dispatcher->expects($this->exactly(6))
            ->method('dispatch')
            ->withConsecutive(
                [static::equalTo((new Event('cache:get.start', $sessionId ?? '123'))->withCache($cache, 'foo'))],
                [static::equalTo((new Event('cache:get.miss', $sessionId ?? '123'))->withCache($cache, 'foo'))],
                [static::equalTo((new Event('build:start', $sessionId ?? '123'))->withCache($cache, 'foo'))],
                [static::equalTo((new Event('build:success', $sessionId ?? '123'))->withCache($cache, 'foo'))],
                [static::equalTo((new Event('cache:set.start', $sessionId ?? '123'))->withCache($cache, 'foo'))],
                [static::equalTo((new Event('cache:set.success', $sessionId ?? '123'))->withCache($cache, 'foo'))]
            );

        // act
        $builder = (new CacheBuilder($this->uuidFactory))
            ->withBuilder(function() : string {
                return 'qux';
            })
            ->withCache($cache, function() : string {
                return 'foo';
            });

        /** @var EventDispatcherInterface $dispatcher */
        $builder = $isLazyDispatcherEnabled
            ? $builder->withLazyEventDispatcher(function($instance) use ($builder, $dispatcher) : EventDispatcherInterface {
                static::assertInstanceOf(ICacheBuilder::class, $instance);
                return $dispatcher;
            }) : $builder->withEventDispatcher($dispatcher);
        if($sessionId !== null) {
            $builder = $builder->withSessionId($sessionId);
        }
        $result = $builder->get();

        // assert
        static::assertEquals('qux', $result);
    }

    /**
     * @test
     * @dataProvider isLazyDispatcherEnabled_sessionId_Provider
     * @param bool $isLazyDispatcherEnabled
     * @param string|null $sessionId
     */
    public function Cache_miss_with_build_and_ttl(bool $isLazyDispatcherEnabled, ?string $sessionId) : void {

        // arrange
        $cache = $this->newMock(CacheInterface::class);
        $cache->expects($this->once())
            ->method('get')
            ->with(static::equalTo('foo'))
            ->willReturn(null);
        $cache->expects($this->once())
            ->method('set')
            ->with(static::equalTo('foo'), static::equalTo('qux'), static::equalTo(1500))
            ->willReturn(true);
        $dispatcher = $this->newMock(EventDispatcherInterface::class);

        /** @var CacheInterface $cache */
        $dispatcher->expects($this->exactly(6))
            ->method('dispatch')
            ->withConsecutive(
                [static::equalTo((new Event('cache:get.start', $sessionId ?? '123'))->withCache($cache, 'foo'))],
                [static::equalTo((new Event('cache:get.miss', $sessionId ?? '123'))->withCache($cache, 'foo'))],
                [static::equalTo((new Event('build:start', $sessionId ?? '123'))->withCache($cache, 'foo'))],
                [static::equalTo((new Event('build:success', $sessionId ?? '123'))->withCache($cache, 'foo'))],
                [static::equalTo((new Event('cache:set.start', $sessionId ?? '123'))->withCache($cache, 'foo'))],
                [static::equalTo((new Event('cache:set.success', $sessionId ?? '123'))->withCache($cache, 'foo'))]
            );

        // act
        $builder = (new CacheBuilder($this->uuidFactory))
            ->withBuilder(function() : string {
                return 'qux';
            })
            ->withCache($cache, function() : string {
                return 'foo';
            })
            ->withCacheLifespanBuilder(function($result) : int {

                // assert
                static::assertEquals('qux', $result);
                return 1500;
            });

        /** @var EventDispatcherInterface $dispatcher */
        $builder = $isLazyDispatcherEnabled
            ? $builder->withLazyEventDispatcher(function($instance) use ($builder, $dispatcher) : EventDispatcherInterface {
                static::assertInstanceOf(ICacheBuilder::class, $instance);
                return $dispatcher;
            }) : $builder->withEventDispatcher($dispatcher);
        if($sessionId !== null) {
            $builder = $builder->withSessionId($sessionId);
        }
        $result = $builder->get();

        // assert
        static::assertEquals('qux', $result);
    }

    /**
     * @test
     * @dataProvider isLazyDispatcherEnabled_sessionId_Provider
     * @param bool $isLazyDispatcherEnabled
     * @param string|null $sessionId
     */
    public function Cache_miss_without_build(bool $isLazyDispatcherEnabled, ?string $sessionId) : void {

        // arrange
        $cache = $this->newMock(CacheInterface::class);
        $cache->expects($this->once())
            ->method('get')
            ->with(static::equalTo('foo'))
            ->willReturn(null);
        $dispatcher = $this->newMock(EventDispatcherInterface::class);

        /** @var CacheInterface $cache */
        $dispatcher->expects($this->exactly(2))
            ->method('dispatch')
            ->withConsecutive(
                [static::equalTo((new Event('cache:get.start', $sessionId ?? '123'))->withCache($cache, 'foo'))],
                [static::equalTo((new Event('cache:get.miss', $sessionId ?? '123'))->withCache($cache, 'foo'))]
            );

        // act
        $builder = (new CacheBuilder($this->uuidFactory))
            ->withCache($cache, function() : string {
                return 'foo';
            });

        /** @var EventDispatcherInterface $dispatcher */
        $builder = $isLazyDispatcherEnabled
            ? $builder->withLazyEventDispatcher(function($instance) use ($builder, $dispatcher) : EventDispatcherInterface {
                static::assertInstanceOf(ICacheBuilder::class, $instance);
                return $dispatcher;
            }) : $builder->withEventDispatcher($dispatcher);
        if($sessionId !== null) {
            $builder = $builder->withSessionId($sessionId);
        }
        $result = $builder->get();

        // assert
        static::assertNull($result);
    }

    /**
     * @test
     * @dataProvider isLazyDispatcherEnabled_sessionId_Provider
     * @param bool $isLazyDispatcherEnabled
     * @param string|null $sessionId
     */
    public function Cache_hit_fails_validation_with_build(bool $isLazyDispatcherEnabled, ?string $sessionId) : void {

        // arrange
        $cache = $this->newMock(CacheInterface::class);
        $cache->expects($this->once())
            ->method('get')
            ->with(static::equalTo('foo'))
            ->willReturn('fred');
        $cache->expects($this->once())
            ->method('set')
            ->with(static::equalTo('foo'), static::equalTo('xyzzy'), static::equalTo(0))
            ->willReturn(true);
        $dispatcher = $this->newMock(EventDispatcherInterface::class);

        /** @var CacheInterface $cache */
        $dispatcher->expects($this->exactly(6))
            ->method('dispatch')
            ->withConsecutive(
                [static::equalTo((new Event('cache:get.start', $sessionId ?? '123'))->withCache($cache, 'foo'))],
                [static::equalTo((new Event('cache:get.miss', $sessionId ?? '123'))->withCache($cache, 'foo'))],
                [static::equalTo((new Event('build:start', $sessionId ?? '123'))->withCache($cache, 'foo'))],
                [static::equalTo((new Event('build:success', $sessionId ?? '123'))->withCache($cache, 'foo'))],
                [static::equalTo((new Event('cache:set.start', $sessionId ?? '123'))->withCache($cache, 'foo'))],
                [static::equalTo((new Event('cache:set.success', $sessionId ?? '123'))->withCache($cache, 'foo'))]
            );

        // act
        $builder = (new CacheBuilder($this->uuidFactory))
            ->withBuilder(function() : string {
                return 'xyzzy';
            })
            ->withCache($cache, function() : string {
                return 'foo';
            })
            ->withCacheValidator(function($result) : bool {

                // assert
                static::assertEquals('fred', $result);
                return false;
            });

        /** @var EventDispatcherInterface $dispatcher */
        $builder = $isLazyDispatcherEnabled
            ? $builder->withLazyEventDispatcher(function($instance) use ($builder, $dispatcher) : EventDispatcherInterface {
                static::assertInstanceOf(ICacheBuilder::class, $instance);
                return $dispatcher;
            }) : $builder->withEventDispatcher($dispatcher);
        if($sessionId !== null) {
            $builder = $builder->withSessionId($sessionId);
        }
        $result = $builder->get();

        // assert
        static::assertEquals('xyzzy', $result);
    }

    /**
     * @test
     * @dataProvider isLazyDispatcherEnabled_sessionId_Provider
     * @param bool $isLazyDispatcherEnabled
     * @param string|null $sessionId
     */
    public function Cache_hit_fails_validation_with_build_and_updated_cache_key(bool $isLazyDispatcherEnabled, ?string $sessionId) : void {

        // arrange
        $cache = $this->newMock(CacheInterface::class);
        $cache->expects($this->once())
            ->method('get')
            ->with(static::equalTo('foo'))
            ->willReturn('fred');
        $cache->expects($this->once())
            ->method('set')
            ->with(static::equalTo('bar'), static::equalTo('xyzzy'), static::equalTo(0))
            ->willReturn(true);
        $dispatcher = $this->newMock(EventDispatcherInterface::class);

        /** @var CacheInterface $cache */
        $dispatcher->expects($this->exactly(6))
            ->method('dispatch')
            ->withConsecutive(
                [static::equalTo((new Event('cache:get.start', $sessionId ?? '123'))->withCache($cache, 'foo'))],
                [static::equalTo((new Event('cache:get.miss', $sessionId ?? '123'))->withCache($cache, 'foo'))],
                [static::equalTo((new Event('build:start', $sessionId ?? '123'))->withCache($cache, 'foo'))],
                [static::equalTo((new Event('build:success', $sessionId ?? '123'))->withCache($cache, 'foo'))],
                [static::equalTo((new Event('cache:set.start', $sessionId ?? '123'))->withCache($cache, 'bar'))],
                [static::equalTo((new Event('cache:set.success', $sessionId ?? '123'))->withCache($cache, 'bar'))]
            );
        $key = 'foo';
        $counter = 0;

        // act
        $builder = (new CacheBuilder($this->uuidFactory))
            ->withBuilder(function() use (&$key) : string {
                $key = 'bar';
                return 'xyzzy';
            })
            ->withCache($cache, function() use (&$key, &$counter) : string {
                $counter++;
                return $key;
            })
            ->withCacheValidator(function($result) : bool {

                // assert
                static::assertEquals('fred', $result);
                return false;
            });

        /** @var EventDispatcherInterface $dispatcher */
        $builder = $isLazyDispatcherEnabled
            ? $builder->withLazyEventDispatcher(function($instance) use ($builder, $dispatcher) : EventDispatcherInterface {
                static::assertInstanceOf(ICacheBuilder::class, $instance);
                return $dispatcher;
            }) : $builder->withEventDispatcher($dispatcher);
        if($sessionId !== null) {
            $builder = $builder->withSessionId($sessionId);
        }
        $result = $builder->get();

        // assert
        static::assertEquals('xyzzy', $result);
        static::assertEquals(2, $counter);
    }

    /**
     * @test
     * @dataProvider isLazyDispatcherEnabled_sessionId_Provider
     * @param bool $isLazyDispatcherEnabled
     * @param string|null $sessionId
     */
    public function Handles_cache_get_error(bool $isLazyDispatcherEnabled, ?string $sessionId) : void {

        // arrange
        $cache = $this->newMock(CacheInterface::class);
        $cache->expects($this->once())
            ->method('get')
            ->with(static::equalTo('foo'))
            ->willThrowException(new CacheException());
        $dispatcher = $this->newMock(EventDispatcherInterface::class);

        /** @var CacheInterface $cache */
        $dispatcher->expects($this->exactly(3))
            ->method('dispatch')
            ->withConsecutive(
                [static::equalTo((new Event('cache:get.start', $sessionId ?? '123'))->withCache($cache, 'foo'))],
                [static::callback(function(Event $event) : bool {
                    if($event->getMessage() !== 'cache:get.error') {
                        return false;
                    }
                    if(!($event->getCacheException() instanceof CacheException)) {
                        return false;
                    }
                    if($event->isPropagationStopped()) {
                        return false;
                    }
                    return true;
                })],
                [static::equalTo((new Event('cache:get.miss', $sessionId ?? '123'))->withCache($cache, 'foo'))]
            );

        // act
        $builder = (new CacheBuilder($this->uuidFactory))
            ->withCache($cache, function() : string {
                return 'foo';
            });

        /** @var EventDispatcherInterface $dispatcher */
        $builder = $isLazyDispatcherEnabled
            ? $builder->withLazyEventDispatcher(function($instance) use ($builder, $dispatcher) : EventDispatcherInterface {
                static::assertInstanceOf(ICacheBuilder::class, $instance);
                return $dispatcher;
            }) : $builder->withEventDispatcher($dispatcher);
        if($sessionId !== null) {
            $builder = $builder->withSessionId($sessionId);
        }
        $result = $builder->get();

        // assert
        static::assertNull($result);
    }

    /**
     * @test
     * @dataProvider isLazyDispatcherEnabled_sessionId_Provider
     * @param bool $isLazyDispatcherEnabled
     * @param string|null $sessionId
     */
    public function Handles_cache_get_error_and_builds(bool $isLazyDispatcherEnabled, ?string $sessionId) : void {

        // arrange
        $cache = $this->newMock(CacheInterface::class);
        $cache->expects($this->once())
            ->method('get')
            ->with(static::equalTo('foo'))
            ->willThrowException(new CacheException());
        $cache->expects($this->once())
            ->method('set')
            ->with(static::equalTo('foo'), static::equalTo('plugh'), static::equalTo(0))
            ->willReturn(true);
        $dispatcher = $this->newMock(EventDispatcherInterface::class);

        /** @var CacheInterface $cache */
        $dispatcher->expects($this->exactly(7))
            ->method('dispatch')
            ->withConsecutive(
                [static::equalTo((new Event('cache:get.start', $sessionId ?? '123'))->withCache($cache, 'foo'))],
                [static::callback(function(Event $event) : bool {
                    if($event->getMessage() !== 'cache:get.error') {
                        return false;
                    }
                    if(!($event->getCacheException() instanceof CacheException)) {
                        return false;
                    }
                    if($event->isPropagationStopped()) {
                        return false;
                    }
                    return true;
                })],
                [static::equalTo((new Event('cache:get.miss', $sessionId ?? '123'))->withCache($cache, 'foo'))],
                [static::equalTo((new Event('build:start', $sessionId ?? '123'))->withCache($cache, 'foo'))],
                [static::equalTo((new Event('build:success', $sessionId ?? '123'))->withCache($cache, 'foo'))],
                [static::equalTo((new Event('cache:set.start', $sessionId ?? '123'))->withCache($cache, 'foo'))],
                [static::equalTo((new Event('cache:set.success', $sessionId ?? '123'))->withCache($cache, 'foo'))]
            );

        // act
        $builder = (new CacheBuilder($this->uuidFactory))
            ->withBuilder(function() : string {
                return 'plugh';
            })
            ->withCache($cache, function() : string {
                return 'foo';
            });

        /** @var EventDispatcherInterface $dispatcher */
        $builder = $isLazyDispatcherEnabled
            ? $builder->withLazyEventDispatcher(function($instance) use ($builder, $dispatcher) : EventDispatcherInterface {
                static::assertInstanceOf(ICacheBuilder::class, $instance);
                return $dispatcher;
            }) : $builder->withEventDispatcher($dispatcher);
        if($sessionId !== null) {
            $builder = $builder->withSessionId($sessionId);
        }
        $result = $builder->get();

        // assert
        static::assertEquals('plugh', $result);
    }

    /**
     * @test
     * @dataProvider isLazyDispatcherEnabled_sessionId_Provider
     * @param bool $isLazyDispatcherEnabled
     * @param string|null $sessionId
     */
    public function Handles_cache_set_error(bool $isLazyDispatcherEnabled, ?string $sessionId) : void {

        // arrange
        $cache = $this->newMock(CacheInterface::class);
        $cache->expects($this->once())
            ->method('get')
            ->with(static::equalTo('foo'))
            ->willReturn(null);
        $cache->expects($this->once())
            ->method('set')
            ->with(static::equalTo('foo'), static::equalTo('qux'), static::equalTo(0))
            ->willThrowException(new CacheException());
        $dispatcher = $this->newMock(EventDispatcherInterface::class);

        /** @var CacheInterface $cache */
        $dispatcher->expects($this->exactly(6))
            ->method('dispatch')
            ->withConsecutive(
                [static::equalTo((new Event('cache:get.start', $sessionId ?? '123'))->withCache($cache, 'foo'))],
                [static::equalTo((new Event('cache:get.miss', $sessionId ?? '123'))->withCache($cache, 'foo'))],
                [static::equalTo((new Event('build:start', $sessionId ?? '123'))->withCache($cache, 'foo'))],
                [static::equalTo((new Event('build:success', $sessionId ?? '123'))->withCache($cache, 'foo'))],
                [static::equalTo((new Event('cache:set.start', $sessionId ?? '123'))->withCache($cache, 'foo'))],
                [static::callback(function(Event $event) : bool {
                    if($event->getMessage() !== 'cache:set.error') {
                        return false;
                    }
                    if(!($event->getCacheException() instanceof CacheException)) {
                        return false;
                    }
                    if($event->isPropagationStopped()) {
                        return false;
                    }
                    return true;
                })]
            );

        // act
        $builder = (new CacheBuilder($this->uuidFactory))
            ->withBuilder(function() : string {
                return 'qux';
            })
            ->withCache($cache, function() : string {
                return 'foo';
            });

        /** @var EventDispatcherInterface $dispatcher */
        $builder = $isLazyDispatcherEnabled
            ? $builder->withLazyEventDispatcher(function($instance) use ($builder, $dispatcher) : EventDispatcherInterface {
                static::assertInstanceOf(ICacheBuilder::class, $instance);
                return $dispatcher;
            }) : $builder->withEventDispatcher($dispatcher);
        if($sessionId !== null) {
            $builder = $builder->withSessionId($sessionId);
        }
        $result = $builder->get();

        // assert
        static::assertEquals('qux', $result);
    }

    /**
     * @test
     * @dataProvider isLazyDispatcherEnabled_sessionId_Provider
     * @param bool $isLazyDispatcherEnabled
     * @param string|null $sessionId
     */
    public function Handles_build_error(bool $isLazyDispatcherEnabled, ?string $sessionId) : void {

        // arrange
        $dispatcher = $this->newMock(EventDispatcherInterface::class);
        $dispatcher->expects($this->exactly(3))
            ->method('dispatch')
            ->withConsecutive(
                [static::equalTo(new Event('build:start', $sessionId ?? '123'))],
                [static::callback(function(Event $event) : bool {
                    if($event->getMessage() !== 'build:error') {
                        return false;
                    }
                    if(!($event->getBuildException() instanceof Exception)) {
                        return false;
                    }
                    if($event->isPropagationStopped()) {
                        return false;
                    }
                    return true;
                })],
                [static::equalTo(new Event('build:fail', $sessionId ?? '123'))]
            );

        // act
        $builder = (new CacheBuilder($this->uuidFactory))
            ->withBuilder(function() : string {
                throw new Exception();
            });

        /** @var EventDispatcherInterface $dispatcher */
        $builder = $isLazyDispatcherEnabled
            ? $builder->withLazyEventDispatcher(function($instance) use ($builder, $dispatcher) : EventDispatcherInterface {
                static::assertInstanceOf(ICacheBuilder::class, $instance);
                return $dispatcher;
            }) : $builder->withEventDispatcher($dispatcher);
        if($sessionId !== null) {
            $builder = $builder->withSessionId($sessionId);
        }
        $result = $builder->get();

        // assert
        static::assertNull($result);
    }

    /**
     * @test
     */
    public function Build_without_event_dispatcher() : void {

        // act
        $result = (new CacheBuilder($this->uuidFactory))
            ->withBuilder(function() : string {
                return 'qux';
            })
            ->get();

        // assert
        static::assertEquals('qux', $result);
    }

    /**
     * @test
     */
    public function Cache_hit_without_event_dispatcher() : void {

        // arrange
        $cache = $this->newMock(CacheInterface::class);
        $cache->expects($this->once())
            ->method('get')
            ->with(static::equalTo('foo'))
            ->willReturn('bar');

        // act
        $result = (new CacheBuilder($this->uuidFactory))

            /** @var CacheInterface $cache */
            ->withCache($cache, function() : string {
                return 'foo';
            })
            ->get();

        // assert
        static::assertEquals('bar', $result);
    }

    /**
     * @test
     */
    public function Cache_miss_with_build_without_event_dispatcher() : void {

        // arrange
        $cache = $this->newMock(CacheInterface::class);
        $cache->expects($this->once())
            ->method('get')
            ->with(static::equalTo('foo'))
            ->willReturn(null);
        $cache->expects($this->once())
            ->method('set')
            ->with(static::equalTo('foo'), static::equalTo('qux'), static::equalTo(0));

        // act
        $result = (new CacheBuilder($this->uuidFactory))
            ->withBuilder(function() : string {
                return 'qux';
            })

            /** @var CacheInterface $cache */
            ->withCache($cache, function() : string {
                return 'foo';
            })
            ->get();

        // assert
        static::assertEquals('qux', $result);
    }

    /**
     * @test
     */
    public function Can_get_session_id() : void {

        // arrange
        $builder = (new CacheBuilder($this->uuidFactory))
            ->withBuilder(function() : string {
                return 'qux';
            })
            ->withSessionId('asdf');

        // act
        $firstSessionId = $builder->getSessionId();
        $builder = $builder->withBuilder(function() : string {
            return 'fred';
        });
        $secondSessionId = $builder->getSessionId();
        $builder = $builder->withSessionId('qwerty');
        $thirdSessionId = $builder->getSessionId();

        // assert
        static::assertEquals('asdf', $firstSessionId);
        static::assertEquals('123', $secondSessionId);
        static::assertEquals('qwerty', $thirdSessionId);
    }

    protected function setUp() {
        parent::setUp();
        $uuid = $this->newMock(UuidInterface::class);
        $uuid->expects($this->any())
            ->method('toString')
            ->willReturn('123');
        $this->uuidFactory = $this->newMock(UuidFactoryInterface::class);
        $this->uuidFactory->expects($this->any())
            ->method('uuid4')
            ->willReturn($uuid);
    }
}

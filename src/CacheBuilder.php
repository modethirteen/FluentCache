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
namespace modethirteen\FluentCache;

use Closure;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\SimpleCache\CacheException;
use Psr\SimpleCache\CacheInterface;

class CacheBuilder implements ICacheBuilder {

    /**
     * @var Closure
     */
    private $buildValidator;

    /**
     * @var Closure|null
     */
    private $builder = null;

    /**
     * @var CacheInterface|null
     */
    private $cache = null;

    /**
     * @var string|null
     */
    private $cacheKey = null;

    /**
     * @var Closure
     */
    private $cacheKeyBuilder;

    /**
     * @var Closure
     */
    private $cacheLifespanBuilder;

    /**
     * @var Closure
     */
    private $cacheValidator;

    /**
     * @var EventDispatcherInterface|null
     */
    private $dispatcher = null;

    /**
     * @var Closure
     */
    private $lazyDispatcher;

    /**
     * Creates an instance with a simple build/cache validators that check results for null
     *
     * @note it is intended that references in this instance are maintained when the instance is cloned
     */
    public function __construct() {
        $this->buildValidator = function($result) : bool {
            return $result !== null;
        };
        $this->cacheKeyBuilder = function() : ?string {
            return null;
        };
        $this->cacheLifespanBuilder = function() : int {
            return 0;
        };
        $this->cacheValidator = function($result) : bool {
            return $result !== null;
        };
        $this->lazyDispatcher = function() : EventDispatcherInterface {
            return new class implements EventDispatcherInterface {
                public function dispatch(object $event) : object {
                    return $event;
                }
            };
        };
    }

    public function get() {
        $dispatcher = $this->getDispatcher();
        if($this->getCache() !== null) {
            $dispatcher->dispatch(new Event(Event::CACHE_GET_START));
            $key = $this->getCacheKey();
            try {
                $result = $key !== null ? $this->cache->get($key) : null;
            } catch(CacheException $e) {
                $dispatcher->dispatch(
                    (new Event(Event::CACHE_GET_ERROR))
                        ->withException($e)
                );
                $result = null;
            }
            $cacheValidator = $this->cacheValidator;
            if($cacheValidator($result) === true) {
                $dispatcher->dispatch(new Event(Event::CACHE_GET_STOP_HIT));
                return $result;
            }
            $dispatcher->dispatch(new Event(Event::CACHE_GET_STOP_MISS));
        }
        if($this->builder === null) {
            return null;
        }
        $dispatcher->dispatch(new Event(Event::BUILD_START));
        $builder = $this->builder;
        $result = $builder();
        $dispatcher->dispatch(new Event(Event::BUILD_STOP));
        $buildValidator = $this->buildValidator;
        if($buildValidator($result) === true) {
            $dispatcher->dispatch(new Event(Event::BUILD_VALIDATION_PASS));
            $key = $this->getCacheKey();
            if($this->getCache() !== null && $key !== null) {
                $cacheLifespanBuilder = $this->cacheLifespanBuilder;
                $dispatcher->dispatch(new Event(Event::CACHE_SET_START));
                try {
                    $this->cache->set($key, $result, $cacheLifespanBuilder($result));
                    $dispatcher->dispatch(new Event(Event::CACHE_SET_STOP));
                } catch(CacheException $e) {
                    $dispatcher->dispatch(
                        (new Event(Event::CACHE_SET_ERROR))
                            ->withException($e)
                    );
                }
            }
        } else {
            $dispatcher->dispatch(new Event(Event::BUILD_VALIDATION_FAIL));
        }
        return $result;
    }

    public function getCache() : ?CacheInterface {
        return $this->cache;
    }

    public function getCacheKey() : ?string {
        if($this->cacheKey === null) {
            $cacheKeyBuilder = $this->cacheKeyBuilder;
            $this->cacheKey = $cacheKeyBuilder();
        }
        return $this->cacheKey;
    }

    public function withBuildValidator(Closure $validator) : ICacheBuilder {
        $instance = clone $this;
        $instance->buildValidator = $validator;
        return $instance;
    }

    public function withBuilder(Closure $builder) : ICacheBuilder {
        $instance = clone $this;
        $instance->builder = $builder;
        return $instance;
    }

    public function withCache(CacheInterface $cache, Closure $cacheKeyBuilder) : ICacheBuilder {
        $instance = clone $this;
        $instance->cache = $cache;
        $instance->cacheKeyBuilder = $cacheKeyBuilder;
        return $instance;
    }

    public function withCacheLifespanBuilder(Closure $builder) : ICacheBuilder {
        $instance = clone $this;
        $instance->cacheLifespanBuilder = $builder;
        return $instance;
    }

    public function withCacheValidator(Closure $validator) : ICacheBuilder {
        $instance = clone $this;
        $instance->cacheValidator = $validator;
        return $instance;
    }

    public function withEventDispatcher(EventDispatcherInterface $dispatcher) : ICacheBuilder {
        $instance = clone $this;
        $instance->dispatcher = $dispatcher;
        return $instance;
    }

    public function withLazyEventDispatcher(Closure $dispatcher) : ICacheBuilder {
        $instance = clone $this;
        $instance->dispatcher = null;
        $instance->lazyDispatcher = $dispatcher;
        return $instance;
    }

    /**
     * @return EventDispatcherInterface|null
     */
    private function getDispatcher() : ?EventDispatcherInterface {
        if($this->dispatcher === null) {
            $func = $this->lazyDispatcher;
            $this->dispatcher = $func($this);
        }
        return $this->dispatcher;
    }
}

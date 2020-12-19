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
use Exception;
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
     * @var bool
     */
    private $isCacheKeyStale = true;

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
        if($this->getCache() !== null) {
            $this->dispatch(new Event(Event::CACHE_GET_START));
            $key = $this->getCacheKey();
            try {
                $result = $key !== null ? $this->cache->get($key) : null;
            } catch(CacheException $e) {
                $this->dispatch((new Event(Event::CACHE_GET_ERROR))->withCacheException($e));
                $result = null;
            }
            $cacheValidator = $this->cacheValidator;
            if($cacheValidator($result) === true) {
                $this->dispatch(new Event(Event::CACHE_GET_HIT));
                return $result;
            }
            $this->dispatch(new Event(Event::CACHE_GET_MISS));
        }
        if($this->builder === null) {
            return null;
        }
        $this->dispatch(new Event(Event::BUILD_START));
        $builder = $this->builder;
        try {
            $result = $builder();
        } catch(Exception $e) {
            $this->dispatch((new Event(Event::BUILD_ERROR))->withBuildException($e));
            $result = null;
        }
        $buildValidator = $this->buildValidator;
        if($buildValidator($result) === true) {
            $this->dispatch(new Event(Event::BUILD_SUCCESS));

            // the cache key used for setting a value may be different than the key used to get a value if upstream
            // ...dependencies and state have changed - to be safe, we regenerate the key
            $this->isCacheKeyStale = true;
            $key = $this->getCacheKey();
            if($this->getCache() !== null && $key !== null) {
                $cacheLifespanBuilder = $this->cacheLifespanBuilder;
                $this->dispatch(new Event(Event::CACHE_SET_START));
                try {
                    if($this->cache->set($key, $result, $cacheLifespanBuilder($result))) {
                        $this->dispatch(new Event(Event::CACHE_SET_SUCCESS));
                    } else {
                        $this->dispatch(new Event(Event::CACHE_SET_FAIL));
                    }
                } catch(CacheException $e) {
                    $this->dispatch((new Event(Event::CACHE_SET_ERROR))->withCacheException($e));
                }
            }
        } else {
            $this->dispatch(new Event(Event::BUILD_FAIL));
        }
        return $result;
    }

    public function getCache() : ?CacheInterface {
        return $this->cache;
    }

    public function getCacheKey() : ?string {
        if($this->isCacheKeyStale) {
            $cacheKeyBuilder = $this->cacheKeyBuilder;
            $this->cacheKey = $cacheKeyBuilder();
            $this->isCacheKeyStale = false;
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
     * @param Event $event
     */
    private function dispatch(Event $event) : void {
        if($this->dispatcher === null) {
            $func = $this->lazyDispatcher;
            $this->dispatcher = $func($this);
        }
        if($this->getCache() !== null) {
            $event = $event->withCache($this->getCache(), $this->getCacheKey());
        }
        $this->dispatcher->dispatch($event);
    }
}

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
use Psr\SimpleCache\CacheInterface;

interface ICacheBuilder {

    /**
     * @return mixed|null - null if builder is not set
     */
    function get();

    /**
     * @return CacheInterface|null
     */
    function getCache() : ?CacheInterface;

    /**
     * @return string|null
     */
    function getCacheKey() : ?string;

    /**
     * Return the user defined session id or fallback to a random UUID v4
     *
     * @return string
     */
    function getSessionId() : string;

    /**
     * @param Closure $builder - <$builder() : mixed> : returns result to set in cache and return
     * @return ICacheBuilder
     */
    function withBuilder(Closure $builder) : ICacheBuilder;

    /**
     * @param Closure $validator - <$validator($result) : bool> : returns true if valid result
     * @return ICacheBuilder
     */
    function withBuildValidator(Closure $validator) : ICacheBuilder;

    /**
     * @param CacheInterface $cache
     * @param Closure $cacheKeyBuilder - <$cacheKeyBuilder() : ?string> : returns string|null cache key
     * @return ICacheBuilder
     */
    function withCache(CacheInterface $cache, Closure $cacheKeyBuilder) : ICacheBuilder;

    /**
     * @param Closure $builder - <$builder($result) : int> : returns time to live (ttl) for built results set in cache
     * @return ICacheBuilder
     */
    function withCacheLifespanBuilder(Closure $builder) : ICacheBuilder;

    /**
     * @param Closure $validator - <$validator($result) : bool> : returns true if valid result
     * @return ICacheBuilder
     */
    function withCacheValidator(Closure $validator) : ICacheBuilder;

    /**
     * @param EventDispatcherInterface $dispatcher - dispatches cache, build, and validation events for profiling
     * @return ICacheBuilder
     */
    function withEventDispatcher(EventDispatcherInterface $dispatcher) : ICacheBuilder;

    /**
     * @param Closure $dispatcher - <$dispatcher(ICacheBuilder $this) : EventDispatcherInterface> : initializes dispatcher when `get` method is called
     * @return ICacheBuilder
     */
    function withLazyEventDispatcher(Closure $dispatcher) : ICacheBuilder;

    /**
     * @param string $sessionId - a user defined token to identify events in downstream processing
     * @return ICacheBuilder
     */
    function withSessionId(string $sessionId) : ICacheBuilder;
}

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

use Exception;
use Psr\EventDispatcher\StoppableEventInterface;
use Psr\SimpleCache\CacheException;
use Psr\SimpleCache\CacheInterface;

class Event extends \Symfony\Contracts\EventDispatcher\Event implements StoppableEventInterface {
    const CACHE_GET_START = 'cache:get.start';
    const CACHE_GET_ERROR = 'cache:get.error';
    const CACHE_GET_HIT = 'cache:get.hit';
    const CACHE_GET_MISS = 'cache:get.miss';
    const BUILD_START = 'build:start';
    const BUILD_ERROR = 'build:error';
    const BUILD_SUCCESS = 'build:success';
    const BUILD_FAIL = 'build:fail';
    const CACHE_SET_START = 'cache:set.start';
    const CACHE_SET_ERROR = 'cache:set.error';
    const CACHE_SET_SUCCESS = 'cache:set.success';
    const CACHE_SET_FAIL = 'cache:set.fail';

    /**
     * @var string|null
     */
    private $cacheKey = null;

    /**
     * @var string|null
     */
    private $cacheType = null;

    /**
     * @var Exception|null
     */
    private $buildException = null;

    /**
     * @var CacheException|null
     */
    private $cacheException = null;

    /**
     * @var string
     */
    private $message;

    /**
     * @param string $message
     */
    public function __construct(string $message) {
        $this->message = $message;
    }

    /**
     * @return Exception|null
     */
    public function getBuildException() : ?Exception {
        return $this->buildException;
    }

    /**
     * @return string|null
     */
    public function getCacheKey() : ?string {
        return $this->cacheKey;
    }

    /**
     * @return string|null
     */
    public function getCacheType() : ?string {
        return $this->cacheType;
    }

    /**
     * @return CacheException|null
     */
    public function getCacheException() : ?CacheException {
        return $this->cacheException;
    }

    /**
     * @return string
     */
    public function getMessage() : string {
        return $this->message;
    }

    /**
     * @param Exception $e
     * @return static
     */
    public function withBuildException(Exception $e) : object {
        $event = clone $this;
        $event->buildException = $e;
        return $event;
    }

    /**
     * @param CacheInterface $cache
     * @param string|null $key
     * @return static
     */
    public function withCache(CacheInterface $cache, ?string $key = null) : object {
        $event = clone $this;
        $event->cacheType = get_class($cache);
        $event->cacheKey = $key;
        return $event;
    }

    /**
     * @param CacheException $e
     * @return static
     */
    public function withCacheException(CacheException $e) : object {
        $event = clone $this;
        $event->cacheException = $e;
        return $event;
    }
}

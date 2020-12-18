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

use Psr\EventDispatcher\StoppableEventInterface;
use Psr\SimpleCache\CacheException;
use Psr\SimpleCache\CacheInterface;

class Event extends \Symfony\Contracts\EventDispatcher\Event implements StoppableEventInterface {
    const CACHE_GET_START = 'cache:get.start';
    const CACHE_GET_ERROR = 'cache:get.error';
    const CACHE_GET_HIT = 'cache:get.hit';
    const CACHE_GET_MISS = 'cache:get.miss';
    const CACHE_VALIDATION_SUCCESS = 'cache:validation.success';
    const CACHE_VALIDATION_FAIL = 'cache:validation.fail';
    const BUILD_START = 'build:start';
    const BUILD_STOP = 'build:stop';
    const BUILD_VALIDATION_SUCCESS = 'build:validation.success';
    const BUILD_VALIDATION_FAIL = 'build:validation.fail';
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
     * @var CacheException|null
     */
    private $exception = null;

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
    public function getException() : ?CacheException {
        return $this->exception;
    }

    /**
     * @return string
     */
    public function getMessage() : string {
        return $this->message;
    }

    /**
     * @param CacheInterface $cache
     * @param string $key
     * @return static
     */
    public function withCache(CacheInterface $cache, string $key) : object {
        $event = clone $this;
        $event->cacheType = get_class($cache);
        $event->cacheKey = $key;
        return $event;
    }

    /**
     * @param CacheException $e
     * @return static
     */
    public function withException(CacheException $e) : object {
        $event = clone $this;
        $event->exception = $e;
        return $event;
    }
}

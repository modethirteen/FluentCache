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

class Event implements StoppableEventInterface {
    const CACHE_GET_START = 'cache:get:start';
    const CACHE_GET_ERROR = 'cache:get:error';
    const CACHE_GET_STOP_HIT = 'cache:get:stop.hit';
    const CACHE_GET_STOP_MISS = 'cache:get:stop.miss';
    const BUILD_START = 'build:start';
    const BUILD_STOP = 'build:stop';
    const BUILD_VALIDATION_PASS = 'build:validation.pass';
    const BUILD_VALIDATION_FAIL = 'build:validation.fail';
    const CACHE_SET_START = 'cache:set:start';
    const CACHE_SET_STOP = 'cache:set:stop';
    const CACHE_SET_ERROR = 'cache:set:error';

    /**
     * @var bool
     */
    private $isPropagationStopped = false;

    /**
     * @var string
     */
    private $name;

    /**
     * @var array
     */
    private $data = [];

    /**
     * @param string $name
     */
    public function __construct(string $name) {
        $this->name = $name;
    }

    /**
     * @return array
     */
    public function getData() : array {
        return $this->data;
    }

    /**
     * @return string
     */
    public function getName() : string {
        return $this->name;
    }

    public function isPropagationStopped() : bool {
        return $this->isPropagationStopped;
    }

    /**
     * @param CacheException $e
     * @param bool $isPropagationStopped - should dispatcher halt sending event to downstream listeners
     * @see https://www.php-fig.org/psr/psr-14 for propagation handling
     * @return Event
     */
    public function withException(CacheException $e, bool $isPropagationStopped = false) : Event {
        $event = clone $this;
        $event->data = ['exception' => $e];
        $event->isPropagationStopped = $isPropagationStopped;
        return $event;
    }
}

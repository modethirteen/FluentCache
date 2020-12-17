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

class Event implements StoppableEventInterface {
    const STATE_CACHE_GET_START = 'cache:get:start';
    const STATE_CACHE_GET_ERROR = 'cache:get:error';
    const STATE_CACHE_GET_STOP_HIT = 'cache:get:stop.hit';
    const STATE_CACHE_GET_STOP_MISS = 'cache:get:stop.miss';
    const STATE_BUILD_START = 'build:start';
    const STATE_BUILD_STOP = 'build:stop';
    const STATE_BUILD_VALIDATION_PASS = 'build:validation.pass';
    const STATE_BUILD_VALIDATION_FAIL = 'build:validation.fail';
    const STATE_CACHE_SET_START = 'cache:set:start';
    const STATE_CACHE_SET_STOP = 'cache:set:stop';
    const STATE_CACHE_SET_ERROR = 'cache:set:error';

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
     * @param Exception $e
     * @param bool $isPropagationStopped - should dispatcher halt sending event to downstream listeners
     * @see https://www.php-fig.org/psr/psr-14 for propagation handling
     * @return Event
     */
    public function withException(Exception $e, bool $isPropagationStopped = false) : Event {
        $event = clone $this;
        $event->data = ['exception' => $e];
        $event->isPropagationStopped = $isPropagationStopped;
        return $event;
    }
}

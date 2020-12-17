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

class Event {

    /**
     * @var string
     */
    private $state;

    /**
     * @var array
     */
    private $data;

    /**
     * @param string $state
     * @param array $data
     */
    public function __construct(string $state, array $data = []) {
        $this->state = $state;
        $this->data = $data;
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
    public function getState() : string {
        return $this->state;
    }
}

<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace core\hook\user;

use core\hook\stoppable_trait;
use stdClass;

/**
 * Allows plugins to insert nodes into site primary navigation
 *
 * @package    core
 * @copyright  2024 Juan Leyva
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\core\attribute\label('Allows plugins to perform actions after a user has been authenticated via login/token.php.')]
#[\core\attribute\tags('login')]
class after_login_token_authentication implements \Psr\EventDispatcher\StoppableEventInterface {
    use stoppable_trait;

    /**
     * Creates new hook.
     *
     * @param stdClass $user Current user object
     * @param stdClass $service Current service object
     */
    public function __construct(protected stdClass $user, protected stdClass $service) {
    }

    /**
     * Current user.
     *
     * @return stdClass
     */
    public function get_user(): stdClass {
        return $this->user;
    }

    /**
     * Current service.
     *
     * @return stdClass
     */
    public function get_service(): stdClass {
        return $this->service;
    }
}

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

namespace tool_mfa\local\hooks;
use moodle_exception;

/**
 * Callback for core\hook\user\after_login_token_authentication.
 *
 * @package    tool_mfa
 * @copyright  2024 Juan Leyva
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class after_login_token_authentication {

    /**
     * Checks if the current service enforces MFA.
     *
     * @param \core\hook\user\after_login_token_authentication $hook
     * @throws moodle_exception
     */
    public static function callback(\core\hook\user\after_login_token_authentication $hook): void {
        $service = $hook->get_service();

        // Let's check if the service enforces MFA.
        $mfaconfig = get_config('tool_mfa');
        if ($service->shortname == MOODLE_OFFICIAL_MOBILE_SERVICE && $mfaconfig->enabled && $mfaconfig->enabledformobile) {
            throw new moodle_exception('error:mfarequired', 'tool_mfa');
        }
    }
}

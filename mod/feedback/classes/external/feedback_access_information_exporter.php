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

/**
 * Class for exporting access information for a feedback.
 *
 * @package    mod_feedback
 * @copyright  2017 Juan Leyva <juan@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace mod_feedback\external;
defined('MOODLE_INTERNAL') || die();

use core\external\exporter;

/**
 * Class for exporting access information for a feedback.
 *
 * @copyright  2017 Juan Leyva <juan@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      Moodle 3.3
 */
class feedback_access_information_exporter extends exporter {

    /**
     * Return the list of properties.
     *
     * @return array list of properties
     */
    protected static function define_properties() {
        return array(
            'canedititems' => array(
                'type' => PARAM_BOOL,
                'description' => 'Whether the user can edit questions or not.',
            ),
            'canviewreports' => array(
                'type' => PARAM_BOOL,
                'description' => 'Whether the user can view reports or not.',
            ),
            'candeletesubmissions' => array(
                'type' => PARAM_BOOL,
                'description' => 'Whether the user can delete submissions or not.',
            ),
            'canmapcourse' => array(
                'type' => PARAM_BOOL,
                'description' => 'Whether the user can map courses or not.',
            ),
            'canviewanalysis' => array(
                'type' => PARAM_BOOL,
                'description' => 'Whether the user can view the analysis or not.',
            ),
            'cancomplete' => array(
                'type' => PARAM_BOOL,
                'description' => 'Whether the user can complete the feedback or not.',
            ),
            'cansubmit' => array(
                'type' => PARAM_BOOL,
                'description' => 'Whether the user can submit the feedback or not.',
            ),
            'isempty' => array(
                'type' => PARAM_BOOL,
                'description' => 'Whether the feedback has questions or not.',
            ),
            'isopen' => array(
                'type' => PARAM_BOOL,
                'description' => 'Whether the feedback has active access time restrictions or not.',
            ),
            'isalreadysubmitted' => array(
                'type' => PARAM_BOOL,
                'description' => 'Whether the feedback is already submitted or not.',
            ),
        );
    }
}

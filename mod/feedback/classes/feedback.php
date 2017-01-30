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
 * Class for feedback persistence.
 *
 * @package    mod_feedback
 * @copyright  2017 Juan Leyva <juan@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace mod_feedback;

use context_module;

/**
 * Class for loading/storing feedbacks from the DB.
 *
 * @copyright  22017 Juan Leyva <juan@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class feedback extends \core\persistent {

    /** Table name for feedback persistency */
    const TABLE = 'feedback';

    /**
     * Return the definition of the properties of this model.
     *
     * @return array
     */
    protected static function define_properties() {
        return array(
            'course' => array(
                'type' => PARAM_INT,
            ),
            'name' => array(
                'type' => PARAM_TEXT,
            ),
            'intro' => array(
                'default' => '',
                'type' => PARAM_RAW
            ),
            'introformat' => array(
                'choices' => array(FORMAT_HTML, FORMAT_MOODLE, FORMAT_PLAIN, FORMAT_MARKDOWN),
                'type' => PARAM_INT,
                'default' => FORMAT_MOODLE
            ),
            'anonymous' => array(
                'default' => 1,
                'type' => PARAM_INT
            ),
            'email_notification' => array(
                'default' => 1,
                'type' => PARAM_BOOL
            ),
            'multiple_submit' => array(
                'default' => 1,
                'type' => PARAM_BOOL
            ),
            'autonumbering' => array(
                'default' => 1,
                'type' => PARAM_BOOL
            ),
            'site_after_submit' => array(
                'type' => PARAM_TEXT
            ),
            'page_after_submit' => array(
                'default' => '',
                'type' => PARAM_RAW
            ),
            'page_after_submitformat' => array(
                'choices' => array(FORMAT_HTML, FORMAT_MOODLE, FORMAT_PLAIN, FORMAT_MARKDOWN),
                'type' => PARAM_INT,
                'default' => FORMAT_MOODLE
            ),
            'publish_stats' => array(
                'default' => 0,
                'type' => PARAM_BOOL
            ),
            'timeopen' => array(
                'default' => 0,
                'type' => PARAM_INT
            ),
            'timeclose' => array(
                'default' => 0,
                'type' => PARAM_INT
            ),
            'timecreated' => array(
                'default' => 0,
                'type' => PARAM_INT
            ),
            'timemodified' => array(
                'default' => 0,
                'type' => PARAM_INT
            ),
            'usermodified' => array(
                'default' => 0,
                'type' => PARAM_INT
            ),
            'completionsubmit' => array(
                'default' => 0,
                'type' => PARAM_BOOL
            ),
        );
    }
}

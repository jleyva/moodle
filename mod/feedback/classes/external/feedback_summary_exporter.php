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
 * Class for exporting partial feedback data.
 *
 * @package    mod_feedback
 * @copyright  2017 Juan Leyva <juan@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace mod_feedback\external;
defined('MOODLE_INTERNAL') || die();

use mod_feedback\feedback;
use core\external\exporter;
use renderer_base;
use core_files\external\stored_file_exporter;

/**
 * Class for exporting partial feedback data (some fields are only viewable by admins).
 *
 * @copyright  2017 Juan Leyva <juan@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class feedback_summary_exporter extends exporter {

    /**
     * Return the name of the optional properties.
     * @return array list of optional properties
     */
    public static function get_optional_properties() {
        return array('anonymous', 'email_notification', 'site_after_submit', 'page_after_submit', 'page_after_submitformat',
                        'timeopen', 'timeclose', 'timecreated', 'timemodified', 'usermodified');
    }

    protected static function define_properties() {
        // Get feedback properties.
        $feedbackproperties = feedback::properties_definition();

        // Set some properties optional.
        $optionals = static::get_optional_properties();
        foreach ($optionals as $optional) {
            $feedbackproperties[$optional]['optional'] = true;
            // Remove  default since is now optional.
            if (isset($feedbackproperties[$optional]['default'])) {
                unset($feedbackproperties[$optional]['default']);
            }
        }
        return $feedbackproperties;
    }

    protected static function define_related() {
        return array(
            'context' => 'context'
        );
    }

    protected static function define_other_properties() {
        return array(
            'coursemodule' => array(
                'type' => PARAM_INT
            ),
            'introfiles' => array(
                'type' => stored_file_exporter::read_properties_definition(),
                'multiple' => true
            ),
            'pageaftersubmitfiles' => array(
                'type' => stored_file_exporter::read_properties_definition(),
                'multiple' => true,
                'optional' => true
            ),
        );
    }

    protected function get_other_values(renderer_base $output) {
        $context = $this->related['context'];

        $values = array(
            'coursemodule' => $context->instanceid,
        );

        $fs = get_file_storage();
        $files = array();
        $introfiles = $fs->get_area_files($context->id, 'mod_feedback', 'intro', false, 'filename', false);
        if (!empty($introfiles)) {
            foreach ($introfiles as $storedfile) {
                $fileexporter = new stored_file_exporter($storedfile, array('context' => $context));
                $files[] = $fileexporter->export($output);
            }
        }
        $values['introfiles'] = $files;

        if ($this->data->page_after_submit !== null) {
            $files = array();
            $pageaftersubmitfiles = $fs->get_area_files($context->id, 'mod_feedback', 'page_after_submit', false, 'filename', false);
            if (!empty($pageaftersubmitfiles)) {
                foreach ($pageaftersubmitfiles as $storedfile) {
                    $fileexporter = new stored_file_exporter($storedfile, array('context' => $context));
                    $files[] = $fileexporter->export($output);
                }
            }
            $values['pageaftersubmitfiles'] = $files;
        }

        return $values;
    }

    /**
     * Get the formatting parameters for the intro.
     *
     * @return array
     */
    protected function get_format_parameters_for_intro() {
        return [
            'component' => 'mod_feedback',
            'filearea' => 'intro',
        ];
    }

    /**
     * Get the formatting parameters for the page_after_submit.
     *
     * @return array
     */
    protected function get_format_parameters_for_page_after_submit() {
        return [
            'component' => 'mod_feedback',
            'filearea' => 'page_after_submit',
            'itemid' => 0
        ];
    }
}

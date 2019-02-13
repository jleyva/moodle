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
 * External comment API
 *
 * @package    core_comment
 * @category   external
 * @copyright  Costantino Cito <ccito@cvaconsulting.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      Moodle 2.9
 */

defined('MOODLE_INTERNAL') || die();

require_once("$CFG->libdir/externallib.php");
require_once("$CFG->dirroot/comment/lib.php");

/**
 * External comment API functions
 *
 * @package    core_comment
 * @category   external
 * @copyright  Costantino Cito <ccito@cvaconsulting.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      Moodle 2.9
 */
class core_comment_external extends external_api {
    /**
     * Returns description of method parameters
     *
     * @return external_function_parameters
     * @since Moodle 2.9
     */
    public static function get_comments_parameters() {

        return new external_function_parameters(
            array(
                'contextlevel' => new external_value(PARAM_ALPHA, 'contextlevel system, course, user...'),
                'instanceid'   => new external_value(PARAM_INT, 'the Instance id of item associated with the context level'),
                'component'    => new external_value(PARAM_COMPONENT, 'component'),
                'itemid'       => new external_value(PARAM_INT, 'associated id'),
                'area'         => new external_value(PARAM_AREA, 'string comment area', VALUE_DEFAULT, ''),
                'page'         => new external_value(PARAM_INT, 'page number (0 based)', VALUE_DEFAULT, 0),
            )
        );
    }

    /**
     * Return a list of comments
     *
     * @param string $contextlevel ('system, course, user', etc..)
     * @param int $instanceid
     * @param string $component the name of the component
     * @param int $itemid the item id
     * @param string $area comment area
     * @param int $page page number
     * @return array of comments and warnings
     * @since Moodle 2.9
     */
    public static function get_comments($contextlevel, $instanceid, $component, $itemid, $area = '', $page = 0) {

        $warnings = array();
        $arrayparams = array(
            'contextlevel' => $contextlevel,
            'instanceid'   => $instanceid,
            'component'    => $component,
            'itemid'       => $itemid,
            'area'         => $area,
            'page'         => $page
        );
        $params = self::validate_parameters(self::get_comments_parameters(), $arrayparams);

        $context = self::get_context_from_params($params);
        self::validate_context($context);

        require_capability('moodle/comment:view', $context);

        $args = new stdClass;
        $args->context   = $context;
        $args->area      = $params['area'];
        $args->itemid    = $params['itemid'];
        $args->component = $params['component'];

        $commentobject = new comment($args);
        $comments = $commentobject->get_comments($params['page']);

        // False means no permissions to see comments.
        if ($comments === false) {
            throw new moodle_exception('nopermissions', 'error', '', 'view comments');
        }

        foreach ($comments as $key => $comment) {

                list($comments[$key]->content, $comments[$key]->format) = external_format_text($comment->content,
                                                                                                 $comment->format,
                                                                                                 $context->id,
                                                                                                 $params['component'],
                                                                                                 '',
                                                                                                 0);
        }

        $results = array(
            'comments' => $comments,
            'canpost'  => $commentobject->can_post(),
            'warnings' => $warnings
        );
        return $results;
    }

    /**
     * Returns description of method result value
     *
     * @return external_description
     * @since Moodle 2.9
     */
    public static function get_comments_returns() {
        return new external_single_structure(
            array(
                'comments' => new external_multiple_structure(
                    new external_single_structure(
                        array(
                            'id'             => new external_value(PARAM_INT,  'Comment ID'),
                            'content'        => new external_value(PARAM_RAW,  'The content text formated'),
                            'format'         => new external_format_value('content'),
                            'timecreated'    => new external_value(PARAM_INT,  'Time created (timestamp)'),
                            'strftimeformat' => new external_value(PARAM_NOTAGS, 'Time format'),
                            'profileurl'     => new external_value(PARAM_URL,  'URL profile'),
                            'fullname'       => new external_value(PARAM_NOTAGS, 'fullname'),
                            'time'           => new external_value(PARAM_NOTAGS, 'Time in human format'),
                            'avatar'         => new external_value(PARAM_RAW,  'HTML user picture'),
                            'userid'         => new external_value(PARAM_INT,  'User ID'),
                            'delete'         => new external_value(PARAM_BOOL, 'Permission to delete=true/false', VALUE_OPTIONAL)
                        ), 'comment'
                    ), 'List of comments'
                ),
                'canpost' => new external_value(PARAM_BOOL, 'Whether the user can post in this comment area.', VALUE_OPTIONAL),
                'warnings' => new external_warnings()
            )
        );
    }

    /**
     * Returns description of method parameters
     *
     * @return external_function_parameters
     * @since Moodle 3.7
     */
    public static function add_comment_parameters() {

        return new external_function_parameters(
            array(
                'contextlevel' => new external_value(PARAM_ALPHA, 'contextlevel system, course, user...'),
                'instanceid'   => new external_value(PARAM_INT, 'the Instance id of item associated with the context level'),
                'component'    => new external_value(PARAM_COMPONENT, 'component'),
                'content'    => new external_value(PARAM_RAW, 'component'),
                'itemid'       => new external_value(PARAM_INT, 'associated id'),
                'area'         => new external_value(PARAM_AREA, 'string comment area', VALUE_DEFAULT, ''),
            )
        );
    }

    /**
     * Adds a comment.
     *
     * @param string $contextlevel ('system, course, user', etc..)
     * @param int $instanceid context instance id
     * @param string $component the name of the component where the comment goes
     * @param string $content the comment content
     * @param int $itemid the item id
     * @param string $area comment area
     * @throws comment_exception
     *
     * @return array new comment id and warnings
     * @since Moodle 3.7
     */
    public static function add_comment($contextlevel, $instanceid, $component, $content, $itemid, $area = '') {
        global $CFG, $SITE;

        $params = self::validate_parameters(self::add_comment_parameters(),
            array(
                'contextlevel' => $contextlevel,
                'instanceid'   => $instanceid,
                'component'    => $component,
                'content'      => $content,
                'itemid'       => $itemid,
                'area'         => $area,
            )
        );

        if (empty($CFG->usecomments)) {
            throw new comment_exception('commentsnotenabled', 'moodle');
        }

        $context = self::get_context_from_params($params);
        self::validate_context($context);

        list($context, $course, $cm) = get_context_info_array($context->id);
        if ( $context->id == SYSCONTEXTID ) {
            $course = $SITE;
        }

        // Initialising comment object.
        $args = new stdClass;
        $args->context   = $context;
        $args->course    = $course;
        $args->cm        = $cm;
        $args->component = $params['component'];
        $args->itemid    = $params['itemid'];
        $args->area      = $params['area'];

        $manager = new comment($args);
        if (!$manager->can_post()) {
            throw new comment_exception('nopermissiontocomment');
        }

        $results = array(
            'warnings' => array(),
            'commentid' => 0,
        );

        $newcomment = $manager->add($params['content']);
        if (!empty($newcomment) && is_object($newcomment)) {
            $results['commentid'] = $newcomment->id;
        }

        return $results;
    }

    /**
     * Returns description of method result value
     *
     * @return external_description
     * @since Moodle 3.7
     */
    public static function add_comment_returns() {
        return new external_single_structure(
            array(
                'commentid' => new external_value(PARAM_INT, 'New comment id. 0 if failure.'),
                'warnings' => new external_warnings()
            )
        );
    }

    /**
     * Returns description of method parameters
     *
     * @return external_function_parameters
     * @since Moodle 3.7
     */
    public static function delete_comment_parameters() {

        return new external_function_parameters(
            array(
                'commentid' => new external_value(PARAM_INT, 'Comment to be deleted.'),
            )
        );
    }

    /**
     * Deletes a comment.
     *
     * @param int $commentid comment id to be deleted
     * @throws comment_exception
     *
     * @return array success status and warnings
     * @since Moodle 3.7
     */
    public static function delete_comment($commentid) {
        global $CFG, $DB, $USER;

        $params = self::validate_parameters(self::delete_comment_parameters(),
            array(
                'commentid' => $commentid,
            )
        );

        if (empty($CFG->usecomments)) {
            throw new comment_exception('commentsnotenabled', 'moodle');
        }

        $commentid = $params['commentid'];
        $commentrecord = $DB->get_record('comments', array('id' => $commentid), '*', MUST_EXIST);
        list($context, $course, $cm) = get_context_info_array($commentrecord->contextid);
        if ( $context->id == SYSCONTEXTID ) {
            $course = $SITE;
        }
        self::validate_context($context);

        // Initialising comment object.
        $args = new stdClass;
        $args->context   = $context;
        $args->course    = $course;
        $args->cm        = $cm;
        $args->component = $commentrecord->component;
        $args->itemid    = $commentrecord->itemid;
        $args->area      = $commentrecord->commentarea;

        $manager = new comment($args);

        $result = array(
            'deleted' => false,
            'warnings' => array(),
        );
        if ($manager->can_delete($commentid) || $commentrecord->userid == $USER->id) {
             $result['deleted'] = $manager->delete($commentid);
        } else {
            throw new comment_exception('nopermissiontodelentry');
        }

        return $result;
    }

    /**
     * Returns description of method result value
     *
     * @return external_description
     * @since Moodle 3.7
     */
    public static function delete_comment_returns() {
        return new external_single_structure(
            array(
                'deleted' => new external_value(PARAM_BOOL, 'True if deleted, false otherwise.'),
                'warnings' => new external_warnings()
            )
        );
    }
}

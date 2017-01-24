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
 * Lesson external API
 *
 * @package    mod_lesson
 * @category   external
 * @copyright  2016 Juan Leyva <juan@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      Moodle 3.3
 */

defined('MOODLE_INTERNAL') || die;

require_once($CFG->libdir . '/externallib.php');
require_once($CFG->dirroot . '/mod/lesson/locallib.php');

/**
 * Lesson external functions
 *
 * @package    mod_lesson
 * @category   external
 * @copyright  2016 Juan Leyva <juan@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      Moodle 3.3
 */
class mod_lesson_external extends external_api {

    /**
     * Describes the parameters for get_lessons_by_courses.
     *
     * @return external_function_parameters
     * @since Moodle 3.3
     */
    public static function get_lessons_by_courses_parameters() {
        return new external_function_parameters (
            array(
                'courseids' => new external_multiple_structure(
                    new external_value(PARAM_INT, 'course id'), 'Array of course ids', VALUE_DEFAULT, array()
                ),
            )
        );
    }

    /**
     * Returns a list of lessons in a provided list of courses,
     * if no list is provided all lessons that the user can view will be returned.
     *
     * @param array $courseids Array of course ids
     * @return array of lessons details
     * @since Moodle 3.3
     */
    public static function get_lessons_by_courses($courseids = array()) {
        global $USER;

        $warnings = array();
        $returnedlessons = array();

        $params = array(
            'courseids' => $courseids,
        );
        $params = self::validate_parameters(self::get_lessons_by_courses_parameters(), $params);

        $mycourses = array();
        if (empty($params['courseids'])) {
            $mycourses = enrol_get_my_courses();
            $params['courseids'] = array_keys($mycourses);
        }

        // Ensure there are courseids to loop through.
        if (!empty($params['courseids'])) {

            list($courses, $warnings) = external_util::validate_courses($params['courseids'], $mycourses);

            // Get the lessons in this course, this function checks users visibility permissions.
            // We can avoid then additional validate_context calls.
            $lessons = get_all_instances_in_courses("lesson", $courses);
            foreach ($lessons as $lesson) {
                $context = context_module::instance($lesson->coursemodule);

                $lesson = new lesson($lesson);
                $lesson->update_effective_access($USER->id);

                // Entry to return.
                $lessondetails = array();
                // First, we return information that any user can see in the web interface.
                $lessondetails['id'] = $lesson->id;
                $lessondetails['coursemodule']      = $lesson->coursemodule;
                $lessondetails['course']            = $lesson->course;
                $lessondetails['name']              = external_format_string($lesson->name, $context->id);

                $lessonavailable = $lesson->get_time_restriction_status() === false;
                $lessonavailable = $lessonavailable && $lesson->get_password_restriction_status('') === false;
                $lessonavailable = $lessonavailable && $lesson->get_dependencies_restriction_status() === false;

                if ($lessonavailable) {
                    // Format intro.
                    list($lessondetails['intro'], $lessondetails['introformat']) = external_format_text($lesson->intro,
                                                                    $lesson->introformat, $context->id, 'mod_lesson', 'intro', null);

                    $lessondetails['introfiles'] = external_util::get_area_files($context->id, 'mod_lesson', 'intro', false, false);
                    $lessondetails['mediafiles'] = external_util::get_area_files($context->id, 'mod_lesson', 'mediafile', 0);
                    $viewablefields = array('practice', 'modattempts', 'usepassword', 'grade', 'custom', 'ongoing', 'usemaxgrade',
                                            'maxanswers', 'maxattempts', 'review', 'nextpagedefault', 'feedback', 'minquestions',
                                            'maxpages', 'timelimit', 'retake', 'mediafile', 'mediaheight', 'mediawidth',
                                            'mediaclose', 'slideshow', 'width', 'height', 'bgcolor', 'displayleft', 'displayleftif',
                                            'progressbar', 'allowofflineattempts');

                    // Fields only for managers.
                    if ($lesson->can_manage()) {
                        $additionalfields = array('password', 'dependency', 'conditions', 'activitylink', 'available', 'deadline',
                                                  'timemodified', 'completionendreached', 'completiontimespent');
                        $viewablefields = array_merge($viewablefields, $additionalfields);
                    }

                    foreach ($viewablefields as $field) {
                        $lessondetails[$field] = $lesson->{$field};
                    }
                }
                $returnedlessons[] = $lessondetails;
            }
        }
        $result = array();
        $result['lessons'] = $returnedlessons;
        $result['warnings'] = $warnings;
        return $result;
    }

    /**
     * Describes the get_lessons_by_courses return value.
     *
     * @return external_single_structure
     * @since Moodle 3.3
     */
    public static function get_lessons_by_courses_returns() {
        return new external_single_structure(
            array(
                'lessons' => new external_multiple_structure(
                    new external_single_structure(
                        array(
                            'id' => new external_value(PARAM_INT, 'Standard Moodle primary key.'),
                            'course' => new external_value(PARAM_INT, 'Foreign key reference to the course this lesson is part of.'),
                            'coursemodule' => new external_value(PARAM_INT, 'Course module id.'),
                            'name' => new external_value(PARAM_RAW, 'Lesson name.'),
                            'intro' => new external_value(PARAM_RAW, 'Lesson introduction text.', VALUE_OPTIONAL),
                            'introformat' => new external_format_value('intro', VALUE_OPTIONAL),
                            'introfiles' => new external_files('Files in the introduction text', VALUE_OPTIONAL),
                            'practice' => new external_value(PARAM_INT, 'Practice lesson?', VALUE_OPTIONAL),
                            'modattempts' => new external_value(PARAM_INT, 'Allow student review?', VALUE_OPTIONAL),
                            'usepassword' => new external_value(PARAM_INT, 'Password protected lesson?', VALUE_OPTIONAL),
                            'password' => new external_value(PARAM_RAW, 'Password', VALUE_OPTIONAL),
                            'dependency' => new external_value(PARAM_INT, 'Dependent on (another lesson id)', VALUE_OPTIONAL),
                            'conditions' => new external_value(PARAM_RAW, 'Conditions to enable the lesson', VALUE_OPTIONAL),
                            'grade' => new external_value(PARAM_INT, 'The total that the grade is scaled to be out of',
                                                            VALUE_OPTIONAL),
                            'custom' => new external_value(PARAM_INT, 'Custom scoring?', VALUE_OPTIONAL),
                            'ongoing' => new external_value(PARAM_INT, 'Display ongoing score?', VALUE_OPTIONAL),
                            'usemaxgrade' => new external_value(PARAM_INT, 'How to calculate the final grade', VALUE_OPTIONAL),
                            'maxanswers' => new external_value(PARAM_INT, 'Maximum answers per page', VALUE_OPTIONAL),
                            'maxattempts' => new external_value(PARAM_INT, 'Maximum attempts', VALUE_OPTIONAL),
                            'review' => new external_value(PARAM_INT, 'Provide option to try a question again', VALUE_OPTIONAL),
                            'nextpagedefault' => new external_value(PARAM_INT, 'Action for a correct answer', VALUE_OPTIONAL),
                            'feedback' => new external_value(PARAM_INT, 'Display default feedback', VALUE_OPTIONAL),
                            'minquestions' => new external_value(PARAM_INT, 'Minimum number of questions', VALUE_OPTIONAL),
                            'maxpages' => new external_value(PARAM_INT, 'Number of pages to show', VALUE_OPTIONAL),
                            'timelimit' => new external_value(PARAM_INT, 'Time limit', VALUE_OPTIONAL),
                            'retake' => new external_value(PARAM_INT, 'Re-takes allowed', VALUE_OPTIONAL),
                            'activitylink' => new external_value(PARAM_INT, 'Link to next activity', VALUE_OPTIONAL),
                            'mediafile' => new external_value(PARAM_RAW, 'Local file path or full external URL', VALUE_OPTIONAL),
                            'mediafiles' => new external_files('Media files', VALUE_OPTIONAL),
                            'mediaheight' => new external_value(PARAM_INT, 'Popup for media file height', VALUE_OPTIONAL),
                            'mediawidth' => new external_value(PARAM_INT, 'Popup for media with', VALUE_OPTIONAL),
                            'mediaclose' => new external_value(PARAM_INT, 'Display a close button in the popup?', VALUE_OPTIONAL),
                            'slideshow' => new external_value(PARAM_INT, 'Display lesson as slideshow', VALUE_OPTIONAL),
                            'width' => new external_value(PARAM_INT, 'Slideshow width', VALUE_OPTIONAL),
                            'height' => new external_value(PARAM_INT, 'Slideshow height', VALUE_OPTIONAL),
                            'bgcolor' => new external_value(PARAM_TEXT, 'Slideshow bgcolor', VALUE_OPTIONAL),
                            'displayleft' => new external_value(PARAM_INT, 'Display left pages menu?', VALUE_OPTIONAL),
                            'displayleftif' => new external_value(PARAM_INT, 'Minimum grade to display menu', VALUE_OPTIONAL),
                            'progressbar' => new external_value(PARAM_INT, 'Display progress bar?', VALUE_OPTIONAL),
                            'available' => new external_value(PARAM_INT, 'Available from', VALUE_OPTIONAL),
                            'deadline' => new external_value(PARAM_INT, 'Available until', VALUE_OPTIONAL),
                            'timemodified' => new external_value(PARAM_INT, 'Last time settings were updated', VALUE_OPTIONAL),
                            'completionendreached' => new external_value(PARAM_INT, 'Require end reached for completion?',
                                                                            VALUE_OPTIONAL),
                            'completiontimespent' => new external_value(PARAM_INT, 'Student must do this activity at least for',
                                                                        VALUE_OPTIONAL),
                            'allowofflineattempts' => new external_value(PARAM_INT, 'Whether to allow the lesson to be attempted
                                                                            offline in the mobile app', VALUE_OPTIONAL),
                            'visible' => new external_value(PARAM_INT, 'Visible?', VALUE_OPTIONAL),
                            'groupmode' => new external_value(PARAM_INT, 'Group mode', VALUE_OPTIONAL),
                            'groupingid' => new external_value(PARAM_INT, 'Grouping id', VALUE_OPTIONAL),
                        )
                    )
                ),
                'warnings' => new external_warnings(),
            )
        );
    }

    /**
     * Utility function for validating a lesson.
     *
     * @param int $lessonid lesson instance id
     * @return array array containing the lesson, course, context and course module objects
     * @since  Moodle 3.3
     */
    protected static function validate_lesson($lessonid) {
        global $DB, $USER;

        // Request and permission validation.
        $lesson = $DB->get_record('lesson', array('id' => $lessonid), '*', MUST_EXIST);
        list($course, $cm) = get_course_and_cm_from_instance($lesson, 'lesson');

        $lesson = new lesson($lesson, $cm, $course);
        $lesson->update_effective_access($USER->id);

        $context = $lesson->context;
        self::validate_context($context);

        return array($lesson, $course, $cm, $context);
    }

    /**
     * Validates a new attempt.
     *
     * @param  lesson  $lesson lesson instance
     * @param  array   $params request parameters
     * @param  boolean $return whether to return the errors or throw exceptions
     * @return [array          the errors (if return set to true)
     * @since  Moodle 3.3
     */
    protected static function validate_attempt(lesson $lesson, $params, $return = false) {
        global $USER;

        $errors = array();

        // Avoid checkings for managers.
        if ($lesson->can_manage()) {
            return [];
        }

        // Dead line.
        if ($timerestriction = $lesson->get_time_restriction_status()) {
            $error = ["$timerestriction->reason" => userdate($timerestriction->time)];
            if (!$return) {
                throw new moodle_exception(key($error), 'lesson', '', current($error));
            }
            $errors[key($error)] = current($error);
        }

        // Password protected lesson code.
        if ($passwordrestriction = $lesson->get_password_restriction_status($params['password'])) {
            $error = ["passwordprotectedlesson" => external_format_string($lesson->name, $lesson->context->id)];
            if (!$return) {
                throw new moodle_exception(key($error), 'lesson', '', current($error));
            }
            $errors[key($error)] = current($error);
        }

        // Check for dependencies.
        if ($dependenciesrestriction = $lesson->get_dependencies_restriction_status()) {
            $errorhtmllist = implode('<br />'.get_string('and', 'lesson').'<br />', $dependenciesrestriction->errors);
            $error = ["completethefollowingconditions" => $dependenciesrestriction->dependentlesson->name . $errorhtmllist];
            if (!$return) {
                throw new moodle_exception(key($error), 'lesson', '', current($error));
            }
            $errors[key($error)] = current($error);
        }

        // To check only when no page is set (starting or continuing a lesson).
        if (empty($params['pageid'])) {
            // To avoid multiple calls, store the magic property firstpage.
            $lessonfirstpage = $lesson->firstpage;
            $lessonfirstpageid = $lessonfirstpage ? $lessonfirstpage->id : false;

            // Check if the lesson does not have pages.
            if (!$lessonfirstpageid) {
                $error = ["lessonnotready2" => null];
                if (!$return) {
                    throw new moodle_exception(key($error), 'lesson');
                }
                $errors[key($error)] = current($error);
            }

            // Get the number of retries (also referenced as attempts), and the last page seen.
            $retries = $lesson->count_user_retries($USER->id);
            $lastpageseen = $lesson->get_last_page_seen($retries);

            // Check if the user left a timed session with no retakes.
            if ($lastpageseen !== false && $lastpageseen != LESSON_EOL) {
                if ($lesson->left_during_timed_session($retries) && $lesson->timelimit && !$lesson->retake) {
                    $error = ["leftduringtimednoretake" => null];
                    if (!$return) {
                        throw new moodle_exception(key($error), 'lesson');
                    }
                    $errors[key($error)] = current($error);
                }
            } else if ($retries > 0 && !$lesson->retake) {
                // The user finished the lesson and no retakes are allowed.
                $error = ["noretake" => null];
                if (!$return) {
                    throw new moodle_exception(key($error), 'lesson');
                }
                $errors[key($error)] = current($error);
            }
        } else {
            if (!$timers = $lesson->get_user_timers($USER->id, 'starttime DESC', '*', 0, 1)) {
                $error = ["cannotfindtimer" => null];
                if (!$return) {
                    throw new moodle_exception(key($error), 'lesson');
                }
                $errors[key($error)] = current($error);
            } else {
                $timer = current($timers);
                if (!$lesson->check_time($timer)) {
                    $error = ["eolstudentoutoftime" => null];
                    if (!$return) {
                        throw new moodle_exception(key($error), 'lesson');
                    }
                    $errors[key($error)] = current($error);
                }

                // Check if the user want to review an attempt he just finished.
                if (!empty($params['review'])) {
                    // Allow review only for completed attempts in the following hour.
                    if ($timer->completed and ($timer->lessontime + HOURSECS > time()) ) {
                        $ntries = $lesson->count_user_retries($USER->id);
                        if ($attempts = $lesson->get_attempts($ntries)) {
                            $lastattempt = end($attempts);
                            $USER->modattempts[$lesson->id] = $lastattempt->pageid;
                        }
                    }

                    if (!isset($USER->modattempts[$lesson->id])) {
                        $error = ["studentoutoftimeforreview" => null];
                        if (!$return) {
                            throw new moodle_exception(key($error), 'lesson');
                        }
                        $errors[key($error)] = current($error);
                    }
                }
            }
        }

        return $errors;
    }

    /**
     * Describes the parameters for get_lesson_access_information.
     *
     * @return external_external_function_parameters
     * @since Moodle 3.3
     */
    public static function get_lesson_access_information_parameters() {
        return new external_function_parameters (
            array(
                'lessonid' => new external_value(PARAM_INT, 'lesson instance id')
            )
        );
    }

    /**
     * Return access information for a given lesson.
     *
     * @param int $lessonid lesson instance id
     * @return array of warnings and the access information
     * @since Moodle 3.3
     * @throws  moodle_exception
     */
    public static function get_lesson_access_information($lessonid) {
        global $DB, $USER;

        $warnings = array();

        $params = array(
            'lessonid' => $lessonid
        );
        $params = self::validate_parameters(self::get_lesson_access_information_parameters(), $params);

        list($lesson, $course, $cm, $context) = self::validate_lesson($params['lessonid']);

        $result = array();
        // Capabilities first.
        $result['canmanage'] = $lesson->can_manage();
        $result['canedit'] = has_capability('mod/lesson:edit', $context);
        $result['cangrade'] = has_capability('mod/lesson:grade', $context);
        $result['canviewreports'] = has_capability('mod/lesson:viewreports', $context);

        // Status information.
        $result['reviewmode'] = $lesson->is_in_review_mode();
        $result['retries'] = $lesson->count_user_retries($USER->id);
        $lastpageseen = $lesson->get_last_page_seen($result['retries']);
        $result['lastpageseen'] = ($lastpageseen !== false) ? $lastpageseen : 0;
        $result['leftduringtimedsession'] = $lesson->left_during_timed_session($result['retries']);
        // To avoid multiple calls, store the magic property firstpage.
        $lessonfirstpage = $lesson->firstpage;
        $result['firstpageid'] = $lessonfirstpage ? $lessonfirstpage->id : 0;

        // Access restrictions now, we emulate a new attempt access to get the possible warnings.
        $result['preventaccessreasons'] = [];
        $validationerrors = self::validate_attempt($lesson, ['password' => ''], true);
        foreach ($validationerrors as $reason => $data) {
            $result['preventaccessreasons'][] = [
                'reason' => $reason,
                'data' => $data,
                'message' => get_string($reason, 'lesson', $data),
            ];
        }
        $result['warnings'] = $warnings;
        return $result;
    }

    /**
     * Describes the get_lesson_access_information return value.
     *
     * @return external_single_structure
     * @since Moodle 3.3
     */
    public static function get_lesson_access_information_returns() {
        return new external_single_structure(
            array(
                'canmanage' => new external_value(PARAM_BOOL, 'Whether the user can manage the lesson or not.'),
                'canedit' => new external_value(PARAM_BOOL, 'Whether the user can edit the lesson settings or not.'),
                'cangrade' => new external_value(PARAM_BOOL, 'Whether the user can grade the lesson or not.'),
                'canviewreports' => new external_value(PARAM_BOOL, 'Whether the user can view the lesson reports or not.'),
                'reviewmode' => new external_value(PARAM_BOOL, 'Whether the lesson is in review mode for the current user.'),
                'retries' => new external_value(PARAM_INT, 'The number of retries (also referenced as attempts).'),
                'lastpageseen' => new external_value(PARAM_INT, 'The last page seen id.'),
                'leftduringtimedsession' => new external_value(PARAM_BOOL, 'Whether the user left during a timed session.'),
                'firstpageid' => new external_value(PARAM_INT, 'The lesson first page id.'),
                'preventaccessreasons' => new external_multiple_structure(
                    new external_single_structure(
                        array(
                            'reason' => new external_value(PARAM_ALPHANUMEXT, 'Reason lang string code'),
                            'data' => new external_value(PARAM_RAW, 'Additional data'),
                            'message' => new external_value(PARAM_RAW, 'Complete html message'),
                        ),
                        'The reasons why the user cannot attempt the lesson'
                    )
                ),
                'warnings' => new external_warnings(),
            )
        );
    }

    /**
     * Describes the parameters for view_lesson.
     *
     * @return external_external_function_parameters
     * @since Moodle 3.3
     */
    public static function view_lesson_parameters() {
        return new external_function_parameters (
            array(
                'lessonid' => new external_value(PARAM_INT, 'lesson instance id'),
                'password' => new external_value(PARAM_RAW, 'lesson password', VALUE_DEFAULT, ''),
            )
        );
    }

    /**
     * Trigger the course module viewed event and update the module completion status.
     *
     * @param int $lessonid lesson instance id
     * @param str $password optional password (the lesson may be protected)
     * @return array of warnings and status result
     * @since Moodle 3.3
     * @throws moodle_exception
     */
    public static function view_lesson($lessonid, $password = '') {
        global $DB;

        $params = array('lessonid' => $lessonid, 'password' => $password);
        $params = self::validate_parameters(self::view_lesson_parameters(), $params);
        $warnings = array();

        list($lesson, $course, $cm, $context) = self::validate_lesson($params['lessonid']);
        self::validate_attempt($lesson, $params);

        $lesson->set_module_viewed();

        $result = array();
        $result['status'] = true;
        $result['warnings'] = $warnings;
        return $result;
    }

    /**
     * Describes the view_lesson return value.
     *
     * @return external_single_structure
     * @since Moodle 3.3
     */
    public static function view_lesson_returns() {
        return new external_single_structure(
            array(
                'status' => new external_value(PARAM_BOOL, 'status: true if success'),
                'warnings' => new external_warnings(),
            )
        );
    }

    /**
     * Check if the current user can retrieve lesson information (grades, attempts) about the given user.
     *
     * @param  [type] $userid [description]
     * @throws moodle_exception
     * @since Moodle 3.3
     */
    protected static function check_can_view_user_data($userid, $course, $cm, $context) {
        $user = core_user::get_user($userid, '*', MUST_EXIST);
        core_user::require_active_user($user);
        // Check permissions and that if users share group (if groups enabled).
        require_capability('mod/lesson:viewreports', $context);
        if (!groups_user_groups_visible($course, $user->id, $cm)) {
            throw new moodle_exception('notingroup');
        }
    }

    /**
     * Describes the parameters for get_page_attempts.
     *
     * @return external_external_function_parameters
     * @since Moodle 3.3
     */
    public static function get_page_attempts_parameters() {
        return new external_function_parameters (
            array(
                'lessonid' => new external_value(PARAM_INT, 'lesson instance id'),
                'lessonattempt' => new external_value(PARAM_INT, 'lesson attempt number'),
                'correct' => new external_value(PARAM_BOOL, 'only fetch correct attempts', VALUE_DEFAULT, false),
                'pageid' => new external_value(PARAM_INT, 'only fetch attempts at the given page', VALUE_DEFAULT, null),
                'userid' => new external_value(PARAM_INT, 'only fetch attempts of the given user', VALUE_DEFAULT, null),
            )
        );
    }

    /**
     * Return the list of page question attempts in a given lesson.
     *
     * @param int $lessonid lesson instance id
     * @param int $lessonattempt the lesson attempt number
     * @param bool $correct only fetch correct attempts
     * @param int $pageid only fetch attempts at the given page
     * @param int $userid only fetch attempts of the given user
     * @return array of warnings and page attempts
     * @since Moodle 3.3
     * @throws moodle_exception
     */
    public static function get_page_attempts($lessonid, $lessonattempt, $correct = false, $pageid = null, $userid = null) {
        global $DB, $USER;

        $params = array(
            'lessonid' => $lessonid,
            'lessonattempt' => $lessonattempt,
            'correct' => $correct,
            'pageid' => $pageid,
            'userid' => $userid,
        );
        $params = self::validate_parameters(self::get_page_attempts_parameters(), $params);
        $warnings = array();

        list($lesson, $course, $cm, $context) = self::validate_lesson($params['lessonid']);

        // Default value for userid.
        if (empty($params['userid'])) {
            $params['userid'] = $USER->id;
        }

        // Extra checks so only users with permissions can view other users attempts.
        if ($USER->id != $params['userid']) {
            self::check_can_view_user_data($params['userid'], $course, $cm, $context);
        }

        $result = array();
        $result['attempts'] = $lesson->get_attempts($params['lessonattempt'], $params['correct'], $params['pageid'], $params['userid']);
        $result['warnings'] = $warnings;
        return $result;
    }

    /**
     * Describes the get_page_attempts return value.
     *
     * @return external_single_structure
     * @since Moodle 3.3
     */
    public static function get_page_attempts_returns() {
        return new external_single_structure(
            array(
                'attempts' => new external_multiple_structure(
                    new external_single_structure(
                        array(
                            'id' => new external_value(PARAM_INT, 'The attempt id'),
                            'lessonid' => new external_value(PARAM_INT, 'The attempt lessonid'),
                            'pageid' => new external_value(PARAM_INT, 'The attempt pageid'),
                            'userid' => new external_value(PARAM_INT, 'The user who did the attempt'),
                            'answerid' => new external_value(PARAM_INT, 'The attempt answerid'),
                            'retry' => new external_value(PARAM_INT, 'The lesson attempt number'),
                            'correct' => new external_value(PARAM_INT, 'If it was the correct answer'),
                            'useranswer' => new external_value(PARAM_RAW, 'The complete user answer'),
                            'timeseen' => new external_value(PARAM_INT, 'The time the question was seen'),
                        ),
                        'The question page attempts'
                    )
                ),
                'warnings' => new external_warnings(),
            )
        );
    }

    /**
     * Describes the parameters for get_user_grade.
     *
     * @return external_external_function_parameters
     * @since Moodle 3.3
     */
    public static function get_user_grade_parameters() {
        return new external_function_parameters (
            array(
                'lessonid' => new external_value(PARAM_INT, 'lesson instance id'),
                'userid' => new external_value(PARAM_INT, 'the user id (empty for current user)', VALUE_DEFAULT, null),
            )
        );
    }

    /**
     * Return the list of page question attempts in a given lesson.
     *
     * @param int $lessonid lesson instance id
     * @param int $userid only fetch attempts of the given user
     * @return array of warnings and page attempts
     * @since Moodle 3.3
     * @throws moodle_exception
     */
    public static function get_user_grade($lessonid, $userid = null) {
        global $CFG, $DB, $USER;
        require_once($CFG->libdir . '/gradelib.php');

        $params = array(
            'lessonid' => $lessonid,
            'userid' => $userid,
        );
        $params = self::validate_parameters(self::get_user_grade_parameters(), $params);
        $warnings = array();

        list($lesson, $course, $cm, $context) = self::validate_lesson($params['lessonid']);

        // Default value for userid.
        if (empty($params['userid'])) {
            $params['userid'] = $USER->id;
        }

        // Extra checks so only users with permissions can view other users attempts.
        if ($USER->id != $params['userid']) {
            self::check_can_view_user_data($params['userid'], $course, $cm, $context);
        }

        $grade = null;
        $formattedgrade = null;
        $grades = lesson_get_user_grades($lesson, $params['userid']);
        if (!empty($grades)) {
            $grade = $grades[$params['userid']]->rawgrade;
            $params = array(
                'itemtype' => 'mod',
                'itemmodule' => 'lesson',
                'iteminstance' => $lesson->id,
                'courseid' => $course->id,
                'itemnumber' => 0
            );
            $gradeitem = grade_item::fetch($params);
            $formattedgrade = grade_format_gradevalue($grade, $gradeitem);
        }

        $result = array();
        $result['grade'] = $grade;
        $result['formattedgrade'] = $formattedgrade;
        $result['warnings'] = $warnings;
        return $result;
    }

    /**
     * Describes the get_user_grade return value.
     *
     * @return external_single_structure
     * @since Moodle 3.3
     */
    public static function get_user_grade_returns() {
        return new external_single_structure(
            array(
                'grade' => new external_value(PARAM_FLOAT, 'The lesson final raw grade'),
                'formattedgrade' => new external_value(PARAM_RAW, 'The lesson final grade formatted'),
                'warnings' => new external_warnings(),
            )
        );
    }

    /**
     * Describes an attempt grade structure.
     *
     * @param  int $required if the structure is required or optional
     * @return external_single_structure the structure
     * @since  Moodle 3.3
     */
    protected static function get_user_attempt_grade_structure($required = VALUE_REQUIRED) {
        $data = array(
            'nquestions' => new external_value(PARAM_INT, 'Number of questions answered'),
            'attempts' => new external_value(PARAM_INT, 'Number of question attempts'),
            'total' => new external_value(PARAM_FLOAT, 'Max points possible'),
            'earned' => new external_value(PARAM_FLOAT, 'Points earned by student'),
            'grade' => new external_value(PARAM_FLOAT, 'Calculated percentage grade'),
            'nmanual' => new external_value(PARAM_INT, 'Number of manually graded questions'),
            'manualpoints' => new external_value(PARAM_FLOAT, 'Point value for manually graded questions'),
        );
        return new external_single_structure(
            $data, 'Attempt grade', $required
        );
    }

    /**
     * Describes the parameters for get_user_attempt_grade.
     *
     * @return external_external_function_parameters
     * @since Moodle 3.3
     */
    public static function get_user_attempt_grade_parameters() {
        return new external_function_parameters (
            array(
                'lessonid' => new external_value(PARAM_INT, 'lesson instance id'),
                'lessonattempt' => new external_value(PARAM_INT, 'lesson attempt number'),
                'userid' => new external_value(PARAM_INT, 'the user id (empty for current user)', VALUE_DEFAULT, null),
            )
        );
    }

    /**
     * Return the list of page question attempts in a given lesson.
     *
     * @param int $lessonid lesson instance id
     * @param int $lessonattempt lesson attempt number
     * @param int $userid only fetch attempts of the given user
     * @return array of warnings and page attempts
     * @since Moodle 3.3
     * @throws moodle_exception
     */
    public static function get_user_attempt_grade($lessonid, $lessonattempt, $userid = null) {
        global $CFG, $DB, $USER;
        require_once($CFG->libdir . '/gradelib.php');

        $params = array(
            'lessonid' => $lessonid,
            'lessonattempt' => $lessonattempt,
            'userid' => $userid,
        );
        $params = self::validate_parameters(self::get_user_attempt_grade_parameters(), $params);
        $warnings = array();

        list($lesson, $course, $cm, $context) = self::validate_lesson($params['lessonid']);

        // Default value for userid.
        if (empty($params['userid'])) {
            $params['userid'] = $USER->id;
        }

        // Extra checks so only users with permissions can view other users attempts.
        if ($USER->id != $params['userid']) {
            self::check_can_view_user_data($params['userid'], $course, $cm, $context);
        }

        $result = array();
        $result['grade'] = (array) lesson_grade($lesson, $params['lessonattempt'], $params['userid']);
        $result['warnings'] = $warnings;
        return $result;
    }

    /**
     * Describes the get_user_attempt_grade return value.
     *
     * @return external_single_structure
     * @since Moodle 3.3
     */
    public static function get_user_attempt_grade_returns() {
        return new external_single_structure(
            array(
                'grade' => self::get_user_attempt_grade_structure(),
                'warnings' => new external_warnings(),
            )
        );
    }

    /**
     * Describes the parameters for get_content_pages_viewed.
     *
     * @return external_external_function_parameters
     * @since Moodle 3.3
     */
    public static function get_content_pages_viewed_parameters() {
        return new external_function_parameters (
            array(
                'lessonid' => new external_value(PARAM_INT, 'lesson instance id'),
                'lessonattempt' => new external_value(PARAM_INT, 'lesson attempt number'),
                'userid' => new external_value(PARAM_INT, 'the user id (empty for current user)', VALUE_DEFAULT, null),
            )
        );
    }

    /**
     * Return the list of content pages viewed by a user during a lesson attempt.
     *
     * @param int $lessonid lesson instance id
     * @param int $lessonattempt lesson attempt number
     * @param int $userid only fetch attempts of the given user
     * @return array of warnings and page attempts
     * @since Moodle 3.3
     * @throws moodle_exception
     */
    public static function get_content_pages_viewed($lessonid, $lessonattempt, $userid = null) {
        global $CFG, $DB, $USER;
        require_once($CFG->libdir . '/gradelib.php');

        $params = array(
            'lessonid' => $lessonid,
            'lessonattempt' => $lessonattempt,
            'userid' => $userid,
        );
        $params = self::validate_parameters(self::get_content_pages_viewed_parameters(), $params);
        $warnings = array();

        list($lesson, $course, $cm, $context) = self::validate_lesson($params['lessonid']);

        // Default value for userid.
        if (empty($params['userid'])) {
            $params['userid'] = $USER->id;
        }

        // Extra checks so only users with permissions can view other users attempts.
        if ($USER->id != $params['userid']) {
            self::check_can_view_user_data($params['userid'], $course, $cm, $context);
        }

        $pages = $lesson->get_content_pages_viewed($params['lessonattempt'], $params['userid']);

        $result = array();
        $result['pages'] = $pages;
        $result['warnings'] = $warnings;
        return $result;
    }

    /**
     * Describes the get_content_pages_viewed return value.
     *
     * @return external_single_structure
     * @since Moodle 3.3
     */
    public static function get_content_pages_viewed_returns() {
        return new external_single_structure(
            array(
                'pages' => new external_multiple_structure(
                    new external_single_structure(
                        array(
                            'id' => new external_value(PARAM_INT, 'The attempt id'),
                            'lessonid' => new external_value(PARAM_INT, 'The lesson id'),
                            'pageid' => new external_value(PARAM_INT, 'The page id'),
                            'userid' => new external_value(PARAM_INT, 'The user who view the page'),
                            'retry' => new external_value(PARAM_INT, 'The lesson attempt number'),
                            'flag' => new external_value(PARAM_INT, '1 if the next page was calculated randomly'),
                            'timeseen' => new external_value(PARAM_INT, 'The time the page was seen'),
                            'nextpageid' => new external_value(PARAM_INT, 'The next page chosen id'),
                        ),
                        'The question page attempts'
                    )
                ),
                'warnings' => new external_warnings(),
            )
        );
    }

    /**
     * Describes the parameters for get_user_timers.
     *
     * @return external_external_function_parameters
     * @since Moodle 3.3
     */
    public static function get_user_timers_parameters() {
        return new external_function_parameters (
            array(
                'lessonid' => new external_value(PARAM_INT, 'lesson instance id'),
                'userid' => new external_value(PARAM_INT, 'the user id (empty for current user)', VALUE_DEFAULT, null),
            )
        );
    }

    /**
     * Return the timers in the current lesson for the given user.
     *
     * @param int $lessonid lesson instance id
     * @param int $userid only fetch timers of the given user
     * @return array of warnings and timers
     * @since Moodle 3.3
     * @throws moodle_exception
     */
    public static function get_user_timers($lessonid, $userid = null) {
        global $CFG, $DB, $USER;
        require_once($CFG->libdir . '/gradelib.php');

        $params = array(
            'lessonid' => $lessonid,
            'userid' => $userid,
        );
        $params = self::validate_parameters(self::get_user_timers_parameters(), $params);
        $warnings = array();

        list($lesson, $course, $cm, $context) = self::validate_lesson($params['lessonid']);

        // Default value for userid.
        if (empty($params['userid'])) {
            $params['userid'] = $USER->id;
        }

        // Extra checks so only users with permissions can view other users attempts.
        if ($USER->id != $params['userid']) {
            self::check_can_view_user_data($params['userid'], $course, $cm, $context);
        }

        $timers = $lesson->get_user_timers($params['userid']);

        $result = array();
        $result['timers'] = $timers;
        $result['warnings'] = $warnings;
        return $result;
    }

    /**
     * Describes the get_user_timers return value.
     *
     * @return external_single_structure
     * @since Moodle 3.3
     */
    public static function get_user_timers_returns() {
        return new external_single_structure(
            array(
                'timers' => new external_multiple_structure(
                    new external_single_structure(
                        array(
                            'id' => new external_value(PARAM_INT, 'The attempt id'),
                            'lessonid' => new external_value(PARAM_INT, 'The lesson id'),
                            'userid' => new external_value(PARAM_INT, 'The user id'),
                            'starttime' => new external_value(PARAM_INT, 'First access time for a new timer session'),
                            'lessontime' => new external_value(PARAM_INT, 'Last access time to the lesson during the timer session'),
                            'completed' => new external_value(PARAM_INT, 'If the lesson for this timer was completed'),
                            'timemodifiedoffline' => new external_value(PARAM_INT, 'Last modified time via webservices.'),
                        ),
                        'The timers'
                    )
                ),
                'warnings' => new external_warnings(),
            )
        );
    }

    /**
     * Describes the external structure for a lesson page.
     *
     * @return external_single_structure
     * @since Moodle 3.3
     */
    protected static function get_page_structure() {
        return new external_single_structure(
            array(
                'id' => new external_value(PARAM_INT, 'The id of this lesson page'),
                'lessonid' => new external_value(PARAM_INT, 'The id of the lesson this page belongs to'),
                'prevpageid' => new external_value(PARAM_INT, 'The id of the page before this one'),
                'nextpageid' => new external_value(PARAM_INT, 'The id of the next page in the page sequence'),
                'qtype' => new external_value(PARAM_INT, 'Identifies the page type of this page'),
                'qoption' => new external_value(PARAM_INT, 'Used to record page type specific options'),
                'layout' => new external_value(PARAM_INT, 'Used to record page specific layout selections'),
                'display' => new external_value(PARAM_INT, 'Used to record page specific display selections'),
                'timecreated' => new external_value(PARAM_INT, 'Timestamp for when the page was created'),
                'timemodified' => new external_value(PARAM_INT, 'Timestamp for when the page was last modified'),
                'title' => new external_value(PARAM_RAW, 'The title of this page', VALUE_OPTIONAL),
                'contents' => new external_value(PARAM_RAW, 'The contents of this page', VALUE_OPTIONAL),
                'contentsformat' => new external_format_value('contents', VALUE_OPTIONAL),
                'displayinmenublock' => new external_value(PARAM_BOOL, 'Toggles display in the left menu block'),
                'type' => new external_value(PARAM_INT, 'The type of the page [question | structure]'),
                'typeid' => new external_value(PARAM_INT, 'The unique identifier for the page type'),
                'typestring' => new external_value(PARAM_RAW, 'The string that describes this page type'),
            ),
            'Page fields'
        );
    }

    /**
     * Returns the fields of a page object
     * @param  lesson_page $page the lesson page
     * @return stdClass          the fields matching the external page structure
     * @since Moodle 3.3
     */
    protected static function get_page_fields(lesson_page $page) {
        $lesson = $page->lesson;
        $context = $lesson->context;

        $pagedata = new stdClass; // Contains the data that will be returned by the WS.

        // Return the visible data.
        $visibleproperties = array('id', 'lessonid', 'prevpageid', 'nextpageid', 'qtype', 'qoption', 'layout', 'display',
                                    'displayinmenublock', 'type', 'typeid', 'typestring', 'timecreated', 'timemodified');
        foreach ($visibleproperties as $prop) {
            $pagedata->{$prop} = $page->{$prop};
        }

        // Check if we can see title (contents required custom rendering, we won't returning it here @see get_page_data).
        $canmanage = $lesson->can_manage();
        // If we are managers or the menu block is enabled and is a content page visible.
        if ($canmanage || (lesson_displayleftif($lesson) && $page->displayinmenublock && $page->display)) {
            $pagedata->title = external_format_string($page->title, $context->id);

            list($pagedata->contents, $pagedata->contentsformat) =
                external_format_text($page->contents, $page->contentsformat, $context->id, 'mod_lesson', 'page_contents', $page->id);

        }
        return $pagedata;
    }

    /**
     * Describes the parameters for get_pages.
     *
     * @return external_external_function_parameters
     * @since Moodle 3.3
     */
    public static function get_pages_parameters() {
        return new external_function_parameters (
            array(
                'lessonid' => new external_value(PARAM_INT, 'lesson instance id'),
                'password' => new external_value(PARAM_RAW, 'optional password (the lesson may be protected)', VALUE_DEFAULT, ''),
            )
        );
    }

    /**
     * Return the list of pages in a lesson (based on the user permissions).
     *
     * @param int $lessonid lesson instance id
     * @param str $password optional password (the lesson may be protected)
     * @return array of warnings and status result
     * @since Moodle 3.3
     * @throws moodle_exception
     */
    public static function get_pages($lessonid, $password = '') {
        global $DB;

        $params = array('lessonid' => $lessonid, 'password' => $password);
        $params = self::validate_parameters(self::get_pages_parameters(), $params);
        $warnings = array();

        list($lesson, $course, $cm, $context) = self::validate_lesson($params['lessonid']);
        self::validate_attempt($lesson, $params);

        $lessonpages = $lesson->load_all_pages();
        $pages = array();

        foreach ($lessonpages as $page) {
            $pagedata = new stdClass();

            // Get the page object fields.
            $pagedata->page = self::get_page_fields($page);

            // Now, calculate the file area files (maybe we need to download a lesson for offline usage).
            $pagedata->filescount = 0;
            $pagedata->filessizetotal = 0;
            $files = $page->get_files(false);   // Get pages excluding directories.
            foreach ($files as $file) {
                $pagedata->filescount++;
                $pagedata->filessizetotal += $file->get_filesize();
            }

            // Now the possible answers and page jumps ids.
            $pagedata->answerids = array();
            $pagedata->jumps = array();
            $answers = $page->get_answers();
            foreach ($answers as $answer) {
                $pagedata->answerids[] = $answer->id;
                $pagedata->jumps[] = $answer->jumpto;
                $files = $answer->get_files(false);   // Get pages excluding directories.
                foreach ($files as $file) {
                    $pagedata->filescount++;
                    $pagedata->filessizetotal += $file->get_filesize();
                }
            }
            $pages[] = $pagedata;
        }

        $result = array();
        $result['pages'] = $pages;
        $result['warnings'] = $warnings;
        return $result;
    }

    /**
     * Describes the get_pages return value.
     *
     * @return external_single_structure
     * @since Moodle 3.3
     */
    public static function get_pages_returns() {
        return new external_single_structure(
            array(
                'pages' => new external_multiple_structure(
                    new external_single_structure(
                        array(
                            'page' => self::get_page_structure(),
                            'answerids' => new external_multiple_structure(
                                new external_value(PARAM_INT, 'Answer id'), 'List of answers ids (empty for content pages in  Moodle 1.9)'
                            ),
                            'jumps' => new external_multiple_structure(
                                new external_value(PARAM_INT, 'Page to jump id'), 'List of possible page jumps'
                            ),
                            'filescount' => new external_value(PARAM_INT, 'The total number of files attached to the page'),
                            'filessizetotal' => new external_value(PARAM_INT, 'The total size of the files'),
                        ),
                        'The lesson pages'
                    )
                ),
                'warnings' => new external_warnings(),
            )
        );
    }

    /**
     * Describes the parameters for launch_attempt.
     *
     * @return external_external_function_parameters
     * @since Moodle 3.3
     */
    public static function launch_attempt_parameters() {
        return new external_function_parameters (
            array(
                'lessonid' => new external_value(PARAM_INT, 'lesson instance id'),
                'password' => new external_value(PARAM_RAW, 'optional password (the lesson may be protected)', VALUE_DEFAULT, ''),
                'pageid' => new external_value(PARAM_RAW, 'page id to continue from (only when continuing an attempt)', VALUE_DEFAULT, 0),
                'review' => new external_value(PARAM_RAW, 'if we want to review just after finishing (1 hour margin)', VALUE_DEFAULT, false),
            )
        );
    }

    /**
     * Return lesson messages formatted according the external_messages structure
     *
     * @param  lesson $lesson lesson instance
     * @return array          messages formatted
     * @since Moodle 3.3
     */
    protected static function format_lesson_messages($lesson) {
        $messages = array();
        foreach ($lesson->messages as $message) {
            $messages[] = array(
                'message' => $message[0],
                'type' => $message[1],
            );
        }
        return $messages;
    }

    /**
     * Return a external structure representing messages.
     *
     * @return external_multiple_structure messages structure
     * @since Moodle 3.3
     */
    protected static function external_messages() {
        return new external_multiple_structure(
            new external_single_structure(
                array(
                    'message' => new external_value(PARAM_RAW, 'Message'),
                    'type' => new external_value(PARAM_ALPHANUMEXT, 'Message')
                ), 'The lesson generated messages'
            )
        );
    }

    /**
     * Starts a new attempt or continues an existing one.
     *
     * @param int $lessonid lesson instance id
     * @param str $password optional password (the lesson may be protected)
     * @param int $pageid page id to continue from (only when continuing an attempt)
     * @param bool $review if we want to review just after finishing (1 hour margin)
     * @return array of warnings and status result
     * @since Moodle 3.3
     * @throws moodle_exception
     */
    public static function launch_attempt($lessonid, $password = '', $pageid = 0, $review = false) {
        global $DB;

        $params = array('lessonid' => $lessonid, 'password' => $password, 'pageid' => $pageid, 'review' => $review);
        $params = self::validate_parameters(self::launch_attempt_parameters(), $params);
        $warnings = $messages = array();

        list($lesson, $course, $cm, $context) = self::validate_lesson($params['lessonid']);
        self::validate_attempt($lesson, $params);

        $newpageid = 0;
        // Starting a new lesson attempt.
        if (empty($params['pageid'])) {
            if (!$lesson->can_manage()) {
                $lesson->start_timer();
            }
        } else {
            if ($params['pageid'] == LESSON_EOL) {
                throw new moodle_exception('endoflesson', 'lesson');
            }
            $timer = $lesson->update_timer(true, true);
            if (!$lesson->check_time($timer)) {
                throw new moodle_exception('eolstudentoutoftime', 'lesson');
            }
        }
        $messages = self::format_lesson_messages($lesson);

        $result = array(
            'status' => true,
            'messages' => $messages,
            'warnings' => $warnings,
        );
        return $result;
    }

    /**
     * Describes the launch_attempt return value.
     *
     * @return external_single_structure
     * @since Moodle 3.3
     */
    public static function launch_attempt_returns() {
        return new external_single_structure(
            array(
                'status' => new external_value(PARAM_BOOL, 'status: true if success'),
                'messages' => self::external_messages(),
                'warnings' => new external_warnings(),
            )
        );
    }

    /**
     * Describes the parameters for get_page_data.
     *
     * @return external_external_function_parameters
     * @since Moodle 3.3
     */
    public static function get_page_data_parameters() {
        return new external_function_parameters (
            array(
                'lessonid' => new external_value(PARAM_INT, 'lesson instance id'),
                'pageid' => new external_value(PARAM_INT, 'the page id'),
                'password' => new external_value(PARAM_RAW, 'optional password (the lesson may be protected)', VALUE_DEFAULT, ''),
                'review' => new external_value(PARAM_RAW, 'if we want to review just after finishing (1 hour margin)', VALUE_DEFAULT, false),
            )
        );
    }

    /**
     * Return information of a given page, including its contents.
     *
     * @param int $lessonid lesson instance id
     * @param int $pageid page id
     * @param str $password optional password (the lesson may be protected)
     * @param bool $review if we want to review just after finishing (1 hour margin)
     * @return array of warnings and status result
     * @since Moodle 3.3
     * @throws moodle_exception
     */
    public static function get_page_data($lessonid, $pageid,  $password = '', $review = false) {
        global $DB, $PAGE;

        $params = array('lessonid' => $lessonid, 'password' => $password, 'pageid' => $pageid, 'review' => $review);
        $params = self::validate_parameters(self::get_page_data_parameters(), $params);

        $warnings = $messages = $contentfiles = $answerfiles = $responsefiles = array();
        $pagecontent = $ongoingscore = '';
        $progress = null;

        list($lesson, $course, $cm, $context) = self::validate_lesson($params['lessonid']);
        self::validate_attempt($lesson, $params);

        $pageid = $params['pageid'];

        // This is called if a student leaves during a lesson.
        if ($pageid == LESSON_UNSEENBRANCHPAGE) {
            $pageid = lesson_unseen_question_jump($lesson, $USER->id, $pageid);
        }

        if ($pageid != LESSON_EOL) {
            $lessonoutput = $PAGE->get_renderer('mod_lesson');
            list($page, $pagecontent) = $lesson->prepare_page_and_contents($pageid, $lessonoutput);
            // Page may have changed.
            $pageid = $page->id;

            $pagedata = self::get_page_fields($page);

            // Files.
            $contentfiles = external_util::get_area_files($context->id, 'mod_lesson', 'page_contents', $page->id);

            // Answers.
            $answers = array();
            $pageanswers = $page->get_answers();
            foreach ($pageanswers as $a) {
                $answer = array(
                    'id' => $a->id,
                    'answerfiles' => external_util::get_area_files($context->id, 'mod_lesson', 'page_answers', $a->id),
                    'responsefiles' => external_util::get_area_files($context->id, 'mod_lesson', 'page_responses', $a->id),
                );
                // For managers, return all the information (including correct answers, jumps).
                // If the teacher enabled offline attempts, this information will be downloaded too.
                if ($lesson->can_manage() || $lesson->allowofflineattempts) {
                    $extraproperties = array('jumpto', 'grade', 'score', 'flags', 'timecreated', 'timemodified');
                    foreach ($extraproperties as $prop) {
                        $answer[$prop] = $a->{$prop};
                    }
                }
                $answers[] = $answer;
            }

            // Additional lesson information.
            if (!$lesson->can_manage()) {
                $reviewmode = $lesson->is_in_review_mode();
                if ($lesson->ongoing && !$reviewmode) {
                    $ongoingscore = $lesson->get_ongoing_score_message();
                }
                if ($lesson->progressbar) {
                    $progress = $lesson->calculate_progress();
                }
            }
        }

        $messages = self::format_lesson_messages($lesson);

        $result = array(
            'page' => $pagedata,
            'newpageid' => $pageid,
            'pagecontent' => $pagecontent,
            'ongoingscore' => $ongoingscore,
            'progress' => $progress,
            'contentfiles' => $contentfiles,
            'answers' => $answers,
            'messages' => $messages,
            'warnings' => $warnings,
        );
        return $result;
    }

    /**
     * Describes the get_page_data return value.
     *
     * @return external_single_structure
     * @since Moodle 3.3
     */
    public static function get_page_data_returns() {
        return new external_single_structure(
            array(
                'page' => self::get_page_structure(),
                'newpageid' => new external_value(PARAM_INT, 'New page id (if a jump was made)'),
                'pagecontent' => new external_value(PARAM_RAW, 'Page html content'),
                'ongoingscore' => new external_value(PARAM_TEXT, 'The ongoing messae'),
                'progress' => new external_value(PARAM_INT, 'Progress percentage in the lesson'),
                'contentfiles' => new external_files(),
                'answers' => new external_multiple_structure(
                    new external_single_structure(
                        array(
                            'id' => new external_value(PARAM_INT, 'The ID of this answer in the database'),
                            'answerfiles' => new external_files(),
                            'responsefiles' => new external_files(),
                            'jumpto' => new external_value(PARAM_INT, 'Identifies where the user goes upon completing a page with this answer',
                                                            VALUE_OPTIONAL),
                            'grade' => new external_value(PARAM_INT, 'The grade this answer is worth', VALUE_OPTIONAL),
                            'score' => new external_value(PARAM_INT, 'The score this answer will give', VALUE_OPTIONAL),
                            'flags' => new external_value(PARAM_INT, 'Used to store options for the answer', VALUE_OPTIONAL),
                            'timecreated' => new external_value(PARAM_INT, 'A timestamp of when the answer was created', VALUE_OPTIONAL),
                            'timemodified' => new external_value(PARAM_INT, 'A timestamp of when the answer was modified', VALUE_OPTIONAL),
                        ), 'The page answers'
                    )
                ),
                'messages' => self::external_messages(),
                'warnings' => new external_warnings(),
            )
        );
    }

    /**
     * Describes the parameters for process_page.
     *
     * @return external_external_function_parameters
     * @since Moodle 3.3
     */
    public static function process_page_parameters() {
        return new external_function_parameters (
            array(
                'lessonid' => new external_value(PARAM_INT, 'lesson instance id'),
                'pageid' => new external_value(PARAM_INT, 'the page id'),
                'data' => new external_multiple_structure(
                    new external_single_structure(
                        array(
                            'name' => new external_value(PARAM_RAW, 'data name'),
                            'value' => new external_value(PARAM_RAW, 'data value'),
                        )
                    ), 'the data to be saved'
                ),
                'password' => new external_value(PARAM_RAW, 'optional password (the lesson may be protected)', VALUE_DEFAULT, ''),
                'review' => new external_value(PARAM_RAW, 'if we want to review just after finishing (1 hour margin)', VALUE_DEFAULT, false),
            )
        );
    }

    /**
     * Processes page responses
     *
     * @param int $lessonid lesson instance id
     * @param int $pageid page id
     * @param array $data the data to be saved
     * @param str $password optional password (the lesson may be protected)
     * @param bool $review if we want to review just after finishing (1 hour margin)
     * @return array of warnings and status result
     * @since Moodle 3.3
     * @throws moodle_exception
     */
    public static function process_page($lessonid, $pageid,  $data, $password = '', $review = false) {
        global $DB, $PAGE, $USER;

        $params = array('lessonid' => $lessonid, 'pageid' => $pageid, 'data' => $data, 'password' => $password, 'review' => $review);
        $params = self::validate_parameters(self::process_page_parameters(), $params);

        $warnings = $messages = $contentfiles = $answerfiles = $responsefiles = array();
        $pagecontent = $ongoingscore = '';
        $progress = null;

        list($lesson, $course, $cm, $context) = self::validate_lesson($params['lessonid']);

        // Update timer so the validation can check the time restrictions.
        $timer = $lesson->update_timer();
        self::validate_attempt($lesson, $params);

        // Create the $_POST object required by the lesson question engine.
        $_POST = array();
        foreach ($data as $element) {
            $_POST[$element['name']] = $element['value'];
        }

        // Ignore sesskey (deep in some APIs), the request is already validated.
        $USER->ignoresesskey = true;

        // Process page.
        $page = $lesson->load_page($params['pageid']);
        $result = $lesson->process_page_responses($page);

        // Prepare messages.
        $reviewmode = $lesson->is_in_review_mode();
        $lesson->add_messages_on_page_process($page, $result, $reviewmode);

        // Additional lesson information.
        if (!$lesson->can_manage()) {
            if ($lesson->ongoing && !$reviewmode) {
                $ongoingscore = $lesson->get_ongoing_score_message();
            }
            if ($lesson->progressbar) {
                $progress = $lesson->calculate_progress();
            }
        }

        // Check conditionally everything coming from result (except newpageid because is always set).
        $result = array(
            'newpageid'         => (int) $result->newpageid,
            'nodefaultresponse' => !empty($result->nodefaultresponse),
            'feedback'          => (isset($result->feedback)) ? $result->feedback : '',
            'attemptsremaining' => (isset($result->attemptsremaining)) ? $result->attemptsremaining : null,
            'correctanswer'     => !empty($result->correctanswer),
            'noanswer'          => !empty($result->noanswer),
            'isessayquestion'   => !empty($result->isessayquestion),
            'maxattemptsreached' => !empty($result->maxattemptsreached),
            'response'          => (isset($result->response)) ? $result->response : '',
            'studentanswer'     => (isset($result->studentanswer)) ? $result->studentanswer : '',
            'userresponse'      => (isset($result->userresponse)) ? $result->userresponse : '',
            'reviewmode'        => $reviewmode,
            'ongoingscore'      => $ongoingscore,
            'progress'          => $progress,
            'messages'          => self::format_lesson_messages($lesson),
            'warnings'          => $warnings,
        );
        return $result;
    }

    /**
     * Describes the process_page return value.
     *
     * @return external_single_structure
     * @since Moodle 3.3
     */
    public static function process_page_returns() {
        return new external_single_structure(
            array(
                'newpageid' => new external_value(PARAM_INT, 'New page id (if a jump was made)'),
                'nodefaultresponse' => new external_value(PARAM_BOOL, 'Whether there is not a default response'),
                'feedback' => new external_value(PARAM_RAW, 'The response feedback'),
                'attemptsremaining' => new external_value(PARAM_INT, 'Number of attempts remaining'),
                'correctanswer' => new external_value(PARAM_BOOL, 'Whether the answer is correct'),
                'noanswer' => new external_value(PARAM_BOOL, 'Whether there aren\'t answers'),
                'isessayquestion' => new external_value(PARAM_BOOL, 'Whether is a essay question'),
                'maxattemptsreached' => new external_value(PARAM_BOOL, 'Whether we reachered the max number of attempts'),
                'response' => new external_value(PARAM_RAW, 'The response'),
                'studentanswer' => new external_value(PARAM_RAW, 'The student answer'),
                'userresponse' => new external_value(PARAM_RAW, 'The user response'),
                'reviewmode' => new external_value(PARAM_BOOL, 'Whether the user is reviewing'),
                'ongoingscore' => new external_value(PARAM_TEXT, 'The ongoing messae'),
                'progress' => new external_value(PARAM_INT, 'Progress percentage in the lesson'),
                'messages' => self::external_messages(),
                'warnings' => new external_warnings(),
            )
        );
    }

    /**
     * Describes the parameters for finish_attempt.
     *
     * @return external_external_function_parameters
     * @since Moodle 3.3
     */
    public static function finish_attempt_parameters() {
        return new external_function_parameters (
            array(
                'lessonid' => new external_value(PARAM_INT, 'lesson instance id'),
                'password' => new external_value(PARAM_RAW, 'optional password (the lesson may be protected)', VALUE_DEFAULT, ''),
                'outoftime' => new external_value(PARAM_BOOL, 'if the user run out of time', VALUE_DEFAULT, false),
                'review' => new external_value(PARAM_RAW, 'if we want to review just after finishing (1 hour margin)', VALUE_DEFAULT, false),
            )
        );
    }

    /**
     * Processes page responses
     *
     * @param int $lessonid lesson instance id
     * @param str $password optional password (the lesson may be protected)
     * @param bool $outoftime optional if the user run out of time
     * @param bool $review if we want to review just after finishing (1 hour margin)
     * @return array of warnings and status result
     * @since Moodle 3.3
     * @throws moodle_exception
     */
    public static function finish_attempt($lessonid, $password = '', $outoftime = false, $review = false) {
        global $DB, $PAGE;

        $params = array('lessonid' => $lessonid, 'password' => $password, 'outoftime' => $outoftime, 'review' => $review);
        $params = self::validate_parameters(self::finish_attempt_parameters(), $params);

        $warnings = $messages = $contentfiles = $answerfiles = $responsefiles = array();
        $pagecontent = $ongoingscore = '';
        $progress = null;

        list($lesson, $course, $cm, $context) = self::validate_lesson($params['lessonid']);

        // Update timer so the validation can check the time restrictions.
        $timer = $lesson->update_timer();

        // Return the validation to avoid exception in case out of time.
        $validation = self::validate_attempt($lesson, $params, true);

        if (isset($validation['eolstudentoutoftime'])) {
            // Maybe we run out of time just now.
            $params['outoftime'] = true;
            unset($validation['eolstudentoutoftime']);
        }
        // Check if there are more errors.
        if (!empty($validation)) {
            reset($validation);
            throw new moodle_exception(key($validation), 'lesson', '', current($validation));   // Throw first error.
        }

        $result = $lesson->process_eol_page($params['outoftime']);

        // Return the data.
         $validmessages = array(
            'notenoughtimespent', 'numberofpagesviewed', 'youshouldview', 'numberofcorrectanswers',
            'displayscorewithessays', 'displayscorewithoutessays', 'yourcurrentgradeisoutof', 'eolstudentoutoftimenoanswers',
            'welldone', 'displayofgrade', 'reviewlesson', 'modattemptsnoteacher');

        $data = array();
        foreach ($result as $el => $value) {
            if ($value !== false) {
                $message = '';
                if (in_array($el, $validmessages)) { // Check if the data comes with an informative message.
                    $a = (is_bool($value)) ? null : $value;
                    $message = get_string($el, 'lesson', $a);
                }
                // Return the data.
                $data[] = array(
                    'name' => $el,
                    'value' => (is_bool($value)) ? 1 : json_encode($value), // The data can be a php object.
                    'message' => $message
                );
            }
        }

        // Special cases.
        $progress = null;
        if ($result->progressbar) {
            $progress = $lesson->calculate_progress();
        }

        $result = array(
            'data'     => $data,
            'progress'     => $progress,
            'messages' => self::format_lesson_messages($lesson),
            'warnings' => $warnings,
        );
        return $result;
    }

    /**
     * Describes the finish_attempt return value.
     *
     * @return external_single_structure
     * @since Moodle 3.3
     */
    public static function finish_attempt_returns() {
        return new external_single_structure(
            array(
                'data' => new external_multiple_structure(
                    new external_single_structure(
                        array(
                            'name' => new external_value(PARAM_ALPHANUMEXT, 'data name'),
                            'value' => new external_value(PARAM_RAW, 'data value'),
                            'message' => new external_value(PARAM_RAW, 'data value'),
                        )
                    ), 'The EOL page data'
                ),
                'progress' => new external_value(PARAM_INT, 'Progress percentage in the lesson'),
                'messages' => self::external_messages(),
                'warnings' => new external_warnings(),
            )
        );
    }

    /**
     * Describes the parameters for get_attempts_overview.
     *
     * @return external_external_function_parameters
     * @since Moodle 3.3
     */
    public static function get_attempts_overview_parameters() {
        return new external_function_parameters (
            array(
                'lessonid' => new external_value(PARAM_INT, 'lesson instance id'),
                'groupid' => new external_value(PARAM_INT, 'group id, 0 means that the function will determine the user group',
                                                VALUE_DEFAULT, 0),
            )
        );
    }

    /**
     * Get a list of all the attempts made by users in a lesson.
     *
     * @param int $lessonid lesson instance id
     * @param int $groupid group id, 0 means that the function will determine the user group
     * @return array of warnings and status result
     * @since Moodle 3.3
     * @throws moodle_exception
     */
    public static function get_attempts_overview($lessonid, $groupid = 0) {
        global $DB;

        $params = array('lessonid' => $lessonid, 'groupid' => $groupid);
        $params = self::validate_parameters(self::get_attempts_overview_parameters(), $params);
        $studentsdata = $warnings = array();

        list($lesson, $course, $cm, $context) = self::validate_lesson($params['lessonid']);
        require_capability('mod/lesson:viewreports', $context);

        if (!empty($params['groupid'])) {
            $groupid = $params['groupid'];
            // Determine is the group is visible to user.
            if (!groups_group_visible($groupid, $course, $cm)) {
                throw new moodle_exception('notingroup');
            }
        } else {
            // Check to see if groups are being used here.
            if ($groupmode = groups_get_activity_groupmode($cm)) {
                $groupid = groups_get_activity_group($cm);
                // Determine is the group is visible to user (this is particullary for the group 0 -> all groups).
                if (!groups_group_visible($groupid, $course, $cm)) {
                    throw new moodle_exception('notingroup');
                }
            } else {
                $groupid = 0;
            }
        }

        list($table, $data) = lesson_get_overview_report_table_and_data($lesson, $groupid);
        if ($data !== false) {
            $studentsdata = $data;
        }

        $result = array(
            'data' => $studentsdata,
            'warnings' => $warnings
        );
        return $result;
    }

    /**
     * Describes the get_attempts_overview return value.
     *
     * @return external_single_structure
     * @since Moodle 3.3
     */
    public static function get_attempts_overview_returns() {
        return new external_single_structure(
            array(
                'data' => new external_single_structure(
                    array(
                        'lessonscored' => new external_value(PARAM_BOOL, 'True if the lesson was scored'),
                        'numofattempts' => new external_value(PARAM_INT, 'Number of attempts'),
                        'avescore' => new external_value(PARAM_FLOAT, 'Average score'),
                        'highscore' => new external_value(PARAM_FLOAT, 'High score'),
                        'lowscore' => new external_value(PARAM_FLOAT, 'Low score'),
                        'avetime' => new external_value(PARAM_INT, 'Average time (spent in taking the lesson)'),
                        'hightime' => new external_value(PARAM_INT, 'High time'),
                        'lowtime' => new external_value(PARAM_INT, 'Low time'),
                        'students' => new external_multiple_structure(
                            new external_single_structure(
                                array(
                                    'id' => new external_value(PARAM_INT, 'User id'),
                                    'fullname' => new external_value(PARAM_TEXT, 'User full name'),
                                    'bestgrade' => new external_value(PARAM_FLOAT, 'Best grade'),
                                    'attempts' => new external_multiple_structure(
                                        new external_single_structure(
                                            array(
                                                'try' => new external_value(PARAM_INT, 'Attempt number'),
                                                'grade' => new external_value(PARAM_FLOAT, 'Attempt grade'),
                                                'timestart' => new external_value(PARAM_INT, 'Attempt time started'),
                                                'timeend' => new external_value(PARAM_INT, 'Attempt last time continued'),
                                                'end' => new external_value(PARAM_INT, 'Attempt time ended'),
                                            )
                                        )
                                    )
                                )
                            ), 'Students data, including attempts', VALUE_OPTIONAL
                        ),
                    )
                ),
                'warnings' => new external_warnings(),
            )
        );
    }

    /**
     * Describes the parameters for get_user_attempt.
     *
     * @return external_external_function_parameters
     * @since Moodle 3.3
     */
    public static function get_user_attempt_parameters() {
        return new external_function_parameters (
            array(
                'lessonid' => new external_value(PARAM_INT, 'lesson instance id'),
                'userid' => new external_value(PARAM_INT, 'the user id'),
                'lessonattempt' => new external_value(PARAM_INT, 'the attempt number'),
            )
        );
    }

    /**
     * Return information about the given user attempt (including answers).
     *
     * @param int $lessonid lesson instance id
     * @param int $userid the user id
     * @param int $lessonattempt the attempt number
     * @return array of warnings and page attempts
     * @since Moodle 3.3
     * @throws moodle_exception
     */
    public static function get_user_attempt($lessonid, $userid, $lessonattempt) {
        global $USER;

        $params = array(
            'lessonid' => $lessonid,
            'userid' => $userid,
            'lessonattempt' => $lessonattempt,
        );
        $params = self::validate_parameters(self::get_user_attempt_parameters(), $params);
        $warnings = array();

        list($lesson, $course, $cm, $context) = self::validate_lesson($params['lessonid']);

        // Default value for userid.
        if (empty($params['userid'])) {
            $params['userid'] = $USER->id;
        }

        // Extra checks so only users with permissions can view other users attempts.
        if ($USER->id != $params['userid']) {
            self::check_can_view_user_data($params['userid'], $course, $cm, $context);
        }

        list($answerpages, $userstats) = lesson_get_user_detailed_report_data($lesson, $userid, $params['lessonattempt']);

        $result = array(
            'answerpages' => $answerpages,
            'userstats' => $userstats,
            'warnings' => $warnings,
        );
        return $result;
    }

    /**
     * Describes the get_user_attempt return value.
     *
     * @return external_single_structure
     * @since Moodle 3.3
     */
    public static function get_user_attempt_returns() {
        return new external_single_structure(
            array(
                'answerpages' => new external_multiple_structure(
                    new external_single_structure(
                        array(
                            'title' => new external_value(PARAM_RAW, 'Page title'),
                            'contents' => new external_value(PARAM_RAW, 'Page contents'),
                            'qtype' => new external_value(PARAM_TEXT, 'Identifies the page type of this page'),
                            'grayout' => new external_value(PARAM_INT, 'If is required to apply a grayout'),
                            'answerdata' => new external_single_structure(
                                array(
                                    'score' => new external_value(PARAM_TEXT, 'The score (text version)'),
                                    'response' => new external_value(PARAM_RAW, 'The response text'),
                                    'responseformat' => new external_format_value('response'),
                                    'answers' => new external_multiple_structure(
                                        new external_multiple_structure(new external_value(PARAM_RAW, 'Possible answers and info'))
                                    )
                                ), 'Answer data (empty in content pages from Moodle 1.9)', VALUE_OPTIONAL
                            )
                        )
                    )
                ),
                'userstats' => new external_single_structure(
                    array(
                        'grade' => new external_value(PARAM_FLOAT, 'Attempt final grade'),
                        'completed' => new external_value(PARAM_INT, 'Time completed'),
                        'timetotake' => new external_value(PARAM_INT, 'Time taken'),
                        'gradeinfo' => self::get_user_attempt_grade_structure(VALUE_OPTIONAL)
                    )
                ),
                'warnings' => new external_warnings(),
            )
        );
    }
}

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
 * External comment functions unit tests
 *
 * @package    core_comment
 * @category   external
 * @copyright  2015 Juan Leyva <juan@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      Moodle 2.9
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;

require_once($CFG->dirroot . '/webservice/tests/helpers.php');

/**
 * External comment functions unit tests
 *
 * @package    core_comment
 * @category   external
 * @copyright  2015 Juan Leyva <juan@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      Moodle 2.9
 */
class core_comment_externallib_testcase extends externallib_advanced_testcase {

    /**
     * Tests set up
     */
    protected function setUp() {
        global $CFG, $DB;

        require_once($CFG->dirroot . '/comment/lib.php');

        $CFG->usecomments = true;

        $this->student = $this->getDataGenerator()->create_user();
        $this->course = $this->getDataGenerator()->create_course(array('enablecomment' => 1));
        $studentrole = $DB->get_record('role', array('shortname' => 'student'));
        $this->getDataGenerator()->enrol_user($this->student->id, $this->course->id, $studentrole->id);

        $record = new stdClass();
        $record->course = $this->course->id;
        $record->name = "Mod data  test";
        $record->intro = "Some intro of some sort";
        $record->comments = 1;

        $this->module = $this->getDataGenerator()->create_module('data', $record);
        $field = data_get_field_new('text', $this->module);

        $fielddetail = new stdClass();
        $fielddetail->name = 'Name';
        $fielddetail->description = 'Some name';

        $field->define_field($fielddetail);
        $field->insert_field();
        $this->recordid = data_add_record($this->module);

        $datacontent = array();
        $datacontent['fieldid'] = $field->field->id;
        $datacontent['recordid'] = $this->recordid;
        $datacontent['content'] = 'Asterix';

        $contentid = $DB->insert_record('data_content', $datacontent);
        $this->cm = get_coursemodule_from_instance('data', $this->module->id, $this->course->id);

        $this->context = context_module::instance($this->module->cmid);
    }

    /**
     * Test get_comments
     */
    public function test_get_comments() {
        global $DB;

        $this->resetAfterTest(true);

        $this->setUser($this->student);

        // We need to add the comments manually, the comment API uses the global OUTPUT and this is going to make the WS to fail.
        $newcmt = new stdClass;
        $newcmt->contextid    = $this->context->id;
        $newcmt->commentarea  = 'database_entry';
        $newcmt->itemid       = $this->recordid;
        $newcmt->content      = 'New comment';
        $newcmt->format       = 0;
        $newcmt->userid       = $this->student->id;
        $newcmt->timecreated  = time();
        $cmtid1 = $DB->insert_record('comments', $newcmt);

        $newcmt->content  = 'New comment 2';
        $newcmt->timecreated  = time() + 1;
        $cmtid2 = $DB->insert_record('comments', $newcmt);

        $contextlevel = 'module';
        $instanceid = $this->cm->id;
        $component = 'mod_data';
        $itemid = $this->recordid;
        $area = 'database_entry';
        $page = 0;

        $result = core_comment_external::get_comments($contextlevel, $instanceid, $component, $itemid, $area, $page);
        // We need to execute the return values cleaning process to simulate the web service server.
        $result = external_api::clean_returnvalue(
            core_comment_external::get_comments_returns(), $result);

        $this->assertCount(0, $result['warnings']);
        $this->assertCount(2, $result['comments']);
        $this->assertTrue($result['canpost']);

        $this->assertEquals($this->student->id, $result['comments'][0]['userid']);
        $this->assertEquals($this->student->id, $result['comments'][1]['userid']);

        $this->assertEquals($cmtid2, $result['comments'][0]['id']);
        $this->assertEquals($cmtid1, $result['comments'][1]['id']);
    }

    /**
     * Test add_comment not enabled site level
     */
    public function test_add_comment_not_enabled_site_level() {
        global $CFG;
        $this->resetAfterTest(true);

        $CFG->usecomments = false;
        $this->setUser($this->student);
        $this->expectException(comment_exception::class);
        core_comment_external::add_comment('module', $this->cm->id, 'mod_data', 'abc', $this->recordid, 'database_entry');
    }

    /**
     * Test add_comment not enabled module level
     */
    public function test_add_comment_not_enabled_module_level() {
        global $DB;
        $this->resetAfterTest(true);

        $DB->set_field('data', 'comments', 0, array('id' => $this->module->id));
        $this->setUser($this->student);
        $this->expectException(comment_exception::class);
        core_comment_external::add_comment('module', $this->cm->id, 'mod_data', 'abc', $this->recordid, 'database_entry');
    }

    /**
     * Test add_comment
     */
    public function test_add_comment() {
        global $DB;
        $this->resetAfterTest(true);
        $this->setUser($this->student);

        $content = 'abc';
        $result = external_api::clean_returnvalue(
            core_comment_external::add_comment_returns(),
            core_comment_external::add_comment('module', $this->cm->id, 'mod_data', $content, $this->recordid, 'database_entry')
        );
        $this->assertNotEquals(0, $result['commentid']);

        $comments = external_api::clean_returnvalue(
            core_comment_external::get_comments_returns(),
            core_comment_external::get_comments('module', $this->cm->id, 'mod_data', $this->recordid, 'database_entry')
        );
        $this->assertEquals($result['commentid'], $comments['comments'][0]['id']);
        $this->assertContains($content, $comments['comments'][0]['content']);
        $this->assertEquals($this->student->id, $comments['comments'][0]['userid']);
    }

    /**
     * Test add_comment invalid area
     */
    public function test_add_comment_invalid_area() {
        global $DB;
        $this->resetAfterTest(true);
        $this->setUser($this->student);

        $content = 'abc';
        $this->expectException(comment_exception::class);
        core_comment_external::add_comment('module', $this->cm->id, 'mod_data', $content, $this->recordid, 'bad_area');
    }
}

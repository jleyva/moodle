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
 * Unit tests for the question type base class.
 *
 * @package    moodlecore
 * @subpackage questiontypes
 * @copyright  2008 The Open University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/question/type/questiontypebase.php');


/**
 * Tests for some of ../questionbase.php
 *
 * @copyright  2008 The Open University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class question_type_testcase extends advanced_testcase {
    public static $includecoverage = array('question/type/questiontypebase.php');

    public function test_save_question_name() {
        $this->resetAfterTest();

        $questiongenerator = $this->getDataGenerator()->get_plugin_generator('core_question');
        $cat = $questiongenerator->create_question_category(array());

        $saq = $questiongenerator->create_question('shortanswer', null,
                array('category' => $cat->id, 'name' => 'Test question'));
        $actual = question_bank::load_question_data($saq->id);

        $this->assertSame('Test question', $actual->name);
    }

    public function test_save_question_zero_name() {
        $this->resetAfterTest();

        $questiongenerator = $this->getDataGenerator()->get_plugin_generator('core_question');
        $cat = $questiongenerator->create_question_category(array());

        $saq = $questiongenerator->create_question('shortanswer', null,
                array('category' => $cat->id, 'name' => '0'));
        $actual = question_bank::load_question_data($saq->id);

        $this->assertSame('0', $actual->name);
    }

    /**
     * Test is_plain_html
     */
    public function test_is_plain_html() {
        $q = question_bank::get_qtype('calculated');
        $this->assertTrue($q->is_plain_html());

        $q = question_bank::get_qtype('calculatedmulti');
        $this->assertTrue($q->is_plain_html());

        $q = question_bank::get_qtype('calculatedsimple');
        $this->assertTrue($q->is_plain_html());

        $q = question_bank::get_qtype('ddimageortext');
        $this->assertFalse($q->is_plain_html());

        $q = question_bank::get_qtype('ddmarker');
        $this->assertFalse($q->is_plain_html());

        $q = question_bank::get_qtype('ddwtos');
        $this->assertFalse($q->is_plain_html());

        $q = question_bank::get_qtype('description');
        $this->assertTrue($q->is_plain_html());

        $q = question_bank::get_qtype('essay');
        $this->assertTrue($q->is_plain_html());

        $q = question_bank::get_qtype('gapselect');
        $this->assertTrue($q->is_plain_html());

        $q = question_bank::get_qtype('match');
        $this->assertTrue($q->is_plain_html());

        $q = question_bank::get_qtype('missingtype');
        $this->assertFalse($q->is_plain_html());

        $q = question_bank::get_qtype('multianswer');
        $this->assertTrue($q->is_plain_html());

        $q = question_bank::get_qtype('multichoice');
        $this->assertTrue($q->is_plain_html());

        $q = question_bank::get_qtype('numerical');
        $this->assertTrue($q->is_plain_html());

        $q = question_bank::get_qtype('random');
        $this->assertFalse($q->is_plain_html());

        $q = question_bank::get_qtype('randomsamatch');
        $this->assertFalse($q->is_plain_html());

        $q = question_bank::get_qtype('shortanswer');
        $this->assertTrue($q->is_plain_html());

        $q = question_bank::get_qtype('truefalse');
        $this->assertTrue($q->is_plain_html());
    }
}

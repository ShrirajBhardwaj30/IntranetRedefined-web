<?php
// This file is part of Wiziq - http://www.wiziq.com/
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
 * The main wiziq configuration form
 *
 * It uses the standard core Moodle formslib. For more info about them, please
 * visit: http://docs.moodle.org/en/Development:lib/formslib.php
 *
 * @package    mod_wiziq
 * @copyright  www.wiziq.com
 * @author     dinkar@wiziq.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();
define('WIZIQ_ALLOWED_DIFFRENCE', 300);
define('WIZIQ_MINIMUM_DURATION', 30);
define('WIZIQ_MAXIMUM_DURATION', 300);
global $CFG;
require_once(dirname(dirname(dirname(__FILE__))).'/config.php');
require_once(dirname(__FILE__).'/lib.php');
require_once($CFG->dirroot.'/course/moodleform_mod.php');
require_once('locallib.php');
require_once('lib.php');
require_once($CFG->dirroot.'/lib/dml/moodle_database.php');

/**
 * The main wiziq configuration class.
 * 
 * Module instance settings form. This class inherits the moodleform_mod class to 
 * create the moodle form for wiziq.
 * @copyright  www.wiziq.com
 * @author     dinkar@wiziq.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_wiziq_mod_form extends moodleform_mod {
    /**
     * Defines the structure for wiziq mod_form.
     */
    public function definition() {
        /* @var $COURSE type */
        global $CFG, $OUTPUT, $COURSE, $USER, $DB;
        $mform = $this->_form;
        $schedulenewwiziqclass = html_writer::link(
                new moodle_url("$CFG->wwwroot/course/modedit.php",
                array('add' => 'wiziq', 'type' => '', 'course' => $COURSE->id,
                    'section' => '0', 'return' => '0')),
                get_string('schedule_class', 'wiziq'));
        $navigation_tabs_manage = html_writer::link(
                new moodle_url("$CFG->wwwroot/mod/wiziq/index.php",
                array('id' => $COURSE->id, 'sesskey' => sesskey())),
                get_string('manage_classes', 'wiziq'));
        $navigation_tabs_content = html_writer::link(
                new moodle_url("$CFG->wwwroot/mod/wiziq/content.php",
                array('id' => $COURSE->id, 'sesskey' => sesskey())),
                get_string('manage_content', 'wiziq'));
        $table_html_p1 = '<table>'.'<tr><th>'.$schedulenewwiziqclass.'</a></th><th>|</th>';
        $table_html_p2 ='<th>'.$navigation_tabs_manage.'</th><th>|</th>';
        $table_html_p3 ='<th>'.$navigation_tabs_content.'</th></tr>';
        $table_html = $table_html_p1.$table_html_p2.$table_html_p3;
        $mform->addElement('html', $table_html);
        $mform->addElement('html', '</td></tr></table>');
        //-------------------------------------------------------------------------------
        // Adding the "general" fieldset, where all the common settings are showed
        $mform->addElement('header', 'general', get_string('general', 'form'));
        // Adding the standard "name" field
        $mform->addElement('text', 'name', get_string('wiziqname', 'wiziq'), array('size'=>'64'));
        if (!empty($CFG->formatstringstriptags)) {
            $mform->setType('name', PARAM_TEXT);
        } else {
            $mform->setType('name', PARAM_RAW);
        }
        $mform->addElement('hidden', 'class_id', "");
        $mform->setType('class_id', PARAM_INT);
        $mform->addElement('hidden', 'lasteditorid', "");
        $mform->setType('lasteditorid', PARAM_INT);
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->addRule('name', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');
        $mform->addHelpButton('name', 'wiziqname', 'wiziq');
        // Adding the standard "intro" and "introformat" fields
        $this->add_intro_editor();
        //-------------------------------------------------------------------------------
        // Adding the rest of wiziq settings, spreeading all them into this fieldset
        // or adding more fieldsets ('header' elements) if needed for better logic
        $mform->addElement('header', 'wiziqdatetimesetting',
                get_string('wiziqdatetimesetting', 'wiziq'));
        $vctime = wiziq_timezone();
        $wiziq_timezone_select = $mform->addElement('select', 'wiziq_timezone',
                get_string('vc_class_timezone', 'wiziq'), $vctime);
        if (isset($_COOKIE['wiziq_vctimezone'])) {
            $wiziq_vc_timezone_cookie = $_COOKIE['wiziq_vctimezone'];
            $wiziq_timezone_select->setSelected($wiziq_vc_timezone_cookie);
        }
        $mform->addHelpButton('wiziq_timezone', 'vc_class_timezone', 'wiziq');
        $mform->addElement('checkbox', 'schedule_for_now', get_string('schedule_for_now', 'wiziq'));
        $mform->setDefault('schedule_for_now', false);
        $mform->addHelpButton('schedule_for_now', 'schedule_for_now', 'wiziq');
        $mform->addElement('hidden', 'timenow', time());
        $mform->setType('timenow', PARAM_INT);
        $dtoption = array(
                'startyear' => 1970,
                'stopyear'  => 2020,
                'timezone'  => 99,
                'applydst'  => true,
                'step'      => 1,
                'optional' => false
            );
        $mform->addelement('date_time_selector', 'wiziq_datetime',
                get_string('wiziq_datetime', 'wiziq'), $dtoption);
        $mform->addHelpButton('wiziq_datetime', 'wiziq_datetime', 'wiziq');
        $mform->disabledIf('wiziq_datetime', 'schedule_for_now', 'checked');
        $mform->addElement('text', 'duration', get_string('wiziq_duration', 'wiziq'));
        $mform->setType('duration', PARAM_INT);
        $mform->addRule('duration', get_string('duration_req', 'wiziq'),
                'required', null, 'client', true);
        $mform->addRule('duration', get_string('duration_number', 'wiziq'),
                'numeric', null, 'client');
        $mform->setDefault('duration', 30);
        $mform->addHelpButton('duration', 'duration', 'wiziq');
        $mform->addElement('header', 'wiziqclasssettings',
                get_string('wiziqclasssettings', 'wiziq'));
        $vclang = wiziq_languagexml();
        $wiziq_language_select = $mform->addElement('select', 'vc_language',
                get_string('vc_language', 'wiziq'), $vclang);
        if (isset($_COOKIE['wiziq_vclanguage'])) {
            $wiziq_vc_cookie = $_COOKIE['wiziq_vclanguage'];
            $wiziq_language_select->setSelected($wiziq_vc_cookie);
        }
        $mform->addHelpButton('vc_language', 'vc_language', 'wiziq');
        $recordingtype = array();
        $recordingtype[] = $mform->createElement('radio', 'recording', '',
                get_string('record', 'wiziq'), 1);
        $recordingtype[] = $mform->createElement('radio', 'recording', '',
                get_string('dontrecord', 'wiziq'), 0);
        $mform->setDefault('recording', 1);
        $mform->addGroup($recordingtype, 'recordingtype',
                get_string('recording_option', 'wiziq'), array(' '), false);
        $mform->addHelpButton('recordingtype', 'recordingtype', 'wiziq');
        //TODO: Import list of teacher for this course --- done
        $courseid = $COURSE->id;
        if (is_siteadmin($USER->id)) {
            $teacherdetail = wiziq_getteacherdetail($courseid);
            if (!empty($teacherdetail)) {
                $mform->addElement('checkbox', 'scheduleforother',
                        get_string('scheduleforother', 'wiziq'));
                $mform->setDefault('scheduleforother', false);
                $mform->addHelpButton('scheduleforother', 'scheduleforother', 'wiziq');
                $teacher = array();
                $teacher['select'] = '[select]';
                foreach ($teacherdetail as $value) {
                    $teacher[$value->id] = $value->username;
                }
                $mform->addElement('select', 'presenter_id',
                        get_string('presenter_id', 'wiziq'), $teacher);
                $mform->disabledIf('presenter_id', 'scheduleforother', 'notchecked');
            }
        }
        //-------------------------------------------------------------------------------
        // add standard elements, common to all modules
        $this->standard_coursemodule_elements();
        //-------------------------------------------------------------------------------
        // add standard buttons, common to all modules
        $this->add_action_buttons();
    }

    /**
     * Validates the data input from various input elements.
     * 
     * @param string $data
     * @param string $files
     * 
     * @return string $errors
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);
        if (empty($data['name'])) {
            $errors['name'] = get_string('namerequired', 'wiziq');
        }
        if ($data['wiziq_timezone'] == 'select') {
            $errors['wiziq_timezone'] = get_string('timezone_required', 'wiziq');
        }
        if (array_key_exists('presenter_id', $data)) {
            if ($data['presenter_id'] == 'select') {
                $errors['presenter_id'] = get_string('presenter_required', 'wiziq');
            }
        }
        if (array_key_exists('schedule_for_now', $data)) {
            if ($data['schedule_for_now'] == true) {
                $data['wiziq_datetime'] = $data['timenow'];
            }
        }
        if ($data['wiziq_datetime'] < $data['timenow']) {
            $errors['wiziq_datetime'] = get_string('wrongtime', 'wiziq');
        }
        $wiziq_duration_maxcheck = WIZIQ_MAXIMUM_DURATION < $data['duration'];
        $wiziq_duration_mincheck = $data['duration'] < WIZIQ_MINIMUM_DURATION;
        if ($wiziq_duration_maxcheck || $wiziq_duration_mincheck) {
            $errors['duration'] = get_string('wrongduration', 'wiziq');
        }
        $vc_languagecookie = $data['vc_language'];
        setcookie('wiziq_vclanguage', $vc_languagecookie, time()+(86400 * 365));//86400  = `1 day
        $vc_timezonecookie = $data['wiziq_timezone'];
        setcookie('wiziq_vctimezone', $vc_timezonecookie, time()+(86400 * 365));//86400  = 1 day
        return $errors;
    }
}

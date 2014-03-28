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
 * Prints a particular instance of wiziq
 *
 * You can have a rather longer description of the file as well,
 * if you like, and it can span multiple lines.
 *
 * @package    mod_wiziq
 * @copyright  www.wiziq.com 
 * @author     dinkar@wiziq.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('WIZIQ_HEIGHT', 786);
define('WIZIQ_WIDTH', 1024);
require_once(dirname(dirname(dirname(__FILE__))).'/config.php');
require_once(dirname(__FILE__).'/lib.php');
global $CFG, $USER, $OUTPUT, $PAGE;
$id = optional_param('id', 0, PARAM_INT); // course_module ID
// wiziq instance ID - it should be named as the first character of the module
$w  = optional_param('w', 0, PARAM_INT);
if ($id) {
    $cm         = get_coursemodule_from_id('wiziq', $id, 0, false, MUST_EXIST);
    $course     = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
    $wiziq  = $DB->get_record('wiziq', array('id' => $cm->instance), '*', MUST_EXIST);
} else if ($w) {
    $wiziq  = $DB->get_record('wiziq', array('id' => $w), '*', MUST_EXIST);
    $course = $DB->get_record('course', array('id' => $wiziq->course), '*', MUST_EXIST);
    $cm = get_coursemodule_from_instance('wiziq', $wiziq->id, $course->id, false, MUST_EXIST);
} else {
    error('You must specify a course_module ID or an instance ID');
}
require_login($course, true, $cm);
$context = get_context_instance(CONTEXT_MODULE, $cm->id);
add_to_log($course->id, 'wiziq', 'view', "view.php?id={$cm->id}", $wiziq->name, $cm->id);
//Print the page header

$PAGE->set_url('/mod/wiziq/view.php', array('id' => $cm->id));
$PAGE->set_title(format_string($wiziq->name));
$pagetitle = get_string('wiziq_class', 'wiziq');
$pagetitlename = $pagetitle." ".$wiziq->name;
$PAGE->set_heading(format_string($pagetitlename));
$PAGE->set_context($context);

//------- print output----
echo $OUTPUT->header();

//-------html table just to make the navigation tabs--------
$schedulenewwiziqclass = new moodle_url("$CFG->wwwroot/course/modedit.php",
            array('add' => 'wiziq', 'type' => '', 'course' => $course->id,
                  'section' => '0', 'return' => '0'));
    $navigationtabsmanage = new moodle_url("$CFG->wwwroot/mod/wiziq/index.php",
            array('id' =>  $course->id, 'sesskey' => sesskey()));
    $navigationtabscontent = new moodle_url("$CFG->wwwroot/mod/wiziq/content.php",
            array('id' => $course->id, 'sesskey' => sesskey()));
    $tabs =array();
    $row = array();
    $row[] = new tabobject('wiziq_sch_class', $schedulenewwiziqclass,
            get_string('schedule_class', 'wiziq'));
    $row[] = new tabobject('wizq_mange_class', $navigationtabsmanage,
            get_string('manage_classes', 'wiziq'));
    $row[] = new tabobject('wizq_mange_content', $navigationtabscontent,
            get_string('manage_content', 'wiziq'));
    $tabs[]=$row;
    print_tabs($tabs);
//------- Get details of the class---
$class_id = $wiziq->class_id;
$session = $wiziq->insescod;
if (!isset($class_id)) {
    wiziq_get_data_by_sessioncode($course->id, $session, $class_id, $wiziq->id, $presenter_id,
                                  $presenter_name, $presenter_url, $start_time, $time_zone,
                                  $create_recording, $status, $language_culture_name,
                                  $duration, $recording_url);
    $wiziq->class_status = $status;
    $wiziq->class_id = $class_id;
} else {
    wiziq_get_data($course->id, $class_id, $presenter_id, $presenter_name, $presenter_url,
                   $start_time, $time_zone, $create_recording, $status,
                   $language_culture_name, $duration, $recording_url);
}
//-----actual description table starts form here-----------

$viewtable = new html_table();
$class_details = $wiziq->name;
$viewtable->head = array($class_details);
$viewtable->headspan = array(2, 1);
$presenter = get_string('presenter_name', 'wiziq');
if ($presenter_id == $USER->id) {
    $teacher_you = get_string('teacher_you', 'wiziq');
    $presenter_namedisplay = $teacher_you;
} else {
    $presenter_namedisplay = $presenter_name;
}
$viewrow2 = array($presenter, $presenter_namedisplay);
$status_of_class = get_string('status_of_class', 'wiziq');
$viewrow3 = array($status_of_class, $status);
if ($wiziq->class_status != $status) {
    $updates = new stdClass(); //just enough data for updating the submission
    $updates->id = $wiziq->id;
    $updates->class_status = $status;
    $DB->update_record('wiziq', $updates);
}
$wiziq_class_time = get_string('wiziq_start_time', 'wiziq');
$viewrow4 = array($wiziq_class_time, $start_time);

$wiziq_class_timezone = get_string('wiziq_class_timezone', 'wiziq');
$viewrow5 = array($wiziq_class_timezone, $time_zone);

$duration_class = get_string('wiziq_duration', 'wiziq');
$viewrow6 = array($duration_class, $duration);

$language_name = get_string('language_name', 'wiziq');
$viewrow7 = array($language_name, $language_culture_name);
$create_recording = ltrim(rtrim($create_recording));
if ($create_recording == 'true') {
    $create_recording = get_string('create_recording_true', 'wiziq');
} else if ($create_recording == null) {
    $create_recording = "";
} else {
    $create_recording = get_string('create_recording_false', 'wiziq');
}
$recording_value = get_string('recording_value', 'wiziq');
$viewrow7 = array($recording_value, $create_recording);


$viewtable->data = array($viewrow2, $viewrow3, $viewrow4, $viewrow5, $viewrow6, $viewrow7);
echo html_writer::table($viewtable);

//-------- row to make button visible-----
$buttonrow = new html_table_row();
$buttonrowcell_1 = new html_table_cell();
$statusmsg = ltrim(rtrim($status));
if ($statusmsg == 'upcoming') {
    if ($presenter_id == $USER->id) {
        if (!empty($presenter_url)) {
            $classlink = $presenter_url;
            $wiziq_linkname = get_string('launch_class', 'wiziq');
        } else {
            $classlink = '';
        }
    } else {
        $attendee_url = "";
        wiziq_get_data_attendee($class_id, $USER->id, $attendee_url);
        if (empty($attendee_url)) {
            $attendee_screen_name = "$USER->firstname"." "."$USER->lastname";
            wiziq_addattendee($course->id, $class_id, $USER->id, $attendee_screen_name,
                    $language_culture_name, $attendee_url, $errormsg);
        }
        if (!empty($attendee_url)) {
            $classlink = $attendee_url;
            $wiziq_linkname = get_string('join_class', 'wiziq');
        } else if (!empty ($errormsg)) {
            $classlink = '';
        }
    }
    if (!empty($classlink)) {
        $class_url = new moodle_url($classlink);
        $action = new popup_action('click', $class_url, "class_name",
                array('height' => WIZIQ_HEIGHT, 'width' => WIZIQ_WIDTH));
        $join = $OUTPUT->action_link($class_url, $wiziq_linkname, $action,
                array('title'=>get_string('modulename', 'wiziq')));
    } else {
        $join = get_string('unable_to_get_url', 'wiziq');
    }
    $buttonrowcell_1->text = $join;

    $buttonrowcell_1_style = 'text-align:center; border:0; margin-top:12px; float:left';
    $buttonrowcell_1->style = $buttonrowcell_1_style;

    #-----code to update/edit the class------
    $update = html_writer::link(
            new moodle_url("$CFG->wwwroot/course/mod.php",
                    array('update' => $cm->id, 'return' => true, 'sesskey' => sesskey())),
            get_string('update_class', 'wiziq'));
    $buttonrowcell_2 = new html_table_cell();
    $buttonrowcell_2->text = $update;
    $buttonrowcell_2_style = 'text-align:center; border:0; margin-top:12px; float:left';
    $buttonrowcell_2->style = $buttonrowcell_2_style;
} else if ($statusmsg == 'completed' && $create_recording == 'Yes') {
        $download_recording = get_string('download_recording', 'wiziq');
        $view_recording = get_string('view_recording', 'wiziq');
            $viewrec_url = new moodle_url($recording_url);
            $action = new popup_action('click', $viewrec_url, "view_recording",
                    array('height' => WIZIQ_HEIGHT, 'width' => WIZIQ_WIDTH));
            $view_recording_link = $OUTPUT->action_link($viewrec_url, $view_recording, $action,
                    array('title' => get_string('modulename', 'wiziq')));
        wiziq_downloadrecording($course->id, $wiziq->class_id, $download_recording_link, $errormsg);
    if ($download_recording_link != null) {
        $dnld_rec = html_writer::link( new moodle_url($download_recording_link),
                    $download_recording);
    } else if ($errormsg != null) {
        $dnld_rec = $errormsg;// error returned by api
    } else {
        $dnld_rec = get_string('creatingrecording', 'wiziq');
        $view_recording_link = $view_recording_link;
    }
    $buttonrowcell_6 = new html_table_cell();
    $buttonrowcell_6->text = $dnld_rec;
    $buttonrowcell_6_style = 'text-align:center; border:0; margin-top:12px; float:left';
    $buttonrowcell_6->style = $buttonrowcell_6_style;

    $buttonrowcell_7 = new html_table_cell();
    $buttonrowcell_7->text = $view_recording_link;
    $buttonrowcell_7_style = 'text-align:center; border:0; margin-top:12px; float:left';
    $buttonrowcell_7->style = $buttonrowcell_7_style;

} else if ($statusmsg == 'completed' && $create_recording == 'No') {
    $withoutrec = get_string('classwithoutrec', 'wiziq');
    $buttonrowcell_8 = new html_table_cell();
    $buttonrowcell_8->text = $withoutrec;
    $buttonrowcell_8_style = 'text-align:center; border:0; margin-top:12px; float:left';
    $buttonrowcell_8->style = $buttonrowcell_8_style;
} else {
    $buttonrowcell_5 = new html_table_cell();
    $classnotheld = get_string('viewclassnotheld', 'wiziq');
    $buttonrowcell_5->text = $classnotheld;
    $buttonrowcell_5_style = 'text-align:center; border:0; margin-top:12px; float:left';
    $buttonrowcell_5->style = $buttonrowcell_5_style;
}
#-----code to delete the class------
$deleteclass = html_writer::link(
        new moodle_url("$CFG->wwwroot/course/mod.php",
                array('delete' => $cm->id, 'return' => true, 'sesskey' => sesskey())),
        get_string('delete_class', 'wiziq'));
$buttonrowcell_3 = new html_table_cell();
$buttonrowcell_3->text = $deleteclass;
$buttonrowcell_3_style = 'text-align:center; border:0;margin-top:12px; float:left';
$buttonrowcell_3->style = $buttonrowcell_3_style;
#-----code for attendence report the class------
$attendencereport = html_writer::link(
        new moodle_url("$CFG->wwwroot/mod/wiziq/attendancereport.php",
                array('id' => $wiziq->course, 'classid' => $wiziq->class_id,
                      'sesskey' => sesskey())),
        get_string('attendencereport', 'wiziq'));
$buttonrowcell_9 = new html_table_cell();
$buttonrowcell_9->text = $attendencereport;
$buttonrowcell_9_style = 'text-align:center; border:0;margin-top:12px; float:left';
$buttonrowcell_9->style = $buttonrowcell_9_style;

$buttonrowcell_4 = new html_table_cell();
$buttonrowcell_4->text = '|';
$buttonrowcell_4_style = 'text-align:center; border:0;margin-top:12px; float:left';
$buttonrowcell_4->style = $buttonrowcell_4_style;
if ((is_siteadmin()) || ($presenter_id == $USER->id)) {
    if ($statusmsg == 'upcoming') {
        $buttonrow->cells = array($buttonrowcell_1, $buttonrowcell_4,
            $buttonrowcell_2, $buttonrowcell_4, $buttonrowcell_3);
    } else if ($statusmsg == 'completed' && $create_recording == 'Yes') {
            $hascapatt = has_capability('mod/wiziq:view_attendance_report', $context);
            $hascapdwnrec = has_capability('mod/wiziq:wiziq_download_rec', $context);
            $hascapvwrec = has_capability('mod/wiziq:wiziq_view_rec', $context);
        if ($hascapatt && $hascapdwnrec && $hascapvwrec) {
            $buttonrow->cells = array($buttonrowcell_3, $buttonrowcell_4,
                $buttonrowcell_6, $buttonrowcell_4, $buttonrowcell_7,
                $buttonrowcell_4, $buttonrowcell_9);
        } else if ($hascapdwnrec && $hascapvwrec) {
            $buttonrow->cells = array($buttonrowcell_3, $buttonrowcell_4,
                $buttonrowcell_6, $buttonrowcell_4, $buttonrowcell_7);
        } else if ($hascapvwrec && $hascapatt) {
            $buttonrow->cells = array($buttonrowcell_3, $buttonrowcell_4,
                $buttonrowcell_7, $buttonrowcell_4, $buttonrowcell_9);
        } else if ($hascapatt && $hascapdwnrec) {
            $buttonrow->cells = array($buttonrowcell_3, $buttonrowcell_4,
                $buttonrowcell_6, $buttonrowcell_4, $buttonrowcell_9);
        } else if ($hascapatt && (!$hascapdwnrec) &&(!$hascapvwrec)) {
            $buttonrow->cells = array($buttonrowcell_3, $buttonrowcell_4, $buttonrowcell_9);
        } else if ((!$hascapatt) && ($hascapdwnrec) &&(!$hascapvwrec)) {
            $buttonrow->cells = array($buttonrowcell_3, $buttonrowcell_4, $buttonrowcell_6);
        } else if ((!$hascapatt) && (!$hascapdwnrec) &&($hascapvwrec)) {
            $buttonrow->cells = array($buttonrowcell_3, $buttonrowcell_4, $buttonrowcell_7);
        } else if ((!$hascapatt) && (!$hascapdwnrec) &&(!$hascapvwrec)) {
            $buttonrow->cells = array($buttonrowcell_3);
        }
    } else if ($statusmsg == 'completed' && $create_recording == 'No') {
        if (has_capability('mod/wiziq:view_attendance_report', $context)) {
                $buttonrow->cells = array($buttonrowcell_3, $buttonrowcell_4,
                    $buttonrowcell_8, $buttonrowcell_4, $buttonrowcell_9);
        } else {
            $buttonrow->cells = array($buttonrowcell_3, $buttonrowcell_4, $buttonrowcell_8);
        }
    } else if ($statusmsg == 'Deleted form WizIQ') {
            $buttonrow->cells = array($buttonrowcell_3);
    } else {
        $buttonrow->cells = array($buttonrowcell_3, $buttonrowcell_4, $buttonrowcell_5);
    }
} else { // No delete access to student only admin and presenter have that right
    if ($statusmsg == 'upcoming') {
        $buttonrow->cells = array($buttonrowcell_1, $buttonrowcell_4,
            $buttonrowcell_2);
    } else if ($statusmsg == 'completed' && $create_recording == 'Yes') {
            $hascapatt = has_capability('mod/wiziq:view_attendance_report', $context);
            $hascapdwnrec = has_capability('mod/wiziq:wiziq_download_rec', $context);
            $hascapvwrec = has_capability('mod/wiziq:wiziq_view_rec', $context);
        if ($hascapatt && $hascapdwnrec && $hascapvwrec) {
            $buttonrow->cells = array($buttonrowcell_6, $buttonrowcell_4, $buttonrowcell_7,
                $buttonrowcell_4, $buttonrowcell_9);
        } else if ($hascapdwnrec && $hascapvwrec) {
            $buttonrow->cells = array($buttonrowcell_6, $buttonrowcell_4, $buttonrowcell_7);
        } else if ($hascapvwrec && $hascapatt) {
            $buttonrow->cells = array($buttonrowcell_7, $buttonrowcell_4, $buttonrowcell_9);
        } else if ($hascapatt && $hascapdwnrec) {
            $buttonrow->cells = array($buttonrowcell_6, $buttonrowcell_4, $buttonrowcell_9);
        } else if ($hascapatt && (!$hascapdwnrec) &&(!$hascapvwrec)) {
            $buttonrow->cells = array($buttonrowcell_9);
        } else if ((!$hascapatt) && ($hascapdwnrec) &&(!$hascapvwrec)) {
            $buttonrow->cells = array($buttonrowcell_6);
        } else if ((!$hascapatt) && (!$hascapdwnrec) &&($hascapvwrec)) {
            $buttonrow->cells = array($buttonrowcell_7);
        } else if ((!$hascapatt) && (!$hascapdwnrec) &&(!$hascapvwrec)) {
            $buttonrow->cells = array();
        }
    } else if ($statusmsg == 'completed' && $create_recording == 'No') {
        if (has_capability('mod/wiziq:view_attendance_report', $context)) {
                $buttonrow->cells = array($buttonrowcell_8, $buttonrowcell_4, $buttonrowcell_9);
        } else {
            $buttonrow->cells = array($buttonrowcell_8);
        }
    } else if ($statusmsg == 'Deleted form WizIQ') {
            $buttonrow->cells = array();
    } else {
        $buttonrow->cells = array($buttonrowcell_5);
    }
}
$buttontable = new html_table();
$buttontable->attributes = array("border"=>0);
$buttontable->data = array($buttonrow);
echo html_writer::table($buttontable);
if ($wiziq->intro) {
    // Conditions to show the intro can change to look for own settings or whatever
    echo $OUTPUT->heading(get_string('discription', 'wiziq'));
    echo $OUTPUT->box(format_module_intro('wiziq', $wiziq, $cm->id),
            'generalbox mod_introbox', 'wiziqintro');
}
echo "<br />";
if ($statusmsg == 'completed' && $create_recording == 'Yes' && $download_recording_link == null) {
    notice(get_string('recmsg', 'wiziq'),
            new moodle_url('/course/view.php',
                    array('id' => $course->id)));
}
echo $OUTPUT->footer();

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
 * Library of interface functions and constants for module wiziq
 *
 * All the core Moodle functions, neeeded to allow the module to work
 * integrated in Moodle should be placed here.
 * All the wiziq specific functions, needed to implement all the module
 * logic, should go to locallib.php. This will help to save some memory when
 * Moodle is performing actions across all modules.
 *
 * @package    mod_wiziq
 * @copyright  www.wiziq.com 
 * @author     dinkar@wiziq.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();
define('WIZIQ_TOMINUTES', 60);
global $CFG;
require_once($CFG->dirroot.'/calendar/lib.php');
require_once('locallib.php');
/**
 * Defines the features that are supported by wiziq.
 *
 * @param string $feature FEATURE_xx constant for requested feature
 * @return mixed True if module supports feature, false if not, null if doesn't know
 */
function wiziq_supports($feature) {
    switch($feature) {
        case FEATURE_GROUPS:                  return true;
        case FEATURE_GROUPINGS:               return true;
        case FEATURE_GROUPMEMBERSONLY:        return true;
        case FEATURE_MOD_INTRO:               return true;
        case FEATURE_COMPLETION_TRACKS_VIEWS: return false;
        case FEATURE_GRADE_HAS_GRADE:         return false;
        case FEATURE_GRADE_OUTCOMES:          return false;
        case FEATURE_BACKUP_MOODLE2:          return true;

        default: return null;
    }
}

/**
 * Saves a new instance of the wiziq into the database
 *
 * Given an object containing all the necessary data,
 * (defined by the form in mod_form.php) this function
 * will create a new instance and return the id number
 * of the new instance.
 *
 * @param object $wiziq An object from the form in mod_form.php
 * 
 * @return int The id of the newly inserted wiziq record
 */
function wiziq_add_instance(stdClass $wiziq, mod_wiziq_mod_form $mform = null) {
    global $CFG, $DB, $USER;
    $wiziq_webserviceurl= $CFG->wiziq_webserviceurl;
    $wiziq_access_key= $CFG->wiziq_access_key;
    $wiziq_secretacesskey = $CFG->wiziq_secretacesskey;
    $wiziq->timecreated = time();
    if (property_exists($wiziq, 'schedule_for_now')) {
        if ($wiziq->schedule_for_now == true) {
            $wiziq->wiziq_datetime = $wiziq->timenow;
        }
    }
    if (property_exists($wiziq, 'scheduleforother')) {
        if ($wiziq->scheduleforother == true) {
            $userid = $wiziq->presenter_id;
            $userfirstname = $DB->get_field_select('user', 'firstname', 'id='.$userid);
            $usersecondname = $DB->get_field_select('user', 'lastname', 'id='.$userid);
            $username = $userfirstname." ".$usersecondname;
        }
    } else {
            $userid = $USER->id;
            $userfirstname = $USER->firstname;
            $usersecondname = $USER->lastname;
            $username = $userfirstname." ".$usersecondname;
            $wiziq->presenter_id = $userid;
    }
    if (0 !=($wiziq->groupingid)) {
        $eventtype = 'group';
    } else if (1==$wiziq->course) {
        $eventtype = 'site';
    } else {
        $eventtype = 'course';
    }
    if (1 == $wiziq->recording) {
        $recording = "true";
    } else {
        $recording = "false";
    }
    $class_duration = $wiziq->duration;
    $title = $wiziq->name;
    $presenter_id = $userid;
    $presenter_name = $username;
    $wiziq_datetime = wiziq_converttime($wiziq->wiziq_datetime, $wiziq->wiziq_timezone);
    $vc_language = $wiziq->vc_language;
    $courseid = $wiziq->course;
    $intro= $wiziq->intro;
    $wiziqtimezone = $wiziq->wiziq_timezone;
    $wiziqclass_id = "";
    $errormsg = "";
    $attribnode = "";
    wiziq_scheduleclass($wiziq_secretacesskey, $wiziq_access_key, $wiziq_webserviceurl,
            $title, $presenter_id, $presenter_name, $wiziq_datetime, $wiziqtimezone,
            $class_duration, $vc_language, $recording, $courseid,
            $intro, $attribnode, $wiziqclass_id, $errormsg, $view_recording_url);
    if ($attribnode == "ok") {
        $wiziq->class_id = $wiziqclass_id;
        $wiziq->class_status = "upcoming";
        $wiziq->class_timezone = $wiziqtimezone;
        $wiziq->recording_link = "";
        $wiziq->view_recording_link = $view_recording_url;
        $wiziq->recording_link_status = "0";
        $returnid =  $DB->insert_record('wiziq', $wiziq);
        $event = new stdClass();
        $event->name        = format_string($wiziq->name);
        $event->description = format_module_intro('wiziq', $wiziq, $wiziq->coursemodule);
        $event->courseid    = $wiziq->course;
        $event->groupid     = $wiziq->groupingid;
        $event->userid      = $userid;
        $event->modulename  = 'wiziq';
        $event->instance    = $returnid;
        $event->eventtype   = $eventtype;
        $event->timestart   = $wiziq->wiziq_datetime;
        $event->timeduration = $wiziq->duration;
        calendar_event::create($event);
        return $returnid;
    } else {
        add_to_log($courseid, 'wiziq', 'add class method', '', 'error : '.$errormsg);
        print_error($errormsg);
    }
}


/**
 * Updates an instance of the wiziq in the database
 *
 * Given an object containing all the necessary data,
 * (defined by the form in mod_form.php) this function
 * will update an existing instance with new data.
 *
 * @param object $wiziq An object from the form in mod_form.php
 * 
 * @return boolean Success/Fail
 */
function wiziq_update_instance($wiziq) {
    global $CFG, $DB, $USER;
    $wiziq_webserviceurl= $CFG->wiziq_webserviceurl;
    $wiziq_access_key= $CFG->wiziq_access_key;
    $wiziq_secretacesskey = $CFG->wiziq_secretacesskey;
    $class_id = $wiziq->class_id;
    $wiziq->lasteditorid = $USER->id;
    if (property_exists($wiziq, 'insescod')) {
        $session = $wiziq->insescod;
    }
    if (!isset($class_id)) {
        wiziq_get_data_by_sessioncode($wiziq->course, $session, $class_id, $wiziq->id,
                              $presenter_id, $presenter_name, $presenter_url, $start_time,
                              $time_zone, $create_recording, $status, $language_culture_name,
                              $duration, $recording_url);
    } else {
        wiziq_get_data($wiziq->course, $class_id, $presenter_id, $presenter_name, $presenter_url,
                       $start_time, $time_zone, $create_recording, $status,
                       $language_culture_name, $duration, $recording_url);
    }
    $class_status = ltrim(rtrim($status));
    if (($class_status) != 'expired') {
        $wiziq->timemodified = time();
        $wiziq->id = $wiziq->instance;
        if (! $class_id = $DB->get_field('wiziq', 'class_id', array('id' => $wiziq->id))) {
            return false;
        }
        if (property_exists($wiziq, 'schedule_for_now')) {
            if ($wiziq->schedule_for_now == true) {
                $wiziq->wiziq_datetime = $wiziq->timenow;
            }
        }
        if (property_exists($wiziq, 'scheduleforother')) {
            if ($wiziq->scheduleforother == true) {
                $userid = $wiziq->presenter_id;
                $userfirstname = $DB->get_field_select('user', 'firstname', 'id='.$userid);
                $usersecondname = $DB->get_field_select('user', 'lastname', 'id='.$userid);
                $username = $userfirstname." ".$usersecondname;
            }
        } else if (property_exists($wiziq, 'scheduleforself')) {
            if ($wiziq->scheduleforself == true) {
                $userid = $USER->id;
                $userfirstname = $DB->get_field_select('user', 'firstname', 'id='.$userid);
                $usersecondname = $DB->get_field_select('user', 'lastname', 'id='.$userid);
                $username = $userfirstname." ".$usersecondname;
            }
        } else {
                $userid = $DB->get_field('wiziq', 'presenter_id', array('id' => $wiziq->id));
                $userfirstname = $DB->get_field_select('user', 'firstname', 'id='.$userid);
                $usersecondname = $DB->get_field_select('user', 'lastname', 'id='.$userid);
                $username = $userfirstname." ".$usersecondname;
                $wiziq->presenter_id = $userid;
        }
        if (0 !=($wiziq->groupingid)) {
            $eventtype = 'group';
        } else if (1==$wiziq->course) {
            $eventtype = 'site';
        } else {
            $eventtype = 'course';
        }
        if (1 == $wiziq->recording) {
            $recording = "true";
        } else {
            $recording = "false";
        }
        $class_duration = $wiziq->duration;
        $title = $wiziq->name;
        $presenter_id = $userid;
        $presenter_name = $username;
        $wiziq_datetime = wiziq_converttime($wiziq->wiziq_datetime, $wiziq->wiziq_timezone);
        $vc_language = $wiziq->vc_language;
        $intro= $wiziq->intro;
        $wiziqtimezone = $wiziq->wiziq_timezone;
        $wiziqclass_id = "";
        $errormsg = "";
        $attribnode = "";
        wiziq_modifyclass($wiziq->course, $wiziq_secretacesskey, $wiziq_access_key,
                $wiziq_webserviceurl, $class_id, $title, $presenter_id, $presenter_name,
                $wiziq_datetime, $wiziqtimezone, $class_duration, $vc_language, $recording,
                $intro, $attribnode, $wiziqclass_id, $errormsg);
        if ($attribnode == "ok") {
            # You may have to add extra stuff in here #
            $wiziq->class_status = "upcoming";
            $wiziq->recording_link = "";
            $wiziq->recording_link_status = "0";
            $wiziq->view_recording_link = $recording_url;
            $wiziq->class_timezone = $wiziq->wiziq_timezone;
            $DB->update_record('wiziq', $wiziq);
            $event = new stdClass();
            $event->id = $DB->get_field('event', 'id',
                    array('modulename'=>'wiziq', 'instance'=>$wiziq->id));

            if ($event->id) {

                $event->name        = format_string($wiziq->name);
                $event->description = format_module_intro('wiziq', $wiziq, $wiziq->coursemodule);
                $event->courseid    = $wiziq->course;
                $event->groupid     = $wiziq->groupingid;
                $event->userid      = $userid;
                $event->modulename  = 'wiziq';
                $event->eventtype   = $eventtype;
                $event->timestart   = $wiziq->wiziq_datetime;
                $event->timeduration = $wiziq->duration;
                $calendarevent = calendar_event::load($event->id);
                $calendarevent->update($event);
                return true;
            } else {
                    print_error($errormsg);
            }
        }
    } else {
        print_error("error in case of expired class");
    }
}
/**
 * Removes an instance of the wiziq from the database
 *
 * Given an ID of an instance of this module,
 * this function will permanently delete the instance
 * and any data that depends on it.
 *
 * @param int $id Id of the module instance
 * @return boolean Success/Failure
 */
function wiziq_delete_instance($id) {
    global $DB;
    if (! $wiziq = $DB->get_record('wiziq', array('id' => $id))) {
        return false;
    }
    # Delete any dependent records here #
    if (! $events = $DB->get_records('event',
            array('modulename' => 'wiziq', 'instance' => $wiziq->id))) {
        return false;
    }
    foreach ($events as $event) {
        $event = calendar_event::load($event);
        $event->delete();
    }
    if (! $DB->delete_records('wiziq', array('id' => $wiziq->id))) {
        return false;
    }
    if (!isset($wiziq->class_id)) {
        wiziq_get_data_by_sessioncode_delete($wiziq->id, $wiziq->course,
                                             $wiziq->insescod, $class_id);
        if (isset($class_id)) {
            $wiziq->class_id = $class_id;
        }
    }
    wiziq_delete_class($wiziq->course, $wiziq->class_id);
    return true;
}

/**
 * Removes an instance of the wiziq from the course when course is deleted
 *
 * Called by moodle itself to delete the activities regarding the
 * wiziq in the course.
 *
 * @param int $course Id of the module instance
 * @param string $feedback feedback of the process.
 * @return boolean Success/Failure
 */
function wiziq_delete_course($course, $feedback=true) {
    return true;
}
/**
 * Function to be run periodically according to the moodle cron
 * This function searches for things that need to be done, such
 * as sending out mail, toggling flags etc ...
 *
 * @return boolean
 * @todo Finish documenting this function
 **/
function wiziq_cron () {
    return true;
}




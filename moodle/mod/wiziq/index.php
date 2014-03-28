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
 * This is a one-line short description of the file
 *
 * You can have a rather longer description of the file as well,
 * if you like, and it can span multiple lines.
 *
 * @package    mod_wiziq
 * @copyright  www.wiziq.com 
 * @author     dinkar@wiziq.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

set_time_limit(0);
define('WIZIQ_MAX_TABLE_SIZE', 10);
define('WIZIQ_RECORDING_AVAILABLE', 1);
require_once(dirname(dirname(dirname(__FILE__))).'/config.php');
require_once(dirname(__FILE__).'/lib.php');
require_once($CFG->libdir.'/tablelib.php');
global $DB;
#--------parameter needed---------
$id = required_param('id', PARAM_INT);   // course
$download = optional_param('download', '', PARAM_RAW);
$paging = optional_param('paging', '', PARAM_INT);
confirm_sesskey();

#------setting paging as cookie in order to have paging when page number is changed-------
if (!empty($paging)) {
    setcookie('wiziq_managecookie', $paging, time()+(86400 * 7*365));
    $class_per_page = $paging;
}
$course = $DB->get_record('course', array('id' => $id), '*', MUST_EXIST);
require_course_login($course);
add_to_log($course->id, 'wiziq', 'view',
        'index.php?id='.$course->id.'&sesskey='. sesskey(), 'view index of wiziq classes');
$coursecontext = get_context_instance(CONTEXT_COURSE, $course->id);

$PAGE->set_url('/mod/wiziq/index.php', array('id' => $id, 'sesskey' => sesskey()));
$pagetitle = new stdClass();
$pagetitle->name = get_string('manage_classes', 'wiziq');
$PAGE->set_title(format_string($pagetitle->name));
$PAGE->set_heading(format_string(get_string('wiziq_classes', 'wiziq')));
$PAGE->set_pagelayout('incourse');
$PAGE->set_context($coursecontext);
$course_number = $course->id;

#-----this function get all the data regarding the wiziq class, including cm id's----
if (! $wiziqs = get_all_instances_in_course('wiziq', $course)) {
    notice(get_string('nowiziqs', 'wiziq'),
            new moodle_url('/course/view.php',
                    array('id' => $course->id)));
}

$total_wiziq_records = count($wiziqs);

#------- Creation of table starts------
$table = new flexible_table('manageclasses');
$refresh_txt = get_string('refresh_page', 'wiziq');
$statusicon = '<img src="'.$CFG->wwwroot.'/mod/wiziq/pix/refresh.png" alt='.$refresh_txt.'/>';
$stausimage = '<a href="javascript:location.reload(true)"';
$stausimage .= ' title="'.$refresh_txt.'">'.$statusicon.'</a>';
$statusheading = 'Status'." ".$stausimage;
$table->define_columns(array('name', 'date_time', 'presenter',
    'status', 'manage', 'dnldrec', 'viewrec', 'attendance_report'));
$table->column_style_all('text-align', 'left');
$table->define_headers(array(get_string('name', 'wiziq'),
    get_string('date_time', 'wiziq'), get_string('presenter', 'wiziq'),
    $statusheading, get_string('manage', 'wiziq'),
    get_string('recording_link', 'wiziq'), get_string('viewrec', 'wiziq'),
    get_string('attendance_report', 'wiziq')));
$table->define_baseurl($PAGE->url);
$table->is_downloadable(true);
$table->download_buttons();
$table->show_download_buttons_at(array(TABLE_P_BOTTOM));
$table->sortable(false);
$table->pageable(true);
if (isset($_COOKIE['wiziq_managecookie']) && empty($paging)) {
           $wiziq_managecookie = $_COOKIE['wiziq_managecookie'];
           $selected = $wiziq_managecookie;
           $class_per_page = $wiziq_managecookie;
} else if (!(isset($_COOKIE['wiziq_managecookie'])) && empty($paging)) {
    $class_per_page = WIZIQ_MAX_TABLE_SIZE;
    $selected = "";
} else {
    $class_per_page = $paging;
    $selected = $paging;
}
$table->pagesize($class_per_page, $total_wiziq_records);

#-----naming of the table download file----------------
$wiziq_manage_classes_file = get_string('manage_classes_file', 'wiziq');
$wiziq_file_heading = $wiziq_manage_classes_file." ".$course_number;
$manage_classes = get_string('wiziq_classes_file', 'wiziq');
$wiziq_mc_filename = $manage_classes.$course_number;
$table->is_downloading($download, $wiziq_mc_filename, $wiziq_file_heading);

#--required here so that the $OUTPUT and the html renders only when the page is not downloading--
if (!$table->is_downloading()) {
    echo $OUTPUT->header();
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
    $paging_option = new single_select($PAGE->url, "paging",
            array('5'=>'5', '10'=>'10', '15'=>'15', '20'=>'20'), $selected);

    $paging_option->label = get_string('per_page_classes', 'wiziq');
    echo $OUTPUT->render($paging_option);
}

#------ setting up the table-----
$table->setup();
#--- sorting array to get the newest record first-------
rsort($wiziqs);
$starting_index = $table->get_page_start();
#------slicing the array depending upon the page size choosen by the user------
$slice = array_slice($wiziqs, $starting_index, $class_per_page);

#------ data entry and calling API part-------
$download_recording = get_string('download_recording', 'wiziq');
$view_recording = get_string('view_recording', 'wiziq');
#-----to be used when status will give value Inprogress Via API
$no_download_recording = get_string('no_download_recording', 'wiziq');
$creating_dnld_rcd = get_string('creating_dnld_rcd', 'wiziq');
foreach ($slice as $wiziq) {
    $wiziq_status = $wiziq->class_status;
    $wiziq_datetime = $wiziq->wiziq_datetime;
    if (isset($wiziq->class_id)) {
        $wiziq_classid_array[$wiziq->id] = $wiziq->class_id;
    } else {
        $wiziq_sesscode_array[$wiziq->id] = $wiziq->insescod;
    }
}
if (!empty($wiziq_classid_array)) {
    $wiziq_classid_array = array_filter($wiziq_classid_array);
    wiziq_get_data_manage( $id, $wiziq_classid_array);
}
if (!empty($wiziq_sesscode_array)) {
    $wiziq_sesscode_array = array_filter($wiziq_sesscode_array);// remove null and -ve values
    wiziq_get_data_by_sessioncode_manage($id, $wiziq_sesscode_array);
}
foreach ($slice as $wiziq) {
    $userid = $wiziq->presenter_id;
    $userfirstname = $DB->get_field_select('user', 'firstname', 'id='.$userid);
    $usersecondname = $DB->get_field_select('user', 'lastname', 'id='.$userid);
    $presenter_name = $userfirstname." ".$usersecondname;
    #------  if recording is opted for------
    $wiziqmodulecontext = get_context_instance(CONTEXT_MODULE, $wiziq->coursemodule);
    $newwiziq = $DB->get_record('wiziq', array('id' => $wiziq->id));
    if (!isset($newwiziq->class_id)) {
        $title = $newwiziq->name;
        $start_time = '';
    } else {
        $title = html_writer::link( new moodle_url('/mod/wiziq/view.php',
                array('id' => $wiziq->coursemodule)), format_string($newwiziq->name, true));
        $start_time = wiziq_converttime($newwiziq->wiziq_datetime, $newwiziq->class_timezone);
    }
    $wiziq_completed = ($newwiziq->class_status == 'completed');
    $wiziq_notcompleted = ($newwiziq->recording_link_status != WIZIQ_RECORDING_AVAILABLE);
    if (isset($newwiziq->class_id)) {
        if ($wiziq_completed && $wiziq_notcompleted) {
                wiziq_downloadrecording($id, $newwiziq->class_id,
                                        $download_recording_link, $errormsg);
            if ($download_recording_link != null) {
                $updates = new stdClass(); //just enough data for updating the submission
                $updates->id = $wiziq->id;
                $updates->recording_link_status = WIZIQ_RECORDING_AVAILABLE;
                $updates->recording_link = $download_recording_link;
                $DB->update_record('wiziq', $updates);
                if (has_capability('mod/wiziq:wiziq_download_rec', $wiziqmodulecontext)) {
                    $dnld_rec = html_writer::link( new moodle_url($download_recording_link),
                            $download_recording);
                } else {
                    $dnld_rec = get_string('nocapability', 'wiziq');
                }
                if (has_capability('mod/wiziq:wiziq_view_rec', $wiziqmodulecontext)) {
                    $viewrec_url = new moodle_url($newwiziq->view_recording_link);
                    $action = new popup_action('click', $viewrec_url, "view_recording",
                            array('height' => 786, 'width' => 1024));
                    $view_recording_link = $OUTPUT->action_link($viewrec_url, $view_recording,
                            $action, array('title' => get_string('modulename', 'wiziq')));
                } else {
                    $view_recording_link = get_string('nocapability', 'wiziq');
                }
            } else if ($newwiziq->class_status == 'expired') {
                $dnld_rec = get_string('classnotheld', 'wiziq');
                $view_recording_link = get_string('classnotheld', 'wiziq');
            } else if ($errormsg != null) {
                $dnld_rec = $errormsg;
                if (has_capability('mod/wiziq:wiziq_view_rec', $wiziqmodulecontext)) {
                    $viewrec_url = new moodle_url($newwiziq->view_recording_link);
                    $action = new popup_action('click', $viewrec_url, "view_recording",
                            array('height' => 786, 'width' => 1024));
                    $view_recording_link = $OUTPUT->action_link($viewrec_url, $view_recording,
                            $action, array('title' => get_string('modulename', 'wiziq')));
                } else {
                    $view_recording_link = get_string('nocapability', 'wiziq');
                }
            } else {
                $dnld_rec = "";
                $view_recording_link = "";
            }
        } else if (($newwiziq->recording != 1)) {
                $dnld_rec = "";
                $view_recording_link = get_string('classwithoutrec', 'wiziq');
        } else {
            if (has_capability('mod/wiziq:wiziq_download_rec', $wiziqmodulecontext)) {
                if ($newwiziq->recording_link != "") {
                    $dnld_rec = html_writer::link( new moodle_url($newwiziq->recording_link),
                    $download_recording);
                } else {
                    $dnld_rec = "";
                }
            } else {
                $dnld_rec = get_string('nocapability', 'wiziq');
            }
            if (has_capability('mod/wiziq:wiziq_view_rec', $wiziqmodulecontext)) {
                if ($newwiziq->recording_link != "") {
                    $viewrec_url = new moodle_url($newwiziq->view_recording_link);
                    $action = new popup_action('click', $viewrec_url, "view_recording",
                    array('height' => 786, 'width' => 1024));
                    $view_recording_link = $OUTPUT->action_link($viewrec_url, $view_recording,
                            $action, array('title' => get_string('modulename', 'wiziq')));
                } else {
                    $view_recording_link = "";
                }
            } else {
                $view_recording_link = get_string('nocapability', 'wiziq');
            }
        }
    } else {
        $dnld_rec = '';
        $view_recording_link = get_string('errormsg_session_missing', 'wiziq');
        $newwiziq->class_status = '';
    }
    $editclass = new moodle_url("$CFG->wwwroot/course/mod.php",
            array('update' => $wiziq->coursemodule, 'return' => true, 'sesskey' => sesskey()));
    $editicon = '<img src="'.$CFG->wwwroot.'/mod/wiziq/pix/edit.png" />';
    $editconfirmmsg = get_string('editconfirm', 'wiziq');
    $editconfirm = new confirm_action($editconfirmmsg);
    $edit_wiziq = new action_link($editclass, $editicon, $editconfirm, array());

    $deleteclass = new moodle_url("$CFG->wwwroot/course/mod.php",
            array('delete' => $wiziq->coursemodule, 'return' => true, 'sesskey' => sesskey()));
    $deleteicon = '<img src="'.$CFG->wwwroot.'/mod/wiziq/pix/delete.png" />';
    $deleteconfirmmsg = get_string('deleteconfirm', 'wiziq');
    $deleteconfirm = new confirm_action($deleteconfirmmsg);
    $delete_wiziq = new action_link($deleteclass, $deleteicon, $deleteconfirm, array());
    $wiziqdeletedclass = get_string('deletefromwiziq', 'wiziq');
    if (($newwiziq->class_status != "expired") && ($newwiziq->class_status !="completed")
            && ($newwiziq->class_status != $wiziqdeletedclass && isset($newwiziq->class_id))) {
        $manageclass = $OUTPUT->render($edit_wiziq)."  ".$OUTPUT->render($delete_wiziq);
    } else {
        $manageclass = $OUTPUT->render($delete_wiziq);
    }
    $delfromwiz = get_string('deletefromwiziq', 'wiziq');
    $wiziq_expired = ($newwiziq->class_status != 'expired');
    $wiziq_deletedformwiziq = ($newwiziq->class_status != $delfromwiz);
    $wiziq_upcoming = ($newwiziq->class_status != 'upcoming');
    if (isset($newwiziq->class_id)) {
        if ($wiziq_expired && $wiziq_upcoming && $wiziq_deletedformwiziq) {
            if (has_capability('mod/wiziq:view_attendance_report', $wiziqmodulecontext)) {
                $attendencereport = html_writer::link(
                        new moodle_url("$CFG->wwwroot/mod/wiziq/attendancereport.php",
                    array('id' => $id, 'classid' => $newwiziq->class_id, 'sesskey' => sesskey())),
                        get_string('attendencereport', 'wiziq'));
            } else {
                $attendencereport = get_string('nocapability', 'wiziq');
            }
        } else {
            $attendencereport = get_string('classnotheld', 'wiziq');
        }
    } else {
        $attendencereport = '';
    }
    $table->add_data(array($title, $start_time, $presenter_name,
            $newwiziq->class_status, $manageclass,
            $dnld_rec, $view_recording_link, $attendencereport));
}
$table->setup();
$table->finish_output();
echo $OUTPUT->footer();

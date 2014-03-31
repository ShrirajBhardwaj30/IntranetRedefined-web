<?php

// Local mail plugin for Moodle
// Copyright © 2012,2013 Institut Obert de Catalunya
//
// This program is free software; you can redistribute it and/or
// modify it under the terms of the GNU General Public License
// as published by the Free Software Foundation; either version 2
// of the License, or (at your option) any later version.
//
// Ths program is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with this program; if not, write to the Free Software
// Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.

require_once($CFG->libdir . '/formslib.php');
require_once($CFG->dirroot . '/repository/lib.php');

class mail_compose_form extends moodleform {

    function definition() {
        $mform = $this->_form;
        $message = $this->_customdata['message'];

        // Header

        $label = get_string('compose', 'local_mail');
        $mform->addElement('header', 'general', $label);

        // Course

        $label = get_string('course');
        $text = $message->course()->fullname;
        $mform->addElement('static', 'coursefullname', $label, $text);

        // Recipients

        if ($message and $message->recipients('to')) {
            $text = $this->format_recipients($message->recipients('to'));
            $label = get_string('to', 'local_mail');
            $mform->addElement('static', 'to', $label, $text);
        }

        if ($message and $message->recipients('cc')) {
            $text = $this->format_recipients($message->recipients('cc'));
            $label = get_string('cc', 'local_mail');
            $mform->addElement('static', 'cc', $label, $text);
        }

        if ($message and $message->recipients('bcc')) {
            $text = $this->format_recipients($message->recipients('bcc'));
            $label = get_string('bcc', 'local_mail');
            $mform->addElement('static', 'bcc', $label, $text);
        }

        $label = get_string('addrecipients', 'local_mail');
        $mform->addElement('submit', 'recipients', $label);

        // Subject

        $label = get_string('subject', 'local_mail');
        $mform->addElement('text', 'subject', $label, 'size="48"');
        $mform->setType('subject', PARAM_TEXT);
        $text = get_string('maximumchars', '', 255);
        $mform->addRule('subject', $text, 'maxlength', 255, 'client');

        // Content

        $label = get_string('message', 'local_mail');
        $mform->addElement('editor', 'content', $label, null, self::file_options());
        $mform->setType('content', PARAM_RAW);

        // Attachments

        $label = get_string('attachments', 'local_mail');
        $mform->addElement('filemanager', 'attachments', $label, null, self::file_options());

        // Buttons

        $buttonarray = array();

        $label = get_string('send', 'local_mail');
        $buttonarray[] = $mform->createElement('submit', 'send', $label);

        $label = get_string('save', 'local_mail');
        $buttonarray[] = $mform->createElement('submit', 'save', $label);

        $label = get_string('discard', 'local_mail');
        $buttonarray[] = $mform->createElement('submit', 'discard', $label);

        $mform->addGroup($buttonarray, 'buttonar', '', array(' '), false);
        $mform->closeHeaderBefore('buttonar');
    }

    function validation($data, $files) {
        $message = $this->_customdata['message'];

        $errors = array();

        // Skip on discard
        if (!empty($data['discard'])) {
            return array();
        }

        // Course selected
        if (isset($data['course']) and $data['course'] == SITEID) {
            $errors['course'] = get_string('erroremptycourse', 'local_mail');
        }

        // Empty subject?
        if (!empty($data['send']) and !trim($data['subject'])) {
            $errors['subject'] = get_string('erroremptysubject', 'local_mail');
        }

        // At least one recipient
        if (!empty($data['send']) and (!$message or !$message->recipients())) {
            $errors['recipients'] = get_string('erroremptyrecipients', 'local_mail');
        }

        // Maximum number of attachmnents

        $options = self::file_options();
        $info = file_get_draft_area_info($data['attachments']);
        if ($info['filecount'] > $options['maxfiles']) {
            $errors['attachments'] = get_string('maxfilesreached', 'moodle', $options['maxfiles']);
        }

        return $errors;
    }

    public static function file_options() {
        global $COURSE, $PAGE, $CFG;

        $config_maxbytes = get_config('local_mail', 'maxbytes') ?: LOCAL_MAIL_MAXBYTES;
        $config_maxfiles = get_config('local_mail', 'maxfiles');
        $maxbytes = get_user_max_upload_file_size($PAGE->context, $CFG->maxbytes,
                                                  $COURSE->maxbytes, $config_maxbytes);
        $maxfiles = is_numeric($config_maxfiles) ? $config_maxfiles : LOCAL_MAIL_MAXFILES;
        return array('accepted_types' => '*',
                     'maxbytes' => $maxbytes,
                     'maxfiles' => $maxfiles,
                     'return_types' => FILE_INTERNAL | FILE_EXTERNAL,
                     'subdirs' => false);
    }

    private function format_recipients($users) {
        global $OUTPUT;

        $message = $this->_customdata['message'];

        $content = '';
        
        foreach ($users as $user) { 
            $content .= html_writer::start_tag('div', array('class' => 'mail_recipient'));
            $options = array('courseid' => $message->course(),
                             'link' => false, 'alttext' => false);
            $content .= $OUTPUT->user_picture($user, $options);
            $content .= html_writer::tag('span', s(fullname($user)));
            $attributes = array('type' => 'image',
                                'name' => "remove[{$user->id}]",
                                'src' => $OUTPUT->pix_url('t/delete'),
                                'alt' => get_string('remove'));
            $content .= html_writer::tag('input', '', $attributes);
            $content .= html_writer::end_tag('div');
        }

        return $content;
    }
}

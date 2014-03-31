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

defined('MOODLE_INTERNAL') || die();

require_once('label.class.php');

class local_mail_message {

    private static $index_types = array(
        'inbox', 'drafts', 'sent', 'starred', 'course', 'label', 'trash'
    );

    private $id;
    private $course;
    private $subject;
    private $content;
    private $format;
    private $draft;
    private $time;
    private $refs = array();
    private $users = array();
    private $role = array();
    private $unread = array();
    private $starred = array();
    private $deleted = array();
    private $labels = array();

    static function count_index($userid, $type, $itemid=0) {
        global $DB;

        assert(in_array($type, self::$index_types));

        $conditions = array('userid' => $userid, 'type' => $type, 'item'=> $itemid);
        return $DB->count_records('local_mail_index', $conditions);
    }

    static function count_menu($userid) {
        global $DB;

        $result = new stdClass;
        $result->courses = array();
        $result->labels = array();

        $sql = 'SELECT MIN(id), type, item, unread, COUNT(*) AS count'
            . ' FROM {local_mail_index}'
            . ' WHERE userid = :userid'
            . ' GROUP BY type, item, unread';
        $records = $DB->get_records_sql($sql, array('userid' => $userid));

        foreach ($records as $record) {
            if ($record->type == 'inbox' and $record->unread) {
                $result->inbox = (int) $record->count;
            } else if ($record->type == 'drafts') {
                if (!isset($result->drafts)) {
                    $result->drafts = 0;
                }
                $result->drafts += (int) $record->count;
            } else if ($record->type == 'course' and $record->unread) {
                $result->courses[(int) $record->item] = (int) $record->count;
            } else if ($record->type == 'label' and $record->unread) {
                $result->labels[(int) $record->item] = (int) $record->count;
            }
        }

        return $result;
    }

    static function create($userid, $courseid, $time=false) {
        global $DB;

        $transaction = $DB->start_delegated_transaction();

        $message = new self;
        $message->course = self::fetch_course($courseid);
        $message->users[$userid] = self::fetch_user($userid);

        $record = new stdClass;
        $record->courseid = $message->course->id;
        $record->subject = $message->subject = '';
        $record->content = $message->content = '';
        $record->format = $message->format = -1;
        $record->draft = $message->draft = true;
        $record->time = $message->time = $time ?: time();
        $message->id = $DB->insert_record('local_mail_messages', $record);

        $record = new stdClass;
        $record->messageid = $message->id;
        $record->userid = $userid;
        $record->role = $message->role[$userid] = 'from';
        $record->unread = $message->unread[$userid] = false;
        $record->starred = $message->starred[$userid] = false;
        $record->deleted = $message->deleted[$userid] = false;
        $DB->insert_record('local_mail_message_users', $record);

        $message->create_index($userid, 'drafts');
        $message->create_index($userid, 'course', $courseid);

        $transaction->allow_commit();

        return $message;
    }

    static function delete_course($courseid) {
        global $DB;

        $transaction = $DB->start_delegated_transaction();
        $select = 'messageid IN (SELECT id FROM {local_mail_messages} WHERE courseid = :courseid)';
        $params = array('courseid' => $courseid);
        $DB->delete_records_select('local_mail_index', $select, $params);
        $DB->delete_records_select('local_mail_message_labels', $select, $params);
        $DB->delete_records_select('local_mail_message_users', $select, $params);
        $DB->delete_records_select('local_mail_message_refs', $select, $params);
        $DB->delete_records('local_mail_messages', $params);
        $transaction->allow_commit();
    }

    static function fetch($id) {
        $messages = self::fetch_many(array($id));
        return reset($messages);
    }

    static function fetch_index($userid, $type, $item=0, $limitfrom=0, $limitnum=0) {
        global $DB;

        assert(in_array($type, self::$index_types));

        $conditions = array('userid' => $userid, 'type' => $type, 'item'=> $item);
        $records = $DB->get_records('local_mail_index', $conditions, 'time DESC, messageid DESC',
                                    'messageid', $limitfrom, $limitnum);
        return self::fetch_many(array_keys($records));
    }

    static function fetch_many(array $ids) {
        global $DB;

        $messages = array();

        if (!$ids) {
            return $messages;
        }

        $sql = 'SELECT m.id, m.courseid, m.subject, m.content, m.format,'
            . ' m.draft, m.time, c.shortname, c.fullname, c.groupmode'
            . ' FROM {local_mail_messages} m'
            . ' JOIN {course} c ON c.id = m.courseid'
            . ' WHERE m.id  IN (' . implode(',', $ids) . ')';
        $records = $DB->get_records_sql($sql);

        $sql = 'SELECT mr.id AS recordid, mr.messageid, mr.reference'
            . ' FROM {local_mail_message_refs} mr'
            . ' WHERE mr.messageid IN (' . implode(',', $ids) . ')'
            . ' ORDER BY mr.id ASC';
        $ref_records = $DB->get_records_sql($sql);

        $sql = 'SELECT mu.id AS recordid, mu.messageid, mu.userid, mu.role,'
            . ' mu.unread, mu.starred, mu.deleted,'
            . ' u.username, u.firstname, u.lastname, u.email, u.picture, u.imagealt, u.maildisplay'
            . ' FROM {local_mail_message_users} mu'
            . ' JOIN {user} u ON u.id = mu.userid'
            . ' WHERE mu.messageid  IN (' . implode(',', $ids) . ')'
            . ' ORDER BY u.lastname, u.firstname';
        $user_records = $DB->get_records_sql($sql);

        $sql = 'SELECT ml.id AS recordid, ml.messageid, l.id, l.userid, l.name, l.color'
            . ' FROM {local_mail_message_labels} ml'
            . ' JOIN {local_mail_labels} l ON l.id = ml.labelid'
            . ' WHERE ml.messageid  IN (' . implode(',', $ids) . ')'
            . ' ORDER BY l.name';
        $label_records = $DB->get_records_sql($sql);

        foreach (array_intersect($ids, array_keys($records)) as $id) {
            $messages[] = self::from_records($records[$id], $ref_records,
                                             $user_records, $label_records);
        }

        return $messages;
    }

    static function search_index($userid, $type, $item, array $query) {
        global $DB;

        assert(in_array($type, self::$index_types));
        assert(empty($query['before']) or empty($query['after']));

        $query['pattern'] = !empty($query['pattern']) ? $query['pattern'] : '';

        $sql = 'SELECT messageid FROM {local_mail_index}'
            . ' WHERE userid = :userid AND type = :type AND item = :item';
        $params = array('userid' => $userid, 'type' => $type, 'item' => $item);
        $order = 'DESC';

        if (!empty($query['time'])) {
            $sql .= ' AND time <= :time';
            $params['time'] = $query['time'];
        }

        if (!empty($query['unread'])) {
            $sql .= ' AND unread = 1';
        }

        if (!empty($query['before'])) {
            $from = self::fetch($query['before']);
            $sql .= ' AND time <= :beforetime AND (time < :beforetime2 OR messageid < :beforeid)';
            $params['beforetime'] = $from->time();
            $params['beforetime2'] = $from->time();
            $params['beforeid'] = $from->id();
        } elseif (!empty($query['after'])) {
            $from = self::fetch($query['after']);
            $sql .= ' AND time >= :aftertime AND (time > :aftertime2 OR messageid > :afterid)';
            $params['aftertime'] = $from->time();
            $params['aftertime2'] = $from->time();
            $params['afterid'] = $from->id();
            $order = 'ASC';
        }

        $sql .= " ORDER BY time $order, messageid $order";
        $ids = array_keys($DB->get_records_sql($sql, $params));
        $result = array();
        foreach (array_chunk($ids, 100) as $ids) {
            foreach (self::fetch_many($ids) as $message) {
                if ($message->match($userid, $query['pattern'])) {
                    $result[] = $message;
                }
            }
            if (!empty($query['limit']) and count($result) >= $query['limit']) {
                array_splice($result, $query['limit']);
                break;
            }
        }

        return !empty($query['after']) ? array_reverse($result) : $result;
    }

    function add_label(local_mail_label $label) {
        global $DB;

        assert($this->has_user($label->userid()));
        assert(!$this->draft or $this->role[$label->userid()] == 'from');
        assert(!$this->deleted($label->userid()));

        if (!isset($this->labels[$label->id()])) {
            $transaction = $DB->start_delegated_transaction();
            $record = new stdClass;
            $record->messageid = $this->id;
            $record->labelid = $label->id();
            $DB->insert_record('local_mail_message_labels', $record);
            $this->create_index($label->userid(), 'label', $label->id());
            $transaction->allow_commit();
            $this->labels[$label->id()] = $label;
        }
    }

    function add_recipient($role, $userid) {
        global $DB;

        assert($this->draft);
        assert(!$this->has_recipient($userid));
        assert(in_array($role, array('to', 'cc', 'bcc')));

        $this->users[$userid] = self::fetch_user($userid);

        $record = new stdClass;
        $record->messageid = $this->id;
        $record->userid = $userid;
        $record->role = $this->role[$userid] = $role;
        $record->unread = $this->unread[$userid] = true;
        $record->starred = $this->starred[$userid] = false;
        $record->deleted = $this->deleted[$userid] = false;
        $DB->insert_record('local_mail_message_users', $record);
    }

    function content() {
        return $this->content;
    }

    function course() {
        return $this->course;
    }

    function deleted($userid) {
        assert($this->has_user($userid));
        return $this->deleted[$userid];
    }

    function discard() {
        global $DB;

        assert($this->draft);

        $transaction = $DB->start_delegated_transaction();
        $DB->delete_records('local_mail_messages', array('id' => $this->id));
        $DB->delete_records('local_mail_message_refs', array('messageid' => $this->id));
        $DB->delete_records('local_mail_message_users', array('messageid' => $this->id));
        $DB->delete_records('local_mail_message_labels', array('messageid' => $this->id));
        $DB->delete_records('local_mail_index', array('messageid' => $this->id));
        $transaction->allow_commit();
    }


    function draft() {
        return $this->draft;
    }

    function editable($userid) {
        return $this->draft and $this->has_user($userid) and $this->role[$userid] == 'from';
    }

    function format() {
        return $this->format;
    }

    function forward($userid, $time=false) {
        global $DB;

        assert(!$this->draft);
        assert($this->has_user($userid));

        $transaction = $DB->start_delegated_transaction();

        $message = self::create($userid, $this->course->id, $time);
        $message->save('FW: ' . $this->subject, '', -1, $time);
        $message->set_references($this);

        foreach ($this->labels($userid) as $label) {
            $message->add_label($label);
        }
        
        $transaction->allow_commit();

        return $message;
    }

    function has_label(local_mail_label $label) {
        return isset($this->labels[$label->id()]);
    }

    function has_recipient($userid) {
        return $this->has_user($userid) and $this->role[$userid] != 'from';
    }

    function id() {
        return $this->id;
    }

    function labels($userid=false) {
        assert($userid === false or $this->has_user($userid));

        $result = array();
        foreach ($this->labels as $label) {
            if (!$userid or $label->userid() == $userid) {
                $result[] = $label;
            }
        }
        return $result;
    }

    function recipients() {
        $roles = func_get_args();
        $result = array();
        foreach ($this->users as $user) {
            $role = $this->role[$user->id];
            if ($role != 'from' and (!$roles or in_array($role, $roles))) {
                $result[] = $user;
            }
        }
        return $result;
    }

    function references() {
        $result = self::fetch_many($this->refs);
        usort($result, function($a, $b) {
            return $b->time() - $a->time();
        });
        return $result;
    }

    function remove_label(local_mail_label $label) {
        global $DB;
        assert($this->has_user($label->userid()));
        assert(!$this->draft or $this->role[$label->userid()] == 'from');
        assert(!$this->deleted($label->userid()));

        if (isset($this->labels[$label->id()])) {
            $transaction = $DB->start_delegated_transaction();
            $conditions = array('messageid' => $this->id, 'labelid' => $label->id());
            $DB->delete_records('local_mail_message_labels', $conditions);
            $this->delete_index($label->userid(), 'label', $label->id());
            $transaction->allow_commit();
            unset($this->labels[$label->id()]);
        }
    }

    function remove_recipient($userid) {
        global $DB;

        assert($this->draft);
        assert($this->has_recipient($userid));

        $DB->delete_records('local_mail_message_users', array(
            'messageid' => $this->id,
            'userid' => $userid,
        ));

        unset($this->users[$userid]);
        unset($this->role[$userid]);
        unset($this->unread[$userid]);
        unset($this->starred[$userid]);
        unset($this->deleted[$userid]);
    }

    function reply($userid, $all=false, $time=false) {
        global $DB;

        assert(!$this->draft and $this->has_recipient($userid));
        assert(!$all or in_array($this->role[$userid], array('to', 'cc')));

        if (preg_match('/^RE\s*(?:\[(\d+)\])?:\s*(.*)$/', $this->subject, $matches)) {
            $n_reply = $matches[1] ? (int) $matches[1] + 1 : 2;
            $subject = "RE [$n_reply]: {$matches[2]}";
        } else {
            $subject = 'RE: ' . $this->subject;
        }

        $transaction = $DB->start_delegated_transaction();

        $message = self::create($userid, $this->course->id, $time);
        $message->save($subject, '', -1, $time);
        $sender = $this->sender();
        $message->add_recipient('to', $sender->id);
        $message->set_references($this);

        if ($all) {
            foreach ($this->recipients('to', 'cc') as $user) {
                if ($user->id != $userid) {
                    $message->add_recipient('cc', $user->id);
                }
            }
        }

        foreach ($this->labels($userid) as $label) {
            $message->add_label($label);
        }

        $transaction->allow_commit();

        return $message;
    }

    function save($subject, $content, $format, $time=false) {
        global $DB;

        assert($this->draft);

        $record = new stdClass;
        $record->id = $this->id;
        $record->subject = $this->subject = $subject;
        $record->content = $this->content = $content;
        $record->format = $this->format = $format;
        $record->time = $this->time = $time ?: time();

        $transaction = $DB->start_delegated_transaction();
        $DB->update_record('local_mail_messages', $record);
        $DB->set_field('local_mail_index', 'time', $this->time, array(
            'messageid' => $this->id,
        )); 
        $transaction->allow_commit();
    }

    function send($time=false) {
        global $DB;

        assert($this->draft and count($this->recipients()) > 0);

        $transaction = $DB->start_delegated_transaction();

        $record = new stdClass;
        $record->id = $this->id;
        $record->draft = $this->draft = false;
        $record->time = $this->time = $time ?: time();
        $DB->update_record('local_mail_messages', $record);

        $DB->set_field('local_mail_index', 'time', $this->time, array(
            'messageid' => $this->id,
        )); 

        $DB->set_field('local_mail_index', 'type', 'sent', array(
            'messageid' => $this->id,
            'userid' => $this->sender()->id,
            'type' => 'drafts',
        ));

        foreach ($this->recipients() as $user) {
            $this->create_index($user->id, 'inbox');
            $this->create_index($user->id, 'course', $this->course->id);
        }

        foreach ($this->references() as $reference) {
            foreach ($this->recipients() as $user) {
                if ($reference->has_user($user->id)) {
                    foreach ($reference->labels($user->id) as $label) {
                        $this->add_label($label);
                    }
                }
            }
        }

        $transaction->allow_commit();
    }

    function sender() {
        $userid = array_search('from', $this->role);
        return $this->users[$userid];
    }

    function set_deleted($userid, $value) {
        global $DB;

        assert($this->has_user($userid));
        assert(!$this->draft or $this->role[$userid] == 'from');

        if ($this->deleted[$userid] == (bool) $value) {
            return;
        }

        $transaction = $DB->start_delegated_transaction();

        $conditions = array('messageid' => $this->id, 'userid' => $userid);
        $DB->set_field('local_mail_message_users', 'deleted', (bool) $value, $conditions);

        if ($value) {
            $this->delete_index($userid);
            $this->create_index($userid, 'trash');
        } else {
            $this->delete_index($userid, 'trash');
            if ($this->role[$userid] == 'from') {
                $this->create_index($userid, $this->draft ? 'drafts' : 'sent');
            } else {
                $this->create_index($userid, 'inbox');
            }
            if ($this->starred($userid)) {
                $this->create_index($userid, 'starred');
            }
            $this->create_index($userid, 'course', $this->course->id);
            foreach ($this->labels($userid) as $label) {
                $this->create_index($userid, 'label', $label->id());
            }
        }

        $transaction->allow_commit();

        $this->deleted[$userid] = (bool) $value;
    }

    function set_starred($userid, $value) {
        global $DB;

        assert($this->has_user($userid));
        assert(!$this->draft or $this->role[$userid] == 'from');
        assert(!$this->deleted($userid));

        if ($this->starred[$userid] == (bool) $value) {
            return;
        }

        $transaction = $DB->start_delegated_transaction();

        $conditions = array('messageid' => $this->id, 'userid' => $userid);
        $DB->set_field('local_mail_message_users', 'starred', (bool) $value, $conditions);

        if ($value) {
            $this->create_index($userid, 'starred');
        } else {
            $this->delete_index($userid, 'starred');
        }

        $transaction->allow_commit();

        $this->starred[$userid] = (bool) $value;
    }

    function set_unread($userid, $value) {
        global $DB;

        assert($this->has_user($userid));
        assert(!$this->draft or $this->role[$userid] == 'from');

        if ($this->unread[$userid] == (bool) $value) {
            return;
        }

        $transaction = $DB->start_delegated_transaction();
        $conditions = array('messageid' => $this->id, 'userid' => $userid);
        $DB->set_field('local_mail_message_users', 'unread', (bool) $value, $conditions);
        $DB->set_field('local_mail_index', 'unread', (bool) $value, $conditions);
        $transaction->allow_commit();

        $this->unread[$userid] = (bool) $value;
    }

    function starred($userid) {
        assert($this->has_user($userid));
        return $this->starred[$userid];
    }

    function subject() {
        return $this->subject;
    }

    function time() {
        return $this->time;
    }

    function unread($userid) {
        assert($this->has_user($userid));
        return $this->unread[$userid];
    }

    function viewable($userid, $include_refs=false) {
        global $DB;

        if ($this->has_user($userid)) {
            return !$this->draft or $this->role[$userid] == 'from';
        }

        if ($include_refs) {
            $sql = 'SELECT m.id'
                . ' FROM {local_mail_messages} m'
                . ' JOIN {local_mail_message_users} mu ON mu.messageid = m.id'
                . ' JOIN {local_mail_message_refs} mr ON mr.messageid = m.id'
                . ' WHERE mr.reference = :messageid'
                . ' AND mu.userid = :userid'
                . ' AND (m.draft = 0 OR mu.role = :role)';
            $params = array(
                'role' => 'from',
                'messageid' => $this->id,
                'userid' => $userid,
            );
            return $DB->record_exists_sql($sql, $params);
        }

        return false;
    }

    private function __construct() {}

    private static function from_records($record, $ref_records, $user_records, $label_records) {
        $message = new self;
        $message->id = (int) $record->id;
        $message->course = (object) array(
            'id' => $record->courseid,
            'shortname' => $record->shortname,
            'fullname' => $record->fullname,
            'groupmode' => $record->groupmode,
        );
        $message->subject = $record->subject;
        $message->content = $record->content;
        $message->format = (int) $record->format;
        $message->draft = (bool) $record->draft;
        $message->time = (int) $record->time;

        foreach ($ref_records as $r) {
            if ($r->messageid == $record->id) {
                $message->refs[] = $r->reference;
            }
        }

        foreach ($user_records as $r) {
            if ($r->messageid == $record->id) {
                $message->role[$r->userid] = $r->role;
                $message->unread[$r->userid] = (bool) $r->unread;
                $message->starred[$r->userid] = (bool) $r->starred;
                $message->deleted[$r->userid] = (bool) $r->deleted;
                $message->users[$r->userid] = (object) array(
                    'id' => $r->userid,
                    'username' => $r->username,
                    'firstname' => $r->firstname,
                    'lastname' => $r->lastname,
                    'email' => $r->email,
                    'picture' => $r->picture,
                    'imagealt' => $r->imagealt,
                    'maildisplay' => $r->maildisplay,
                );
            }
        }

        foreach ($label_records as $r) {
            if ($r->messageid == $record->id) {
                $message->labels[$r->id] = local_mail_label::from_record($r);
            }
        }

        return $message;
    }

    private static function fetch_course($courseid) {
        global $DB;
        $conditions = array('id' => $courseid);
        $fields = 'id, shortname, fullname, groupmode';
        return $DB->get_record('course', $conditions, $fields, MUST_EXIST);
    }

    private static function fetch_user($userid) {
        global $DB;
        $conditions = array('id' => $userid);
        $fields = 'id, username, firstname, lastname, email, picture, imagealt, maildisplay';
        return $DB->get_record('user', $conditions, $fields, MUST_EXIST);
    }

    private function create_index($userid, $type, $itemid=0) {
        global $DB;

        $record = new stdClass;
        $record->userid = $userid;
        $record->type = $type;
        $record->item = $itemid;
        $record->time = $this->time;
        $record->messageid = $this->id;
        $record->unread = $this->unread[$userid];

        $DB->insert_record('local_mail_index', $record);
    }

    private function delete_index($userid, $type=false, $itemid=0) {
        global $DB;

        $conditions = array();
        $conditions['messageid'] = $this->id;
        $conditions['userid'] = $userid;
        if ($type) {
            $conditions['type'] = $type;
            $conditions['item'] = $itemid;
            $conditions['time'] = $this->time;
        }
        $DB->delete_records('local_mail_index', $conditions);
    }

    private function has_user($userid) {
        return isset($this->users[$userid]);
    }

    private function match($userid, $pattern) {
        $normalize = function($text) {
            return strtolower(trim(preg_replace('/\s+/', ' ', $text)));
        };

        $pattern = $normalize($pattern);

        $match_text = function($text) use ($normalize, $pattern) {
            return strpos($normalize($text), $pattern) !== false;
        };

        $users = array_merge(array($this->sender()), $this->recipients());
        if (!$pattern or $match_text($this->subject()) or
            array_filter(array_map('fullname', $users), $match_text)) {
            return true;
        }

        $html = format_text($this->content(), $this->format());
        return $match_text(html_to_text($html));
    }

    private function set_references($message) {
        global $DB;

        $this->refs = array_merge(array($message->id), $message->refs);

        $DB->delete_records('local_mail_message_refs', array('messageid' => $this->id));

        foreach ($this->refs as $ref) {
            $record = new stdClass;
            $record->messageid = $this->id;
            $record->reference = $ref;
            $DB->insert_record('local_mail_message_refs', $record);
        }
    }
}

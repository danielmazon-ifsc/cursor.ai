<?php

/**
 * @package    mod_socialforum
 * @subpackage backup-moodle2
 * @copyright 2016 Viddia (http://viddia.com.br)
 * @author    Ricardo Drummond
 */
/**
 * Define all the restore steps that will be used by the restore_socialforum_activity_task
 */

/**
 * Structure step to restore one socialforum activity
 */
class restore_socialforum_activity_structure_step extends restore_activity_structure_step {

    protected function define_structure() {

        $paths = array();
        $userinfo = $this->get_setting_value('userinfo');

        $paths[] = new restore_path_element('socialforum', '/activity/socialforum');
        if ($userinfo) {
            $paths[] = new restore_path_element('socialforum_discussion', '/activity/socialforum/discussions/discussion');
            $paths[] = new restore_path_element('socialforum_post', '/activity/socialforum/discussions/discussion/posts/post');
            $paths[] = new restore_path_element('socialforum_discussion_sub', '/activity/socialforum/discussions/discussion/discussion_subs/discussion_sub');
            $paths[] = new restore_path_element('socialforum_rating', '/activity/socialforum/discussions/discussion/posts/post/ratings/rating');
            $paths[] = new restore_path_element('socialforum_subscription', '/activity/socialforum/subscriptions/subscription');
            $paths[] = new restore_path_element('socialforum_digest', '/activity/socialforum/digests/digest');
            $paths[] = new restore_path_element('socialforum_read', '/activity/socialforum/readposts/read');
            $paths[] = new restore_path_element('socialforum_vote', '/activity/socialforum/votes/vote');
            $paths[] = new restore_path_element('socialforum_vote_count', '/activity/socialforum/vote_counts/vote_count');
            $paths[] = new restore_path_element('socialforum_track', '/activity/socialforum/trackedprefs/track');
        }

        // Return the paths wrapped into standard activity structure
        return $this->prepare_activity_structure($paths);

    }

    protected function process_socialforum($data) {
        global $DB;

        $data = (object) $data;
        $oldid = $data->id;
        $data->course = $this->get_courseid();

        $data->assesstimestart = $this->apply_date_offset($data->assesstimestart);
        $data->assesstimefinish = $this->apply_date_offset($data->assesstimefinish);
        if ($data->scale < 0) { // scale found, get mapping
            $data->scale = -($this->get_mappingid('scale', abs($data->scale)));
        }

        $newitemid = $DB->insert_record('socialforum', $data);
        $this->apply_activity_instance($newitemid);

    }

    protected function process_socialforum_discussion($data) {
        global $DB;

        $data = (object) $data;
        $oldid = $data->id;
        $data->course = $this->get_courseid();

        $data->socialforum = $this->get_new_parentid('socialforum');
        $data->timemodified = $this->apply_date_offset($data->timemodified);
        $data->timestart = $this->apply_date_offset($data->timestart);
        $data->timeend = $this->apply_date_offset($data->timeend);
        $data->userid = $this->get_mappingid('user', $data->userid);
        $data->groupid = $this->get_mappingid('group', $data->groupid);
        $data->usermodified = $this->get_mappingid('user', $data->usermodified);

        $newitemid = $DB->insert_record('socialforum_discussions', $data);
        $this->set_mapping('socialforum_discussion', $oldid, $newitemid);

    }

    protected function process_socialforum_post($data) {
        global $DB;

        $data = (object) $data;
        $oldid = $data->id;

        $data->discussion = $this->get_new_parentid('socialforum_discussion');
        $data->created = $this->apply_date_offset($data->created);
        $data->modified = $this->apply_date_offset($data->modified);
        $data->userid = $this->get_mappingid('user', $data->userid);
        // If post has parent, map it (it has been already restored)
        if (!empty($data->parent)) {
            $data->parent = $this->get_mappingid('socialforum_post', $data->parent);
        }

        $newitemid = $DB->insert_record('socialforum_posts', $data);
        $this->set_mapping('socialforum_post', $oldid, $newitemid, true);

        // If !post->parent, it's the 1st post. Set it in discussion
        if (empty($data->parent)) {
            $DB->set_field('socialforum_discussions', 'firstpost', $newitemid, array('id' => $data->discussion));
        }

    }

    protected function process_socialforum_rating($data) {
        global $DB;

        $data = (object) $data;

        // Cannot use ratings API, cause, it's missing the ability to specify times (modified/created)
        $data->contextid = $this->task->get_contextid();
        $data->itemid = $this->get_new_parentid('socialforum_post');
        if ($data->scaleid < 0) { // scale found, get mapping
            $data->scaleid = -($this->get_mappingid('scale', abs($data->scaleid)));
        }
        $data->rating = $data->value;
        $data->userid = $this->get_mappingid('user', $data->userid);
        $data->timecreated = $this->apply_date_offset($data->timecreated);
        $data->timemodified = $this->apply_date_offset($data->timemodified);

        // We need to check that component and ratingarea are both set here.
        if (empty($data->component)) {
            $data->component = 'mod_socialforum';
        }
        if (empty($data->ratingarea)) {
            $data->ratingarea = 'post';
        }

        $newitemid = $DB->insert_record('rating', $data);

    }

    protected function process_socialforum_subscription($data) {
        global $DB;

        $data = (object) $data;
        $oldid = $data->id;

        $data->socialforum = $this->get_new_parentid('socialforum');
        $data->userid = $this->get_mappingid('user', $data->userid);

        $newitemid = $DB->insert_record('socialforum_subscriptions', $data);
        $this->set_mapping('socialforum_subscription', $oldid, $newitemid, true);

    }

    protected function process_socialforum_discussion_sub($data) {
        global $DB;

        $data = (object) $data;
        $oldid = $data->id;

        $data->discussion = $this->get_new_parentid('socialforum_discussion');
        $data->socialforum = $this->get_new_parentid('socialforum');
        $data->userid = $this->get_mappingid('user', $data->userid);

        $newitemid = $DB->insert_record('socialforum_discussion_subs', $data);
        $this->set_mapping('socialforum_discussion_sub', $oldid, $newitemid, true);

    }

    protected function process_socialforum_digest($data) {
        global $DB;

        $data = (object) $data;
        $oldid = $data->id;

        $data->socialforum = $this->get_new_parentid('socialforum');
        $data->userid = $this->get_mappingid('user', $data->userid);

        $newitemid = $DB->insert_record('socialforum_digests', $data);

    }

    protected function process_socialforum_read($data) {
        global $DB;

        $data = (object) $data;
        $oldid = $data->id;

        $data->socialforumid = $this->get_new_parentid('socialforum');
        $data->discussionid = $this->get_mappingid('socialforum_discussion', $data->discussionid);
        $data->postid = $this->get_mappingid('socialforum_post', $data->postid);
        $data->userid = $this->get_mappingid('user', $data->userid);

        $newitemid = $DB->insert_record('socialforum_read', $data);

    }

    protected function process_socialforum_vote($data) {
        global $DB;

        $data = (object) $data;
        $oldid = $data->id;

        $data->socialforumid = $this->get_new_parentid('socialforum');
        $data->discussionid = $this->get_mappingid('socialforum_discussion', $data->discussionid);
        $data->postid = $this->get_mappingid('socialforum_post', $data->postid);
        $data->userid = $this->get_mappingid('user', $data->userid);

        $newitemid = $DB->insert_record('socialforum_vote', $data);

    }

    protected function process_socialforum_vote_count($data) {
        global $DB;

        $data = (object) $data;
        $oldid = $data->id;

        $data->socialforumid = $this->get_new_parentid('socialforum');
        $data->discussionid = $this->get_mappingid('socialforum_discussion', $data->discussionid);
        $data->postid = $this->get_mappingid('socialforum_post', $data->postid);
        $data->userid = $this->get_mappingid('user', $data->userid);

        $newitemid = $DB->insert_record('socialforum_vote_count', $data);

    }

    protected function process_socialforum_track($data) {
        global $DB;

        $data = (object) $data;
        $oldid = $data->id;

        $data->socialforumid = $this->get_new_parentid('socialforum');
        $data->userid = $this->get_mappingid('user', $data->userid);

        $newitemid = $DB->insert_record('socialforum_track_prefs', $data);

    }

    protected function after_execute() {
        // Add socialforum related files, no need to match by itemname (just internally handled context)
        $this->add_related_files('mod_socialforum', 'intro', null);

        // Add post related files, matching by itemname = 'socialforum_post'
        $this->add_related_files('mod_socialforum', 'post', 'socialforum_post');
        $this->add_related_files('mod_socialforum', 'attachment', 'socialforum_post');

    }

    protected function after_restore() {
        global $DB;

        // If the socialforum is of type 'single' and no discussion has been ignited
        // (non-userinfo backup/restore) create the discussion here, using socialforum
        // information as base for the initial post.
        $socialforumid = $this->task->get_activityid();
        $socialforumrec = $DB->get_record('socialforum', array('id' => $socialforumid));
        if ($socialforumrec->type == 'single' && !$DB->record_exists('socialforum_discussions', array('socialforum' => $socialforumid))) {
            // Create single discussion/lead post from socialforum data
            $sd = new stdClass();
            $sd->course = $socialforumrec->course;
            $sd->socialforum = $socialforumrec->id;
            $sd->name = $socialforumrec->name;
            $sd->assessed = $socialforumrec->assessed;
            $sd->message = $socialforumrec->intro;
            $sd->messageformat = $socialforumrec->introformat;
            $sd->messagetrust = true;
            $sd->mailnow = false;
            $sdid = socialforum_add_discussion($sd, null, null, $this->task->get_userid());
            // Mark the post as mailed
            $DB->set_field('socialforum_posts', 'mailed', '1', array('discussion' => $sdid));
            // Copy all the files from mod_foum/intro to mod_socialforum/post
            $fs = get_file_storage();
            $files = $fs->get_area_files($this->task->get_contextid(), 'mod_socialforum', 'intro');
            foreach ($files as $file) {
                $newfilerecord = new stdClass();
                $newfilerecord->filearea = 'post';
                $newfilerecord->itemid = $DB->get_field('socialforum_discussions', 'firstpost', array('id' => $sdid));
                $fs->create_file_from_storedfile($newfilerecord, $file);
            }
        }

    }

}

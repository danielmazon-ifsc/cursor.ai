<?php

/**
 * @package    mod_socialforum
 * @subpackage backup-moodle2
 * @copyright  2016 Viddia (http://viddia.com.br)
 * @author     Ricardo Drummond
 */
/**
 * Define all the backup steps that will be used by the backup_socialforum_activity_task
 */

/**
 * Define the complete socialforum structure for backup, with file and id annotations
 */
class backup_socialforum_activity_structure_step extends backup_activity_structure_step {

    protected function define_structure() {

        // To know if we are including userinfo
        $userinfo = $this->get_setting_value('userinfo');

        // Define each element separated

        $socialforum = new backup_nested_element('socialforum', array('id'), array(
            'type', 'name', 'intro', 'introformat',
            'assessed', 'assesstimestart', 'assesstimefinish', 'scale',
            'maxbytes', 'maxattachments', 'forcesubscribe', 'trackingtype',
            'rsstype', 'rssarticles', 'timemodified', 'warnafter',
            'blockafter', 'blockperiod', 'completiondiscussions', 'completionreplies',
            'completionposts', 'displaywordcount'));

        $discussions = new backup_nested_element('discussions');

        $discussion = new backup_nested_element('discussion', array('id'), array(
            'name', 'firstpost', 'userid', 'groupid',
            'assessed', 'timemodified', 'usermodified', 'timestart',
            'timeend', 'pinned'));

        $posts = new backup_nested_element('posts');

        $post = new backup_nested_element('post', array('id'), array(
            'parent', 'userid', 'created', 'modified',
            'mailed', 'subject', 'message', 'messageformat',
            'messagetrust', 'attachment', 'totalscore', 'mailnow'));

        $ratings = new backup_nested_element('ratings');

        $rating = new backup_nested_element('rating', array('id'), array(
            'component', 'ratingarea', 'scaleid', 'value', 'userid', 'timecreated', 'timemodified'));

        $discussionsubs = new backup_nested_element('discussion_subs');

        $discussionsub = new backup_nested_element('discussion_sub', array('id'), array(
            'userid',
            'preference',
        ));

        $subscriptions = new backup_nested_element('subscriptions');

        $subscription = new backup_nested_element('subscription', array('id'), array(
            'userid'));

        $digests = new backup_nested_element('digests');

        $digest = new backup_nested_element('digest', array('id'), array(
            'userid', 'maildigest'));

        $readposts = new backup_nested_element('readposts');

        $read = new backup_nested_element('read', array('id'), array(
            'userid', 'discussionid', 'postid', 'firstread',
            'lastread'));

        $votes = new backup_nested_element('votes');

        $vote = new backup_nested_element('vote', array('id'), array(
            'discussionid', 'postid', 'userid', 'votes'));

        $vote_counts = new backup_nested_element('vote_counts');

        $vote_count = new backup_nested_element('vote_count', array('id'), array(
            'discussionid', 'postid', 'userid', 'counting'));

        $trackedprefs = new backup_nested_element('trackedprefs');

        $track = new backup_nested_element('track', array('id'), array(
            'userid'));

        // Build the tree

        $socialforum->add_child($discussions);
        $discussions->add_child($discussion);

        $socialforum->add_child($subscriptions);
        $subscriptions->add_child($subscription);

        $socialforum->add_child($digests);
        $digests->add_child($digest);

        $socialforum->add_child($readposts);
        $readposts->add_child($read);

        $socialforum->add_child($votes);
        $votes->add_child($vote);

        $socialforum->add_child($vote_counts);
        $vote_counts->add_child($vote_count);

        $socialforum->add_child($trackedprefs);
        $trackedprefs->add_child($track);

        $discussion->add_child($posts);
        $posts->add_child($post);

        $post->add_child($ratings);
        $ratings->add_child($rating);

        $discussion->add_child($discussionsubs);
        $discussionsubs->add_child($discussionsub);

        // Define sources

        $socialforum->set_source_table('socialforum', array('id' => backup::VAR_ACTIVITYID));

        // All these source definitions only happen if we are including user info
        if ($userinfo) {
            $discussion->set_source_sql('
                SELECT *
                  FROM {socialforum_discussions}
                 WHERE socialforum = ?', array(backup::VAR_PARENTID));

            // Need posts ordered by id so parents are always before childs on restore
            $post->set_source_table('socialforum_posts', array('discussion' => backup::VAR_PARENTID), 'id ASC');
            $discussionsub->set_source_table('socialforum_discussion_subs', array('discussion' => backup::VAR_PARENTID));

            $subscription->set_source_table('socialforum_subscriptions', array('socialforum' => backup::VAR_PARENTID));
            $digest->set_source_table('socialforum_digests', array('socialforum' => backup::VAR_PARENTID));

            $read->set_source_table('socialforum_read', array('socialforumid' => backup::VAR_PARENTID));

            $vote->set_source_table('socialforum_vote', array('socialforumid' => backup::VAR_PARENTID));

            $vote_count->set_source_table('socialforum_vote_count', array('socialforumid' => backup::VAR_PARENTID));

            $track->set_source_table('socialforum_track_prefs', array('socialforumid' => backup::VAR_PARENTID));

            $track->set_source_table('socialforum_track_prefs', array('socialforumid' => backup::VAR_PARENTID));

            $rating->set_source_table('rating', array('contextid' => backup::VAR_CONTEXTID,
                'component' => backup_helper::is_sqlparam('mod_socialforum'),
                'ratingarea' => backup_helper::is_sqlparam('post'),
                'itemid' => backup::VAR_PARENTID));
            $rating->set_source_alias('rating', 'value');
        }

        // Define id annotations

        $socialforum->annotate_ids('scale', 'scale');

        $discussion->annotate_ids('group', 'groupid');

        $post->annotate_ids('user', 'userid');

        $discussionsub->annotate_ids('user', 'userid');

        $rating->annotate_ids('scale', 'scaleid');

        $rating->annotate_ids('user', 'userid');

        $subscription->annotate_ids('user', 'userid');

        $digest->annotate_ids('user', 'userid');

        $read->annotate_ids('user', 'userid');

        $vote->annotate_ids('user', 'userid');

        $vote_count->annotate_ids('user', 'userid');

        $track->annotate_ids('user', 'userid');

        // Define file annotations

        $socialforum->annotate_files('mod_socialforum', 'intro', null); // This file area hasn't itemid

        $post->annotate_files('mod_socialforum', 'post', 'id');
        $post->annotate_files('mod_socialforum', 'attachment', 'id');

        // Return the root element (socialforum), wrapped into standard activity structure
        return $this->prepare_activity_structure($socialforum);

    }

}

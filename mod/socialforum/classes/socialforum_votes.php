<?php

/**
 * File containing the definition of socialforum_votes class. An objec of
 * socialforum_votes will represent and manage votes for one social forum
 * discussion or post
 * 
 * @package   mod_socialforum
 * @copyright 2016 Viddia (http://viddia.com.br)
 * @author    Ricardo Drummond
 */

namespace mod_socialforum;

/**
 * Description of socialforum_votes class
 *
 * @package   mod_socialforum
 * @copyright 2016 Viddia (http://viddia.com.br)
 * @author    Ricardo Drummond
 *
 * @property-read int $socialforumid Id of the socialforum 
 * @property-read int $discussionid Id of the discussion
 * @property-read int $postid Id of the post
 * @property-read int $votes How many votes were computed
 * @property-read int $userid User who created discussion or post
 */
class socialforum_votes {

    /** @var array event data */
    protected $data;

    // Public methods

    /**
     * Constructs a socialforum_votes object
     * 
     * @param int $socialforumid Id of social forum
     * @param int $discussionid Id of discussion
     * @param int $postid Id of post
     * @param int $userid Id of user
     * @throws \invalid_parameter_exception In case od invalid parameters
     */
    public function __construct($socialforumid, $discussionid = null, $postid = null, $userid) {

        if (!is_numeric($socialforumid) || $socialforumid <= 0) {
            throw new \invalid_parameter_exception('socialforumid must be positive integer: ' . $socialforumid);
        }
        if ((!is_numeric($discussionid) || $discussionid <= 0) && (!is_numeric($postid) || $postid <= 0)) {
            throw new \invalid_parameter_exception('discussionid or postid must be positive integer');
        }
        if ($discussionid && $postid) {
            throw new \invalid_parameter_exception('discussionid and postid cannot be both not null');
        }
        if (!$discussionid && !$postid) {
            throw new \invalid_parameter_exception('discussionid and postid cannot be both null');
        }
        if (!is_numeric($userid) || $userid <= 0) {
            throw new \invalid_parameter_exception('userid must be positive integer: ' . $userid);
        }

        $this->data = array();
        if (!$this->retrieve_socialforum_votes($socialforumid, $discussionid, $postid, $userid)) {
            $this->create_socialforum_votes($socialforumid, $discussionid, $postid, $userid);
            $this->retrieve_socialforum_votes($socialforumid, $discussionid, $postid, $userid);
        }
    }

    /**
     * Magic getter for read only access.
     *
     * @param string $name
     * @return mixed
     * @throws \coding_exception in case there's no field with that name
     */
    public function __get($name) {
        if (array_key_exists($name, $this->data)) {
            return $this->data[$name];
        }
        debugging("Accessing non-existent socialforum_votes property '$name'");
    }

    /**
     * Magic setter.
     *
     * Note: we must not allow modification of data from outside,
     *       after trigger() the data MUST NOT CHANGE!!!
     *
     * @param string $name
     * @param mixed $value
     *
     * @throws \coding_exception Setters are not supported for this class
     */
    public function __set($name, $value) {
        throw new \coding_exception('socialforum_votes properties must not be modified.');
    }

    /**
     * Is data property set?
     *
     * @param string $name
     * @return bool
     */
    public function __isset($name) {
        return isset($this->data[$name]);
    }

    /**
     * Compute votes on behalf of supplied user. Generates events registering
     * user vote and ocasionally events registering changes in post or
     * discussion relevancy and/or in position as the most relevant post or
     * discussion
     * 
     * @param int $userid Id of the user who voted. Must be a different user
     *          from user who created discussion or post
     * @param int $numvotes Number of votes to be computed on behalf of the
     *          user. Must be an integer
     * @throws invalid_parameter_exception In case of any invalid argument
     * @throws invalid_state_exception In case of nonexisting user, user is
     *          the same as user who created post or discussion or post is the
     *          first post in discussion (actually the discussion text itself)
     * @return int updated number of votes
     */
    public function add_votes($userid, $numvotes = 1) {
        global $DB;

        if (!is_numeric($userid) || $userid <= 0) {
            throw new \invalid_parameter_exception('userid must be positive integer');
        }
        if (!is_numeric($numvotes) || $numvotes == 0) {
            throw new \invalid_parameter_exception('numvotes must be integer and different from zero');
        }

        // Check if user who voted is the same as post or discussion creator
        if ($userid == $this->userid) {
            throw new \invalid_state_exception('user cannot vote in his/her own discussion or post');
        }
        // Check if user has already voted in this post or discussion
        $sql = 'SELECT * '
                . 'FROM {socialforum_vote} '
                . 'WHERE socialforumid = :socialforumid '
                . 'AND userid = :userid '
                . 'AND (discussionid = :discussionid '
                . 'OR postid = :postid);';
        $vote = $DB->get_records_sql($sql, array('socialforumid' => $this->socialforumid, 'discussionid' => $this->discussionid, 'postid' => $this->postid, 'userid' => $userid));
        if ($vote) {
            throw new \invalid_state_exception('user cannot vote more than once in a discussion or post');
        }

        $dbtransaction = $DB->start_delegated_transaction();
        try {

            // Insert vote record
            $newvote = array();
            $newvote['votes'] = $numvotes;
            $newvote['socialforumid'] = $this->socialforumid;
            $newvote['discussionid'] = $this->discussionid;
            $newvote['postid'] = $this->postid;
            $newvote['userid'] = $userid;
            $DB->insert_record('socialforum_vote', $newvote);

            // Update vote total
            $sql = 'SELECT * '
                    . 'FROM {socialforum_vote_count} '
                    . 'WHERE socialforumid = :socialforumid '
                    . 'AND userid = :userid '
                    . 'AND (discussionid = :discussionid '
                    . 'OR postid = :postid);';
            $vote_count = $DB->get_records_sql($sql, array('socialforumid' => $this->socialforumid, 'discussionid' => $this->discussionid, 'postid' => $this->postid, 'userid' => $this->userid));
            if (!$vote_count) {
                throw new \invalid_state_exception("Social forum votes structure doesn't exist");
            }
            if (count($vote_count) > 1) {
                throw new \invalid_state_exception("More than one social forum votes structure found");
            }
            $total = reset($vote_count);
            $total->counting += $numvotes;
            $DB->update_record('socialforum_vote_count', $total);

            // Update votes total
            $this->data['votes'] += $numvotes;

            // Generate vote event
            $socialforum = $DB->get_record('socialforum', array('id' => $this->socialforumid));
            $params = array(
                'objectid' => ($this->discussionid ? $this->discussionid : $this->postid),
                'userid' => $userid,
                'relateduserid' => $this->userid,
                'context' => \context_user::instance($userid),
                'other' => array(
                    'discussionid' => $this->discussionid,
                    'socialforumid' => $this->socialforumid,
                    'socialforumtype' => $socialforum->type,
                ),
            );
            if ($this->discussionid) {
                $event = \mod_socialforum\event\discussion_relevancyvoted::create($params);
            } else {
                $event = \mod_socialforum\event\post_relevancyvoted::create($params);
            }
            $event->trigger();

            $this->check_relevancy($numvotes);
            if ($this->postid) {
                $this->check_mostrelevant($numvotes);
            }

            // Confirm transaction
            $dbtransaction->allow_commit();
        } catch (\Throwable $e) {
            $dbtransaction->rollback($e);
            debugging("Error: " . $e->getMessage());
            throw $e;
        }

        return $this->data['votes'];
    }

    /**
     * Cancel votes computed in on behalf of the user. Generates an event
     * registering user vote cancellation and ocasionally events registering
     * changes in post or discussion relevancy and/or in position as the most
     * relevant post or discussion
     * 
     * @param int $userid Id of the user whose votes will be disconsidered
     * @throws invalid_parameter_exception In case of any invalid argument
     * @return int updated number of votes
     */
    public function remove_votes($userid) {
        global $DB;

        if (!is_numeric($userid) || $userid <= 0) {
            throw new \invalid_parameter_exception('userid must be positive integer');
        }

        // Check if user has voted in this post or discussion
        $sql = 'SELECT * '
                . 'FROM {socialforum_vote} '
                . 'WHERE socialforumid = :socialforumid '
                . 'AND userid = :userid '
                . 'AND (discussionid = :discussionid '
                . 'OR postid = :postid);';
        $votes = $DB->get_records_sql($sql, array('socialforumid' => $this->socialforumid, 'discussionid' => $this->discussionid, 'postid' => $this->postid, 'userid' => $userid));
        if (!$votes) {
            return $this->votes;
        }
        if (count($votes) > 1) {
            throw new \invalid_state_exception("User voted more than once in a social forum discussion or post");
        }
        $vote = reset($votes);

        $dbtransaction = $DB->start_delegated_transaction();
        try {

            // Delete vote record
            $DB->delete_records('socialforum_vote', array('id' => $vote->id));

            // Update vote total
            $sql = 'SELECT * '
                    . 'FROM {socialforum_vote_count} '
                    . 'WHERE socialforumid = :socialforumid '
                    . 'AND userid = :userid '
                    . 'AND (discussionid = :discussionid '
                    . 'OR postid = :postid);';
            $vote_count = $DB->get_records_sql($sql, array('socialforumid' => $this->socialforumid, 'discussionid' => $this->discussionid, 'postid' => $this->postid, 'userid' => $this->userid));
            if (!$vote_count) {
                throw new \invalid_state_exception("Social forum votes structure doesn't exist");
            }
            if (count($vote_count) > 1) {
                throw new \invalid_state_exception("More than one social forum votes structure found");
            }
            $total = reset($vote_count);
            $total->counting -= $vote->votes;
            $DB->update_record('socialforum_vote_count', $total);

            // Update votes total
            $this->data['votes'] -= $vote->votes;

            // Generate vote event
            $socialforum = $DB->get_record('socialforum', array('id' => $this->socialforumid));
            $params = array(
                'objectid' => ($this->discussionid ? $this->discussionid : $this->postid),
                'userid' => $userid,
                'relateduserid' => $this->userid,
                'context' => \context_user::instance($userid),
                'other' => array(
                    'discussionid' => $this->discussionid,
                    'socialforumid' => $this->socialforumid,
                    'socialforumtype' => $socialforum->type,
                ),
            );
            if ($this->discussionid) {
                $event = \mod_socialforum\event\discussion_relevancyvotecancelled::create($params);
            } else {
                $event = \mod_socialforum\event\post_relevancyvotecancelled::create($params);
            }
            $event->trigger();

            $this->check_relevancy(-$vote->votes);
            $this->check_mostrelevant(-$vote->votes);

            // Confirm transaction
            $dbtransaction->allow_commit();
        } catch (\Throwable $e) {
            $dbtransaction->rollback($e);
            debugging("Error: " . $e->getMessage());
            throw $e;
        }

        return $this->data['votes'];
    }

    /**
     * Recover user votes in this post or discussion
     * 
     * @param int $userid User whose votes should be retrieved
     * @return int Number of votes given by user or zero if user hasn't voted
     * @throw illegal_parameter_exception In case of invalid user id
     */
    public function get_user_votes($userid) {
        global $DB;

        if (!is_numeric($userid) || $userid <= 0) {
            throw new \invalid_parameter_exception('userid must be positive integer');
        }

        // Check if user has voted in this post or discussion
        $sql = 'SELECT * '
                . 'FROM {socialforum_vote} '
                . 'WHERE socialforumid = :socialforumid '
                . 'AND userid = :userid '
                . 'AND (discussionid = :discussionid '
                . 'OR postid = :postid);';
        $votes = $DB->get_records_sql($sql, array('socialforumid' => $this->socialforumid, 'discussionid' => $this->discussionid, 'postid' => $this->postid, 'userid' => $userid));
        if (!$votes) {
            // User hasn't voted
            return 0;
        }
        if (count($votes) > 1) {
            throw new \invalid_state_exception("User voted more than once in a social forum discussion or post");
        }
        $vote = reset($votes);
        return $vote->votes;
    }

    // Protected methods

    /**
     * Retrieve social forum votes from the database
     * 
     * @param int $socialforumid Id of social forum
     * @param int $discussionid Id of discussion
     * @param int $postid Id of post
     * @param int $userid Id of user
     * @throws invalid_parameter_exception In case of any invalid argument
     * @return bool true if social votes could be found, false otherwise
     */
    protected function retrieve_socialforum_votes($socialforumid, $discussionid = null, $postid = null, $userid) {
        global $DB;

        if (!is_numeric($socialforumid) || $socialforumid <= 0) {
            throw new \invalid_parameter_exception('socialforumid must be positive integer: ' . $socialforumid);
        }
        if ((!is_numeric($discussionid) || $discussionid <= 0) && (!is_numeric($postid) || $postid <= 0)) {
            throw new \invalid_parameter_exception('discussionid or postid must be positive integer');
        }
        if (!$discussionid && !$postid) {
            throw new \invalid_parameter_exception('discussionid and postid cannot be both null');
        }
        if ($discussionid && $postid) {
            throw new \invalid_parameter_exception('discussionid and postid cannot be both not null');
        }
        if (!is_numeric($userid) || $userid <= 0) {
            throw new \invalid_parameter_exception('userid must be positive integer: ' . $userid);
        }

        // Retrieve vote total
        $sql = 'SELECT * '
                . 'FROM {socialforum_vote_count} '
                . 'WHERE socialforumid = :socialforumid '
                . 'AND userid = :userid '
                . 'AND (discussionid = :discussionid '
                . 'OR postid = :postid);';
        $vote_count = $DB->get_records_sql($sql, array('socialforumid' => $socialforumid, 'discussionid' => $discussionid, 'postid' => $postid, 'userid' => $userid));
        if (!$vote_count) {
            return false;
        }
        if (count($vote_count) > 1) {
            throw new \invalid_state_exception("More than one social forum votes structure found");
        }
        $total = reset($vote_count);

        $this->data['socialforumid'] = $total->socialforumid;
        $this->data['discussionid'] = $total->discussionid;
        $this->data['postid'] = $total->postid;
        $this->data['votes'] = $total->counting;
        $this->data['userid'] = $total->userid;

        return true;
    }

    /**
     * Create a social forum votes structure in the database
     * 
     * @param int $socialforumid Id of social forum
     * @param int $discussionid Id of discussion
     * @param int $postid Id of post
     * @param int $userid Id of user
     * @throws invalid_parameter_exception In case of any invalid argument
     * @throws invalid_state_exception In case social forum votes already
     * exists, there's no such discussion or post or post is discussion itself
     */
    protected function create_socialforum_votes($socialforumid, $discussionid = null, $postid = null, $userid) {
        global $DB;

        if (!is_numeric($socialforumid) || $socialforumid <= 0) {
            throw new \invalid_parameter_exception('socialforumid must be positive integer: ' . $socialforumid);
        }
        if ((!is_numeric($discussionid) || $discussionid <= 0) && (!is_numeric($postid) || $postid <= 0)) {
            throw new \invalid_parameter_exception('discussionid or postid must be positive integer');
        }
        if (!$discussionid && !$postid) {
            throw new \invalid_parameter_exception('discussionid and postid cannot be both null');
        }
        if ($discussionid && $postid) {
            throw new \invalid_parameter_exception('discussionid and postid cannot be both null');
        }
        if (!is_numeric($userid) || $userid <= 0) {
            throw new \invalid_parameter_exception('userid must be positive integer: ' . $userid);
        }

        // Retrieve vote total
        $sql = 'SELECT * '
                . 'FROM {socialforum_vote_count} '
                . 'WHERE socialforumid = :socialforumid '
                . 'AND userid = :userid '
                . 'AND (discussionid = :discussionid '
                . 'OR postid = :postid);';
        $vote_count = $DB->get_records_sql($sql, array('socialforumid' => $socialforumid, 'discussionid' => $discussionid, 'postid' => $postid, 'userid' => $userid));
        if ($vote_count) {
            throw new \invalid_state_exception("Social forum votes structure already exists");
        }

        $socialforum = $DB->get_record('socialforum', array('id' => $socialforumid));
        if (!$socialforum) {
            throw new \invalid_state_exception("Social forum not found");
        }
        $user = $DB->get_record('user', array('id' => $userid));
        if (!$user) {
            throw new \invalid_state_exception("User not found");
        }
        if ($discussionid) {
            $discussion = $DB->get_record('socialforum_discussions', array('id' => $discussionid));
            if (!$discussion) {
                throw new \invalid_state_exception("Discution not found");
            }
        } else {
            $post = $DB->get_record('socialforum_posts', array('id' => $postid));
            if (!$post) {
                throw new \invalid_state_exception("Post not found");
            }
        }
        $record = array();
        $record['socialforumid'] = $this->data['socialforumid'] = $socialforumid;
        $record['discussionid'] = $this->data['discussionid'] = $discussionid;
        $record['postid'] = $this->data['postid'] = $postid;
        $record['counting'] = $this->data['votes'] = 0;
        $record['userid'] = $this->data['userid'] = $userid;
        $DB->insert_record('socialforum_vote_count', $record);
    }

    /**
     * Check if this post or discussion has become relevant or irrelevante by
     * last votes received and generate corresponding event if so
     * 
     * @param int $numvotes Number of votes received
     */
    protected function check_relevancy($numvotes) {
        global $DB;

        if ($this->votes > 0 && $numvotes >= $this->votes) {
            // $this->votes was less or equal zero before last votes
            // Generate relevant event
            $socialforum = $DB->get_record('socialforum', array('id' => $this->socialforumid));
            $params = array(
                'objectid' => ($this->discussionid ? $this->discussionid : $this->postid),
                'userid' => $this->userid,
                'relateduserid' => null,
                'context' => \context_user::instance($this->userid),
                'other' => array(
                    'discussionid' => $this->discussionid,
                    'socialforumid' => $this->socialforumid,
                    'socialforumtype' => $socialforum->type,
                ),
            );
            if ($this->discussionid) {
                $event = \mod_socialforum\event\discussion_votedrelevant::create($params);
            } else {
                $event = \mod_socialforum\event\post_votedrelevant::create($params);
            }
            $event->trigger();
        } else {
            if ($this->votes <= 0 && $numvotes < $this->votes) {
                // $this->votes was greater than zero before last votes
                // Generate relevant event
                $socialforum = $DB->get_record('socialforum', array('id' => $this->socialforumid));
                $params = array(
                    'objectid' => ($this->discussionid ? $this->discussionid : $this->postid),
                    'userid' => $this->userid,
                    'relateduserid' => null,
                    'context' => \context_user::instance($this->userid),
                    'other' => array(
                        'discussionid' => $this->discussionid,
                        'socialforumid' => $this->socialforumid,
                        'socialforumtype' => $socialforum->type,
                    ),
                );
                if ($this->discussionid) {
                    $event = \mod_socialforum\event\discussion_votedirrelevant::create($params);
                } else {
                    $event = \mod_socialforum\event\post_votedirrelevant::create($params);
                }
                $event->trigger();
            }
        }
    }

    /**
     * Check if this post has become the most relevant by last votes received
     * and generate corresponding event if so
     * 
     * @param int $numvotes Number of votes received
     */
    protected function check_mostrelevant($numvotes) {
        global $DB;

        // Only posts can be voted as the most relevant
        if ($this->discussionid) {
            return;
        }

        // Get this votes correponding post 
        $post = $DB->get_record('socialforum_posts', array('id' => $this->postid));
        if (!$post) {
            throw new \invalid_state_exception("Post not found");
        }
        // Get posts from same discussion
        $otherposts = $DB->get_records('socialforum_posts', array('discussion' => $post->discussion), '', 'id');
        $ids = '';
        foreach ($otherposts as $post) {
            $ids .= $post->id . ",";
        }
        if (!empty($ids)) {
            // Remove last ,
            $ids = substr($ids, 0, strlen($ids) - 1);
        }

        $sql = 'SELECT * '
                . 'FROM {socialforum_vote_count} '
                . 'WHERE postid IN (' . $ids . ') ';
        $othervotes = $DB->get_records_sql($sql, array());

        // Calculate maximum numver of votes of the other posts
        $maxvotes = 0;
        $max = null;
        foreach ($othervotes as $vote) {
            if ($vote->postid != $this->postid) {
                if ($vote->counting > $maxvotes) {
                    $maxvotes = $vote->counting;
                    $max = $vote;
                }
            }
        }

        // Check if it is the most relevant
        if ($this->votes > $maxvotes) {
            // Only a relevant post can be the most relevant
            if ($this->votes <= 0) {
                return;
            }
            // Check if it has become the most relevant with the last votes
            if (($this->votes - $numvotes) <= $maxvotes) {
                $socialforum = $DB->get_record('socialforum', array('id' => $this->socialforumid));
                // Generate most relevant event
                $params = array(
                    'objectid' => $this->postid,
                    'userid' => $this->userid,
                    'relateduserid' => null,
                    'context' => \context_user::instance($this->userid),
                    'other' => array(
                        'discussionid' => $this->discussionid,
                        'socialforumid' => $this->socialforumid,
                        'socialforumtype' => $socialforum->type,
                    ),
                );
                $event = \mod_socialforum\event\post_votedmostrelevant::create($params);
                $event->trigger();
                // Generate no longer most relevant event for previous most relevant
                if ($max != null) {
                    $params = array(
                        'objectid' => $max->postid,
                        'userid' => $max->userid,
                        'relateduserid' => null,
                        'context' => \context_user::instance($max->userid),
                        'other' => array(
                            'discussionid' => $this->discussionid,
                            'socialforumid' => $this->socialforumid,
                            'socialforumtype' => $socialforum->type,
                        ),
                    );
                    $event = \mod_socialforum\event\post_nolongermostrelevant::create($params);
                    $event->trigger();
                }
            }
        } else {
            // Check if it has just lost most relevant position
            if (($this->votes - $numvotes) > $maxvotes) {
                $socialforum = $DB->get_record('socialforum', array('id' => $this->socialforumid));
                // Generate no longer most relevant event
                $params = array(
                    'objectid' => $this->postid,
                    'userid' => $this->userid,
                    'relateduserid' => null,
                    'context' => \context_user::instance($this->userid),
                    'other' => array(
                        'discussionid' => $this->discussionid,
                        'socialforumid' => $this->socialforumid,
                        'socialforumtype' => $socialforum->type,
                    ),
                );
                $event = \mod_socialforum\event\post_nolongermostrelevant::create($params);
                $event->trigger();
                // Generate most relevant event for new most relevant
                if ($max != null) {
                    $params = array(
                        'objectid' => $max->postid,
                        'userid' => $max->userid,
                        'relateduserid' => null,
                        'context' => \context_user::instance($max->userid),
                        'other' => array(
                            'discussionid' => $this->discussionid,
                            'socialforumid' => $this->socialforumid,
                            'socialforumtype' => $socialforum->type,
                        ),
                    );
                    $event = \mod_socialforum\event\post_votedmostrelevant::create($params);
                    $event->trigger();
                }
            }
        }
    }

}

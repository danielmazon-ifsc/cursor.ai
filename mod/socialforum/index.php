<?php

/**
 * @package   mod_socialforum
 * @copyright 2016 Viddia (http://viddia.com.br)
 * @author    Ricardo Drummond
 */
require_once(dirname(__FILE__) . '/../../config.php');
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->dirroot . '/mod/socialforum/lib.php');
require_once($CFG->libdir . '/rsslib.php');

$id = optional_param('id', 0, PARAM_INT);                   // Course id
$subscribe = optional_param('subscribe', null, PARAM_INT);  // Subscribe/Unsubscribe all socialforums

$url = new moodle_url('/mod/socialforum/index.php', array('id' => $id));
if ($subscribe !== null) {
    require_sesskey();
    $url->param('subscribe', $subscribe);
}
$PAGE->set_url($url);

if ($id) {
    if (!$course = $DB->get_record('course', array('id' => $id))) {
        print_error('invalidcourseid');
    }
} else {
    $course = get_site();
}

require_course_login($course);
$PAGE->set_pagelayout('incourse');
$coursecontext = context_course::instance($course->id);


unset($SESSION->fromdiscussion);

$params = array(
    'context' => context_course::instance($course->id)
);
$event = \mod_socialforum\event\course_module_instance_list_viewed::create($params);
$event->add_record_snapshot('course', $course);
$event->trigger();

$strsocialforums = get_string('socialforums', 'mod_socialforum');
$strsocialforum = get_string('socialforum', 'mod_socialforum');
$strdescription = get_string('description');
$strdiscussions = get_string('discussions', 'mod_socialforum');
$strsubscribed = get_string('subscribed', 'mod_socialforum');
$strunreadposts = get_string('unreadposts', 'mod_socialforum');
$strtracking = get_string('tracking', 'mod_socialforum');
$strmarkallread = get_string('markallread', 'mod_socialforum');
$strtracksocialforum = get_string('tracksocialforum', 'mod_socialforum');
$strnotracksocialforum = get_string('notracksocialforum', 'mod_socialforum');
$strsubscribe = get_string('subscribe', 'mod_socialforum');
$strunsubscribe = get_string('unsubscribe', 'mod_socialforum');
$stryes = get_string('yes');
$strno = get_string('no');
$strrss = get_string('rss');
$stremaildigest = get_string('emaildigest');

$searchform = socialforum_search_form($course);

// Retrieve the list of socialforum digest options for later.
$digestoptions = socialforum_get_user_digest_options();
$digestoptions_selector = new single_select(new moodle_url('/mod/socialforum/maildigest.php', array(
    'backtoindex' => 1,
        )), 'maildigest', $digestoptions, null, '');
$digestoptions_selector->method = 'post';

// Start of the table for General Social Forums

$generaltable = new html_table();
$generaltable->head = array($strsocialforum, $strdescription, $strdiscussions);
$generaltable->align = array('left', 'left', 'center');

if ($usetracking = socialforum_tp_can_track_socialforums()) {
    $untracked = socialforum_tp_get_untracked_socialforums($USER->id, $course->id);

    $generaltable->head[] = $strunreadposts;
    $generaltable->align[] = 'center';

    $generaltable->head[] = $strtracking;
    $generaltable->align[] = 'center';
}

// Fill the subscription cache for this course and user combination.
\mod_socialforum\subscriptions::fill_subscription_cache_for_course($course->id, $USER->id);

$can_subscribe = is_enrolled($coursecontext);
if ($can_subscribe) {
    $generaltable->head[] = $strsubscribed;
    $generaltable->align[] = 'center';

    $generaltable->head[] = $stremaildigest . ' ' . $OUTPUT->help_icon('emaildigesttype', 'mod_socialforum');
    $generaltable->align[] = 'center';
}

if ($show_rss = (($can_subscribe || $course->id == SITEID) &&
        isset($CFG->enablerssfeeds) && isset($CFG->socialforum_enablerssfeeds) &&
        $CFG->enablerssfeeds && $CFG->socialforum_enablerssfeeds)) {
    $generaltable->head[] = $strrss;
    $generaltable->align[] = 'center';
}

$usesections = course_format_uses_sections($course->format);

$table = new html_table();

// Parse and organise all the socialforums.  Most socialforums are course modules but
// some special ones are not.  These get placed in the general socialforums
// category with the socialforums in section 0.

$socialforums = $DB->get_records_sql("
    SELECT f.*,
           d.maildigest
      FROM {socialforum} f
 LEFT JOIN {socialforum_digests} d ON d.socialforum = f.id AND d.userid = ?
     WHERE f.course = ?
    ", array($USER->id, $course->id));

$generalsocialforums = array();
$learningsocialforums = array();
$modinfo = get_fast_modinfo($course);

foreach ($modinfo->get_instances_of('socialforum') as $socialforumid => $cm) {
    if (!$cm->uservisible or ! isset($socialforums[$socialforumid])) {
        continue;
    }

    $socialforum = $socialforums[$socialforumid];

    if (!$context = context_module::instance($cm->id, IGNORE_MISSING)) {
        continue;   // Shouldn't happen
    }

    if (!has_capability('mod/socialforum:viewdiscussion', $context)) {
        continue;
    }

    // fill two type array - order in modinfo is the same as in course
    if ($socialforum->type == 'news' or $socialforum->type == 'social') {
        $generalsocialforums[$socialforum->id] = $socialforum;
    } else if ($course->id == SITEID or empty($cm->sectionnum)) {
        $generalsocialforums[$socialforum->id] = $socialforum;
    } else {
        $learningsocialforums[$socialforum->id] = $socialforum;
    }
}

// Do course wide subscribe/unsubscribe if requested
if (!is_null($subscribe)) {
    if (isguestuser() or ! $can_subscribe) {
        // There should not be any links leading to this place, just redirect.
        redirect(
                new moodle_url('/mod/socialforum/index.php', array('id' => $id)), get_string('subscribeenrolledonly', 'mod_socialforum'), null, \core\output\notification::NOTIFY_ERROR
        );
    }
    // Can proceed now, the user is not guest and is enrolled
    foreach ($modinfo->get_instances_of('socialforum') as $socialforumid => $cm) {
        $socialforum = $socialforums[$socialforumid];
        $modcontext = context_module::instance($cm->id);
        $cansub = false;

        if (has_capability('mod/socialforum:viewdiscussion', $modcontext)) {
            $cansub = true;
        }
        if ($cansub && $cm->visible == 0 &&
                !has_capability('mod/socialforum:managesubscriptions', $modcontext)) {
            $cansub = false;
        }
        if (!\mod_socialforum\subscriptions::is_forcesubscribed($socialforum)) {
            $subscribed = \mod_socialforum\subscriptions::is_subscribed($USER->id, $socialforum, null, $cm);
            $canmanageactivities = has_capability('moodle/course:manageactivities', $coursecontext, $USER->id);
            if (($canmanageactivities || \mod_socialforum\subscriptions::is_subscribable($socialforum)) && $subscribe && !$subscribed && $cansub) {
                \mod_socialforum\subscriptions::subscribe_user($USER->id, $socialforum, $modcontext, true);
            } else if (!$subscribe && $subscribed) {
                \mod_socialforum\subscriptions::unsubscribe_user($USER->id, $socialforum, $modcontext, true);
            }
        }
    }
    $returnto = socialforum_go_back_to(new moodle_url('/mod/socialforum/index.php', array('id' => $course->id)));
    $shortname = format_string($course->shortname, true, array('context' => context_course::instance($course->id)));
    if ($subscribe) {
        redirect(
                $returnto, get_string('nowallsubscribed', 'socialforum', $shortname), null, \core\output\notification::NOTIFY_SUCCESS
        );
    } else {
        redirect(
                $returnto, get_string('nowallunsubscribed', 'socialforum', $shortname), null, \core\output\notification::NOTIFY_SUCCESS
        );
    }
}

/// First, let's process the general socialforums and build up a display

if ($generalsocialforums) {
    foreach ($generalsocialforums as $socialforum) {
        $cm = $modinfo->instances['socialforum'][$socialforum->id];
        $context = context_module::instance($cm->id);

        $count = socialforum_count_discussions($socialforum, $cm, $course);

        if ($usetracking) {
            if ($socialforum->trackingtype == SOCIALFORUM_TRACKING_OFF) {
                $unreadlink = '-';
                $trackedlink = '-';
            } else {
                if (isset($untracked[$socialforum->id])) {
                    $unreadlink = '-';
                } else if ($unread = socialforum_tp_count_socialforum_unread_posts($cm, $course)) {
                    $unreadlink = '<span class="unread"><a href="view.php?f=' . $socialforum->id . '">' . $unread . '</a>';
                    $unreadlink .= '<a title="' . $strmarkallread . '" href="markposts.php?f=' .
                            $socialforum->id . '&amp;mark=read&amp;sesskey=' . sesskey() . '"><img src="' . $OUTPUT->image_url('t/markasread') . '" alt="' . $strmarkallread . '" class="iconsmall" /></a></span>';
                } else {
                    $unreadlink = '<span class="read">0</span>';
                }

                if (($socialforum->trackingtype == SOCIALFORUM_TRACKING_FORCED) && ($CFG->socialforum_allowforcedreadtracking)) {
                    $trackedlink = $stryes;
                } else if ($socialforum->trackingtype === SOCIALFORUM_TRACKING_OFF || ($USER->trackforums == 0)) {
                    $trackedlink = '-';
                } else {
                    $aurl = new moodle_url('/mod/socialforum/settracking.php', array(
                        'id' => $socialforum->id,
                        'sesskey' => sesskey(),
                    ));
                    if (!isset($untracked[$socialforum->id])) {
                        $trackedlink = $OUTPUT->single_button($aurl, $stryes, 'post', array('title' => $strnotracksocialforum));
                    } else {
                        $trackedlink = $OUTPUT->single_button($aurl, $strno, 'post', array('title' => $strtracksocialforum));
                    }
                }
            }
        }

        $socialforum->intro = shorten_text(format_module_intro('socialforum', $socialforum, $cm->id), $CFG->socialforum_shortpost);
        $socialforumname = format_string($socialforum->name, true);

        if ($cm->visible) {
            $style = '';
        } else {
            $style = 'class="dimmed"';
        }
        $socialforumlink = "<a href=\"view.php?f=$socialforum->id\" $style>" . format_string($socialforum->name, true) . "</a>";
        $discussionlink = "<a href=\"view.php?f=$socialforum->id\" $style>" . $count . "</a>";

        $row = array($socialforumlink, $socialforum->intro, $discussionlink);
        if ($usetracking) {
            $row[] = $unreadlink;
            $row[] = $trackedlink;    // Tracking.
        }

        if ($can_subscribe) {
            $row[] = socialforum_get_subscribe_link($socialforum, $context, array('subscribed' => $stryes,
                'unsubscribed' => $strno, 'forcesubscribed' => $stryes,
                'cantsubscribe' => '-'), false, false, true);

            $digestoptions_selector->url->param('id', $socialforum->id);
            if ($socialforum->maildigest === null) {
                $digestoptions_selector->selected = -1;
            } else {
                $digestoptions_selector->selected = $socialforum->maildigest;
            }
            $row[] = $OUTPUT->render($digestoptions_selector);
        }

        //If this socialforum has RSS activated, calculate it
        if ($show_rss) {
            if ($socialforum->rsstype and $socialforum->rssarticles) {
                //Calculate the tooltip text
                if ($socialforum->rsstype == 1) {
                    $tooltiptext = get_string('rsssubscriberssdiscussions', 'mod_socialforum');
                } else {
                    $tooltiptext = get_string('rsssubscriberssposts', 'mod_socialforum');
                }

                if (!isloggedin() && $course->id == SITEID) {
                    $userid = guest_user()->id;
                } else {
                    $userid = $USER->id;
                }
                //Get html code for RSS link
                $row[] = rss_get_link($context->id, $userid, 'mod_socialforum', $socialforum->id, $tooltiptext);
            } else {
                $row[] = '&nbsp;';
            }
        }

        $generaltable->data[] = $row;
    }
}


// Start of the table for Learning Social Forums
$learningtable = new html_table();
$learningtable->head = array($strsocialforum, $strdescription, $strdiscussions);
$learningtable->align = array('left', 'left', 'center');

if ($usetracking) {
    $learningtable->head[] = $strunreadposts;
    $learningtable->align[] = 'center';

    $learningtable->head[] = $strtracking;
    $learningtable->align[] = 'center';
}

if ($can_subscribe) {
    $learningtable->head[] = $strsubscribed;
    $learningtable->align[] = 'center';

    $learningtable->head[] = $stremaildigest . ' ' . $OUTPUT->help_icon('emaildigesttype', 'mod_socialforum');
    $learningtable->align[] = 'center';
}

if ($show_rss = (($can_subscribe || $course->id == SITEID) &&
        isset($CFG->enablerssfeeds) && isset($CFG->socialforum_enablerssfeeds) &&
        $CFG->enablerssfeeds && $CFG->socialforum_enablerssfeeds)) {
    $learningtable->head[] = $strrss;
    $learningtable->align[] = 'center';
}

/// Now let's process the learning socialforums

if ($course->id != SITEID) {    // Only real courses have learning socialforums
    // 'format_.'$course->format only applicable when not SITEID (format_site is not a format)
    $strsectionname = get_string('sectionname', 'format_' . $course->format);
    // Add extra field for section number, at the front
    array_unshift($learningtable->head, $strsectionname);
    array_unshift($learningtable->align, 'center');


    if ($learningsocialforums) {
        $currentsection = '';
        foreach ($learningsocialforums as $socialforum) {
            $cm = $modinfo->instances['socialforum'][$socialforum->id];
            $context = context_module::instance($cm->id);

            $count = socialforum_count_discussions($socialforum, $cm, $course);

            if ($usetracking) {
                if ($socialforum->trackingtype == SOCIALFORUM_TRACKING_OFF) {
                    $unreadlink = '-';
                    $trackedlink = '-';
                } else {
                    if (isset($untracked[$socialforum->id])) {
                        $unreadlink = '-';
                    } else if ($unread = socialforum_tp_count_socialforum_unread_posts($cm, $course)) {
                        $unreadlink = '<span class="unread"><a href="view.php?f=' . $socialforum->id . '">' . $unread . '</a>';
                        $unreadlink .= '<a title="' . $strmarkallread . '" href="markposts.php?f=' .
                                $socialforum->id . '&amp;mark=read&sesskey=' . sesskey() . '"><img src="' . $OUTPUT->image_url('t/markasread') . '" alt="' . $strmarkallread . '" class="iconsmall" /></a></span>';
                    } else {
                        $unreadlink = '<span class="read">0</span>';
                    }

                    if (($socialforum->trackingtype == SOCIALFORUM_TRACKING_FORCED) && ($CFG->socialforum_allowforcedreadtracking)) {
                        $trackedlink = $stryes;
                    } else if ($socialforum->trackingtype === SOCIALFORUM_TRACKING_OFF || ($USER->trackforums == 0)) {
                        $trackedlink = '-';
                    } else {
                        $aurl = new moodle_url('/mod/socialforum/settracking.php', array('id' => $socialforum->id));
                        if (!isset($untracked[$socialforum->id])) {
                            $trackedlink = $OUTPUT->single_button($aurl, $stryes, 'post', array('title' => $strnotracksocialforum));
                        } else {
                            $trackedlink = $OUTPUT->single_button($aurl, $strno, 'post', array('title' => $strtracksocialforum));
                        }
                    }
                }
            }

            $socialforum->intro = shorten_text(format_module_intro('socialforum', $socialforum, $cm->id), $CFG->socialforum_shortpost);

            if ($cm->sectionnum != $currentsection) {
                $printsection = get_section_name($course, $cm->sectionnum);
                if ($currentsection) {
                    $learningtable->data[] = 'hr';
                }
                $currentsection = $cm->sectionnum;
            } else {
                $printsection = '';
            }

            $socialforumname = format_string($socialforum->name, true);

            if ($cm->visible) {
                $style = '';
            } else {
                $style = 'class="dimmed"';
            }
            $socialforumlink = "<a href=\"view.php?f=$socialforum->id\" $style>" . format_string($socialforum->name, true) . "</a>";
            $discussionlink = "<a href=\"view.php?f=$socialforum->id\" $style>" . $count . "</a>";

            $row = array($printsection, $socialforumlink, $socialforum->intro, $discussionlink);
            if ($usetracking) {
                $row[] = $unreadlink;
                $row[] = $trackedlink;    // Tracking.
            }

            if ($can_subscribe) {
                $row[] = socialforum_get_subscribe_link($socialforum, $context, array('subscribed' => $stryes,
                    'unsubscribed' => $strno, 'forcesubscribed' => $stryes,
                    'cantsubscribe' => '-'), false, false, true);

                $digestoptions_selector->url->param('id', $socialforum->id);
                if ($socialforum->maildigest === null) {
                    $digestoptions_selector->selected = -1;
                } else {
                    $digestoptions_selector->selected = $socialforum->maildigest;
                }
                $row[] = $OUTPUT->render($digestoptions_selector);
            }

            //If this socialforum has RSS activated, calculate it
            if ($show_rss) {
                if ($socialforum->rsstype and $socialforum->rssarticles) {
                    //Calculate the tolltip text
                    if ($socialforum->rsstype == 1) {
                        $tooltiptext = get_string('rsssubscriberssdiscussions', 'mod_socialforum');
                    } else {
                        $tooltiptext = get_string('rsssubscriberssposts', 'mod_socialforum');
                    }
                    //Get html code for RSS link
                    $row[] = rss_get_link($context->id, $USER->id, 'mod_socialforum', $socialforum->id, $tooltiptext);
                } else {
                    $row[] = '&nbsp;';
                }
            }

            $learningtable->data[] = $row;
        }
    }
}


/// Output the page
$PAGE->navbar->add($strsocialforums);
$PAGE->set_title("$course->shortname: $strsocialforums");
$PAGE->set_heading($course->fullname);
$PAGE->set_button($searchform);
echo $OUTPUT->header();

// Show the subscribe all options only to non-guest, enrolled users
if (!isguestuser() && isloggedin() && $can_subscribe) {
    echo $OUTPUT->box_start('subscription');
    echo html_writer::tag('div', html_writer::link(new moodle_url('/mod/socialforum/index.php', array('id' => $course->id, 'subscribe' => 1, 'sesskey' => sesskey())), get_string('allsubscribe', 'mod_socialforum')), array('class' => 'helplink'));
    echo html_writer::tag('div', html_writer::link(new moodle_url('/mod/socialforum/index.php', array('id' => $course->id, 'subscribe' => 0, 'sesskey' => sesskey())), get_string('allunsubscribe', 'mod_socialforum')), array('class' => 'helplink'));
    echo $OUTPUT->box_end();
    echo $OUTPUT->box('&nbsp;', 'clearer');
}

if ($generalsocialforums) {
    echo $OUTPUT->heading(get_string('generalsocialforums', 'mod_socialforum'), 2);
    echo html_writer::table($generaltable);
}

if ($learningsocialforums) {
    echo $OUTPUT->heading(get_string('learningsocialforums', 'mod_socialforum'), 2);
    echo html_writer::table($learningtable);
}

echo $OUTPUT->footer();


<?php

/**
 * @package   mod_socialforum
 * @copyright 2016 Viddia (http://viddia.com.br)
 * @author    Ricardo Drummond
 */
require_once('../../config.php');
require_once($CFG->dirroot . '/mod/socialforum/lib.php');

$id = required_param('id', PARAM_INT);                  // course id
$search = trim(optional_param('search', '', PARAM_NOTAGS));  // search string
$page = optional_param('page', 0, PARAM_INT);   // which page to show
$perpage = optional_param('perpage', 10, PARAM_INT);   // how many per page
$showform = optional_param('showform', 0, PARAM_INT);   // Just show the form

$user = trim(optional_param('user', '', PARAM_NOTAGS));    // Names to search for
$userid = trim(optional_param('userid', 0, PARAM_INT));      // UserID to search for
$socialforumid = trim(optional_param('socialforumid', 0, PARAM_INT));      // Social ForumID to search for
$subject = trim(optional_param('subject', '', PARAM_NOTAGS)); // Subject
$phrase = trim(optional_param('phrase', '', PARAM_NOTAGS));  // Phrase
$words = trim(optional_param('words', '', PARAM_NOTAGS));   // Words
$fullwords = trim(optional_param('fullwords', '', PARAM_NOTAGS)); // Whole words
$notwords = trim(optional_param('notwords', '', PARAM_NOTAGS));   // Words we don't want

$timefromrestrict = optional_param('timefromrestrict', 0, PARAM_INT); // Use starting date
$fromday = optional_param('fromday', 0, PARAM_INT);      // Starting date
$frommonth = optional_param('frommonth', 0, PARAM_INT);      // Starting date
$fromyear = optional_param('fromyear', 0, PARAM_INT);      // Starting date
$fromhour = optional_param('fromhour', 0, PARAM_INT);      // Starting date
$fromminute = optional_param('fromminute', 0, PARAM_INT);      // Starting date
if ($timefromrestrict) {
    $calendartype = \core_calendar\type_factory::get_calendar_instance();
    $gregorianfrom = $calendartype->convert_to_gregorian($fromyear, $frommonth, $fromday);
    $datefrom = make_timestamp($gregorianfrom['year'], $gregorianfrom['month'], $gregorianfrom['day'], $fromhour, $fromminute);
} else {
    $datefrom = optional_param('datefrom', 0, PARAM_INT);      // Starting date
}

$timetorestrict = optional_param('timetorestrict', 0, PARAM_INT); // Use ending date
$today = optional_param('today', 0, PARAM_INT);      // Ending date
$tomonth = optional_param('tomonth', 0, PARAM_INT);      // Ending date
$toyear = optional_param('toyear', 0, PARAM_INT);      // Ending date
$tohour = optional_param('tohour', 0, PARAM_INT);      // Ending date
$tominute = optional_param('tominute', 0, PARAM_INT);      // Ending date
if ($timetorestrict) {
    $calendartype = \core_calendar\type_factory::get_calendar_instance();
    $gregorianto = $calendartype->convert_to_gregorian($toyear, $tomonth, $today);
    $dateto = make_timestamp($gregorianto['year'], $gregorianto['month'], $gregorianto['day'], $tohour, $tominute);
} else {
    $dateto = optional_param('dateto', 0, PARAM_INT);      // Ending date
}

$PAGE->set_pagelayout('standard');
$PAGE->set_url($FULLME); //TODO: this is very sloppy --skodak

if (empty($search)) {   // Check the other parameters instead
    if (!empty($words)) {
        $search .= ' ' . $words;
    }
    if (!empty($userid)) {
        $search .= ' userid:' . $userid;
    }
    if (!empty($socialforumid)) {
        $search .= ' forumid:' . $socialforumid;
    }
    if (!empty($user)) {
        $search .= ' ' . SOCIALFORUM_clean_search_terms($user, 'user:');
    }
    if (!empty($subject)) {
        $search .= ' ' . SOCIALFORUM_clean_search_terms($subject, 'subject:');
    }
    if (!empty($fullwords)) {
        $search .= ' ' . SOCIALFORUM_clean_search_terms($fullwords, '+');
    }
    if (!empty($notwords)) {
        $search .= ' ' . SOCIALFORUM_clean_search_terms($notwords, '-');
    }
    if (!empty($phrase)) {
        $search .= ' "' . $phrase . '"';
    }
    if (!empty($datefrom)) {
        $search .= ' datefrom:' . $datefrom;
    }
    if (!empty($dateto)) {
        $search .= ' dateto:' . $dateto;
    }
    $individualparams = true;
} else {
    $individualparams = false;
}

if ($search) {
    $search = socialforum_clean_search_terms($search);
}

if (!$course = $DB->get_record('course', array('id' => $id))) {
    print_error('invalidcourseid');
}

require_course_login($course);

$params = array(
    'context' => $PAGE->context,
    'other' => array('searchterm' => $search)
);

$event = \mod_socialforum\event\course_searched::create($params);
$event->trigger();

$socialforum = $DB->get_record("socialforum", array("id" => $socialforumid));
if (!$cm = get_coursemodule_from_instance("socialforum", $socialforumid, $id)) {
    print_error('invalidcoursemodule');
}
$url = new moodle_url('/mod/socialforum/view.php', array('id' => $cm->id));
$socialforumlink = html_writer::tag('h2', html_writer::tag('a', $socialforum->name, array('href' => $url)));
$strsearch = get_string("search", "mod_socialforum");
$strsearchresults = get_string("searchresults", "mod_socialforum");
$strpage = get_string("page");

$searchterms = str_replace('socialforumid:', 'instance:', $search);
$searchterms = explode(' ', $searchterms);

$searchform = socialforum_search_form($socialforum);

$PAGE->navbar->add($strsearch, new moodle_url('/mod/socialforum/search.php', array('id' => $course->id)));
$PAGE->navbar->add($strsearchresults);
if (!$posts = socialforum_search_posts($searchterms, $course->id, $page * $perpage, $perpage, $totalcount)) {
    $PAGE->set_title($strsearchresults);
    $PAGE->set_heading($course->fullname);
    echo $OUTPUT->header();
    echo $socialforumlink;
    echo $OUTPUT->heading($strsearchresults, 3);
    echo $OUTPUT->heading(get_string("noposts", "mod_socialforum"), 4);

    if (!$individualparams) {
        $words = $search;
    }

    echo $searchform;

    echo $OUTPUT->footer();
    exit;
}

//including this here to prevent it being included if there are no search results
require_once($CFG->dirroot . '/rating/lib.php');

//set up the ratings information that will be the same for all posts
$ratingoptions = new stdClass();
$ratingoptions->component = 'mod_socialforum';
$ratingoptions->ratingarea = 'post';
$ratingoptions->userid = $USER->id;
$ratingoptions->returnurl = $PAGE->url->out(false);
$rm = new rating_manager();

$PAGE->set_title($strsearchresults);
$PAGE->set_heading($course->fullname);
echo $OUTPUT->header();
echo $socialforumlink;
echo $OUTPUT->heading("$strsearchresults: $totalcount", 3);

$url = new moodle_url('search.php', array('search' => $search, 'id' => $course->id, 'perpage' => $perpage));
echo $OUTPUT->paging_bar($totalcount, $page, $perpage, $url);

//added to implement highlighting of search terms found only in HTML markup
//fiedorow - 9/2/2005
$strippedsearch = str_replace('user:', '', $search);
$strippedsearch = str_replace('subject:', '', $strippedsearch);
$strippedsearch = str_replace('&quot;', '', $strippedsearch);
$searchterms = explode(' ', $strippedsearch);    // Search for words independently
foreach ($searchterms as $key => $searchterm) {
    if (preg_match('/^\-/', $searchterm)) {
        unset($searchterms[$key]);
    } else {
        $searchterms[$key] = preg_replace('/^\+/', '', $searchterm);
    }
}
$strippedsearch = implode(' ', $searchterms);    // Rebuild the string

foreach ($posts as $post) {

    // Replace the simple subject with the three items forum name -> thread name -> subject
    // (if all three are appropriate) each as a link.
    if (!$discussion = $DB->get_record('socialforum_discussions', array('id' => $post->discussion))) {
        print_error('invaliddiscussionid', 'socialforum');
    }
    if (!$socialforum = $DB->get_record('socialforum', array('id' => "$discussion->socialforum"))) {
        print_error('invalidforumid', 'socialforum');
    }

    if (!$cm = get_coursemodule_from_instance('socialforum', $socialforum->id)) {
        print_error('invalidcoursemodule');
    }

    $post->subject = highlight($strippedsearch, $post->subject);
    $discussion->name = highlight($strippedsearch, $discussion->name);

    $fullsubject = "<a href=\"view.php?f=$socialforum->id\">" . format_string($socialforum->name, true) . "</a>";
    if ($socialforum->type != 'single') {
        $fullsubject .= " -> <a href=\"discuss.php?d=$discussion->id\">" . format_string($discussion->name, true) . "</a>";
        if ($post->parent != 0) {
            $fullsubject .= " -> <a href=\"discuss.php?d=$post->discussion&amp;parent=$post->id\">" . format_string($post->subject, true) . "</a>";
        }
    }

    $post->subject = $fullsubject;
    $post->subjectnoformat = true;

    //add the ratings information to the post
    //Unfortunately seem to have do this individually as posts may be from different forums
    if ($socialforum->assessed != RATING_AGGREGATE_NONE) {
        $modcontext = context_module::instance($cm->id);
        $ratingoptions->context = $modcontext;
        $ratingoptions->items = array($post);
        $ratingoptions->aggregate = $socialforum->assessed; //the aggregation method
        $ratingoptions->scaleid = $socialforum->scale;
        $ratingoptions->assesstimestart = $socialforum->assesstimestart;
        $ratingoptions->assesstimefinish = $socialforum->assesstimefinish;
        $postswithratings = $rm->get_ratings($ratingoptions);

        if ($postswithratings && count($postswithratings) == 1) {
            $post = $postswithratings[0];
        }
    }

    // Identify search terms only found in HTML markup, and add a warning about them to
    // the start of the message text. However, do not do the highlighting here. SOCIALFORUM_print_post
    // will do it for us later.
    $missing_terms = "";

    $options = new stdClass();
    $options->trusted = $post->messagetrust;
    $post->message = highlight($strippedsearch, format_text($post->message, $post->messageformat, $options, $course->id), 0, '<fgw9sdpq4>', '</fgw9sdpq4>');

    foreach ($searchterms as $searchterm) {
        if (preg_match("/$searchterm/i", $post->message) && !preg_match('/<fgw9sdpq4>' . $searchterm . '<\/fgw9sdpq4>/i', $post->message)) {
            $missing_terms .= " $searchterm";
        }
    }

    $post->message = str_replace('<fgw9sdpq4>', '<span class="highlight">', $post->message);
    $post->message = str_replace('</fgw9sdpq4>', '</span>', $post->message);

    if ($missing_terms) {
        $strmissingsearchterms = get_string('missingsearchterms', 'mod_socialforum');
        $post->message = '<p class="highlight2">' . $strmissingsearchterms . ' ' . $missing_terms . '</p>' . $post->message;
    }

    // Prepare a link to the post in context, to be displayed after the forum post.
    $fulllink = "<a href=\"discuss.php?d=$post->discussion#p$post->id\">" . get_string("postincontext", "mod_socialforum") . "</a>";

    // Message is now html format.
    if ($post->messageformat != FORMAT_HTML) {
        $post->messageformat = FORMAT_HTML;
    }

    // Now pring the post.
    socialforum_print_post($post, $discussion, $socialforum, $cm, $course, false, false, false, $fulllink, '', -99, false);
}

echo $OUTPUT->paging_bar($totalcount, $page, $perpage, $url);
echo $searchform;

echo $OUTPUT->footer();

/**
 * This function takes each word out of the search string, makes sure they are at least
 * two characters long and returns an string of the space-separated search
 * terms.
 *
 * @param string $words String containing space-separated strings to search for.
 * @param string $prefix String to prepend to the each token taken out of $words.
 * @return string The filtered search terms, separated by spaces.
 * @todo Take the hardcoded limit out of this function and put it into a user-specified parameter.
 */
function socialforum_clean_search_terms($words, $prefix = '') {
    $searchterms = explode(' ', $words);
    foreach ($searchterms as $key => $searchterm) {
        if ($prefix) {
            $searchterms[$key] = $prefix . $searchterm;
        }
    }
    return trim(implode(' ', $searchterms));
}

/**
 * Retrieve a list of the forums that this user can view.
 *
 * @param stdClass $course The Course to use.
 * @return array A set of formatted forum names stored against the forum id.
 */
function socialforum_menu_list($course) {
    $menu = array();

    $modinfo = get_fast_modinfo($course);
    if (empty($modinfo->instances['socialforum'])) {
        return $menu;
    }

    foreach ($modinfo->instances['socialforum'] as $cm) {
        if (!$cm->uservisible) {
            continue;
        }
        $context = context_module::instance($cm->id);
        if (!has_capability('mod/socialforum:viewdiscussion', $context)) {
            continue;
        }
        $menu[$cm->instance] = format_string($cm->name);
    }

    return $menu;
}

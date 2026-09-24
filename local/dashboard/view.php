<?php

require_once('../../config.php');
require_once('./lib.php');

require_login();

$PAGE->set_url($CFG->wwwroot . '/local/dashboard/view.php', array());

$systemcontext = context_system::instance();
$personalcontext = context_user::instance($USER->id);

if (is_siteadmin()) {
    $PAGE->set_pagelayout('admin');
} else {
    $PAGE->set_pagelayout('standard');
}
$PAGE->set_context($personalcontext);

$strdashboard = get_string('dashboard', 'local_dashboard');
$PAGE->set_title($strdashboard);
$PAGE->set_heading($strdashboard);

// JS must be queued BEFORE the header is emitted so the loader can include it
// in the page output. Calling $PAGE->requires->js() after header has been
// printed is a no-op for the current render and only happens to work for
// subsequent pages via a side-effect.
$PAGE->requires->js('/local/dashboard/dashboard.js');
echo $OUTPUT->header();
echo local_dashboard_view($USER);
echo $OUTPUT->footer();

<?php

require_once('../../config.php');
require_once('./lib.php');

$page = max(1, optional_param('page', 1, PARAM_INT));

require_login();

$PAGE->set_url('/local/notifications/view.php', array('page' => $page));

$systemcontext = context_system::instance();
$personalcontext = context_user::instance($USER->id);

$PAGE->set_pagelayout('admin');
$PAGE->set_context($personalcontext);

$strnotifications = get_string('notifications', 'local_notifications');
$PAGE->set_title($strnotifications);
$PAGE->set_heading($strnotifications);

echo $OUTPUT->header();
echo local_notifications_print_banner();
echo local_notifications_print_content($USER->id, $page);
echo $OUTPUT->footer();

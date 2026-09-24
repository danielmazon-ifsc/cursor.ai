<?php

defined('MOODLE_INTERNAL') || die();

define('MAX_NOTIFICATIONS_PER_PAGE', 8);

/** @var array<string, array{0:int,1:array}> Request cache for notification lists. */
$GLOBALS['_local_notifications_usercache'] = [];

/**
 * Allowed notification components shown in the PDI center.
 *
 * @return string[]
 */
function local_notifications_allowed_components(): array {
    return ['mod_cquiz', 'local_coin', 'local_autobadge', 'local_studypace'];
}

/**
 * Build SQL fragment and params for allowed components.
 *
 * @return array{0:string,1:array}
 */
function local_notifications_component_sql(): array {
    global $DB;
    $components = local_notifications_allowed_components();
    return $DB->get_in_or_equal($components, SQL_PARAMS_NAMED, 'cmp');
}

/**
 * @param string $contexturl Raw value from {notifications}.contexturl
 * @return string Safe href for anchors
 */
function local_notifications_normalize_url($contexturl): string {
    global $CFG;

    $contexturl = trim((string) $contexturl);
    if ($contexturl === '') {
        return (new moodle_url('/'))->out(false);
    }
    if (preg_match('#^https?://#i', $contexturl)) {
        if (strpos($contexturl, $CFG->wwwroot) === 0) {
            return $contexturl;
        }
        return (new moodle_url('/'))->out(false);
    }
    if ($contexturl[0] === '/') {
        return $CFG->wwwroot . $contexturl;
    }
    return (new moodle_url('/' . ltrim($contexturl, '/')))->out(false);
}

/**
 * @param string $relativepix Path under wwwroot, e.g. /local/notifications/pix/...
 * @return string
 */
function local_notifications_pix_url($relativepix): string {
    global $CFG;
    return $CFG->wwwroot . $relativepix;
}

/**
 * @param int $userid
 * @return int Unread count across allowed components (site-wide).
 */
function local_notifications_count_unread($userid): int {
    global $DB;

    list($insql, $params) = local_notifications_component_sql();
    $params['userid'] = $userid;

    return (int) $DB->count_records_sql(
        'SELECT COUNT(1)
           FROM {notifications} n
          WHERE n.useridto = :userid
            AND n.component ' . $insql . '
            AND n.timeread IS NULL',
        $params
    );
}

/**
 * @param int $userid
 * @return int Total notifications for allowed components.
 */
function local_notifications_count_total($userid): int {
    global $DB;

    list($insql, $params) = local_notifications_component_sql();
    $params['userid'] = $userid;

    return (int) $DB->count_records_sql(
        'SELECT COUNT(1)
           FROM {notifications} n
          WHERE n.useridto = :userid
            AND n.component ' . $insql,
        $params
    );
}

/**
 * Map DB rows to notification objects for display.
 *
 * @param array $notificationrecords
 * @return array
 */
function local_notifications_map_records(array $notificationrecords): array {
    $notifications = [];
    foreach ($notificationrecords as $notificationrecord) {
        $newnotification = new stdClass();
        $newnotification->id = $notificationrecord->id;
        $newnotification->time = $notificationrecord->datetime;
        switch ($notificationrecord->component) {
            case 'mod_cquiz':
                $newnotification->iconurl = local_notifications_pix_url('/local/notifications/pix/icon-grade.jpg');
                break;
            case 'local_coin':
                $newnotification->iconurl = local_notifications_pix_url('/local/notifications/pix/icon-key.png');
                break;
            case 'local_autobadge':
                $newnotification->iconurl = local_notifications_pix_url('/local/notifications/pix/icon-badge.png');
                break;
            case 'local_studypace':
                $newnotification->iconurl = local_notifications_pix_url('/local/notifications/pix/icon-level.png');
                break;
            default:
                continue 2;
        }
        $newnotification->url = local_notifications_normalize_url($notificationrecord->contexturl);
        $newnotification->shortmessage = format_string($notificationrecord->subject);
        $newnotification->longmessage = format_text($notificationrecord->message, FORMAT_PLAIN);
        $newnotification->unread = empty($notificationrecord->timeread);
        $notifications[] = $newnotification;
    }
    return $notifications;
}

/**
 * @param int $userid
 * @param int $limit 0 = no limit
 * @param int $offset
 * @return array{0:int,1:array} [unread in slice - legacy, notifications]
 */
function local_notifications_get_user_last_notifications($userid, $limit = 0, $offset = 0) {
    global $DB;

    $cachekey = $userid . ':' . $limit . ':' . $offset;
    if (isset($GLOBALS['_local_notifications_usercache'][$cachekey])) {
        return $GLOBALS['_local_notifications_usercache'][$cachekey];
    }

    list($insql, $params) = local_notifications_component_sql();
    $params['userid'] = $userid;

    $sql = 'SELECT n.id, n.timecreated AS datetime, n.component, n.subject, n.fullmessage AS message, '
            . 'n.contexturl, n.timeread '
            . 'FROM {notifications} n '
            . 'WHERE n.useridto = :userid AND n.component ' . $insql
            . ' ORDER BY n.timecreated DESC';

    if ($limit > 0) {
        $notificationrecords = $DB->get_records_sql($sql, $params, $offset, $limit);
    } else {
        $notificationrecords = $DB->get_records_sql($sql, $params);
    }

    $notifications = local_notifications_map_records($notificationrecords);
    $numunread = 0;
    foreach ($notifications as $notification) {
        if ($notification->unread) {
            ++$numunread;
        }
    }

    $result = [$numunread, $notifications];
    $GLOBALS['_local_notifications_usercache'][$cachekey] = $result;
    return $result;
}

function local_notifications_print_banner() {
    $html = '';
    $html .= '<!-- Banner BEGIN -->';
    $html .= html_writer::start_div('page-section border-bottom-2', array(
                'style' => 'height: 220px; background-color: #0E4242 !important;'
    ));
    $html .= html_writer::start_div('narrow-page container h-100');
    $html .= html_writer::start_div('d-flex flex-column flex-lg-row align-items-center h-100 justify-content-start');
    $html .= html_writer::start_div('d-flex flex-column flex-md-row align-items-center align-items-md-center flex mb-16pt mb-lg-0 text-center text-md-left');
    $html .= html_writer::start_div('avatar rounded-circle mr-md-16pt', array(
                'style' => 'background-color: #37B6B5 !important; width: 5.125rem; height: 5.125rem;'
    ));
    $html .= html_writer::start_tag('i', array(
                'class' => 'icon fa fa-bell',
                'style' => 'font-size: 48px; color: #F5FEFF !important; margin-top: 1rem; margin-left: 1.1rem;'
    ));
    $html .= html_writer::end_tag('i');
    $html .= html_writer::end_div();
    $html .= html_writer::start_div('mr-auto w-50');
    $html .= html_writer::start_tag('h1', array(
                'class' => 'pt-3',
                'style' => 'color: #F5FEFF !important; line-height: 1.0;'
    ));
    $html .= get_string('notificationscenter', 'local_notifications');
    $html .= html_writer::end_tag('h1');
    $html .= html_writer::end_div();
    $html .= html_writer::end_div();
    $html .= html_writer::start_div('ml-lg-16pt');
    $html .= html_writer::start_div('d-flex flex-column flex-sm-row align-items-center justify-content-start');
    $dashboardurl = new moodle_url('/local/dashboard/view.php');
    $html .= html_writer::start_tag('a', array(
                'href' => $dashboardurl->out(false),
                'class' => 'btn btn-outline-white btn-rounded mb-16pt mb-sm-0 mr-sm-16pt'
    ));
    $html .= get_string('checkmydashboard', 'local_notifications');
    $html .= html_writer::start_tag('i', array(
                'class' => 'icon fa fa-user',
                'style' => 'margin-right: 0; margin-left: .5rem;'
    ));
    $html .= html_writer::end_tag('i');
    $html .= html_writer::end_tag('a');
    $html .= html_writer::end_div();
    $html .= html_writer::end_div();
    $html .= html_writer::end_div();
    $html .= html_writer::end_div();
    $html .= html_writer::end_div();
    $html .= '<!-- Banner END -->';
    return $html;
}

function local_notifications_time_to_text($time) {
    $timestr = str_replace("De", "de", ucwords(date(get_string('datetimeformat', 'local_notifications'), $time)));
    return $timestr;
}

function local_notifications_filter_notifications($notifications, $notificationstart, $notificationsend, $markasread, $userid = 0) {
    $filterednotifications = array();
    $pos = 0;
    foreach ($notifications as $notification) {
        if ($pos >= $notificationstart && $pos <= $notificationsend) {
            $filterednotifications[] = $notification;
        }
        ++$pos;
    }
    if ($markasread) {
        local_notifications_mark_as_read($filterednotifications, $userid);
    }
    return $filterednotifications;
}

/**
 * Mark notification rows as read for one recipient only.
 *
 * @param array $notifications Display objects with an id property.
 * @param int $userid Recipient ({notifications}.useridto).
 */
function local_notifications_mark_as_read($notifications, $userid) {
    global $DB;

    $userid = (int) $userid;
    if ($userid <= 0 || empty($notifications)) {
        return;
    }
    $ids = [];
    foreach ($notifications as $notification) {
        if (!empty($notification->id)) {
            $ids[] = (int) $notification->id;
        }
    }
    if (empty($ids)) {
        return;
    }
    list($insql, $params) = $DB->get_in_or_equal($ids, SQL_PARAMS_NAMED, 'nid');
    $params['now'] = time();
    $params['userid'] = $userid;
    $DB->execute(
        "UPDATE {notifications}
            SET timeread = :now
          WHERE timeread IS NULL
            AND useridto = :userid
            AND id $insql",
        $params
    );
}

function local_notifications_get_most_recent_notifications($userid, $maxnotifications = 4): array {
    $numunread = local_notifications_count_unread($userid);
    list(, $notifications) = local_notifications_get_user_last_notifications(
        $userid,
        max($maxnotifications * 3, $maxnotifications),
        0
    );
    $recentnotifications = local_notifications_filter_notifications($notifications, 0, $maxnotifications - 1, false);
    return array($numunread, $recentnotifications);
}

function local_notifications_print_content($userid, $page = 1) {
    $page = max(1, (int) $page);
    $offset = ($page - 1) * MAX_NOTIFICATIONS_PER_PAGE;
    $total = local_notifications_count_total($userid);
    $numpages = max(1, (int) ceil($total / MAX_NOTIFICATIONS_PER_PAGE));

    $html = '';
    $html .= '<!-- Content BEGIN -->';
    $html .= html_writer::start_div('narrow-page container');
    $html .= html_writer::start_div('pt-32pt');
    $html .= html_writer::end_div();
    $html .= html_writer::start_div('page-section');
    $html .= html_writer::start_div('container page__container');
    $html .= html_writer::start_div('row');
    $html .= html_writer::start_div('col-lg-12');
    $html .= html_writer::start_div('page-separator');
    $html .= html_writer::start_div('page-separator__text');
    $html .= get_string('lastnotifications', 'local_notifications');
    $html .= html_writer::end_div();
    $html .= html_writer::end_div();
    $html .= html_writer::start_div('mb-24pt');

    list(, $pagenotifications) = local_notifications_get_user_last_notifications(
        $userid,
        MAX_NOTIFICATIONS_PER_PAGE,
        $offset
    );
    local_notifications_mark_as_read($pagenotifications, $userid);

    foreach ($pagenotifications as $notification) {
        $iconurl = isset($notification->iconurl) ? clean_param((string) $notification->iconurl, PARAM_URL) : '';
        $linkurl = isset($notification->url) ? clean_param((string) $notification->url, PARAM_URL) : '';
        $html .= '<!-- Notification BEGIN -->';
        $html .= html_writer::start_div('notification card posts-card d-flex ' . ($notification->unread ? ' unread' : ' read'));
        $html .= html_writer::start_div('posts-card__content d-flex align-items-center flex-wrap');
        $html .= html_writer::start_div('notification-icon mr-3 flex-shrink-0');
        if ($linkurl !== '') {
            $html .= html_writer::start_tag('a', ['href' => $linkurl]);
        }
        if ($iconurl !== '') {
            $html .= html_writer::tag('img', '', [
                'src' => $iconurl,
                'alt' => '',
                'class' => 'notification-icon__img rounded-circle',
            ]);
        }
        if ($linkurl !== '') {
            $html .= html_writer::end_tag('a');
        }
        $html .= html_writer::end_div();
        $html .= html_writer::start_div('posts-card__title flex d-flex flex-column');
        if ($linkurl !== '') {
            $html .= html_writer::start_tag('a', [
                'href' => $linkurl,
                'class' => 'card-title mr-3',
                'style' => 'font-weight: bolder;',
            ]);
            $html .= $notification->shortmessage;
            $html .= html_writer::end_tag('a');
        } else {
            $html .= html_writer::tag('span', $notification->shortmessage, [
                'class' => 'card-title mr-3',
                'style' => 'font-weight: bolder;',
            ]);
        }
        $html .= html_writer::start_tag('small', array(
                    'class' => 'text-50'
        ));
        $html .= html_writer::start_div('mr-3 text-50 posts-card__date');
        $html .= html_writer::start_tag('small');
        $html .= local_notifications_time_to_text($notification->time);
        $html .= html_writer::end_tag('small');
        $html .= html_writer::end_div();
        $html .= html_writer::end_tag('small');
        $html .= html_writer::end_div();
        $html .= html_writer::start_div('pr-32pt text-50 flex');
        $html .= html_writer::start_tag('small');
        $html .= $notification->longmessage;
        $html .= html_writer::end_tag('small');
        $html .= html_writer::end_div();
        $html .= html_writer::end_div();
        $html .= html_writer::end_div();
        $html .= '<!-- Notification END -->';
    }
    $html .= html_writer::end_div();
    $html .= html_writer::start_tag('ul', array(
                'class' => 'pagination justify-content-start pagination-xsm m-0'
    ));
    if ($page > 1) {
        $html .= html_writer::start_tag('li', array(
                    'class' => 'page-item'
        ));
        $html .= html_writer::start_tag('a', array(
                    'class' => 'page-link',
                    'href' => '?page=' . ($page - 1)
        ));
        $html .= html_writer::start_tag('i', array(
                    'class' => 'icon fa fa-chevron-left'
        ));
        $html .= html_writer::end_tag('i');
        $html .= html_writer::end_tag('a');
        $html .= html_writer::end_tag('li');
    }
    for ($i = 1; $i <= $numpages; ++$i) {
        $html .= html_writer::start_tag('li', array(
                    'class' => 'page-item' . ($i == $page ? ' active' : '')
        ));
        $html .= html_writer::start_tag('a', array(
                    'class' => 'page-link',
                    'href' => '?page=' . $i
        ));
        $html .= $i;
        $html .= html_writer::end_tag('a');
        $html .= html_writer::end_tag('li');
    }
    if ($page < $numpages) {
        $html .= html_writer::start_tag('li', array(
                    'class' => 'page-item'
        ));
        $html .= html_writer::start_tag('a', array(
                    'class' => 'page-link',
                    'href' => '?page=' . ($page + 1)
        ));
        $html .= html_writer::start_tag('i', array(
                    'class' => 'icon fa fa-chevron-right'
        ));
        $html .= html_writer::end_tag('i');
        $html .= html_writer::end_tag('a');
        $html .= html_writer::end_tag('li');
    }
    $html .= html_writer::end_tag('ul');
    $html .= html_writer::end_div();
    $html .= html_writer::end_div();
    $html .= html_writer::end_div();
    $html .= html_writer::end_div();
    $html .= html_writer::end_div();
    $html .= '<!-- Content END -->';
    return $html;
}

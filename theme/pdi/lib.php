<?php

defined('MOODLE_INTERNAL') || die();

function theme_pdi_prepare_url($url) {
    $preparedurl = '';
    $urlparts = explode('/', $url);
    for ($i = 3; $i < count($urlparts); ++$i) {
        $preparedurl .= '/' . $urlparts[$i];
    }
    return urlencode($preparedurl);
}

function theme_pdi_is_cm_completed($userid, $cmid) {
    global $DB;
    $record = $DB->get_record('course_modules_completion', array('coursemoduleid' => $cmid, 'userid' => $userid));
    if ($record) {
        return ($record->completionstate == COMPLETION_COMPLETE || $record->completionstate == COMPLETION_COMPLETE_FAIL || $record->completionstate == COMPLETION_COMPLETE_PASS);
    } else {
        return false;
    }
}

/**
 * Page-specific CSS files under /theme/pdi/style/ (served via extrasheet.php).
 *
 * @return string[] sheet names without .css extension
 */
function theme_pdi_extra_sheet_names(): array {
    return [
        'home',
        'format_specialization',
        'avaliacao',
        'local_profile',
        'block_onboarding',
        'mod_scorm',
        'format_conferenceroom',
        'format_saladeconferencias',
        'format_videogallery',
        'mod_video',
        'mod_cquiz',
        'local_notifications',
        'local_dashboard',
    ];
}

/**
 * Whether a course module is a reaction/satisfaction evaluation page.
 *
 * @param cm_info|null $cm
 * @return bool
 */
function theme_pdi_is_avaliacao_reacao_cm(?cm_info $cm): bool {
    if ($cm === null) {
        return false;
    }
    if (!in_array($cm->modname, ['page', 'survey', 'questionnaire'], true)) {
        return false;
    }

    $name = core_text::strtolower(strip_tags($cm->get_formatted_name()));
    foreach ([
        'reação',
        'reacao',
        'avaliação de reação',
        'avaliacao de reacao',
        'página avaliação',
        'pagina avaliacao',
        'avaliação de satisfação',
        'avaliacao de satisfacao',
    ] as $needle) {
        if (strpos($name, $needle) !== false) {
            return true;
        }
    }

    return false;
}

/**
 * Whether the current page is a reaction/satisfaction evaluation activity.
 *
 * @param moodle_page $page
 * @return bool
 */
function theme_pdi_is_avaliacao_reacao_page(moodle_page $page): bool {
    return theme_pdi_is_avaliacao_reacao_cm($page->cm ?? null);
}

/**
 * Extra stylesheets for this page (loaded via page_init; not in $THEME->sheets).
 *
 * @param moodle_page $page
 * @return string[] sheet names without .css
 */
function theme_pdi_resolve_extra_sheets(moodle_page $page): array {
    $bodyid = $page->bodyid ?? '';
    $sheets = [];

    $rules = [
        'home' => ['page-site-index'],
        'format_specialization' => ['page-course-view-specialization'],
        'avaliacao' => ['page-course-view-specialization'],
        'local_profile' => ['page-local-profile-edit'],
        // Onboarding runs on the My Moodle page (/my/, body page-my-index).
        'block_onboarding' => ['page-my-index', 'page-local-dashboard-view', 'page-site-index'],
        'mod_scorm' => ['page-mod-scorm-player'],
        'format_conferenceroom' => ['page-course-view-conferenceroom'],
        'format_saladeconferencias' => ['page-course-view-saladeconferencias'],
        'format_videogallery' => ['page-course-view-videogallery'],
        'mod_video' => ['page-mod-video-view'],
        'mod_cquiz' => ['page-mod-cquiz-view', 'page-mod-cquiz-attempt', 'page-mod-cquiz-review'],
        'local_notifications' => ['page-local-notifications-view'],
        'local_dashboard' => ['page-local-dashboard-view'],
    ];

    foreach ($rules as $sheet => $bodyids) {
        if (in_array($bodyid, $bodyids, true)) {
            $sheets[] = $sheet;
        }
    }

    return array_unique($sheets);
}

/**
 * Load page-specific CSS instead of bundling every sheet on every request.
 *
 * @param moodle_page $page
 */
function theme_pdi_page_init(moodle_page $page) {
    $rev = theme_get_revision();

    if (theme_pdi_is_avaliacao_reacao_page($page)) {
        $page->add_body_class('pdi-avaliacao-reacao');
    }

    $sheets = theme_pdi_resolve_extra_sheets($page);
    if (theme_pdi_is_avaliacao_reacao_page($page) && !in_array('avaliacao', $sheets, true)) {
        $sheets[] = 'avaliacao';
    }

    foreach ($sheets as $sheet) {
        $page->requires->css(new moodle_url('/theme/pdi/extrasheet.php', [
            'sheet' => $sheet,
            'rev' => $rev,
        ]));
    }
}

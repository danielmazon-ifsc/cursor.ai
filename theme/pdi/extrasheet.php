<?php
// This file is part of Moodle - http://moodle.org/
//
// Serves page-specific theme CSS with [[pix:]] / [[font:]] placeholders resolved.
// Raw files under /theme/pdi/style/ are linked via $PAGE->requires->css() from
// theme_pdi_page_init(); without this script those placeholders reach the browser
// literally and break backgrounds (404 on /style/[[pix:...]]).

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

$sheet = required_param('sheet', PARAM_ALPHANUMEXT);
$rev = optional_param('rev', '', PARAM_RAW);

if (!in_array($sheet, theme_pdi_extra_sheet_names(), true)) {
    header('HTTP/1.1 404 Not Found');
    die('/* unknown sheet */');
}

$path = $CFG->dirroot . '/theme/pdi/style/' . $sheet . '.css';
if (!is_readable($path)) {
    header('HTTP/1.1 404 Not Found');
    die('/* missing sheet */');
}

$theme = theme_config::load('pdi');
$css = file_get_contents($path);
$css = $theme->post_process($css);

$etag = '"' . sha1($rev . '|' . $sheet . '|' . $css) . '"';

header('Content-Type: text/css; charset=utf-8');
header('Cache-Control: public, max-age=2592000');
header('ETag: ' . $etag);

if (!empty($_SERVER['HTTP_IF_NONE_MATCH']) && trim($_SERVER['HTTP_IF_NONE_MATCH']) === $etag) {
    header('HTTP/1.1 304 Not Modified');
    exit;
}

echo $css;

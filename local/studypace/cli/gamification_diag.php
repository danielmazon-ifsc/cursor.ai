<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * CLI: print gamification diagnostic for one user (same logic as Recalcular UI).
 *
 * Usage (from Moodle root):
 *   php local/studypace/cli/gamification_diag.php --userid=136
 *
 * @package   local_studypace
 * @copyright 2025 Viddia (http://viddia.com.br)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');
require_once($CFG->dirroot . '/local/studypace/classes/studypace.php');
require_once($CFG->dirroot . '/course/format/specialization/lib.php');

list($options, $unrecognized) = cli_get_params([
    'help' => false,
    'userid' => 0,
], [
    'h' => 'help',
    'u' => 'userid',
]);

if ($unrecognized) {
    $unrecognized = implode(' / ', $unrecognized);
    cli_error(get_string('cliunknowoption', 'admin', $unrecognized));
}

if ($options['help'] || empty($options['userid'])) {
    $help = <<<EOF
Diagnóstico de chaves e medalhas (local_studypace).

Opções:
  --userid=ID   ID do usuário (obrigatório)
  -h, --help    Esta ajuda

Exemplo:
  php local/studypace/cli/gamification_diag.php --userid=136

EOF;
    echo $help;
    exit(0);
}

$userid = (int) $options['userid'];
$user = $DB->get_record('user', ['id' => $userid, 'deleted' => 0], 'id, firstname, lastname, username');
if (!$user) {
    cli_error('Usuário não encontrado ou excluído: ' . $userid);
}

cli_heading('Gamificação — ' . fullname($user) . ' (id=' . $userid . ')');

$summary = \local_studypace\studypace::get_user_gamification_summary($userid);
cli_writeln('Chaves (saldo total): ' . $summary->total_keys);
cli_writeln('Medalhas emitidas: ' . $summary->total_badges);
cli_writeln('Gatilho planned_completion: '
    . ($summary->coin_trigger_standard !== null ? '+' . $summary->coin_trigger_standard : 'não configurado'));
cli_writeln('Gatilho planned_completion_high_grade: '
    . ($summary->coin_trigger_highgrade !== null ? '+' . $summary->coin_trigger_highgrade : 'não configurado'));
if (!$summary->triggers_configured) {
    cli_writeln('');
    cli_writeln('AVISO: triggersjson do local_coin não lista os eventos planned_completion.');
}

$eixos = \format_specialization::get_courses_in_format(true);
if (empty($eixos)) {
    cli_writeln('Nenhum curso no formato specialization encontrado.');
    exit(0);
}

cli_writeln('');
cli_writeln(str_pad('Eixo', 28) . str_pad('Prazo', 20) . str_pad('Concluído', 20)
    . str_pad('Prazo?', 8) . str_pad('Evento', 42) . str_pad('Nota', 6) . 'Situação');
cli_writeln(str_repeat('-', 130));

foreach ($eixos as $eixo) {
    $g = \local_studypace\studypace::get_gamification_diagnostic($userid, (int) $eixo->id);
    if (empty($g->valid)) {
        continue;
    }
    $eventkey = $g->planned_event_key;
    $eventlabel = [
        'none' => '—',
        'incomplete' => 'incompleto',
        'late' => 'FORA DO PRAZO',
        'standard' => 'planned_completion',
        'high_grade' => 'planned_completion_high_grade',
        'would_standard' => 'fecharia: planned_completion',
        'would_high_grade' => 'fecharia: high_grade',
    ][$eventkey] ?? $eventkey;

    $ontime = ($g->completed_on_time === true) ? 'Sim' : (($g->completed_on_time === false) ? 'Não' : '—');

    if ($g->gamification_expected && $g->gamification_delivered) {
        $status = 'OK';
    } else if ($g->gamification_expected && !$g->gamification_delivered) {
        $status = 'PENDENTE';
    } else if ($g->planned_event_key === 'incomplete') {
        $status = $g->mandatory_done . '/' . $g->mandatory_total . ' CMs';
    } else {
        $status = 'N/A';
    }

    $name = \core_text::strlen($eixo->fullname) > 26
        ? \core_text::substr($eixo->fullname, 0, 24) . '..'
        : $eixo->fullname;

    cli_writeln(str_pad($name, 28)
        . str_pad($g->estimatedcompletiontxt, 20)
        . str_pad($g->actualcompletiontxt, 20)
        . str_pad($ontime, 8)
        . str_pad($eventlabel, 42)
        . str_pad($g->grade > 0 ? round($g->grade) . '%' : '—', 6)
        . $status);

    cli_writeln('  Medalha: ' . $g->badge_expected_name
        . ($g->badge_expected_exists ? ($g->badge_issued ? ' [concedida]' : ' [existe, não concedida]') : ' [NÃO EXISTE]'));
    if ($g->gamification_expected) {
        $expected = ($g->planned_event_key === 'high_grade') ? $g->coin_trigger_highgrade : $g->coin_trigger_standard;
        cli_writeln('  Chaves: esperado +' . ($expected ?? '?') . ', creditado +' . $g->coins_credited_course . ' neste curso');
    }
}

cli_writeln('');
cli_writeln('Para reparar progresso + ver tabelas na web: Admin → Recalcular Ritmo de Estudo → usuário ' . $userid);

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
 * CLI: list users with on-time Eixo completions but missing keys and/or badges.
 *
 * @package   local_studypace
 * @copyright 2026 Viddia (http://viddia.com.br)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');
require_once($CFG->dirroot . '/local/studypace/classes/gamification_gaps.php');

use local_studypace\gamification_gaps;

list($options, $unrecognized) = cli_get_params([
    'help' => false,
    'userid' => 0,
    'format' => 'summary',
    'output' => '',
    'only' => gamification_gaps::FILTER_ANY,
], [
    'h' => 'help',
    'u' => 'userid',
    'f' => 'format',
    'o' => 'output',
]);

if ($unrecognized) {
    $unrecognized = implode(' / ', $unrecognized);
    cli_error(get_string('cliunknowoption', 'admin', $unrecognized));
}

if (!in_array($options['only'], gamification_gaps::allowed_filters(), true)) {
    cli_error('Valor inválido para --only. Use: ' . implode(', ', gamification_gaps::allowed_filters()));
}

$allowedformats = ['summary', 'table', 'csv'];
if (!in_array($options['format'], $allowedformats, true)) {
    cli_error('Valor inválido para --format. Use: ' . implode(', ', $allowedformats));
}

if ($options['help']) {
    $help = <<<EOF
Lista alunos com Eixo concluído NO PRAZO mas sem chave e/ou medalha registrada.

Interface web equivalente: Admin → PDI – Ritmo de estudo → Lacunas de gamificação
(/local/studypace/gamification_gaps.php)

Opções:
  --format=summary|table|csv   Formato de saída (padrão: summary)
  --only=any|both|badge|coins  Filtrar tipo de lacuna (padrão: any)
  --userid=ID                  Restringir a um usuário
  --output=/caminho/arquivo    Destino CSV (obrigatório se --format=csv)
  -h, --help                   Esta ajuda

Exemplos:
  php local/studypace/cli/gamification_gaps.php
  php local/studypace/cli/gamification_gaps.php --only=both
  php local/studypace/cli/gamification_gaps.php --format=csv --output=/tmp/gaps.csv

EOF;
    echo $help;
    exit(0);
}

@set_time_limit(0);
raise_memory_limit(MEMORY_HUGE);

$userid = (int) $options['userid'];
$gaps = gamification_gaps::find($userid, $options['only']);
gamification_gaps::enrich($gaps);
$summary = gamification_gaps::summarize($gaps);

if ($options['format'] === 'summary') {
    cli_heading('Lacunas de gamificação (Eixos concluídos no prazo)');
    cli_writeln('Gatilhos local_coin (planned_completion): '
        . (gamification_gaps::triggers_configured() ? 'configurados' : 'NÃO configurados ou JSON inválido'));
    cli_writeln('Filtro --only: ' . $options['only']);
    cli_writeln('');
    cli_writeln('Total de lacunas (usuário × Eixo): ' . $summary->totalrows);
    cli_writeln('Usuários distintos afetados: ' . $summary->usercount);
    cli_writeln('');
    cli_writeln('Por tipo:');
    cli_writeln('  Sem medalha E sem chaves: ' . $summary->bytype[gamification_gaps::FILTER_BOTH]);
    cli_writeln('  Só medalha em falta:       ' . $summary->bytype[gamification_gaps::FILTER_BADGE]);
    cli_writeln('  Só chaves em falta:        ' . $summary->bytype[gamification_gaps::FILTER_COINS]);
    cli_writeln('');

    if (!empty($summary->bycourse)) {
        cli_writeln('Por Eixo:');
        cli_writeln(str_pad('Curso', 36) . str_pad('Lacunas', 10) . 'Usuários');
        cli_writeln(str_repeat('-', 60));
        foreach ($summary->bycourse as $info) {
            $label = $info->shortname . ' — ' . $info->name;
            if (\core_text::strlen($label) > 34) {
                $label = \core_text::substr($label, 0, 32) . '..';
            }
            cli_writeln(str_pad($label, 36)
                . str_pad((string) $info->rows, 10)
                . count($info->users));
        }
    } else {
        cli_writeln('Nenhuma lacuna encontrada com os filtros atuais.');
    }

    cli_writeln('');
    cli_writeln('Detalhe: --format=table ou --format=csv --output=/tmp/gaps.csv');
    exit(0);
}

if ($options['format'] === 'csv') {
    if (empty($options['output'])) {
        cli_error('Use --output=/caminho/arquivo.csv com --format=csv');
    }
    if (file_put_contents($options['output'], gamification_gaps::build_csv($gaps)) === false) {
        cli_error('Não foi possível escrever em: ' . $options['output']);
    }
    cli_writeln('CSV gravado: ' . $options['output'] . ' (' . count($gaps) . ' linhas)');
    exit(0);
}

cli_heading('Lacunas de gamificação');
if (empty($gaps)) {
    cli_writeln('Nenhuma lacuna encontrada.');
    exit(0);
}

cli_writeln(str_pad('User', 10) . str_pad('Eixo', 22) . str_pad('Concluído', 12)
    . str_pad('Nota', 6) . str_pad('Medalha', 28) . str_pad('Chaves', 14) . 'Tipo');
cli_writeln(str_repeat('-', 100));

foreach ($gaps as $gap) {
    $coins = ($gap->coins_credited > 0 ? '+' . $gap->coins_credited : '0')
        . ' / ' . ($gap->coins_expected ?? '?');
    $badge = ($gap->has_badge ? 'sim' : 'NÃO')
        . ' (' . ($gap->badge_expected ?? '?') . ')';
    $eixolabel = $gap->courseshortname;
    if (\core_text::strlen($eixolabel) > 20) {
        $eixolabel = \core_text::substr($eixolabel, 0, 18) . '..';
    }
    cli_writeln(str_pad((string) $gap->userid, 10)
        . str_pad($eixolabel, 22)
        . str_pad(userdate($gap->actualcompletion, '%Y-%m-%d'), 12)
        . str_pad(($gap->grade ?? '—') . '%', 6)
        . str_pad($badge, 28)
        . str_pad($coins, 14)
        . $gap->gap_type);
}

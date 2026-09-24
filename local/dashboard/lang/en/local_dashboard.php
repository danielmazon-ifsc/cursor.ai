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
 * English strings for local_dashboard.
 *
 * @package   local_dashboard
 * @copyright 2026 Viddia (http://viddia.com.br)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Dashboard';
$string['dashboard'] = 'Dashboard';
$string['courseduration'] = 'Course completion estimation';
$string['coursedurationlong'] = 'The plan {$a->planname} sets an estimation of {$a->planmonths} months to complete the course.';
$string['months'] = '{$a} months';
$string['planname'] = 'Plan {$a}';
$string['pannel'] = 'Pannel';
$string['gamificationrules'] = 'Gamification Rules';
$string['preferences'] = 'Preferences';
$string['yourprogress'] = 'Your Progress';
$string['yourlearningjourney'] = 'Your Learning Journey';
$string['trackyourprogress'] = 'Track your progress in each specialization';
$string['eixograde'] = '{$a}';
$string['specializationscompleted'] = '{$a} Specialization(s) Completed';
$string['specializationinprogress'] = '{$a} in Progress';
$string['levelinprogress'] = 'Level {$a}';
$string['ofspecialization'] = 'Of {$a}';
$string['progressdescription'] = '{$a->name}, you are in Level {$a->level} of {$a->specialization}. Keep the pace!';
$string['newlevels'] = '+{$a} levels this month';
$string['overview'] = 'Overview';
$string['onaverage'] = 'on Average';
$string['averagedesc'] = 'The Average is based on your grades in the quizes.';
$string['doingwell'] = 'You are doing well!';
$string['keys'] = 'Keys';
$string['newthismonth'] = '+{$a} this month';
$string['completedalllevels'] = 'You completed all levels of {$a}';
$string['currentspecialization'] = 'You are in Level {$a->level} of {$a->specializationname}';
$string['confroomalwaysavailable'] = '{$a} is always available - you can visit it any time';
$string['confroomcompleted'] = 'You finished every session in the {$a}';
$string['confroomlabel_line1'] = 'Conference';
$string['confroomlabel_line2'] = 'Room';
$string['seeallnotifications'] = 'See all notifications';
$string['lastnotifications'] = 'Last notifications';
$string['youroverallprogress'] = 'Your overall progress';
$string['overallprogresshowto'] = 'The percentage counts each Eixo (and Sala de Conferências) by its workload hours only after activities are completed and the minimum grade of {$a}% is reached for the course and for every graded discipline (section).';
$string['platformgamificationrules'] = 'Platform gamification rules';
$string['watchtolearn'] = 'Watch the video below to understand how the platform gamification works, including how to get keys, conquer badges and advance in rankings.';
$string['locked'] = 'Locked';
$string['available'] = '{$a} available';
$string['belowmingrade'] = 'You finished {$a}, but did not reach the minimum grade of 60% for the course or for one of its disciplines';
$string['notenroled'] = 'Not Enroled';
$string['place'] = 'Place';
$string['amongstudents'] = ' among {$a} students';
$string['similarperformance'] = 'of students have a similat performance';
$string['coursecompleted'] = '% of the Course completed.';
$string['expectedprogress'] = 'According to your plan, the expected progress would be {$a}%.';
$string['badpace'] = 'Increase your pace! You have already completed {$a->currentprogress}% of the course, but, according to your current plan ({$a->months} months), the expected progress would be {$a->expectedprogress}%.';
$string['goodpace'] = 'Keep your pace! You have already completed {$a->currentprogress}% of the course, and, according to your current plan ({$a->months} months), the expected progress would be {$a->expectedprogress}%.';
$string['badgeboard'] = 'Badge Board';
$string['badgedesc'] = 'Your accomplishments in the specializations of the course';

// Admin ranking report.
$string['admincategory'] = 'Dashboard';
$string['rankingpageheading'] = 'Student ranking';
$string['rankingintro'] = 'Coin ranking of all active students, ordered best-first. Visible to administrators only.';
$string['rankingtotalstudents'] = 'Active students: {$a}';
$string['rankingdownloadcsv'] = 'Download CSV';
$string['rankingnostudents'] = 'No active students found.';
$string['rankingcolposition'] = 'Position';
$string['rankingcolname'] = 'Name';
$string['rankingcolusername'] = 'Username';
$string['rankingcolemail'] = 'Email';
$string['rankingcolcoins'] = 'Coins';
$string['rankingcolfinalgrade'] = 'Final grade';
$string['rankinggradenote'] = 'Each Eixo column shows the consolidated course grade for students who completed that Eixo; an Eixo that was not completed counts as 0. The final grade is the sum of all Eixo grades. Students are ranked by coins; ties on coins are broken by the higher final grade.';

// Admin course workload (progress weighting).
$string['workloadpageheading'] = 'Course workload hours';
$string['workloadintro'] = 'Set the workload (in hours) for each Eixo and Sala de Conferências course. '
    . 'Dashboard progress (current and expected) is weighted proportionally: a course with twice the hours '
    . 'contributes twice as much to the overall percentage. Leave a field empty or zero to exclude a course '
    . 'from the weighting. If no workloads are configured, the system falls back to 90% for Eixos and 10% '
    . 'for Sala (split equally within each group).';
$string['workloadeixos'] = 'Eixos (format_specialization)';
$string['workloadsala'] = 'Sala de Conferências (format_saladeconferencias)';
$string['workloadhours'] = 'Workload hours';
$string['workloadhours_help'] = 'Number of hours for this course. Used to calculate its share of the overall dashboard progress percentage.';
$string['workloadnocourses'] = 'No courses found for this format.';
$string['workloadinvalid'] = 'Enter a non-negative number.';
$string['workloadsaved'] = 'Course workload hours saved.';
$string['workloadfallbacknotice'] = 'No workload hours configured yet. Progress uses the default weighting: 90% for Eixos and 10% for Sala de Conferências.';
$string['workloadsummary'] = '{$a->courses} course(s) configured with a total of {$a->hours} hours. Progress percentages are proportional to these values.';

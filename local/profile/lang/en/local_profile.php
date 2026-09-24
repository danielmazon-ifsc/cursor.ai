<?php

/**
 * Plugin strings (en)
 *
 * @package   local_profile
 */
$string['pluginname'] = 'Profile';
$string['task_enrol_next_eixo'] = 'Retry auto-enrolment into the next Eixo';
$string['profileupdated'] = 'Profile updated!';
$string['profileupdatefailed'] = 'Profile update failed!';
$string['nothingtoupdate'] = 'Nothing to save.';
$string['profile'] = 'Profile';
$string['editprofile'] = 'Edit profile';
$string['email'] = 'Email';
$string['city'] = 'City';
$string['country'] = 'Country';
$string['profilepicture'] = 'Profile picture';
$string['chooseotheravatar'] = 'Choose other avatar';
$string['chooseyouravatar'] = 'Choose your avatar';
$string['back'] = 'Back';
$string['newpassword'] = 'New password';
$string['repeatpassword'] = 'Repeat password';
$string['minpasswordlength'] = 'Password must have at least 4 characters';
$string['repeatpasswordmustmatch'] = 'Repeated password must match password';
$string['crontask'] = 'Enrol users in new specialization courses';
$string['configurations'] = 'Configurations';
$string['configurationstxt'] = "Change your profile's avatar or your study plan in this page.";
$string['savechanges'] = 'Save Changes';
$string['studyplan'] = 'Study Plan';
$string['changeonlyonce'] = 'You can change your study plan.';
$string['changeanytime'] = 'You can change your study plan.';
$string['cannotchange'] = 'You can no longer change your study plan because you already changed it once.';
$string['yourcurrentplan'] = 'Your current plan: ';
$string['plannotselected'] = 'Not selected yet';
$string['selectnewplan'] = 'Select a new plan';
$string['plantxt'] = 'Plan {$a->name} ({$a->months} months)';
$string['choosethebest'] = 'Choose the plan which is the best fit for your learning pace.';
$string['plan'] = 'Plan';
$string['profilepicture'] = 'Profile picture';
$string['chooseanotheravatar'] = 'Choose another avatar';
$string['chooseyouravatar'] = 'Choose your avatar';
$string['membersince'] = 'Member since {$a}';
$string['dateformat'] = '%B %d, %Y';

// Admin: Eixo auto-progression diagnostic.
$string['admincategory'] = 'Profile';
$string['progcheckpageheading'] = 'Eixo auto-progression check';
$string['progcheckintro'] = 'Lists students who completed an Eixo but were never enrolled into the next visible Eixo (an auto-progression failure). You can repair an individual student or everyone in the report; repairs are idempotent and only create the missing enrolment.';
$string['progcheckuseridlabel'] = 'User ID (optional)';
$string['progcheckuseridplaceholder'] = 'All students';
$string['progcheckrunbutton'] = 'Run check';
$string['progchecknone'] = 'No auto-progression failures found. Every student who completed an Eixo is enrolled in the next one.';
$string['progcheckfound'] = '{$a} auto-progression failure(s) found.';
$string['progcheckdownloadcsv'] = 'Download CSV';
$string['progcheckrepair'] = 'Repair';
$string['progcheckrepairall'] = 'Repair all';
$string['progcheckrepairallconfirm'] = 'Re-run the auto-enrolment for every student in the report? This only creates missing next-Eixo enrolments.';
$string['progcheckrepairedone'] = 'Repair finished for user {$a->userid}: {$a->created} new enrolment(s) created.';
$string['progcheckrepairedall'] = 'Repair finished: {$a} new enrolment(s) created.';
$string['progcheckcoluser'] = 'Student';
$string['progcheckcolusername'] = 'Username';
$string['progcheckcolemail'] = 'Email';
$string['progcheckcolcompleted'] = 'Completed Eixo';
$string['progcheckcolcompletedon'] = 'Completed on';
$string['progcheckcolnext'] = 'Missing next Eixo';
$string['progcheckcolretry'] = 'Automatic retry';
$string['progcheckcolaction'] = 'Action';
$string['progcheckretryqueued'] = 'Queued for {$a->time} (attempt {$a->attempt})';
$string['progcheckretrynone'] = 'None scheduled';

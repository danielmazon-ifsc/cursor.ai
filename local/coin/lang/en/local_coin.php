<?php

// General strings
$string['pluginname'] = 'Coins';
$string['crontask'] = 'Coins expiration';

// Settings strings
$string['coindenomination'] = 'Coin denomination';
$string['coindenomination_desc'] = 'Name by which coins will be referenciated thoughout the site.';
$string['coindenomination_default'] = 'Coin';
$string['coindenomination_plural'] = 'Coin denomination plural';
$string['coindenomination_plural_desc'] = 'Plural of name by which coins will be referenciated thoughout the site.';
$string['coindenomination_plural_default'] = 'Coins';
$string['expirationperiod'] = 'Coins expiration period';
$string['expirationperiod_desc'] = 'Number of days in which coins will loose their value after being acquired.';
$string['coinsawarding'] = 'Events that award coins';
$string['coinsawarding_desc'] = 'Set which events award coins and how many coins each one awards, along with whether coins should be awarded to the user executing the action or the user affected by it. This field should contain a JSON formatted associative array, each row containing an event name, amount of coins to be awarded and who is going to receive the award (userid/relateduserid). Example: {"\\\\\\core\\\\\\event\\\\\\course_viewed":{"amount":1,"userid":"userid"},"\\\\\\mod_page\\\\\\event\\\\\\course_module_viewed":{"amount":5,"userid":"relateduserid"}}';
$string['coinsawarding_default'] = '{"\\\\local_studypace\\\\event\\\\planned_completion":{"amount":10,"userid":"relateduserid"},"\\\\local_studypace\\\\event\\\\planned_completion_high_grade":{"amount":20,"userid":"relateduserid"}}';
$string['coinscancelling'] = 'Events that cancel awarded coins';
$string['coinscancelling_desc'] = 'Set which events will cancel coins previoulsy awarded and whether coins should be withdrawn from the user executing the action or the user affected by it. This field should contain a JSON formatted associative array, each row containing an event name and who is going to receive the award (userid/relateduserid). Example: {"\\\\\\core\\\\\\event\\\\\\course_viewed":{"userid":"userid"},"\\\\\\mod_page\\\\\\event\\\\\\course_module_viewed":{"userid":"relateduserid"}}';
$string['coinscancelling_default'] = '{}';

// Messages to user
$string['awardmessage'] = 'Message to be sent in coin awarding';
$string['awardmessage_desc'] = 'Message to be sent to the user whenever he/she gets new coins. May be plain text or Moodle-auto format, including HTML tags and multi-lang tags. The following placeholders may be included in the message: Amount of coins {$a->amount}, Reason for coin awarding {$a->reason}, User fullname {$a->fullname} and User first name {$a->firstname}';
$string['awardmessage_default'] = 'Hi {$a->firstname}, 
    
You have been awarded {$a->amount} new coin(s). Reason? {$a->reason}

Your learning team';
$string['awardsubject'] = 'Subject of the message to be sent in coin awarding';
$string['awardsubject_desc'] = 'Subject of the message to be sent to the user whenever he/she gets new coins. In plain text format, it may contain the following placeholders: Amount of coins {$a->amount}, Reason for coin awarding {$a->reason}, User fullname {$a->fullname} and User first name {$a->firstname}';
$string['awardsubject_default'] = 'New coins awarded to you';
$string['cancelmessage'] = 'Message to be sent in coin cancelling';
$string['cancelmessage_desc'] = 'Message to be sent to the user whenever he/she has coins cancelled. May be plain text or Moodle-auto format, including HTML tags and multi-lang tags. The following placeholders may be included in the message: Amount of coins {$a->amount}, Reason for coin awarding {$a->reason}, User fullname {$a->fullname} and User first name {$a->firstname}';
$string['cancelmessage_default'] = 'Hi {$a->firstname},

We are letting you know that {$a->amount} key(s) have been removed from your stack.

{$a->reason}

Your learning team';
$string['cancelreason_expired'] = 'These keys expired after the validity period configured for the course.';
$string['cancelmessage_expired'] = 'Hi {$a->firstname},

{$a->amount} key(s) expired and were removed from your stack, according to the course validity period.

Your learning team';
$string['cancelmessage_expired_setting'] = 'Message when keys expire (scheduled task)';
$string['cancelmessage_expired_setting_desc'] = 'Sent at midnight when cron removes old keys from the ledger. Placeholders: {$a->firstname}, {$a->amount}, {$a->fullname}.';
$string['cancelsubject'] = 'Subject of the message to be sent in coin cancelling';
$string['cancelsubject_desc'] = 'Subject of the message to be sent to the user whenever he/she has coins cancelled. In plain text format, it may contain the following placeholders: Amount of coins {$a->amount}, Reason for coin awarding {$a->reason}, User fullname {$a->fullname} and User first name {$a->firstname}';
$string['cancelsubject_default'] = 'Coins withdrawn from your account';
$string['coinconfig'] = 'Coins Configuration';
$string['coinledger'] = 'Coins Ledger';
$string['coinsbalance'] = 'Coins Balance';

// Event strings
$string['eventcoinsawarded'] = 'Coins awarded';
$string['eventcoinscancelled'] = 'Coins cancelled';
$string['eventcoinssubtracted'] = 'Coins subtracted';

// Message strings
$string['messageprovider:coinstackchanges'] = 'Messages sent to inform users of changes in their coin stack';
// Deprecated: kept for older language caches; no longer used in code.
$string['secret'] = 'Secret';

// Report strings
$string['coinsledger'] = 'Coins Ledger';
$string['startdate'] = 'Start date';
$string['finishdate'] = 'Finish date';
$string['getledger'] = 'Get ledger';
$string['coins'] = 'Coins';
$string['action'] = 'Action';

// Upgrade health checks (admin UI).
$string['upgradecheckspageheading'] = 'Upgrade verification (coins)';
$string['upgradechecksintro'] = 'Use this page instead of manual SQL before and after upgrading gamification plugins (Coins / Study Pace).';
$string['upgradechecksversionsheading'] = 'Installed versions';
$string['upgradechecksplugincol'] = 'Plugin';
$string['upgradechecksversioncol'] = 'Database version';
$string['upgradechecksversionshint'] = 'After copying files, open Site administration → Notifications to run the upgrade. Expected local_coin: 2026052641 or newer.';
$string['upgradechecksbeforeheading'] = 'Before upgrade';
$string['upgradechecksbeforeintro'] = 'Duplicate ledger rows (coin_ledger) sharing the same user + course + action. Back up the database before upgrading Coins if any are found.';
$string['upgradechecksnoduplicates'] = 'No duplicate ledger rows found. Safe to proceed with the upgrade.';
$string['upgradechecksduplicatesfound'] = 'Found {$a->groups} duplicate group(s) ({$a->extras} extra ledger row(s)).';
$string['upgradechecksduplicatehint'] = 'The Coins plugin upgrade removes extra rows and rebuilds balances automatically.';
$string['upgradechecksafterheading'] = 'After upgrade';
$string['upgradechecksafterintro'] = 'Compares coin_stack balances with the sum of coin_ledger amounts per user and course.';
$string['upgradechecksbalancesok'] = 'Balances match the ledger.';
$string['upgradechecksmismatchfound'] = '{$a->count} user/course pair(s) with balance different from the ledger sum.';
$string['upgradechecksorphansfound'] = '{$a->count} pair(s) with ledger credits but no stack row — use the button below to fix.';
$string['upgradechecksusercol'] = 'User';
$string['upgradecheckscoursecol'] = 'Course (ID)';
$string['upgradechecksactioncol'] = 'Action (ledger)';
$string['upgradechecksduplicatecountcol'] = 'Duplicate rows';
$string['upgradechecksstackcol'] = 'Stack balance';
$string['upgradechecksledgercol'] = 'Ledger sum';
$string['upgradecheckssampletruncated'] = 'Showing the first 50 rows only.';
$string['upgradechecksrebuildhint'] = 'If mismatches remain after the automatic upgrade, use this button to rebuild all balances from the ledger.';
$string['upgradechecksrebuildbutton'] = 'Rebuild balances from ledger';
$string['upgradechecksrebuilddone'] = 'Rebuild complete: {$a->updated} updated, {$a->inserted} inserted, {$a->zeroed} zeroed.';
$string['upgradechecksnextheading'] = 'Next steps';
$string['upgradechecksnextintro'] = 'After balances look correct:';
$string['upgradecheckslinknotifications'] = 'Site administration → Notifications (apply pending upgrades)';
$string['upgradecheckslinkgaps'] = 'Study Pace → Gamification gaps (report before batch repair)';
$string['upgradecheckslinkrepair'] = 'Study Pace → Recalculate and restore keys (per-user repair)';
$string['upgradecheckslinkpurge'] = 'Site administration → Purge caches';

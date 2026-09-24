<?php

// General strings
$string['pluginname'] = 'Automatic Badges';
$string['messageprovider:autobadgecontrol'] = 'Automatic badges awarding';

// Messages to user
$string['awardmessage'] = 'Message to be sent in badge awarding';
$string['awardmessage_desc'] = 'Message to be sent to the user whenever he/she get a new badge. May be plain text or Moodle-auto format, including HTML tags and multi-lang tags. The following placeholders may be included in the message: Badge name {$a->badgename}, Badge description {$a->badgedescription}, Course name {$a->coursename}, User fullname {$a->fullname} and User first name {$a->firstname}';
$string['awardmessage_default'] = 'Hi {$a->firstname}, 
    
You have been awarded badge {$a->badgedescription} in course {$a->coursename}

Your learning team';
$string['awardsubject'] = 'Subject of the message to be sent in badge awarding';
$string['awardsubject_desc'] = 'Subject of the message to be sent to the user whenever he/she gets a new badge. In plain text format, it may contain the following placeholders: Badge name {$a->badgename}, Badge description {$a->badgedescription}, Course name {$a->coursename}, User fullname {$a->fullname} and User first name {$a->firstname}';
$string['awardsubject_default'] = 'New badge awarded to you';

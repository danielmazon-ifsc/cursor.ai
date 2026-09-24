<?php

/**
 * Strings for component 'socialforum', language 'en', branch 'MOODLE_20_STABLE'
 *
 * @package   mod_socialforum
 * @copyright 2016 Viddia (http://viddia.com.br)
 * @author    Ricardo Drummond
 */
$string['activityoverview'] = 'There are new social forum posts';
$string['addanewdiscussion'] = 'Add a new discussion topic';
$string['addanewquestion'] = 'Add a new question';
$string['addanewtopic'] = 'Add a new topic';
$string['advancedsearch'] = 'Advanced search';
$string['allsocialforums'] = 'All social forums';
$string['allowdiscussions'] = 'Can a {$a} post to this social forum?';
$string['allowsallsubscribe'] = 'This social forum allows everyone to choose whether to subscribe or not';
$string['allowsdiscussions'] = 'This social forum allows each person to start one discussion topic.';
$string['allsubscribe'] = 'Subscribe to all social forums';
$string['allunsubscribe'] = 'Unsubscribe from all social forums';
$string['alreadyfirstpost'] = 'This is already the first post in the discussion';
$string['anyfile'] = 'Any file';
$string['areaattachment'] = 'Attachments';
$string['areapost'] = 'Messages';
$string['attachment'] = 'Attachment';
$string['attachment_help'] = 'You can optionally attach one or more files to a social forum post. If you attach an image, it will be displayed after the message.';
$string['attachmentnopost'] = 'You cannot export attachments without a post id';
$string['attachments'] = 'Attachments';
$string['attachmentswordcount'] = 'Attachments and word count';
$string['blockafter'] = 'Post threshold for blocking';
$string['blockafter_help'] = 'This setting specifies the maximum number of posts which a user can post in the given time period. Users with the capability mod/socialforum:postwithoutthrottling are exempt from post limits.';
$string['blockperiod'] = 'Time period for blocking';
$string['blockperiod_help'] = 'Students can be blocked from posting more than a given number of posts in a given time period. Users with the capability mod/socialforum:postwithoutthrottling are exempt from post limits.';
$string['blockperioddisabled'] = 'Don\'t block';
$string['blogsocialforum'] = 'Standard social forum displayed in a blog-like format';
$string['bynameondate'] = 'by {$a->name} - {$a->date}';
$string['cannotadd'] = 'Could not add the discussion for this social forum';
$string['cannotadddiscussion'] = 'Adding discussions to this social forum requires group membership.';
$string['cannotadddiscussionall'] = 'You do not have permission to add a new discussion topic for all participants.';
$string['cannotaddsubscriber'] = 'Could not add subscriber with id {$a} to this social forum!';
$string['cannotaddteachersocialforumto'] = 'Could not add converted teacher social forum instance to section 0 in the course';
$string['cannotcreatediscussion'] = 'Could not create new discussion';
$string['cannotcreateinstanceforteacher'] = 'Could not create new course module instance for the teacher social forum';
$string['cannotdeletepost'] = 'You can\'t delete this post!';
$string['cannoteditposts'] = 'You can\'t edit other people\'s posts!';
$string['cannotfinddiscussion'] = 'Could not find the discussion in this social forum';
$string['cannotfindfirstpost'] = 'Could not find the first post in this social forum';
$string['cannotfindorcreatesocialforum'] = 'Could not find or create a main news social forum for the site';
$string['cannotfindparentpost'] = 'Could not find top parent of post {$a}';
$string['cannotmovefromsinglesocialforum'] = 'Cannot move discussion from a simple single discussion social forum';
$string['cannotmovenotvisible'] = 'Social Forum not visible';
$string['cannotmovetonotexist'] = 'You can\'t move to that social forum - it doesn\'t exist!';
$string['cannotmovetonotfound'] = 'Target social forum not found in this course.';
$string['cannotmovetosinglesocialforum'] = 'Cannot move discussion to a simple single discussion social forum';
$string['cannotpurgecachedrss'] = 'Could not purge the cached RSS feeds for the source and/or destination social forum(s) - check your file permissionssocialforums';
$string['cannotremovesubscriber'] = 'Could not remove subscriber with id {$a} from this social forum!';
$string['cannotreply'] = 'You cannot reply to this post';
$string['cannotsplit'] = 'Discussions from this social forum cannot be split';
$string['cannotsubscribe'] = 'Sorry, but you must be a group member to subscribe.';
$string['cannottrack'] = 'Could not stop tracking that social forum';
$string['cannotunsubscribe'] = 'Could not unsubscribe you from that social forum';
$string['cannotupdatepost'] = 'You can not update this post';
$string['cannotviewpostyet'] = 'You cannot read other students questions in this discussion yet because you haven\'t posted';
$string['cannotviewusersposts'] = 'There are no posts made by this user that you are able to view.';
$string['cleanreadtime'] = 'Mark old posts as read hour';
$string['clicktounsubscribe'] = 'You are subscribed to this discussion. Click to unsubscribe.';
$string['clicktosubscribe'] = 'You are not subscribed to this discussion. Click to subscribe.';
$string['completiondiscussions'] = 'Student must create discussions:';
$string['completiondiscussionsgroup'] = 'Require discussions';
$string['completiondiscussionshelp'] = 'requiring discussions to complete';
$string['completionposts'] = 'Student must post discussions or replies:';
$string['completionpostsgroup'] = 'Require posts';
$string['completionpostshelp'] = 'requiring discussions or replies to complete';
$string['completionreplies'] = 'Student must post replies:';
$string['completionrepliesgroup'] = 'Require replies';
$string['completionreplieshelp'] = 'requiring replies to complete';
$string['configcleanreadtime'] = 'The hour of the day to clean old posts from the \'read\' table.';
$string['configdigestmailtime'] = 'People who choose to have emails sent to them in digest form will be emailed the digest daily. This setting controls which time of day the daily mail will be sent (the next cron that runs after this hour will send it).';
$string['configdisplaymode'] = 'The default display mode for discussions if one isn\'t set.';
$string['configenablerssfeeds'] = 'This switch will enable the possibility of RSS feeds for all social forums.  You will still need to turn feeds on manually in the settings for each social forum.';
$string['configenabletimedposts'] = 'Set to \'yes\' if you want to allow setting of display periods when posting a new social forum discussion.';
$string['configlongpost'] = 'Any post over this length (in characters not including HTML) is considered long. Posts displayed on the site front page, social format course pages, or user profiles are shortened to a natural break somewhere between the socialforum_shortpost and socialforum_longpost values.';
$string['configmanydiscussions'] = 'Maximum number of discussions shown in a social forum per page';
$string['configmaxattachments'] = 'Default maximum number of attachments allowed per post.';
$string['configmaxbytes'] = 'Default maximum size for all social forum attachments on the site (subject to course limits and other local settings)';
$string['configoldpostdays'] = 'Number of days old any post is considered read.';
$string['configreplytouser'] = 'When a social forum post is mailed out, should it contain the user\'s email address so that recipients can reply personally rather than via the social forum? Even if set to \'Yes\' users can choose in their profile to keep their email address secret.';
$string['configrsstypedefault'] = 'If RSS feeds are enabled, sets the default activity type.';
$string['configrssarticlesdefault'] = 'If RSS feeds are enabled, sets the default number of articles (either discussions or posts).';
$string['configshortpost'] = 'Any post under this length (in characters not including HTML) is considered short (see below).';
$string['configtrackingtype'] = 'Default setting for read tracking.';
$string['configtrackreadposts'] = 'Set to \'yes\' if you want to track read/unread for each user.';
$string['configusermarksread'] = 'If \'yes\', the user must manually mark a post as read. If \'no\', when the post is viewed it is marked as read.';
$string['confirmsubscribediscussion'] = 'Do you really want to subscribe to discussion \'{$a->discussion}\' in social forum \'{$a->socialforum}\'?';
$string['confirmunsubscribediscussion'] = 'Do you really want to unsubscribe from discussion \'{$a->discussion}\' in social forum \'{$a->socialforum}\'?';
$string['confirmsubscribe'] = 'Do you really want to subscribe to social forum \'{$a}\'?';
$string['confirmunsubscribe'] = 'Do you really want to unsubscribe from social forum \'{$a}\'?';
$string['couldnotadd'] = 'Could not add your post due to an unknown error';
$string['couldnotdeletereplies'] = 'Sorry, that cannot be deleted as people have already responded to it';
$string['couldnotupdate'] = 'Could not update your post due to an unknown error';
$string['crontask'] = 'Social Forum mailings and maintenance jobs';
$string['delete'] = 'Delete';
$string['deleteddiscussion'] = 'The discussion topic has been deleted';
$string['deletedpost'] = 'The post has been deleted';
$string['deletedposts'] = 'Those posts have been deleted';
$string['deletesure'] = 'Are you sure you want to delete this post?';
$string['deletesureplural'] = 'Are you sure you want to delete this post and all replies? ({$a} posts)';
$string['digestmailheader'] = 'This is your daily digest of new posts from the {$a->sitename} social forums. To change your default social forum email preferences, go to {$a->userprefs}.';
$string['digestmailpost'] = 'Change your social forum digest preferences';
$string['digestmailpostlink'] = 'Change your social forum digest preferences: {$a}';
$string['digestmailprefs'] = 'your user profile';
$string['digestmailsubject'] = '{$a}: social forum digest';
$string['digestmailtime'] = 'Hour to send digest emails';
$string['digestsentusers'] = 'Email digests successfully sent to {$a} users.';
$string['disallowsubscribe'] = 'Subscriptions not allowed';
$string['disallowsubscription'] = 'Subscription';
$string['disallowsubscription_help'] = 'This social forum has been configured so that you cannot subscribe to discussions.';
$string['disallowsubscribeteacher'] = 'Subscriptions not allowed (except for teachers)';
$string['discussion'] = 'Discussion';
$string['discussionmoved'] = 'This discussion has been moved to \'{$a}\'.';
$string['discussionmovedpost'] = 'This discussion has been moved to <a href="{$a->discusshref}">here</a> in the social forum <a href="{$a->socialforumhref}">{$a->socialforumname}</a>';
$string['discussionname'] = 'Discussion name';
$string['discussionnownotsubscribed'] = '{$a->name} will NOT be notified of new posts in \'{$a->discussion}\' of \'{$a->socialforum}\'';
$string['discussionnowsubscribed'] = '{$a->name} will be notified of new posts in \'{$a->discussion}\' of \'{$a->socialforum}\'';
$string['discussionpin'] = 'Pin';
$string['discussionpinned'] = 'Pinned';
$string['discussionpinned_help'] = 'Pinned discussions will appear at the top of a social forum.';
$string['discussionsubscribestop'] = 'I don\'t want to be notified of new posts in this discussion';
$string['discussionsubscribestart'] = 'Send me notifications of new posts in this discussion';
$string['discussionsubscription'] = 'Discussion subscription';
$string['discussionsubscription_help'] = 'Subscribing to a discussion means you will receive notifications of new posts to that discussion.';
$string['discussions'] = 'Discussions';
$string['discussionsstartedby'] = 'Discussions started by {$a}';
$string['discussionsstartedbyrecent'] = 'Discussions recently started by {$a}';
$string['discussionsstartedbyuserincourse'] = 'Discussions started by {$a->fullname} in {$a->coursename}';
$string['discussionunpin'] = 'Unpin';
$string['discussthistopic'] = 'Discuss this topic';
$string['displayend'] = 'Display end';
$string['displayend_help'] = 'This setting specifies whether a social forum post should be hidden after a certain date. Note that administrators can always view social forum posts.';
$string['displaymode'] = 'Display mode';
$string['displayperiod'] = 'Display period';
$string['displaystart'] = 'Display start';
$string['displaystart_help'] = 'This setting specifies whether a social forum post should be displayed from a certain date. Note that administrators can always view social forum posts.';
$string['displaywordcount'] = 'Display word count';
$string['displaywordcount_help'] = 'This setting specifies whether the word count of each post should be displayed or not.';
$string['eachusersocialforum'] = 'Each person posts one discussion';
$string['edit'] = 'Edit';
$string['editedby'] = 'Edited by {$a->name} - original submission {$a->date}';
$string['editedpostupdated'] = '{$a}\'s post was updated';
$string['editing'] = 'Editing';
$string['eventcoursesearched'] = 'Course searched';
$string['eventdiscussioncreated'] = 'Discussion created';
$string['eventdiscussionupdated'] = 'Discussion updated';
$string['eventdiscussiondeleted'] = 'Discussion deleted';
$string['eventdiscussionmoved'] = 'Discussion moved';
$string['eventdiscussionviewed'] = 'Discussion viewed';
$string['eventdiscussionsubscriptioncreated'] = 'Discussion subscription created';
$string['eventdiscussionsubscriptiondeleted'] = 'Discussion subscription deleted';
$string['eventdiscussionpinned'] = 'Discussion pinned';
$string['eventdiscussionunpinned'] = 'Discussion unpinned';
$string['eventuserreportviewed'] = 'User report viewed';
$string['eventteacherpostreplied'] = 'Teacher post replied';
$string['eventpostcreated'] = 'Post created';
$string['eventpostdeleted'] = 'Post deleted';
$string['eventpostupdated'] = 'Post updated';
$string['eventreadtrackingdisabled'] = 'Read tracking disabled';
$string['eventreadtrackingenabled'] = 'Read tracking enabled';
$string['eventsubscribersviewed'] = 'Subscribers viewed';
$string['eventsubscriptioncreated'] = 'Subscription created';
$string['eventsubscriptiondeleted'] = 'Subscription deleted';
$string['emaildigestcompleteshort'] = 'Complete posts';
$string['emaildigestdefault'] = 'Default ({$a})';
$string['emaildigestoffshort'] = 'No digest';
$string['emaildigestsubjectsshort'] = 'Subjects only';
$string['emaildigesttype'] = 'Email digest options';
$string['emaildigesttype_help'] = 'The type of notification that you will receive for each social forum.

* Default - follow the digest setting found in your user profile. If you update your profile, then that change will be reflected here too;
* No digest - you will receive one e-mail per social forum post;
* Digest - complete posts - you will receive one digest e-mail per day containing the complete contents of each social forum post;
* Digest - subjects only - you will receive one digest e-mail per day containing just the subject of each social forum post.
';
$string['emaildigestupdated'] = 'The e-mail digest option was changed to \'{$a->maildigesttitle}\' for the social forum \'{$a->socialforum}\'. {$a->maildigestdescription}';
$string['emaildigestupdated_default'] = 'Your default profile setting of \'{$a->maildigesttitle}\' was used for the social forum \'{$a->socialforum}\'. {$a->maildigestdescription}.';
$string['emaildigest_0'] = 'You will receive one e-mail per social forum post.';
$string['emaildigest_1'] = 'You will receive one digest e-mail per day containing the complete contents of each social forum post.';
$string['emaildigest_2'] = 'You will receive one digest e-mail per day containing the subject of each social forum post.';
$string['emptymessage'] = 'Something was wrong with your post. Perhaps you left it blank, or the attachment was too big. Your changes have NOT been saved.';
$string['erroremptymessage'] = 'Post message cannot be empty';
$string['erroremptysubject'] = 'Post subject cannot be empty.';
$string['errorenrolmentrequired'] = 'You must be enrolled in this course to access this content';
$string['errorwhiledelete'] = 'An error occurred while deleting record.';
$string['eventassessableuploaded'] = 'Some content has been posted.';
$string['everyonecanchoose'] = 'Everyone can choose to be subscribed';
$string['everyonecannowchoose'] = 'Everyone can now choose to be subscribed';
$string['everyoneisnowsubscribed'] = 'Everyone is now subscribed to this social forum';
$string['everyoneissubscribed'] = 'Everyone is subscribed to this social forum';
$string['existingsubscribers'] = 'Existing subscribers';
$string['exportdiscussion'] = 'Export whole discussion to portfolio';
$string['forcedreadtracking'] = 'Allow forced read tracking';
$string['forcedreadtracking_desc'] = 'Allows social forums to be set to forced read tracking. Will result in decreased performance for some users, particularly on courses with many social forums and posts. When off, any social forums previously set to Forced are treated as optional.';
$string['forcesubscribed_help'] = 'This social forum has been configured so that you cannot unsubscribe from discussions.';
$string['forcesubscribed'] = 'This social forum forces everyone to be subscribed';
$string['socialforum'] = 'Forum';
$string['socialforum:addinstance'] = 'Add a new social forum';
$string['socialforum:addnews'] = 'Add news';
$string['socialforum:addquestion'] = 'Add question';
$string['socialforum:allowforcesubscribe'] = 'Allow force subscribe';
$string['socialforumauthorhidden'] = 'Author (hidden)';
$string['socialforumblockingalmosttoomanyposts'] = 'You are approaching the posting threshold. You have posted {$a->numposts} times in the last {$a->blockperiod} and the limit is {$a->blockafter} posts.';
$string['socialforumbodyhidden'] = 'This post cannot be viewed by you, probably because you have not posted in the discussion, the maximum editing time hasn\'t passed yet, the discussion has not started or the discussion has expired.';
$string['socialforum:canposttomygroups'] = 'Can post to all groups you have access to';
$string['socialforum:createattachment'] = 'Create attachments';
$string['socialforum:deleteanypost'] = 'Delete any posts (anytime)';
$string['socialforum:deleteownpost'] = 'Delete own posts (within deadline)';
$string['socialforum:editanypost'] = 'Edit any post';
$string['socialforum:exportdiscussion'] = 'Export whole discussion';
$string['socialforum:exportownpost'] = 'Export own post';
$string['socialforum:exportpost'] = 'Export post';
$string['socialforumintro'] = 'Description';
$string['socialforum:managesubscriptions'] = 'Manage subscriptions';
$string['socialforum:movediscussions'] = 'Move discussions';
$string['socialforum:pindiscussions'] = 'Pin discussions';
$string['socialforum:postwithoutthrottling'] = 'Exempt from post threshold';
$string['socialforumname'] = 'Social Forum name';
$string['socialforumposts'] = 'Social Forum posts';
$string['socialforum:rate'] = 'Rate posts';
$string['socialforum:replynews'] = 'Reply to news';
$string['socialforum:replypost'] = 'Reply to posts';
$string['socialforums'] = 'Social Forums';
$string['socialforum:splitdiscussions'] = 'Split discussions';
$string['socialforum:startdiscussion'] = 'Start new discussions';
$string['socialforumsubjecthidden'] = 'Subject (hidden)';
$string['socialforumtracked'] = 'Unread posts are being tracked';
$string['socialforumtrackednot'] = 'Unread posts are not being tracked';
$string['socialforumtype'] = 'Social Forum type';
$string['socialforumtype_help'] = 'There are 5 social forum types:

* A single simple discussion - A single discussion topic which everyone can reply to (cannot be used with separate groups)
* Each person posts one discussion - Each student can post exactly one new discussion topic, which everyone can then reply to
* Q and A social forum - Students must first post their perspectives before viewing other students\' posts
* Standard social forum displayed in a blog-like format - An open social forum where anyone can start a new discussion at any time, and in which discussion topics are displayed on one page with "Discuss this topic" links
* Standard social forum for general use - An open social forum where anyone can start a new discussion at any time';
$string['socialforum:viewallratings'] = 'View all raw ratings given by individuals';
$string['socialforum:viewanyrating'] = 'View total ratings that anyone received';
$string['socialforum:viewdiscussion'] = 'View discussions';
$string['socialforum:viewhiddentimedposts'] = 'View hidden timed posts';
$string['socialforum:viewqandawithoutposting'] = 'Always see Q and A posts';
$string['socialforum:viewrating'] = 'View the total rating you received';
$string['socialforum:viewsubscribers'] = 'View subscribers';
$string['generalsocialforum'] = 'Standard social forum for general use';
$string['generalsocialforums'] = 'General social forums';
$string['hiddensocialforumpost'] = 'Hidden social forum post';
$string['insocialforum'] = 'in {$a}';
$string['introblog'] = 'The posts in this social forum were copied here automatically from blogs of users in this course because those blog entries are no longer available';
$string['intronews'] = 'General news and announcements';
$string['introsocial'] = 'An open social forum for chatting about anything you want to';
$string['introteacher'] = 'A social forum for teacher-only notes and discussion';
$string['invalidaccess'] = 'This page was not accessed correctly';
$string['invaliddiscussionid'] = 'Discussion ID was incorrect or no longer exists';
$string['invaliddigestsetting'] = 'An invalid mail digest setting was provided';
$string['invalidforcesubscribe'] = 'Invalid force subscription mode';
$string['invalidsocialforumid'] = 'Social Forum ID was incorrect';
$string['invalidparentpostid'] = 'Parent post ID was incorrect';
$string['invalidpostid'] = 'Invalid post ID - {$a}';
$string['lastpost'] = 'Last post';
$string['learningsocialforums'] = 'Learning social forums';
$string['longpost'] = 'Long post';
$string['mailnow'] = 'Send social forum post notifications with no editing-time delay';
$string['manydiscussions'] = 'Discussions per page';
$string['markalldread'] = 'Mark all posts in this discussion read.';
$string['markallread'] = 'Mark all posts in this social forum read.';
$string['markread'] = 'Mark read';
$string['markreadbutton'] = 'Mark<br />read';
$string['markunread'] = 'Mark unread';
$string['markunreadbutton'] = 'Mark<br />unread';
$string['maxattachments'] = 'Maximum number of attachments';
$string['maxattachments_help'] = 'This setting specifies the maximum number of files that can be attached to a social forum post.';
$string['maxattachmentsize'] = 'Maximum attachment size';
$string['maxattachmentsize_help'] = 'This setting specifies the largest size of file that can be attached to a social forum post.';
$string['maxtimehaspassed'] = 'Sorry, but the maximum time for editing this post ({$a}) has passed!';
$string['message'] = 'Message';
$string['messageinboundattachmentdisallowed'] = 'Unable to post your reply, since it includes an attachment and the social forum doesn\'t allow attachments.';
$string['messageinboundfilecountexceeded'] = 'Unable to post your reply, since it includes more than the maximum number of attachments allowed for the social forum ({$a->socialforum->maxattachments}).';
$string['messageinboundfilesizeexceeded'] = 'Unable to post your reply, since the total attachment size ({$a->filesize}) is greater than the maximum size allowed for the social forum ({$a->maxbytes}).';
$string['messageinboundsocialforumhidden'] = 'Unable to post your reply, since the social forum is currently unavailable.';
$string['messageinboundnopostsocialforum'] = 'Unable to post your reply, since you do not have permission to post in the {$a->socialforum->name} social forum.';
$string['messageinboundthresholdhit'] = 'Unable to post your reply.  You have exceeded the posting threshold set for this social forum';
$string['messageprovider:digests'] = 'Subscribed social forum digests';
$string['messageprovider:posts'] = 'Subscribed social forum posts';
$string['missingsearchterms'] = 'The following search terms occur only in the HTML markup of this message:';
$string['modenested'] = 'Display replies in nested form';
$string['modulename'] = 'Social Forum';
$string['modulename_help'] = 'The social forum activity module enables participants to have asynchronous discussions i.e. discussions that take place over an extended period of time.

There are several social forum types to choose from, such as a standard social forum where anyone can start a new discussion at any time; a social forum where each student can post exactly one discussion; or a question and answer social forum where students must first post before being able to view other students\' posts. A teacher can allow files to be attached to social forum posts. Attached images are displayed in the social forum post.

Participants can subscribe to a social forum to receive notifications of new social forum posts. A teacher can set the subscription mode to optional, forced or auto, or prevent subscription completely. If required, students can be blocked from posting more than a given number of posts in a given time period; this can prevent individuals from dominating discussions.

Social Forum posts can be rated by teachers or students (peer evaluation). Ratings can be aggregated to form a final grade which is recorded in the gradebook.

Social Forums have many uses, such as

* A social space for students to get to know each other
* For course announcements (using a news social forum with forced subscription)
* For discussing course content or reading materials
* For continuing online an issue raised previously in a face-to-face session
* For teacher-only discussions (using a hidden social forum)
* A help centre where tutors and students can give advice
* A one-on-one support area for private student-teacher communications (using a social forum with separate groups and with one student per group)
* For extension activities, for example ‘brain teasers’ for students to ponder and suggest solutions to';
$string['modulename_link'] = 'mod/socialforum/view';
$string['modulenameplural'] = 'Social Forums';
$string['more'] = 'more';
$string['movedmarker'] = '(Moved)';
$string['movethisdiscussionto'] = 'Move this discussion to ...';
$string['mustprovidediscussionorpost'] = 'You must provide either a discussion id or post id to export';
$string['myprofileownpost'] = 'My social forum posts';
$string['myprofileowndis'] = 'My social forum discussions';
$string['myprofileotherdis'] = 'Social Forum discussions';
$string['namenews'] = 'Announcements';
$string['namenews_help'] = 'The course announcements social forum is a special social forum for announcements and is automatically created when a course is created. A course can have only one announcements social forum. Only teachers and administrators can post announcements. The "Latest announcements" block will display recent announcements.';
$string['namesocial'] = 'Social social forum';
$string['nameteacher'] = 'Teacher social forum';
$string['nextdiscussiona'] = 'Next discussion: {$a}';
$string['newsocialforumposts'] = 'New social forum posts';
$string['noattachments'] = 'There are no attachments to this post';
$string['nodiscussions'] = 'There are no discussion topics yet in this social forum';
$string['nodiscussionsstartedby'] = '{$a} has not started any discussions';
$string['nodiscussionsstartedbyyou'] = 'You haven\'t started any discussions yet';
$string['noguestpost'] = 'Sorry, guests are not allowed to post.';
$string['noguestsubscribe'] = 'Sorry, guests are not allowed to subscribe.';
$string['noguesttracking'] = 'Sorry, guests are not allowed to set tracking options.';
$string['nomorepostscontaining'] = 'No more posts containing \'{$a}\' were found';
$string['nonews'] = 'No news has been posted yet';
$string['noonecansubscribenow'] = 'Subscriptions are now disallowed';
$string['nopermissiontosubscribe'] = 'You do not have the permission to view social forum subscribers';
$string['nopermissiontoview'] = 'You do not have permissions to view this post';
$string['nopostsocialforum'] = 'Sorry, you are not allowed to post to this social forum';
$string['noposts'] = 'No posts';
$string['nopostsmadebyuser'] = '{$a} has made no posts';
$string['nopostsmadebyyou'] = 'You haven\'t made any posts';
$string['noquestions'] = 'There are no questions yet in this social forum';
$string['nosubscribers'] = 'There are no subscribers yet for this social forum';
$string['notsubscribed'] = 'Subscribe';
$string['notexists'] = 'Discussion no longer exists';
$string['nothingnew'] = 'Nothing new for {$a}';
$string['notingroup'] = 'Sorry, but you need to be part of a group to see this social forum.';
$string['notinstalled'] = 'The social forum module is not installed';
$string['notpartofdiscussion'] = 'This post is not part of a discussion!';
$string['notracksocialforum'] = 'Don\'t track unread posts';
$string['noviewdiscussionspermission'] = 'You do not have the permission to view discussions in this social forum';
$string['nowallsubscribed'] = 'All social forums in {$a} are subscribed.';
$string['nowallunsubscribed'] = 'All social forums in {$a} are not subscribed.';
$string['nownotsubscribed'] = '{$a->name} will NOT be notified of new posts in \'{$a->socialforum}\'';
$string['nownottracking'] = '{$a->name} is no longer tracking \'{$a->socialforum}\'.';
$string['nowsubscribed'] = '{$a->name} will be notified of new posts in \'{$a->socialforum}\'';
$string['nowtracking'] = '{$a->name} is now tracking \'{$a->socialforum}\'.';
$string['numposts'] = '{$a} posts';
$string['olderdiscussions'] = 'Older discussions';
$string['oldertopics'] = 'Older topics';
$string['oldpostdays'] = 'Read after days';
$string['overviewnumpostssince'] = '{$a} posts since last login';
$string['overviewnumunread'] = '{$a} total unread';
$string['page-mod-forum-x'] = 'Any social forum module page';
$string['page-mod-forum-view'] = 'Social Forum module main page';
$string['page-mod-forum-discuss'] = 'Social Forum module discussion thread page';
$string['parent'] = 'Show parent';
$string['parentofthispost'] = 'Parent of this post';
$string['permalink'] = 'Permalink';
$string['posttomygroups'] = 'Post a copy to all groups';
$string['posttomygroups_help'] = 'Posts a copy of this message to all groups you have access to. Participants in groups you do not have access to will not see this post';
$string['prevdiscussiona'] = 'Previous discussion: {$a}';
$string['pluginadministration'] = 'Social Forum administration';
$string['pluginname'] = 'Social Forum';
$string['postadded'] = '<p>Your post was successfully added.</p> <p>You have {$a} to edit it if you want to make any changes.</p>';
$string['postaddedsuccess'] = 'Your post was successfully added.';
$string['postaddedtimeleft'] = 'You have {$a} to edit it if you want to make any changes.';
$string['postbymailsuccess'] = 'Congratulations, your social forum post with subject "{$a->subject}" was successfully added. You can view it at {$a->discussionurl}.';
$string['postbymailsuccess_html'] = 'Congratulations, your <a href="{$a->discussionurl}">social forum post</a> with subject "{$a->subject}" was successfully posted.';
$string['postbyuser'] = '{$a->post} by {$a->user}';
$string['postincontext'] = 'See this post in context';
$string['postmailinfolink'] = 'This is a copy of a message posted in {$a->coursename}.

To reply click on this link: {$a->replylink}';
$string['postmailnow'] = '<p>This post will be mailed out immediately to all social forum subscribers.</p>';
$string['postmailsubject'] = '{$a->courseshortname}: {$a->subject}';
$string['postrating1'] = 'Mostly separate knowing';
$string['postrating2'] = 'Separate and connected';
$string['postrating3'] = 'Mostly connected knowing';
$string['posts'] = 'Posts';
$string['postsmadebyuser'] = 'Posts made by {$a}';
$string['postsmadebyuserincourse'] = 'Posts made by {$a->fullname} in {$a->coursename}';
$string['posttosocialforum'] = 'Post';
$string['postupdated'] = 'Your post was updated';
$string['potentialsubscribers'] = 'Potential subscribers';
$string['processingdigest'] = 'Processing email digest for user {$a}';
$string['processingpost'] = 'Processing post {$a}';
$string['prune'] = 'Split';
$string['prunedpost'] = 'A new discussion has been created from that post';
$string['pruneheading'] = 'Split the discussion and move this post to a new discussion';
$string['qandasocialforum'] = 'Q and A social forum';
$string['qandanotify'] = 'This is a question and answer social forum. In order to see other responses to these questions, you must first post your answer';
$string['re'] = 'Re:';
$string['readtherest'] = 'Read the rest of this topic';
$string['replies'] = 'Replies';
$string['repliesmany'] = '{$a} replies so far';
$string['repliesone'] = '{$a} reply so far';
$string['reply'] = 'Reply';
$string['replysocialforum'] = 'Reply to social forum';
$string['replytopostbyemail'] = 'You can reply to this via email.';
$string['replytouser'] = 'Use email address in reply';
$string['reply_handler'] = 'Reply to social forum posts via email';
$string['reply_handler_name'] = 'Reply to social forum posts';
$string['resetsocialforums'] = 'Delete posts from';
$string['resetsocialforumsall'] = 'Delete all posts';
$string['resetdigests'] = 'Delete all per-user social forum digest preferences';
$string['resetsubscriptions'] = 'Delete all social forum subscriptions';
$string['resettrackprefs'] = 'Delete all social forum tracking preferences';
$string['rsssubscriberssdiscussions'] = 'RSS feed of discussions';
$string['rsssubscriberssposts'] = 'RSS feed of posts';
$string['rssarticles'] = 'Number of RSS recent articles';
$string['rssarticles_help'] = 'This setting specifies the number of articles (either discussions or posts) to include in the RSS feed. Between 5 and 20 generally acceptable.';
$string['rsstype'] = 'RSS feed for this activity';
$string['rsstype_help'] = 'To enable the RSS feed for this activity, select either discussions or posts to be included in the feed.';
$string['rsstypedefault'] = 'RSS feed type';
$string['search'] = 'Search';
$string['search:post'] = 'Social Forum - posts';
$string['search:activity'] = 'Social Forum - activity information';
$string['searchdatefrom'] = 'Posts must be newer than this';
$string['searchdateto'] = 'Posts must be older than this';
$string['searchsocialforumintro'] = 'Please enter search terms into one or more of the following fields:';
$string['searchsocialforums'] = 'Buscar em fóruns';
$string['searchfullwords'] = 'These words should appear as whole words';
$string['searchnotwords'] = 'These words should NOT be included';
$string['searcholderposts'] = 'Search older posts...';
$string['searchphrase'] = 'This exact phrase must appear in the post';
$string['searchresults'] = 'Search results';
$string['searchsubject'] = 'These words should be in the subject';
$string['searchuser'] = 'This name should match the author';
$string['searchuserid'] = 'The Moodle ID of the author';
$string['searchwhichsocialforums'] = 'Choose which social forums to search';
$string['searchwords'] = 'These words can appear anywhere in the post';
$string['searchforums'] = 'Search forums';
$string['allforums'] = 'All forums';
$string['searchforumintro'] = 'Please enter search terms into one or more of the following fields:';
$string['searchwhichforums'] = 'Choose which forums to search';
$string['seeallposts'] = 'See all posts made by this user';
$string['shortpost'] = 'Short post';
$string['showsubscribers'] = 'Show/edit current subscribers';
$string['singlesocialforum'] = 'A single simple discussion';
$string['smallmessage'] = '{$a->user} posted in {$a->socialforumname}';
$string['smallmessagedigest'] = 'Social Forum digest containing {$a} messages';
$string['startedby'] = 'Started by';
$string['subject'] = 'Subject';
$string['subscribe'] = 'Subscribe to this social forum';
$string['subscribediscussion'] = 'Subscribe to this discussion';
$string['subscribeall'] = 'Subscribe everyone to this social forum';
$string['subscribeenrolledonly'] = 'Sorry, only enrolled users are allowed to subscribe to social forum post notifications.';
$string['subscribed'] = 'Subscribed';
$string['subscribenone'] = 'Unsubscribe everyone from this social forum';
$string['subscribers'] = 'Subscribers';
$string['subscriberstowithcount'] = 'Subscribers to "{$a->name}" ({$a->count})';
$string['subscribestart'] = 'Send me notifications of new posts in this social forum';
$string['subscribestop'] = 'I don\'t want to be notified of new posts in this social forum';
$string['subscription'] = 'Subscription';
$string['subscription_help'] = 'If you are subscribed to a social forum it means you will receive notification of new social forum posts. Usually you can choose whether you wish to be subscribed, though sometimes subscription is forced so that everyone receives notifications.';
$string['subscriptionandtracking'] = 'Subscription and tracking';
$string['subscriptionmode'] = 'Subscription mode';
$string['subscriptionmode_help'] = 'When a participant is subscribed to a social forum it means they will receive social forum post notifications. There are 4 subscription mode options:

* Optional subscription - Participants can choose whether to be subscribed
* Forced subscription - Everyone is subscribed and cannot unsubscribe
* Auto subscription - Everyone is subscribed initially but can choose to unsubscribe at any time
* Subscription disabled - Subscriptions are not allowed

Note: Any subscription mode changes will only affect users who enrol in the course in the future, and not existing users.';
$string['subscriptionoptional'] = 'Optional subscription';
$string['subscriptionforced'] = 'Forced subscription';
$string['subscriptionauto'] = 'Auto subscription';
$string['subscriptiondisabled'] = 'Subscription disabled';
$string['subscriptions'] = 'Subscriptions';
$string['thissocialforumisthrottled'] = 'This social forum has a limit to the number of social forum postings you can make in a given time period - this is currently set at {$a->blockafter} posting(s) in {$a->blockperiod}';
$string['timedhidden'] = 'Timed status: Hidden from students';
$string['timedposts'] = 'Timed posts';
$string['timedvisible'] = 'Timed status: Visible to all users';
$string['timestartenderror'] = 'Display end date cannot be earlier than the start date';
$string['tracksocialforum'] = 'Track unread posts';
$string['tracking'] = 'Track';
$string['trackingoff'] = 'Off';
$string['trackingon'] = 'Forced';
$string['trackingoptional'] = 'Optional';
$string['trackingtype'] = 'Read tracking';
$string['trackingtype_help'] = 'If enabled, participants can track read and unread posts in the social forum and in discussions. There are three options:

* Optional - Participants can choose whether to turn tracking on or off via a link in the administration block. Social Forum tracking must also be enabled in the user\'s profile settings.
* Forced - Tracking is always on, regardless of user setting. Available depending on administrative setting.
* Off - Read and unread posts are not tracked.';
$string['unread'] = 'Unread';
$string['unreadposts'] = 'Unread posts';
$string['unreadpostsnumber'] = '({$a} unread posts)';
$string['unreadpostsone'] = '(1 unread post)';
$string['unsubscribe'] = 'Unsubscribe from this social forum';
$string['unsubscribelink'] = 'Unsubscribe from this social forum: {$a}';
$string['unsubscribediscussion'] = 'Unsubscribe from this discussion';
$string['unsubscribediscussionlink'] = 'Unsubscribe from this discussion: {$a}';
$string['unsubscribeall'] = 'Unsubscribe from all social forums';
$string['unsubscribeallconfirm'] = 'You are currently subscribed to {$a->socialforums} social forums, and {$a->discussions} discussions. Do you really want to unsubscribe from all social forums and discussions, and disable discussion auto-subscription?';
$string['unsubscribeallconfirmsocialforums'] = 'You are currently subscribed to {$a->socialforums} social forums. Do you really want to unsubscribe from all social forums and disable discussion auto-subscription?';
$string['unsubscribeallconfirmdiscussions'] = 'You are currently subscribed to {$a->discussions} discussions. Do you really want to unsubscribe from all discussions and disable discussion auto-subscription?';
$string['unsubscribealldone'] = 'All optional social forum subscriptions were removed. You will still receive notifications from social forums with forced subscription. To manage social forum notifications go to Messaging in My Profile Settings.';
$string['unsubscribeallempty'] = 'You are not subscribed to any social forums. To disable all notifications from this server go to Messaging in My Profile Settings.';
$string['unsubscribed'] = 'Unsubscribed';
$string['unsubscribeshort'] = 'Unsubscribe';
$string['usermarksread'] = 'Manual message read marking';
$string['viewalldiscussions'] = 'View all discussions';
$string['warnafter'] = 'Post threshold for warning';
$string['warnafter_help'] = 'Students can be warned as they approach the maximum number of posts allowed in a given period. This setting specifies after how many posts they are warned. Users with the capability mod/socialforum:postwithoutthrottling are exempt from post limits.';
$string['warnformorepost'] = 'Warning! There is more than one discussion in this social forum - using the most recent';
$string['yournewquestion'] = 'Your new question';
$string['yournewtopic'] = 'Your new discussion topic';
$string['yourreply'] = 'Your reply';

// Vote strings
$string['relevant'] = 'Relevant';
$string['relevancy'] = 'Relevancy';
$string['irrelevant'] = 'Irrelevant';

// Vote events
$string['eventdiscussionrelevancyvoted'] = 'User voted in a discussion as relevant';
$string['eventdiscussionrelevancyvotecancelled'] = "User's vote in a discussion relevancy cancelled";
$string['eventdiscussionvotedrelevant'] = "User's discussion voted as relevant";
$string['eventdiscussionvotedirrelevant'] = "User's discussion voted as irrelevant";
$string['eventpostrelevancyvoted'] = 'User voted in a post as relevant';
$string['eventpostrelevancyvotecancelled'] = "User's vote in a post relevancy cancelled";
$string['eventpostvotedrelevant'] = "User's post voted as relevant";
$string['eventpostvotedirrelevant'] = "User's post voted as irrelevant";
$string['eventpostvotedmostrelevant'] = "User's post voted as the most relevant";
$string['eventpostnolongermostrelevant'] = "User's post is no longer the most relevant";

// Deprecated since Moodle 3.0.
$string['subscribersto'] = 'Subscribers to "{$a->name}"';

// Deprecated since Moodle 3.1.
$string['postmailinfo'] = 'This is a copy of a message posted on the {$a} website.

To reply click on this link:';
$string['unmarkasimproper'] = 'Marked as improper content. Click to unmark';
$string['markasimproper'] = 'Click to mark as improper content';
$string['eventpostmarkedimproper'] = 'User marked a post as improper content';
$string['eventpostunmarkedimproper'] = 'User unmarked a post as improper content';
$string['lastreply'] = 'Last reply';
$string['related'] = 'Related';
$string['relateddesc'] = 'People in this discussion are talking about this related vídeo.';
$string['topics'] = 'Topics';
$string['topic'] = 'Topic';
$string['beforeposting'] = 'Before posting';
$string['beforepostingtext'] = 'Other students might have already posted the same question before.<br>Do a quick search first to make sure that your question is unique.';
$string['searchinforum'] = 'Search in forum';

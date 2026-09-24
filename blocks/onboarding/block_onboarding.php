<?php

require_once($CFG->dirroot . '/blocks/onboarding/locallib.php');

/**
 * Onboarding block
 *
 * @package   block_onboarding
 */
class block_onboarding extends block_base {

    function init() {
        $this->title = get_string('pluginname', 'block_onboarding');
    }

    function applicable_formats() {
        return array(
            'course-view' => false,
            'site' => false,
            'mod' => false,
            'my' => true
        );
    }

    private function print_first_page($datetime = FALSE) {
        global $USER, $PAGE, $CFG;

        $getpassword = !is_siteadmin() && ($USER->auth != 'secretaria');

        $html = '';
        $html .= '<!-- First page BEGIN -->';
        $html .= html_writer::start_div('mdk-header-layout js-mdk-header-layout bg-primary900 max-height-100vh', array(
                    'id' => 'firstpage'
        ));
        $html .= html_writer::start_div('mdk-box js-mdk-box mb-0', array(
                    'data-effects' => 'parallax-background blend-background'
        ));
        $html .= html_writer::start_div('mdk-box__bg');
        $html .= html_writer::start_div('mdk-box__bg-front');
        $html .= html_writer::end_div();
        $html .= html_writer::end_div();
        $html .= html_writer::start_div('mdk-box__content justify-content-center max-height-100vh');
        $html .= html_writer::start_div('narrow-page container page__container page-section d-flex flex-column justify-content-center max-height-100vh', array(
                    'style' => 'min-height: 100vh;'
        ));
        $html .= html_writer::start_div('mdk-box__content justify-content-center');
        $html .= html_writer::start_div('hero container page__container text-center py-64pt', array(
                    'style' => 'padding-bottom: 0 !important;'
        ));
        $html .= html_writer::start_tag('h1', array(
                    'class' => 'text-white text-shadow'
        ));
        $html .= get_string('welcome', 'block_onboarding');
        $html .= html_writer::end_tag('h1');
        $html .= html_writer::end_div();
        $html .= html_writer::end_div();
        $html .= html_writer::start_div('flex d-flex justify-content-center align-items-center pb-64pt');

        $html .= '<!-- Time screen BEGIN -->';
        $html .= html_writer::start_div('card rounded-lg p-4 mb-24pt w-50 pt-5 pl-5 pr-5', array(
                    'id' => 'timeScreen',
                    'style' => 'width: 600px; transition: opacity 0.5s ease-in-out; align-self: baseline; margin-top: 5rem !important;'
        ));
        $html .= html_writer::start_div('row');
        $html .= html_writer::start_div('col-lg-12');
        $html .= html_writer::start_div('d-flex flex-column h-100');
        $html .= html_writer::start_div();
        $html .= html_writer::start_tag('h4', array(
                    'class' => 'text-center pl-5 pr-5 mb-4'
        ));
        $html .= get_string('howmuchtimetxt', 'block_onboarding');
        $html .= html_writer::end_tag('h4');
        $html .= html_writer::end_div();
        $html .= html_writer::start_div('d-flex justify-content-center align-items-center');
        $html .= html_writer::start_div('avatar avatar-sm bg-purple100 rounded-circle', array(
                    'style' => 'align-content: center; padding-left: .5rem;'
        ));
        $html .= html_writer::start_tag('i', array(
                    'class' => 'icon fa fa-check text-purple500',
                    'style' => 'font-size: 25px; margin-left: 3px;'
        ));
        $html .= html_writer::end_tag('i');
        $html .= html_writer::end_div();
        $html .= html_writer::start_div('ml-3 text-black-100');
        $html .= html_writer::start_tag('strong');
        $html .= get_string('chooseoneoptionbelow', 'block_onboarding');
        $html .= html_writer::end_tag('strong');
        $html .= html_writer::end_div();
        $html .= html_writer::end_div();
        $html .= html_writer::start_div('d-flex flex-column flex-grow-1 mt-3');
        $html .= html_writer::start_tag('form', array(
                    'action' => '#',
                    'class' => 'form-horizontal'
        ));
        $sesskey = sesskey();
        $html .= html_writer::empty_tag('input', array(
                    'type' => 'hidden',
                    'id' => 'sesskey',
                    'name' => 'sesskey',
                    'value' => $sesskey
        ));
        $html .= html_writer::start_div('form-group');
        $html .= html_writer::tag('label', '', array(
                    'class' => 'form-label h3 mb-4'
        ));
        $html .= html_writer::start_div('form-group px-4');
        $html .= html_writer::start_div('d-flex flex-wrap justify-content-between mt-0', array(
                    'id' => 'timeoption'
        ));

        require_once($CFG->dirroot . '/local/profile/lib.php');
        $studyplans = local_profile_get_study_plans();
        foreach ($studyplans as $months => $planname) {
            $html .= html_writer::start_div('chip chip-outline-secondary time-option', array(
                        'onclick' => 'selectTimeOption(this);',
                        'data-value' => $months
            ));
            $html .= get_string('months', 'block_onboarding', $months);
            $html .= html_writer::end_div();
        }
        $html .= html_writer::end_div();
        $html .= html_writer::end_div();
        $html .= html_writer::end_div();
        $html .= html_writer::end_tag('form');
        $html .= html_writer::end_div();
        $html .= html_writer::start_div('form-group d-flex justify-content-end mt-4 gap-2');
        $html .= html_writer::tag('button', get_string('forward', 'block_onboarding'), array(
                    'type' => 'button',
                    'onclick' => 'validateTimeScreen() && switchForms("timeScreen", "dataScreen");',
                    'class' => 'btn btn-primary btn-rounded'
        ));
        $html .= html_writer::end_div();
        $html .= html_writer::end_div();
        $html .= html_writer::end_div();
        $html .= html_writer::end_div();
        $html .= html_writer::end_div();
        $html .= '<!-- Time screen END -->';

        $html .= '<!-- Data screen BEGIN -->';
        $html .= html_writer::start_div('card rounded-lg p-4 mb-24pt w-50 pt-5 pl-5 pr-5', array(
                    'id' => 'dataScreen',
                    'style' => 'display: none; width: 600px; transition: opacity 0.5s ease-in-out; align-self: baseline; margin-top: 5rem !important;'
        ));
        $html .= html_writer::start_div('d-flex flex-column h-100 justify-content-between');
        $html .= html_writer::start_div();
        $html .= html_writer::start_tag('h4', array(
                    'class' => 'text-center pl-5 pr-5 mb-4'
        ));
        $html .= get_string('providepersonalinfo', 'block_onboarding');
        $html .= html_writer::end_tag('h4');
        $html .= html_writer::end_div();
        $html .= html_writer::start_div('user-info-container d-flex align-items-center justify-content-center');
        $html .= html_writer::start_div('profile-image me-4 mr-3 mb-auto');
        $userpicture = new user_picture($USER);
        $userpicture->size = 140;
        $userpictureurl = $userpicture->get_url($PAGE);
        $html .= html_writer::tag('img', '', array(
                    'src' => $userpictureurl,
                    'alt' => 'user',
                    'class' => 'rounded-circle',
                    'style' => 'width: 110px; height: 110px; object-fit: cover; border: 3px solid #6774df;'
        ));
        $html .= html_writer::end_div();
        $html .= html_writer::start_div('user-details flex-grow-1 d-flex');
        $html .= html_writer::start_div('col-12');
        $html .= html_writer::start_div('info-item mb-3');
        $html .= html_writer::start_tag('label', array(
                    'class' => 'form-label',
                    'for' => 'fullName',
                    'style' => 'font-size: 10px; letter-spacing: normal;'
        ));
        $html .= get_string('fullname', 'block_onboarding');
        $html .= html_writer::end_tag('label');
        $username = $USER->firstname . ' ' . $USER->lastname;
        $html .= html_writer::tag('input', '', array(
                    'type' => 'text',
                    'id' => 'fullName',
                    'class' => 'form-control rounded-lg',
                    'value' => $username
        ));
        $html .= html_writer::end_div();
        $html .= html_writer::start_div('info-item mb-3', array(
                    'style' => ($datetime ? 'display: none;' : '')
        ));
        $html .= html_writer::start_tag('label', array(
                    'class' => 'form-label',
                    'for' => 'hireDate',
                    'style' => 'font-size: 10px; letter-spacing: normal;'
        ));
        $html .= get_string('hiringdate', 'block_onboarding');
        $html .= html_writer::end_tag('label');
        $hiretimestamp = $datetime ? $datetime : time();
        $hiredatetxt = userdate($hiretimestamp, '%Y-%m-%d');
        $html .= html_writer::tag('input', '', array(
                    'type' => 'date',
                    'id' => 'hireDate',
                    'class' => 'form-control rounded-lg',
                    'style' => 'max-width: 200px;',
                    'value' => $hiredatetxt
        ));
        $html .= html_writer::end_div();
        $html .= html_writer::start_div('info-item mb-3');
        $html .= html_writer::start_tag('label', array(
                    'class' => 'form-label',
                    'for' => 'email',
                    'style' => 'font-size: 10px; letter-spacing: normal;'
        ));
        $html .= 'E-mail';
        $html .= html_writer::end_tag('label');
        $useremail = $USER->email;
        $html .= html_writer::tag('input', '', array(
                    'type' => 'email',
                    'id' => 'email',
                    'class' => 'form-control rounded-lg',
                    'value' => $useremail
        ));
        $html .= html_writer::end_div();
        $html .= html_writer::end_div();
        $html .= html_writer::end_div();
        $html .= html_writer::end_div();
        $html .= html_writer::start_div('form-group mt-4 border-top-1 pt-4');
        $html .= html_writer::start_div('custom-control custom-checkbox');
        $html .= html_writer::tag('input', '', array(
                    'type' => 'checkbox',
                    'id' => 'confirmInfo',
                    'class' => 'custom-control-input',
                    'required' => ''
        ));
        $html .= html_writer::start_tag('label', array(
                    'for' => 'confirmInfo',
                    'class' => 'custom-control-label',
                    'style' => 'font-size: 14px;'
        ));
        $html .= get_string('herebydeclare', 'block_onboarding');
        $html .= html_writer::end_tag('label');
        $html .= html_writer::end_div();
        $html .= html_writer::end_div();
        $html .= html_writer::start_div('form-group d-flex justify-content-end mt-4 gap-2');
        $html .= html_writer::tag('button', get_string('back'), array(
                    'type' => 'button',
                    'onclick' => 'switchForms("dataScreen", "timeScreen");',
                    'class' => 'btn btn-outline-primary btn-rounded me-2 mr-3'
        ));
        $html .= html_writer::tag('button', get_string('forward', 'block_onboarding'), array(
                    'type' => 'button',
                    'onclick' => 'validateDataScreen() && switchForms("dataScreen", ' . ($getpassword ? '"passwordScreen"' : '"avatarScreen"') . ');',
                    'class' => 'btn btn-primary btn-rounded'
        ));
        $html .= html_writer::end_div();
        $html .= html_writer::end_div();
        $html .= html_writer::end_div();
        $html .= '<!-- Data screen END -->';

        $html .= '<!-- Password screen BEGIN -->';
        $html .= html_writer::start_div('card rounded-lg p-4 mb-24pt w-50 pt-5 pl-5 pr-5', array(
                    'id' => 'passwordScreen',
                    'style' => 'display: none; width: 600px; transition: opacity 0.5s ease-in-out; align-self: baseline; margin-top: 5rem !important;'
        ));
        $html .= html_writer::start_div('d-flex flex-column h-100');
        $html .= html_writer::start_div();
        $html .= html_writer::start_tag('h4', array(
                    'class' => 'text-center pl-5 pr-5 mb-4'
        ));
        $html .= get_string('createpassword', 'block_onboarding');
        $html .= html_writer::end_tag('h4');
        $html .= html_writer::end_div();
        $html .= html_writer::start_div('d-flex flex-column flex-grow-1 mt-3');
        $html .= html_writer::start_div('info-item mb-4');
        $html .= html_writer::start_tag('label', array(
                    'class' => 'form-label',
                    'style' => 'font-size: 10px; letter-spacing: normal;'
        ));
        $html .= get_string('newpassword', 'block_onboarding');
        $html .= html_writer::end_tag('label');
        $typeyourpassword = get_string('typeyourpassword', 'block_onboarding');
        $html .= html_writer::tag('input', '', array(
                    'type' => 'password',
                    'id' => 'password',
                    'class' => 'form-control rounded-lg',
                    'placeholder' => $typeyourpassword,
                    'required' => ''
        ));
        $html .= html_writer::start_tag('small', array(
                    'class' => 'form-text text-muted'
        ));
        $html .= get_string('passwordrequirements', 'block_onboarding');
        $html .= html_writer::end_tag('small');
        $html .= html_writer::end_div();
        $html .= html_writer::start_div('info-item mb-3');
        $html .= html_writer::start_tag('label', array(
                    'class' => 'form-label',
                    'style' => 'font-size: 10px; letter-spacing: normal;'
        ));
        $html .= get_string('passwordconfirmation', 'block_onboarding');
        $html .= html_writer::end_tag('label');
        $typeyourpasswordagain = get_string('retypepassword', 'block_onboarding');
        $html .= html_writer::tag('input', '', array(
                    'type' => 'password',
                    'id' => 'confirmPassword',
                    'class' => 'form-control rounded-lg',
                    'placeholder' => $typeyourpasswordagain,
                    'required' => ''
        ));
        $html .= html_writer::end_div();
        $html .= html_writer::end_div();
        $html .= html_writer::start_div('form-group d-flex justify-content-end mt-4 gap-2');
        $html .= html_writer::tag('button', get_string('back'), array(
                    'type' => 'button',
                    'onclick' => 'switchForms("passwordScreen", "dataScreen");',
                    'class' => 'btn btn-outline-primary btn-rounded me-2 mr-3'
        ));
        $html .= html_writer::tag('button', get_string('forward', 'block_onboarding'), array(
                    'type' => 'button',
                    'onclick' => 'validatePasswordScreen() && switchForms("passwordScreen", "avatarScreen");',
                    'class' => 'btn btn-primary btn-rounded'
        ));
        $html .= html_writer::end_div();
        $html .= html_writer::end_div();
        $html .= html_writer::end_div();
        $html .= '<!-- Password screen END -->';

        $html .= '<!-- Avatar screen BEGIN -->';
        $html .= html_writer::start_div('card rounded-lg p-4 mb-24pt w-50 pt-5 pl-5 pr-5', array(
                    'id' => 'avatarScreen',
                    'style' => 'display: none; width: 600px; transition: opacity 0.5s ease-in-out; align-self: baseline; margin-top: 5rem !important;'
        ));
        $html .= html_writer::start_div('d-flex flex-column h-100');
        $html .= html_writer::start_div();
        $html .= html_writer::start_tag('h4', array(
                    'class' => 'text-center pl-5 pr-5 mb-4'
        ));
        $html .= get_string('chooseavatar', 'block_onboarding');
        $html .= html_writer::end_tag('h4');
        $html .= html_writer::end_div();
        $html .= html_writer::start_div('flex-grow-1 d-flex align-items-center');
        $html .= html_writer::start_div('carousel slide w-100', array(
                    'id' => 'avatarCarousel',
                    'data-interval' => 'false'
        ));
        $html .= html_writer::start_div('carousel-inner text-center', array(
                    'id' => 'avataroption'
        ));
        $avatars = local_profile_get_avatars('pdi');
        $numavatars = count($avatars);
        $numavatargroups = round($numavatars / 3);
        for ($numgroup = 0; $numgroup < $numavatargroups; ++$numgroup) {
            $html .= html_writer::start_div('carousel-item' . ($numgroup == 0 ? ' active' : ''), array(
                        'style' => 'margin-left: 23px;'
            ));
            $html .= html_writer::start_div('avatar-group');
            for ($i = ($numgroup * 3) + 1; $i <= ($numgroup * 3) + 3; ++$i) {
                $avatarurl = $avatars[$i];
                // Browser needs wwwroot to load the file in subdirectory
                // installs; updateprofile.php expects the relative path, so we
                // keep that in the data attribute that the AJAX call reads.
                $html .= html_writer::tag('img', '', array(
                            'src' => $CFG->wwwroot . $avatarurl,
                            'class' => 'avatar-option' . ((($i + 1) % 3) == 0 ? ' center-avatar' : ''),
                            'avatar-url' => $avatarurl
                ));
            }
            $html .= html_writer::end_div();
            $html .= html_writer::end_div();
        }
        $html .= html_writer::end_div();
        $html .= html_writer::start_tag('a', array(
                    'class' => 'carousel-control-prev bg-primary200 border-1',
                    'href' => '#avatarCarousel',
                    'role' => 'button',
                    'data-slide' => 'prev'
        ));
        $html .= html_writer::start_tag('i', array(
                    'class' => 'icon fa fa-chevron-left text-primary400',
                    'style' => 'margin-right: 0;'
        ));
        $html .= html_writer::end_tag('i');
        $html .= html_writer::end_tag('a');
        $html .= html_writer::start_tag('a', array(
                    'class' => 'carousel-control-next bg-primary200 border-1',
                    'href' => '#avatarCarousel',
                    'role' => 'button',
                    'data-slide' => 'next'
        ));
        $html .= html_writer::start_tag('i', array(
                    'class' => 'icon fa fa-chevron-right text-primary400',
                    'style' => 'margin-right: 0;'
        ));
        $html .= html_writer::end_tag('i');
        $html .= html_writer::end_tag('a');
        $html .= html_writer::end_div();
        $html .= html_writer::end_div();
        $html .= html_writer::start_div('form-group d-flex justify-content-end mt-4 gap-2');
        $html .= html_writer::tag('button', get_string('back'), array(
                    'type' => 'button',
                    'onclick' => 'switchForms("avatarScreen", ' . ($getpassword ? '"passwordScreen"' : '"dataScreen"') . ');',
                    'class' => 'btn btn-outline-primary btn-rounded me-2 mr-3'
        ));
        $html .= html_writer::tag('button', get_string('finishprofile', 'block_onboarding'), array(
                    'type' => 'button',
                    'onclick' => 'finishOnboardingProfile(event);',
                    'class' => 'btn btn-primary btn-rounded'
        ));
        $html .= html_writer::end_div();
        $html .= html_writer::end_div();
        $html .= html_writer::end_div();
        $html .= '<!-- Forth Form END -->';

        $html .= html_writer::end_div();
        $html .= html_writer::end_div();
        $html .= html_writer::end_div();
        $html .= html_writer::end_div();
        $html .= html_writer::end_div();

        $html .= '<!-- First page END -->';
        return $html;
    }

    function print_second_page($continue) {
        global $USER, $PAGE;

        $html = '';
        $html .= '<!-- Second page BEGIN -->';
        $html .= html_writer::start_div('mdk-header-layout__content page-content', array(
                    'id' => 'secondpage',
                    'style' => 'display: none; height: 927px;'
        ));
        $html .= html_writer::start_div('container d-flex justify-content-center align-items-center', array(
                    'style' => 'min-height: 80vh;'
        ));
        $html .= html_writer::start_div('card text-center', array(
                    'style' => 'max-width: 500px; background-color: rgba(255, 255, 255, 0.9);'
        ));
        $html .= html_writer::start_div('card-body');
        $html .= html_writer::start_div('avatar avatar-xxl mx-auto mb-4', array(
                    'style' => 'margin-top: -50px;'
        ));
        $userpicture = new user_picture($USER);
        $userpicture->size = 140;
        $userpictureurl = $userpicture->get_url($PAGE);
        $html .= html_writer::tag('img', '', array(
                    'src' => $userpictureurl,
                    'alt' => 'avatar',
                    'class' => 'avatar-img rounded-circle border border-4 border-white',
                    'style' => 'width: 100px; height: 100px; object-fit: cover;',
                    'id' => 'userpicture'
        ));
        $html .= html_writer::end_div();
        $html .= html_writer::start_div('card-title');
        $html .= html_writer::start_tag('h2', array(
                    'class' => 'pr-32pt pl-32pt'
        ));
        $html .= get_string('welcomefull', 'block_onboarding', $USER->firstname);
        $html .= html_writer::end_tag('h2');
        $html .= html_writer::end_div();
        $html .= html_writer::start_tag('p', array(
                    'class' => 'card-text pl-16pt pr-16pt',
                    'style' => 'color: rgb(39, 44, 51);'
        ));
        $html .= get_string('contentprepared', 'block_onboarding');
        $html .= html_writer::end_tag('p');
        $buttoncontent = html_writer::start_tag('span', array(
                    'id' => 'btnText'
        ));
        $buttoncontent .= get_string('continue');
        $buttoncontent .= html_writer::end_tag('span');
        $buttoncontent .= html_writer::start_div('spinner-border spinner-border-sm d-none', array(
                    'id' => 'loadingSpinner',
                    'role' => 'status'
        ));
        $buttoncontent .= html_writer::end_div();
        if ($continue) {
            $html .= html_writer::tag('button', $buttoncontent, array(
                        'type' => 'button',
                        'onclick' => 'handleContinue("secondpage", "thirdpage");',
                        'class' => 'btn btn-primary btn-rounded',
                        'id' => 'continueBtn'
            ));
        } else {
            // moodle_url prefixes $CFG->wwwroot, so subdirectory installs
            // (e.g. https://host/moodle/) get the correct absolute URL.
            // The previous bare '/?redirect=0' resolved against the host
            // root and 404'd whenever Moodle wasn't installed at /.
            $continuehref = (new moodle_url('/', array('redirect' => 0)))->out(false);
            $html .= html_writer::tag('a', $buttoncontent, array(
                        'href' => $continuehref,
                        'class' => 'btn btn-primary btn-rounded',
                        'id' => 'continueBtn'
            ));
        }
        $html .= html_writer::end_div();
        $html .= html_writer::end_div();
        $html .= html_writer::end_div();
        $html .= html_writer::end_div();
        $html .= '<!-- Second page END -->';
        return $html;
    }

    function print_player($config) {
        switch ($config->contentformat) {
            case BLOCK_FORMAT_NONE:
                return '';
            case BLOCK_FORMAT_VIMEO:
                $format = get_string('vimeoformatdefault', 'block_onboarding');
                $content = $config->vimeoid;
                break;
            case BLOCK_FORMAT_YOUTUBE:
                $format = get_string('youtubeformatdefault', 'block_onboarding');
                $content = $config->youtubeid;
                break;
            case BLOCK_FORMAT_EMBEDED:
                $format = get_string('embededformatdefault', 'block_onboarding');
                $content = $config->embededurl;
                break;
        }
        $html = '';
        $html .= html_writer::start_div('js-player embed-responsive embed-responsive-16by9');
        $html .= str_replace("{\$a}", $content, $format);
        $html .= html_writer::end_div();
        return $html;
    }

    function print_third_page($config) {
        if (!$config || $config->contentformat == BLOCK_FORMAT_NONE) {
            return '';
        }
        $html = '';
        $html .= '<!-- Third page BEGIN -->';
        $html .= html_writer::start_div('mdk-header-layout__content page-content', array(
                    'id' => 'thirdpage',
                    'style' => 'display: none; height: 927px;'
        ));
        $html .= html_writer::start_div('container d-flex justify-content-center align-items-center', array(
                    'style' => 'padding-top: 5rem;'
        ));
        $html .= html_writer::start_div('card text-center', array(
                    'style' => 'max-width: 1000px; background-color: rgba(255, 255, 255, 0.9); padding: 5px 0px 12px 0px;'
        ));
        $html .= html_writer::start_div('card-body');
        $html .= html_writer::start_div('card-title');
        $html .= html_writer::start_tag('h3', array(
                    'class' => 'pr-32pt pl-32pt mt-3 pb-4'
        ));
        $html .= get_string('learntonavigate', 'block_onboarding');
        $html .= html_writer::end_tag('h3');
        $html .= html_writer::end_div();
        $html .= html_writer::start_div('card-text');
        $html .= $this->print_player($config);
        $html .= html_writer::end_div();
        $buttontext = get_string('accessthecourse', 'block_onboarding');
        $buttontext .= html_writer::start_tag('i', array(
                    'class' => 'icon fa fa-chevron-right',
                    'style' => 'color: unset;'
        ));
        $buttontext .= html_writer::end_tag('i');
        // Same wwwroot-aware fix as in print_second_page() — a bare
        // '/?redirect=0' breaks on subdirectory installs.
        $accesshref = (new moodle_url('/', array('redirect' => 0)))->out(false);
        $html .= html_writer::tag('a', $buttontext, array(
                    'href' => $accesshref,
                    'class' => 'btn btn-primary btn-rounded',
                    'style' => 'margin-top: 1rem;'
        ));
        $html .= html_writer::end_div();
        $html .= html_writer::end_div();
        $html .= html_writer::end_div();
        $html .= html_writer::end_div();
        $html .= '<!-- Third page END -->';
        return $html;
    }

    function get_content() {
        global $CFG, $USER, $PAGE, $OUTPUT;

        require_once($CFG->dirroot . '/local/profile/lib.php');
        $lastprofileupdate = local_profile_get_last_profile_update_time($USER);
        require_once($CFG->dirroot . '/lib/accesslib.php');
        $pagepath = $PAGE->url instanceof moodle_url ? $PAGE->url->out_as_local_url(false) : (string) $PAGE->url;
        if (strpos($pagepath, 'indexsys') === false && ($lastprofileupdate || is_siteadmin())) {
            redirect(new moodle_url('/', ['redirect' => 0]));
        }

        if ($this->content !== NULL) {
            return $this->content;
        }

        $this->content = new stdClass();
        $this->content->footer = '';

        // Inline fallback: this Moodle build has no page_requirements_manager::css_inline().
        // Theme sheet block_onboarding.css (via extrasheet.php) is the primary styling path.
        $bgurl = $OUTPUT->image_url('background', 'block_onboarding')->out(false);

        $html = '';
        $html .= '<!-- Onboarding BEGIN -->';
        $html .= html_writer::start_div('block-onboarding-canvas', [
            'style' => 'min-height:100vh;background-image:url(' . $bgurl . ');'
                . 'background-size:cover;background-position:center center;background-repeat:no-repeat;',
        ]);
        $datetxt = optional_param('date', '', PARAM_TEXT);
        $datetime = strtotime($datetxt);
        $html .= $this->print_first_page($datetime);
        $html .= $this->print_second_page($this->config ? $this->config->contentformat != BLOCK_FORMAT_NONE : false);
        $html .= $this->print_third_page($this->config);
        $html .= html_writer::end_div();
        $html .= '<!-- Onboarding END -->';
        $this->content->text = $html;

        return $this->content;
    }

    function get_required_javascript() {
        parent::get_required_javascript();
        $this->page->requires->js('/blocks/onboarding/block_onboarding.js');
    }

}

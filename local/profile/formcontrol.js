/* eslint-env browser, jquery */
/* global M */

var avatarURL = null;

function toggleAvatarSelector(event) {
    if (event && typeof event.preventDefault === 'function') {
        event.preventDefault();
    }
    var selector = document.getElementById("avatarSelector");
    if (!selector) {
        return;
    }
    selector.style.display = (selector.style.display === "none" ? "block" : "none");
}

function selectAvatar(avatarFile, element) {
    document.querySelectorAll(".avatar-item").forEach(function(item) {
        item.classList.remove("selected");
    });
    if (element) {
        element.classList.add("selected");
    }
    avatarURL = avatarFile;
}

function saveAvatar() {
    if (!avatarURL) {
        return;
    }
    var avatarImg = document.getElementById("avatarImg");
    if (avatarImg) {
        // avatarURL is a wwwroot-relative path (e.g. /local/profile/pix/...).
        // Prepend M.cfg.wwwroot so the preview works in subdirectory installs
        // like http://host/moodle-pdi - otherwise the browser would resolve it
        // against the document host root and 404.
        var wwwroot = (typeof M !== 'undefined' && M.cfg && M.cfg.wwwroot) ? M.cfg.wwwroot : '';
        avatarImg.src = wwwroot + avatarURL;
    }
    var selector = document.getElementById("avatarSelector");
    if (selector) {
        selector.style.display = "none";
    }
}

function updateProfile() {
    var planSelectEl = document.getElementById("planSelect");
    var timeOption = planSelectEl ? planSelectEl.value : "";
    var sesskeyEl = document.getElementById('sesskey');
    var sesskey = sesskeyEl ? sesskeyEl.value : (typeof M !== 'undefined' && M.cfg ? M.cfg.sesskey : "");
    // Use the canonical Moodle config object instead of a non-existent global.
    var wwwroot = (typeof M !== 'undefined' && M.cfg && M.cfg.wwwroot) ? M.cfg.wwwroot : "";

    var params = ['sesskey=' + encodeURIComponent(sesskey)];
    var update = false;
    if (timeOption && Number(timeOption) > 0) {
        params.push('timeoption=' + encodeURIComponent(timeOption));
        update = true;
    }
    if (avatarURL) {
        params.push('avatarurl=' + encodeURIComponent(avatarURL));
        update = true;
    }
    if (!update) {
        return;
    }
    var url = wwwroot + '/local/profile/updateprofile.php?' + params.join('&');
    // Pull strings via M.util.get_string so the lang packs (pt_br / en) are
    // honoured instead of hard-coding Portuguese alerts.
    var getStr = (typeof M !== 'undefined' && M.util && M.util.get_string) ?
        M.util.get_string : null;
    var successMsg = getStr ? getStr('profileupdated', 'local_profile') : 'Profile updated!';
    var defaultErr = getStr ? getStr('profileupdatefailed', 'local_profile') : 'Profile update failed!';
    var nothingMsg = getStr ? getStr('nothingtoupdate', 'local_profile') : 'Nothing to save.';

    var returnToEl = document.getElementById('returnto');
    if (returnToEl && returnToEl.value) {
        params.push('returnto=' + encodeURIComponent(returnToEl.value));
    }

    $.ajax({
        url: url,
        method: 'POST',
        dataType: 'json',
        success: function(data) {
            window.alert(successMsg);
            if (data && data.redirect) {
                window.location.replace(data.redirect);
            }
        },
        error: function(request) {
            var msg = (request && request.responseText) ? request.responseText : defaultErr;
            if (request && request.status === 400 && !request.responseText) {
                msg = nothingMsg;
            }
            window.alert(msg);
        }
    });
}

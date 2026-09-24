/* eslint-env browser */
/* global M, Config */

/*
 * block_onboarding — frontend logic for the new-user onboarding flow.
 *
 * History note: this file used to call jQuery as `$(...)` and `$.ajax(...)`.
 * In modern Moodle (>=4.x) jQuery is loaded via AMD/RequireJS and is *not*
 * guaranteed to be on the global window when an old-style
 * $PAGE->requires->js() script executes — depending on which page hosts the
 * onboarding block, the script could run before jQuery had time to expose
 * itself globally, producing `ReferenceError: $ is not defined`. Rewriting
 * the three jQuery touch-points in vanilla JS removes that timing-dependency
 * entirely and keeps the file self-contained.
 *
 * Functions that the rendered HTML calls via onclick="..." (switchForms,
 * switchPages, selectTimeOption, validateTimeScreen, validateDataScreen,
 * validatePasswordScreen, validateAvatarScreen, updateProfile,
 * handleContinue) MUST remain global declarations — wrapping them in an IIFE
 * or AMD callback would break the onclick wiring rendered server-side.
 */

function switchForms(formSrcId, fromDstId) {
    var formSrc = document.getElementById(formSrcId);
    var formDst = document.getElementById(fromDstId);
    formSrc.style.opacity = "0";
    setTimeout(function () {
        formSrc.style.display = "none";
        formDst.style.display = "block";
        setTimeout(function () {
            formDst.style.opacity = "1";
        }, 50);
    }, 500);
}

function switchPages(pageSrcId, pageDstId) {
    var pageSrc = document.getElementById(pageSrcId);
    var pageDst = document.getElementById(pageDstId);
    pageSrc.style.opacity = "0";
    setTimeout(function () {
        pageSrc.style.display = "none";
        pageDst.style.display = "flex";
        setTimeout(function () {
            pageDst.style.opacity = "1";
        }, 50);
    }, 500);
}

document.addEventListener("DOMContentLoaded", function () {
    var avatarCarousel = document.getElementById("avatarCarousel");
    // The onboarding block only ships its carousel HTML on the welcome page
    // for new users. On any other page that hosts this script (admin
    // pages, dashboard for an existing user, etc.) the carousel is absent
    // and we'd previously crash here with "Cannot read properties of null
    // (reading 'querySelector')". Bail out cleanly when the markup isn't on
    // this page — none of the carousel handlers would have anything to do.
    if (!avatarCarousel) {
        return;
    }

    function moveToCenter(clickedAvatar) {
        var avatars = document.querySelectorAll(".avatar-option");
        var currentCenter = document.querySelector(".center-avatar");
        if (currentCenter) {
            currentCenter.classList.remove("center-avatar", "selected");
            currentCenter.style.opacity = "0.5";
        }
        clickedAvatar.classList.add("center-avatar", "selected");
        clickedAvatar.style.opacity = "1";
        avatars.forEach(function (avatar) {
            if (avatar !== clickedAvatar) {
                avatar.classList.remove("selected");
                avatar.style.opacity = "0.5";
            }
        });
    }

    document.querySelectorAll(".avatar-option").forEach(function (avatar) {
        avatar.addEventListener("click", function (event) {
            // event.target is already a DOM element; the previous
            // $(event.target)[0] dance was a jQuery no-op that
            // happened to also pull in the entire jQuery dependency.
            moveToCenter(event.target);
        });
    });

    document.querySelectorAll(".carousel-control-next").forEach(function (btn) {
        btn.addEventListener("click", function () {
            var currentCenter = document.querySelector(".center-avatar");
            if (!currentCenter) {
                return;
            }
            var nextAvatar = currentCenter.nextElementSibling;
            if (nextAvatar) {
                moveToCenter(nextAvatar);
            }
        });
    });

    document.querySelectorAll(".carousel-control-prev").forEach(function (btn) {
        btn.addEventListener("click", function () {
            var currentCenter = document.querySelector(".center-avatar");
            if (!currentCenter) {
                return;
            }
            var prevAvatar = currentCenter.previousElementSibling;
            if (prevAvatar) {
                moveToCenter(prevAvatar);
            }
        });
    });
});

function selectTimeOption(element) {
    document.querySelectorAll(".time-option").forEach(function (option) {
        option.classList.remove("selected");
    });
    element.classList.add("selected");
}

function validateTimeScreen() {
    var atLeastOneSelected = false;
    document.querySelectorAll(".time-option").forEach(function (option) {
        if (option.classList.contains("selected")) {
            atLeastOneSelected = true;
            var timeOption = document.querySelector("#timeoption");
            timeOption.setAttribute("time-selected", option.getAttribute("data-value"));
        }
    });
    if (!atLeastOneSelected) {
        alert("Você deve selecionar um tempo!");
    }
    return atLeastOneSelected;
}

function validateDataScreen() {
    var fullName = document.querySelector("#fullName");
    if (fullName.value === "") {
        alert("Nome completo não pode ser vazio!");
        return false;
    }
    var email = document.querySelector("#email");
    if (email.value === "") {
        alert("E-mail não pode ser vazio!");
        return false;
    }
    var confirmInfo = document.querySelector("#confirmInfo");
    if (!confirmInfo.checked) {
        alert("Você precisa marcar a declaração de veracidade!");
        return false;
    }
    return true;
}

function validatePasswordScreen() {
    var password = document.querySelector("#password");
    if (password.value.length < 8) {
        alert("Senha dever ter tamanho mínimo de 8!");
        return false;
    }
    if (!/[a-z]/.test(password.value)) {
        alert("Senha deve conter pelo menos uma letra!");
        return false;
    }
    if (!/[0-9]/.test(password.value)) {
        alert("Senha deve conter pelo menos um número!");
        return false;
    }
    var confirmPassword = document.querySelector("#confirmPassword");
    if (password.value !== confirmPassword.value) {
        alert("Confirmação de senha deve ser igual à senha!");
        return false;
    }
    return true;
}

function validateAvatarScreen() {
    var avatars = document.querySelectorAll(".avatar-option");
    var avatarUrl = "";
    avatars.forEach(function (avatar) {
        if (avatar.classList.contains("selected")) {
            // getAttribute returns a string; the previous code chained
            // .value here, which returns undefined for primitive strings
            // and silently made this validator a no-op (it just happened
            // to "work" because the foreach found at least one match).
            avatarUrl = avatar.getAttribute("avatar-url") || "";
            var avatarOption = document.querySelector("#avataroption");
            avatarOption.setAttribute("avatar-selected", avatarUrl);
        }
    });
    if (avatarUrl === "") {
        alert("Um avatar deve ser selecionado!");
        return false;
    }
    return true;
}

/**
 * Read the canonical Moodle wwwroot from whichever global Moodle exposes
 * first. Different page layouts expose different globals — `Config.wwwroot`
 * is what /lib/javascript-config.php emits inline for legacy callers, and
 * `M.cfg.wwwroot` is the modern equivalent surfaced by the AMD loader.
 * Using whichever is present means the script keeps working regardless of
 * which loader populated the globals on this particular page.
 *
 * @returns {string}
 */
function onboardingWwwroot() {
    if (typeof Config !== "undefined" && Config && Config.wwwroot) {
        return Config.wwwroot;
    }
    if (typeof M !== "undefined" && M.cfg && M.cfg.wwwroot) {
        return M.cfg.wwwroot;
    }
    // Last-resort fallback: derive from window.location. Loses the
    // subdirectory portion of wwwroot, so it's only correct on a
    // top-level install — but at least it never throws.
    return window.location.protocol + "//" + window.location.host;
}

function updateProfile() {
    /*
     * Submit the onboarding profile to /local/profile/updateprofile.php.
     *
     * Switched from the previous $.ajax + GET query-string builder to
     * fetch() + URLSearchParams for three reasons (full details in the
     * 2026052601 versionlog entry):
     *
     *   1. PORTABILITY — URLSearchParams URL-encodes every value
     *      correctly, even non-ASCII names and hire dates with slashes.
     *   2. SECURITY — the password is now in the POST body, not in the
     *      URL query string, so it stops being persisted in browser
     *      history, Apache/Nginx access logs and reverse-proxy logs.
     *   3. NO JQUERY DEPENDENCY — see the file header. jQuery is no
     *      longer guaranteed to be on the global scope in Moodle 4.x
     *      page contexts that load this block, so $.ajax was unreliable.
     *
     * updateprofile.php uses optional_param() which accepts both GET and
     * POST, so the server contract is preserved.
     */
    var timeOption  = document.querySelector("#timeoption").getAttribute("time-selected");
    var fullName    = document.querySelector("#fullName").value;
    var email       = document.querySelector("#email").value;
    var hireDate    = document.querySelector("#hireDate").value;
    var passwordEl  = document.querySelector("#password");
    var password    = passwordEl ? passwordEl.value : "";
    var avatarOption = document.querySelector("#avataroption").getAttribute("avatar-selected");
    var sesskey     = document.getElementById("sesskey").value;

    var wwwroot = onboardingWwwroot();

    // avatarOption is a wwwroot-relative path like
    // "/local/profile/pix/avatars/avatar-pdi-3.png". Setting it directly
    // as <img src> resolves against the host root, which 404s on
    // subdirectory installs (e.g. https://host/moodle/). Prefixing with
    // the resolved wwwroot produces the canonical absolute URL on every
    // install layout.
    var userPictureElement = document.querySelector("#userpicture");
    if (userPictureElement && avatarOption) {
        userPictureElement.setAttribute("src", wwwroot + avatarOption);
    }

    var body = new URLSearchParams();
    body.append("sesskey",    sesskey);
    body.append("timeoption", timeOption);
    body.append("fullname",   fullName);
    body.append("email",      email);
    body.append("hiredate",   hireDate);
    body.append("avatarurl",  avatarOption);
    if (password !== "") {
        body.append("password", password);
    }

    // credentials: 'same-origin' preserves the session cookie so
    // require_login() on the server side doesn't 303-redirect to login.
    return fetch(wwwroot + "/local/profile/updateprofile.php", {
        method: "POST",
        credentials: "same-origin",
        body: body
    }).then(function (response) {
        if (!response.ok) {
            return response.text().then(function (text) {
                throw new Error(text || "Falha na atualização do perfil!");
            });
        }
        return true;
    });
}

/**
 * Validate avatar, save profile, then advance to the welcome screen.
 *
 * @param {Event} event
 */
function finishOnboardingProfile(event) {
    if (event) {
        event.preventDefault();
    }
    if (!validateAvatarScreen()) {
        return false;
    }
    updateProfile().then(function () {
        switchPages("firstpage", "secondpage");
    }).catch(function (err) {
        alert(err && err.message ? err.message : "Falha na atualização do perfil!");
    });
    return false;
}

function handleContinue(pageSrcId, pageDstId) {
    var pageSrc = document.getElementById(pageSrcId);
    var pageDst = document.getElementById(pageDstId);
    var button = document.getElementById("continueBtn");
    var spinner = document.getElementById("loadingSpinner");
    var btnText = document.getElementById("btnText");

    button.disabled = true;

    spinner.classList.remove("d-none");
    btnText.textContent = "Preparando...";

    setTimeout(function () {
        pageSrc.style.display = "none";
        pageDst.style.display = "flex";
        setTimeout(function () {
            pageDst.style.opacity = "1";
        }, 50);
    }, 3000);
}

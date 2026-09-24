/* eslint-env browser */

document.addEventListener('DOMContentLoaded', function() {
    var prev = document.querySelector('.left');
    var next = document.querySelector('.right');
    var container = document.querySelector('.avatars');
    var avatars = document.querySelectorAll('.avatars-container .avatar-item');

    if (!prev || !next || !container || !avatars.length) {
        return;
    }

    var currentIndex = Math.floor(avatars.length / 2);
    var val = (avatars.length - 1 - Math.floor(avatars.length / 2)) * 195;
    var translateVal = 0;

    for (var i = 0; i < avatars.length; i++) {
        if (i === Math.floor(avatars.length / 2)) {
            avatars[i].classList.add('current');
        }
        avatars[i].addEventListener('click', function() {
            window.location.hash = 'avatar-confirm';
        });
    }

    var defaultVal = 0;
    if (avatars.length % 2 === 0) {
        defaultVal = 90;
        translateVal -= 90;
        container.style.transform = 'translateX(' + translateVal + 'px)';
    }

    prev.addEventListener('click', function() {
        if (currentIndex - 1 < 0) {
            avatars[currentIndex].classList.remove('current');
            avatars[avatars.length - 1].classList.add('current');
            currentIndex = avatars.length - 1;
            translateVal = -val - defaultVal;
            container.style.transform = 'translateX(' + translateVal + 'px)';
        } else {
            avatars[currentIndex].classList.remove('current');
            avatars[currentIndex - 1].classList.add('current');
            currentIndex -= 1;
            translateVal += 195;
            container.style.transform = 'translateX(' + translateVal + 'px)';
        }
    });

    next.addEventListener('click', function() {
        if (currentIndex + 1 >= avatars.length) {
            avatars[currentIndex].classList.remove('current');
            avatars[0].classList.add('current');
            currentIndex = 0;
            translateVal = val + defaultVal;
            container.style.transform = 'translateX(' + translateVal + 'px)';
            return;
        }
        avatars[currentIndex].classList.remove('current');
        avatars[currentIndex + 1].classList.add('current');
        currentIndex += 1;
        translateVal -= 195;
        container.style.transform = 'translateX(' + translateVal + 'px)';
    });
});

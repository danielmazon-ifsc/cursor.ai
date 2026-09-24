document.addEventListener("DOMContentLoaded", () => {
    const bodyId = document.body.attributes["id"].value;
    if (bodyId.localeCompare("pdi-course-view") === 0) {
        document.querySelectorAll("#currentvideo").forEach((video) => {
            var cmid = video.attributes["cmid"].value;
            var sesskey = document.getElementById('sesskey').value;
            var baseurl = '/mod/video/togglecompletion.php?fromajax=1&completionstate=1';
            var url = baseurl.concat('&id=').concat(cmid).concat('&sesskey=').concat(sesskey);
            $.ajax({url: url});
            console.log('Completion marked for course module: ' + cmid);
        });
    }
});
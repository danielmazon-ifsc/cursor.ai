document.addEventListener("DOMContentLoaded", function () {
    // The body element should always have an id on Moodle pages, but defend
    // against unexpected DOMs so a single missing attribute never blocks the
    // whole script.
    var bodyIdAttr = document.body && document.body.getAttribute("id");
    if (!bodyIdAttr || bodyIdAttr !== "page-site-index") {
        return;
    }

    var tooltip = document.createElement("div");
    tooltip.className = "custom-tooltip";
    document.body.appendChild(tooltip);

    // Read the tooltip/URL from data-* attributes (the renderer emits
    // data-tooltip and data-url). Older theme caches may still serve HTML
    // with the legacy custom "tooltip"/"url" attributes, so fall back to
    // those to avoid breaking the page during a rolling deploy.
    function readAttr(el, dataKey, legacyKey) {
        if (!el) {
            return "";
        }
        var ds = el.dataset || {};
        if (ds[dataKey] !== undefined && ds[dataKey] !== null) {
            return ds[dataKey];
        }
        var legacy = el.getAttribute(legacyKey);
        return legacy === null ? "" : legacy;
    }

    document.querySelectorAll(".interactive-area").forEach(function (area) {
        area.addEventListener("mousemove", function (e) {
            var title = readAttr(area, "tooltip", "tooltip") || "Área Interativa";
            tooltip.textContent = title;
            tooltip.style.display = "block";

            var container = document.querySelector(".containerhome");
            if (!container) {
                return;
            }
            var containerRect = container.getBoundingClientRect();
            var offset = 5;
            var relativeX = e.clientX - containerRect.left;
            var relativeY = e.clientY - containerRect.top;
            tooltip.style.left = (containerRect.left + relativeX + offset) + "px";
            tooltip.style.top = (containerRect.top + relativeY + offset) + "px";
        });
        area.addEventListener("mouseout", function () {
            tooltip.style.display = "none";
        });
        area.addEventListener("click", function () {
            if (area.classList.contains("locked-area")) {
                return;
            }
            var url = readAttr(area, "url", "url");
            if (url && url !== "#") {
                window.location.href = url;
            }
        });
    });
});

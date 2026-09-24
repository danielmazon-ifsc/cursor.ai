document.addEventListener("DOMContentLoaded", () => {
    const bodyId = document.body.attributes["id"].value;
    if (bodyId.localeCompare("page-site-index") === 0) {

        const tooltip = document.createElement("div");
        tooltip.className = "custom-tooltip";
        document.body.appendChild(tooltip);

        document.querySelectorAll(".interactive-area").forEach((area) => {
            area.addEventListener("mousemove", (e) => {
                const title = area.attributes["tooltip"].value || "Área Interativa";
                tooltip.textContent = title;
                tooltip.style.display = "block";

                const container = document.querySelector('.containerhome');
                const containerRect = container.getBoundingClientRect();
                const offset = 5;
                const relativeX = e.clientX - containerRect.left;
                const relativeY = e.clientY - containerRect.top;
                tooltip.style.left = `${containerRect.left + relativeX + offset}px`;
                tooltip.style.top = `${containerRect.top + relativeY + offset}px`;
            });
            area.addEventListener("mouseout", () => {
                tooltip.style.display = "none";
            });
            area.addEventListener("click", () => {
                if (!area.classList.contains("locked-area")) {
                    window.location.href = area.attributes["url"].value;
                }
            });
        });
    
    }
});
// Gestion du clic sur l'area
document.querySelectorAll(".area").forEach(area => {
    area.addEventListener("click", () => {
        area.querySelector("label").click()
    })
})
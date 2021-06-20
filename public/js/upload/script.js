// Gestion du clic sur l'area
document.querySelectorAll(".area").forEach(area => {
    area.addEventListener("click", () => {
        area.querySelector("label").click()
    })
})

// Changement du label si un fichier est déposé
document.querySelectorAll("input[type='file']").forEach(input => {
    input.addEventListener("change", e => {
        input.parentElement.querySelector("label").innerHTML
            = "Fichier déposé : " + e.target.files[0].name
    })
})
// Gestion du clic sur l'area et du glisser-déposer
document.querySelectorAll(".area").forEach(area => {
    area.addEventListener("click", () => {
        area.querySelector("label").click()
    })

    area.addEventListener("dragover", e => {
        // Empêche le fichier de s'ouvrir et permet de déclencher l'event drop
        e.preventDefault()
        // On change le label
        area.querySelector("label").innerHTML =
            "Relâchez pour déposer"
    })

    area.addEventListener("dragleave", () => {
        // On remet le label original
        area.querySelector("label").innerHTML =
            area.dataset.originalMessage
    })

    area.addEventListener("drop", e => {
        // Empêche le fichier de s'ouvrir
        e.preventDefault();
        // On récupère le fichier
        let dt;
        if (e.dataTransfer.items) {
            if (e.dataTransfer.items[0].kind === 'file') {
                let file = e.dataTransfer.items[0].getAsFile();
                dt = new DataTransfer()
                dt.items.add(file)
            }
        } else {
            dt = e.dataTransfer
        }
        // On ajoute le fichier à l'input
        area.querySelector("input[type='file']").files = dt.files
        area.querySelector("label").innerHTML =
            "Fichier déposé : " + dt.files[0].name
    })
})

// Changement du label si un fichier est déposé
document.querySelectorAll("input[type='file']").forEach(input => {
    input.addEventListener("change", e => {
        input.parentElement.querySelector("label").innerHTML
            = "Fichier déposé : " + e.target.files[0].name
    })
})
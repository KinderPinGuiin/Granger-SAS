window.addEventListener("load", () => {
    // On ajoute le WYSIWYG
    new WYSIWYG(".WYSIWYG", CLASS_FOLDER + "WYSIWYG/", [])
    // On ajout un listener qui remplit l'input lors de l'envoi
    const form = document.querySelector(".mail_form")
    const mailArea = form.querySelector(".WYSIWYG .editor")
    form.addEventListener("submit", e => {
        e.preventDefault()
        form.querySelector(".content").value = mailArea.innerHTML
        e.target.submit()
    })

    // Définit le contenu du select selon sa valeur
    function setNewContent(select, mailArea) {
        if (select.value == "1") {
            mailArea.innerHTML = document.querySelector(".accept_content").innerHTML.trim()
        } else {
            mailArea.innerHTML = document.querySelector(".deny_content").innerHTML.trim()
        }
    }
    const select = form.querySelector("select")
    setNewContent(select, mailArea)
    // On change le contenu du mail par défaut si le select change
    select.addEventListener("change", () => {
        setNewContent(select, mailArea)
    })
})
window.addEventListener("load", () => {
    // WYSIWYG sur les offres
    new WYSIWYG(
        ".WYSIWYG", CLASS_FOLDER + "WYSIWYG/", 
        [], document.querySelector(".update_form .content").value
    )

    // On gère la soumission du formulaire pour remplir les input hidden
    document.querySelector(".update_form").addEventListener("submit", e => {
        e.preventDefault()
        e.originalTarget.querySelector(".update_form .content").value =
            e.originalTarget.querySelector(".WYSIWYG .editor").innerHTML
        e.target.submit()
    })
})
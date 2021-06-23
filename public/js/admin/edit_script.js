"use strict"

// On ajoute le WYSIWYG et on focus dedans
window.addEventListener("load", () => {
    const wysiwygHome = new WYSIWYG(
        ".WYSIWYG_accueil", CLASS_FOLDER + "WYSIWYG/", 
        [], document.querySelector(".homeContent").innerHTML
    )
    const wysiwygAbout = new WYSIWYG(
        ".WYSIWYG_about", CLASS_FOLDER + "WYSIWYG/",
        [], document.querySelector(".aboutContent").innerHTML
    )

    // On gère la soumission du formulaire pour remplir les input hidden
    document.querySelectorAll(".edit_form").forEach(form => {
        form.addEventListener("submit", e => {
            e.preventDefault()
            e.originalTarget.querySelector(".editedContent").value =
                e.originalTarget.querySelector(".WYSIWYG .editor").innerHTML
            e.target.submit()
        })
    })

    // On gère l'upload d'image
    document.querySelector(".upload_image form").addEventListener("submit", e => {
        e.preventDefault()
        let data = new FormData(document.querySelector(".upload_image form"))
        $.ajax({
            type: "POST",
            enctype: 'multipart/form-data',
            url: "/image/upload",
            data: data,
            processData: false,
            contentType: false,
            cache: false,
            success: function (data) {
                console.log(data)
            },
            error: function (e) {
                console.log(e)
            }
        });
    })
})

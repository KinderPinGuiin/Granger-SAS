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
})

// On gère l'upload des images
// document.querySelector(".upload").addEventListener("submit", e => {
//     e.preventDefault()
//     // On récupère les données du formulaire
//     // const files = e.target.querySelector("input[type='file']").files
//     let formData = new FormData(document.querySelector(".upload"))
//     $.ajax({
//         url: "/image/upload",
//         enctype: 'multipart/form-data',
//         type: "POST",
//         data: formData,
//         processData: false,
//         contentType: false,
//         cache: false,
//         timeout: 800000,
//         success: function (data) {
//             console.log(data)
//         },
//         error: function (e) {
//             console.log(e.responseJSON)
//         }
//     })
// })
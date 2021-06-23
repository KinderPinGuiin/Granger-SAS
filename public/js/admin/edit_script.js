"use strict"

// On ajoute le WYSIWYG et on focus dedans
window.addEventListener("load", () => {
    new WYSIWYG(
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

    wysiwygAbout.addButton(
        "image", CLASS_FOLDER + "WYSIWYG/logos/image_button.png", 
        "Bouton ajouter une image", "Ajouter une image", displayImageManager
    )

    function displayImageManager() {
        const container = document.querySelector(".image_manager")
        container.style.display = "flex"
        listImage(container.querySelector(".images_container"))
        // On adapte la taille de l'image
    }

    function uploadImage() {
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
                success: (data) => {
                    console.log(data)
                },
                error: (e) => {
                    console.log(e)
                }
            })
        })
    }

    function listImage(container) {
        // Listing des images
        container.setAttribute("data-status", "loading")
        container.querySelector(".images").innerHTML = ""
        $.ajax({
            type: "POST",
            url: "/images",
            success: (data) => {
                let imageContainer, imageElement, deleteButton, i
                for (i in data) {
                    deleteButton = document.createElement("div")
                    deleteButton.classList.add("close_button")
                    deleteButton.setAttribute("data-id", i)
                    deleteButton.addEventListener("click", e => {
                        deleteImage(e.target)
                    })

                    imageElement = document.createElement("img")
                    imageElement.setAttribute("src", data[i].url)
                    imageElement.setAttribute("alt", data[i].alt)
                    // On adapte la taille de l'image
                    if (data[i].width > data[i].height) {
                        imageElement.style.width = "100%"
                        imageElement.style.height = "auto"
                    } else {
                        imageElement.style.width = "auto"
                        imageElement.style.height = "100%"
                    }

                    imageContainer = document.createElement("div")
                    imageContainer.classList.add("image")

                    imageContainer.appendChild(imageElement)
                    imageContainer.appendChild(deleteButton)
                    container.querySelector(".images").appendChild(imageContainer)
                }
                container.setAttribute("data-status", "show")
            },
            error: (e) => {
                console.log(e)
            }
        })
    }

    function deleteImage(buttonClicked) {
        $.ajax({
            type: "POST",
            url: "/image/delete/" + buttonClicked.dataset.id,
            success: (data) => {
                buttonClicked.parentElement.remove()
            },
            error: (e) => {
                console.log(e)
            }
        })
    }
})

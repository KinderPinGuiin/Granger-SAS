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
        // On ajoute les event listeners
        container.querySelector(".upload_btn_container button").addEventListener("click", () => {
            switchUpload(container)
        })
        container.querySelector(".select_btn_container button").addEventListener("click", () => {
            switchSelect(container)
        })
        container.querySelector(".add_btn_container button").addEventListener("click", () => {
            wysiwygAbout.editor.appendChild(document.querySelector(".image[data-selected='true'] img"))
        })
        document.querySelector(".upload_image_container form").addEventListener("submit", e => {
            e.preventDefault()
            uploadImage(".upload_image_container form", () => {
                switchSelect(container)
                listImage(container.querySelector(".images_container"), () => bindEventsOnImages(container))
            }, e => {
                document.querySelector(".upload_image_container form").innerHTML +=
                    "<div class='error'>" + e.responseJSON.error + "</div>"
            })
        })
        document.querySelector(".image_manager > .close_button").addEventListener("click", () => {
            container.style.display = "none"
        })
        // On affiche la pop-up
        container.style.display = "block"
        // On liste les images
        listImage(container.querySelector(".images_container"), () => bindEventsOnImages(container))
    }

    function switchSelect(container) {
        container.querySelector(".select_btn_container").style.display = "none"
        container.querySelector(".upload_btn_container").style.display = "flex"
        container.querySelector(".upload_image_container").style.display = "none"
        container.querySelector(".images_container").style.display = "flex"
    }

    function switchUpload(container) {
        container.querySelector(".upload_btn_container").style.display = "none"
        container.querySelector(".select_btn_container").style.display = "flex"
        container.querySelector(".images_container").style.display = "none"
        container.querySelector(".upload_image_container").style.display = "flex"
    }

    function uploadImage(formSelector, success, failure) {
        // On gère l'upload d'image
        let data = new FormData(document.querySelector(formSelector))
        $.ajax({
            type: "POST",
            enctype: 'multipart/form-data',
            url: "/image/upload",
            data: data,
            processData: false,
            contentType: false,
            cache: false,
            success: success,
            error: failure
        })
    }

    function listImage(container, callback = null) {
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

                    imageElement = document.createElement("img")
                    imageElement.setAttribute("src", data[i].url)
                    imageElement.setAttribute("alt", data[i].alt)
                    imageElement.setAttribute("data-original-width", data[i].width)
                    imageElement.setAttribute("data-original-height", data[i].height)
                    // On adapte la taille de l'image
                    if (parseInt(data[i].width) > parseInt(data[i].height)) {
                        imageElement.style.width = "100%"
                        imageElement.style.height = "auto"
                    } else {
                        imageElement.style.width = "auto"
                        imageElement.style.height = "100%"
                    }

                    imageContainer = document.createElement("div")
                    imageContainer.classList.add("image")
                    imageContainer.setAttribute("data-id", i)

                    imageContainer.appendChild(imageElement)
                    imageContainer.appendChild(deleteButton)
                    container.querySelector(".images").appendChild(imageContainer)
                }
                container.setAttribute("data-status", "show")
                if (callback instanceof Function) {
                    callback()
                }
            },
            error: (e) => {
                console.log(e)
            }
        })
    }

    function deleteImage(buttonClicked, success, failure) {
        $.ajax({
            type: "POST",
            url: "/image/delete/" + buttonClicked.dataset.id,
            success: success,
            error: failure
        })
    }

    function bindEventsOnImages(container) {
        // On ajoute les events sur les images
        let images = container.querySelectorAll(".images_container .image")
        container.querySelector(".images_container").addEventListener("click", () => {
            document.querySelector(".image_manager .add_btn_container")
                .style.display = "none"
            document.querySelector(".image_manager .upload_btn_container")
                .style.display = "flex"
            images.forEach(image => {
                image.setAttribute("data-selected", null)
                image.style.outline = "none"
            })
        })
        images.forEach(image => {
            image.addEventListener("click", e => {
                imageFocus(e, images, image)
            })
            image.querySelector(".close_button").addEventListener("click", e => {
                deleteImageOnClick(e)
            })
        })
    }

    function imageFocus(e, images, image) {
        e.stopPropagation()
        if (image.style.outline != "blue solid 2px") {
            document.querySelector(".image_manager .upload_btn_container")
                .style.display = "none"
            document.querySelector(".image_manager .add_btn_container")
                .style.display = "flex"
            images.forEach(image => {
                image.setAttribute("data-selected", null)
                image.style.outline = "none"
            })
            image.setAttribute("data-selected", true)
            image.style.outline = "2px solid blue"
        } else {
            document.querySelector(".image_manager .add_btn_container")
                .style.display = "none"
            document.querySelector(".image_manager .upload_btn_container")
                .style.display = "flex"
            image.style.outline = "none"
        }
    }

    function deleteImageOnClick(e) {
        e.stopPropagation()
        document.querySelector(".image_manager .add_btn_container")
            .style.display = "none"
        document.querySelector(".image_manager .upload_btn_container")
            .style.display = "flex"
        deleteImage(e.target, () => {
            e.target.parentElement.remove()
        }, (e) => {
            console.log(e)
        })
    }
})

"use strict"

// On ajoute le WYSIWYG et on focus dedans
window.addEventListener("load", () => {
    new WYSIWYG(".WYSIWYG_accueil", CLASS_FOLDER + "WYSIWYG/")
    new WYSIWYG(".WYSIWYG_about", CLASS_FOLDER + "WYSIWYG/")
})
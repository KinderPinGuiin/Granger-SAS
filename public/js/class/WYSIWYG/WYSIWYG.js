"use strict"

class WYSIWYG {
    /**
     * Constructeur de la classe WYSIWYG
     * 
     * @param string selector  Le sélecteur du conteneur WYSIWYG 
     * @param string classPath Le chemin absolu du fichier de la classe
     * @param array  buttons   Un JSON contenant les boutons
     * @param string content   Le contenu par défaut du WYSIWYG
     */
    constructor(selector, classPath, buttons = [], content = "") {
        if (typeof selector != "string" || typeof selector != "string" ||
            (buttons).constructor != ([]).constructor || typeof content != "string") {
            throw "Invalid parameters"
        }

        /*
         * Déclaration des propriétées
         */

        this.container = document.querySelector(selector);
        if (this.container === null) {
            throw "Invalid selector";
        }
        this.editor = document.createElement("div")
        this.editor.classList.add("editor")
        // On enlève le correcteur
        this.editor.innerHTML = content
        this.buttonsContainer = document.createElement("div")
        this.buttonsContainer.classList.add("buttonsContainer")
        this.buttonList = [{
            "bold": {
                apply: () => {
                    document.execCommand("bold", false)
                },
                logo: classPath + "logos/bold.png",
                logoAlt: "Gras",
                desc: "Permet de mettre le texte en gras"
            },
            "italic": {
                apply: () => {
                    document.execCommand("italic", false)
                },
                logo: classPath + "logos/italic.png",
                logoAlt: "Italique",
                desc: "Permet de mettre le texte en italique"
            },
            "underline": {
                apply: () => {
                    document.execCommand("underline", false)
                },
                logo: classPath + "logos/underline.png",
                logoAlt: "Souligner",
                desc: "Permet de souligner le texte"
            },
            "title": {
                apply: () => {
                    document.execCommand("formatBlock", false, "h1")
                    // On ajoute une ligne vide à la fin du conteneur
                    // pour ne pas rester bloqué dans le bloc
                    this.editor.innerHTML = this.editor.innerHTML.trim()
                    this.editor.innerHTML += "<br/>"
                },
                logo: classPath + "logos/title.png",
                logoAlt: "Bloc de code",
                desc: "Ajoute un titre"
            },
            "justifyLeft": {
                apply: () => {
                    document.execCommand("justifyLeft", false)
                },
                logo: classPath + "logos/justifyLeft.png",
                logoAlt: "Aligner à gauche",
                desc: "Permet d'aligner le texte à gauche"
            },
            "justifyCenter": {
                apply: () => {
                    document.execCommand("justifyCenter", false)
                },
                logo: classPath + "logos/justifyCenter.png",
                logoAlt: "Centrer",
                desc: "Permet de centrer le texte"
            },
            "justifyRight": {
                apply: () => {
                    document.execCommand("justifyRight", false)
                },
                logo: classPath + "logos/justifyRight.png",
                logoAlt: "Aligner à droite",
                desc: "Permet d'aligner le texte à droite"
            },
            "justifyFull": {
                apply: () => {
                    document.execCommand("justifyFull", false)
                },
                logo: classPath + "logos/justifyFull.png",
                logoAlt: "Ajuster",
                desc: "Permet d'ajuster le texte"
            },
            "codeBlock": {
                apply: () => {
                    document.execCommand("formatBlock", false, "pre")
                    // On ajoute une ligne vide à la fin du conteneur
                    // pour ne pas rester bloqué dans le bloc de code
                    this.editor.innerHTML = this.editor.innerHTML.trim()
                    this.editor.innerHTML += "<br/>"
                },
                logo: classPath + "logos/codeBlock.png",
                logoAlt: "Bloc de code",
                desc: "Permet de créer un bloc de code"
            }
        }]

        /*
         * Actions du constructeur
         */

        let defaultButtons = [
            "bold", "italic", "underline", "title", "justifyLeft", "justifyCenter", "justifyRight", "justifyFull"
        ]
        // On créé la liste des boutons
        this.buttons = defaultButtons
        buttons.forEach(button => {
            if (this.buttons.indexOf(button) < 0) {
                this.buttons.push(button)
            }
        })

        // On créé l'éditeur
        this.container.appendChild(this.buttonsContainer)
        this.container.appendChild(this.editor)
        this.editor.contentEditable = "true"

        // On ajoute les boutons à la barre
        this.buttons.forEach(button => {
            this.addToList(button)
        })
    }

    /**
     * Créé un bouton et l'ajoute à la barre
     */
    addButton(name, logo, logoAlt, desc, apply) {
        this.buttonList[0][name] = {
            "logo": logo,
            "logoAlt": logoAlt,
            "desc": desc,
            "apply": apply
        }
        this.buttons.push(name)
        this.addToList(name)
    }

    /**
     * Ajoute le bouton à la barre et ajout les events listener
     * @param {string} button Le nom du bouton 
     */
    addToList(button) {
        let _button = document.createElement("button")
        _button.setAttribute("title", this.buttonList[0][button]["desc"])
        _button.innerHTML = `<img src="${this.buttonList[0][button]["logo"]}" 
                                alt="${this.buttonList[0][button]["logoAlt"]}"
                                width="20" height="20" />`
        this.buttonsContainer.appendChild(_button)

        // On ajoute les events listeners
        _button.addEventListener("click", (e) => {
            e.preventDefault()
            this.buttonList[0][button]["apply"]()
        })

        _button.addEventListener("mousedown", () => {
            _button.style.boxShadow = "inset 0px 0px 10px 0px rgba(0,0,0,0.75)"
        })

        _button.addEventListener("mouseup", () => {
            _button.style.boxShadow = "none"
        })
    }
}          
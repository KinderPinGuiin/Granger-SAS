const searchBar = document.querySelector(".search_bar")
const searchIn = document.querySelectorAll("[data-search='true']")

searchBar.addEventListener("keyup", e => {
    if (
        (/^[a-z1-9]$/i).test(e.key) 
        || e.key === " " 
        || e.key.toLowerCase() === "backspace" && searchBar.value !== ""
        || searchBar.value === ""
    ) {
        searchIn.forEach(element => {
            if (
                element.innerHTML.toLowerCase()
                    .includes(searchBar.value.toLowerCase().trim())
            ) {
                element.style.display = "block"
            } else {
                element.style.display = "none"
            }
        })
    }
})
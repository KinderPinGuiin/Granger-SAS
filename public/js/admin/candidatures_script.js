const searchBar = document.querySelector(".search_bar")
const candidatures = document.querySelectorAll(".candidature")

searchBar.addEventListener("keyup", e => {
    if (
        (/^[a-z1-9]$/i).test(e.key) 
        || e.key === " " 
        || e.key.toLowerCase() === "backspace" && searchBar.value !== ""
        || searchBar.value === ""
    ) {
        candidatures.forEach(candidature => {
            if (
                candidature.querySelector("a").innerHTML.toLowerCase()
                    .includes(searchBar.value.toLowerCase().trim())
            ) {
                candidature.style.display = "block"
            } else {
                candidature.style.display = "none"
            }
        })
    }
})
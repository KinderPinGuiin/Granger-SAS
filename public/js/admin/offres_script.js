// Mettre en ligne une offre directement depuis la page des offres
document.querySelectorAll("#online").forEach(checkbox => {
    checkbox.addEventListener("change", setOnline)
})

function setOnline(e) {
    e.stopPropagation()
    const offreId = e.target.parentElement.dataset.id
    $.ajax({
        type: "GET",
        url: "/admin/offres/set-online/" + offreId,
        data: {
            "onlineValue": e.target.parentElement.querySelector("#online").checked
        },
        success: data => console.info(data),
        error: err => console.warn(err)
    })
}
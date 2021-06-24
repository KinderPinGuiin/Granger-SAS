document.querySelectorAll("label[for='online']").forEach(label => {
    label.addEventListener("click", setOnline)
    document.querySelector("#online")
        .addEventListener("click", setOnline)
})

function setOnline(e) {
    const offreId = e.target.parentElement.dataset.id
    $.ajax({
        type: "GET",
        url: "/admin/offres/set-online/" + offreId,
        data: {
            "onlineValue": e.target.parentElement.querySelector("#online").checked
        },
        success: data => console.log(data),
        error: err => console.warn(err)
    })
}
$(document).ready(() => {
    $(".document_form").on("submit", e => {
        e.preventDefault()
        $.ajax({
            method: "POST",
            enctype: 'multipart/form-data',
            url: "/profil/upload-documents",
            data: new FormData(e.target),
            processData: false,
            contentType: false,
            cache: false,
            timeout: 800000,
            success: function (data) {
                e.target.querySelector(".status").innerHTML = data.message
            },
            error: function (err) {
                e.target.querySelector(".status").innerHTML = err.responseJSON.error
            }
        })
    })
})
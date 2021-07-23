$(document).ready(() => {
    $(".document_form").on("submit", e => {
        e.preventDefault()
        let form = new FormData(e.target)
        $.ajax({
            method: "POST",
            enctype: 'multipart/form-data',
            url: "/profil/upload-documents",
            data: form,
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
document.querySelector(".signup_form").addEventListener("submit", e => {
    e.preventDefault()
    const key = e.target.querySelector("input[name='geocoding_key']").value
    const location = e.target.querySelector("input.signup_city").value
    $.ajax({
        url: "http://www.mapquestapi.com/geocoding/v1/address?key=" + key + "&location=" + location,
        success: data => {
            let latLng = data.results[0].locations[0].latLng
            e.target.querySelector("input.latitude").value = latLng.lat
            e.target.querySelector("input.longitude").value = latLng.lng
        },
        failure: err => console.error(err)
    })
    e.target.submit()
})
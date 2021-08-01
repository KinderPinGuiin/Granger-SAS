function initMap() {
    const map = new google.maps.Map(document.getElementById("map"), {
        zoom: 6,
        center: { lat: 47.08247, lng: 2.39685 },
    })
    $.ajax({
        url: "/admin/map/markers",
        success: data => {
            data.markers.forEach(marker => {
                let _marker = new google.maps.Marker({
                    position: { lat: marker.lat, lng: marker.long },
                    icon: marker.icon,
                    map: map
                })
                _marker.addListener("click", () => {
                    window.location = marker.user
                })
            })
        },
        failure: err => console.error(err)
    })
}
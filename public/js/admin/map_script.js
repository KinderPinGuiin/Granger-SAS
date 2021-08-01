function initMap() {
    const france = { lat: 47.08247, lng: 2.39685 };
    const map = new google.maps.Map(document.getElementById("map"), {
        zoom: 6,
        center: france,
    });
    const marker = new google.maps.Marker({
        position: france,
        map: map,
    });
}  
var markersArray = [];
var map;
var infowindow;

//position = {lat: -99.99, lgn: -99.99}
function initMap(position, idMap = 'divMapa', zoom = 13) {
    if(typeof position == 'undefined') {
        setLatLgtEmpresa();
        var position = {lat: latitude, lng: longitude};
    }
    map = new google.maps.Map(document.getElementById(idMap), {
        center: position,
        zoom: zoom
    });
    
    if(typeof iconsLegend != 'undefined')
        createLegends(iconsLegend);
}

function addMarker(position, pathImage, size = 40, title = ' ', contentInfo = false, callback = null, callmaps) {
    var icon = {
        url: root + pathImage, // url
        scaledSize: new google.maps.Size(size, size), // scaled size
    };
    var marker = new google.maps.Marker({
      position: position,
      icon: icon,
      map: map,
      title: title
    });
    if(contentInfo != false){
        marker.addListener('click', function() {
            if (infowindow) 
                infowindow.close();
            infowindow = new google.maps.InfoWindow({
                content: contentInfo    
            });
            if(typeof callback == 'function')
                callback();

            infowindow.open(map, marker);
        });
        google.maps.event.addListener(map, 'click', function(){
            if(map !== null && typeof map !== "undefined"){
                if(typeof $("#info-window").attr('opened') != 'undefined'){
                    if(typeof callmaps == 'function')
                        callmaps();
                    infowindow.close(map, marker);
                }
            }
        });
    }
    markersArray.push(marker);
}
function clearAllMarkers () {
    $.each(markersArray, function (i, el) {
        el.setMap(null);
    });
    markersArray = [];
}

function createLegends (icons) {
    var legend = document.getElementById('legendMaps');
    for (var key in icons) {
        var type = icons[key];
        var name = type.name;
        var icon = type.icon;
        var div = document.createElement('div');
        div.innerHTML = '<img src="' + icon + '"> ' + name;
        legend.appendChild(div);
    }
    map.controls[google.maps.ControlPosition.LEFT_BOTTOM].push(legend);
}

function goCenter(){
    setLatLgtEmpresa();
    var position = {lat: latitude, lng: longitude};
    map.panTo(position);
}
/*
 * Copyright 2007, Nico Goeminne.
 *
 * All use in any way is strickly forbidden, except for
 * Mapperz <mapperz@googlemail.com> and
 * Pamela Fox (Google Map API)
 *
 * NOTE:
 *  - THIS CODE IS CONFIDENTIAL!
 *  - THIS CODE WILL BE FREE (OPEN SOURCE) IN THE FUTURE AS
 *    LEGAL MATTERS ARE RESOLVED
 */


/*
 * Class GReverseGeocoder v1.0
 *
 * This class is used to obtain reverse geocodes (addresses) for user specified points.
 * It does not use any caching mechanisme and is limited to 10 000 request per day.
 * It uses both GDirections as GClientGeocoder. The default country is set to "Belguim".
 * The country could be set using the GReverseGeocoder.setBaseCountry before method
 * before any reverse geocoding is attempted.
 *
 * All data is obtained by use of the Google Maps API only!
 * Therefore Reverse Geocoding is only supported for those countries in which Google Maps
 * supports Geocoding (GClientGeocoder) and Driving Directions (GDirections).
 * As a result only street level results are returned. (No house numbers)
 */

/*
 * Creates a new instance of a reversegeocoder.
 * Note that the reverseGeocode() method initiates a new query,
 * which in turn triggers a "load" event once the query has finished loading.
 *
 * Additionally, the object contains two event listeners which you can intercept:
 *
 *  "load":  This event is triggered when the results of a reverse geocode query issued via
 *           GReverseGeocoder.reverseGeocode() are available. Note that the reverseGeocode() method
 *           initiates a new query, which in turn triggers a "load" event once the query has finished
 *           loading.
 *
 *  "error": This event is triggered if a reverse geocode request results in an error.
 *           Callers can use GReverseGeocoder.getStatus() to get more information about the error.
 *           In GReverseGeocoder v1.0 the getStatus() method proxies the GDirections.getStatus() behavior.
 */
function GReverseGeocoder(map)
{
  // we don't actually need the map variable but to be sure the Google Map API is loaded
  this.map=map;
  this.gdirections = new GDirections();
  this.geocoder = new google.maps.Geocoder();
  this.country="Brazil";
  this.lastpoint=null;
  this.experimental=false;
  this.ad="";
  this.step=10;
  this.start=1;
  this.gdirectionsrefine = new GDirections();
  GEvent.bind(this.gdirections, "error", this, this.handleError);
  GEvent.bind(this.gdirections, "load", this, this.processDirection);
  GEvent.bind(this.gdirectionsrefine, "error", this, this.handleError);
  GEvent.bind(this.gdirectionsrefine, "load", this, this.processDirectionRefine);
}

/*
 * Sets the reversegeocoder for use specified by the given country.
 * GReverseGeocoder.setBaseCountry() should be called before
 * any reverse geocoding is attempted.
 */
GReverseGeocoder.prototype.setBaseCountry = function(country){
  this.country=country;
}

/*
 * This method issues a new reverse geocode query.
 * The parameter is a GLatLng point.
 * If successful the Placemark object is passed to the user-specified
 * listener.
 *
 * E.g. var listener = GEvent.addListener(reversegeocoder,"load",
 *        function(placemark){
 *          alert("The reverse geocoded address is " + placemark.address);
 *        }
 *      );
 */
GReverseGeocoder.prototype.reverseGeocode = function(point){
  this.lastpoint = point;
  this.gdirections.clear();
  this.gdirections.loadFromWaypoints([point.toUrlValue(6),point.toUrlValue(6)],{getSteps: true, locale: "BR", getPolyline:true});
}

/*
 * Returns the status of the reverse geocode request.
 * In GReverseGeocoder v1.0 the getStatus() method proxies the GDirections.getStatus() behavior.
 * The returned object has the following form: {   code: 200   request: "directions" }
 * The status code can take any of the values defined in GGeoStatusCode. (Since Google Map API 2.81)
 */
GReverseGeocoder.prototype.getStatus = function(){
  return this.gdirections.getStatus();
}

/*
 * Private implementation methods
 */

/*
 * This method is called when a GDirection error occurs,
 * or if the GReverseGeocoder does not find an address.
 */
GReverseGeocoder.prototype.handleError = function() {
  GEvent.trigger(this, "error");
}

/*
 * This method first gets the closest street using GDirections,
 * then tries to find addresses by combinating that street
 * with the supplied country using the GClientGeocoder.
 * This can result in multiple addresses and is filtered
 * by the GReverseGeocoder.getBestMatchingPlacemark() method.
 */
GReverseGeocoder.prototype.processDirection = function() {
  var source = this;
  // snap to road
//  this.lastpoint=this.gdirections.getPolyline().getVertex(0);

  var nrroutes = this.gdirections.getNumRoutes();
  if (nrroutes != 0) {
    var route = this.gdirections.getRoute(0);
    var nrsteps = route.getNumSteps();
    if (nrsteps != 0) {
      var step = route.getStep(0);
      var street = step.getDescriptionHtml();
      street = this.getStreet(street) + " " + this.country;
      this.geocoder.getLocations(street,
        function(response) {
          var placemark = source.getBestMatchingPlacemark(response);
          if (placemark != null) {
            if(source.experimental) {
            	source.ad = placemark.address;
            	source.step=10;
            	source.start=1;
            	source.houseNumberSearch();
            }
            else {
            	GEvent.trigger(source, "load", placemark);
            }
          }
          else{
            source.handleError();
          }
        }
      );
    }
  }
}

/*
 * Finds the closest address towards the original point
 * form the resultset obtained by the GClientGeocoder request.
 * In GReverseGeocoder v1.0 an address is considered only if
 * it is within 1000m. If none of the addresses is in this range
 * the query will fail.
 */
 // TODO: the minimum Accuracy should be an optional parameter in
 // the GReverseGeocoder.
GReverseGeocoder.prototype.getBestMatchingPlacemark = function(response){
  if (!response || response.Status.code != 200) return null;
  var j = -1;
  var mindist = 10000;
  for (var i = 0; i < response.Placemark.length; i++){
    var place = response.Placemark[i];
    var point = new GLatLng(place.Point.coordinates[1], place.Point.coordinates[0]);
    var temp = this.lastpoint.distanceFrom(point);
    if (temp < mindist) {
      j = i;
      mindist = temp;
    }
  }
  if(j < 0 ) return null;
  return response.Placemark[j];
}

 /*

 {	"id":"",
  	"address":"Binnenweg 41, 9050 Ledeberg, Gent, Belgium",
  	"AddressDetails":{
  		"Country":{
  			"CountryNameCode":"BE",
  			"AdministrativeArea":{
  				"AdministrativeAreaName":"Vlaams Gewest",
  				"SubAdministrativeArea":{
  					"SubAdministrativeAreaName":"Oost-Vlaanderen",
  					"Locality":{
  						"LocalityName":"Gent",
  						"DependentLocality":{
  							"DependentLocalityName":"Ledeberg",
  							"Thoroughfare":{
  								"ThoroughfareName":"Binnenweg 41"
  							},
  							"PostalCode":{
  								"PostalCodeNumber":"9050"
  							}
  						}
  					}
  				}
  			}
  		},
  		"Accuracy": 8
  	},
  	"Point":{
  		"coordinates":[3.739867,51.036155,0]
  	}
  }

 */

GReverseGeocoder.prototype.processDirectionRefine = function(){
  var nrgeocodes = this.gdirectionsrefine.getNumGeocodes();
  var j = -1;
  var mindist = 100;
  for (var i = 1; i < nrgeocodes; i++){
    var place = this.gdirectionsrefine.getGeocode(i);
    var point = new GLatLng(place.Point.coordinates[1], place.Point.coordinates[0]);
    if (place.AddressDetails.Accuracy == 8){
      var temp = this.lastpoint.distanceFrom(point);
      if (temp < mindist) {
        j = i;
        mindist = temp;
      }
    }
  }
  if(j < 0 ) {
    if ( this.start + (24 * this.step) < 2000){
    	this.start = this.start + (25 * this.step);
    	this.houseNumberSearch();
    }
    else {
    	// house number above 2000 give up.
    	this.handleError();
    }
  }
  else {
    if (this.step == 1){
    	GEvent.trigger(this, "load", this.gdirectionsrefine.getGeocode(j));
    }
    else{
    	var place = this.gdirectionsrefine.getGeocode(j);
    	var nr = place.address.split(",",1)[0].split(" ");
	nr = nr[nr.length-1];
	this.start = nr - 10;
	this.step = 1;
	this.houseNumberSearch();
    }
  }
}

GReverseGeocoder.prototype.houseNumberSearch = function(){
  this.gdirectionsrefine.clear();


  this.gdirectionsrefine.loadFromWaypoints([
  	("" + (this.start + (0 * this.step))  + " ") + this.ad,
  	("" + (this.start + (1 * this.step))  + " ") + this.ad,
  	("" + (this.start + (2 * this.step))  + " ") + this.ad,
  	("" + (this.start + (3 * this.step))  + " ") + this.ad,
  	("" + (this.start + (4 * this.step))  + " ") + this.ad,
  	("" + (this.start + (5 * this.step))  + " ") + this.ad,
  	("" + (this.start + (6 * this.step))  + " ") + this.ad,
  	("" + (this.start + (7 * this.step))  + " ") + this.ad,
  	("" + (this.start + (8 * this.step))  + " ") + this.ad,
  	("" + (this.start + (9 * this.step))  + " ") + this.ad,
  	("" + (this.start + (10 * this.step))  + " ") + this.ad,
  	("" + (this.start + (11 * this.step))  + " ") + this.ad,
  	("" + (this.start + (12 * this.step))  + " ") + this.ad,
  	("" + (this.start + (13 * this.step))  + " ") + this.ad,
  	("" + (this.start + (14 * this.step))  + " ") + this.ad,
  	("" + (this.start + (15 * this.step))  + " ") + this.ad,
  	("" + (this.start + (16 * this.step))  + " ") + this.ad,
  	("" + (this.start + (17 * this.step))  + " ") + this.ad,
  	("" + (this.start + (18 * this.step))  + " ") + this.ad,
  	("" + (this.start + (19 * this.step))  + " ") + this.ad,
  	("" + (this.start + (20 * this.step))  + " ") + this.ad,
  	("" + (this.start + (21 * this.step))  + " ") + this.ad,
  	("" + (this.start + (22 * this.step))  + " ") + this.ad,
  	("" + (this.start + (23 * this.step))  + " ") + this.ad,
  	("" + (this.start + (24 * this.step))  + " ") + this.ad
  ],{getSteps: true, locale: "GB"});
}

/*
 * Cuts out the street form the GDirections result.
 */
// The Google Map API team should consider to give back a
// well defined stucture on the GStep.getDescriptionHtml()
// method rather than some arbitrary html
// or better, just provid us with a reverse geocoder
GReverseGeocoder.prototype.getStreet = function(street){
  var str = street.substring(street.lastIndexOf("<b>")+3,street.lastIndexOf("</b>"));
  if (str.lastIndexOf("/<wbr/>") > 0) {
    str = str.substring(0, str.lastIndexOf("/<wbr/>"));
  }
  return str;
}

GReverseGeocoder.prototype.setExperimentalHouseNumber = function (setting){
  this.experimental = setting;
}

var urlGlobalPadrao = 'http://'+$(location).attr('host');

$(document).ready(function(){
  ///var so = '';
  if(navigator.userAgent.indexOf('Linux') != -1){
    ///var so = "Linux";
    urlGlobalPadrao = 'http://'+$(location).attr('host');
  }else{
    urlGlobalPadrao = 'http://'+$(location).attr('host')+'/ctrl/public';
  }

})

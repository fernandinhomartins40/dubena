var global_codGrupo;
var global_tipo;
var global_menu;

var mensagem="";

//CODIFICA PARA URL
function URLEncode(plaintext)
{
	// The Javascript escape and unescape functions do not correspond
	// with what browsers actually do...
	var SAFECHARS = "0123456789" +					// Numeric
					"ABCDEFGHIJKLMNOPQRSTUVWXYZ" +	// Alphabetic
					"abcdefghijklmnopqrstuvwxyz" +
					"-_.!~*'()";					// RFC2396 Mark characters
	var HEX = "0123456789ABCDEF";
	var encoded = "";
	for (var i = 0; i < plaintext.length; i++ ) {
		var ch = plaintext.charAt(i);
	    if (ch == " ") {
		    encoded += "+";				// x-www-urlencoded, rather than %20
		} else if (SAFECHARS.indexOf(ch) != -1) {
		    encoded += ch;
		} else {
		    var charCode = ch.charCodeAt(0);
			if (charCode > 255) {
			    alert( "Unicode Character '"
                        + ch
                        + "' cannot be encoded using standard URL encoding.\n" +
				          "(URL encoding only supports 8-bit characters.)\n" +
						  "A space (+) will be substituted." );
				encoded += "+";
			} else {
				encoded += "%";
				encoded += HEX.charAt((charCode >> 4) & 0xF);
				encoded += HEX.charAt(charCode & 0xF);
			}
		}
	} // for

	return encoded;
}

//DECODIFICA DE URL

function URLDecode(psEncodeString)
{
  var lsRegExp = /\+/g;
  return unescape(String(psEncodeString).replace(lsRegExp, " "));
}

function showDivCerca(empresa_id){
	$.ajax({
            url: root+'/api/getCercaRastreamento',
            type: 'GET',
            dataType: 'json',
            data: {
                    empresa_id: empresa_id,
            },
            error: function(res) {
                console.log(res);
            },
            success: function(res) {
                //console.log(res);
                //document.getElementById("showDivCerca").innerHTML = URLDecode(res);
                var cerca = $('#selCerca');
                cerca.empty();
                cerca.append("<option value='-1|-1'>Nenhuma</option>");
                var empresa_id = -1;
                $.each(res, function (index, element) {
                    cerca.append("<option value='" + element.empresa_id + '|' + element.id + "' id=id" + element.id + ">" + element.descricao + "</option>");
                    empresa_id = element.empresa_id;
                });
                cerca.append("<option value='" + empresa_id + "|0' id='id0'>Todas</option>");
                
            }
	});
	//if(tipo != 0 && tipo!=3){
	//	document.getElementById("id"+tipo).checked = "checked";
	//}
	return;
}

	function mudaSizeMap(){
		var fator = 1.2;
                try{
                    if(qnt_mapas!=undefined){
                            if (qnt_mapas >= 4) {
                                    fator = 2.2;
                            }
                    }
                }catch(err){

                }

		if(document.getElementById("map")!=null){
			var frame = document.getElementById("map");
			tam = document.body.parentNode.clientHeight/fator;
			frame.style.height = tam+"px";
		}else if(document.getElementById("mapHtml")!=null){
			var frame = document.getElementById("mapHtml");
			tam = document.body.parentNode.clientHeight/fator;
			frame.style.height = tam+"px";
		}
                try{
                    if (qnt_mapas != null){

                    }
                }catch(e){
                    qnt_mapas = null;
                }
		if (qnt_mapas != null) {
			for (i = 0; i <= qnt_mapas; i++) {
				if (document.getElementById("map_add" + i) != null) {
					var frame = document.getElementById("map_add" + i);
					tam = document.body.parentNode.clientHeight / fator;
					frame.style.height = tam + "px";
				}
			}
		}

	}

	try{
		//var bounds = new GLatLngBounds();
                var bounds = new LatLngBounds();
	}catch (e) {
		// TODO: handle exception
	}

        function selectedCerca(cerca_id){
            showCerca(cerca_id);
        }

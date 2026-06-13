var global_codGrupo;
var global_tipo;
var global_menu;

var mensagem="";
//function clickIE() {if (document.all) {(mensagem);return false;}}
//function clickNS(e) {if 
//(document.layers||(document.getElementById&&!document.all)) {
//if (e.which==2||e.which==3) {(mensagem);return false;}}}
//if (document.layers) 
//{document.captureEvents(Event.MOUSEDOWN);document.onmousedown=clickNS;}
//else{document.onmouseup=clickNS;document.oncontextmenu=clickIE;}
//document.oncontextmenu=new Function("return false")

function setActionPath(newPath){
	document.forms[0].action = newPath;
}
//CRIA O Objeto XMLHttpRequest
function criarXMLHTTP() {
	var arrSignatures = ["MSXML2.XMLHTTP.5.0", "MSXML2.XMLHTTP.4.0", "MSXML2.XMLHTTP.3.0", "MSXML2.XMLHTTP", "Microsoft.XMLHTTP"];
	var xmlhttp = false;
	for (var i=0; i < arrSignatures.length; i++) {
		try {
			var oRequest = new ActiveXObject(arrSignatures[i]);
			xmlhttp = oRequest;
			break;
		} catch (oError) {
		}
	}
	if(!xmlhttp && typeof XMLHttpRequest != 'undefined')
	{
		//PARA O FIREFOX
		xmlhttp = new XMLHttpRequest();		
	}

	return xmlhttp;
}

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

function URLDecodeOld(encoded)
{
   // Replace + with ' '
   // Replace %xx with equivalent character
   // Put [ERROR] in output if %xx is invalid.
   var HEXCHARS = "0123456789ABCDEFabcdef"; 
   var plaintext = "";
   var i = 0;
   while (i < encoded.length) {
       var ch = encoded.charAt(i);
	   if (ch == "+") {
	       plaintext += " ";
		   i++;
	   } else if (ch == "%") {
			if (i < (encoded.length-2) 
					&& HEXCHARS.indexOf(encoded.charAt(i+1)) != -1 
					&& HEXCHARS.indexOf(encoded.charAt(i+2)) != -1 ) {
				plaintext += unescape( encoded.substr(i,3) );
				i += 3;
			} else {
				//alert( 'Bad escape combination near ...' + encoded.substr(i) );
				plaintext += "%[ERROR]";
				i++;
			}
		} else {
		   plaintext += ch;
		   i++;
		}
	} // while
   return plaintext;
}

function carregaTitulo(titulo)
{		
	document.title = titulo;
	window.defaultStatus = titulo;
	window.status = titulo;
}

function Bloqueia_Caracteres(evnt)
{
 //Fun��o permite digita��o de n�meros
 	if (clientNavigator == "IE")
	{
		alert(evnt.keyCode); //46 � o .
		if (evnt.keyCode == 46){
			return true;
		}
 		else if (evnt.keyCode < 48 || evnt.keyCode > 57)
		{
			//alert ("N�o � numero");
 			return false;
 		}
 	}else
	{
		//alert(evnt.charCode);
		//alert(evnt.keyCode);
		if (evnt.keyCode == 0)
			if (evnt.charCode == 46){
				return true;
			}
	 		else if ((evnt.charCode < 48 || evnt.charCode > 57)){
	 			return false;
	 		}
 	}
	//alert ("� numero");
	return true;
}



function formataMonetario(valor) {
    var vr = retornaInteiro(String(valor));
    var result = '';
    var aux = '';
    var counter= 0;
    if(vr.length == 0) result = '';
    else if(vr.length == 1) result = '0,0' + vr;
    else if(vr.length == 2) result = '0,' + vr;
    else if(vr.length>1) {
        for(i=vr.length-1;i>=0;i--) {
            if(counter==2)
                aux += ',';
            else if((counter-2)%3==0 && counter>=5) 
                aux += '.';

            aux += vr.charAt(i);
            counter++;
        }

        for(i=aux.length-1;i>=0;i--) {
            result += aux.charAt(i);
        }
    }
    return result;
}

function formataValor(strCampo, TeclaPres){
    var vr = new String(strCampo.value);
    var tecla = TeclaPres.keyCode ? TeclaPres.keyCode : TeclaPres.which ? TeclaPres.which : TeclaPres.charCode;
    var aux = '';
    var counter=0;
    
    if((vr.length >= strCampo.maxLength && tecla != 8) || (vr.length==0 && tecla==48))
        return false;
    if((vr.length-2)%4==0 && strCampo.maxLength==vr.length+1 && tecla != 8)
        return false;    
	
    if(tecla == 8) {
    //se for backspace
        strCampo.value = formataMonetario(vr);
    } else if(tecla >= 48 && tecla <= 57) {
    //se for n�mero
        vr = retornaInteiro(vr);

        if(vr.length == 0) strCampo.value = '0,0';
        else if(vr.length == 1) strCampo.value = '0,' + vr;
        else if(vr.length>1) {
            strCampo.value = formataMonetario(vr);
        }
    } else {
        return false;
    }
}

function formatar(src, mask){
  var i = src.value.length;
  var saida = mask.substring(0,1);
  var texto = mask.substring(i)
if (texto.substring(0,1) != saida)
  {
    src.value += texto.substring(0,1);
  }
}


function carrega_imagem(post,local,div){
	var oHTTPRequest = criarXMLHTTP();
	var enviar = "";
	if (post.length>=1)
	{
		enviar = post[0]+"="+ URLDecode(document.getElementById(post[0]).value);
		for(i=1;i<post.length;i++)
		{
			enviar += "&"+post[i]+"="+URLDecode(document.getElementById(post[i]).value);
		}
	}
		if (local.length==0)
	{
		div.innerHTML = "";
	}
	else{		
		oHTTPRequest.open("get", local+"?"+enviar, true);
		oHTTPRequest.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
		oHTTPRequest.setRequestHeader("Cache-Control", "no-store, no-cache, must-revalidate");
		oHTTPRequest.setRequestHeader("Cache-Control", "post-check=0, pre-check=0");
		oHTTPRequest.setRequestHeader("Pragma", "no-cache");
		oHTTPRequest.onreadystatechange=function() {
			if (oHTTPRequest.readyState==4){
				div.innerHTML = oHTTPRequest.responseText;
			} else {
				//div.innerHTML = "carregando";		
				processando2(div);
			}
		}
		oHTTPRequest.send("");
	}
}

function carrega_div(post,local,div)
{
	var oHTTPRequest = criarXMLHTTP();
	var enviar = "";
	if (post.length>=1)
	{
		enviar = post[0]+"="+ URLDecode(document.getElementById(post[0]).value);
		for(i=1;i<post.length;i++)
		{
			enviar += "&"+post[i]+"="+URLDecode(document.getElementById(post[i]).value);
		}
	}
		if (local.length==0)
	{
		div.innerHTML = "";
	}
	else{
		oHTTPRequest.open("post", local, true);
		oHTTPRequest.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
		oHTTPRequest.setRequestHeader("Cache-Control", "no-store, no-cache, must-revalidate");
		oHTTPRequest.setRequestHeader("Cache-Control", "post-check=0, pre-check=0");
		oHTTPRequest.setRequestHeader("Pragma", "no-cache");
		oHTTPRequest.onreadystatechange=function() {
			if (oHTTPRequest.readyState==4){
				div.innerHTML = URLDecode(oHTTPRequest.responseText);
			} else {
				//div.innerHTML = "carregando";
			}
		}
		oHTTPRequest.send(enviar);
	}
}
function zera_calendar(num_cal)
{
	var i; 
	var div;
	for(i=1;i<=num_cal;i++){
		div = "data"+i;
		document.getElementById(div).value = "";
	}
}

function carrega_div_com_calendar(post,local,div,num_cal,time)
{
	var oHTTPRequest = criarXMLHTTP();
	var enviar = "";
	if (post.length>=1)
	{
		enviar = post[0]+"="+ URLDecode(document.getElementById(post[0]).value);
		for(i=1;i<post.length;i++)
		{
			enviar += "&"+post[i]+"="+URLDecode(document.getElementById(post[i]).value);
		}
	}
	if (local.length==0)
	{
		div.innerHTML = "";
	}
	else{
		oHTTPRequest.open("post", local, true);
		oHTTPRequest.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
		oHTTPRequest.onreadystatechange=function() {
			if (oHTTPRequest.readyState==4){			
				div.innerHTML = URLDecode(oHTTPRequest.responseText);
				for (var i=1;i<=num_cal;i++)
				if (time)
				{
				Calendar.setup({
							inputField     :    "data"+i,      // id of the input field
							ifFormat       :    "%d/%m/%Y %H:%M",      // format of the input field
							align          :    "br",           // alignment (defaults to "Bl")
							weekNumbers    :    false,
							showsTime	   : 	time, 
							step           : 	1,
							button         :   "imagem"+i,   // trigger for the calendar (button ID)
							singleClick    :    true            // single-click mode
						});
				}
				else{
					Calendar.setup({
							inputField     :    "data"+i,      // id of the input field
							ifFormat       :    "%d/%m/%Y",      // format of the input field
							align          :    "br",           // alignment (defaults to "Bl")
							weekNumbers    :    false,
							showsTime	   : 	time, 
							step           : 	1,
							button         :   "imagem"+i,   // trigger for the calendar (button ID)
							singleClick    :    true            // single-click mode
						});
				} 
			} else {
	//			div.innerHTML = "carregando";
			}
		}
		oHTTPRequest.send(enviar);
	}
}

function processandoCercas(div)
{
		document.getElementById(div).innerHTML = 
				//'<table width="100%" border="0" align="center" cellpadding="0" cellspacing="0">'+
				//'<tr>'+
				//'<td height="150" bgcolor="#ffffff" valign="center" alingn="center">'+
				'<table width="98%" border="0" align="center" cellpadding="7" cellspacing="2" bgcolor="#f7f7f7"	style="margin-top: 4px;border: solid 1px #ebebeb; border-top: solid 2px #dedede; ">'+
				'<tr>'+
				'<td  align="center">'+				
				"<img src='_images/loading.gif' valing='center' align='center' /> "+
				'Processando'
				'</td>'+
				'</tr>'+
				"</table>"+
				//"</BR>"+
				//'</td>'+
				//'</tr>'+
				//"</table>"+
				"</BR>";
}

function processandoCercasOld(div)
{
	var msg = '<table width="620" border="0" align="center" cellpadding="0" cellspacing="0">'+
				'<tr>'+
				'<td height="150" bgcolor="#ffffff" valign="center" align="center">'+
				'<table width="98%" border="0" align="center" cellpadding="7" '+
				'cellspacing="2" bgcolor="#f7f7f7"'+
				' style="margin-top: 4px;border: solid 1px #ebebeb; border-top: solid 2px #dedede;">'+
				'<tr>'+
				'<td  align="center">'+
				"<img src='_images/loading.gif' valing='center' align='center' /> "+
				'Carregando Cercas Eletronicas'
				'</td>'+
				'</tr>'+
				"</table>"+
				"</BR>"+
				'</td>'+
				'</tr>'+
				"</table>"+
				"</BR>";
		document.getElementById(div).innerHTML = msg;
				
}

function processando(div)
{
		document.getElementById(div).innerHTML = 
				'<table width="100%" border="0" align="center" cellpadding="0" cellspacing="0">'+
				'<tr>'+
				'<td height="150" bgcolor="#ffffff" valign="center" alingn="center">'+
				'<table width="98%" border="0" align="center" cellpadding="7" cellspacing="2" bgcolor="#f7f7f7"	style="margin-top: 4px;border: solid 1px #ebebeb; border-top: solid 2px #dedede; ">'+
				'<tr>'+
				'<td  align="center">'+				
				"<img src='_images/loading.gif' valing='center' align='center' /> "+
				'Processando'
				'</td>'+
				'</tr>'+
				"</table>"+
				"</BR>"+
				'</td>'+
				'</tr>'+
				"</table>"+
				"</BR>";
}

function processandoOld(div)
{
	var msg = '<table width="656" border="0" align="center" cellpadding="0" cellspacing="0">'+
				'<tr>'+
				'<td height="150" bgcolor="#ffffff" valign="center" align="center">'+
				'<table width="98%" border="0" align="center" cellpadding="7" '+
				'cellspacing="2" bgcolor="#f7f7f7"'+
				' style="margin-top: 4px;border: solid 1px #ebebeb; border-top: solid 2px #dedede;">'+
				'<tr>'+
				'<td  align="center">'+
				"<img src='_images/loading.gif' valing='center' align='center' /> "+
				'Processando'
				'</td>'+
				'</tr>'+
				"</table>"+
				"</BR>"+
				'</td>'+
				'</tr>'+
				"</table>"+
				"</BR>";
		document.getElementById(div).innerHTML = msg;
				
}

function processando2(div)
{
		div.innerHTML = 
				'<table width="100%" border="0" align="center" cellpadding="0" cellspacing="0">'+
				'<tr>'+
				'<td height="150" bgcolor="#ffffff" valign="center" alingn="center">'+
				'<table width="98%" border="0" align="center" cellpadding="7" cellspacing="2" bgcolor="#f7f7f7"	style="margin-top: 4px;border: solid 1px #ebebeb; border-top: solid 2px #dedede; ">'+
				'<tr>'+
				'<td  align="center">'+				
				"<img src='_images/loading.gif' valing='center' align='center' /> "+
				'Processando'
				'</td>'+
				'</tr>'+
				"</table>"+
				"</BR>"+
				'</td>'+
				'</tr>'+
				"</table>"+
				"</BR>";
}

function processando2Old(div)
{
	var msg = '<table width="656" border="0" align="center" cellpadding="0" cellspacing="0">'+
				'<tr>'+
				'<td height="150" bgcolor="#ffffff" valign="center" align="center">'+
				'<table width="98%" border="0" align="center" cellpadding="7" '+
				'cellspacing="2" bgcolor="#f7f7f7"'+
				' style="margin-top: 4px;border: solid 1px #ebebeb; border-top: solid 2px #dedede;">'+
				'<tr>'+
				'<td  align="center">'+
				"<img src='_images/loading.gif' valing='center' align='center' /> "+
				'Processando'
				'</td>'+
				'</tr>'+
				"</table>"+
				"</BR>"+
				'</td>'+
				'</tr>'+
				"</table>"+
				"</BR>";
		div.innerHTML = msg;
				
}

function carregaEntidades(entidade,local,titulo,atual,nome_atual,mostra_loading)
{
	if (mostra_loading)
		processando("conteudo");
	carregaTitulo(titulo);	
	carrega_div("",local,document.getElementById('conteudo'));
	document.getElementById('menu').innerHTML = "<input type='hidden' id='entidade' value='"+entidade+"'>"
	+"<input type='hidden' id='atual' value='"+atual+"'>"
	+"<input type='hidden' id='nome_atual' value='"+nome_atual+"'>";
	carrega_div("","_ajax/cabecalho.php",document.getElementById('cabecalho'));
	carrega_div(["entidade"],"_ajax/menu.php",document.getElementById('menu'));
	carrega_div(["entidade","nome_atual"],"_ajax/referencia.php",document.getElementById('referencia'));
	carrega_div(["entidade","nome_atual","atual"],"_ajax/abas.php",document.getElementById('div_aba'));
	carregaLogSistema([],entidade, local, nome_atual);
}

function configDivsDragAndDrop(){
	if($('.nodrop').attr("id") != undefined && $(".drag").attr("id") != undefined && $(".drop").attr("id") != undefined){
		$(function(){
			// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
			$(".drag")
				.bind( "dragstart", function( event ){
					// ref the "dragged" element, make a copy
					
					var $drag = $( this ), $proxy = $drag.clone();
					// modify the "dragged" source element
					$drag.addClass("outline");
					// insert and return the "proxy" element		
					return $proxy.appendTo( document.body ).addClass("ghost");
					})
				.bind( "drag", function( event ){
					// update the "proxy" element position
					$( event.dragProxy ).css({
						left: event.offsetX, 
						top: event.offsetY
						});
					})
				.bind( "dragend", function( event ){
					// remove the "proxy" element
					$( event.dragProxy ).fadeOut( "normal", function(){
						$( this ).remove();
						});
					// if there is no drop AND the target was previously dropped 
					if ( !event.dropTarget && $(this).parent().is(".drop") ){
						// output details of the action
						$.get('_ajax/manter_grupos_update.php', { idVeiculo: event.dragTarget.id, idGrupo: $('.nodrop').attr("id") } );
						// put it in it's original <div>
						$('.nodrop').append( this );
						}
					// restore to a normal state
					$( this ).removeClass("outline");	
					
					});
			$('.drop')
				.bind( "dropstart", function( event ){
					// don't drop in itself
					if ( this == event.dragTarget.parentNode ) return false;
					// activate the "drop" target element
					$( this ).addClass("active");
					})
				.bind( "drop", function( event ){
					// if there was a drop, move some data...
					$( this ).append( event.dragTarget );
					// output details of the action...
					$.get('_ajax/manter_grupos_update.php', { idVeiculo: event.dragTarget.id, idGrupo: this.id } );	
					})
				.bind( "dropend", function( event ){
					// deactivate the "drop" target element
					$( this ).removeClass("active");
					});
			// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
		});
	}else{
		setTimeout("configDivsDragAndDrop()",500);
	}
}

function recarregaEntidades(entidade,local,atual,nome_atual)
{
	processando("conteudo");
	carregaConteudo(local);
	carregaReferencia(entidade,nome_atual);
	carregaAbas(entidade,atual,nome_atual);
	carregaLogSistema([],entidade, local, nome_atual);
}

function recarregaEntidadesComMap(entidade,local,atual,nome_atual,cor,tipo,codCerca,acao)
{
	carregaConteudoComMap(local,cor,codCerca,tipo, acao);
	carregaReferencia(entidade,nome_atual);
	carregaAbas(entidade,atual,nome_atual);
	carregaLogSistema([],entidade, local, nome_atual);
}

function recarregaEntidadesReferenciaPost(entidade,local,post,atual,nome_atual)
{
	carregaConteudoPost(local,post);
	carregaReferencia(entidade,nome_atual);
	carregaAbas(entidade,atual,nome_atual);
	//processando("conteudo");
}

function recarregaEntidadesPaginaPost(local,post)
{
	carregaConteudoPost(local,post);
}

function chama()
{
	alert("TESTE CHAMA");
}

function wrapper(parameter) {
    if(typeof(parameter)=="function")
        parameter.call();
}
function myFunction() {
    alert("teste");
}
	//wrapper(myFunction);

//	var searchFn = "fnCallCal";

// SearchFn (nome)
// O eval () método retorna uma função objet, de modo que você pode usar o resultado como uma função

//eval (searchFn) (nome); 


	function recarregaEntidadesReferenciaPostNew(tipoEnvio, pagCarregar, arrayPost, divConteudo, divProcessando, nrEntidade, nomeExibirAba, nrAcaoEntidade, calendar, numCalendar, time)
	{
		if(calendar == null || calendar == ""){
			calendar = false;
		}
		if(numCalendar == null || numCalendar == ""){
			numCalendar = 0;
		}
		if(time == null || time == ""){
			time = false;
		}
		carregaConteudoNew(tipoEnvio, pagCarregar, arrayPost, divConteudo, divProcessando, nrEntidade, nomeExibirAba, nrAcaoEntidade, calendar, numCalendar, time);
		carregaReferencia(nrEntidade,nomeExibirAba);
		carregaAbas(nrEntidade,nrAcaoEntidade,nomeExibirAba);	
	}
	
	function recarregaEntidadesNew(tipoEnvio, pagCarregar, arrayPost, divConteudo, divProcessando, nrEntidade, nomeExibirAba, nrAcaoEntidade, calendar, numCalendar, time)
	{
		if(calendar == null || calendar == ""){
			calendar = false;
		}
		if(numCalendar == null || numCalendar == ""){
			numCalendar = 0;
		}
		if(time == null || time == ""){
			time = false;
		}
		carregaConteudoNew(tipoEnvio, pagCarregar, arrayPost, divConteudo, divProcessando, nrEntidade, nomeExibirAba, nrAcaoEntidade, calendar, numCalendar, time);
		carregaReferencia(nrEntidade,nomeExibirAba);
		carregaAbas(nrEntidade,nrAcaoEntidade,nomeExibirAba);	
		carregaLogSistema([],nrEntidade, pagCarregar, nomeExibirAba);
		
	}
	
	function carregaConteudoNew(tipoEnvio, pagCarregar, arrayPost, divConteudo, divProcessando, nrEntidade, nomeExibirAba, nrAcaoEntidade, calendar, numCalendar, time)
	{
	//	alert(executaJs);
	//	executaJs.call(this, nrEntidade, nomeExibirAba);
		
		var oHTTPRequest = createXMLHTTP();
		var enviar = "";
		oHTTPRequest.open(tipoEnvio, pagCarregar, true);
		oHTTPRequest.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
		oHTTPRequest.onreadystatechange=function() {
			if (oHTTPRequest.readyState==4){
				document.getElementById(divConteudo).innerHTML = URLDecode(oHTTPRequest.responseText);
				var time = false;
				if(calendar){
					for (var i=1;i<=numCalendar;i++)
						if (time)
						{
						Calendar.setup({
									inputField     :    "data"+i,      // id of the input field
									ifFormat       :    "%d/%m/%Y %H:%M",      // format of the input field
									align          :    "br",           // alignment (defaults to "Bl")
									weekNumbers    :    false,
									showsTime	   : 	time, 
									step           : 	1,
									button         :   "imagem"+i,   // trigger for the calendar (button ID)
									singleClick    :    true            // single-click mode
								});
						}
						else{
							Calendar.setup({
									inputField     :    "data"+i,      // id of the input field
									ifFormat       :    "%d/%m/%Y",      // format of the input field
									align          :    "br",           // alignment (defaults to "Bl")
									weekNumbers    :    false,
									showsTime	   : 	time, 
									step           : 	1,
									button         :   "imagem"+i,   // trigger for the calendar (button ID)
									singleClick    :    true            // single-click mode
								});
						}
				}
			} else {
				processando(divProcessando);
			}
		}
		var enviar = "";
		if (arrayPost.length >= 1)
		{
			enviar = arrayPost[0]+"="+ URLEncode(document.getElementById(arrayPost[0]).value);
			for(i = 1; i < arrayPost.length; i++)
			{				
				enviar += "&"+arrayPost[i]+"="+URLEncode(document.getElementById(arrayPost[i]).value);
			}
		}
		oHTTPRequest.send(enviar);
	}
function testeOnLoad(){
	alert("ONLOAD()");
}
function carregaConteudoPost(local,post)
{
	var oHTTPRequest = createXMLHTTP();
	var enviar = "";
//	oHTTPRequest.open("post", local, true);
//	oHTTPRequest.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
	oHTTPRequest.open("POST", local, true);
	oHTTPRequest.setRequestHeader("Content-Type", "application/x-www-form-urlencoded; charset=UTF-8");
	oHTTPRequest.setRequestHeader("Cache-Control", "no-store, no-cache, must-revalidate");
	oHTTPRequest.setRequestHeader("Cache-Control", "post-check=0, pre-check=0");
	oHTTPRequest.setRequestHeader("Pragma", "no-cache");
//	oHTTPRequest.send(url);
	oHTTPRequest.onreadystatechange=function() {
		if (oHTTPRequest.readyState==4){
			document.getElementById("conteudo").innerHTML = URLDecode(oHTTPRequest.responseText);
			configDivsDragAndDrop();
		} 
		else
		{
			processando("conteudo");
		}
	}
	if (post.length>=1)
	{
		enviar = post[0]+"="+ URLEncode(document.getElementById(post[0]).value);
		for(i=1;i<post.length;i++)
		{				
			enviar += "&"+post[i]+"="+URLEncode(document.getElementById(post[i]).value);
		}
	}
	oHTTPRequest.send(enviar);
	
}

function carregaConteudoPostCerca(local,post,acao)
{
	var oHTTPRequest = createXMLHTTP();
	var enviar = "";
	oHTTPRequest.open("post", local, true);
	oHTTPRequest.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
	oHTTPRequest.onreadystatechange=function() {
		if (oHTTPRequest.readyState==4){
			document.getElementById("conteudo").innerHTML = URLDecode(oHTTPRequest.responseText);
			var cdCerca = 0;
			//if(acao == "alterar"){
				//alert(acao);
				var cor = document.getElementById(post[0]).value;
				var tipo = document.getElementById(post[2]).value;
				if(document.getElementById(post[3]) != null && acao != "incluir"){
					cdCerca = document.getElementById(post[3]).value;
				}
			//}else{
			//	var cor = URLEncode(document.inclusao.cor.value);
			//	var tipo = URLEncode(document.inclusao.cd_cerca_eletronica_tipo.value);
			//}
			carregaMapHtml(cor, tipo, cdCerca, acao);
		} 
		else
		{
			processando("conteudo");
		}
	}
	if (post.length>=1)
	{
		enviar = post[0]+"="+ URLEncode(document.getElementById(post[0]).value);
		for(i=1;i<post.length;i++)
		{				
			enviar += "&"+post[i]+"="+URLEncode(document.getElementById(post[i]).value);
		}
	}
	oHTTPRequest.send(enviar);
}

function carregaDivPostCerca(local,post,div)
{
	var oHTTPRequest = createXMLHTTP();
	var enviar = "";
	oHTTPRequest.open("post", local, true);
	oHTTPRequest.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
	oHTTPRequest.onreadystatechange=function() {
		if (oHTTPRequest.readyState==4){
			document.getElementById(div).innerHTML = URLDecode(oHTTPRequest.responseText);
		}
	}
	if (post.length>=1)
	{
		enviar = post[0]+"="+ URLEncode(document.getElementById(post[0]).value);
		for(i=1;i<post.length;i++)
		{				
			enviar += "&"+post[i]+"="+URLEncode(document.getElementById(post[i]).value);
		}
	}
	oHTTPRequest.send(enviar);
}

function carregaMapHtml(cor, tipo, cdCerca, acao)
{
	var local = "";
	if(tipo == 1){
		local = "_ajax/02-Poligono.php";
	}else if(tipo == 2){
		local = "_ajax/05-Direcao.php";
	}
	var oHTTPRequest = createXMLHTTP(); 
	oHTTPRequest.open("post", local, true);
	oHTTPRequest.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
	oHTTPRequest.onreadystatechange=function() {
		if (oHTTPRequest.readyState==4){
			document.getElementById("mapHtml").innerHTML = URLDecode(oHTTPRequest.responseText);
			if(tipo == 1){
				carregaMapCercaEletronica(cor, acao);
				clearMap();
			}else if(tipo == 2){
				carregaMapaRota(cor, acao);
				clearMapTrajeto();
			}
			mudaSizeMap();
		}
	}
	oHTTPRequest.send("");
	//alert("verificaExisteCerca : " + cdCerca);
	if(cdCerca != 0){
		setTimeout("verificaExisteCerca("+cdCerca+", "+tipo+")",2000);
	}
}

function showDivCerca(tipo, menu){
	var oHTTPRequest = createXMLHTTP(); 
	oHTTPRequest.open("post", "_ajax/cerca_eletronica_show_monitoracao.php?tipo="+tipo+"&menu="+menu, true);
	oHTTPRequest.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
	oHTTPRequest.onreadystatechange=function() {
		if (oHTTPRequest.readyState==4){
			document.getElementById("showDivCerca").innerHTML = URLDecode(oHTTPRequest.responseText);
		}else{
			processandoCercas("showDivCerca");
		}
	}
	if(tipo != 0 && tipo!=3){
		document.getElementById("id"+tipo).checked = "checked";
	}
	oHTTPRequest.send("");
}

function recarregaEntidadesPost(local,post)
{
	carregaConteudoPost(local,post);
	processando("conteudo");
}

function recarregaEntidadesPostGrupo(local,post)
{
	carregaConteudoPost(local,post);
	processando("conteudo");
}

function recarregaEntidadesPostCerca(local,post,acao)
{
	carregaConteudoPostCerca(local,post,acao);
	processando("conteudo");	
}

function recarregaDivPostCerca(local,post,div)
{
	carregaDivPostCerca(local,post,div);
	processando(div);
}

function carregaAbas(entidade,atual,nome_atual)
{
		var oHTTPRequest = createXMLHTTP(); 
		oHTTPRequest.open("post", "_ajax/abas.php", true);
		oHTTPRequest.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
		oHTTPRequest.onreadystatechange=function() {
			if (oHTTPRequest.readyState==4){
				document.getElementById("div_aba").innerHTML = URLDecode(oHTTPRequest.responseText);		
			} 
//			else {
//				document.getElementById("div_aba").innerHTML = "carregando";
//			}
		}
		oHTTPRequest.send("entidade="+entidade+"&atual="+atual+"&nome_atual="+nome_atual);
}
function createXMLHTTP() {
	var arrSignatures = ["MSXML2.XMLHTTP.5.0", "MSXML2.XMLHTTP.4.0", "MSXML2.XMLHTTP.3.0", "MSXML2.XMLHTTP", "Microsoft.XMLHTTP"];
	var xmlhttp = false;
	for (var i=0; i < arrSignatures.length; i++) {
		try {
			var oRequest = new ActiveXObject(arrSignatures[i]);
			xmlhttp = oRequest;
			break;
		} catch (oError) {
		}
	}
	if(!xmlhttp && typeof XMLHttpRequest != 'undefined')
	{
		//PARA O FIREFOX
		xmlhttp = new XMLHttpRequest();
	}
	
	return xmlhttp;
}
function carregaReferencia(entidade,nome_atual)
{
		var oHTTPRequest = createXMLHTTP(); 
		oHTTPRequest.open("post", "_ajax/referencia.php", true);
		oHTTPRequest.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
		oHTTPRequest.onreadystatechange=function() {
			if (oHTTPRequest.readyState==4){
				document.getElementById("referencia").innerHTML = URLDecode(oHTTPRequest.responseText);
			} 
//			else {
//				document.getElementById("referencia").innerHTML = "carregando";
//			}
		}
		oHTTPRequest.send("entidade="+entidade+"&nome_atual="+nome_atual);
}



		
function carregaConteudo(local, qtdeCalen)
{
		var oHTTPRequest = createXMLHTTP(); 
//		oHTTPRequest.open("post", local, true);
//		oHTTPRequest.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
		oHTTPRequest.open("POST", local, true);
		oHTTPRequest.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
		oHTTPRequest.setRequestHeader("Cache-Control", "no-store, no-cache, must-revalidate");
//		oHTTPRequest.setRequestHeader("Cache-Control", "post-check=0, pre-check=0");
		oHTTPRequest.setRequestHeader("Pragma", "no-cache");
		//processando();
		oHTTPRequest.onreadystatechange=function() {
			if (oHTTPRequest.readyState==4){
				document.getElementById("conteudo").innerHTML = URLDecode(oHTTPRequest.responseText);
                                if(qtdeCalen > 0){
                                for (var i=1;i<=num_cal;i++)
                                    if (time)
                                    {
                                    Calendar.setup({
                                                            inputField     :    "data"+i,      // id of the input field
                                                            ifFormat       :    "%d/%m/%Y %H:%M",      // format of the input field
                                                            align          :    "br",           // alignment (defaults to "Bl")
                                                            weekNumbers    :    false,
                                                            showsTime	   : 	time,
                                                            step           : 	1,
                                                            button         :   "imagem"+i,   // trigger for the calendar (button ID)
                                                            singleClick    :    true            // single-click mode
                                                    });
                                    }
                                    else{
                                            Calendar.setup({
                                                            inputField     :    "data"+i,      // id of the input field
                                                            ifFormat       :    "%d/%m/%Y",      // format of the input field
                                                            align          :    "br",           // alignment (defaults to "Bl")
                                                            weekNumbers    :    false,
                                                            showsTime	   : 	time,
                                                            step           : 	1,
                                                            button         :   "imagem"+i,   // trigger for the calendar (button ID)
                                                            singleClick    :    true            // single-click mode
                                                    });
                                    }
                                }
			} 
//			else {
//				processando();
//			}
		}
		oHTTPRequest.send("");
}

function carregaConteudoComMap(local,cor,codCerca,tipo, acao)
{
	var oHTTPRequest = createXMLHTTP(); 
	oHTTPRequest.open("post", local, true);
	oHTTPRequest.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
	oHTTPRequest.onreadystatechange=function() {
		if (oHTTPRequest.readyState==4){
			document.getElementById("conteudo").innerHTML = URLDecode(oHTTPRequest.responseText);
			carregaMapHtml(cor, tipo, codCerca, acao);
			//setTimeout("verificaExisteCerca("+codCerca+","+tipo+")", 2000);
		}
	}
	oHTTPRequest.send("");
}

function carregaEntidadesFull(entidade,local,nome_atual,titulo,mostra_loading)
{
	if (mostra_loading)
		processando("conteudo");
	carregaTitulo(titulo);	
	carrega_div("",local,document.getElementById('conteudo'));
	carregaLogSistema([],entidade, local, nome_atual);		
}
 
function carregaEntidadescomcalendarepost(post,entidade,local,titulo,atual,nome_atual,mostra_loading,num_cal,time)
{
	if (mostra_loading)
		processando("conteudo");
	carregaTitulo(titulo);	
	carrega_div_com_calendar(post,local,document.getElementById('conteudo'),num_cal,time);
//	carrega_div("",local,document.getElementById('conteudo'));
	document.getElementById('menu').innerHTML = "<input type='hidden' id='entidade' value='"+entidade+"'>"
	+"<input type='hidden' id='atual' value='"+atual+"'>"
	+"<input type='hidden' id='nome_atual' value='"+nome_atual+"'>";
	carrega_div("","_ajax/cabecalho.php",document.getElementById('cabecalho'));
	carrega_div(["entidade"],"_ajax/menu.php",document.getElementById('menu'));
	carrega_div(["entidade","nome_atual"],"_ajax/referencia.php",document.getElementById('referencia'));
	carrega_div(["entidade","nome_atual"],"_ajax/abas.php",document.getElementById('div_aba'));	
	carregaLogSistema(post,entidade, local, nome_atual);
}

function carregaLogin(user,pass,pass2, cdManu)
{
	var oHTTPRequest = criarXMLHTTP(); 
	oHTTPRequest.open("post", "_ajax/login.php?cdManu="+cdManu, true);
	oHTTPRequest.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
	oHTTPRequest.onreadystatechange=function() {
		if (oHTTPRequest.readyState==4){
			document.getElementById("login").innerHTML = URLDecode(oHTTPRequest.responseText);
		} else {
//				document.getElementById("login").innerHTML = "carregando";
		}
	}
	oHTTPRequest.send("user=" + user + "&pass=" + pass + "&pass2=" + pass2);
}

function carregaLogSistema(post,entidade, local, nome_atual){
	var oHTTPRequest = criarXMLHTTP(); 
	oHTTPRequest.open("post", "_ajax/log_sistema.php", true);
	oHTTPRequest.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
	/* caso nao estiver logando descomente
	oHTTPRequest.onreadystatechange=function() {
		if (oHTTPRequest.readyState==4){
			document.getElementById("log").innerHTML = URLDecode(oHTTPRequest.responseText);
		}
	}
	*/
	var enviar = "";
	if (post.length>=1)
	{		
		for(i=0;i<post.length;i++)
		{
			enviar += "&"+post[i]+"="+URLDecode(document.getElementById(post[i]).value);
		}
	}	
	oHTTPRequest.send("entidade="+entidade+"&local="+local+"&nome_atual="+nome_atual+enviar);
	//alert("carregando log");
}

//function carregaLogSistema2(post,local){
	//var oHTTPRequest = criarXMLHTTP(); 
	//oHTTPRequest.open("post", "_ajax/log_sistema.php", true);
	//oHTTPRequest.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
	/* caso nao estiver logando descomente
	oHTTPRequest.onreadystatechange=function() {
		if (oHTTPRequest.readyState==4){
			document.getElementById("log").innerHTML = URLDecode(oHTTPRequest.responseText);
		}
	}
	*/
	//var enviar = "";
	//if (post.length>=1)
	//{
	//	for(i=1;i<post.length;i++)
	//	{
	//		enviar += "&"+post[i]+"="+URLDecode(document.getElementById(post[i]).value);
	//	}		
	//}	
	//oHTTPRequest.send("local="+local+enviar);
//	//alert("carregando log 2");
//}
	var indice = "";
	function exportarExcel(cd_veiculo,data1,data2){
		//alert(cd_veiculo); 
		//alert(data1);
		//alert(data2);
		//alert("exportarExcel");
		//if(escolhido == -1){
		//	alert("Selecione um veiculo para exportar os dados!");
		//}else{
			//var data1 = URLEncode(document.getElementById('data1').value);
			//var data2 = URLEncode(document.getElementById('data2').value);
			var campoExportarExcel = document.getElementById("exportarExcel");
			campoExportarExcel.innerHTML = "Gerando arquivo...";
			var intervalo = document.getElementById("intervalo").value;
//			document.getElementById("exportarExcel").innerHTML = "Gerando arquivo...";
			var oHTTPRequest = createXMLHTTP(); 
			oHTTPRequest.open("post", "criarProgresso.php?tipo=excel"+'&'+ Math.ceil ( Math.random() * 100000 ), true);
			oHTTPRequest.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
			oHTTPRequest.onreadystatechange=function() {
				if (oHTTPRequest.readyState==4){
					indice = URLDecode(oHTTPRequest.responseText);
					var oHTTPRequest2 = createXMLHTTP(); 
					oHTTPRequest2.open("post", "exportarExcel.php?cd_veiculo="+cd_veiculo+"&data1="+data1+"&data2="+data2+"&indice="+indice+"&intervalo="+intervalo , true);
					oHTTPRequest2.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
					oHTTPRequest2.onreadystatechange=function() {
						if (oHTTPRequest2.readyState==4){
							campoExportarExcel.innerHTML = "<table border=0 height=20 width=150><tr>100% <td id='concluido' class='progresso_verde' style='width:100%; border:0;'></td></tr></table>"
							location.href = URLDecode(oHTTPRequest2.responseText);
							//open ("exportarExcel.php?cd_veiculo="+escolhido+"&data1="+data1+"&data2="+data2, "Mapa", "status=no,width=600, height=600, left=300, top=100");
							//alert(URLDecode(oHTTPRequest.responseText));
						}
					}
					oHTTPRequest2.send("");
					campoExportarExcel.innerHTML = "<table border=0 height=20 width=150><tr>0% <td id='faltando' class='progresso_vermelho' style='width:100%; border:0;'></td></tr></table>"
					getBarraProgresso();
				}
			}
			oHTTPRequest.send("");
		//}
	}
	
	function exportarExcel2(cd_veiculo,data1,data2){
		//alert(cd_veiculo); 
		//alert(data1);
		//alert(data2);
		//alert("exportarExcel");
		//if(escolhido == -1){
		//	alert("Selecione um veiculo para exportar os dados!");
		//}else{
			//var data1 = URLEncode(document.getElementById('data1').value);
			//var data2 = URLEncode(document.getElementById('data2').value);
			var campoExportarExcel = document.getElementById("exportarExcel");
			campoExportarExcel.innerHTML = "Gerando arquivo...";
			var intervalo = document.getElementById("intervalo").value;
//			document.getElementById("exportarExcel").innerHTML = "Gerando arquivo...";
			var oHTTPRequest = createXMLHTTP(); 
			oHTTPRequest.open("post", "criarProgresso.php?tipo=excel"+'&'+ Math.ceil ( Math.random() * 100000 ), true);
			oHTTPRequest.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
			oHTTPRequest.onreadystatechange=function() {
				if (oHTTPRequest.readyState==4){
					indice = URLDecode(oHTTPRequest.responseText);
					var oHTTPRequest2 = createXMLHTTP(); 
					oHTTPRequest2.open("post", "exportarExcel2.php?cd_veiculo="+cd_veiculo+"&data1="+data1+"&data2="+data2+"&indice="+indice+"&intervalo="+intervalo , true);
					oHTTPRequest2.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
					oHTTPRequest2.onreadystatechange=function() {
						if (oHTTPRequest2.readyState==4){
							campoExportarExcel.innerHTML = "<table border=0 height=20 width=150><tr>100% <td id='concluido' class='progresso_verde' style='width:100%; border:0;'></td></tr></table>"
							location.href = URLDecode(oHTTPRequest2.responseText);
							//open ("exportarExcel.php?cd_veiculo="+escolhido+"&data1="+data1+"&data2="+data2, "Mapa", "status=no,width=600, height=600, left=300, top=100");
							//alert(URLDecode(oHTTPRequest.responseText));
						}
					}
					oHTTPRequest2.send("");
					campoExportarExcel.innerHTML = "<table border=0 height=20 width=150><tr>0% <td id='faltando' class='progresso_vermelho' style='width:100%; border:0;'></td></tr></table>"
					getBarraProgresso();
				}
			}
			oHTTPRequest.send("");
		//}
	}
	
	function getBarraProgresso()
	{
		var oHTTPRequest3 = createXMLHTTP();
		var campoExportarExcel = document.getElementById("exportarExcel");
		var campoConcluido = document.getElementById("concluido");
		var campoFaltando = document.getElementById("faltando");
		var faltando = 0;
		oHTTPRequest3.open("post", "verificarProgresso.php?indice="+indice+'&'+ Math.ceil ( Math.random() * 100000 ), true);
		oHTTPRequest3.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
		oHTTPRequest3.onreadystatechange=function() {
			if (oHTTPRequest3.readyState==4){
				concluido = URLDecode(oHTTPRequest3.responseText);
				faltando = 100 - concluido;
				campoExportarExcel.innerHTML = "<table border=0 height=20 width=150><tr>"+concluido+"% <td id='concluido' class='progresso_verde' style='width:"+concluido+"%; border:0;'></td><td id='faltando' class='progresso_vermelho' style='width:"+faltando+"%; border:0;'></td></tr></table>";
//				campoExportarExcel.innerHTML = "Progresso... " + progresso;
				if(concluido != 100)
				{
					setTimeout("getBarraProgresso()", 3000);
				} else {
					campoExportarExcel.innerHTML = "<table border=0 height=20 width=150><tr>100% <td id='concluido' class='progresso_verde' style='width:100%; border:0;'></td></tr></table>";
				}
			}
		}
		oHTTPRequest3.send("");
	}
	
	function exportarTxt(cd_veiculo,data1,data2){
		//alert("exportarTxt");
		//if(escolhido == -1){
		//	alert("Selecione um veiculo para exportar os dados!");
		//}else{
			//var data1 = URLEncode(document.getElementById('data1').value);
			//var data2 = URLEncode(document.getElementById('data2').value);
			document.getElementById("exportarTxt").innerHTML = "Gerando arquivo...";
			var oHTTPRequest = createXMLHTTP(); 
			oHTTPRequest.open("post", "exportarTxt.php?cd_veiculo="+cd_veiculo+"&data1="+data1+"&data2="+data2 , true);
			oHTTPRequest.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
			oHTTPRequest.onreadystatechange=function() {
				if (oHTTPRequest.readyState==4){
					document.getElementById("exportarTxt").innerHTML = "Abrindo...";
					window.open(URLDecode(oHTTPRequest.responseText));
					document.getElementById("exportarTxt").innerHTML = "";
					//open ("exportarTxt.php?cd_veiculo="+escolhido+"&data1="+data1+"&data2="+data2, "Mapa", "status=no,width=600, height=600, left=300, top=100");
					//alert(URLDecode(oHTTPRequest.responseText));
				}
			}
			oHTTPRequest.send("");
		//}
	}
	
	function openPossiveisExportacoes(cd_veiculo,data1,data2){
		//alert("openPossiveisExportacoes");
		exportarExcel(cd_veiculo,data1,data2);
		exportarTxt(cd_veiculo,data1,data2);
	}
	
	function openExportacoes(){
		if(escolhido == -1){
			alert("Selecione um veiculo para exportar os dados!");
		}else{
			var data1 = URLEncode(document.getElementById('data1').value);
			var data2 = URLEncode(document.getElementById('data2').value);
			window.open ("exportacoes.php?cd_veiculo="+escolhido+"&data1="+data1+"&data2="+data2, "Mapa", "status=yes, width=500, height=250, left=300, top=100");
		}
	}
	
	function textCounter(field, countfield, maxlimit) {
		if (field.value.length > maxlimit)
		{
			field.value = field.value.substring(0, maxlimit);
		}else{
			countfield.value = maxlimit - field.value.length;
		}
	}
	
	function validaData(data1, data2){
		var dataAtual = new Date();
		var mes = [];
		mes[0] = "01";
		mes[1] = "02";
		mes[2] = "03";
		mes[3] = "04";
		mes[4] = "05";
		mes[5] = "06";
		mes[6] = "07";
		mes[7] = "08";
		mes[8] = "09";
		mes[9] = "10";
		mes[10] = "11";
		mes[11] = "12";
		if(data1 == ""){
			dataA = dataAtual.getDate() + '/' + mes[dataAtual.getMonth()] + '/' + dataAtual.getFullYear();
			data1 = dataA;
		}
		if(data2 == ""){
			dataA = dataAtual.getDate() + '/' + mes[dataAtual.getMonth()] + '/' + dataAtual.getFullYear();
			data2 = dataA;
		}
		//alert(data1);
		//alert(data2);
		var date1 = data1.split("/");
		var date2 = data2.split("/");
		var sDate = new Date(date1[2], date1[1], date1[0]);
		var eDate = new Date(date2[2], date2[1], date2[0]);
		var daysApart = Math.abs(Math.round((eDate.getTime()-sDate.getTime())/86400000));
		if(daysApart <= 7 ){
			return true;
		}else{
			return false;
		}
	}
	
	function toggle(v) { $S(v).display=($S(v).display=='none'?'block':'none'); }
	
	function recolheCM(cabecalho,menu)
	{
		$(document).ready(function(){
			if(cabecalho == true){
				$("#cabecalho").toggle(800);
		    }
		    if(menu == true){	
				$("#menu").toggle(800);
		    }
		});
		
	}
	
	function recolheCabecalhoMenu(cabecalho,menu)
	{
		if(cabecalho == true){
	      if(document.getElementById('cabecalho').style.display == "none")
	      {
			document.getElementById('cabecalho').style.display = "";
	      }
	      else
	      {
			document.getElementById('cabecalho').style.display = "none";
	      }
	    }
	    if(menu == true){
	      if(document.getElementById('menu').style.display == "none")
	      {
	      	document.getElementById('menu').style.display = "";
	      }
	      else
	      {
	      	document.getElementById('menu').style.display = "none";
	      }
	    }
	}
	
	function mudaSizeMap(){
		var fator = 1.2;
		if(qnt_mapas!=undefined){
			if (qnt_mapas >= 4) {
				fator = 2.2;
			}
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
	
	function mudaSizeMap2(){
		var fator = 1.2;
				
		if(document.getElementById("map")!=null){
			var frame = document.getElementById("map");
			tam = document.body.parentNode.clientHeight/fator;
			frame.style.height = tam+"px"; 	
		}else if(document.getElementById("mapHtml")!=null){
			var frame = document.getElementById("mapHtml");
			tam = document.body.parentNode.clientHeight/fator;
			frame.style.height = tam+"px"; 	
		}
			
	}
	
	
	function quickSearch(){
		alert("quickSearch INIT");
		$(document).ready(function () {
			$('table#veiculos tbody tr').quicksearch({
				attached: "table#veiculos",
				position: "before",
//				stripeRowClass: ['r1', 'r2', 'r3'],
				labelText: null,
				inputText: 'Busca'
			});
		});
		carregaWindowQuickSearch();
		
		// second simple accordion with special markup
//		jQuery('#navigation').accordion({
//			active: false,
//			header: '.head',
//			navigation: true,
//			event: 'mouseover',
//			fillSpace: true,
//			animated: 'easeslide'
//		});
		
		jQuery('#navigation').accordion();
		
//		jQuery('#navigation').accordion({ 
//		    event: 'mouseover', 
//		    active: '.selected', 
//		    selectedClass: 'active', 
//		    animated: "bounceslide", 
//		    header: "dt" 
//		}).bind("change.ui-accordion", function(event, ui) { 
//		    jQuery('<div>' + ui.oldHeader.text() + ' hidden, ' + ui.newHeader.text() + ' shown</div>').appendTo('#log'); 
//		});
		
	}
	
	function carregaWindowQuickSearch(){
		alert("WindowQuickSearch INIT");
		$(document).ready(function () {
			$('#windowOpen').bind(
					'click',
					function() {
						if($('#window').css('display') == 'none') { 
							$('#window').show();
						}
						this.blur();
						return false;
					}
				);
			$('#windowClose').bind(
				'click',
				function()
				{
					$('#window').TransferTo(
						{
							to:'windowOpen',
							className:'transferer2', 
							duration: 400
						}
					).hide();
				}
			);
			$('#windowMin').bind(
				'click',
				function()
				{
					$('#windowContent').SlideToggleUp(300);
					$('#windowBottom, #windowBottomContent').animate({height: 10}, 300);
					$('#window').animate({height:40},300).get(0).isMinimized = true;
					$(this).hide();
					$('#windowResize').hide();
					$('#windowMax').show();
				}
			);
			$('#windowMax').bind(
				'click',
				function()
				{
					var windowSize = $.iUtil.getSize(document.getElementById('windowContent'));
					$('#windowContent').SlideToggleUp(300);
					$('#windowBottom, #windowBottomContent').animate({height: windowSize.hb + 13}, 300);
					$('#window').animate({height:windowSize.hb+43}, 300).get(0).isMinimized = false;
					$(this).hide();
					$('#windowMin, #windowResize').show();
				}
			);
			$('#window').Resizable(
				{
					minWidth: 200,
					minHeight: 100,
					maxWidth: 300,
					maxHeight: 400,
					dragHandle: '#windowTop',
					handlers: {
						se: '#windowResize'
					},
					onResize : function(size, position) {
						$('#windowBottom, #windowBottomContent').css('height', size.height - 33 + 'px');
						var windowContentEl = $('#windowContent').css('width', size.width - 25 + 'px');
						if (!document.getElementById('window').isMinimized) {
							windowContentEl.css('height', size.height - 48 + 'px');
						}
					}
				}
			);
		});
	}
	
	
	function openNovidades(){
		window.open("img.html", "Novidades", "status=yes, width=650, height=500, left=300, top=100, scrollbars=yes");
	}
	
	function openLog(data,veiculo,log){
		window.open("_ajax/relatorio_desconectados_log.php?data="+data+"&veiculo="+veiculo+"&log="+log, "Log", "status=yes, width=650, height=500, left=300, top=100, scrollbars=yes,resizable=1");
	}
	
	function showLocaisGrupoMap(codGrupo, tipo, menu)
	{
		tipo=1;
		global_codGrupo = codGrupo;
		global_menu =  menu;
		global_tipo = tipo;
		//alert("opa");
		if (GBrowserIsCompatible())
		{
			var request = GXmlHttp.create();           
			var novo;
			request.open('GET', '_ajax/busca_locais_grupo.php?codGrupo='+codGrupo+'&tipo='+tipo+'&'+ Math.ceil ( Math.random() * 100000 ), true);
			//alert('_ajax/busca_locais_grupo.php?codGrupo='+codGrupo+'&tipo='+tipo);
			request.setRequestHeader("Cache-Control", "no-store, no-cache, must-revalidate");               
			request.setRequestHeader("Cache-Control", "post-check=0, pre-check=0");
			request.setRequestHeader("Pragma", "no-cache");
			request.onreadystatechange = function() {
				if (request.readyState == 4) {
					var lat;
					var lon;
					var raio;
					var cd;
//					var cor;
					var lastCd;
					var mesmo;
					var xmlDoc = request.responseXML;
					if (navigator.appName.indexOf('Microsoft') != -1){//internet explorer
						var markersArray = xmlDoc.getElementsByTagName("ponto");
					}else{
						var markersArray = xmlDoc.documentElement.getElementsByTagName("ponto");
					}
					var ponto;
					var pontos;
					var lastCor;
					var wpoints;
					var countPolygono = 0;
					var countPolyline = 0;
					for (var i = 0; i < markersArray.length; i++) {
						//cd = markersArray[i].getAttribute("cd");
						raio = markersArray[i].getAttribute("raio");
						lat = markersArray[i].getAttribute("nm_latitude");
						lon = markersArray[i].getAttribute("nm_longitude");
						
						if(lat != null && raio != null){
							 drawCircle(lat, lon, raio/1000, "25", "", "2", "0.5", "", "0.2");
						}
					}
				}
			}
			request.send(null);
		}
	}
	
	var bounds = new GLatLngBounds();
        var bounds = new LatLngBounds();
		
	function drawCircle(lat, lon, radius, nodes, liColor, liWidth, liOpa, fillColor, fillOpa)
	{
		if (qnt_mapas != null && qnt_mapas>0) {
			
				var centerCircle = new GLatLng(lat, lon);
				
				var latConv = centerCircle.distanceFrom(new GLatLng(centerCircle.lat() + 0.1, centerCircle.lng())) / 100;
				var lngConv = centerCircle.distanceFrom(new GLatLng(centerCircle.lat(), centerCircle.lng() + 0.1)) / 100;
				
				var points = [];
				var step = parseInt(360 / nodes) || 10;
				for (var i = 0; i <= 360; i += step) {
					var pint = new GLatLng(centerCircle.lat() + (radius / latConv * Math.cos(i * Math.PI / 180)), centerCircle.lng() +
					(radius / lngConv * Math.sin(i * Math.PI / 180)));
					points.push(pint);
					bounds.extend(pint); 
				}
				points.push(points[0]); 
				fillColor = fillColor || liColor || "#0055ff";
				liWidth = liWidth || 2;
								
				for (j = 0; j < qnt_mapas; j++) {
					if (mapas[j] == null || mapas[j] == undefined) {
						//alert("sou null");
					}
					else {
						mapas[j].addOverlay(new GPolygon(points, liColor, liWidth, liOpa, fillColor, fillOpa));
					}
				}
	}
	else 
		{
			setTimeout("drawCircle(\"" + lat + "\",\"" + lon + "\",\"" + radius + "\"," + nodes + ",\"" + liColor + "\",\"" + liWidth + "\",\"" + liOpa + "\",\"" + fillColor + "\"," + fillOpa + ")", 1000);
		}
	}
	
	function combo(divId){
		if(document.getElementById(divId)!=null || document.getElementById(divId)!=undefined){
			MSDropDown.init();
		}else{
			var metodo = "combo('"+divId+"')";
			setTimeout(metodo,100);
		}
	}
var clientNavigator;
if (navigator.appName.indexOf('Microsoft') != -1){
	clientNavigator = "IE";
}else{
    clientNavigator = "Other";
}

function controlaCamada(nomeDiv,nomeDiv2)
{
    if( document.getElementById(nomeDiv).style.visibility == "hidden" ) {
		document.getElementById(nomeDiv2).style.visibility = "hidden";
		document.getElementById(nomeDiv).style.visibility = "visible";
    } else {
        document.getElementById(nomeDiv).style.visibility = "hidden";
		document.getElementById(nomeDiv2).style.visibility = "visible";
    }
//	document.getElementById(nomeDiv).style.display = 'none';
//	document.getElementById(nomeDiv).style.display = 'block';
//
//	document.getElementById(nomeDiv2).style.display = 'none';
//	document.getElementById(nomeDiv2).style.display = 'block';
}

function separador_data(data)
{
    var ano = data.substr(0,4);//ano
    var mes = data.substr(5,2);//mes
    var dia = data.substr(8,2);//dia
    var horas = data.substr(11,2);//horas
    var minutos = data.substr(14,2);//minutos
    var segundos = data.substr(17,2);//segundos

    var dataformatada = dia+'/'+mes+'/'+ano+' '+horas+':'+minutos+':'+segundos;

    return dataformatada;
}

function retornaInteiro(valor) {
    // retira caracteres invalidos da string
    var result = "";
    var aux;
    var validos = "0123456789";
    for (var i=0; i < valor.length; i++) {
        aux = validos.indexOf(valor.substring(i, i+1));
        if (aux>=0) {
        result += aux;
        }
    }
    var length = result.length;
    //retira os zeros obsoletos
    for(i=0;i<length;i++) {
        if(result.charAt(0)=="0") {
            result = result.substr(1);
        } else {
            break;
        }
    }
    return result;
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

function Bloqueia_Caracteres(evnt)
{
 //Fun��o permite digita��o de n�meros
 	if (clientNavigator == "IE")
	{
	//	alert(evnt.keyCode);
 		if (evnt.keyCode < 48 || evnt.keyCode > 57)
		{
			//alert ("N�o � numero");
 			return false
 		}
 	}else
	{
		//alert(evnt.charCode);
		//alert(evnt.keyCode);
		if (evnt.keyCode == 0)
	 		if ((evnt.charCode < 48 || evnt.charCode > 57)){
	 			return false
	 		}
 	}
	//alert ("� numero");
	return true;
}

function Restante_texto(input,tamanho_max)
{
	var rest = tamanho_max - input.value.length - 1;
	if (rest < 0)
	{
		return false;
	}
	else
	{
		document.getElementById("rest").value = rest;
		return true;
	}
}

function enviarFormulario(campos, action, f, entidade, local)
{
	checked = new Array();
	f.action = action;
	//CRIA ARRAY PARA VALIDAR CAMPOS RADIO
	for(i=0;i<campos.length;i++)
	{
		try
		{
			el = document.getElementById(campos[i][0]);
			if(el.type=="radio")
			{
				e = f.elements;
				checked[el.name] = false;
				for(j=0;j<e.length;j++)
				{
					if(e[j].name==el.name && e[j].checked==true)
					{
						checked[el.name] = true;
					}
				}
			}
		} catch(e) { alert(campos[i][0]+"\n\n"+e); }
	}

	for(i=0;i<campos.length;i++)
	{
		el = document.getElementById(campos[i][0]);
		if(el.type=="radio" && checked[el.id]==false)
		{
			alert("Favor selecionar uma op��o para o campo "+campos[i][1]);
			el.focus();
			return false;
		}
		if(el.value=="")
		{
			alert("Favor preencher o campo "+campos[i][1]);
			el.focus();
			return false;
		}

		if(campos[i][2]!="" && el.value.length<campos[i][2])
		{
			alert("O campo "+campos[i][1]+" deve conter mais de "+campos[i][2]+" caracteres");
			el.focus();
			return false;
		}
	}
	//carregaLogSistema([],entidade, local+"&nome="+f.nome.value, entidade);
	f.submit();
}

function rad(x)
{
	return x*Math.PI/180;
}

function distHaversine(p1, p2)
{
//	var R = 6371000; // earth's mean radius in metres //'
//	var dLat  = rad(p2.y - p1.y);
//	var dLong = rad(p2.x - p1.x);
//	var a = Math.sin(dLat/2) * Math.sin(dLat/2) +
//	       Math.cos(rad(p1.y)) * Math.cos(rad(p2.y)) * Math.sin(dLong/2) * Math.sin(dLong/2);
//	var c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a));
//	var d = R * c;
//	return d;
        var R = 6371000; // earth's mean radius in metres //'
	var dLat  = rad(p2.lat() - p1.lat());
	var dLong = rad(p2.lng() - p1.lng());
	var a = Math.sin(dLat/2) * Math.sin(dLat/2) +
	       Math.cos(rad(p1.lat())) * Math.cos(rad(p2.lat())) * Math.sin(dLong/2) * Math.sin(dLong/2);
	var c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a));
	var d = R * c;
	return d;
}

function atualizar()
{
	document.forms[0].submit();
}

function apertou_enter(evnt)
{
	if (evnt.keyCode == 13)
	{
		atualizar();
	}
}

function scrollPage()
{
	scrTime += scrInt;
	if (scrTime < scrDur) {
		window.scrollTo( 0, easeInOut(scrTime,scrSt,scrDist,scrDur) );
	}else{
		window.scrollTo( 0, scrSt+scrDist );
		clearInterval(scrollInt);
	}
}

function scrollToAnchor()
{
	scrDist = 10 - scrSt;
	scrDur = 500;
	scrTime = 0;
	scrInt = 10;

	// set interval
	clearInterval(scrollInt);
	scrollInt = setInterval( scrollPage, scrInt );
}

/* EASING FUNCTIONS	*/

function easeInOut(t,b,c,d)
	{
		return c/2 * (1 - Math.cos(Math.PI*t/d)) + b;
	}

function envForm(f){
	f.submit();
}

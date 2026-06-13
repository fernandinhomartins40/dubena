function formatNumber(number, places)
{
	  number = parseFloat(number);
    number = number.toFixed(places) + '';
    x = number.split('.');
    x1 = x[0];
    x2 = x.length > 1 ? ',' + x[1] : '';
    var rgx = /(\d+)(\d{3})/;
    while (rgx.test(x1)) {
        x1 = x1.replace(rgx, '$1' + '.' + '$2');
    }
    return x1 + x2;
}

function formattedToNumber(str){
	 x = str.split(',');
	 x1 = x[0].replace(new RegExp(/\./g), '');
	 x2 = x.length > 1 ? '.' + x[1] : '';
	 ret = parseFloat(x1 + x2);
	 ret = isNaN(ret) ? 0 : ret;
	 ret = Math.round(ret*100)/100;
	 return ret;
}
function formatar(mascara, documento){
	var i = documento.value.length;
	var saida = mascara.substring(0,1);
	var texto = mascara.substring(i)
	if (texto.substring(0,1) != saida){
		documento.value += texto.substring(0,1);
	} else {
	}
}

function setTwoNumberDecimal(campo) {
    campo.value = parseFloat(campo.value).toFixed(2);
}
function validarCNPJ(cnpj) {

	cnpj = cnpj.replace(/[^\d]+/g,'');

	if(cnpj == '') return false;

	if (cnpj.length != 14)
	return false;

	// Elimina CNPJs invalidos conhecidos
	if (cnpj == "00000000000000" ||
	cnpj == "11111111111111" ||
	cnpj == "22222222222222" ||
	cnpj == "33333333333333" ||
	cnpj == "44444444444444" ||
	cnpj == "55555555555555" ||
	cnpj == "66666666666666" ||
	cnpj == "77777777777777" ||
	cnpj == "88888888888888" ||
	cnpj == "99999999999999")
	return false;

	// Valida DVs
	tamanho = cnpj.length - 2
	numeros = cnpj.substring(0,tamanho);
	digitos = cnpj.substring(tamanho);
	soma = 0;
	pos = tamanho - 7;
	for (i = tamanho; i >= 1; i--) {
		soma += numeros.charAt(tamanho - i) * pos--;
		if (pos < 2)
		pos = 9;
	}
	resultado = soma % 11 < 2 ? 0 : 11 - soma % 11;
	if (resultado != digitos.charAt(0))
	return false;

	tamanho = tamanho + 1;
	numeros = cnpj.substring(0,tamanho);
	soma = 0;
	pos = tamanho - 7;
	for (i = tamanho; i >= 1; i--) {
		soma += numeros.charAt(tamanho - i) * pos--;
		if (pos < 2)
		pos = 9;
	}
	resultado = soma % 11 < 2 ? 0 : 11 - soma % 11;
	if (resultado != digitos.charAt(1))
	return false;

	return true;

}

function isInt(n){
    return parseInt(n) % 1 === 0;
}

function select2data(data, text){
	for(i = 0; i < data.length; i++){
			data[i].text = data[i][text];
			delete data[i].descricao;
	}
	//console.log(data);
	return data;
}
function currentDate(){
	var currentdate = new Date();
	var fd =  ((currentdate.getDate()).toString().length==1 ? '0' : '')
						+ currentdate.getDate() + "/"
						+ ((currentdate.getMonth()+1).toString().length==1 ? '0' : '')
						+ (currentdate.getMonth()+1)  + "/"
						+ currentdate.getFullYear();
	return fd;
}
function currentDateTime(){
	var currentdate = new Date();
	var fd =  ((currentdate.getDate()).toString().length==1 ? '0' : '')
						+ currentdate.getDate() + "/"
						+ ((currentdate.getMonth()+1).toString().length==1 ? '0' : '')
						+ (currentdate.getMonth()+1)  + "/"
						+ currentdate.getFullYear() + " "
						+ ((currentdate.getHours()).toString().length==1 ? '0' : '')
						+ currentdate.getHours() + ":"
						+ ((currentdate.getMinutes()).toString().length==1 ? '0' : '')
						+ currentdate.getMinutes();
	return fd;
}
function currentDateTimeComplete(){
	var currentdate = new Date();
	var fd =  ((currentdate.getDate()).toString().length==1 ? '0' : '')
						+ currentdate.getDate() + "/"
						+ ((currentdate.getMonth()+1).toString().length==1 ? '0' : '')
						+ (currentdate.getMonth()+1)  + "/"
						+ currentdate.getFullYear() + " "
						+ ((currentdate.getHours()).toString().length==1 ? '0' : '')
						+ currentdate.getHours() + ":"
						+ ((currentdate.getMinutes()).toString().length==1 ? '0' : '')
						+ currentdate.getMinutes() + ":"
						+ ((currentdate.getSeconds()).toString().length==1 ? '0' : '')
						+ currentdate.getSeconds();
	return fd;
}
function invertStrDate(data){
	dt = '';
	if(data.indexOf('-')!=-1){
		dt = data.split('-');
		return dt[2]+'/'+dt[1]+'/'+dt[0];
	} else if(data.indexOf('/')!=-1){
		dt = data.split('/');
		return dt[2]+'/'+dt[1]+'/'+dt[0];
	}
	return 'invalid date';
}


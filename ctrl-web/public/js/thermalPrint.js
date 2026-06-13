
var command = {
	init: '\x1B' + '\x40',
	charsetLatin: '\x1B' + '\x74' + '\x27',
	center: '\x1B' + '\x61' + '\x01',
	left: '\x1B' + '\x61' + '\x30',
	right: '\x1B' + '\x61' + '\x32',
	boldOn: '\x1B' + '\x45' + '\x0D',
	small: '\x1B' + '\x4D' + '\x31',
	normal: '\x1B' + '\x4D' + '\x30',
	large: '\x1D' + '\x21' + '\x11',
	boldOff: '\x1B' + '\x45',
	cutPaper: '\x1B' + '\x69'
}

function printEscpos (dataPrint, vias, printer){
	var dados = [];
	dados.push({type:'raw', data: command.init}); //init
	dados.push({type:'raw', data: command.charsetLatin}); //charset

	for (var i = 0; i < dataPrint.length; i++)
		dados.push({type:'raw', data: dataPrint[i] + '\n'});

	dados.push({type:'raw', data: command.boldOn}); //bold
	dados.push({type:'raw', data: command.center}); //bold
	dados.push({type:'raw', data: '\n\n' + 'DOCUMENTO NÃO FISCAL\n\n'});
	dados.push({type:'raw', data: '\n\n\n\n\n'});
	dados.push({type:'raw', data: command.cutPaper}); //cut paper

	connectAndPrint(dados, vias, printer);
}

function connectAndPrint(dados, vias, printer) {
	connect().then(function() {
		return print(dados, vias, printer);
	}).catch(printFail);             // so one catch is often enough for all promises
}

// connection wrapper
//  - allows active and inactive connections to resolve regardless
//  - try to connect once before firing the mimetype launcher
//  - if connection fails, catch the reject, fire the mimetype launcher
//  - after mimetype launcher is fired, try to connect 3 more times
function connect() {
	return new RSVP.Promise(function(resolve, reject) {
		if (qz.websocket.isActive()) {	// if already active, resolve immediately
			resolve();
		} else {
			// try to connect once before firing the mimetype launcher
			qz.websocket.connect().then(resolve, function reject() {
				// if a connect was not succesful, launch the mimetime, try 3 more times
				window.location.assign("qz:launch");
				qz.websocket.connect({ retries: 2, delay: 1 }).then(resolve, reject);
			});
		}
	});
}

// print 
function print(dados, vias, printer) {
	var options =  { size: { width: 8.5, height: 11}, units: "in", density: "600", copies: vias };
	var config = qz.configs.create(printer, options);
	return qz.print(config, dados).catch(printDisplayError).then(function () {
		printSuccess()
	});
}

//error
function printDisplayError(e) {
	bootbox.alert('Erro ao imprimir: ' + e);
	console.error(e);
}

// notify successful print
function printSuccess() {
	bootbox.alert("Impressão realizada com sucesso!");
}

// exception catch-all
function printFail(e) {
	bootbox.alert("Error: " + e);
	console.error(e);
}

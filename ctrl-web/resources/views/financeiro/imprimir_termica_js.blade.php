<script type="text/javascript">

var escpos = {
	init: '\x1B' + '\x40',
	charsetLatin: '\x1B' + '\x74' + '\x27',
	center: '\x1B' + '\x61' + '\x01',
	boldOn: '\x1B' + '\x45' + '\x0D',
	boldOff: '\x1B' + '\x45' + '\x0A',
	cutPaper: '\x1B' + '\x69'
};

function connectAndPrint(dados, vias) {
	//console.log(dados);
	//printSuccess();
	//return;
		// our promise chain
		connect().then(function() {
				return print(dados, vias);
		}).then(function() {
				printSuccess();              // exceptions get thrown all the way up the stack
		}).catch(printFail);             // so one catch is often enough for all promises

		// NOTE:  If a function returns a promise, you don't need to wrap it in a fuction call.
		//        The following is perfectly valid:
		//
		//        connect().then(print).then(success).catch(fail);
		//
		// Important, in this case success is NOT a promise, so it should stay wrapped in a function() to avoid confusion
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

// print logic
function print(dados, vias) {
		dados1 = [];
		for(i=0;i<dados.length;i++){
			dados1.push(dados[i]);
		}
		if(vias==2){
			for(i=0;i<dados.length;i++){
				dados1.push(dados[i]);
			}
		}
		console.log(dados1);
		var printer = "EPSON TM-T20 Receipt";
		var options =  { size: { width: 8.5, height: 11}, units: "in", density: "600" };
		var config = qz.configs.create(printer, options);


		return qz.print(config, dados1).catch(printDisplayError);
}
</script>

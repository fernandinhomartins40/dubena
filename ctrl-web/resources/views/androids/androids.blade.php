
@extends('layouts.mainmenu')

@section('content')
<div id="mainContent" class="content">
    <div id="divCadastro">
        <div class="row">
            <div class="col-xs-12">
                <div class="box-header">
                    <!--
                    <div class="row">
                        <div class="col-md-12">
                            <div class="col-md-6" style="margin-bottom:1%">
                                <a href="{{ URL::route('android.create') }}" class="btn btn-nw-registro btnNwRegistro">Novo Cadastro</a>
                            </div>
                        </div>
                    </div>
                    -->
                    <div class="panel panel-default">
                        <div class="panel-heading">
                            <h3 class="box-title">Androids</h3>
                        </div><!-- /.box-header -->
                        <div class="panel-body">
                            <div class="col-md-12">
                                <table id="tblCadastro" urlupdate="{{ route("android.edit", ":id") }}" url="{{ route("android.show", ":id") }}" btnClick="false" class="table table-bordered table-hover table-condensed dataTable">
                                    <thead>
                                        <tr>
                                            <th>C&oacute;digo</th>
                                            <th>Id</th>
                                            <th>Descrição</th>
                                            <th>Usuário</th>
                                            <th>Ativo</th>
                                            <th style="width:200px;">Operação</th>
                                        </tr>
                                    </thead>
                                    <tbody id="androids-list" name="androids-list">
                                        @foreach ($androids as $android)
                                        <tr id="android{{$android->id}}">
                                            <td>{{$android->id}}</td>
                                            <td>{{$android->androidid}}</td>
                                            <td>{{$android->descricao}}</td>
                                            <td>{{$android->user == null ? '' : $android->user->name}}</td>
                                            <td>{{$android->ativo ? 'Sim' : 'Não'}}</td>
                                            <td>
                                                @can('view', $android)
                                                    <button onclick="window.location.href = '{{route('android.show',$android->id)}}'"
                                                        class='btn btn-nw-buscas btn-xs' data-toggle='tooltip' data-trigger="hover" data-placement="bottom" title="Visualizar">
                                                            <span class="fa fa-eye fa-lg"></span>
                                                    </button>
                                                @endcan
                                                @can('update', $android)
                                                    <button onclick="window.location.href = '{{route('android.edit',$android->id)}}'"
                                                        class='btn btn-nw-geral btn-xs' data-toggle='tooltip' data-trigger="hover" data-placement="bottom" title="Editar">
                                                                <span class="fa fa-pencil-square-o fa-lg"></span>
                                                    </button>
                                                @endcan
                                                <!--
                                                <button class='btn btn-nw-registro btn-xs' id="btnRemover">Remover</button>
                                                -->
                                            </td>
                                        </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div><!-- /.box-body -->
                    </div><!-- /.box -->
                    <!--
                    <div class="row">
                        <div class="col-md-12">
                            <div class="col-md-5">
                                <a href="{{ URL::route('android.create') }}" class="btn btn-nw-registro btnNwRegistro">Novo Cadastro</a>
                            </div>
                        </div>
                    </div>
                    -->
                </div><!-- /.col -->
            </div><!-- /.row -->
            @include('general.modal_del')
            <div id='rotaDel' class="hidden">{{url('android')}}/</div>
            <!--Rota para redirecionar via ajax-->
            <div id='rotaIndex' class="hidden">{{route('android.index')}}</div>

        </div><!-- /.content-wrapper -->
		<!-- <button type="button" onclick="imprimir();">Imprimir</button> -->
    </div>
	<script src="{{URL::to('plugins/qz-tray/js/dependencies/rsvp-3.1.0.min.js')}}"></script>
	<script src="{{URL::to('plugins/qz-tray/js/dependencies/sha-256.min.js')}}"></script>
	<script src="{{URL::to('plugins/qz-tray/js/qz-tray.js')}}"></script>
	<script>
	// 	var escpos = {
	// 		init: '\x1B' + '\x40',
	// 		charsetLatin: '\x1B' + '\x74' + '\x27',
	// 		center: '\x1B' + '\x61' + '\x01',
	// 		boldOn: '\x1B' + '\x45' + '\x0D',
	// 		boldOff: '\x1B' + '\x45' + '\x0A',
	// 		cutPaper: '\x1B' + '\x69'
	// 	};

	// 	function imprimir(){
	// 		dados = [];

	// 		dados.push({type:'raw', data: escpos.init}); //init
	// 		dados.push({type:'raw', data: escpos.charsetLatin}); //charset
	// 		dados.push({type:'raw', data: escpos.center}); //center
	// 		dados.push({type:'raw', data: '{!!Session::get("empresa_padrao")->razao_social!!}'+'\n'});
	// 		dados.push({type:'raw', data: 'CNPJ: '+'{{Session::get("empresa_padrao")->cnpj}}'+'\n'});
	// 		dados.push({type:'raw', data: 'DOCUMENTO NÃO FISCAL'+'\n\n'});
	// 		dados.push({type:'raw', data: escpos.boldOn}); //bold
	// 		dados.push({type:'raw', data: 'TESTE DE IMPRESSÃO\n\n'});
	// 		dados.push({type:'raw', data: '\n\n\n\n\n'});

	// 		dados.push({type:'raw', data: escpos.cutPaper}); //cut paper
	// 		var vias = 1;
	// 		console.log(dados);
	// 		connectAndPrint(dados, vias);
	// 	}
	// 	function connectAndPrint(dados, vias) {
	// 		connect().then(function() {
	// 			return print(dados, vias);
	// 		}).then(function() {
	// 			printSuccess();              // exceptions get thrown all the way up the stack
	// 		}).catch(printFail);             // so one catch is often enough for all promises
	// 	}

	// 	// connection wrapper
	// 	//  - allows active and inactive connections to resolve regardless
	// 	//  - try to connect once before firing the mimetype launcher
	// 	//  - if connection fails, catch the reject, fire the mimetype launcher
	// 	//  - after mimetype launcher is fired, try to connect 3 more times
	// 	function connect() {
	// 		return new RSVP.Promise(function(resolve, reject) {
	// 			if (qz.websocket.isActive()) {	// if already active, resolve immediately
	// 					resolve();
	// 			} else {
	// 				// try to connect once before firing the mimetype launcher
	// 				qz.websocket.connect().then(resolve, function reject() {
	// 						// if a connect was not succesful, launch the mimetime, try 3 more times
	// 						window.location.assign("qz:launch");
	// 						qz.websocket.connect({ retries: 2, delay: 1 }).then(resolve, reject);
	// 				});
	// 			}
	// 		});
	// 	}

	// 	// print logic
	// 	function print(dados, vias) {
	// 		dados1 = [];
	// 		for(i=0;i<dados.length;i++){
	// 			dados1.push(dados[i]);
	// 		}
	// 		if(vias==2){
	// 			for(i=0;i<dados.length;i++){
	// 				dados1.push(dados[i]);
	// 			}
	// 		}
	// 		var printer = "EPSON TM-T20 Receipt";
	// 		var options =  { size: { width: 8.5, height: 11}, units: "in", density: "600" };
	// 		var config = qz.configs.create(printer, options);

	// 		return qz.print(config, dados1).catch(printDisplayError);
	// 	}
	// 	function printDisplayError(e) {
	// 		bootbox.alert('Erro ao imprimir: ' + e);
	// 		console.error(e);
	// 	}

	// 	// notify successful print
	// 	function printSuccess() {
	// 		bootbox.alert("Impressão realizada com sucesso!");
	// 	}

	// 	// exception catch-all
	// 	function printFail(e) {
	// 		bootbox.alert("Error: " + e);
	// 		console.error(e);
	// 	}

	</script>
    @endsection

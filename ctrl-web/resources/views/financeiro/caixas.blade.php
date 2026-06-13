
@extends('layouts.mainmenu')

@section('content')
<style>

</style>
<div id="mainContent" class="content">
    <div id="divCadastro">
        <div class="row">
            <div class="col-xs-12">
                <div class="box-header">
                    <div class="panel panel-default">
                        <div class="panel-heading">
                            <h3 class="box-title">Caixas permitidos para {{\Auth::User()->name}}</h3>
                        </div><!-- /.box-header -->
                        <div class="panel-body">
                            <div class="col-sm-12">
                                <!-- {{ $contador = 0 }} -->
                                @foreach($contas as $conta)
                                    {{-- @if($conta != null) --}}
                                        @if($conta->ativo)
                                            @if($contador == 0)
                                                <div class="row">
                                            @endif

                                            <div class="col-sm-3">
                                                <div class="info-box">
                                                    <div class="info-box-icon bg-aqua caixa-header">
                                                        <img id="imgInicial" class="img-circle imagem" src="{{URL::to('dist/img/caixa_'.($conta->fechado==1?'fechado':'aberto').'.png')}}" alt="Logotipo"/>
                                                        <div class="caixa-texto fontSize_16"
                                                                onclick="detalhar('{{$conta->id}}|{{$conta->fechado}}');">
                                                            <div>{{ ($conta->fechado == 1 ? 'Abrir Caixa' : 'Visualizar') }}</div>
                                                        </div>
                                                    </div>

                                                    <div class="info-box-content">
                                                        <span class="info-box-number"><small>Nº {{$conta->conta}}</small></span>
                                                        <span class="info-box-number"><small>{{$conta->descricao}}</small></span>
                                                        <span class="info-box-number"><small>{{$conta->fechado==1?'Fechado':'Aberto'}}</span>
                                                        <span class="info-box-number">
                                                            @if($conta->lancarfechado)
                                                                <a href="#" onclick="detalharFechado('{{$conta->id}}|{{$conta->fechado}}');">
                                                                    <small>Lançamento retroativo</small>
                                                                </a>
                                                            @else
                                                            <br>
                                                            @endif
                                                        </span>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- {{ $contador++ }} -->

                                            @if($contador == 4)
                                                </div>
                                                <!-- {{ $contador = 0 }} -->
                                            @endif
                                        @endif
                                    {{-- @endif --}}
                                @endforeach
                                <!-- /.info-box -->
                            </div>
                        </div>
                    </div>
                </div>
            </div><!-- /.col -->
        </div><!-- /.row -->

    </div>
    <div class="modal fade popupModal" id="popup_abrircaixa" tabindex="-1" role="dialog" aria-labelledby="myModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal"><span aria-hidden="true">&times;</span><span class="sr-only">Close</span></button>
                    <h4 class="modal-title" id="myModalLabelFecharCaixa">Confirmar abertura do caixa</h4>
                </div>
                <div class="modal-body  center text-center">
                    <div class="box-body center text-center">
                        <div class="form-group crud_space col-sm-12">
                            {{Form::hidden('conta_id',"", ['id'=>'conta_id'])}}
                            {!! Form::label('data_abertura', 'Data de Abertura:', ['class'=>'col-sm-3 control-label input-sm','style'=>'text-align:right;']) !!}
                            <div class="col-sm-9">
                                <div class="input-group date" id="datetimepicker1">
                                    {!! Form::text('data_abertura',null,['class'=>'form-control input-sm']) !!}
                                    <span class="input-group-addon">
                                        <span class="glyphicon glyphicon-calendar"></span>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" id="btnCloseAbrirCaixa" class="btn btn-nw-geral" data-dismiss="modal">Cancelar</button>
                    <button type="button" id="btnAbrirCaixa" class="btn btn-nw-registro" onclick="abrirCaixa();">Abrir Caixa</button>
                </div>
            </div>
        </div>
    </div>
    <meta name="csrf-token" content="{{ csrf_token() }}" />
    	<script type="text/javascript">

	var operacao = "";
	var root = '{{url("/")}}';
        $(document).ready(function() {
            var myDate = new Date();
            $('#datetimepicker1').datetimepicker({
                    locale: 'pt-br',
                    defaultDate:myDate,
                    format: 'DD/MM/YYYY HH:mm:ss',
            });
        });
	function detalhar(inp){
		pars = inp.split('|');
		if(pars[1]==1){
			confirmaAbrirCaixa(pars[0]);
		} else {
			abrirTelaCaixa(pars[0], false);
		}
	}
        function detalharFechado(inp){
            pars = inp.split('|');
            abrirTelaCaixa(pars[0], true);
	}

        function confirmaAbrirCaixa(conta_id){
			$('#data_abertura').val(currentDateTimeComplete());
                        $('#conta_id').val(conta_id);
			$('#popup_abrircaixa').modal('show');
	}

	function abrirCaixa(){
                $('#popup_abrircaixa').modal('hide');
		if($('#data_abertura').val()==''){
			bootbox.alert('Informe a data de abertura desejada.');
			return false;
		}
		$.ajaxSetup({
				headers: {
						'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
				}
		});
		//console.log(hotCategorias.getData());
		$.ajax({
				url: root+'/api/abrirCaixa',
				type: 'POST',
				dataType: 'json',
				data: {
					"_token": "{{ csrf_token() }}",
					conta_id: $('#conta_id').val(),
					data_abertura: $('#data_abertura').val(),
				},
				success: function(res) {
					if(res.substr(0,3) == 'OK|'){
						abrirTelaCaixa($('#conta_id').val(), false);
					} else {
						bootbox.alert('Houve um problema ao abrir o caixa: ' + data);
					}
				},
				error: function (data) {
					if(typeof(data) == 'object'){
						var msg = '';
						var responseText = '';
						for (var key in data) {
							if(key == 'responseJSON'){
								for(var key1 in data['responseJSON']){
									msg += data['responseJSON'][key1];
								}
							}
							if(key == 'responseText'){
								responseText = data['responseText'];
							}
						}
						if(msg != '')
							bootbox.alert('Erro ao abrir o caixa: ' + msg);
						else
							bootbox.alert('Erro ao abrir o caixa: ' + responseText);
						//bootbox.alert('Erro ao gravar: ' + data.responseJSON.descricao);
					} else if(typeof(data) == 'string'){
							bootbox.alert('Erro ao abrir o caixa: ' + data);
					} else {
						bootbox.alert('Houve um erro desconhecido ao abrir o caixa!');
					}
				}
		});
	}
	function abrirTelaCaixa(id, fechado){
		var url = '{{ route("financeiro.abrirTelaCaixa", ":par") }}';
		url = url.replace(':par', id + '|' + (fechado?'1':'0'));
		window.open(url, '_self');
	}
	</script>

</div><!-- /.content-wrapper -->

@endsection

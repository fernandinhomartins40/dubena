 @extends('layouts.mainmenu')
 @section('content')
<div id="mainContent" class="content">
    <div id="divCadastro">
        <div class="row">
            <div class="col-xs-12">
                <div class="box-header">
                    <div class="row">
                        <div class="col-md-12">
                            <div class="col-md-6" style="margin-bottom:1%">
                                @can('create', App\Conveniofechamento::class)
                                    <a href="{{ URL::route('fechamentoconvenio.create') }}" class="btn btn-nw-registro">Novo Registro</a>
                                @endcan
                            </div>
                            <!--col-md-6-->
                        </div>
                        <!--col-md-12-->
                    </div>
                    <!--row-->
                    <div class="panel panel-default">
                        <div class="panel-heading">
                            <h3 class="box-title">Fechamento de Convênios</h3>
                        </div><!-- /.box-header -->
                        {{ Form::open(['id' => 'searchbar','class'=>'form-horizontal'])}}
                        <div class="panel-body">
                            <div class="form-group crud_space">
                                {!! Form::label('datainicio', 'De:', ['class'=>'col-sm-1 control-label input-sm']) !!}
                                <div class="col-sm-2">
                                    <div class="input-group date generalDatePicker" id="datetimepicker1">
                                        {!! Form::datetime('datainicio',null,['id'=>'datainicio', 'class'=>'form-control input-sm generalDatePicker']) !!}
                                        <span class="input-group-addon">
                                            <span class="glyphicon glyphicon-calendar"></span>
                                        </span>
                                    </div>
                                </div>
                                {!! Form::label('datafim', 'Até:', ['class'=>'col-sm-1 control-label input-sm']) !!}
                                <div class="col-sm-2">
                                    <div class="input-group date generalDatePicker" id="datetimepicker1">
                                        {!! Form::datetime('datafim',null,['id'=>'datafim','class'=>'form-control input-sm generalDatePicker']) !!}
                                        <span class="input-group-addon">
                                            <span class="glyphicon glyphicon-calendar"></span>
                                        </span>
                                    </div>
                                </div>
                                {!! Form::label('cliente_id', 'Convênio:', ['class'=>'col-sm-1 control-label input-sm']) !!}
                                <div class="col-sm-3">
                                    {!! Form::select('cliente_id',$clientes, null, ['id'=>'cliente_id', 'class' => 'form-control selectChosen']) !!}
                                </div>
                                {{Form::close()}}
                                <div class="col-md-2">
                                    <button class="btn btn-sm btn-nw-buscas" id='btnConsultaFechamentos' type="button" data-toggle='tooltip' data-trigger="hover" data-placement="bottom" title="Buscar">
                                        <span class="fa fa-search fa-lg"></span>
                                    </button>
                                    <a class="btn btn-sm btn-github" id='btnZeraFiltro' type="button" data-toggle='tooltip' data-trigger="hover" data-placement="bottom" title="Limpar" href="{{ route('fechamentoconvenio.index') }}">
                                        <span class="fa fa-recycle fa-lg"></span>
                                    </a>
                                </div>
                            </div>
                        </div>
                        <div class="panel-body">
                            <div class="col-md-12">
                                <table id="tblCadastro" class="table table-bordered table-hover table-condensed dataTable">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Cliente</th>
                                            <th>Data Emissão</th>
                                            <th>Data Vencimento</th>
                                            <th>Valor Total</th>
                                            <th style="width:200px;">Operação</th>
                                        </tr>
                                    </thead>
                                    <tbody id="agencias-list" name="veiculoentradasaida-list">
                                        @if(isset($conveniosfechados))
                                        @foreach($conveniosfechados as $convenio)
                                            <tr>
                                                <td>{{$convenio->id}}</td>
                                                <td>{{$convenio->cliente->nome}}</td>
                                                <td>{{requestDataOracle($convenio->dataemissao,false,true)}}</td>
                                                <td>{{requestDataOracle($convenio->datavencimento,false,true)}}</td>
                                                <td>{{requestNumeroDecimalOracle($convenio->valor)}}</td>
                                                <td>
                                                    @can('view', $convenio)
                                                        <button onclick="window.location.href = '{{route('fechamentoconvenio.show',$convenio->id)}}'"
                                                            class='btn btn-nw-buscas btn-xs' data-toggle='tooltip' data-trigger="hover" data-placement="bottom" title="Visualizar">
                                                                <span class="fa fa-eye fa-lg"></span>
                                                        </button>
                                                    @endcan
                                                    @can('update', $convenio)
                                                        <button onclick="window.location.href = '{{route('fechamentoconvenio.edit',$convenio->id)}}'"
                                                            class='btn btn-nw-geral btn-xs' data-toggle='tooltip' data-trigger="hover" data-placement="bottom" title="Editar">
                                                                    <span class="fa fa-pencil-square-o fa-lg"></span>
                                                        </button>
                                                    @endcan
                                                    @can('view', $convenio)
                                                        <a href="{{URL::to('fechamentoconvenio/relatorio/'.$convenio->id)}}" target="_blank" type="button" id="btnPdf"
                                                            class='btn btn-danger btn-xs' data-toggle='tooltip' data-trigger="hover" data-placement="bottom" title="Gerar PDF">
                                                                <span class="fa fa-file-pdf-o fa-lg" aria-hidden="true"></span>
                                                        </a>
                                                        <a href="{{URL::to('fechamentoconvenio/relatorioxls/'.$convenio->id)}}" target="_blank" type="button" id="btnXls"
                                                            class='btn btn-success btn-xs' data-toggle='tooltip' data-trigger="hover" data-placement="bottom" title="Gerar Excel">
                                                                <span class="fa fa-file-excel-o fa-lg" aria-hidden="true"></span>
                                                        </a>
                                                    @endcan
                                                     @can('update', $convenio)
                                                        @if(!$convenio->nfemitida_id)
                                                            <button onclick="confirmaEmitirNF({{$convenio->id}})"
                                                                class='btn btn-default btn-xs' data-toggle='tooltip' data-trigger="hover" data-placement="bottom" title="Emitir NF">
                                                                        <span class="fa fa-file-o fa-lg"></span>
                                                            </button>
                                                        @endif
                                                        @if($convenio->financeiro && count($convenio->financeiro->boleto)==0 && $convenio->financeiro && count($convenio->financeiro->parcelas->where('baixado', 1))==0 && count($convenio->financeiro->parcelas->where('boletogerado', 1))==0)
                                                            <button onclick="confirmaEmitirBoleto({{$convenio->id}})"
                                                                class='btn btn-default btn-xs' data-toggle='tooltip' data-trigger="hover" data-placement="bottom" title="Emitir Boleto">
                                                                        <span class="fa fa-barcode fa-lg"></span>
                                                            </button>
                                                        @endif
                                                    @endcan
                                                </td>
                                            </tr>
                                        @endforeach
                                        @endif
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <!-- /.box-body -->
                    </div>
                    <!-- /.box -->
                    <div class="row">
                        <div class="col-md-12">
                            <div class="col-md-5">
                                @can('create', App\Conveniofechamento::class)
                                    <a href="{{ URL::route('fechamentoconvenio.create') }}" class="btn btn-nw-registro">Novo Registro</a>
                                @endcan
                            </div>
                        </div>
                    </div>
                </div>
                <!-- /.col -->
            </div>
        </div>
        <!-- /.content-wrapper -->
    </div>
</div>
<script type="text/javascript">
@if(isset($_GET['cod']))
if(performance.navigation.type != 1) {
    bootbox.confirm({
        title: "Confirmação",
        message: "Deseja imprimir o relatorio do fechamento?",
        buttons: {
            cancel: {
                label: "Não",
                className: "btn-nw-geral pull-center"
            },
            confirm: {
                label: "Sim",
                className: "btn-nw-registro pull-center"
            }
        },
        callback: function (result) {
            var id = '{{$_GET["cod"]}}';
            if (result) {
                var url = '{!!url("fechamentoconvenio/relatorio/:id")!!}';
                url = url.replace(':id', id);
                window.open(url, '_blank');
            }
        }
    });
}
@endif
$('#btnConsultaFechamentos').click(function () {
    var urlFiltro = root + '/fechamentoconvenio.filtro?cliente=:cliente&datainicio=:datast&datafim=:dataen';
    var cliente = $("#cliente_id").val() == "" ? 0 : $("#cliente_id").val();
    var datainicio = insertDataOracle($("#datainicio").val());
    var datafim = insertDataOracle($("#datafim").val());
    urlFiltro = urlFiltro.replace(':cliente', cliente);
    urlFiltro = urlFiltro.replace(':datast', datainicio);
    urlFiltro = urlFiltro.replace(':dataen', datafim);
    window.location.href = urlFiltro;
});
$( document ).ready( function () {
    if ( getParametro("cliente") ) {
        var cliente_id = getParametro("cliente");
        var inicio = retornarData("datainicio", false);
        var fim = retornarData("datafim", false);
        $("#cliente_id").val(cliente_id).trigger('chosen:updated');
        $("#datainicio").val(inicio);
        $("#datafim").val(fim);
    }
});

function confirmaEmitirNF(conveniofechamento_id){
    bootbox.confirm({
        title: "Confirmação",
        message: "Deseja emitir a NF para esse fechamento?",
        buttons: {
            cancel: {
                label: "Não",
                className: "btn-nw-geral pull-center"
            },
            confirm: {
                label: "Sim",
                className: "btn-nw-registro pull-center"
            }
        },
        callback: function (result) {
            if (result) {
                emitirNF(conveniofechamento_id);
            }
        }
    });
}

function emitirNF(conveniofechamento_id){
    $.ajaxSetup({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        }
    });
    showLoaderAjax("Aguarde", "Gerando Nota Fiscal", false);
    $.ajax({
        url: root + '/fechamentoconvenio.emitirNF',
        type: 'POST',
        dataType: 'json',
        data: {
            conveniofechamento_id: conveniofechamento_id,
        },
        success: function (res) {
            console.log(res);
            hideLoaderAjax();
            if(res.status == 'OK'){
                bootbox.alert(res.data.mensagem, function() {
                    $('#btnConsultaFechamentos').trigger('click');    
                    var url = root + "/nfemitida/evento/consultar?id=:id";
                    url = url.replace(":id", res.data.id);
                    window.open(url, "_blank");
                });
            } else {
                bootbox.alert('erro: ' + res.msg);
            }
        },
        error: function (data) {
            hideLoaderAjax();
            errorFunctionAjax(data);
        }
    });
}

function confirmaEmitirBoleto(conveniofechamento_id){
    bootbox.confirm({
        title: "Confirmação",
        message: "Deseja emitir o boleto para esse fechamento?",
        buttons: {
            cancel: {
                label: "Não",
                className: "btn-nw-geral pull-center"
            },
            confirm: {
                label: "Sim",
                className: "btn-nw-registro pull-center"
            }
        },
        callback: function (result) {
            if (result) {
                emitirBoleto(conveniofechamento_id);
            }
        }
    });
}

function emitirBoleto(conveniofechamento_id){
    $.ajaxSetup({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        }
    });
    showLoaderAjax("Aguarde", "Gerando Boleto", false);
    $.ajax({
        url: root + '/fechamentoconvenio.emitirBoleto',
        type: 'POST',
        dataType: 'json',
        data: {
            conveniofechamento_id: conveniofechamento_id,
        },
        success: function (res) {
            hideLoaderAjax();
            if(res.status == 'OK'){
                console.log(res);
                bootbox.alert(res.data.mensagem, function() {
                    $('#btnConsultaFechamentos').trigger('click');    
                    //const pdfDataUri = "data:application/pdf;base64," + res.data.boleto;
                    //window.open(pdfDataUri, '_blank');
                    const binaryString = atob(res.data.boleto);
                    const len = binaryString.length;
                    const bytes = new Uint8Array(len);
                    for (let i = 0; i < len; i++) {
                        bytes[i] = binaryString.charCodeAt(i);
                    }
                    const blob = new Blob([bytes], { type: 'application/pdf' });
                    const blobUrl = URL.createObjectURL(blob);
                    const a = document.createElement('a');
                    a.href = blobUrl;
                    a.download = 'boleto_' + res.data.id + '.pdf'; 
                    a.target = '_blank'; 
                    //window.open(blobUrl, '_blank');
                    a.click();
                    URL.revokeObjectURL(blobUrl);
                });
            } else {
                bootbox.alert('erro: ' + res.msg);
            }
        },
        error: function (data) {
            hideLoaderAjax();
            errorFunctionAjax(data);
        }
    });
}

function errorFunctionAjax(data) {
    if (typeof (data) == 'object') {
        var msg = '';
        var responseText = '';
        for (var key in data) {
            if (key == 'responseJSON') {
                for (var key1 in data['responseJSON']) {
                    msg += data['responseJSON'][key1];
                }
            }
            if (key == 'responseText') {
                responseText = data['responseText'];
            }
        }
        if (msg != '')
            bootbox.alert('Erro ao executar a operação: ' + msg);
        else
            bootbox.alert('Erro ao executar a operação: ' + responseText);
    } else if (typeof (data) == 'string') {
        bootbox.alert('Erro ao executar a operação: ' + data);
    } else {
        bootbox.alert('Houve um erro desconhecido ao executar a operação!');
    }
}

</script>
@endsection
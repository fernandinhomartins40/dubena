@extends('layouts.mainmenu')

@section('content')
<script src="{{URL::to('js/customNf.js')}}"></script>
<div id="mainContent" class="content">
    <div id="divCadastro">
        <div class="row">
            <div class="col-xs-12">
                <div class="box-header">
                    <div class="panel panel-default">
                        <div class="panel-heading">
                            <h3 class="box-title">Configurações NF p/ Pedidos e Fechamento de Convênio</h3>
                        </div><!-- /.box-header -->
                            {{ Form::open(['class' => 'form-horizontal', 'id' => 'fmCad']) }}
                            <div class="panel-body">
                                <div class="col-md-12">
                                    {{Form::label('operacaoconfig_id', 'Operação Padrão:', ['class' => 'input-sm control-label col-sm-2'])}}
                                    <div class="col-sm-4">
                                        {{Form::select('operacaoconfig_id', $operacoes, null, ['class' => 'selectChosen', 'id' => 'operacaoconfig_id'])}}
                                    </div>
                                </div>
                                <div class="col-md-12">
                                    {{Form::label('nfgrupofiscal_id', 'Grupo Fiscal:', ['class' => 'input-sm control-label col-sm-2'])}}
                                    <div class="col-sm-4">
                                        {{Form::select('nfgrupofiscal_id', $nfgrupofiscals, null, ['class' => 'selectChosen', 'id' => 'nfgrupofiscal_id'])}}
                                    </div>
                                    {{Form::label('operacao_id', 'Operação Item:', ['class' => 'input-sm control-label col-sm-1'])}}
                                    <div class="col-sm-4">
                                        {{Form::select('operacao_id', $operacoes, null, ['class' => 'selectChosen', 'id' => 'operacao_id'])}}
                                    </div>
                                    <div class="col-sm-1">
                                        @can('create', App\Nfceconfigpedido::class)
                                            <button type="button" id="btnAdd" class="btn btn-nw-buscas btn-xs">Adicionar</button>
                                        @endcan
                                    </div>
                                </div>
                                <div class="col-md-12">
                                    <table id="tblOperacoes" url="" btnClick="false" class="table table-bordered table-hover table-condensed">
                                        <thead>
                                            <tr>
                                                <th>Cód. Padrão</th>
                                                <th>Operação Padrão</th>
                                                <th>Cód. Grupo</th>
                                                <th>Grupo Fiscal</th>
                                                <th>Cód. Nova</th>
                                                <th>Operação Item</th>
                                                <th>Remover</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach($vinculos as $vinculo)
                                                <tr>
                                                    <td>{{$vinculo->nfoperacao_id}}</td>
                                                    <td>{{$vinculo->nfoperacao->descricao}}</td>
                                                    <td>{{$vinculo->nfgrupofiscal_id}}</td>
                                                    <td>{{$vinculo->nfgrupofiscal->descricao}}</td>
                                                    <td>{{$vinculo->nfoperacaonova_id}}</td>
                                                    <td>{{$vinculo->nfoperacaonova->descricao}}</td>
                                                    <td>
                                                        @can('delete', $vinculo)
                                                            <button type='button' class='btn btn-nw-registro btn-xs' id='btnRemover'>Remover</button>
                                                        @endcan
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                                {!! Form::submit('Gravar', ['class' => 'btn btn-nw-registro', 'id' => 'btnCadastro']) !!}
                            </div><!-- /.box-body -->
                        {!! Form::close() !!}
                    </div><!-- /.box -->
                </div><!-- /.col -->
            </div><!-- /.row -->
            <!--Rota para a linguagem do plugin de paginação-->
            <div id='urlLanguage' class="hidden">{{URL::to('plugins/datatables/Portuguese-Brasil.json')}}</div>

        </div><!-- /.content-wrapper -->
    </div>
</div>

<script type="text/javascript">

    var btnRemove = "<button type='button' class='btn btn-nw-registro btn-xs' id='btnRemover'>Remover</button>";
    var deleted = [];
    $(document).ready(function () {
        tblOperacoes = $("#tblOperacoes").DataTable({
            "language": {"url": urlDataTable},
            "processing": false,
            "bPaginate": false,
            "bLengthChange": false,
            "bFilter": false,
            "bSort": false,
            "bInfo": false,
            "bAutoWidth": true,
            "destroy": true,
            "sScrollY": "350",
            'columnDefs': [{ width: "9%", targets: [0,2,4]},{ width: "5%", targets: [6]}]
        });
    });
    $("#operacaoconfig_id").on('change', function () {
        var operacao_id = isEmpty($(this).val()) ? 0 : $(this).val();
        enableDisableOptions();
        if(operacao_id > 0){
            var url = root + '/searchConfigNfcePedidoByOperacao/' + operacao_id;
            ajaxGenerator(url, 'GET', function (data) {
                if(typeof data === 'object' || typeof data === 'array') {
                    $.each(data, function (i, el) {
                        if(tblOperacoes.rows().data().length > 0){
                            if(!existsInTable(el))
                                preencherTbl(el);
                        } else {
                            preencherTbl(el);
                        }
                    });
                } else {
                    bootbox.alert('Erro ao buscar configurações:' + data);
                }
            });
            tblOperacoes.rows().every(function () {
                var d = this.data();
                if(d[0] == $("#operacaoconfig_id").val())
                    enableDisableOptions(d[2], true);
            });
        }
    });
    $("#tblOperacoes").on('click', 'button', function () {
        var tr = $(this).parents('tr');
        var nfgrupofiscal_id = $($(tr).children('td')[2]).text();
        var operacaoconfig_id = $($(tr).children('td')[0]).text();
        deleted.push(operacaoconfig_id);
        tblOperacoes.row(tr).remove().draw();
        if(operacaoconfig_id == $("#operacaoconfig_id").val())
            enableDisableOptions(nfgrupofiscal_id, false);
    });
    $("#btnAdd").on('click', function () {
        var operacaoconfig_id = isEmpty($("#operacaoconfig_id").val()) ? 0 : $("#operacaoconfig_id").val();
        if(operacaoconfig_id == 0){
            bootbox.alert('Selecione a operação para configuração');
            return false;
        }

        var operacao_id = isEmpty($("#operacao_id").val()) ? 0 : $("#operacao_id").val();
        var nfgrupofiscal_id = isEmpty($("#nfgrupofiscal_id").val()) ? 0 : $("#nfgrupofiscal_id").val();
        if(operacao_id > 0 && nfgrupofiscal_id > 0) {
            var url = root + "/api/nfimpostoByGfOperacao/" + nfgrupofiscal_id + "/" + operacao_id;
            ajaxGenerator(url, 'GET', function (data) {
                if(data == 'NOK')
                    bootbox.alert({message: 'Não há nenhum imposto cadastrado para este grupo fiscal e operação', callback: function () {addToTable(nfgrupofiscal_id, operacao_id)}});
                else
                    addToTable(nfgrupofiscal_id, operacao_id)
            });
        } else {
            bootbox.alert("Selecione a operação e o grupo fiscal");
        }
    });

    $("#fmCad").on('submit', function (e) {
        e.preventDefault();

        if(tblOperacoes.rows().data().length == 0){
            bootbox.alert("Adicione ao menos um grupo fiscal");
            return false;
        }
        var formData = new FormData($(this)[0]);
        var data = [];
        tblOperacoes.rows().every(function () {
            data.push(this.data());
        });
        formData.append('nfgrupofiscals', JSON.stringify(data));
        formData.append('deleted', JSON.stringify(deleted));
        ajaxGenerator(root + '/confgNfcePedido', 'POST', function (data) {
            if(data == 'OK|')
                bootbox.alert('Gravado com sucesso!');
            else
                bootbox.alert('Erro:' + data);
        }, null, formData);
        return false;
    });

    function addToTable (nfgrupofiscal_id, operacao_id) {
        setTimeout(function () {
            var operacao = $("#operacao_id option:selected").text();
            var nfgrupofiscal = $("#nfgrupofiscal_id option:selected").text();
            var operacaonova = $("#operacaoconfig_id option:selected").text();
            var operacaonova_id = $("#operacaoconfig_id").val();
            tblOperacoes.row.add([operacaonova_id, operacaonova, nfgrupofiscal_id, nfgrupofiscal, operacao_id, operacao, btnRemove]).draw();
            $("#nfgrupofiscal_id option:selected").prop('disabled', true).trigger('chosen:updated');
        }, 500);
    }

    function enableDisableOptions (nfgrupofiscal_id, enableDisable) {
        if(typeof nfgrupofiscal_id == 'undefined'){
            $("#nfgrupofiscal_id option").filter(function () {
                $(this).prop('disabled', false).trigger('chosen:updated');
            });
        } else {
            $("#nfgrupofiscal_id option").filter(function () {
               if($(this).val() == nfgrupofiscal_id) {
                    $(this).prop('disabled', enableDisable).trigger('chosen:updated');
                    return false;
               }
            });
        }
    }

    function preencherTbl (el) {
        var data = [el.nfoperacao_id, el.nfoperacao.descricao, el.nfgrupofiscal_id, el.nfgrupofiscal.descricao, el.nfoperacaonova_id, el.nfoperacaonova.descricao, btnRemove];
        tblOperacoes.row.add(data).draw();
    }

    function existsInTable (el) {
        var exists = false;
        tblOperacoes.rows().every(function () {
            var d = this.data();
            if(el.nfoperacao_id == d[0] && el.nfgrupofiscal_id == d[2]) {
                exists = true;
                return true;
            }
        });
        if(exists)
            return true;
        return false;
    }
</script>
@endsection


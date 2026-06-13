@extends('layouts.mainmenu')
@section('content')
<div id="mainContent" class="content">
    <div id="divCadastro" class="row">
        <div class="col-md-12">
            <!-- Custom Tabs -->
            @if(isset($veiculo))
            {{ Form::model($veiculo, array('id'=>'fmCadastro', 'method' => 'PATCH', 'class' => 'form-horizontal', 'files' => true, 'route' => array('veiculo.update', $veiculo->id))) }}
            @else
            {{ Form::open(['id'=>'fmCadastro', 'route' => 'veiculo.store', 'class' => 'form-horizontal', 'files' => true]) }}
            @endif
            <ul>
            <div class="panel panel-default">
                    <div class="panel-heading">
                        <h3 class="panel-title">Veículo</h3>
                    </div>
                <div class="nav-tabs-custom">
                    <ul class="nav nav-tabs">
                        <li class="active"><a href="#tab_1" data-toggle="tab">Dados Gerais</a></li>
                        <li class=""><a href="#tab_2" data-toggle="tab">Documentos</a></li>
                        <li class=""><a href="#tab_3" data-toggle="tab">Setores</a></li>
                    </ul>
                    <div class="tab-content">
                        <div class="tab-pane active" id="tab_1">
                            <!-- form start -->
                            <div class="row">
                                <div id="tabCadastro" class="col-md-12">
                                    <div class="box-body">
                                        <div class="form-group crud_space">
                                            {!! Form::label('veiculotipo_id', 'Tipo Veículo:', ['class'=>'col-sm-2 control-label input-sm']) !!}
                                            <div class="col-sm-2">
                                                {!! Form::select('veiculotipo_id', $veiculotipos, null, ['id'=>'veiculotipo_id', 'class' => 'selectChosen form-control', 'style'=>'padding:0px;max-height:24px;']) !!}
                                            </div>
                                            {!! Form::label('ativo', 'Ativo:', ['class'=>'col-sm-2 control-label input-sm']) !!}
                                            <div class="col-sm-1 checkbox">
                                                {{ Form::checkbox('ativo') }}
                                            </div>
                                        </div>
                                        <div class="form-group crud_space">
                                            {!! Form::label('placa', 'Placa:', ['class'=>'col-sm-2 control-label input-sm']) !!}
                                            <div class="col-sm-2">
                                                {!! Form::text('placa',null,['id'=>'placa', 'class'=>'form-control input-sm placa']) !!}
                                            </div>
                                            {!! Form::label('placauf', 'UF:', ['class'=>'col-sm-2 control-label input-sm']) !!}
                                            <div class="col-sm-2">
                                                {!! Form::select('placauf', $estados, null, ['class' => 'form-control selectChosen', 'style'=>'padding:0px;max-height:24px;']) !!}
                                            </div>
                                        </div>
                                        <div class="form-group crud_space">
                                            {!! Form::label('descricao', 'Descrição:', ['class'=>'col-sm-2 control-label input-sm']) !!}
                                            <div class="col-sm-6">
                                                {!! Form::text('descricao',null,['class'=>'form-control input-sm']) !!}
                                            </div>
                                        </div>
                                        <div class="form-group crud_space">
                                            {!! Form::label('tipocombustivel_id', 'Combustível:', ['class'=>'col-sm-2 control-label input-sm']) !!}
                                            <div class="col-sm-2">
                                                {!! Form::select('tipocombustivel_id', $tipocombustivels, null, ['id'=>'tipocombustivel_id', 'class' => 'selectChosen form-control', 'style'=>'padding:0px;max-height:24px;']) !!}
                                            </div>
                                        </div>
                                        <div class="form-group crud_space">
                                            {!! Form::label('pneus', 'Pneus:', ['class'=>'col-sm-2 control-label input-sm']) !!}
                                            <div class="col-sm-2">
                                                {!! Form::text('pneus',null,['class'=>'form-control input-sm']) !!}
                                            </div>
                                            {!! Form::label('pneusvidautilkm', 'Pneus vida útil km:', ['class'=>'col-sm-2 control-label input-sm']) !!}
                                            <div class="col-sm-2">
                                                {!! Form::text('pneusvidautilkm',null,['class'=>'form-control input-sm']) !!}
                                            </div>
                                        </div>
                                        <div class="form-group crud_space">
                                            {!! Form::label('colaborador_id', 'Condutor:', ['class'=>'col-sm-2 control-label input-sm']) !!}
                                            <div class="col-sm-6">
                                                {!! Form::select('colaborador_id', $colaboradors, null, ['id'=>'colaborador_id', 'class' => 'selectChosen form-control', 'style'=>'padding:0px;max-height:24px;']) !!}
                                            </div>
                                        </div>
                                        <div class="form-group crud_space">
                                            {!! Form::label('kminicial', 'Km inicial:', ['class'=>'col-sm-2 control-label input-sm']) !!}
                                            <div class="col-sm-2">
                                                {!! Form::text('kminicial',null,['class'=>'form-control input-sm']) !!}
                                            </div>
                                            {!! Form::label('kmatual', 'Km atual:', ['class'=>'col-sm-2 control-label input-sm']) !!}
                                            <div class="col-sm-2">
                                                {{ Form::hidden('kmatual') }}
                                                {!! Form::text('kmatual',null,['class'=>'form-control input-sm', 'disabled' => 'disabled', 'id'=>'kmatualjs' ]) !!}
                                            </div>
                                        </div>
                                        <div class="form-group crud_space">
                                            {!! Form::label('kmtrocaoleo', 'Troca de óleo a cada (KM):', ['class'=>'col-sm-2 control-label input-sm']) !!}
                                            <div class="col-sm-2">
                                                {!! Form::text('kmtrocaoleo',null,['class'=>'form-control input-sm']) !!}
                                            </div>
                                            {!! Form::label('kmultimatrocaoleo', 'Km última troca óleo:', ['class'=>'col-sm-2 control-label input-sm']) !!}
                                            <div class="col-sm-2">
                                                {{ Form::hidden('kmultimatrocaoleo') }}
                                                {!! Form::text('kmultimatrocaoleo',null,['class'=>'form-control input-sm', 'id'=>'kmultimatrocaoleocampo']) !!}
                                            </div>
                                        </div>
                                        <div class="form-group crud_space">
                                            {!! Form::label('alertasdiasantes', 'Alertas dias antes:', ['class'=>'col-sm-2 control-label input-sm']) !!}
                                            <div class="col-sm-1">
                                                {!! Form::text('alertasdiasantes',null,['class'=>'form-control input-sm']) !!}
                                            </div>
                                            {!! Form::label('usarastreamento', 'Rastrear Veículo:', ['class'=>'col-sm-3 control-label input-sm']) !!}
                                            <div class="col-sm-1 checkbox">
                                                {{ Form::checkbox('usarastreamento') }}
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div><!-- /.tab-pane -->
                        </div><!-- /.tab-pane -->
                        <div class="tab-pane" id="tab_2">
                            <!-- form start -->
                            <div class="row">
                                <div id="tabCadastroDoc" class="col-md-10">
                                    <div class="box-body">
                                        <div class="col-md-12  col-md-offset-1">
                                            <div class="col-sm-2">
                                                {!! Form::text('descricao_doc',null,['id'=>'descricao_doc', 'class'=>'form-control input-sm', 'placeholder'=>'Descrição']) !!}
                                            </div>
                                            <div class="col-md-2">
                                                {!! Form::select('tipodocumento_id', $tipodocumentos, null, ['id'=>'tipodocumento_id', 'class' => 'selectChosen form-control', 'style'=>'padding:0px;max-height:24px;']) !!}
                                            </div>
                                            <div class="col-sm-2">
                                                {!! Form::text('numero',null,['id'=>'numero', 'class'=>'form-control input-sm number', 'placeholder'=>'Número']) !!}
                                            </div>
                                            <div class="col-sm-2">
                                                <div class="input-group generalDatePicker" id="">
                                                    {!! Form::text('vencimento',null,['id'=>'vencimento', 'class'=>'form-control input-sm generalDatePicker', 'placeholder'=>'Vencimento']) !!}
                                                    <span class="input-group-addon">
                                                        <span class="glyphicon glyphicon-calendar"></span>
                                                    </span>
                                                </div>
                                            </div>
                                            {!! Form::label('alerta', 'Alerta:', ['class'=>'col-sm-1 control-label input-sm']) !!}
                                            <div class="col-sm-1 checkbox">
                                                {{ Form::checkbox('alerta') }}
                                            </div>
                                            <button type="button" id="btnAddDoc" class="btn btn-xs btn-nw-buscas" onclick="addDoc();">Adicionar</button>
                                        </div>
                                        <div class="col-md-12  col-md-offset-1">
                                            {{ Form::hidden('documentos',"", ['id'=>'documentos']) }}
                                            <table id="tblDocumentos" class="table table-bordered table-hover table-condensed">
                                                <thead>
                                                    <tr>
                                                        <th>Descrição</th>
                                                        <th>Tipo Documento ID</th>
                                                        <th>Tipo Documento</th>
                                                        <th>Número</th>
                                                        <th>Vencimento</th>
                                                        <th>Alerta</th>
                                                        <th style='width: 12%'>Operação</th>
                                                    </tr>
                                                </thead>
                                                <tbody id="veiculos-list" name="veiculos-list">
                                                    @if(isset($veiculo))
                                                    @foreach ($veiculo->veiculodocumentos as $documento)
                                                    <tr id="doc{{$documento->id}}">
                                                        <td>{{$documento->descricao}}</td>
                                                        <td>{{$documento->tipodocumento->id}}</td>
                                                        <td>{{$documento->tipodocumento->descricao}}</td>
                                                        <td>{{$documento->numero}}</td>
                                                        <td>{{$documento->vencimento}}</td>
                                                        <td>{{$documento->alerta == 1 ? 'Sim' : 'Não'}}</td>
                                                        <td><button type='button' class='btn btn-nw-registro btn-xs' id='btnRemoverDocumento'>Remover</button></td>
                                                    </tr>
                                                    @endforeach
                                                    @endif
                                                </tbody>
                                            </table>
                                        </div><!-- /.box -->
                                    </div>
                                </div>
                            </div>
                        </div><!-- /.tab-pane -->
                        <div class="tab-pane" id="tab_3">
                            <!-- form start -->
                            <div class="row">
                                <div id="tabCadastroSetores" class="col-md-10">
                                    <div class="box-body">
                                        <div class="form-group crud_space">
                                            {!! Form::label('setors', 'Setores:', ['class'=>'col-sm-2 control-label input-sm']) !!}
                                            <div class="col-sm-5">
                                                {!! Form::select('setors', $setors, null, ['id'=>'setors', 'class' => 'form-control input-sm selectDisableSearch']) !!}
                                            </div>
                                            <div class="col-sm-2">
                                                <button type="button" id="addSetor" class='btn btn-nw-buscas btn-xs'>Adicionar</button>
                                            </div>
                                        </div>
                                        <div class="col-sm-10 col-sm-push-2">
                                            <hr class='thin'>
                                            <table id="tblListaSetors" class="table table-bordered table-hover table-condensed  bg-success">
                                                <thead>
                                                    <tr>
                                                        <th style="width:75px">C&oacute;digo</th>
                                                        <th style="width:300px">Setor</th>
                                                        <th style="width:50px;">Operação</th>
                                                    </tr>
                                                </thead>
                                                <tbody id="tbodySetorsList" name="tbodySetorsList">
                                                    @if(isset($veiculo))
                                                    @foreach ($veiculo->setors as $setor)
                                                    <tr id="tr{!! $setor->id !!}">
                                                        <td>{{$setor->id}}</td>
                                                        <td>{{$setor->descricao}}</td>
                                                        <td>
                                                            <button type='button' class='btn btn-nw-registro btn-xs' id='btnRemover{!! $setor->id !!}'>Remover</button>
                                                        </td>
                                                    </tr>
                                                    @endforeach
                                                    @endif
                                                </tbody>
                                            </table>
                                            {!! Form::text('inputSetorsList', $inputSetorsList, ['id'=>'inputSetorsList', 'class' => 'form-control input-sm hidden']) !!}
                                            {!! Form::text('inputSetorsListId', $inputSetorsListId, ['id'=>'inputSetorsListId', 'class' => 'form-control input-sm hidden']) !!}

                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div><!-- /.tab-pane -->
                    </div><!-- /.tab-content -->
                            <div class="box-footer">
                                <div class="col-md-4">
                                    {!! Form::submit('Gravar', ['class' => 'btn btn-nw-registro']) !!}
                                    <a type="button" href="{{url('veiculo')}}" class="btn btn-nw-geral">Voltar</a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                {!! Form::close() !!}
            </ul><!-- /.col -->
        </div>
    </div>

<script type="text/javascript">
    function addDoc() {
        if ($('#descricao_doc').val().trim() == '') {
            bootbox.alert('Preencha a descrição.');
            return;
        }
        if ($('#tipodocumento_id').isEmpty()) {
            bootbox.alert('Preencha o tipo de documento.');
            return;
        }
        if ($('#numero').val().trim() == '') {
            bootbox.alert('Preencha o número.');
            return;
        }
        if ($('#vencimento').val().trim() == '') {
            bootbox.alert('Preencha o vencimento.');
            return;
        }
        tblDocumentos.row.add([
            $('#descricao_doc').val(),
            $('#tipodocumento_id').val(),
            $('#tipodocumento_id option:selected').text(),
            $('#numero').val(),
            $('#vencimento').val(),
            $('#alerta').prop('checked') == 1 ? 'Sim' : 'Não',
            "<button type='button' class='btn btn-nw-registro btn-xs' id='btnRemoverDocumento'>Remover</button>"
        ]).draw(false);
        $('#descricao_doc').val('');
        $('#tipodocumento_id').val('').trigger('chosen:updated');
        $('#numero').val('');
        $('#vencimento').val('');
        $('#alerta').prop("checked", false);
    }
    var confirm;
    var t;
    var root = '{{url("/")}}';
    $(".delete").on("submit", function () {
        return confirm("Quer remover o registro atual?");
    });
    jQuery(document).ready(function ($) {
        @if(str_contains(Request::url(),'edit'))
            $("#kmultimatrocaoleocampo").prop('disabled', 'disabled');
        @endif
        tblDocumentos = $('#tblDocumentos').DataTable({
            "language": {"url": "{{URL::to('plugins/datatables/Portuguese-Brasil.json')}}"},
            "processing": false,
            "bPaginate": false,
            "bLengthChange": false,
            "bFilter": false,
            "bSort": true,
            "bInfo": false,
            "bAutoWidth": false,
            "columnDefs": [
                {
                    "targets": [0],
                    "visible": false,
                    "targets": [1],
                    "visible": false,
                }
            ]
        });
        $("#fmCadastro").on("submit", function () {
            var docs = [];
            tblDocumentos.rows().every(function () {
                var d = this.data();
                docs.push(d);
            });
            $('#documentos').val(JSON.stringify(docs));
        });
        $('#tblDocumentos').on('click', 'button', function () {
            var trElem = $(this).closest("tr");// grabs the button's parent tr element
            var firstTd = $(trElem).children("td")[0]; //takes the first td which would have your Id
            /*var telefone = $(trElem).children("td")[2];*/
            if ($(firstTd).text() != "") {
                if ($(this).context.id == 'btnRemoverDocumento') {
                    tblDocumentos
                            .row($(this).parents('tr'))
                            .remove()
                            .draw();
                }
            }
            ;
        });
        $("#addSetor").on('click', function () {
            var setor = $('#setors option:selected').text();
            var id = $('#setors').val();
            var button = "<button type='button' class='btn btn-nw-registro btn-xs' id='btnRemover" + id + "'>Remover</button>";

            if ($("#inputSetorsList").val().indexOf(id) !== -1) {
                bootbox.alert('Setor já adicionado a esse veículo!');
            } else {
                listId = $("#inputSetorsListId").val($("#inputSetorsListId").val() + ($("#inputSetorsListId").val()==''?'':'||')  +  id);
                setorsListTable = "<tr id='tr" + id + "'><td>" + id + "</td><td>" + setor + "</td><td>" + button + "</td></tr>";
                $("#inputSetorsList").val($("#inputSetorsList").val() + setorsListTable);
                $("#tbodySetorsList").append(setorsListTable);
            }
        });

        $("#tblListaSetors").on('click', 'button', function () {
            id = $(this).attr('id');
            id = id.replace('btnRemover', 'tr');
            $("#" + id).remove();
            $("#inputSetorsList").val('');
            $("#inputSetorsList").val($("#tbodySetorsList").html());
            id = id.replace('tr', '');
            if ($("#inputSetorsListId").val().indexOf("||" + id) != -1) {
               $("#inputSetorsListId").val($("#inputSetorsListId").val().replace('||' + id, ''));
            }
            else if($("#inputSetorsListId").val().indexOf(id + '||') != -1) {
                $("#inputSetorsListId").val($("#inputSetorsListId").val().replace(id + '||', ''));
            } else {
               $("#inputSetorsListId").val($("#inputSetorsListId").val().replace(id, ''));
            }
        });

    });

        var errorElement = document.querySelector('#errorMsg');

        function errorMsg(msg, error) {
            errorElement.innerHTML += '<p>' + msg + '</p>';
            if (typeof error !== 'undefined') {
                console.error(error);
            }
        }
        @if ($errors -> any())
        $(document).ready(function() {
                carregarDocumentosErro();
                @if (!isset($show) && !isset($veiculo))
                    $('#kmatual').val($("#kminicial").val());
                    $('#kmatualjs').val($("#kminicial").val());
                @endif
        });
        @endif
        setTimeout(function () {
            @if (isset($show))
                    desativarInputs();
                var ids = [".btn-danger", ".btn-nw-registro", '#btnAddDoc', '#addSetor'];
                desativarInputsEspecificos(ids);
            @endif
        }, $(document).ready());
    
    function carregarDocumentosErro() {
        tblDocumentos.clear();
        documentos = JSON.parse($('#documentos').val());
        for (i = 0; i < documentos.length; i++) {
            tblDocumentos.row.add([
                documentos[i][0],
                documentos[i][1],
                documentos[i][2],
                documentos[i][3],
                documentos[i][4],
                documentos[i][5],
                documentos[i][6]
            ]).draw(false);
        }
    }
    $("#kminicial").blur(function (event) {
        @if (!isset($show) && !isset($veiculo))
            $('#kmatual').val($("#kminicial").val());
            $('#kmatualjs').val($("#kminicial").val());
        @endif
    });
</script>
@endsection

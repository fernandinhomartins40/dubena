@extends('layouts.mainmenu')
@section('content')
<div id="mainContent" class="content">
    <div id="divCadastro" class="row">
        <div class="col-md-12">
            <div class="box-header">
                <h3 class="box-title">TESTE ESTOQUE</h3>
            </div><!-- /.box-header -->
            <!-- Custom Tabs -->
            <!-- <form id="fmCadastro" role="form" class="form-horizontal" method="POST" enctype="multipart/form-data"> -->
            @if(isset($agencia))
            {{ Form::model($agencia, array('id'=>'fmCadastro', 'method' => 'PATCH', 'class' => 'form-horizontal', 'files' => true, 'route' => array('agencia.update', $agencia->id))) }}
            @else
            {{ Form::open(['id'=>'fmCadastro', 'route' => 'testesestoque.store', 'class' => 'form-horizontal', 'files' => true]) }}
            @endif
            <ul>
                <div class="nav-tabs-custom">
                    <ul class="nav nav-tabs">
                        <li class="active"><a href="#tab_1" data-toggle="tab">Dados Gerais</a></li>
                        <li class=""><a href="#tab_2" data-toggle="tab">Endereço</a></li>
                        <li class=""><a href="#tab_3" data-toggle="tab">Contatos</a></li>
                    </ul>
                    <div class="tab-content">
                        <div class="tab-pane active" id="tab_1">
                            <!-- form start -->
                            <div class="row">
                                <div id="tabCadastro" class="col-md-10">
                                    <div class="box-body">
                                        <div class="form-group crud_space">
                                            {!! Form::label('banco_id', 'Banco:', ['class'=>'col-sm-2 control-label input-sm']) !!}
                                            <div class="col-sm-10">

                                            </div>
                                        </div>
                                        <div class="form-group crud_space">
                                            {!! Form::label('agencia', 'Agência:', ['class'=>'col-sm-2 control-label input-sm']) !!}
                                            <div class="col-sm-2">
                                                {!! Form::text('agencia',null,['class'=>'form-control input-sm']) !!}
                                            </div>
                                            {!! Form::label('agenciadigito', 'Agência Dígito:', ['class'=>'col-sm-2 control-label input-sm']) !!}
                                            <div class="col-sm-2">
                                                {!! Form::text('agenciadigito',null,['class'=>'form-control input-sm']) !!}
                                            </div>
                                            {!! Form::label('postobeneficiario', 'Posto Beneficiário:', ['class'=>'col-sm-2 control-label input-sm']) !!}
                                            <div class="col-sm-2">
                                                {!! Form::text('postobeneficiario',null,['class'=>'form-control input-sm']) !!}
                                            </div>
                                        </div>
                                        <div class="form-group crud_space">
                                            {!! Form::label('descricao', 'Descrição:', ['class'=>'col-sm-2 control-label input-sm']) !!}
                                            <div class="col-sm-10">
                                                {!! Form::text('descricao',null,['class'=>'form-control input-sm']) !!}
                                            </div>
                                        </div>
                                        <div class="form-group crud_space">
                                            {!! Form::label('ativo', 'Ativo:', ['class'=>'col-sm-2 control-label input-sm']) !!}
                                            <div class="col-sm-2 checkbox">

                                                {{ Form::checkbox('ativo') }}
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div><!-- /.tab-pane -->
                        </div><!-- /.tab-pane -->
                        <div class="tab-pane" id="tab_2">
                            <div class="row">
                                <div id="tabCadastro" class="col-md-10">
                                    <div class="box-body">

                                    </div>
                                </div>
                            </div>
                        </div><!-- /.tab-pane -->
                        <div class="tab-pane" id="tab_3">
                            <!-- form start -->
                            <div class="row">
                                <div id="tabCadastro" class="col-md-10">
                                    <div class="box-body">
                                        <div class="form-group crud_space">
                                            {!! Form::label('email', 'e-mail:', ['class'=>'col-sm-3 control-label input-sm']) !!}
                                            <div class="col-sm-9">
                                                {!! Form::text('email',null,['class'=>'form-control input-sm']) !!}
                                            </div>
                                        </div>
                                        <div class="col-md-6  col-md-offset-4">
                                            {{Form::hidden('telefones',"", ['id'=>'telefones'])}}
                                            <table id="tblTelefones" class="table table-bordered table-hover table-condensed">
                                                <thead>
                                                    <tr>
                                                        <th></th>
                                                        <th>Tipo Telefone</th>
                                                        <th>Número</th>
                                                        <th></th>
                                                    </tr>
                                                </thead>
                                                <tbody id="agencias-list" name="agencias-list">
                                                    @if(isset($agencia))
                                                    @foreach ($agencia->telefones as $telefone)
                                                    <tr id="fone{{$telefone->telefonetipo_id}}">
                                                        <td>{{$telefone->telefonetipo_id}}</td>
                                                        <td>{{$telefone->telefonetipo->descricao}}</td>
                                                        <td>{{$telefone->telefone}}</td>
                                                        <td><button type='button' class='btn btn-nw-registro small' id='btnRemoverTelefone'>Remover</button></td>
                                                    </tr>
                                                    @endforeach
                                                    @endif
                                                </tbody>
                                            </table>
                                        </div><!-- /.box -->
                                        <div class="col-md-6  col-md-offset-4">
                                            <div class="col-md-4">

                                            </div>
                                            <input type="text" id="telefone" class="col-md-4">
                                            <button type="button" class="btn-primary col-md-2" onclick="addFone();">Adicionar</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div><!-- /.tab-pane -->
                    </div><!-- /.tab-content -->
                    <div class="nav-tabs-custom" style="margin-top:5px;">
                        <div class="col-md-12">
                            <div class="box-footer">
                                <div class="col-md-4">
                                    {!! Form::submit('Gravar', ['class' => 'btn btn-nw-registro']) !!}
                                    <button type="button" onclick="window.history.back();" class="btn btn-nw-geral">Voltar</button>
                                </div>
                                @if($errors->any())
                                <div id="saveError" class="alert alert-danger alert-dismissable col-md-4">
                                    <button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>
                                    <span class="glyphicon glyphicon-remove"></span>
                                    @foreach($errors->all() as $error)
                                    <p>{{ $error }}</p>
                                    @endforeach
                                </div>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
                {!! Form::close() !!}
                {{ Form::open(['id'=>'fmCadastro', 'route' => 'testesestoque.store', 'class' => 'form-horizontal', 'files' => true]) }}
                {{Form::hidden('operacao','movimentarEstoque')}}
                {!! Form::submit('Movimentar Estoque', ['class' => 'btn btn-nw-registro']) !!}
                {!! Form::close() !!}
                {{ Form::open(['id'=>'fmCadastro', 'route' => 'testesestoque.store', 'class' => 'form-horizontal', 'files' => true]) }}
                {{Form::hidden('operacao','fecharEstoque')}}
                {!! Form::submit('Fechar Estoque', ['class' => 'btn btn-nw-registro']) !!}
                {!! Form::close() !!}
                {{ Form::open(['id'=>'fmCadastro', 'route' => 'testesestoque.store', 'class' => 'form-horizontal', 'files' => true]) }}
                {{Form::hidden('operacao','abrirEstoque')}}
                {!! Form::submit('Abrir Estoque', ['class' => 'btn btn-nw-registro']) !!}
                {!! Form::close() !!}
                @if(isset($agencia))
                <!--
                <div class="nav-tabs-custom">
                <div class="col-md-12">
                <div class="box-footer">
                <div class="col-md-4">
                {!! Form::open(['class' => 'delete', 'action' => ['AgenciaController@destroy', $agencia->id], 'method' => 'delete']) !!}
                {!! Form::submit('Remover', ['class'=>'btn btn-danger']) !!}
                {!! Form::close() !!}
            </div>
        </div>
    </div>
</div>
-->
@endif
</ul><!-- /.col -->
</div>
</div>
</div>
<div id="popup_capture" class="modal fade popupModal" tabindex="-1" role="dialog" aria-labelledby="myModalLabel">
    <div class="modal-dialog" role="document" id="fundo_popup">
        <div class="modal-content">
            <div class="modal-header" id="popup_int">
                <button type="button" id="btnCloseCapture" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                <h4 class="modal-title" id="myModalLabel" style="text-align: center;">Capturar Foto</h4>
            </div>
            <div class="container" style="margin-left:20px;">
                <video autoplay></video>
                <div class="photoArea" style="margin-left: 70px;"></div>
            </div>
            <canvas width='360' height='480' style="border: 1px solid #d3d3d3;display:none;"></canvas>
            <img id="foto_popup" width='180' height='240'></img>
            <div class="controls text-center">
                <input type="button" value="Iniciar câmera" onclick="startCapture()" />
                <input type="button" value="Capturar Foto" onclick="takePhoto()" />
                <!--
                <input type="button" value="Parar câmera" onclick="stopCapture()" />
            -->
        </div>
    </div>
</div>
</div>
@include('general.popupbairrocidade_form_partial')
<!-- DATA TABES SCRIPT -->
<!-- page script -->
<script type="text/javascript">
var nomeCidade;
var origemCidade;
var origemUF;
var nomeBairro;
var confirm;
var t;
var root = '{{url("/")}}';
window.onload=function(){
    document.getElementById('foto').onchange = function (evt) {
        var tgt = evt.target || window.event.srcElement,
        files = tgt.files;
        // FileReader support
        if (FileReader && files && files.length) {
            var fr = new FileReader();
            fr.onload = function () {
                document.getElementById('fotoImg').src = fr.result;
            }
            fr.readAsDataURL(files[0]);
        }
    }
}//]]>
$('.modal-wide').on('show.bs.modal', function () {
    var height = $(window).height() - 200;
    $(this).find('.modal-body').css('max-height', height);
});
$('#popup_cidade').on('shown.bs.modal', function () {
    $('#descricao_cidade').focus();
});
$('#popup_bairro').on('shown.bs.modal', function () {
    $('#descricao_bairro').focus();
});
$(".delete").on("submit", function(){
    return confirm("Quer remover o registro atual?");
});
jQuery(document).ready(function($){
    mudarTipoPessoa();
    $("#cep").mask("99999-999",{placeholder:""});
    $('#popup_capture').on('hidden.bs.modal', function () {
        stopCapture();
    })
    $(".modal-wide").on("show.bs.modal", function() {
        var height = $(window).height() - 200;
        $(this).find(".modal-body").css("max-height", height);
    });
    tblFone = $('#tblTelefones').DataTable( {
        "language": { "url" : "{{URL::to('plugins/datatables/Portuguese-Brasil.json')}}"},
        "processing": false,
        "bPaginate": false,
        "bLengthChange": false,
        "bFilter": false,
        "bSort": true,
        "bInfo": false,
        "bAutoWidth": false,
        "columnDefs": [
            {
                "targets": [ 0 ],
                "visible": false
            }
        ]
    });
    $("#fmCadastro").on("submit", function(){
        var fones = [];
        tblFone.rows().every( function () {
            var d = this.data();
            fones.push(d);
        } );
        $('#telefones').val(JSON.stringify(fones));
        var contatos = [];
        tblCont.rows().every( function () {
            var d = this.data();
            contatos.push(d);
        } );
        $('#contatos').val(JSON.stringify(contatos));
    });
    $('#datetimepicker1').datetimepicker({
        locale: 'pt-br',
        viewMode: 'years',
        format: 'DD/MM/YYYY'
    });
    $('#datetimepicker2').datetimepicker({
        locale: 'pt-br',
        viewMode: 'years',
        format: 'DD/MM/YYYY'
    });
    $('#datetimepicker3').datetimepicker({
        locale: 'pt-br',
        viewMode: 'days',
        format: 'DD/MM/YYYY'
    });
    $('#uf').change(function(){
        changeUf(null, null);
    });
    $('#cidade_id').change(function(){
        changeCidade(null);
    });
    $("#cep").blur(function() {
        //Nova variável "cep" somente com dígitos.
        var cep = $(this).val().replace(/\D/g, '');
        //Verifica se campo cep possui valor informado.
        if (cep != "") {
            //Expressão regular para validar o CEP.
            var validacep = /^[0-9]{8}$/;
            //Valida o formato do CEP.
            if(validacep.test(cep)) {
                $.getJSON("//viacep.com.br/ws/"+ cep +"/json/?callback=?", function(dados) {
                    if (!("erro" in dados)) {
                        if($("#endereco").val() == ''){
                            atualizarEndereco(dados);
                        } else if($("#endereco").val() != dados.logradouro){
                            bootbox.confirm({
                                title: "Confirmação",
                                message: "Deseja atualizar o endereço pelo CEP?",
                                buttons: {
                                    cancel: {
                                        label: "Não",
                                        className: "btn-default pull-center"
                                    },
                                    confirm: {
                                        label: "Sim",
                                        className: "btn-danger pull-center"
                                    }
                                },
                                callback: function(result) {
                                    if (result) {
                                        atualizarEndereco(dados);
                                    }
                                }
                            });
                        }
                    }
                });
            }
        }
    });
    $("form#fmCidade").submit(function(){
        $('#grupo_id_cidade').val($('#grupo_id').val());
        $('#uf_cidade').val($('#' + origemUF).val());
        var formData = new FormData($(this)[0]);
        $.ajax({
            type: "POST",
            url: "{{ url('cidade')}}",
            data: formData,
            async: false,
            success: function (data) {
                changeUf(function(){
                    //alert(origemCidade);
                    $('#' + 'cidade_id').val(data);
                    $('#' + origemCidade).trigger('chosen:updated');
                    if(nomeBairro != '' && nomeBairro != undefined){
                        preencherBairro();
                    }
                },null);
                $('#btnCloseCidade').click();
            },
            error: function (data) {
                alert('erro');
            },
            cache: false,
            contentType: false,
            processData: false
        });
        return false;
    });
    $('form#fmBairro').submit(function(){
        //$('#grupo_id_bairro').val($('#grupo_id').val());
        $('#uf_bairro').val($('#uf').val());
        $('#cidade_id_bairro').val($('#cidade_id').val());
        var formData = new FormData($(this)[0]);
        $.ajax({
            type: "POST",
            url: "{{ url('bairro')}}",
            data: formData,
            async: false,
            success: function (data) {
                changeCidade(function(){
                    $('#bairro_id').val(data);
                    $('#bairro_id').trigger('chosen:updated');
                });
                $('#btnCloseBairro').click();
            },
            error: function (data) {
                console.log(data);
                alert('Erro ao incluir bairro');
            },
            cache: false,
            contentType: false,
            processData: false
        });
        return false;
    });
    $('#responsaveltipo_id').val('');
    @if($errors->any())
    carregarTelefonesErro();
    carregarEnderecoErro();
    @endif
});
//PARAMETROS: FUNCOES PARA ACHAR CIDADE E BAIRRO, APOS MONTAR AS SELECTS
function changeUf(callbackC, callbackB){
    if($('#uf').val() != ''){
        $.get("{{ url('cidade/dropdown')}}",
        { option: $('#uf').val() },
        function(data) {
            var cidade = $('#cidade_id');
            cidade.chosen("destroy");
            cidade.empty();
            $.each(data, function(index, element) {
                cidade.append("<option value='"+ element.id +"'>" + element.descricao + "</option>");
            });
            cidade.chosen({no_results_text: "nenhum registro encontrado.", width: "88%", placeholder_text_single: "Escolha a cidade"});
            if (typeof callbackC === "function") {
                callbackC();
            }
            changeCidade(callbackB);
        });
    }
}
function changeCidade(callbackB){
    if($('#cidade_id').val()!=''){
        $.get("{{ url('bairro/dropdown')}}",
        { option: $('#cidade_id').val() },
        function(data) {
            var bairro = $('#bairro_id');
            bairro.chosen("destroy");
            bairro.empty();
            $.each(data, function(index, element) {
                bairro.append("<option value='"+ element.id +"'>" + element.descricao + "</option>");
            });
            bairro.chosen({no_results_text: "nenhum registro encontrado.", width: "83%", placeholder_text_single: "Escolha o bairro"});
            if (typeof callbackB === "function") {
                callbackB();
            }
        });
    }
}
function addFone(){
    if(!isInt($('#telefonetipo_id').val())){
        bootbox.alert('Preencha o tipo de telefone.');
        return;
    }
    if($('#telefone').val().trim()==''){
        bootbox.alert('Preencha o telefone.');
        return;
    }
    tblFone.row.add( [
        $('#telefonetipo_id').val(),
        $('#telefonetipo_id option:selected').text(),
        $('#telefone').val(),
        "<button type='button' class='btn btn-danger small' id='btnRemoverTelefone'>Remover</button>"
    ] ).draw( false );
    $('#telefone').val('');
}
$('#tblTelefones').on( 'click', 'button', function () {
    var trElem = $(this).closest("tr");// grabs the button's parent tr element
    var firstTd = $(trElem).children("td")[0]; //takes the first td which would have your Id
    var telefone = $(trElem).children("td")[2];
    if($(firstTd).text() != ""){
        if($(this).context.id == 'btnRemoverTelefone'){
            tblFone
            .row( $(this).parents('tr') )
            .remove()
            .draw();
        }
    };
} );
function atualizarEndereco(dados){
    $("#endereco").val(dados.logradouro);
    $('#uf').val(dados.uf);
    $('#uf').trigger('chosen:updated');
    nomeCidade = dados.localidade.toUpperCase();
    nomeBairro = dados.bairro.toUpperCase();
    changeUf(function(){preencherCidade()},	function(){preencherBairro()});
    //setTimeout(function(){
    //	preencherCidade();
    //	setTimeout(function(){
    //		preencherBairro();
    //	},1200);
    //},1200);
}
function preencherCidade(){
    $('#cidade_id').val($('#cidade_id option').filter(function () { return $(this).html().toUpperCase() == nomeCidade }).val());
    $('#cidade_id').trigger('chosen:updated');
    setTimeout(function(){
        if($('#cidade_id').val() == null && nomeCidade != ''){
            bootbox.confirm({
                title: "Confirmação",
                message: "Cidade " + nomeCidade + " não encontrada. Deseja cadastrar?",
                buttons: {
                    cancel: {
                        label: "Não",
                        className: "btn-default pull-center"
                    },
                    confirm: {
                        label: "Sim",
                        className: "btn-danger pull-center"
                    }
                },
                callback: function(result) {
                    if (result) {
                        //alert('vou atualizar');
                        origemUF = 'uf';
                        $('#descricao_cidade').val(nomeCidade);
                        $('form#fmCidade').submit();
                    }
                }
            });
        }
    },600);
}
function preencherBairro(){
    $('#bairro_id').val($('#bairro_id option').filter(function () { return $(this).html().toUpperCase() == nomeBairro }).val());
    $('#bairro_id').trigger('chosen:updated');
    setTimeout(function(){
        if($('#bairro_id').val() == null && $('#cidade_id').val() != null && nomeBairro != ''){
            bootbox.confirm({
                title: "Confirmação",
                message: "Bairro " + nomeBairro + " não encontrado para esta cidade. Deseja cadastrar?",
                buttons: {
                    cancel: {
                        label: "Não",
                        className: "btn-default pull-center"
                    },
                    confirm: {
                        label: "Sim",
                        className: "btn-danger pull-center"
                    }
                },
                callback: function(result) {
                    if (result) {
                        //alert('vou atualizar');
                        $('#descricao_bairro').val(nomeBairro);
                        $('form#fmBairro').submit();
                    }
                }
            });
        }
    },600);
}
function buscarCEP(campoCidade, campoUF, campoEndereco, campoCEP){
    if($(campoUF).val() != null && $(campoCidade).val() != null && $(campoEndereco).val() != null) {
        $.ajax({
            type: "GET",
            url: "//viacep.com.br/ws/"+ $(campoUF).val() + "/" + $(campoCidade + " option:selected").text() + "/" + $(campoEndereco).val() + "/json",
            async: false,
            success: function (data) {
                if($.isArray(data) && data.length>0){
                    $('#tblCEP').dataTable( {
                        "language": { "url" : "{{URL::to('plugins/datatables/Portuguese-Brasil.json')}}"
                    },
                    "processing": false,
                    "bPaginate": true,
                    "bLengthChange": false,
                    "bFilter": false,
                    "bSort": true,
                    "bInfo": false,
                    "bAutoWidth": false,
                    "data": data,
                    "destroy": true,
                    "columns": [
                        { "data": "bairro" },
                        { "data": "cep" },
                        { "data": "complemento" },
                        { "data": "logradouro" },
                        { "data": "localidade" }
                    ]

                } );
                $('#tblCEP').on( 'click', 'tr', function () {
                    var trElem = $(this).closest("tr");
                    var cep = $(trElem).children("td")[1]; //takes the first td which would have your Id
                    if($(cep).text() != ""){
                        $('#btnCloseCEP').click();
                        //alert($(cep).text());
                        $(campoCEP).val($(cep).text());
                        $(campoCEP).focus();
                    };
                } );

                $('#popup_cep').modal('show');
            } else {
                bootbox.alert('CEP não encontrado. Verifique se a cidade e o endereço (no mínimo 3 caracteres) foram preenchidos.');
            }
        },
        error: function (data) {
            bootbox.alert('CEP não encontrado. Verifique se a cidade e o endereço (no mínimo 3 caracteres) foram preenchidos.');
        },
        cache: false,
        contentType: false,
        processData: false
    });
} else {
    bootbox.alert('Favor preencher cidade e endereço (no mínimo 3 caracteres) para buscar o CEP.');
}
}
function carregarTelefonesErro(){
    tblFone.clear();
    contatos = JSON.parse($('#telefones').val());
    for(i=0;i<contatos.length;i++){
        tblFone.row.add( [
            contatos[i][0],
            contatos[i][1],
            contatos[i][2],
            contatos[i][3]
        ] ).draw( false );
    }
    //console.log(contatos);
}
function carregarEnderecoErro(){
    $("#endereco").val('<?php echo Request::old('endereco'); ?>');
    $("#cep").val('<?php echo Request::old('cep'); ?>');
    $('#uf').val('<?php echo Request::old('uf'); ?>');
    $('#uf').trigger('chosen:updated');
    $('#numero').val('<?php echo Request::old('numero'); ?>');
    $('#complemento').val('<?php echo Request::old('complemento'); ?>');
    changeUf(function(){preencherCidadeErro()},	function(){preencherBairroErro()});
}
function preencherCidadeErro(){
    $('#cidade_id').val('<?php echo Request::old('cidade_id'); ?>');
    $('#cidade_id').trigger('chosen:updated');
}
function preencherBairroErro(){
    $('#bairro_id').val('<?php echo Request::old('bairro_id'); ?>');
    $('#bairro_id').trigger('chosen:updated');
}
var video = document.querySelector('video');
var canvas = document.querySelector('canvas');
var constraints = window.constraints = {
    audio: false,
    video: true
};
var errorElement = document.querySelector('#errorMsg');
function takePhoto() {
    if (window.stream) {
        var ctx = canvas.getContext('2d');
        //ctx.drawImage(video, 0, 0, 320, 240); // original draw image
        //ctx.drawImage(video, 0, 0, 640, 480, 0, 0, 320, 240); // entire image
        //instead of
        //ctx.drawImage(video, 70, 0, 180, 190, 0, 0, 140, 190);
        // we double the source coordinates
        //ctx.drawImage(video, 180, 80, 280, 380, 0, 0, 180, 240);
        ctx.drawImage(video, 140, 0, 360, 480, 0, 0, 360, 480);
        //document.querySelector('img').src = canvas.toDataURL('image/jpeg');
        document.getElementById('foto_capture').value = canvas.toDataURL('image/png');
        document.getElementById('foto_popup').src = canvas.toDataURL('image/png');
        document.getElementById('fotoImg').src = canvas.toDataURL('image/png');
    }
}
function startCapture(){
    navigator.mediaDevices.getUserMedia(constraints)
    .then(function(stream) {
        var videoTracks = stream.getVideoTracks();
        //console.log('Got stream with constraints:', constraints);
        //console.log('Using video device: ' + videoTracks[0].label);
        stream.onended = function() {
            //console.log('Stream ended');
        };
        window.stream = stream; // make variable available to browser console
        video.srcObject = stream;
    })
    .catch(function(error) {
        if (error.name === 'ConstraintNotSatisfiedError') {
            errorMsg('The resolution ' + constraints.video.width.exact + 'x' +
            constraints.video.width.exact + ' px is not supported by your device.');
        } else if (error.name === 'PermissionDeniedError') {
            errorMsg('Permissions have not been granted to use your camera and ' +
            'microphone, you need to allow the page access to your devices in ' +
            'order for the demo to work.');
        }
        errorMsg('getUserMedia error: ' + error.name, error);
    });
}
function errorMsg(msg, error) {
    errorElement.innerHTML += '<p>' + msg + '</p>';
    if (typeof error !== 'undefined') {
        console.error(error);
    }
}
function stopCapture() {
    video.pause();
    //window.stream.stop();
    var track = window.stream.getTracks()[0];  // if only one media track
    track.stop();
}
function mudarTipoPessoa(){
    if($('#tipopessoa_id').val()==1){
        $('#divPessoaFisica').show();
        $('#divPessoaFisica1').show();
        $('#divPessoaFisica2').show();
        $('#divPessoaJuridica').hide();
        $('#divPessoaJuridica1').hide();
        $("label[for='nome']").text("Nome:");
        $("label[for='fantasia']").text("Apelido:");
    }	else {
        $('#divPessoaFisica').hide();
        $('#divPessoaFisica1').hide();
        $('#divPessoaFisica2').hide();
        $('#divPessoaJuridica').show();
        $('#divPessoaJuridica1').show();
        $("label[for='nome']").text("Razão Social:");
        $("label[for='fantasia']").text("Fantasia:");
    }
}
</script>
@endsection

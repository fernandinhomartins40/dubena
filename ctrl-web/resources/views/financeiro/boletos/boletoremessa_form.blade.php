@extends('layouts.mainmenu')
@section('content')
<div id="divCadastro">
    <div class="row">
        <div class="col-xs-12">
            <div class="box-header">
                <div class="panel panel-default">
                    <div class="panel-heading">
                        <h3 class="box-title">Remessas de Boletos</h3>
                    </div>
                    <!-- /.box-header -->
                    <div class="nav-tabs-custom">
                        <ul class="nav nav-tabs">
                            <li class="active"><a href="#tab_1" data-toggle="tab">Remessas de Boletos</a></li>
                        </ul>
                        <div class="tab-content">
                           <div class="tab-pane active" id="tab_1">
                                <!-- form start -->
                                <div class="row">
                                    <div id="tabCadastro" class="col-sm-12">
                                        <div class="box-body">
                                                @if(isset($remessa))
                                                    {{ Form::model($remessa, array('id'=>'fmCadastro', 'method' => 'PATCH', 'class' => 'form-horizontal', 'files' => true, 'route' => array('remessa.update', $remessa->id))) }}
                                                @else
                                                    {{ Form::open(['id'=>'fmCadastro', 'route' => 'remessa.store', 'class' => 'form-horizontal', 'files' => true]) }}
                                                @endif   
                                                @if(!isset($show))
                                                    <div class="form-group crud_space">
                                                        {{ Form::label('datainicio', 'Data Início:', ['class'=>'col-sm-1 control-label input-sm']) }}
                                                        <div class="col-sm-2">
                                                            <div class="input-group generalDatePicker">
                                                                {{ Form::text('datainicio',null,['id' => 'datainicio','class'=>'form-control generalDatePicker input-sm']) }}
                                                                <span class="input-group-addon">
                                                                    <span class="glyphicon glyphicon-calendar"></span>
                                                                </span>    
                                                            </div>
                                                        </div>
                                                        {{ Form::label('datafim', 'Data Fim:', ['class'=>'col-sm-1 control-label input-sm']) }}
                                                        <div class="col-sm-2">
                                                            <div class="input-group generalDatePicker">
                                                                {{ Form::text('datafim',null,['id' => 'datafim','class'=>'form-control generalDatePicker input-sm']) }}
                                                                <span class="input-group-addon">
                                                                    <span class="glyphicon glyphicon-calendar"></span>
                                                                </span>    
                                                            </div>
                                                        </div>
                                                        {{ Form::label('conta_id', 'Conta:', ['class'=>'col-sm-1 control-label input-sm']) }}
                                                        <div class="col-sm-3">
                                                            @if(!isset($remessa))
                                                                {{Form::select('conta_id', $contas->pluck('descricao', 'id'), null, ['class' => 'selectChosen', 'id' => 'conta_id'])}}
                                                            @else
                                                                {{Form::select('conta_id', $contas->pluck('descricao', 'id'), null, ['class' => 'selectChosen', 'id' => 'conta_id', 'disabled'])}}
                                                            @endif
                                                        </div>  
                                                        @if(isset($conta))
                                                            <div class="sequencial">
                                                                {{ Form::label('boletoremessasequencia', 'Sequencial:', ['class'=>'col-sm-1 control-label input-sm']) }}
                                                                <div class="col-sm-1">
                                                                    {{Form::text('boletoremessasequencia', $conta->boletoremessasequencia, ['class' => 'form-control input-sm', 'disabled', 'id' => 'boletoremessasequencia'])}}
                                                                </div>    
                                                            </div>
                                                        @endif
                                                    </div>
                                                    <div class="form-group crud_space">
                                                        {{ Form::label('gerouremessa', 'Gerou Remessa:', ['class'=>'col-sm-1 control-label input-sm']) }}
                                                        <div class="col-sm-1 checkbox">
                                                            {{Form::checkbox('gerouremessa')}}
                                                        </div>     
                                                        {{ Form::label('ocorrencia_id', 'Ocorrência(s):', ['class'=>'col-sm-2 control-label input-sm']) }}
                                                        <div class="col-sm-6">
                                                            {{Form::select('ocorrencia_id', [], null, ['class' => 'selectChosen', 'multiple', 'id' => 'ocorrencia_id'])}}
                                                        </div>
                                                        <div class="col-sm 2">
                                                            <button type="button" id='btnLimpar' onclick="resetForm()" class="btn btn-sm btn-github" data-toggle='tooltip' data-trigger="hover" data-placement="bottom" title="Limpar"><span class="fa fa-recycle fa-lg"></span></button>
                                                            <!-- <button type="button" id='btnRemove' class="btn btn-nw-registro btn-sm" data-toggle='tooltip' data-trigger="hover" data-placement="bottom" title="Gerar PDF"><span class="fa fa-file-pdf-o fa-lg" aria-hidden="true"></span></button> -->
                                                            <button id="btnFiltro" type="button" class="btn btn-nw-buscas btn-sm" data-toggle='tooltip' data-trigger="hover" data-placement="bottom" title="Buscar"><span class="fa fa-search fa-lg"></span></button>
                                                        </div>
                                                    </div>
                                                @else
                                                    <div class="form-group crud_space">
                                                        {{ Form::label('numerosequencia', 'Nº Sequência:', ['class'=>'col-sm-1 control-label input-sm']) }}
                                                        <div class="col-sm-1">
                                                            {{Form::text('numerosequencia', null, ['class' => 'form-control input-sm', 'disabled', 'id' => 'numerosequencia'])}}
                                                        </div> 
                                                        {{ Form::label('conta_id', 'Conta:', ['class'=>'col-sm-1 control-label input-sm']) }}
                                                        <div class="col-sm-3">
                                                            {{Form::text('conta_id', $remessa->conta->descricao, ['class' => 'form-control input-sm', 'disabled', 'id' => 'gerouremessa'])}}
                                                        </div>    
                                                        {{ Form::label('gerouremessa', 'Gerou Remessa:', ['class'=>'col-sm-1 control-label input-sm']) }}
                                                        <div class="col-sm-1">
                                                            {{Form::text('gerouremessa', $remessa->gerouremessa ? 'Sim' : 'Não', ['class' => 'form-control input-sm', 'disabled', 'id' => 'gerouremessa'])}}
                                                        </div>  
                                                        {{ Form::label('cancelado', 'Cancelado:', ['class'=>'col-sm-1 control-label input-sm']) }}
                                                        <div class="col-sm-1">
                                                            {{Form::text('cancelado', $remessa->cancelado ? 'Sim' : 'Não', ['class' => 'form-control input-sm', 'disabled', 'id' => 'cancelado'])}}
                                                        </div>   
                                                        {{ Form::label('data', 'Data:', ['class'=>'col-sm-1 control-label input-sm']) }}
                                                        <div class="col-sm-1">
                                                            {{Form::text('data', requestDataOracle($remessa->datahora, false), ['class' => 'form-control input-sm', 'disabled', 'id' => 'cancelado'])}}
                                                        </div>   
                                                    </div>
                                                @endif
                                                {{Form::hidden('parsUrl', null, ['id' => 'parsUrl'])}}
                                                {{Form::hidden('boletosJson', null, ['id' => 'boletosJson'])}}
                                                @if(isset($boletos))
                                                    <div class="form-group crud_space margTop_30" style="max-width: 98%; margin-left: 2%">
                                                        <div class="col-sm-12">
                                                            <table id="tblBoletos" class="table table-bordered table-hover table-condensed">
                                                                <thead>
                                                                    <tr>
                                                                        <th class="hidden">Cód. Boleto</th>
                                                                        <th>Cód. Financeiro.</th>
                                                                        @if (isset($userem) && $userem)
                                                                            <th class="hidden">Cód. Parc.</th>
                                                                        @else
                                                                            <th>Cód. Parc.</th>
                                                                        @endif
                                                                        <th>Nosso Número</th>
                                                                        <th>Cliente/Info Cancelamento</th>
                                                                        <th>Data Boleto</th>
                                                                        @if (isset($userem) && $userem)
                                                                            <th class="hidden">Emissão Parc.</th>
                                                                        @else
                                                                            <th>Emissão Parc.</th>
                                                                        @endif
                                                                        <th>Vencto Parc.</th>
                                                                        <th>Valor</th>
                                                                        <th>Juros</th>
                                                                        <th>Multa</th>
                                                                        @if(!isset($show))
                                                                            <th>Remover</th>
                                                                        @endif
                                                                    </tr>
                                                                </thead>
                                                                <tbody>
                                                                    @if(!$errors->any())
                                                                        @foreach($boletos as &$boleto)
                                                                            <tr>
                                                                                <td class="hidden">{{$boleto->id}}</td>
                                                                                <td>{{$boleto->parcela_id}}</td>
                                                                                @if (isset($userem) && $userem)
                                                                                    <td class="hidden">{{$boleto->financeiroparcela_id}}</td>
                                                                                @else
                                                                                    <td>{{$boleto->financeiroparcela_id}}</td>
                                                                                @endif

                                                                                <td>{{$boleto->nossonumero . '-' . $boleto->dv}}</td>
                                                                                <td>{{is_null($boleto->parcela_id) ? @$boleto->info_cancelamento : $boleto->cliente}}</td>
                                                                                <td>{{requestDataOracle($boleto->datahora, false)}}</td>

                                                                                @if (isset($boleto->historico) && $boleto->historico == "true")
                                                                                    <td></td>
                                                                                    <td></td>
                                                                                    <td></td>
                                                                                    <td></td>
                                                                                    <td></td>
                                                                                @elseif (isset($boleto->remessa) && $boleto->remessa)
                                                                                    @if (isset($userem) && $userem)
                                                                                        <td class="hidden"></td>
                                                                                    @else
                                                                                        <td></td>
                                                                                    @endif
                                                                                    <td>{{requestDataOracle($boleto->datavencimento, false)}}</td>
                                                                                    <td>{{requestNumeroDecimalOracle($boleto->valor)}}</td>
                                                                                    <td>{{requestNumeroDecimalOracle($boleto->juros)}}</td>
                                                                                    <td>{{requestNumeroDecimalOracle($boleto->multa)}}</td>
                                                                                @else
                                                                                    <td>{{requestDataOracle($boleto->dataemissao, false)}}</td>
                                                                                    <td>{{requestDataOracle($boleto->datavencimento, false)}}</td>
                                                                                <!--alteração dia 09/07/18 porque na visualização de um boleto com cancelamento de abatimento não estava funcionando da maneira correta -->
                                                                                    @if($boleto->codigo !== "04")
                                                                                        <td>{{requestNumeroDecimalOracle($boleto->valor - $boleto->desconto + $boleto->valor_abatimento)}}</td>
                                                                                    @else
                                                                                        <td>{{requestNumeroDecimalOracle($boleto->valor - $boleto->desconto)}}</td>
                                                                                        <!--{{$boleto->valor_abatimento = 0}}-->
                                                                                    @endif
                                                                                    <td>{{requestNumeroDecimalOracle($boleto->juros)}}</td>
                                                                                    <td>{{requestNumeroDecimalOracle($boleto->multa)}}</td>
                                                                                @endif

                                                                                @if(!isset($show))
                                                                                    <td><button class="btn btn-nw-registro btn-xs" id="btnRemover" type="button">Remover</button></td>
                                                                                @endif
                                                                            </tr>
                                                                        @endforeach
                                                                    @endif
                                                                </tbody>
                                                            </table>
                                                        </div>
                                                    </div>
                                                    <div class="form-group crud_spac margTop_30">
                                                        <div class="col-sm-3" style="margin-left: 2%">
                                                            <i>Total de registros: <span id="totalBoletos">{{$errors->any() ? '' : count($boletos)}}</span></i>
                                                            <!--alteração dia 09/07/18 porque na visualização de um boleto com cancelamento de abatimento não estava funcionando da maneira correta-->
                                                            <i class="fright">Valor Total: <span id="totalValorBoletos">{{$errors->any() ? '' : requestNumeroDecimalOracle($boletos->sum('valor') - $boletos->sum('desconto') + $boletos->sum('valor_abatimento'))}}<span></i>
                                                        </div>
                                                    </div>
                                                    {{Form::hidden('inputTotalValorBoletos', null, ['class' => 'dinheiro', 'id' => 'inputTotalValorBoletos'])}}
                                                @endif
                                                @if(isset($remessa) || \Request::getQueryString() != '')
                                                    <div class="box-footer">
                                                        <div class="col-md-4">
                                                            @if(!isset($show))
                                                                {{ Form::submit('Gravar', ['class' => 'btn btn-nw-registro']) }}
                                                            @endif
                                                            <a href="{{url('remessa')}}" class="btn btn-nw-geral">Voltar</a>
                                                        </div>
                                                    </div>
                                                @endif
                                            {{ Form::close() }}
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div><!-- /.panel-default -->
            </div>
        </div>
    </div>
</div>
@include('general.modal_report_iframe')
<script type="text/javascript">
    var firstConta = "{{$contas->first() != null ? $contas->first()->id : 0}}";
    var parsUrl = "{{urlencode(Request::getQueryString())}}";
    var selecteds;
    @if($errors->any())
        var errors = true;
    @else
        var errors = false;
    @endif
    $(document).ready(function () {
       initTable();
       initFields();
       enableDisableSelects();
       if (!errors) {
           selecteds = setPrevSelecteds();
           changeConta();
       }
    });

    function initFields() {
        var $rem = $("#gerouremessa");
        if (!getParametro('conta_id')) {
            return;
        }
        $rem.prop('checked', getParametro('gerouremessa') === "1").trigger('checked');
        $("#datainicio").val(getParametro('datainicio') ? getParametro('datainicio') : dataAtual());
        $("#datafim").val(getParametro('datafim') ? getParametro('datafim') : dataAtual());
        $("#conta_id").val(getParametro('conta_id'));
    }

    $("#conta_id").on("change", function () {
        changeConta();
    });
    
    $("#gerouremessa").on('click', function () {
        enableDisableSelects();
    });

    $("#fmCadastro").on('submit', function (e) {
        var boletosJson = [];
        var conta_id = isNaN(parseInt($("#conta_id").val())) ? '' : $("#conta_id").val();
        if(isEmpty(conta_id)){
            bootbox.alert("Selecione a conta para gerar a remessa.");
            return false;
        }
        var data = tblBoletos.rows().data();
        if(data.length == 0){
            bootbox.alert("Ao menos 1 boleto deve existir para gerar a remessa.");
            return false;
        }
        $.each(tblBoletos.rows().data(), function (i, el) {
            var data = {
                boleto_id: el[0],
                financeiroparcela_id: el[1],
                parcela_id: el[2],
                nossonumero: el[3],
                cliente: el[4],
                datahoraboleto: el[5],
                dataemissao: el[6],
                datavencimento: el[7],
                valor: el[8],
                juros: el[9],
                multa: el[10],
                conta_id: conta_id
            };
            boletosJson.push(data);
        });
        $("#parsUrl").val(parsUrl);
        $("#boletosJson").val(JSON.stringify(boletosJson));
    });

    $("#tblBoletos").on('click', 'button', function () {
        var row = $(this).parents('tr');
        tblBoletos.row(row).remove().draw();
        calculaTotalTable();
    });

    $("#btnFiltro").on('click', function () {
        var url = root;

        @if(!isset($remessa))
            url += '/remessa/create?datainicio=:datainicio&datafim=:datafim&conta_id=:conta_id';
        @else
            url += '/remessa/{{$remessa->id}}/edit?datainicio=:datainicio&datafim=:datafim&conta_id=:conta_id';
        @endif
        url += '&gerouremessa=:gerouremessa&ocorrencia=:ocorrencia';

        var datainicio = $("#datainicio").val();
        var datafim = $("#datafim").val();
        var gerouremessa = typeof $("#gerouremessa:checked").val() !== 'undefined';
        var conta_id = $("#conta_id").intVal();
        var ocorrencia = $("#ocorrencia_id").val();
        if (isEmpty(datainicio)) {
            bootbox.alert('O campo Data Início é obrigatório');
            return;
        }

        if (isEmpty(datafim)) {
            bootbox.alert('O campo Data Fim é obrigatório');
            return;
        }

        if (isEmpty(conta_id)) {
            bootbox.alert('O campo Conta é obrigatório');
            return;
        }
        url = url.replace(':datainicio', datainicio);
        url = url.replace(':datafim', datafim);
        url = url.replace(':gerouremessa', gerouremessa ? 1 : 0);
        url = url.replace(':conta_id', conta_id);
        url = url.replace(':ocorrencia', gerouremessa && ocorrencia ? ocorrencia : 0);
        window.location.href = url;
    });

    function enableDisableSelects () {
        $("#ocorrencia_id").prop('disabled', typeof $('#gerouremessa:checked').val() === 'undefined').trigger('chosen:updated');
    }

    function resetForm () {
        $("#gerouremessa").prop('checked', false);
        enableDisableSelects();
        $("#datafim, #datainicio").val(dataAtual());

        @if(!isset($remessa))
            $(".sequencial").hide();
            $("#conta_id").val(firstConta);
        @endif
        $("#ocorrencia_id").val("");
        $(".selectChosen").trigger('chosen:updated');
        
        tblBoletos.clear().draw();
        calculaTotalTable();
    }

    function changeConta() {
        var conta_id = $("#conta_id").intVal();
        if (!conta_id) {
            return;
        }
        ajaxGenerator(root + '/api/getCodMovRemessaByConta/' + conta_id + '/0', "GET", function (data) {
            if ($.isArray(data) || typeof data === 'object') {
                var ocorrencias = '';
                $.each(data, function (i, el) {
                    ocorrencias += "<option value='" + el.id + "'>" + el.codigo + ' - ' + el.descricao + "</option>";
                });
                let $ocorr = $("#ocorrencia_id");
                $ocorr.html(ocorrencias);
                if ($.isArray(selecteds)) {
                    $.each(selecteds, function(i, e){
                        $ocorr.find("option[value='" + e + "']").prop("selected", true);
                console.log(e);
                    });
                }
                $ocorr.trigger('chosen:updated');
                selecteds = false;
            } else {
                bootbox.alert('' + data);
            }
        });
    }

    function initTable () {
        tblBoletos = $("#tblBoletos").DataTable({
            "language": {"url": urlDataTable},
            "processing": true,
            "bPaginate": false,
            "bLengthChange": false,
            "bFilter": false,
            "bSort": false,
            "bInfo": false,
            "bAutoWidth": false,
            "scrollY": '300',
            "aoColumnDefs": [
            {"bSortable": false, "aTargets": [0,1]},
            {"bVisible": false, "aTargets": [0,1]}
            ]
        }); 
        if(errors)
            carregarBoletosErro();
    }

    function carregarBoletosErro () {
        var boletos = JSON.parse($("#boletosJson").val());
        var btnRemover = '<button class="btn btn-nw-registro btn-xs" id="btnRemover" type="button">Remover</button>';
        $.each(boletos, function (i, el) {
            var d = [   el.boleto_id, 
                        el.financeiroparcela_id, 
                        el.parcela_id, 
                        el.nossonumero, 
                        el.cliente, 
                        el.datahoraboleto, 
                        el.dataemissao, 
                        el.datavencimento, 
                        el.valor, 
                        el.juros, 
                        el.multa, 
                        btnRemover
                    ];
            tblBoletos.row.add(d);
        });
        calculaTotalTable();
    }

    function calculaTotalTable() {
        var total = 0;
        var totalValor = 0;
        tblBoletos.rows().every(function() {
            var d = this.data();
            totalValor += floatFormatter(d[8], 2) + floatFormatter(d[9], 2) + floatFormatter(d[10], 2);
            total++;
        });
        $("#totalBoletos").text(total);
        totalValor = totalValor.toFixed(2).replace('.', ',');
        $("#inputTotalValorBoletos").val(totalValor);
        $('.dinheiro').each(function(){
            $(this).maskMoney('mask', $(this).val());
        })
        $("#totalValorBoletos").text($("#inputTotalValorBoletos").val());
    }

    function floatFormatter(value){
        return parseFloat(parseFloat(value.replace('R$ ', '').replace(/\./g, '').replace(',', '.')).toFixed(2));
    }

    function setPrevSelecteds() {
        var sel = getParametro('ocorrencia');
        if (!sel) {
            return false;
        }
        return sel.split(",");
    }
</script>
@endsection
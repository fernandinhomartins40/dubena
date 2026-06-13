@extends('layouts.mainmenu')
@section('content')
<style>
    .headerChart{
        font-size: 15px !important;
        font-weight: bold !important;
        display: block !important;
    }
</style>
<div id="divCadastro">
    <div class="row">
        <div class="col-xs-12">
            <div class="box-header">
                <div class="panel panel-default">
                    <div class="panel-heading">
                        <h3 class="box-title">Gráficos de Tempo de Entregas</h3>
                    </div>
                    <div class="nav-tabs-custom">
                        <ul class="nav nav-tabs">
                            <li class="active"><a href="#tab_1" data-toggle="tab">Gráficos de Tempo de Entregas</a></li>
                        </ul>
                        <div class="tab-content">
                            <div class="tab-pane active" id="tab_1">
                                <div class="row">
                                    <div id="tabCadastro" class="col-sm-12">
                                        <div class="box-body">
                                            {{ Form::open(['id' => 'fmFiltros','class'=>'form-horizontal'])}}
                                            <div class="form-group crud_space col-sm-10">
                                                {{ Form::label('year', 'Ano:', ['class'=>'col-sm-1 col-sm-offset-3 control-label input-sm']) }}
                                                <div class="col-sm-2">
                                                    {{ Form::select('year', $years, null, ['id' => 'year', 'class'=>'selectChosen']) }}
                                                </div>
                                                {{ Form::label('produto_id', 'Tipo de GLP:', ['class'=>'col-sm-1 control-label input-sm']) }}
                                                <div class="col-sm-2">
                                                    {{ Form::select('produto_id', $produtos, null, ['id' => 'produto_id', 'class'=>'selectChosen']) }}
                                                </div>
                                                <div class="col-sm-2">
                                                    <button id="btnFiltro" type="button" class="btn btn-nw-geral btn-sm" data-toggle='tooltip' data-trigger="hover" data-placement="bottom" title="Buscar Entregas"><span class="fa fa-search fa-lg"></span></button>
                                                    <button type="button" id='btnLimpar' class="btn btn-sm btn-github" data-toggle='tooltip' data-trigger="hover" data-placement="bottom" title="Limpar"><span class="fa fa-recycle fa-lg"></span></button>
                                                </div>
                                            </div> 
                                            {{ Form::close() }}
                                            <div class="form-group crud_space margTop_50">
                                                <div class="col-sm-10 col-sm-offset-1 divBoxChart">
                                                    <div class="box-title"> Tempo de Entrega Anual de Todos os Setores</div>
                                                        <br />
                                                        <div class="col-sm-6 margBottom_30">
                                                            <div class=" box-header with-border">
                                                                <p class="box-title headerChart text-center"> Entregas no Ano</p>
                                                            </div>
                                                            <div class="box-body" id="bodyY">
                                                                <canvas id="chartGeneralY"></canvas>
                                                            </div>
                                                        </div>
                                                        <div class="col-sm-6 margBottom_30">
                                                            <div class=" box-header with-border">
                                                                <p class="box-title headerChart text-center"> Entregas no Mês Atual</p>
                                                            </div>
                                                            <div class="box-body" id="bodyM">
                                                                <canvas id="chartGeneralM"></canvas>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="form-group crud_space margTop_50 divCharts divBoxChart">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<script src="{{asset('plugins/chartjs.min.js')}}"></script>
<script type="text/javascript">
    var tempoentregaConfig;
    var startYear = $("#year").val();
    var lastChart;
    $(document).ready(function () {
        tempoentregaConfig = parseInt("{{Session::get('empresa_config')->tempoentrega}}");
        if(isEmpty(tempoentregaConfig))
            bootbox.alert('Não há um tempo de entrega selecionado nas configurações da empresa.');
    });
    $("#btnLimpar").on('click', function() {
        $("#year").val(startYear).trigger('chosen:updated');
        $("#produto_id").val("").trigger('chosen:updated');
        clearCharts();
    });
    $("#btnFiltro").on('click', function () {
        if(isEmpty($("#year").val())){
            bootbox.alert('O campo Ano é obrigatório.');
            return;
        }
        searchPedidos($("#year").val(), $("#produto_id").val());
    });

    function searchPedidos (year, produto_id) {
        var url = root + '/api/searchPedidosTempoEntregas?year=' + year + "&prod=" + produto_id;
        clearCharts();
        lastChart = 0;
        ajaxGenerator(url, 'GET', function (data) {
            var passou = false;
            $.each(data.year, function (i, el) {
                passou = true;
                return;
            });
            $.each(data.month, function (i, el) {
                passou = true;
                return;
            });
            if(!passou) {
                bootbox.alert('Nenhum pedido encontrado para estes filtros.');
            } else {
                populateChartsYear(data.year);
                populateChartsMonth(data.month);
            }
        });
    }

    function clearCharts () {
        $(".divCharts").html('');
        $("#bodyY").html('<canvas id="chartGeneralY" class="divChartsGeneral"></canvas>');
        $("#bodyM").html('<canvas id="chartGeneralM" class="divChartsGeneral"></canvas>');
    }

    function populateChartsYear(data) {
       populateCharsGeneral(data, 'Y');
    }

    function populateChartsMonth(data) {
        populateCharsGeneral(data, 'M');
    }

    function populateCharsGeneral (data, type) {
        var tempo1 = 0;
        var tempo2 = 0;
        var tempo3 = 0;
        var tempo4 = 0;
        var tempo5 = 0;
        var tempoAll1 = 0;
        var tempoAll2 = 0;
        var tempoAll3 = 0;
        var tempoAll4 = 0;
        var tempoAll5 = 0;
        var passou = false;
        $.each(data, function(i, el){
            var tempoentrega = parseInt(el.tempoentrega);
            passou = true;
            tempo1 = !isNaN(parseInt(el.tempo1)) ? parseInt(el.tempo1) : 0;
            tempoAll1 += tempo1;
            tempo2 = !isNaN(parseInt(el.tempo2)) ? parseInt(el.tempo2) : 0;
            tempoAll2 += tempo2;
            tempo3 = !isNaN(parseInt(el.tempo3)) ? parseInt(el.tempo3) : 0;
            tempoAll3 += tempo3;
            tempo4 = !isNaN(parseInt(el.tempo4)) ? parseInt(el.tempo4) : 0;
            tempoAll4 += tempo4;
            tempo5 = !isNaN(parseInt(el.tempo5)) ? parseInt(el.tempo5) : 0;
            tempoAll5 += tempo5;

            var hasPedido = tempo1 > 0 || tempo2 > 0 || tempo3 > 0 || tempo4 > 0 || tempo5 > 0;
            if(hasPedido) {
                if(typeof $("#" + el.setor.split(" ").join("")).html() === "undefined") {
                    var html = '<div id="' + el.setor.split(" ").join("") + '" class="col-sm-8 col-sm-offset-2">';
                    html += '<hr /><div class="box-title"> Tempo de Entrega Anual do ' + el.setor + ' em ' + $("#year").val() + '</div><br />';
                    $(".divCharts").append(html);
                }

                var dataChart = [tempo1, tempo2, tempo3, tempo4, tempo5]
                if(type === 'Y') {
                    insertNewChartYear(el.setor, function () {
                        updateChart(dataChart);
                    });
                } else {
                    insertNewChartMonth(el.setor, function (idChart) {
                        updateChart(dataChart, idChart);
                    });
                }
            }
        });
        var dataChart = [tempoAll1, tempoAll2, tempoAll3, tempoAll4, tempoAll5];
        if(passou)
            updateChart(dataChart, "General" + type);
    }

    function insertNewChartMonth (setor, callback) {
        lastChart++;
        var html ='<div class="col-sm-6 margBottom_30">';
        html += '<div class=" box-header with-border">';
        html += '<div class="box-title headerChart text-center"> Entregas no Mês Atual</div></div>';
        html += '<div class="box-body"><canvas id="chart' + setor.split(" ").join("") + '"></canvas> </div></div></div>';
        $("#" + setor.split(" ").join("")).append(html);
        if(typeof callback == 'function')
            callback(setor);
    }

    function insertNewChartYear(setor, callback) {
        lastChart++;
        var html = '<div class="col-sm-6 margBottom_30">';
        html += '<div class=" box-header with-border">';
        html += '<div class="box-title headerChart text-center"> Entregas no Ano</div></div>';
        html += '<div class="box-body"><canvas id="chart' + lastChart + '"></canvas> </div></div></div>';
        $("#" + setor.split(" ").join("")).append(html);
        if(typeof callback == 'function')
            callback();
    }

    function updateChart (data, idChart) {
        if(idChart != "GeneralY" && idChart != "GeneralM")
            idChart = typeof idChart === 'undefined' ? lastChart : idChart.split(" ").join("");
        var divChart = document.getElementById("chart" + idChart).getContext("2d");
        var background = ["#75B0FD", "#B7EF56","#F4FD2B","#FDB72C","#D54A40"];
        var datasets = [{ backgroundColor: background, data: data }];
        var labels = [" < 15m", " 15-30m", " 30-45m", " 45-60m", " > 60m"];
        chart = new Chart(divChart, {
                type: 'pie', 
                data: {
                      labels: labels,
                      datasets: datasets
                    }, 
                options: {
                    title: {
                        text: 'Entregas no Ano.'
                    }
                }
            });
    }
</script>
@endsection
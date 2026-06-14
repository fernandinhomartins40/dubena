
@extends('monitora.layouts.mainmenu')

@section('content')
<style>

</style>
<div id="mainContent" class="content">
    <div id="divCadastro">
        <div class="row">
            <div class="col-md-6 col-md-offset-3">
                <div class="box-header">
                    <h3 class="box-title">Relatório de Follow Up</h3>
                </div><!-- /.box-header -->
                <div class="box">
                    <div class="box-body">
                        <div class="col-md-12">
                            <div class="form-group crud_space col-sm-12">
                                {!! Form::label('datainicial', 'Data Início:', ['class'=>'col-sm-3 control-label input-sm']) !!}
                                <div class="col-sm-4">
                                    <div class="input-group date" id="datetimepicker1">
                                        {!! Form::text('datainicial',null,['class'=>'form-control input-sm']) !!}
                                        <span class="input-group-addon">
                                            <span class="glyphicon glyphicon-calendar"></span>
                                        </span>
                                    </div>
                                </div>
                            </div>
                            <div class="form-group crud_space col-sm-12">
                                {!! Form::label('datafinal', 'Data Término:', ['class'=>'col-sm-3 control-label input-sm']) !!}
                                <div class="col-sm-4">
                                    <div class="input-group date" id="datetimepicker2">
                                        {!! Form::text('datafinal',null,['class'=>'form-control input-sm']) !!}
                                        <span class="input-group-addon">
                                            <span class="glyphicon glyphicon-calendar"></span>
                                        </span>
                                    </div>
                                </div>
                            </div>
                            <div class="form-group crud_space col-sm-12">
                                {!! Form::label('tipos_list', 'Tipos:', ['class'=>'col-sm-3 control-label input-sm']) !!}
                                <div class="col-sm-9">
                                    {!! Form::select('tipos_list[]',$tipos, [],['id'=>'tipos_list','class'=>'form-control input-sm', 'multiple', 'style' => 'width:100%;']) !!}
                                </div>
                            </div>
                            <div class="form-group crud_space col-sm-12">
                                {!! Form::label('situacaos_list', 'Situações:', ['class'=>'col-sm-3 control-label input-sm']) !!}
                                <div class="col-sm-9">
                                    {!! Form::select('situacaos_list[]',$situacaos, [],['id'=>'situacaos_list','class'=>'form-control input-sm', 'multiple', 'style' => 'width:100%;']) !!}
                                </div>
                            </div>
                            <div class="form-group crud_space  col-sm-12">
                                {!! Form::label('cliente_id', 'Cliente:', ['class'=>'col-sm-3 control-label input-sm']) !!}
                                <div class="col-sm-9">
                                    @if(isset($cliente))
                                    <select id="searchbox" name="cliente_id" placeholder="Buscar cliente" class="form-control" style="float:left;width:100%;" value="" data-selectize-value = '[{"id":{{$cliente->id}},"nome":"{{$cliente->nome}}"}]'></select>
                                    @else
                                    <select id="searchbox" name="cliente_id" placeholder="Buscar cliente" class="form-control" style="float:left;width:100%;" value="" data-selectize-value = '[]'></select>
                                    @endif
                                </div>
                            </div>
                            <div class="form-group crud_space  col-sm-12">
                                {!! Form::label('centrocusto_id', 'C.Custo:', ['class'=>'col-sm-3 control-label input-sm']) !!}
                                <div class="col-sm-9">
                                    {{Form::hidden('centrocusto_id',$centrocusto_id, ['id'=>'centrocusto_id'])}}
                                    <div class="input-group">
                                        {!! Form::text('centrocusto_descricao',$centrocusto_descricao,['id'=>'centrocusto_descricao', 'class'=>'form-control input-sm', 'disabled']) !!}
                                        <span class="input-group-btn">
                                            <button type="button" class="btn btn-nw-buscas form-control input-sm" id="btnCcusto" onclick="abrirCentroCusto();">Mudar</button>
                                        </span>
                                    </div>
                                </div>
                            </div>
                            <div class="form-group crud_space  col-sm-12">
                                {!! Form::label('planoconta_id', 'P.Conta:', ['class'=>'col-sm-3 control-label input-sm']) !!}
                                <div class="col-sm-9">
                                    {{Form::hidden('planoconta_id',$planoconta_id, ['id'=>'planoconta_id'])}}
                                    <div class="input-group">
                                        {!! Form::text('planoconta_descricao',$planoconta_descricao,['id'=>'planoconta_descricao', 'class'=>'form-control input-sm', 'disabled']) !!}
                                        <span class="input-group-btn">
                                            <button type="button" class="btn btn-nw-buscas form-control input-sm" id="btnPconta" onclick="abrirPlanoConta();">Mudar</button>
                                        </span>
                                    </div>
                                </div>
                            </div>

                        </div>
                    </div><!-- /.box-body -->
                </div><!-- /.box -->
                <div class="row">
                    <div class="col-md-12">
                        <div class="col-md-5">
                            <button type="button" id="btnPrint" class="btn btn-nw-registro" onclick="imprimir();">PDF</button>
                            <button type="button" id="btnPrint" class="btn btn-excel" onclick="gerarXls();">Excel</button>
                        </div>
                    </div>
                </div>
            </div><!-- /.col -->
        </div><!-- /.row -->

    </div>
</div>
<meta name="csrf-token" content="{{ csrf_token() }}" />
<script src="{{URL::to('plugins/tree-multiselect.min.js')}}"></script>
<script src="{{URL::to('plugins/jstree/dist/jstree.min.js')}}"></script>
<script src="{{URL::to('plugins/bootstrap-multiselect/bootstrap-multiselect.js')}}"></script>

<!-- page script -->
@include('monitora.financeiro.centrocustos_partial1_js')
@include('monitora.financeiro.planocontas_partial1_js')
<script type="text/javascript">

                            var operacao = "";
                            $(document).ready(function () {
                                $('#datetimepicker1').datetimepicker({
                                    locale: 'pt-br',
                                    viewMode: 'days',
                                    format: 'DD/MM/YYYY'
                                });
                                $('#datetimepicker2').datetimepicker({
                                    locale: 'pt-br',
                                    viewMode: 'days',
                                    format: 'DD/MM/YYYY'
                                });
                                $('#tipos_list').multiselect({
                                    includeSelectAllOption: false,
                                    enableFiltering: false,
                                    allSelectedText: "Todos selecionados",
                                    nonSelectedText: 'Selecione os tipos'
                                });
                                $('#situacaos_list').multiselect({
                                    includeSelectAllOption: false,
                                    enableFiltering: false,
                                    allSelectedText: "Todas selecionadas",
                                    nonSelectedText: 'Selecione as situações'
                                });
                                $('#searchbox').selectize({
                                    valueField: 'id',
                                    labelField: 'nome',
                                    searchField: ['nome'],
                                    maxOptions: 10,
                                    options: [],
                                    create: false,
                                    render: {
                                        option: function (item, escape) {
                                            return '<div>' + escape(item.nome) + '</div>';
                                        }
                                    },
                                    optgroups: [
                                        {value: 'cliente', label: 'Clientes'},
                                    ],
                                    optgroupField: 'class',
                                    optgroupOrder: ['cliente'],
                                    load: function (query, callback) {
                                        if (!query.length)
                                            return callback();
                                        $.ajax({
                                            url: '{{url("/")}}/api/searchCliente',
                                            type: 'GET',
                                            dataType: 'json',
                                            data: {
                                                q: query
                                            },
                                            error: function () {
                                                callback();
                                            },
                                            success: function (res) {
                                                console.log(res);
                                                callback(res.data);
                                            }
                                        });
                                    },
                                    onChange: function () {
                                        //alert('aqui');
                                        //preencheDadosCliente($('#searchbox').selectize()[0].selectize.getValue());
                                        //console.log($('#searchbox').selectize()[0].selectize.getItem(this.items[0]).context.innerText);
                                    }, onInitialize: function () {
                                        var existingOptions = JSON.parse(this.$input.attr('data-selectize-value'));
                                        var self = this;
                                        if (Object.prototype.toString.call(existingOptions) === "[object Array]") {
                                            existingOptions.forEach(function (existingOption) {
                                                self.addOption(existingOption);
                                                self.addItem(existingOption[self.settings.valueField]);
                                            });
                                        } else if (typeof existingOptions === 'object') {
                                            self.addOption(existingOptions);
                                            self.addItem(existingOptions[self.settings.valueField]);
                                        }
                                    }
                                });
                            });
                            function imprimir() {
                                var url = '{{ route("monitora.report.FollowUp", ":par") }}';
                                url = url.replace(':par', $('#datainicial').val().replace(/\//g, '-') + '|' + $('#datafinal').val().replace(/\//g, '-') + '|' + $('#tipos_list').val() + '|' + $('#situacaos_list').val());
                                window.open(url, '_blank');
                            }
                            function gerarXls() {
                                var url = '{{ route("monitora.report.FollowUpXls", ":par") }}';
                                url = url.replace(':par', $('#datainicial').val().replace(/\//g, '-') + '|' + $('#datafinal').val().replace(/\//g, '-') + '|' + $('#tipos_list').val() + '|' + $('#situacaos_list').val());
                                window.open(url, '_self');
                            }
</script>
@include('monitora.financeiro.centrocustos_partial2_js')
@include('monitora.financeiro.planocontas_partial2_js')
</div><!-- /.content-wrapper -->
@include('monitora.financeiro.centrocustos_partial1')
@include('monitora.financeiro.planocontas_partial1')

@endsection

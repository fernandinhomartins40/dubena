@extends('layouts.mainmenu')

@section('content')

<div id="divCadastro">
    <div class="row">
        <div class="col-xs-12">
            <div class="box-header">
                <div class="panel panel-default">
                    <div class="panel-heading">
                        <h3 class="box-title">Baixa do PIX</h3>
                    </div>
                    <!-- /.box-header -->
                    <div class="nav-tabs-custom">
                        <ul class="nav nav-tabs">
                            <li class="active"><a href="#tab_1" data-toggle="tab">Baixa do PIX</a></li>
                        </ul>
                        <div class="tab-content">
                            {{Form::open(['id' => 'fmParcelas', 'method' => 'GET', 'class' => 'form-horizontal'])}}
                            <div class="tab-pane active" id="tab_1">
                                <!-- form start -->
                                <div class="row">
                                    <div id="tabCadastro" class="col-sm-12">
                                        <div class="box-body">
                                            <div class="form-group crud_space" style="margin-left: 1.5%">
                                                {{ Form::label('datainicio', 'Data início:', ['class'=>'col-sm-1 control-label input-sm','style'=>'text-align:right;'])}}
                                                <div class="col-sm-2">
                                                    <div class="input-group date generalDatePicker" id="datetimepicker1">
                                                        {{ Form::datetime('datainicio',null,['id'=>'datainicio','class'=>'form-control input-sm generalDatePicker']) }}
                                                        <span class="input-group-addon">
                                                            <span class="glyphicon glyphicon-calendar"></span>
                                                        </span>
                                                    </div>
                                                </div>
                                                {{ Form::label('datafim', 'Data fim:', ['class'=>'col-sm-1 control-label input-sm','style'=>'text-align:right;'])}}
                                                <div class="col-sm-2">
                                                    <div class="input-group date generalDatePicker" id="datetimepicker1">
                                                        {{ Form::datetime('datafim',null,['id'=>'datafim','class'=>'form-control input-sm generalDatePicker']) }}
                                                        <span class="input-group-addon">
                                                            <span class="glyphicon glyphicon-calendar"></span>
                                                        </span>
                                                    </div>
                                                </div>
                                                <div class="col-sm-2">
                                                    <button type="button" id='btnLimpar' class="btn btn-sm btn-github" data-toggle='tooltip'
                                                        onclick="window.location.href = '{{route('pix.index')}}'"
                                                        data-trigger="hover" data-placement="bottom" title="Limpar">
                                                            <span class="fa fa-recycle fa-lg"></span>
                                                    </button>
                                                    <button type="button" id='btnBuscar' class="btn btn-sm btn-nw-registro" data-toggle='tooltip'
                                                        data-trigger="hover" data-placement="bottom" title="Buscar Parcelas">
                                                            <span class="fa fa-search fa-lg"></span>
                                                    </button>
                                                </div>
                                            </div>
                                            <div class="form-group crud_space" style="margin-left: 1.5%">
                                                <div class="col-sm-12">
                                                    <table class="table no-select table-bordered table-condensed" style="font-size: 13.5px;" id="tblParcelas">
                                                        <thead>
                                                            <tr>
                                                                <th>Cód. Parcela</th>
                                                                <th>Cliente</th>
                                                                <th>Data Venda</th>
                                                                <th>Data Pagamento</th>
                                                                <th>Valor</th>
                                                                <th>Valor Pago</th>
                                                                <th>Transaction ID</th>
                                                                <th>End To End ID</th>
                                                                <th>Status</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            @if (isset($transactions))
                                                                @foreach ($transactions as $transaction)
                                                                    <tr>
                                                                        <td>{{ $transaction->parcela_id }}</td>
                                                                        <td>{{ $transaction->cliente }}</td>
                                                                        <td>{{ $transaction->venda }}</td>
                                                                        <td>{{ $transaction->pagamento }}</td>
                                                                        <td>{{ $transaction->valor }}</td>
                                                                        <td>{{ $transaction->valorpago }}</td>
                                                                        <td>{{ $transaction->txid }}</td>
                                                                        <td>{{ $transaction->endtoendid }}</td>
                                                                        <td>{{ $transaction->status  }}</td>
                                                                    </tr>
                                                                @endforeach
                                                            @endif
                                                        </tbody>
                                                    </table>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="box-footer">
                                <div class="col-md-4">
                                    @if(isset($transactions) && count($transactions) > 0)
                                        {{Form::submit('Continuar', ['class' => 'btn btn-nw-registro'])}}
                                    @endif
                                </div>
                            </div>
                            {{Form::close()}}
                        </div>
                    </div>
                </div><!-- /.panel-default -->
            </div>
        </div>
    </div>
</div>

<script type="text/javascript" src="{{URL::to('js/pixtransaction.js')}}"></script>

@endsection

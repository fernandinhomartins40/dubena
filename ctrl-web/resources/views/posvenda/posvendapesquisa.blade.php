 @extends('layouts.mainmenu') @section('content')
<div id="mainContent" class="content">
    <div id="divCadastro" class="row">
        <div class="col-md-12">
            <ul>
                <div class="panel panel-default">
                    <div class="header panel-default">
                        <div class="panel-heading">
                            <h3 class="panel-title">Pesquisa de Pós-Venda</h3>
                        </div>
                    </div>
                    <!-- /.box-header -->
                    <div class="nav-tabs-custom">
                        <ul class="nav nav-tabs">
                            <li class="active"><a href="#tab_1" data-toggle="tab">Pós-Venda</a></li>
                        </ul>
                        <div class="tab-content">
                            <div class="tab-pane active" id="tab_1">
                                <!-- form start -->
                                <div class="row">
                                    <div id="tabCadastro" class="col-md-12">
                                        <div class="box-body">
                                            {{ Form::open(['id'=>'fmCadastro', 'class' => 'form-horizontal', 'files' => true]) }}
                                            <div class="form-group crud_space">
                                                {{ Form::label('datainicio', 'Data inicial:', ['class'=>'col-sm-2 control-label input-sm'])}}
                                                <div class="col-sm-2">
                                                    <div class="input-group date generalDateTimePicker" id="datetimepicker1">
                                                        {{ Form::datetime('datainicio',null,['id'=>'datainicio','class'=>'form-control input-sm generalDateTimePicker']) }}
                                                        <span class="input-group-addon">
                                                            <span class="glyphicon glyphicon-calendar"></span>
                                                        </span>
                                                    </div>
                                                </div>
                                                {{ Form::label('datafim', 'Data Final:', ['class'=>'col-sm-1 control-label input-sm']) }}
                                                <div class="col-sm-2">
                                                    <div class="input-group date generalDateTimePicker" id="datetimepicker1">
                                                        {{ Form::datetime('datafim',null,['id'=>'datafim','class'=>'form-control input-sm generalDateTimePicker']) }}
                                                        <span class="input-group-addon">
                                                            <span class="glyphicon glyphicon-calendar"></span>
                                                        </span>
                                                    </div>
                                                </div>
                                                {{ Form::label('datahoraatual', 'Data Hora Pesquisa:', ['class'=>'col-sm-2 control-label input-sm'])}}
                                                <div class="col-sm-2">
                                                    <div class="input-group date generalDateTimePicker" id="datetimepicker1">
                                                        {{ Form::datetime('datahoraatual',null,['id'=>'datahoraatual','class'=>'form-control input-sm generalDateTimePicker','readonly'])}}
                                                        <span class="input-group-addon">
                                                            <span class="glyphicon glyphicon-calendar"></span>
                                                        </span>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="form-group crud_space">
                                                {{ Form::label('setor', 'Setores:', ['class'=>'col-sm-2 control-label input-sm']) }}
                                                <div class="col-sm-2" style="margin-right:10px;">
                                                    {{ Form::select('setor',$setores, null, ['id'=>'setor', 'class' => 'form-control selectChosen']) }}
                                                </div>
                                                {{ Form::label('colaborador', 'Colaboradores:', ['class'=>'col-sm-1 control-label input-sm'])}}
                                                <div class="col-sm-2">
                                                    {{ Form::select('colaborador',$colaborador, null, ['id'=>'colaborador', 'class' => 'form-control selectChosen input-sm'])}}
                                                </div>
                                                <div class="col-sm-3">
                                                    <button type="button" id='btnLimpar' onclick="window.location.href = '{{route('posvenda.index')}}'" class="btn btn-sm btn-nw-geral" data-toggle='tooltip' data-trigger="hover" data-placement="bottom" title="Limpar"><span class="fa fa-recycle fa-lg"></span></button>
													<button id="btnFiltrar" type="button" class="btn btn-nw-buscas btn-sm" data-toggle='tooltip' data-trigger="hover" data-placement="bottom" title="Buscar Formulários"><span class="fa fa-search fa-lg"></span></button>
                                                </div>
                                                {{Form::close()}}
                                            </div>
                                            <div class="form-group crud_space">
                                                <div class="col-md-10 col-md-push-1">
                                                    <table id="tblCadastroPosVenda" url="{{route('posvenda.create','pedido=:id&dataatual=:dataatual')}}" btnclick="false" class="table table-bordered table-hover table-condensed">
                                                        <thead>
                                                            <tr>
                                                                <th>Cód. Pedido</th>
                                                                <th>Data/Hora</th>
                                                                <th>Cliente</th>
                                                                <th>Telefones</th>
                                                                <th>Status</th>
                                                                <th>Valor Venda</th>
                                                                <th>Pagamento</th>
                                                                <th>Setor</th>
                                                                <th>Endereço</th>
                                                                <th>Pós-Venda</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody id="checklists-list" name="checklist-list">
                                                            @if(isset($clientes)) 
                                                                @foreach($clientes as $cliente)
                                                                    @if(!is_null($cliente->posvenda))
                                                                        <tr class="linhaselecionada">
                                                                    @else
                                                                        <tr class="">
                                                                    @endif
                                                                        <td>{{$cliente->pedido_id}}</td>
                                                                        <td>{{$cliente->data}}</td>
                                                                        <td>{{$cliente->cliente}}</td>
                                                                        <td>{{$cliente->telefone}}</td>
                                                                        <td>{{$cliente->status}}</td>
                                                                        <td>{{$cliente->valor}}</td>
                                                                        <td>{{$cliente->condicao}}</td>
                                                                        <td>{{$cliente->setor}}</td>
                                                                        <td>{{$cliente->endereco}}</td>
                                                                        <td>{{$cliente->posvenda}}</td>
                                                                    </tr>
                                                                @endforeach 
                                                            @endif
                                                        </tbody>
                                                    </table>
                                                </div>
                                            </div>
                                        </div><!-- /.box-body -->
                                    </div><!-- /.box -->
                                </div><!-- /.col -->
                            </div><!-- /.row -->
                        </div><!-- /.content-wrapper -->
                    </div>
                </div>
            </ul>
        </div>
    </div>
</div>
<script type="text/javascript" src="{{URL::to('js/posvendapesquisa.js')}}"></script>
@endsection
@extends('layouts.mainmenu') 
@section('content')
<div id="mainContent" class="content">
    <div id="divCadastro" class="row">
        <div class="col-md-12">
            @if(isset($inventario))
                {{ Form::model($inventario, array('id'=>'fmCadastro', 'method' => 'PATCH', 'class' => 'form-horizontal','files' => true, 'route' => array('inventario.update', $inventario->id))) }}
            @else 
                {{ Form::open(['id'=>'fmCadastro','route' => 'inventario.store', 'class' => 'form-horizontal', 'files' => true]) }} 
            @endif
            <ul>
                <div class="panel panel-default">
                    <div class="panel-heading">
                        <h3 class="panel-title">Controle de Inventário</h3>
                    </div>
                    <div class="nav-tabs-custom">
                        <ul class="nav nav-tabs">
                            <li class="active"><a href="#tab_1" data-toggle="tab">Informações Gerais</a></li>
                        </ul>
                        <div class="tab-content">
                            <div class="tab-pane active" id="tab_1">
                                <!-- form start -->
                                <div class="row">
                                    <div id="tabCadastro" class="col-md-12">
                                        <div class="box-body">
                                            <div class="col-md-12">
                                                <div class="form-group crud_space">
                                                    {{Form::label('datainventario', 'Data:', ['class' => 'input-sm control-label col-sm-2'])}}
                                                    <div class="col-sm-2">
                                                        <div class="input-group generalDatePicker">
                                                            {{Form::text('datainventario', null, ['id' => 'datainventario', 'class' => 'input-sm form-control generalDatePicker'])}}
                                                            <span class="input-group-addon">
                                                                <span class="glyphicon glyphicon-calendar"></span>
                                                            </span>
                                                        </div>
                                                    </div>
                                                    {{Form::label('mesentrega', 'Mês de Entrega:', ['class' => 'input-sm control-label col-sm-2'])}}
                                                    <div class="col-sm-2">
                                                        <div class="input-group generalDateMesAno">
                                                            {{Form::text('mesentrega', null, ['id' => 'mesentrega', 'class' => 'input-sm form-control generalDateMesAno'])}}
                                                            <span class="input-group-addon">
                                                                <span class="glyphicon glyphicon-calendar"></span>
                                                            </span>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="form-group crud_space">
                                                    {{ Form::label('produto_id', 'Produto:', ['class'=>'col-sm-2 control-label input-sm']) }}
                                                    <div class="col-sm-2">
                                                        {{ Form::select('produto_id', $produtos, null, ['id'=>'produto_id', 'class' => 'form-control selectChosen']) }}
                                                    </div>
                                                    {{ Form::label('valorunitario', 'Valor Unitário:', ['class'=>'col-sm-1 control-label input-sm']) }}
                                                    <div class="col-sm-1">
                                                        {{ Form::text('valorunitario', null, ['id'=>'valorunitario', 'class' => 'form-control input-sm dinheiro']) }}
                                                    </div>
                                                    {{ Form::label('quantidade', 'Quantidade:', ['class'=>'col-sm-1 control-label input-sm']) }}
                                                    <div class="col-sm-1">
                                                        {{ Form::text('quantidade', null, ['id'=>'quantidade', 'class' => 'form-control input-sm number']) }}
                                                    </div>
                                                    <button id="addProduto" type="button" class="btn btn-xs btn-nw-buscas" data-toggle='tooltip' data-trigger="hover" data-placement="bottom" title="Adicionar Produto">Adicionar</button>
                                                </div>
                                                <div class="form-group crud_space">
                                                    <div class="col-md-9 col-sm-offset-1">
                                                        {{Form::hidden('produtos',null,['id'=>'produtos'])}}
                                                        <table id="tblInventarioItems" class="table table-bordered table-condensed table-hover table-striped bg-success">
                                                            <thead>
                                                                <tr>
                                                                    <th>id</th>
                                                                    <th>Produto</th>
                                                                    <th>Valor Unitário</th>
                                                                    <th>Quantidade</th>
                                                                    <th>Valor Total</th>
                                                                    <th>Operação</th>
                                                                </tr>
                                                            </thead>
                                                            <tbody>
                                                                @if(isset($items) && count($items) > 0)
                                                                    @foreach($items as $item)
                                                                        <tr>
                                                                            <td>{{$item->produto_id}}</td>
                                                                            <td>{{$item->descricao}}</td>
                                                                            <td>{{requestNumeroDecimalOracle($item->valorunitario)}}</td>
                                                                            <td>{{$item->quantidade}}</td>
                                                                            <td>{{requestNumeroDecimalOracle($item->total)}}</td>
                                                                            <td>
                                                                                <button class="btn btn-xs btn-nw-registro" id="btnRemover" data-toggle="tooltip" data-trigger="hover" data-placement="bottom" title="Remover Empresa">Remover</button>
                                                                            </td>
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
                            </div>
                        </div>
                        <div class="box-footer" style="margin-top: -30px">
                            <div class="col-md-4">
                                {!! Form::submit('Gravar', ['id'=>'btnGravar','class' => 'btn btn-nw-registro']) !!}
                                <a type="button" href="{{url('inventario')}}" class="btn btn-nw-geral">Voltar</a>
                            </div>
                        </div>
                    </div>
                </div>
            </ul>
            {{Form::close()}}
        </div>
    </div>
</div>
<script type="text/javascript" src="{{URL::to('js/inventario.js')}}"></script>
<script type="text/javascript">
    @if($errors->any())
        $( window ).load( function() {
            errorsFix();
        });
    @endif
    @if(isset($show))
        desativarInputs();
    @endif
</script>

@endsection
@extends('layouts.mainmenu') @section('content')
<div id="mainContent" class="content">
    <div id="divCadastro" class="row">
        <div class="col-md-12">
            @if(isset($atualizacao))
                {{ Form::model($atualizacao, array('id'=>'fmCadastro', 'method' => 'PATCH', 'class' => 'form-horizontal','files' => true, 'route' => array('atualizarprecos.update', $atualizacao->id))) }} 
            @else 
                {{ Form::open(['id'=>'fmCadastro','route' =>'atualizarprecos.store', 'class' => 'form-horizontal', 'files' => true]) }} 
            @endif
            <ul>
                <div class="panel panel-default">
                    <div class="panel-heading">
                        <h3 class="panel-title">Atualização de Preços</h3>
                    </div>
                    <div class="nav-tabs-custom">
                        <ul class="nav nav-tabs">
                            <li class="active">
                                <a href="#tab_1" data-toggle="tab">Informações Gerais</a>
                            </li>
                        </ul>
                        <div class="tab-content">
                            <div class="tab-pane active" id="tab_1"><!-- form start -->
                                <div class="row">
                                    <div id="tabCadastro" class="col-md-12">
                                        <div class="box-body">
                                            <div class="col-md-12">
                                                <div class="form-group crud_space">
                                                    <div id="selects">
                                                        {{ Form::label('setor_id', 'Setor:', ['class'=>'col-sm-1 control-label input-sm']) }}
                                                        <div class="col-sm-2">
                                                            {{ Form::select('setor_id', $setor, null, ['id'=>'seto_id', 'class' => 'form-control selectChosen']) }}
                                                        </div>
                                                        {{ Form::label('tipopessoa_id', 'Tipo de Pessoa:', ['class'=>'col-sm-1 control-label input-sm']) }}
                                                        <div class="col-sm-2">
                                                            {{ Form::select('tipopessoa_id', $tipo, null, ['id'=>'tipopessoa_id', 'class' => 'form-control selectChosen']) }}
                                                        </div>
                                                        {{ Form::label('segmento_id', 'Segmento:', ['class'=>'col-sm-1 control-label input-sm']) }}
                                                        <div class="col-sm-2">
                                                            {{ Form::select('segmento_id', $segmento, null, ['id'=>'segmento_id', 'class' => 'form-control selectChosen']) }}
                                                        </div>
                                                    </div>
                                                    <div id="descricao" class='hidden col-sm-5 col-sm-offset-3'>
                                                        <p style="font-style:italic;font-weight:bold;"><span id="filtrosusados">{{@$atualizacao->descricao}}</span></p>
                                                    </div>
                                                </div>
                                                <div class="form-group crud_space">
                                                    {{ Form::label('produto_id', 'Produto:', ['class'=>'col-sm-1 control-label input-sm']) }}
                                                    <div class="col-sm-2">
                                                        {{ Form::select('produto_id', $produtos, null, ['id'=>'produto_id', 'class' => 'form-control selectChosen']) }}
                                                    </div>
                                                    <div id="checkbox">
                                                         {{ Form::label('mudoubase', 'Alterar no Produto:', ['class'=>'col-md-2 control-label input-sm']) }}
                                                        <div class="col-md-1 checkbox">
                                                            {{ Form::checkbox('mudoubase',1) }}
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="form-group crud_space">
                                                    {{ Form::label('tipo', 'Tipo:', ['class'=>'col-sm-1 control-label input-sm']) }}
                                                    <div class="col-sm-2 radio">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
                                                        {{ Form::radio('tipo', '1' , true, ['onclick'=>'changeClassValue()']) }}  <label> Preço Unitário </label>
                                                        <br />&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
                                                        {{ Form::radio('tipo', '2' , false, ['onclick'=>'changeClassValue()']) }} <label> Valor </label>
                                                        <br />&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
                                                        {{ Form::radio('tipo', '3' , false, ['onclick'=>'changeClassValue()']) }} <label> Percentual </label>
                                                    </div>
                                                    {{ Form::label('variacao', 'Variação:', ['class'=>'col-sm-1 control-label input-sm']) }}
                                                    <div class="col-sm-2 radio">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
                                                        {{ Form::radio('variacao', 'A' , true) }}  <label> Aumentar </label>
                                                        <br />&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
                                                        {{ Form::radio('variacao', 'D' , false) }} <label> Diminuir </label>
                                                    </div>
                                                    {{ Form::label('valor', 'Valor:', ['class'=>'col-sm-1 control-label input-sm']) }}
                                                    <div class="col-sm-1">
                                                        {{ Form::text('valor0', null, ['id'=>'acrescimo_0', 'class' => 'form-control input-sm dinheiro']) }}
                                                        {{ Form::text('valor1', null, ['id'=>'acrescimo_1', 'class' => 'form-control input-sm percentagem hidden']) }}
                                                        {{ Form::hidden('valor_banco', @$atualizacao->valor, ['id'=>'valor_banco']) }}
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="box-footer">
                            <div class="col-md-4">
                                {!! Form::submit('Gravar', ['id'=>'btnGravar','class' => 'btn btn-nw-registro']) !!}
                                <a type="button" href="{{url('atualizarprecos')}}" class="btn btn-nw-geral">Voltar</a>
                            </div>
                        </div>
                    </div>
                </div>
            </ul>
            {{ Form::close() }}
        </div>
    </div>
</div>
<script type="text/javascript" src="{{URL::to('js/atualizacaoprec.js')}}"></script>
<script type="text/javascript">
@if(isset($show))
    desativarInputs();
    esconderDivs()
    var show = true;
@endif
</script>

@endsection
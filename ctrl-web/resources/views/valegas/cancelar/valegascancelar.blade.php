 
@extends('layouts.mainmenu')

@section('content')
<div id="mainContent" class="content">
    <div id="divCadastro">
        <div class="row">
            <div class="col-xs-6 col-xs-push-2">
                <div class="box-header">
                    <div class="panel panel-default">
                        <div class="panel-heading">
                            <h3 class="box-title">Cancelar Vale Gás</h3>
                        </div><!-- /.box-header -->
                        <div class="panel-body">
                            <div class="col-md-12 ">
                                {{ Form::open(['id'=>'fmCadastro', 'route' => 'valegascancelar.store', 'class' => 'form-horizontal', 'files' => true]) }}
                                <div class="crud_space form-group">
                                    {{ Form::label('codigo', 'Cód. Vale Gás:', ['class'=>'col-sm-4 control-label input-sm','style'=>'text-align:right;']) }}
                                    <div class="col-md-4 ">
                                        {{ Form::text('codigo', $codigo,['class'=>'input-sm form-control', 'id' => 'codigo']) }}
                                    </div>
                                </div>
                                <div class="crud_space form-group">
                                    {{ Form::label('situacao', 'Situação:', ['class'=>'col-sm-4 control-label input-sm','style'=>'text-align:right;']) }}
                                    <div class="col-md-4 ">
                                        {{ Form::select('situacao', $situacoes, $situacao,['class'=>'selectChosen', 'id' => 'situacao', 'disabled' => 'true']) }}
                                    </div>
                                </div>
                                <div class="crud_space form-group">
                                    <div class="col-md-4">
                                    </div>                                    
                                    <div class="col-md-4 ">
                                        <button class="btn btn-sm btn-nw-registro" id='btnBaixar' type="submit">Gravar</button>
                                        <a class="btn btn-sm btn-nw-geral" href="{{route('home')}}" type="button">voltar</a>
                                    </div>                                    
                                </div>
                                {{ Form::close() }}
                            </div><!-- /.box-body -->
                        </div><!-- /.box -->
                    </div><!-- /.col -->
                </div><!-- /.row -->
                <!-- page script -->
            </div><!-- /.content-wrapper -->
        </div>
    </div>
</div>
<script type="text/javascript">
    $("#btnBaixar").click(function () {
        $("#situacao").prop('disabled', false).trigger('chosen:updated');
    });
</script>
@endsection

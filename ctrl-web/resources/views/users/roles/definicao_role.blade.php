@extends('layouts.mainmenu') 
@section('content')
<div id="mainContent" class="content">
    <div id="divCadastro" class="row">
        <div class="col-md-12">
            <ul>
                <div class="panel panel-default">
                    <div class="panel-heading">
                        <h3 class="panel-title">Definir Papéis</h3>
                    </div>
                    <div class="nav-tabs-custom">
                        <ul class="nav nav-tabs">
                            <li class="active"><a href="#tab_1" data-toggle="tab">Definir Papéis</a></li>
                        </ul>
                        <div class="tab-content">
                            <div class="tab-pane active" id="tab_1"><!-- form start -->
                                <div class="row">
                                    <div id="tabCadastro" class="col-md-10">
                                        <div class="box-body">
                                            {{ Form::open(['id'=>'fmCadastro','route' => 'definir.store', 'class' => 'form-horizontal', 'files' => true]) }}
                                            <div class="form-group crud_space">
                                                {{ Form::label('user_id', 'Usuário:', ['class'=>'col-sm-2 control-label input-sm']) }}
                                                <div class="col-sm-4">
                                                    {{ Form::select('user_id',$users, null,['id'=>'user_id','class'=>'selectChosen form-control input-sm']) }}
                                                </div>
                                                {{ Form::label('tipo_id', 'Tipo:', ['class'=>'col-sm-1 control-label input-sm']) }}
                                                <div class="col-sm-3">
                                                    {{ Form::select('tipo_id',$tipos, null,['id'=>'tipo_id','class'=>'selectChosen form-control input-sm']) }}
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="box-footer">
                            <div class="col-md-4">
                                {{ Form::submit('Gravar', ['class' => 'btn btn-nw-registro']) }}
                                <a type="button" href="{{url('home')}}" class="btn btn-nw-geral">Voltar</a>
                            </div>
                        </div>
                    </div>
                </div>
            </ul>
            {{ Form::close() }}
        </div>
    </div>
</div>
@endsection

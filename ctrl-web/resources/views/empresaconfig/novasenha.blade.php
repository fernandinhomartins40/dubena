@extends('layouts.mainmenu') 
@section('content')
<div id="mainContent" class="content">
    <div id="divCadastro" class="row">
        <div class="col-md-12">
            <ul>
                <div class="panel panel-default">
                    <div class="panel-heading">                    
                        <h3 class="panel-title">Senha Mestra</h3>
                    </div>
                    <div class="nav-tabs-custom">
                        <ul class="nav nav-tabs">
                            <li class="active"><a href="#tab_1" data-toggle="tab">Senha Mestra</a></li>
                        </ul>
                        <div class="tab-content">
                            <div class="tab-pane active" id="tab_1">
                                <!-- form start -->
                                <div class="row">
                                    <div id="tabCadastro" class="col-md-10">
                                        <div class="box-body">
                                            <div class="modal-body col-md-12 col-md-push-3">
                                                @if(isset($empconfig->senhamestre))
                                                {{ Form::open(['id'=>'novaSenha','url' => 'empresaconfig/changepassword', 'class' => 'form-horizontal', 'files' => true]) }}
                                                <div class="form-group crud_space">
                                                    {{ Form::label('senhaatual', 'Senha Atual:', ['class'=>'col-md-2 control-label input-sm']) }}
                                                    <div class="col-md-5">
                                                        {{ Form::password('senhaatual',null,['id' => 'senhaatual','class'=>'form-control number input-sm']) }}
                                                    </div>
                                                </div>
                                                <div class="form-group crud_space">
                                                    {{ Form::label('senhanova', 'Nova Senha:', ['class'=>'col-md-2 control-label input-sm']) }}
                                                    <div class="col-md-5">
                                                        {{ Form::password('senhanova',null,['id' => 'senhanova','class'=>'form-control number input-sm']) }}
                                                    </div>
                                                </div>
                                                <div class="form-group crud_space">
                                                    {{ Form::label('confirmarsenha', 'Confirmar Senha:', ['class'=>'col-md-2 control-label input-sm']) }}
                                                    <div class="col-md-5">
                                                        {{ Form::password('confirmarsenha',null,['id' => 'senhamestre','class'=>'form-control number input-sm']) }}
                                                    </div>
                                                </div>
                                                @else
                                                {{ Form::open(['id'=>'novaSenha','url' => 'empresaconfig/changepassword','class' => 'form-horizontal', 'files' => true]) }}
                                                <div class="form-group crud_space">
                                                    {{ Form::label('senhanova', 'Nova Senha:', ['class'=>'col-md-2 control-label input-sm']) }}
                                                    <div class="col-md-5">
                                                        {{ Form::password('senhanova',null,['id' => 'senhanova','class'=>'form-control number input-sm']) }}
                                                    </div>
                                                </div>
                                                <div class="form-group crud_space">
                                                    {{ Form::label('confirmarsenha', 'Confirmar Senha:', ['class'=>'col-md-2 control-label input-sm']) }}
                                                    <div class="col-md-5">
                                                        {{ Form::password('confirmarsenha',null,['id' => 'confirmarsenha','class'=>'form-control number input-sm']) }}
                                                    </div>
                                                </div>                
                                                @endif
                                                {{ Form::hidden('config_id',@$empconfig->id,['id' => 'config_id','class'=>'form-control number input-sm']) }}
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="box-footer">
                            <div class="col-md-4">
                                {!! Form::submit('Gravar', ['class' => 'btn btn-nw-registro']) !!}
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
<script type="text/javascript" src="{{URL::to('js/senhamestre.js')}}"></script>
<script type="text/javascript">
var root = '{{url("/")}}';
@if($errors->any())
    errorsany = true;
@else
    errorsany = false;
@endif
</script>
@endsection

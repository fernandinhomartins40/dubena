@extends('layouts.mainmenu')

@section('content')

<div id="mainContent" class="content">
    <div id="divCadastro" class="row">
        <div class="col-md-12">

            <!-- Custom Tabs -->
            <!-- <form id="fmCadastro" role="form" class="form-horizontal" method="POST" enctype="multipart/form-data"> -->
            @if($config !== null)
            {{ Form::model($config, array('id'=>'fmCadastro', 'method' => 'PATCH', 'class' => 'form-horizontal','files' => true, 'route' => array('config.update', $config->id))) }}
            @else 
            {{ Form::open(['id'=>'fmCadastro','route' => 'config.store', 'class' => 'form-horizontal', 'files' => true]) }} 
            @endif
            <ul>
                <div class="panel panel-default">
                    <div class="panel-heading">                    
                        <h3 class="panel-title">Configurações Gerais</h3>
                    </div>
                    <div class="nav-tabs-custom">
                        <ul class="nav nav-tabs">
                            <li class="active"><a href="#tab_1" data-toggle="tab">Dados</a></li>
                        </ul>
                        <div class="tab-content">
                            <div class="tab-pane active" id="tab_1">
                                <!-- form start -->
                                <div class="row">
                                    <div id="tabCadastro" class="col-md-10">
                                        <div class="box-body">
                                            <div class="form-group crud_space">
                                                <div class="col-md-2 col-md-push-1">
                                                    <h4></h4>
                                                </div>
                                            </div>                                            
                                            <div class="form-group crud_space">
                                                {{ Form::label('urlsistemaweb', 'URL do Sistema Web:', ['class'=>'col-sm-3 control-label input-sm']) }}
                                                <div class="col-sm-8">
                                                    {{ Form::text('urlsistemaweb',null,['id' => 'urlsistemaweb','class'=>'form-control input-sm']) }}
                                                </div> 
                                            </div>
                                            <div class="form-group crud_space">
                                                {{ Form::label('urltraccar', 'URL do Serviço Traccar:', ['class'=>'col-sm-3 control-label input-sm']) }}
                                                <div class="col-sm-8">
                                                    {{ Form::text('urltraccar',null,['id' => 'urltraccar','class'=>'form-control input-sm']) }}
                                                </div> 
                                            </div>
                                            <div class="form-group crud_space">
                                                {{ Form::label('usertraccar', 'Usuário do Serviço Traccar:', ['class'=>'col-sm-3 control-label input-sm']) }}
                                                <div class="col-sm-8">
                                                    {{ Form::text('usertraccar',null,['id' => 'usertraccar','class'=>'form-control input-sm']) }}
                                                </div> 
                                            </div>
                                            <div class="form-group crud_space">
                                                {{ Form::label('passwordtraccar', 'Senha do Serviço Traccar:', ['class'=>'col-sm-3 control-label input-sm']) }}
                                                <div class="col-sm-8">
                                                    {{ Form::password('passwordtraccar',null,['id' => 'passwordtraccar','class'=>'form-control input-sm']) }}
                                                </div> 
                                            </div>
                                            <div class="form-group crud_space">
                                                {{ Form::label('keygooglemaps', 'Key API Google Maps:', ['class'=>'col-sm-3 control-label input-sm']) }}
                                                <div class="col-sm-8">
                                                    {{ Form::text('keygooglemaps',null,['id' => 'keygooglemaps','class'=>'form-control input-sm']) }}
                                                </div> 
                                            </div>
                                            <div class="form-group crud_space">
                                                {{ Form::label('temporefresh', 'Tempo de Refresh de Veículos:', ['class'=>'col-sm-3 control-label input-sm']) }}
                                                <div class="col-sm-1">
                                                    {{ Form::number('temporefresh',null,['id' => 'temporefresh','class'=>'form-control input-sm']) }}
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
                            {!! Form::submit('Gravar', ['id'=>'btngravar','class' => 'btn btn-nw-registro']) !!}
                            <a id="habilitaredicao" type="button" class="btn btn-nw-geral">Habilitar Edição</a>
                        </div>
                    </div>
                </div>
            </ul>
            {!! Form::close() !!} 
        </div>
    </div>
</div>

<script type="text/javascript" src="{{URL::to('js/config.js')}}"></script>
<script type="text/javascript">
var root = '{{url("/")}}';
@if($errors->any())
    errorsany = true;
@else
    errorsany = false;
@endif
</script>
@endsection
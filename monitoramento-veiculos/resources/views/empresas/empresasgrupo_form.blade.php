
@extends('layouts.mainmenu')

@section('content')
<div id="mainContent" class="content">
    <div id="divCadastro" class="row">
        <div class="col-md-12">
            <div class="box-header">
                <h3 class="box-title">Grupo de Empresas</h3>
            </div><!-- /.box-header -->
            <!-- Custom Tabs -->
            <!-- <form id="fmCadastro" role="form" class="form-horizontal" method="POST" enctype="multipart/form-data"> -->

            @if(isset($EmpresaGrupo))
            {{ Form::model($EmpresaGrupo, array('method' => 'PATCH', 'class' => 'form-horizontal', 'route' => array('empresas_grupo.update', $EmpresaGrupo->id))) }}
            @else
            {{ Form::open(['route' => 'empresas_grupo.store', 'class' => 'form-horizontal']) }}
            @endif
            <ul>

                <div class="nav-tabs-custom">
                    <ul class="nav nav-tabs">
                        <li class="active"><a href="#tab_1" data-toggle="tab">Dados do Grupo de Empresas</a></li>
                    </ul>
                    <div class="tab-content">
                        <div class="tab-pane active" id="tab_1">
                            <!-- form start -->

                            <div class="row">
                                <div id="tabCadastro" class="col-md-10">
                                    <div class="box-body">
                                        <div class="form-group crud_space">
                                            {!! Form::label('descricao', 'Descrição:', ['class'=>'col-sm-3 control-label input-sm']) !!}
                                            <div class="col-sm-9">
                                                {!! Form::text('descricao',null,['class'=>'form-control input-sm']) !!}
                                            </div>
                                        </div>
                                        <div class="form-group crud_space">
                                            {!! Form::label('ativo', 'Ativo:', ['class'=>'col-sm-3 control-label input-sm']) !!}
                                            <div class="col-sm-9">
                                                <!--
                                                {!! Form::text('ativo',null,['class'=>'form-control input-sm']) !!}
                                                {!! Form::checkbox('ativo',null,['class'=>'form-control input-sm']) !!}
                                                -->
                                                
                                                {{ Form::checkbox('ativo') }}

                                            </div>
                                        </div>
                                    </div><!-- /.box-body -->
                                </div>
                            </div>
                        </div><!-- /.tab-pane -->
                    </div><!-- /.tab-pane -->
                </div><!-- /.tab-content -->
                <div class="nav-tabs-custom">
                    <div class="col-md-12">
                        <div class="box-footer">
                            <div class="col-md-4">
                                <!--
                                <button id="btnGravar" type="submit" class="btn btn-nw-registro">Gravar</button>
                                -->

                                {!! Form::submit('Gravar', ['class' => 'btn btn-nw-registro']) !!}
                                <a href="{{ URL::route('empresas_grupo.index') }}" class="btn btn-nw-geral">Voltar</a>
                            </div>

                            @if($errors->any())
                            <div id="saveError" class="alert alert-danger alert-dismissable col-md-4">
                                <button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>
                                <span class="glyphicon glyphicon-remove"></span>
                                @foreach($errors->all() as $error)
                                <p>{{ $error }}</p>
                                @endforeach
                            </div>
                            @endif
                        </div>
                    </div>
                </div>
                {!! Form::close() !!}
                @if(isset($EmpresaGrupo))
                <div class="nav-tabs-custom">
                    <div class="col-md-12">
                        <div class="box-footer">
                            <div class="col-md-4">
                                {!! Form::open(['class' => 'delete', 'action' => ['EmpresasGrupoController@destroy', $EmpresaGrupo->id], 'method' => 'delete']) !!}
                                <!--
                                {{ Form::model($EmpresaGrupo, array('method' => 'delete', 'class' => 'form-horizontal', 'route' => array('empresas_grupo.destroy', $EmpresaGrupo->id))) }}
                                -->
                                {!! Form::submit('Remover', ['class'=>'btn btn-nw-registro']) !!}
                                {!! Form::close() !!}
                            </div>
                        </div>
                    </div>
                </div><!-- nav-tabs-custom -->
                @endif
        </div><!-- /.col -->
    </div>
    <!-- page script -->
    <script type="text/javascript">
        $(".delete").on("submit", function () {
            return confirm("Quer remover o registro atual?");
        });
    </script>
</div>
@endsection

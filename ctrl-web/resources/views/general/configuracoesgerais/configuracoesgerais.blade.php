@extends('layouts.mainmenu')

@section('content')

    <div id="mainContent" class="content">
        <div id="divCadastro" class="row">
            <div class="col-md-12"><!-- Custom Tabs -->
                @if(isset($configG) && $configG !== null)
                    {{ Form::model($configG, array('id'=>'fmCadastro', 'method' => 'POST', 'class' => 'form-horizontal','files' => true, 'route' => array('configuracoesGerais.store'))) }}
                @else
                    {{ Form::open(['id'=>'fmCadastro','route' => 'configuracoesGerais.store', 'class' => 'form-horizontal', 'files' => true]) }}
                @endif
                <ul>
                    <div class="panel panel-default">
                        <div class="panel-heading">
                            <h3 class="panel-title">Configurações Gerais</h3>
                        </div>
                        <div class="nav-tabs-custom">
                            <ul class="nav nav-tabs">
                                <li class="active"><a href="#tab_1" data-toggle="tab">Dados Gerais</a></li>
                                <li class=""><a href="#tab_2" data-toggle="tab">E-Mail</a></li>
                                <li class=""><a href="#tab_3" data-toggle="tab">Responsável Técnico</a></li>
                            </ul>
                            <div class="tab-content">
                                <div class="tab-pane active" id="tab_1">
                                    <!-- form start -->
                                    <div class="row">
                                        <div id="tabCadastro" class="col-md-12">
                                            <div class="box-body">
                                                <div class="crud_space form-group">
                                                    {{Form::label('keygooglemaps', 'Key Google Maps:', ['class' => 'input-sm control-label col-sm-2'])}}
                                                    <div class="col-sm-3">
                                                        {{Form::text('keygooglemaps', null, ['class' => 'input-sm form-control','id' => 'keygooglemaps'])}}
                                                    </div>
                                                    {{Form::label('linkmonitoramento', 'Link de Acesso ao Monitoramento:', ['class' => 'input-sm control-label col-sm-3'])}}
                                                    <div class="col-sm-3">
                                                        {{Form::text('linkmonitoramento', null, ['class' => 'input-sm form-control','id' => 'linkmonitoramento'])}}
                                                    </div>
                                                </div>
                                                <div class="form-group crud_space">
                                                    {{ Form::label('emailkeygoogle', 'E-mail Chave API Google:', ['class'=>'col-sm-2 control-label input-sm']) }}
                                                    <div class="col-sm-3">
                                                        {{ Form::email('emailkeygoogle', null, ['id' => 'emailkeygoogle','class'=>'form-control input-sm']) }}
                                                    </div>
                                                </div>
                                                <hr />
                                                <div class="form-group crud_space">
                                                    <div class="col-sm-2 col-sm-push-1" style="font-size: 15px">
                                                        SAT CF-e
                                                    </div>
                                                </div>

                                                <div class="form-group crud_space">
                                                    {{ Form::label('satemitcnpjhomolog', 'CNPJ Emitente SAT (Homolog):', ['class'=>'col-sm-2 control-label input-sm']) }}
                                                    <div class="col-sm-3">
                                                        {{ Form::text('satemitcnpjhomolog', null, ['id' => 'satemitcnpjhomolog','class'=>'cnpj form-control input-sm']) }}
                                                    </div>
                                                </div>
                                                <div class="form-group crud_space">
                                                    {{ Form::label('satcnpjprod', 'CNPJ Software House SAT (Prod):', ['class'=>'col-sm-2 control-label input-sm']) }}
                                                    <div class="col-sm-3">
                                                        {{ Form::text('satcnpjprod', null, ['id' => 'satcnpjprod','class'=>'cnpj form-control input-sm']) }}
                                                    </div>
                                                    {{ Form::label('satcnpjhomolog', 'CNPJ Software House SAT (Homolog):', ['class'=>'col-sm-3 control-label input-sm']) }}
                                                    <div class="col-sm-3">
                                                        {{ Form::text('satcnpjhomolog', null, ['id' => 'satcnpjhomolog','class'=>'cnpj form-control input-sm']) }}
                                                    </div>
                                                </div>
                                                <div class="form-group crud_space">
                                                    {{ Form::label('satsignacprod', 'Assinatura AC SAT (Prod):', ['class'=>'col-sm-2 control-label input-sm']) }}
                                                    <div class="col-sm-3">
                                                        {{ Form::text('satsignacprod', null, ['id' => 'satsignacprod','class'=>'form-control input-sm']) }}
                                                    </div>
                                                    {{ Form::label('satsignachomolog', 'Assinatura AC SAT (Homolog):', ['class'=>'col-sm-3 control-label input-sm']) }}
                                                    <div class="col-sm-3">
                                                        {{ Form::text('satsignachomolog', null, ['id' => 'satsignachomolog','class'=>'form-control input-sm']) }}
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="tab-pane" id="tab_2">
                                    <div class="row">
                                        <div id="tabCadastro" class="col-md-12">
                                            <div class="box-body">
                                                <div class="form-group crud_space">
                                                    {{ Form::label('remembermails',
                                                    'E-mails para lembretes do sistema:', ['class'=>'col-sm-2 control-label input-sm']) }}
                                                    <div class="col-sm-3">
                                                        {{ Form::text('remembermails', null,
                                                            ['id' => 'remembermails','class'=>'form-control input-sm',
                                                                "data-toggle" => 'tooltip',
                                                                "data-trigger" => "hover",
                                                                "data-placement" => "bottom",
                                                                "title" => "(separar por \";\" para informar vários)"
                                                            ]) }}
                                                    </div>
                                                </div>
                                                @include('empresaconfig.partials.aba_email')
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="tab-pane" id="tab_3">
                                    <div class="row">
                                        <div class="col-md-12">
                                            <div class="box-body">
                                                <div class="form-group crud_space">
                                                    {{Form::label('rtcnpj', 'CNPJ:', ['class' => 'input-sm control-label col-sm-2'])}}
                                                    <div class="col-sm-2">
                                                        {{Form::text('rtcnpj', null, ['class' => 'input-sm form-control cnpj','id' => 'rtcnpj'])}}
                                                    </div>
                                                    {{Form::label('rtcontato', 'Nome:', ['class' => 'input-sm control-label col-sm-1'])}}
                                                    <div class="col-sm-2">
                                                        {{Form::text('rtcontato', null, ['class' => 'input-sm form-control','id' => 'rtcontato'])}}
                                                    </div>
                                                    {{Form::label('rtemail', 'E-Mail:', ['class' => 'input-sm control-label col-sm-1'])}}
                                                    <div class="col-sm-2">
                                                        {{Form::text('rtemail', null, ['class' => 'input-sm form-control','id' => 'rtemail'])}}
                                                    </div>
                                                </div>
                                                <div class="form-group crud_space">
                                                    {{Form::label('rttelefone', 'Telefone:', ['class' => 'input-sm control-label col-sm-2'])}}
                                                    <div class="col-sm-2">
                                                        {{Form::text('rttelefone', null, ['class' => 'input-sm form-control telefone','id' => 'rttelefone'])}}
                                                    </div>
                                                    {{Form::label('rtidcsrt', 'ID CSRT:', ['class' => 'input-sm control-label col-sm-1'])}}
                                                    <div class="col-sm-2">
                                                        {{Form::text('rtidcsrt', null, ['class' => 'input-sm form-control','id' => 'rtidcsrt'])}}
                                                    </div>
                                                    {{Form::label('rtcsrt', 'CSRT:', ['class' => 'input-sm control-label col-sm-1'])}}
                                                    <div class="col-sm-2">
                                                        {{Form::text('rtcsrt', null, ['class' => 'input-sm form-control','id' => 'rtcsrt'])}}
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
                                @can('create', App\Configuracoesgerais::class)
                                    {{ Form::submit('Gravar', ['id'=>'btngravar','class' => 'btn btn-nw-registro']) }}
                                @endcan
                            </div>
                        </div>
                    </div>
                </ul>
                {{ Form::close() }}
            </div>
        </div>
    </div>
    <script type="text/javascript">
        $("#fmCadastro").on('submit', function (e) {
            e.preventDefault();
            var url = root + '/configuracoesGerais.store';
            var formData = new FormData($(this)[0]);
            ajaxGenerator(url, 'POST', function (data) {
                if(data === "OK|")
                    bootbox.alert('Gravado com sucesso');
                else
                    bootbox.alert('' + data);
            }, null, formData);
        });
    </script>
@endsection

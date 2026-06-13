<div class="row">
    <div id="tabCadastro" class="col-md-10">
        <div class="box-body">
            <div class="form-group crud_space">
                {{ Form::label('spedemite', 'Emite Sped:', ['class'=>'col-sm-3 control-label input-sm']) }}
                <div class="col-sm-1  checkbox">
                    <!--{{ Form::hidden('spedemite',0) }}-->
                    {{ Form::checkbox('spedemite') }}
                </div>
            </div>
            <div class="form-group crud_space">
                {{ Form::label('spedregistro1010', 'Registro 1010:', ['class'=>'col-sm-3 control-label input-sm']) }}
                <div class="col-sm-2">
                    {{ Form::text('spedregistro1010',null,['class'=>'form-control input-sm', 'readonly']) }}
                </div>
                <div class="col-sm-2">
                    <button class="btn btn-nw-buscas btn-xs" type='button' id='btnResponder'>Inserir</button>
                </div>
            </div>
            <div class="form-group crud_space">
                {{ Form::label('spedincidenciatributaria', 'Incidência Tributária:', ['class'=>'col-sm-3 control-label input-sm']) }}
                <div class="col-sm-3">
                    {{ Form::select('spedincidenciatributaria', $spedincidenciatributarias, null, ['class' => 'form-control selectDisableSearch', 'style'=>'border-radius: 5px ! important;', 'onchange'=>'']) }}
                </div>
                {{ Form::label('spedperfil', 'Perfil:', ['class'=>'col-sm-2 control-label input-sm']) }}
                <div class="col-sm-3">
                    {{ Form::select('spedperfil', $spedperfils, null, ['class' => 'form-control selectDisableSearch', 'style'=>'border-radius: 5px ! important;', 'onchange'=>'']) }}
                </div>
            </div>
            <div class="form-group crud_space">
                {{ Form::label('spedapropriacaocredito', 'Apropriação de Crédito:', ['class'=>'col-sm-3 control-label input-sm']) }}
                <div class="col-sm-3">
                    {{ Form::select('spedapropriacaocredito', $spedapropriacaocreditos, null, ['class' => 'form-control selectDisableSearch', 'style'=>'border-radius: 5px ! important;', 'onchange'=>'']) }}
                </div>
                {{ Form::label('spedatividade', 'Atividade:', ['class'=>'col-sm-2 control-label input-sm']) }}
                <div class="col-sm-3">
                    {{ Form::select('spedatividade', $spedatividades, null, ['class' => 'form-control selectDisableSearch', 'style'=>'border-radius: 5px ! important;', 'onchange'=>'']) }}
                </div>
            </div>
            <div class="form-group crud_space">
                {{ Form::label('spedtipocontribuicao', 'Tipo Contribuição:', ['class'=>'col-sm-3 control-label input-sm']) }}
                <div class="col-sm-3">
                    {{ Form::select('spedtipocontribuicao', $spedtipocontribuicao, null, ['class' => 'form-control selectDisableSearch', 'style'=>'border-radius: 5px ! important;', 'onchange'=>'']) }}
                </div>
                {{ Form::label('spedregimecumulativo', 'Regime Cumulativo:', ['class'=>'col-sm-2 control-label input-sm']) }}
                <div class="col-sm-3">
                    {{ Form::select('spedregimecumulativo', $spedregimecumulativos, null, ['class' => 'form-control selectDisableSearch', 'style'=>'border-radius: 5px ! important;', 'onchange'=>'']) }}
                </div>
            </div>
        </div><!-- /.box-body -->
    </div> <!--tabCadastro-->
</div> <!--row-->
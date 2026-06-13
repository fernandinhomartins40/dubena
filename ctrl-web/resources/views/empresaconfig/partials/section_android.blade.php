<div class="form-group crud_space">
    <div id="boxandroidutiliza">
        {{ Form::label('androidutiliza', 'Utiliza:', ['class'=>'col-md-3 control-label input-sm']) }}
        <div class="col-md-1 checkbox">
            {{ Form::checkbox('androidutiliza',1) }}
        </div>
    </div>
    <div id="boxandroidenviatodos">
        {{ Form::label('androidenviatodos', 'Envia Notificação a Todos do Setor:', ['class'=>'col-md-3 control-label input-sm']) }}
        <div class="col-md-1 checkbox">
            {{ Form::checkbox('androidenviatodos',1) }}
        </div>
    </div>
    <div class="col-sm-4">
        <div id="boxvalidacordenadasentrega">
            {{ Form::label('validacordenadasentrega', 'Grava Coordenadas da Entrega:', ['class'=>'col-md-8 control-label input-sm']) }}
            <div class="col-md-1 checkbox">
                {{ Form::checkbox('validacordenadasentrega',1) }}
            </div>
        </div>
    </div>
</div>
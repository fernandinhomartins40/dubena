<!-- Modal -->
<div class="modal fade" id="modalChangeProdutosConvenio" tabindex="-1" role="dialog" aria-labelledby="myModalLabel">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">

            <div class="modal-header">
                <button type="button" class="close btnClosePromocao" data-dismiss="modal"><span aria-hidden="true">×</span><span class="sr-only">Close</span></button>
                <h4 class="modal-title">Imprimir Etiquetas</h4>
            </div>
            {{Form::open(['id' => 'fmImprimirEtiquetas'])}}
            <div class="modal-body col-md-12">
                <div class="form-group crud_space margTop_15">
                    {!! Form::label('apartir', 'Iniciar a Partir de:', ['class' => 'control-label col-md-6 input-sm'])!!}
                    <div class="col-md-6">
                        {!! Form::text('apartir',null,['class'=>'form-control number', 'id' => 'apartir'])!!}
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="submit" id='btnModalImprimir' class="btn btn-nw-registro">Imprimir</button>
                <button type="button" class="btn btn-nw-geral btnClosePromocao" data-dismiss="modal">Fechar</button>
            </div>
            {{Form::close()}}
        </div>
    </div>
</div>
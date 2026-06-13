<!-- form start -->
<div class="row">
    <div id="tabCadastro" class="col-md-10">
        <div class="box-body">
            <div class="form-group crud_space">
                <div class="col-sm-2 col-sm-offset-4">
                    @if(isset($Empresa->logo))
                    <input type="text" id="logo" name="logo" value="{{ $Empresa->logo }}" style="visibility: hidden; width: 1px; height: 1px" multiple ></input>
                    @else
                    <input type="text" id="logo" name="logo" style="visibility: hidden; width: 1px; height: 1px" multiple />
                    @endif
                    <input type="file" id="file" name="file" style="visibility: hidden; width: 1px; height: 1px" multiple />
                    <div class="action">
                        <a href="" onclick="document.getElementById('file').click(); return false">
                            @if(isset($Empresa->logo))
                            <img id="cropped" style="max-width:200px; border-radius:50%; padding-left: 4.5px;" src="data:image/png;base64,{{ $Empresa->logo }}" alt="Logotipo"/>
                            @else
                            <img id="cropped" style="max-width:200px; border-radius:50%; padding-left: 4.5px;" src="{{ URL::to('dist/img/upload.jpg') }}" alt="Logotipo"/>
                            @endif
                        </a>
                    </div>
                </div>
                <div class="col-sm-2">
                    <div class="imageBox">
                        <div class="thumbBox"></div>
                        <div class="spinner" style="display: none">Loading...</div>
                    </div>
                </div>
            </div> <!-- form-group  -->
            <div class="form-group crud_space">
                <div class="col-sm-4 col-sm-push-4">
                    <input type="button" id="btnCarregarImagem" for="file" onclick="document.getElementById('file').click(); return false" class="btn btn-nw-geral" value="Carregar">
                    <input type="button" id="btnRemoverImagem" for="" onclick="" class="btn btn-danger" value="Remover">
                </div>
                <div class="col-sm-5 col-sm-offset-2">
                    <div class="col-sm-3">
                        <input type="button" id="btnCrop" class="btn btn-nw-geral" value="Cortar">
                    </div>
                    <div class="col-sm-1">
                        <input type="button" id="btnZoomIn" class="btn btn-nw-geral" value="+">
                    </div>
                    <div class="col-sm-1">
                        <input type="button" id="btnZoomOut" class="btn btn-nw-geral" value="-">
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
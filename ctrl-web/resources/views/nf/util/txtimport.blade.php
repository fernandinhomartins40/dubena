@extends('layouts.mainmenu')

@section('content')

<button id="btnUpload" data- type="button" class="btn btn-sm btn-nw-registro" data-toggle='tooltip' data-trigger="hover" data-placement="bottom" title="Importat TXT">
    <span class="fa fa-upload fa-lg"></span>
</button>
@include('general.modals.upload_file')
<script>

    $("#btnUpload").on('click', function () {
        $("#modal-upload-file").modal('show');
    });
    var validFormatUpload = ['txt'];
    $("#file-upload").attr('accept', '.txt');

    var callbackUpload = function () {
        var url = root + '/nfemitida.import.txt';
        $("#fmUpload").off().attr({
            'action': url,
            'method': 'post'
        }).on('submit', function () {
            if (isEmpty($("#file-upload").val())) {
                bootbox.alert('Selecione um arquivo');
                return false;
            }
        });
    }
</script>
@endsection

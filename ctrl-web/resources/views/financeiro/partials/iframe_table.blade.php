<!DOCTYPE html>
<html>
    <head>
        <link href="{{asset('bootstrap/css/bootstrap.min.css')}}" rel="stylesheet" type="text/css">
        <link type="text/css" href="{{asset('bootstrap/css/bootstrap-responsive.css')}}" rel="stylesheet">
        <link href="{{asset('css/font-awesome.min.css')}}" rel="stylesheet" type="text/css" />
        <link href="{{asset('dist/css/AdminLTE.min.css')}}" rel="stylesheet" type="text/css" />
        <link href="{{asset('css/form.css')}}" rel="stylesheet" type="text/css" />
        <link href="{{asset('css/novolayout.css')}}" rel="stylesheet" type="text/css"/>
        <link href="{{asset('css/custom.css')}}" rel="stylesheet" type="text/css" />
        <link href="{{asset('css/lib/great-table.css')}}" rel="stylesheet" type="text/css" />

        <script src="{{asset('plugins/jQuery/jQuery-2.1.4.min.js')}}"></script>
        <script src="{{asset('bootstrap/js/bootstrap.min.js')}}"></script>
        <script src="{{asset('js/lib/great-table.js')}}"></script>
    </head>
    <body>
        <div class="panel panel-default">
            <table class="table hidden" id="tblMovto">
            </table>
        </div>
        <script>
            var data = {!!$data!!};
        </script>
        <script src="{{asset('js/iframeTableCaixa.js')}}"></script>
    </body>
</html>
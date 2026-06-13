<script type="text/javascript">
    var allCodICMS = [];
    @foreach($allCodICMS as $key => $cst)
        var cst = {};
        cst.id = '{{$key}}';
        cst.cst = '{{$cst}}';
        allCodICMS.push(cst);
    @endforeach

    var allowedICMSST = [];
    @foreach($allowedICMSST as $key => $cst)
        var cst = {};
        cst.id = '{{$key}}';
        cst.cst = '{{$cst}}';
        allowedICMSST.push(cst);
    @endforeach

    var allowedICMSDeson = [];
    @foreach($allowedICMSDeson as $key => $cst)
        var cst = {};
        cst.id = '{{$key}}';
        cst.cst = '{{$cst}}';
        allowedICMSDeson.push(cst);
    @endforeach

    var allowedICMSDesonGroup40 = []; //avaliar necessidade
    @foreach($allowedICMSDesonGroup40 as $key => $cst)
        var cst = {};
        cst.id = '{{$key}}';
        cst.cst = '{{$cst}}';
        allowedICMSDesonGroup40.push(cst);
    @endforeach

    var allowedICMSFCPNormal = [];
    @foreach($allowedICMSFCPNormal as $key => $cst)
        var cst = {};
        cst.id = '{{$key}}';
        cst.cst = '{{$cst}}';
        allowedICMSFCPNormal.push(cst);
    @endforeach

    @foreach($allowedICMSFCP as $key => $cst)
        var cst = {};
        cst.id = '{{$key}}';
        cst.cst = '{{$cst}}';
        allowedICMSFCPNormal.push(cst);
    @endforeach

    @foreach($allowedICMSFCPST as $key => $cst)
        var cst = {};
        cst.id = '{{$key}}';
        cst.cst = '{{$cst}}';
        allowedICMSFCPNormal.push(cst);
    @endforeach

    var allowedICMSREDBC = [];
    @foreach($allowedICMSREDBC as $key => $cst)
        var cst = {};
        cst.id = '{{$key}}';
        cst.cst = '{{$cst}}';
        allowedICMSREDBC.push(cst);
    @endforeach

    var allowedICMSREDBCST = [];
    @foreach($allowedICMSREDBCST as $key => $cst)
        var cst = {};
        cst.id = '{{$key}}';
        cst.cst = '{{$cst}}';
        allowedICMSREDBCST.push(cst);
    @endforeach

    var allowedICMSTagPart = []; //avaliar necessidade
    @foreach($allowedICMSTagPart as $key => $cst)
        var cst = {};
        cst.id = '{{$key}}';
        cst.cst = '{{$cst}}';
        allowedICMSTagPart.push(cst);
    @endforeach

    var allowedDiferimento = [];
    @foreach($allowedDiferimento as $key => $cst)
        var cst = {};
        cst.id = '{{$key}}';
        cst.cst = '{{$cst}}';
        allowedDiferimento.push(cst);
    @endforeach

    var allowedMODBC = [];
    @foreach($allowedMODBC as $key => $cst)
        var cst = {};
        cst.id = '{{$key}}';
        cst.cst = '{{$cst}}';
        allowedMODBC.push(cst);
    @endforeach

    var allowedMODBCST = [];
    @foreach($allowedMODBCST as $key => $cst)
        var cst = {};
        cst.id = '{{$key}}';
        cst.cst = '{{$cst}}';
        allowedMODBCST.push(cst);
    @endforeach

    var isSN = [];
    @foreach($isSN as $key => $cst)
        var cst = {};
        cst.id = '{{$key}}';
        cst.cst = '{{$cst}}';
        isSN.push(cst);
    @endforeach

    var motDesICMS = [];
    @foreach($motDesICMS as $key => $cst)
        var cst = JSON.parse('{!!json_encode($cst)!!}');
        motDesICMS.push(cst);
    @endforeach
    motDesICMS = new CollectJS(motDesICMS);
    var motDesonPf = '{{@$imposto->pfnfmotdesonicms}}';
    var motDesonPj = '{{@$imposto->nfmotdesonicms}}';
    var motDesonPfUf = '';
    var motDesonPjUf = '';
    
    var showing = false;
    @if (isset($show) && $show)
        showing = true;
    @endif
    var errorsany = false;
    @if ($errors -> any())
        errorsany = true;
    @endif
    
    var button = createButtonsGrid();
    var estadosImp = [];
    @if (isset($estadosimp))
        @foreach($estadosimp as $imp)
            var imp = JSON.parse('{!!json_encode($imp)!!}');
            imp.buttons = button;
            estadosImp.push(imp);
        @endforeach
    @endif
    
    var urlNaturezaPis = '{{URL::to("nfimposto/ajaxnatureza/:id")}}';
    $(document).ready(function () {
        onDocReady();
    });
    
    $("#index").val(getParametro('index') ? getParametro('index') : root + '/nfimposto');
</script>
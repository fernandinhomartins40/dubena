$(document).ready(function () {
    tblEmpresa = $("#tblEmpresas").DataTable({
        "language": {
            "url": urlDataTable
        },
        "processing": false,
        "bPaginate": false,
        "bLengthChange": false,
        "bFilter": false,
        "bSort": false,
        "bInfo": false,
        "bAutoWidth": false,
        "destroy": true,
        "sScrollY": "200",
        "aoColumnDefs": [{
            "bVisible": false,
            "aTargets": [0]
        },{
            "width": "30%",
            "aTargets":[2]
        }]
    });

    tblPermissions = $("#tblPermissions").DataTable({
        "language": {
            "url": urlDataTable
        },
        "processing": false,
        "bPaginate": false,
        "bLengthChange": false,
        "bFilter": true,
        "bSort": false,
        "bInfo": false,
        "bAutoWidth": false,
        "destroy": true,
        "sScrollY": "270",
        "aoColumnDefs": [{
            "bVisible": false,
            "aTargets": [0, 1]
        }]
    });

    tblPermissionsGiven = $("#tblPermissionsGiven").DataTable({
        "language": {
            "url": urlDataTable
        },
        "processing": false,
        "bPaginate": false,
        "bLengthChange": false,
        "bFilter": true,
        "bSort": false,
        "bInfo": false,
        "bAutoWidth": false,
        "destroy": true,
        "sScrollY": "270",
        "aoColumnDefs": [{
            "bVisible": false,
            "aTargets": [0, 1, 2]
        }]
    });
    initFunctions();
});
// IDs Alertas: Pedidos = 102 (Me Ligue) | Pesquisa Check = 103 |  Troca Oleo = 91 |
// Troca Pneu = 92 | Veiculos = 70 | Colaboradores = 24 |
$( document ).ajaxComplete( function() {
    filterInput( tblEmpresa );
});

var botoes = '<a class="btn btn-xs btn-nw-registro" id="btnRemover" data-toggle="tooltip" data-trigger="hover" data-placement="bottom" title="Remover Empresa">Remover</a>&nbsp;&nbsp;' +
'<a class="btn btn-xs btn-nw-geral" id="addPermissoes" data-toggle="tooltip" data-trigger="hover" data-placement="bottom" title="Adicionar Permissões">Permissões</a>';
var checked = "<i class='fa fa-check'></i>";
var remove = "<i class='fa fa-remove'></i>";

$('a[data-toggle="tab"]').on('shown.bs.tab', function( e ) {
    $($.fn.dataTable.tables(true)).DataTable().columns.adjust();
});


$("#grupos").change( function () {
    getEmpresas();
});

$("#visualizar").click( function () {
    var vis = $(this);
    checkboxes( vis, 'criar', 'editar', 'deletar', 'baixar', 'alerta' );
});

$("#criar").click( function () {
    checkboxes( 'visualizar', 'criar', 'editar', 'deletar', 'baixar', 'alerta' );
});

$("#editar").click( function () {
    checkboxes( 'visualizar', 'criar', 'editar', 'deletar', 'baixar', 'alerta' );
});

$("#baixar").click( function () {
    checkboxes( 'visualizar', 'criar', 'editar', 'deletar', 'baixar', 'alerta' );
});

$("#alerta").click( function () {
    checkboxes( 'visualizar', 'criar', 'editar', 'deletar', 'baixar', 'alerta' );
});

$("#deletar").click( function () {
    checkboxes( 'visualizar', 'criar', 'editar', 'deletar', 'baixar', 'alerta' );
});

$("#tblPermissions").on('click', 'tr', function () {
    var row = $( this );
    if ( !row.hasClass( 'emEntrega' ) ) marcarVariasLinhas( row );
});

$("#tblPermissionsGiven").on('click', 'tr', function () {
    var row = $(this);
    marcarVariasLinhas( row );
});

$("#btnAddMenu").click( function () {
    var permissions = tblPermissions;
    var given = tblPermissionsGiven;
    var validar = $("#visualizar").prop('checked');
    if ( permissions.rows('.linhaselecionada').any() && validar ) adicionarMenus( permissions, given );
});

$("#btnRemoveMenu").click( function () {
    var permissions = tblPermissions;
    var given = tblPermissionsGiven;
    if ( given.rows('.linhaselecionada').any() ) removerMenus( permissions, given );
});

$("#btnCheckAll").click( function() {
    var permissions = tblPermissions;
    checkAll( permissions );
});

$("#btnUnCheckAll").click( function() {
    var permissions = tblPermissions;
    checkAll( permissions, false );
});

$("#btnCheckAllGiven").click( function() {
    var given = tblPermissionsGiven;
    checkAll( given );
});

$("#btnUnCheckAllGiven").click( function() {
    var given = tblPermissionsGiven;
    checkAll( given, false);
});

$("#addEmpresa").click( function () {
    var empresas = tblEmpresa;
    if ( !$("#empresas").isEmpty() ) addEmpresas(empresas);
    $("#empresas").val("").trigger('chosen:updated');
});

$("#tblEmpresas").on('click', '#btnRemover', function (e) {
    var trelem = $(this).closest("tr");
    var parent = $(this).parents('tr');
    var empresas = tblEmpresa;
    bootbox.confirm({
        title: "Atenção!",
        message: "Deseja remover está empresa?",
        buttons: {
            confirm: {
                label: "Sim",
                className: "btn-nw-registro"
            },
            cancel: {
                label: "Não",
                className: "btn-nw-geral"
            }
        },
        backdrop: true,
        closeButton: false,
        callback: function (res) {
            if (res) {
                removeEmpresa( empresas, trelem, parent );
            }
        }
    });
});

$("#tblEmpresas").on('click', '#addPermissoes', function (e) {
    var parent = $(this).parents('tr');
    modalPermissoes( parent );
    e.stopPropagation();
});

$("#btnSalvarPermissoes").click( function ( e ) {
    var permissions = tblPermissions;
    var given = tblPermissionsGiven;
    if ( given.rows().any() ) salvarPermissoes( permissions, given );
});

$("#modal_permissoes").on('hidden.bs.modal', function(){
    var permissions = tblPermissions;
    var given = tblPermissionsGiven;
    limparTabelas( permissions, given );
});

$("#btnSubmit").click( function( e ) {
    var empresas = tblEmpresa;
    var empresa_padrao = $("#empresa_id").val();
    if( !isEmpty(empresa_padrao) ) {
        if( empresas.rows().any() ) {
            validarSubmit( empresas, e );
        } else {
            e.preventDefault();
            bootbox.alert("Por favor, vincule alguma empresa com permissões ao usuário.");
        }
    }else{
        e.preventDefault();
        bootbox.alert('Por favor, inclua uma empresa padrão ao usuário, ela deve estar dentre as empresa(s) que o mesmo tem acesso.');
    }
});

function getEmpresas() {
    var grupo    = $("#grupos").val();
    var $empresa = $("#empresas");
    $empresa.empty().trigger('chosen:updated');
    $("#empresa_id").empty().trigger('chosen:updated');
    if( !grupo.isEmpty() ) {
        var url = root + "/ajax.empresas?grupo=:grupo";
        var url = url.replace(':grupo', grupo);
        var html = "<option value=''>Selecione</option>";
        ajaxGenerator( url, 'GET', function ( data ) {
            $.each( data, function ( i, val ) {
                html += "<option value='" + val.id + "'>" + val.nome_informal + "</option>";
            });
            $empresa.append(html).trigger('chosen:updated');
            $("#empresa_id").append(html).trigger('chosen:updated');
            if( typeof empresa_usuario !== "undefined" ) $("#empresa_id").val(empresa_usuario).trigger('chosen:updated');
        }, null, null, true );
    } else {
        $("#empresas").empty().trigger('chosen:updated');
    }
}

function addEmpresas ( table ) {
    var empresa_id = $("#empresas").val();
    var $empresa = $("#empresas").find("option:selected");

    table.row.add([
        empresa_id,
        $empresa.text(),
        botoes
    ]).draw();

    $empresa.prop('disabled', true).trigger('chosen:updated');
    criarInputs( table, 'empresa' );
}

function removeEmpresa( table, trelem, parent ) {
    var id = table.row(parent).data()[0];

    $( $("#empresasconteudo").find("#" + id) ).remove();

    if (trelem.context.id == "btnRemover") {
        table.row(parent).remove().draw();
    }

    filterInput( table );
}

function filterInput( table ) {
    $("#empresas option").filter(function() {
        $(this).removeAttr('disabled');
        var that = $(this);
        $("#empresas").trigger('chosen:updated');
        table.rows().every(function(i, x) {
            var data = this.data();
            if (data[0] == that.val()) {
                that.prop('disabled', true).trigger('chosen:updated');
            }
        });
    }).trigger('chosen:updated');
}

function criarInputs( table, name, array = null ) {
    if ( table.rows().any() ) {
        table.rows().every( function() {
            var d = this.data();
            var find = $("#empresasconteudo").find($("#" + d[0])).length;
            $("#empresas option").filter( function() {
                if( $(this).val() == d[0] ) {
                    $(this).prop('disabled',true).trigger('chosen:updated');
                    return false;
                }
            });

            if( find == 0 ) {
                $("#empresasconteudo").append("<input type='text' name='empresapermission[]' id='" + d[0] + "' />");
            }
            if( array != null ) {
                var perm = [];
                $.each(array, function( i,menu ) {
                    if( menu.empresa_id == d[0] ) {
                        perm.push({
                            "menu_id":      menu.menu_id,
                            "parent_id":    menu.parent_id,
                            "titulo":       menu.titulo,
                            "empresa_id":   menu.empresa_id,
                            "visualizar":   menu.visualizar,
                            "criar":        menu.criar,
                            "editar":       menu.editar,
                            "baixar":       menu.baixar,
                            "alerta":       menu.alerta,
                            "deletar":      menu.deletar
                        });
                    }
                });
                $( $("#empresasconteudo").find("#" + d[0]) ).val( JSON.stringify( perm ) );
            }
        });
    }
}

function checkboxes( visualizar, criar, editar, deletar, baixar, alerta ) {
    var cri = $("#" + criar);
    var edi = $("#" + editar);
    var bai = $("#" + baixar);
    var ale = $("#" + alerta);
    var del = $("#" + deletar);

    if ( typeof visualizar === "string" ) {
        var vis = $("#" + visualizar);

        if ( cri.prop('checked') ) vis.prop( 'checked', true );
        if ( edi.prop('checked') ) vis.prop( 'checked', true );
        if ( bai.prop('checked') ) vis.prop( 'checked', true );
        if ( ale.prop('checked') ) vis.prop( 'checked', true );
        if ( del.prop('checked') ) vis.prop( 'checked', true );

    } else {
        if ( !visualizar.prop('checked') ) {
            cri.prop('checked', false);
            edi.prop('checked', false);
            bai.prop('checked', false);
            ale.prop('checked', false);
            del.prop('checked', false);
        }
    }
}

function adicionarMenus( permission, given ) {
    var vis = $("#visualizar").prop('checked');
    var cri = $("#criar").prop('checked');
    var edi = $("#editar").prop('checked');
    var bai = $("#baixar").prop('checked');
    var ale = $("#alerta").prop('checked');
    var del = $("#deletar").prop('checked');
    var emp_id = $("#emp_id").val();
    var x = 0;

    var data = permission.rows('.linhaselecionada').data();
    $.each(data, function (i, menu) {
        let existe = exist( financeiro, 'id', menu[0] );
        let extale = exist( alertas, 'id', menu[0] );
        let isPed = exist( pedido, "id", menu[0] );

        if( extale ) rta = ale == true;
        else rta = false;

        if( existe ) rig = bai == true;
        else rig = false;

        if ( isPed ) hdl = false;
        else hdl = del == true;

        given.row.add([
            menu[0],
            menu[1],
            emp_id,
            menu[2],
            checked,
            cri == true ? checked : remove,
            edi == true ? checked : remove,
            rig == true ? checked : remove,
            rta == true ? checked : remove,
            hdl == true ? checked : remove
        ]);
    });
    given.draw();
    permission.$('tr.linhaselecionada').each(function () {
        $(this).removeClass('linhaselecionada');
        $(this).addClass('emEntrega');
    });

    $("#visualizar").prop('checked', false);
    $("#criar").prop('checked', false);
    $("#editar").prop('checked', false);
    $("#baixar").prop('checked', false);
    $("#alerta").prop('checked', false);
    $("#deletar").prop('checked', false);
}

function removerMenus( permissions, given ) {
    given.$('tr.linhaselecionada').each(function () {
        var givData = given.rows( $(this) ).data().flatten();
        var that = $(this);
        permissions.$('tr.emEntrega').each( function () {
            var perData = permissions.rows( $(this) ).data().flatten();
            if (perData[0] == givData[0]) {
                $( this ).removeClass('emEntrega');
                given.row( that ).remove();
            }
        });
    });
    given.draw();
}

function salvarPermissoes( permissions, given ) {
    var permitidos = [];
    var empresa_id = $("#emp_id").val();
    var input = $("#empresasconteudo").find("#" + empresa_id);

    given.rows().every(function () {
        var data = this.data();
        permitidos.push({
            "menu_id":      data[0],
            "parent_id":    data[1],
            "empresa_id":   empresa_id,
            "titulo":       data[3],
            "visualizar":   data[4].includes('check') ? 1 : 0,
            "criar":        data[5].includes('check') ? 1 : 0,
            "editar":       data[6].includes('check') ? 1 : 0,
            "baixar":       data[7].includes('check') ? 1 : 0,
            "alerta":       data[8].includes('check') ? 1 : 0,
            "deletar":      data[9].includes('check') ? 1 : 0
        });
    });
    var input = $(input).val("");
    input.val(JSON.stringify(permitidos));
    limparTabelas(permissions, given);
    $("#modal_permissoes").modal('toggle');
}

function limparTabelas( permissions, given ) {
    permissions.$('tr.linhaselecionada').removeClass('linhaselecionada');
    permissions.$('tr.emEntrega').removeClass('emEntrega');
    given.clear().draw();
}

function modalPermissoes( parent ) {
    var id = tblEmpresa.row(parent).data()[0];
    var empresa = tblEmpresa.row(parent).data()[1];
    var given = tblPermissionsGiven;
    $("#empresa_desc").val(empresa);
    $("#emp_id").val(id);
    var input = $( $("#empresasconteudo").find("#" + id) ).val();
    var permissoes = isEmpty(input) ? "" : JSON.parse(input);
    if( permissoes.length > 0 ) preencherGiven( given, permissoes );

    $("#modal_permissoes").modal('show');
}

function preencherGiven( given, permissoes ) {
    var x = 0;
    $.each(permissoes, function( i, menu ) {
        given.row.add([
            menu.menu_id,
            menu.parent_id,
            menu.empresa_id,
            menu.titulo,
            menu.visualizar == 1 ? checked : remove,
            menu.criar == 1 ? checked : remove,
            menu.editar == 1 ? checked : remove,
            menu.baixar == 1 ? checked : remove,
            menu.alerta == 1 ? checked : remove,
            menu.deletar == 1 ? checked : remove
        ]);
        $("#permission_" + menu.menu_id).addClass('emEntrega');
    });
    given.draw();
}

function checkAll( table, check = true ) {
    var x = 0;
    if( check ) {
        table.rows().every( function() {
            if(! $( table.row(x).node() ).hasClass('emEntrega') && $( table.row(x).node() ).is(':visible') ) {
                $( table.row(x).node() ).addClass('linhaselecionada');
            }
            x++;
        });
    } else {
        table.$('tr.linhaselecionada').removeClass('linhaselecionada');
    }
}

function validarSubmit( empresas,e ) {
    var info = [];
    var empresa_padrao = $("#empresa_id").val();
    var validado = false;
    empresas.rows().every( function() {
        var data = this.data();
        var perm = $($("#empresasconteudo").find("#" + data[0])).val();
        if( !isEmpty(perm) ) {
            info.push({
                "empresa_id":data[0],
                "empresa":data[1]
            });
            if(data[0] == empresa_padrao){
                validado = true;
            }
        } else {
            e.preventDefault();
            bootbox.alert('Existe uma ou mais empresas sem permissões, por favor insira alguma ou a remova.');
            return false;
        }
    });
    $("#empresas_list").val(JSON.stringify(info));
    if( validado ) {
        return validado;
    } else {
        e.preventDefault();
        bootbox.alert('A empresa padrão deve estar entres as empresas que este usuário tem acesso.');
    }
    return false;
}

function mostrarEditarMenus() {
    var permitidos = !$("#menus_permitidos").isEmpty() ? JSON.parse($("#menus_permitidos").val()) : [];
    criarInputs( tblEmpresa, 'empresa', permitidos );

}

function initFunctions() {
    if( !$("#grupos").isEmpty() ) getEmpresas();
    unFinanceiros();
    unAlertas();
}

function unFinanceiros() {
    var $financeiros = $("#financeiros");

    if( !$financeiros.isEmpty() ) {
        financeiro = JSON.parse( $financeiros.val() );
    }
}

function unAlertas() {
    var $alertas = $("#alertas");

    if ( !$alertas.isEmpty() ) {
        alertas = JSON.parse( $alertas.val() );
        pedido = alertas.filter(alerta => alerta.descricao == 'pedido.index');
    }
}

function exist( myObj, index, value ) {
    if( typeof myObj !== "object" ) return false;
    var size = Object.keys(myObj).length;

    for ( var i = 0; i < size; i++ ) {
        if( myObj[i][index] == value ) return true;
    }

    return false;
}

<script>
    $( document ).ready( function () {

        if (window.top !== window.self)  {
            $("body").html("");
            bootbox.alert('Não autorizado. Recarregue a página');
        }
        $(".navbar .menu").slimScroll({
            height: '1450px'
        });
        $().tooltip({container: 'body'})
    });

    {{ $vnativacreate = str_contains(Request::url(), 'vendaativa')}}
    @if((!str_contains(Request::url(), 'edit') && !isset($show)) || $vnativacreate)
        @if (str_contains(Request::url(), '/create'))
            $("#ativo").prop('checked', true);
        @elseif (str_contains(Request::url(),'report'))
            $("#ativo").prop('checked', true);
        @elseif (str_contains(Request::url(),'vendaativa.filtro'))
            $("#ativo").prop('checked', true);
        @endif
    @endif

    var clicks = 0,
        timer = null,
        str = "ABCDEFGHIJKLMNOPQRSTUVWXYZ",
        name = "{{ Auth::user()->name }}",
        alertaMeLiga = "{{ is_null(Session::get('permissoes')->where('descricao','pedido.index')->first()) ? 0 : Session::get('permissoes')->where('descricao','pedido.index')->first()->alerta }}",
        alertaPosition = "{{ is_null(Session::get('permissoes')->where('descricao','report.logcercas')->first()) ? 0 : Session::get('permissoes')->where('descricao','report.logcercas')->first()->alerta }}";

    if ( alertaMeLiga === "1" || alertaPosition === "1" ) {
        setupInterval( function () {
            meLiga(alertaMeLiga);
        }, 60000);
    }

    if( typeof name !== undefined ) {
        var h = parseInt( str.indexOf( name.charAt(0) ) ) * 12;
        var s = 10 + 2.5 * parseInt( str.indexOf( name.charAt(0) ) );
        var iniciais = { h, s };
        var nome = name.charAt(0);
        $('.initial').append('<div id="initial" style="vertical-align: middle;background-color: #154295;" class="user-initial"><div>' + nome + '</div></div>');
        $('.show-profile').append('<div id="initial" style="background-color: #154295;" class="initial-show"><div>' + nome + '</div></div>');
    }

    @if(isset($show) && $show && !isset($nfemitida))
        $("#buscarEndereco").attr('disabled', 'disabled');
        $( document ).ready( function( $ ) {
            $(".btn-nw-registro").attr('disabled', 'disabled');
            $('input[type=submit]').hide();
        });
    @endif

    $("#noti-bell").click(function () {
        Notification.requestPermission();
    });

    $('.notifications-menu').on( 'click', ".notify-alert", function ( e ) {
        e.stopPropagation();
        var that = this;
        clicks++;
        if( clicks === 1 ) {
            timer = setTimeout( function () {
                var $icone = $( that ).find('.icone');
                if( !$icone.hasClass('fa-check') )  var atualizado = atualizarAlerta( that, 'R' );
                clicks = 0;
            }, 200 );
        } else {
            clearTimeout(timer);
            $('.tooltip').tooltip('destroy');
            var atualizado = atualizarAlerta( that, 'A' );
            clicks = 0;
        }
    }).on( 'dblclick', ".notify-alert", function ( e ) {
        e.preventDefault();
    });

    function atualizarAlerta( that, tipo ) {
        let $icone = $( that ).find('.icone');
        let contador = $(".count").text();
        let contadorAva = $(".avaliacoes-count").text();
        let id = $( that ).find('.alert-desc').attr('id');
        let emp = $( that ).find('.alert-desc').attr('emp');
        let url = root + `/readnotification?notification=${id}&status=${tipo}&tipo=`;
        let isDroid = $(that).find('.alert-desc').attr('android') === "true";
        let isMobile = $(that).find('.alert-desc').attr('mobile') === "true";

        if (isDroid) url += "1";
        else if (isMobile) url += "2";
        else url += "3";

        $icone = removeClass($icone);
        $icone.addClass("fa-spinner fa-pulse fa-fw");

        ajaxGenerator( url, 'GET', function ( data ) {
            if ( data === 'OK' ) {
                let $notifBody = $(".custom-notify");
                let $avaBody = $(".avaliacoes-body");
                if ( contador >= 1 ) {
                    let $header = $(".notificacoes");
                    let $count = $(".count");
                    let notificacoes = $notifBody.find('.normal').length;
                    $count.text(notificacoes);
                    if ( notificacoes <= 0 ) {
                        $count.text('');
                        $header.text('Você não possui nenhuma notificação nova.');
                    } else {
                        $header.text(`Você possui ${notificacoes} notificação(ões) novas!`);
                    }
                }

                if ( contadorAva >= 1 ) {
                    let $header = $(".avaliacoes-header");
                    let $count = $(".avaliacoes-count");
                    let avaliacoesLen = $avaBody.find('.normal').length;
                    $count.text(avaliacoesLen);
                    if ( avaliacoesLen <= 0 ) {
                        $count.text('');
                        $header.text('Você não possui nenhuma notificação nova.');
                    } else {
                        $header.text(`Você possui ${avaliacoesLen} notificação(ões) novas!`);
                    }
                }

                if ( tipo == 'R' ) {
                    $icone.removeClass("fa-spinner fa-pulse fa-fw");
                    $icone.addClass('fa-check');
                } else {
                    $icone.addClass('fa-check');
                    $( that ).remove();
                    let exist = $notifBody.find('[emp="'+emp+'"]:eq(0)').attr('id');
                    let existsAva = $avaBody.find('[emp="'+emp+'"]:eq(0)').attr('id');
                    if( exist === undefined ) {
                        $('#empresa_' + emp).remove();
                    }
                    if( existsAva === undefined ) {
                        $('#empresa_app_' + emp).remove();
                    }
                }
            } else {
                console.error('Oops! Something went wrong... We will send a staff of high trained horses to start working on it!!');
            }
        }, null, null, true );
        if( tipo == 'R' ) {
            if( $icone.hasClass('fa-check') ) return true;
            else return false;
        }
    }

    function meLiga() {
        let type = "1";
        if (alertaMeLiga === "1" && alertaPosition === "0") {
            type = "2"
        } else if (alertaMeLiga === "0" && alertaPosition === "1") {
            type = "3"
        }

        var url = `${root}/meliganotification?tipo=${type}`;
        ajaxGenerator( url, 'GET', function( data ) {
            if( data === "null" ) return false;

            let $content = $(".custom-notify");
            let notificacoes = JSON.parse( data );
            let avaliacoes = [];
            let anterior = '';
            let html = '';
            let droid = '<i class="normal fa fa-android text-green icone" aria-hidden="true"></i>';
            let check = '<i class="fa fa-check text-aqua icone" aria-hidden="true"></i>';
            let data_toggle = 'data-toggle="tooltip" data-trigger="hover" data-container="body" ';
            let lef = 'data-placement="left"';
            let bot = 'data-placement="bottom"';
            let count = notificacoes.length;
            let contnoti = 0;
            let existence = 0;
            let contador = 0;
            let dangerCount = 0;
            if( count > 0 ) {
                let len = 0;
                let quant = count;
                for( let i = 0; i < notificacoes.length; i++ ) {
                    const notificacao = notificacoes[i];

                    if (notificacao.dangerlevel == 4) {
                        avaliacoes.push(notificacao);
                        count--;
                        quant--;
                        continue;
                    }

                    let exist = $content.find(`#${notificacao.men_id}`); //Existencia da notificação na barra
                    len = $content.find(`#empresa_${notificacao.empresa_id}`).length; // Existencia da Empresa na barra

                    if( ( anterior == '' || anterior !== notificacao.empresa_id) && len === 0 ) { // Caso não exista
                        html += `<li id="empresa_${notificacao.empresa_id}" class="custom-header">${notificacao.empresa}</li>`;
                    }

                    if (notificacao.appnotification === '1' && notificacao.situacao === 'N' && notificacao.dangerlevel >= 2)
                        dangerCount++;

                    if( exist.length === 0 ) {
                        let icon = droid;
                        let isDroid = true;
                        if ( notificacao.appnotification == '1' ) {
                            icon = getDangerLevel(notificacao);
                            isDroid = false;
                        }
                        if( notificacao.situacao !== 'E' && notificacao.situacao !== 'N' ) {
                            icon = check;
                            quant--;
                            contnoti--;
                        }

                        let str_len = notificacao.nome.length + notificacao.descricao.length;
                        let toggle = str_len >= 90 ? data_toggle + lef : data_toggle + bot;

                        html += `<li><a href="#" class="notify-alert">${icon} `;
                        html += `<span class="alert-desc" title="${notificacao.descricao}" `;
                        html += `android="${isDroid ? "true" : "false"}" mobile=${isDroid ? "false" : "true"} `;
                        html += `${toggle} emp="${notificacao.empresa_id}" `;
                        html += `id="${notificacao.men_id}">`;
                        html += `${notificacao.nome}: ${notificacao.descricao}</span></a></li>`;

                        anterior = notificacao.empresa_id;
                        contnoti++;
                    } else {
                        if ( notificacao.situacao === 'R' ) {
                            let $icone = $(exist).parent().find(".icone");
                            if ( $icone.hasClass('normal') && $icone.hasClass('fa-android') ) {
                                $icone.removeClass('normal fa-android text-green');
                                $icone.addClass('icone fa-check text-aqua');
                            }
                        }
                        existence++;
                        contnoti--;
                        dangerCount--;
                    }
                }

                if( existence <= count ) {
                    let $notificacoes = $(".notificacoes");
                    let $count = $(".count");
                    if ( len > 0 ) $(`#empresa_${anterior}`).after(html);
                    else $content.append(html);

                    count = $content.find('.normal').length - existence;
                    contador = $count.text();
                    if ( contador.isEmpty() && count > 0 ) {
                        $notificacoes.text(`Você tem ${count} notificação(ões) novas!`);
                        $count.text(count);
                    } else if( !contador.isEmpty() && contador > 0 ) {
                        count += parseInt(existence);
                        $count.text(count);
                        $notificacoes.text(`Você tem ${count} notificação(ões) novas!`);
                    } else if( count <= 0 ) {
                        $count.text('');
                        $notificacoes.text('Você não tem nenhuma notificação nova.');
                    }
                }

                if ( contnoti > 0 ) {
                    if ( Notification.permission == 'granted' ) {
                        let msg = "Sistema Nacional Gás!";
                        let options = {
                            body: 'Você possui ' + contnoti + ' mensagens novas!',
                            data: {
                                dateOfArrival: Date.now(),
                                primaryKey: 1
                            }
                        };
                        let n = new Notification(msg, options);
                    }
                }

                if (dangerCount > 0) {
                    if ( Notification.permission == 'granted' ) {
                        let msg = "Sistema Nacional Gás!";
                        let options = {
                            body: 'Você possui 1 ou mais pedidos do aplicativo com nível de alerta ou perigo!',
                            data: {
                                dateOfArrival: Date.now(),
                                primaryKey: 1
                            }
                        };
                        let noti = new Notification(msg, options);
                    }
                }

                if (avaliacoes.length > 0) renderAvaliacoes(avaliacoes);
            }
        }, null, null, true );
    }

    function getDangerLevel(notificacao) {
        let info = '<i class="icone normal fa fa-exclamation-circle text-aqua" aria-hidden="true"></i>';
        let alert = '<i class="icone normal fa fa-exclamation-triangle text-alert" aria-hidden="true"></i>';
        let danger = '<i class="icone normal fa fa-exclamation text-danger" aria-hidden="true"></i>';

        switch (notificacao.dangerlevel) {
            case '1':
                return info;
            case '2':
                return alert;
            default:
                return danger;
        }
    }

    function removeClass($icone) {
        let arr_classes = [
            "fa-exclamation-circle",
            "fa-exclamation-triangle",
            "fa-exclamation",
            "fa-list",
            "fa-car",
            "fa-stethoscope",
            "fa-cogs",
            "fa-android",
            "fa-check",
        ];
        let classe = "normal";

        for (let i = 0; i < arr_classes.length; i++) {
            const element = arr_classes[i];
            if ($icone.hasClass(element)) {
                console.log("removeu");
                $icone.removeClass(`${classe} ${element}`);
            }
        }
        return $icone;
    }

    function setupInterval( callback, interval ) {
        var chave = '_intervalInMins_';
        var agora = Date.now();
        var mins  = localStorage.getItem( chave );
        executarCallback = function () {
            localStorage.setItem( chave, interval );
            callback();
        };
        if( mins ) {
            var time = parseInt(mins);
            var delta = agora - time;
            if( delta > interval ) {
                setInterval( 'executarCallback()', interval );
            } else {
                setTimeout( function () {
                    executarCallback();
                    setInterval( 'executarCallback()', interval );
                }, interval - delta);
            }
        } else {
            setInterval( 'executarCallback()', interval );
        }
        localStorage.setItem( chave, agora );
    }

    function renderAvaliacoes( avaliacoes ) {
        let $header = $(".avaliacoes-header");
        let $content = $(".avaliacoes-body");
        let $count = $(".avaliacoes-count");
        let anterior = '';
        let html = '';
        let icon = '<i class="icone normal fa fa-exclamation-circle text-aqua" aria-hidden="true"></i>';;
        let check = '<i class="fa fa-check text-aqua icone" aria-hidden="true"></i>';
        let count = avaliacoes.length;
        let quant = avaliacoes.length;
        let existence = 0;
        let len = 0;

        for (let i = 0; i < avaliacoes.length; i++) {
            const avaliacao = avaliacoes[i];

            let exist = $content.find(`#${avaliacao.men_id}`); //Existencia da notificação na barra
            len = $content.find(`#empresa_app_${avaliacao.empresa_id}`).length; // Existencia da Empresa na barra

            if( ( anterior == '' || anterior !== avaliacao.empresa_id) && len === 0 ) { // Caso não exista
                html += `<li id="empresa_app_${avaliacao.empresa_id}" class="custom-header">${avaliacao.empresa}</li>`;
            }

            if (exist.length <= 0) {
                let isRead = avaliacao.situacao !== 'E' && avaliacao.situacao !== 'N';

                if( isRead ) quant--;

                html += `<li><a href="#" class="notify-alert">${isRead ? check : icon} `;
                html += `<span class="alert-desc" title="${avaliacao.descricao}" `;
                html += `android="false" mobile="true" emp="${avaliacao.empresa_id}" `;
                html += `id="${avaliacao.men_id}">`;
                html += `${avaliacao.nome}: ${avaliacao.descricao}</span></a></li>`;
                anterior = avaliacao.empresa_id;
            } else {
                if ( avaliacao.situacao === 'R' ) {
                    let $icone = $(exist).parent().find(".icone");
                    if ( $icone.hasClass('normal') && $icone.hasClass('fa-android') ) {
                        $icone.removeClass('normal fa-android text-green');
                        $icone.addClass('icone fa-check text-aqua');
                    }
                }
                existence++;
            }
        }

        if( existence <= count ) {
            if ( len > 0 ) $(`#empresa_app_${anterior}`).after(html);
            else $content.append(html);

            count = $content.find('.normal').length - existence;
            contador = $count.text();
            if ( contador.isEmpty() && count > 0 ) {
                $header.text(`Você tem ${count} notificação(ões) novas!`);
                $count.text(count);
            } else if( !contador.isEmpty() && contador > 0 ) {
                count += parseInt(existence);
                $count.text(count);
                $header.text(`Você tem ${count} notificação(ões) novas!`);
            } else if( count <= 0 ) {
                $count.text('');
                $header.text('Você não tem nenhuma notificação nova.');
            }
        }

    }
// 90
</script>

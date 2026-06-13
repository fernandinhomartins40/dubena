<style>
body, html {
	height: 100%;
}

body {
	margin: 0;
	font: normal 11px Tahoma, Verdana, Helvetica, sans-serif;
	color: #333;
	background-color: #f7f7f7;
}

input[type="radio"] {border:none} /* No border */

input[type="checkbox"] {border:none} /* No border */

#comunicado a{
	color: #444444;
	text-decoration: none;
}

#comunicado a:hover { text-decoration: underline; }

#tarefas-home a {
	color: #333;
	text-decoration: none;
}

#tarefas-home a:hover { font-weight: bold;}

#docs-home a {
	color: #666;
	text-decoration: none;
}

#docs-home a:hover {text-decoration:underline}

/*s menu */

#menu                 { width: 170px; float: left; font: 11px tahoma; padding-right: 0px;}
#menu ul,li           { list-style: none; margin: 0px; }
#menu ul              { padding: 10px 0px 0px 0px; background: #F7F7F7 url('../_images/01_top.gif') no-repeat; }
#menu li              { height: 40px; background: #F7F7F7 url('../_images/02_bg_off.gif') no-repeat; }
#menu li a            { width: 128px; height: 14px; display: block; margin: 0px 10px 0px 12px; padding: 8px 10px; color: #8A8A8A; text-decoration: none; }
#menu li a:hover      { color: #666; background: #F7F7F7; }
#menu li.show         { background: #F7F7F7 url('../_images/02_bg_on.gif') no-repeat; }
#menu li.show a       { color: #FFFFFF; font-weight: bold; }
#menu li.show a:hover { color: #FFFFFF; background: none !important; }

/* estilos */

.alterar a, .remover a{
	font-size: 11px;
	color: #999;
	text-decoration: none;
}
.alterar a:hover {color: #d98f3b}
.remover a:hover {color: #cc0000}

.cor1 {
	color: #7e7e00;
}

.cor2 {
	color: #999900;
}

.cor3 {
	color: #999;
}

.cor4 {
	color: #D5D5D5;
}

.cor5 {
	color: #fffbd9;
}

.cor6 {
	color: #555555;
}

.red {
	color: #cc0000;
}

.black {
	color: #444444;
}

.cabecalho {
    background-color: #CCCCCC;
}

.cabecalho2 {
    background-color: #D8D8D8;
}

/* abas */

#abas a{
	color: #444444;
	text-decoration: none;
}

#abas a:hover { color: #666; }

#bt-aba-on {
	margin-left: 8px;
	padding: 8px;
	padding-bottom: 15px;
	border-top: solid 2px #fff;
	height: 17px;
	background-color: #001eee;
	text-align: center;
	color: #fff;
}

#bt-aba-on a { color: #444444 }


/* anchor */

a{
	color: #555555;
	text-decoration: none;
	cursor:pointer;
}

a:hover {
	text-decoration: underline;
	cursor:pointer;
}
td{
	color: #555555;
	text-decoration: none;
}


/* paginacao */

#paginacao {
	margin-left: 170px;
}

#paginacao #pags a{
	margin-right: 4px;
}

#paginacao #pags a:hover {
	color: #95ba65;
}

#paginacao #pags .visited {
	font-weight: bold;
	padding: 2px;
	color: #fff;
	background-color: #95ba65;
}


/* forms */

input, textarea{
	padding: 2px;
	border: inset 1px;
	font: normal 11px Tahoma, Verdana, Helvetica, sans-serif;
	color: #666;
}

dl { width: 300px; }
dl,dd { margin: 0; }
dt { background: #F39; font-size: 18px; padding: 5px; margin: 2px; }
dt a { color: #FFF; }
dd a { color: #000; }
ul { list-style: none; padding: 5px; }

.progresso_verde
{
	background-image:url('../_images/verde.png');
	background-repeat:repeat-x;
	border-left: solid 1px #708090 ;
	text-align:center;
}
.progresso_vermelho
{
	background-image:url('../_images/vermelho.png');
	background-repeat:repeat-x;
	border-left: solid 1px #708090 ;
	text-align:center;
}

/*****************************************************
	CSS PARA MAPA CERCA ELETRONICA
******************************************************/


#map { position: relative;
	width: 100%;
	height: 420px;
}

{ /* Only for IE */
	behavior:url(#default#VML);
}

/* .tooltip { 
	text-align: center;
	opacity: .70;
	-moz-opacity:.70;
	filter:Alpha(opacity=70);
	white-space: nowrap;
	margin: 0;
	padding: 2px 0.5ex;
	border: 1px solid #000;
	font-weight: bold;
	font-size: 9pt;
	font-family: Verdana;
	background-color: #fff;
} */

td.off {
background: #F7F7F7;
}

/**
d4d4d4
**/
td.on {
background: #FFFBD9;
}

/*********************************
 	Window Quick Search
**********************************/

#window
{
	position: absolute;
	left: 200px;
	top: 100px;
	width: 400px;
	height: 300px;
	overflow: auto;
	display: none;
	z-index: 9999;
}
#windowTop
{
	height: 30px;
	overflow: 30px;
	background-image: url(../_images/window_quick_search/window_top_end.png);
	background-position: right top;
	background-repeat: no-repeat;
	position: relative;
	overflow: hidden;
	cursor: move;
}
#windowTopContent
{
	margin-right: 13px;
	background-image:url(../_images/window_quick_search/window_top_start.png);
	background-position:left top;
	background-repeat: no-repeat;
	overflow: hidden;
	height: 30px;
	line-height: 30px;
	text-indent: 10px;
	font-family:Arial, Helvetica, sans-serif;
	font-weight: bold;
	font-size: 14px;
	color: #555555;
}
#windowMin
{
	position: absolute;
	right: 25px;
	top: 10px;
	cursor: pointer;
}
#windowMax
{
	position: absolute;
	right: 25px;
	top: 10px;
	cursor: pointer;
	display: none;
}
#windowClose
{
	position: absolute;
	right: 10px;
	top: 10px;
	cursor: pointer;
}
#windowBottom
{
	position: relative;
	height: 270px;
	background-image: url(../_images/window_quick_search/window_bottom_end.png);
	background-position: right bottom;
	background-repeat: no-repeat;
}
#windowBottomContent
{
	position: relative;
	height: 270px;
	background-image: url(../_images/window_quick_search/window_bottom_start.png);
	background-position: left bottom;
	background-repeat: no-repeat;
	margin-right: 13px;
}
#windowResize
{
	position: absolute;
	right: 3px;
	bottom: 5px;
	cursor: se-resize;
}
#windowContent
{
	position:absolute;
	top: 30px;
	left: 10px;
	width: auto;
	height: auto;
	overflow: auto;
	margin-right: 10px;
	border: 1px solid #666666;
	height: 255px;
	width: 375px;
	font-family:Arial, Helvetica, sans-serif;
	font-size: 11px;
	background-color: #fff;
}
#windowContent *
{
	margin: 2px;
}
.transferer2
{
	border: 1px solid #6BAF04;
	background-color: #B4F155;
	filter:alpha(opacity=30);
	-moz-opacity: 0.3;
	opacity: 0.3;
}


/*DIV�s Drag and Drop*/
.str { color: #00A; }
.kwd { color: #808; }
.com { color: #777; }
.typ { color: #088; }
.lit { color: #800; }
.pun { color: #000; }
.pln { color: #002; }
.tag { color: #008; }
.atn { color: #606; }
.atv { color: #080; }
.dec { color: #606; }

.box {
	padding: 10px 20px;
	background: #AAA;
	border: 1px solid #AAA;
	text-align: center;
	font-size: 10px;
	margin: 0 0 10px 0;
	}

.drag {
	border-style: solid;
	border-width: 1px;
	border-top-width: 3px;
	border-color: #DCDCDC;
	height: 28px;
	width: 90px;
	float: left;
	margin: 1px;
	cursor: move;
	font-size: 11px;
	text-align: center;
	}
	.drop .drag {
		background: #FFFBD9;
		border-color: #DCDCDC;
		border-top-color: #FFCC00;
		border-top-width: 2px;
		height: 28px;
		width: 90px;
		font-size: 11px;
		}
.drop {
	border-style: solid;
	border-width: 1px;
	border-top-width: 3px;
	border-color: #D3D3D3;
	height:200px;
	width:240px;
	float: left;
	background: #FFFFFF;
	margin: 5px;
	padding: 5px;
	overflow: auto;
	}
.ghost {
	position: fixed;
	filter:alpha(opacity=50);
	-moz-opacity: 0.5;
	opacity: 0.5;
	background-color: #FFFBD9;
	border-color: #DCDCDC;
	color: #FFCC00;
	}
.outline {
	background-color: #FFFBD9;
	border-color: #FFCC00;
	border-style: dashed;
	color: #DAA;
	}
.active {
	background-color: #FFCC00;
	border-color: #FFFBD9;
	}
.nodrop {
	height:100%;
	width:100%;
	float: center;
	background: #F5F5F5;
	}
.log {
	border: 1px solid #AAA;
	padding: 10px;
	overflow: auto;
	height: 160px;
	width: 464px;
	}
/*body{
	padding: 0 5px;
	font-family: Verdana, sans-serif;
	background-color: #DDD;
}
ul, li, h3, h2, h1, p{
	padding:0;
	margin:0;
	list-style:none;
}*/

.sidebar{
	position:absolute;
	right:5px;
	top:15px;
}

#links{
	border:1px solid black;
	/*width:210px;*/
	padding:10px;
	background-color:white;
}
#links h3{
	color:#933;
}
#links ul{
	padding: 8px 0 3px 20px;
}
#links li{
	list-style-type:circle;
}
#links a{
	color:#69C;
}

h1{
	margin:20px 0;
	color:#5B739C;
}
h1 strong{
	font-size:13px;
	color:#777;
}
h2.title{
	color:#933;
	margin-bottom:10px;
	text-align:right;
}
.clear{
	clear:left;
}
#navigation, #content,.section{
	padding:0;
	margin:0;
	list-style:none;
}
#content{
	font-family: Tahoma;
	overflow:hidden;
	width:75%;
	background-color:#FFFFFF;
	position:relative;
	height:180px;
	float:left;
}
	#content h2{
		color:#555555;
		padding-left:0px;
		/*margin:10px;*/
	}
	#content a{
		color:#777;
		font-weight:bolder;
		text-decoration:none;
	}
#navigation{
	float:left;
	width:25%;
	overflow:auto;
	height:180px;
	background-color:#FFFBD9;
	/*background-image: url("../../_images/bg_tbl.gif");
	background-repeat: repeat-x-y;*/

}
	#navigation .sup{
		padding-top:10px;
		padding-left:10px;
		font-weight:bolder;
		font-family: Tahoma;
	}
		#navigation ul{
			list-style:circle;
			padding-left:15px;
		}
		#navigation a{
			color:#555555;
			/*font-weight:bolder;*/
			text-decoration:none;
		}
		#navigation .sup li{
			font-size:11px;
			font-weight:normal;
		}
		#navigation a.scrolling{
			color:#933;
		}

.section{
	width: 95%;
	position:relative;
}

	.section .sub{
		position:relative;
		float:left;
		padding-left:15px;
		width:100%;
		height:180px;
	}

		.section .next, .section .prev, .section .pag{
			font-size:16px;
			position:absolute;
			bottom:0px;
			letter-spacing:0px;
		}
		.section .next{
			right:30px;
			padding-bottom: 3px;
		}
		.section .prev{
			left:30px;
			padding-bottom: 3px;
		}
		.section .pag{
			right:50%;
			cursor:default;
			padding-bottom: 3px;
			font-size:10px;
			letter-spacing:0px;
		}



</style>

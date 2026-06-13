
@extends('layouts.nomenu')

@section('content')
<link href="{{URL::to('plugins/chosen/chosen.css')}}" rel="stylesheet" type="text/css" />
<link href="{{URL::to('plugins/tree-multiselect.min.css')}}" rel="stylesheet" type="text/css" />
<link href="{{URL::to('plugins/jstree/dist/themes/default/style.min.css')}}" rel="stylesheet" type="text/css" />
<style>
	.popupModal .modal-dialog{width:53%;}
	@media screen and (min-width: 768px) {
		.popupModal .modal-dialog{width:600px;}
		.btn-submit:hover {
		background-color: #a58f2a;
		}
		.btn-submit {
		display: block;
		padding: 12px;
		width: 100%;
		color: #fff;
		border: 0;
		margin-top: 40px;
		background-color: #f58f2a;
		}
		div.show-image {
			position: relative;
			float:left;
			margin:5px;
		}
		div.show-image:hover img{
			opacity:0.5;
		}
		div.show-image:hover input {
			display: block;
		}
		div.show-image input {
			position:absolute;
			display:none;
		}
		div.show-image input.capture {
			top:0;
			left:0;
		}
		div.show-image input.load {
				top:25px;
				left:0;
		}
		.checkbox input[type="checkbox"], .checkbox-inline input[type="checkbox"]{
			margin-left: 0px;
		}
		.scroll-container {
		width: 584px;
		height: 350px;
		margin: 1rem 0 1rem;
		overflow: hidden;
		}
	}
</style>
<div id="mainContent" class="content">
@include('financeiro.financeiro_form_partial')
</div>
<!-- DATA TABES SCRIPT -->
<script src="{{URL::to('plugins/jQuery/jQuery-2.1.4.min.js')}}"></script>
<!-- Bootstrap 3.3.2 JS -->
<script src="{{URL::to('bootstrap/js/bootstrap.min.js')}}" type="text/javascript"></script>
<!-- AdminLTE App -->
<script src="{{URL::to('dist/js/app.min.js')}}" type="text/javascript"></script>

<script src="{{URL::to('plugins/datepicker1/moment/moment-with-locales.js')}}" type="text/javascript"></script>
<script src="{{URL::to('plugins/datepicker1/js/bootstrap-datetimepicker.min.js')}}" type="text/javascript"></script>


<script src="{{URL::to('plugins/datatables/jquery.dataTables.min.js')}}" type="text/javascript"></script>
<script src="{{URL::to('plugins/datatables/dataTables.bootstrap.min.js')}}" type="text/javascript"></script>
<script src="{{URL::to('plugins/slimScroll/jquery.slimscroll.min.js')}}" type="text/javascript"></script>
<script src="{{URL::to('plugins/fastclick/fastclick.min.js')}}"></script>
<script src="{{URL::to('plugins/custom_utils.js')}}"></script>
<script src="{{URL::to('plugins/bootbox.min.js')}}"></script>
<script src="{{URL::to('plugins/chosen/chosen.jquery.latin.js')}}"></script>
<script src="{{URL::to('plugins/selectize/js/standalone/selectize.min.js')}}"></script>
<script src="{{URL::to('plugins/tree-multiselect.min.js')}}"></script>
<script src="{{URL::to('plugins/jstree/dist/jstree.min.js')}}"></script>
<script src="{{URL::to('plugins/handsontable/dist/handsontable.full.js')}}"></script>

<script src="{{URL::to('js/jquery.mask.min.js')}}"></script>
<script src="{{URL::to('plugins/input-mask/jquery.inputmask.js')}}"></script>


<script src="{{URL::to('js/custom.js')}}"></script>
<script src="{{URL::to('js/jqueryMaskMoney.js')}}"></script>
<script src="{{URL::to('js/shortcut.js')}}"></script>

@include('financeiro.financeiro_form_partial_js')
@endsection

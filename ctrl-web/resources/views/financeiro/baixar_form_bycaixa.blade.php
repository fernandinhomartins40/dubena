
@extends('layouts.nomenu')

@section('content')
<link href="{{URL::to('plugins/chosen/chosen.css')}}" rel="stylesheet" type="text/css" />
<link href="{{URL::to('plugins/tree-multiselect.min.css')}}" rel="stylesheet" type="text/css" />
<link href="{{URL::to('plugins/jstree/dist/themes/default/style.min.css')}}" rel="stylesheet" type="text/css" />
<style>
	.popupModal .modal-dialog{width:53%;}
	@media screen and (min-width: 768px) {
		.popupModal .modal-dialog{
			width:600px;
		}
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
			height: 350px;
			margin: 1rem 0 1rem;
			overflow: hidden;
		}
	}
</style>
<div id="mainContent" class="content">
@include('financeiro.baixar_form_partial')
</div>
@include('financeiro.baixar_form_partial_js')
@endsection

@extends('layouts.app')

@section('content')

<users users-model="{{ $users }}" uf-prop="{{ $uf }}"></users>

@endsection
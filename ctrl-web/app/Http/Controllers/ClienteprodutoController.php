<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Clienteproduto;
use App\Http\Requests;

class ClienteprodutoController extends Controller
{
    function buscaPrecos($cliente_id)
    {
        return Clienteproduto::where('cliente_id', $cliente_id)->get();
    }
}

<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class DescontochequeController extends Controller
{
    public function index()
    {
    	return View('cheque.desconto.descontocheques');
    }
}

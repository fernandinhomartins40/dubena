<?php

namespace App\Api\Http\Controllers;

use App\Http\Controllers\Controller;

use Illuminate\Http\Request;

class PassportController extends Controller
{

    public function index() 
    {
        return view('passport.index');
    }

}


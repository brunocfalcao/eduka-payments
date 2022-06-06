<?php

namespace Eduka\Payments\Controllers;

use App\Http\Controllers\Controller;

class PaylinkController extends Controller
{
    public function __construct()
    {
        //
    }

    public function checkout()
    {
        return view('site::default');
    }
}

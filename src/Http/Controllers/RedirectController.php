<?php

namespace Eduka\Payments\Http\Controllers;

class RedirectController
{
    public function __invoke()
    {
        return view('course::thanks-for-buying');
    }
}

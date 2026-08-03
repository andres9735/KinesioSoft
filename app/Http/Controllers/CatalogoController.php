<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

class CatalogoController extends Controller
{
    /**
     * Muestra la pantalla principal del módulo Catálogos.
     */
    public function index(): View
    {
        return view('catalogos.index');
    }
}

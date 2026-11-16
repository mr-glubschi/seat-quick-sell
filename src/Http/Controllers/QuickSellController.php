<?php

namespace Glubschi\Seat\QuickSell\Http\Controllers;

use Illuminate\Contracts\View\View;
use Seat\Web\Http\Controllers\Controller;

class QuickSellController extends Controller
{
    /** The Quick Sell page. Milestone 1: an empty page that proves install, menu and permission work. */
    public function index(): View
    {
        return view('quicksell::index');
    }
}

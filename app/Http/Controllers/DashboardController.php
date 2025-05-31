<?php
namespace App\Http\Controllers;

use App\Services\ErpApiService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class DashboardController extends Controller
{
    private ErpApiService $erpApiService;

    public function __construct(ErpApiService $erpApiService)
    {
        $this->erpApiService = $erpApiService;
    }

    public function index(Request $request)
    {
        return view('dashboard.index');
    }
}
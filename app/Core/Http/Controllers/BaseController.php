<?php

namespace App\Core\Http\Controllers;

use App\Core\Traits\ApiResponse;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Routing\Controller;


abstract class BaseController extends Controller
{
    use AuthorizesRequests;
    use ApiResponse;
}

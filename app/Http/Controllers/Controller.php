<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests; // For Laravel 9+
// For older Laravel versions, you might also see: use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Routing\Controller as BaseController;

class Controller extends BaseController
{
    use AuthorizesRequests, ValidatesRequests; 
    // For older Laravel, it might be: use AuthorizesRequests, DispatchesJobs, ValidatesRequests;
}
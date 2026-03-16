<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\View;
use App\Core\DB;
use App\Models\Website;


require_once __DIR__ . '/../Core/Auth.php';
require_once __DIR__ . '/../Core/View.php';
require_once __DIR__ . '/../Models/Website.php';

class DashboardController
{
    public function index()
    {
        Auth::requireLogin();

        $userId = Auth::userId();
        $isAdmin = Auth::role() === 'admin';

        $stats = Website::getDashboardStats($userId, $isAdmin);

        View::render('dashboard/index', ['stats' => $stats]);
    }
}

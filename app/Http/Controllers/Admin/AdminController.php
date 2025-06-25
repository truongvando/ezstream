<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ServicePackage;
use App\Models\VpsServer;

class AdminController extends Controller
{
    public function vpsServers()
    {
        $vpsServers = VpsServer::all();
        return view('admin.vps-servers', compact('vpsServers'));
    }

    public function servicePackages()
    {
        $servicePackages = ServicePackage::all();
        return view('admin.service-packages', compact('servicePackages'));
    }
}

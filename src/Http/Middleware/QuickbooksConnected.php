<?php

namespace Codemonkey76\Quickbooks\Http\Middleware;

use Illuminate\Contracts\Routing\UrlGenerator;
use Illuminate\Contracts\Session\Session;
use Illuminate\Http\Request;
use Illuminate\Routing\Redirector;
use Spinen\QuickBooks\Client;

class QuickbooksConnected
{
    protected $quickbooksClient;
    protected $redirector;
    protected $session;
    protected $url_generator;

    public function __construct()
    {
        
    }
    
}
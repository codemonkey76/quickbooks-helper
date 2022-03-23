<?php

namespace Codemonkey76\Quickbooks\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Routing\Redirector;
use Illuminate\Contracts\Session\Session;
use Illuminate\Contracts\Routing\UrlGenerator;
use Codemonkey76\Quickbooks\QuickbooksClient;

class QuickbooksConnected
{
    protected QuickbooksClient $quickbooksClient;
    protected Redirector $redirector;
    protected Session $session;
    protected UrlGenerator $url_generator;

    public function __construct(
        QuickbooksClient $quickbooksClient,
        Redirector $redirector,
        Session $session,
        UrlGenerator $url_generator)
    {
        $this->quickbooksClient = $quickbooksClient;
        $this->redirector = $redirector;
        $this->session = $session;
        $this->url_generator = $url_generator;
    }

    public function handle(Request $request, Closure $next)
    {
        if (!$this->quickbooksClient->hasValidRefreshToken())
        {
            $this->session->put('url.intended', $this->url_generator->to($request->path()));

            return $this->redirector->route('quickbooks.connect');
        }

        return $next($request);
    }

}
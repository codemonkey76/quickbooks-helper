<?php

namespace Codemonkey76\Quickbooks;

class QuickbooksClient
{
    protected $configs;
    protected $data_service;
    protected $report_service;

    public function __construct($configs, Token $token)
    {
        $this->configs = $configs;
        $this->setToken($token);
    }

    public function setToken(Token $token)
    {

    }

    public function deleteToken(Token $token)
    {

    }

    public function configureLogging()
    {

    }

    public function authorizationUri()
    {
        
    }

    public function hasValidRefreshToken()
    {

    }
    public function hasValidAccessToken()
    {

    }

    public function getReportService()
    {

    }
    public function getDataService()
    {

    }

    public function exchangeCodeForToken($code, $realm_id)
    {

    }

    protected function parseDataConfigs()
    {

    }

    protected function makeDataService()
    {

    }
}
<?php

namespace Codemonkey76\Quickbooks\Services;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class QuickbooksHelper
{
    public $app;
    public $dataService;

    protected static Closure $itemQuery;
    protected static Closure $itemFilter;
    protected static Closure $invoiceQuery;
    protected static Closure $invoiceFilter;
    protected static Closure $customerQuery;
    protected static Closure $customerFilter;

    public function items()
    {
        return call_user_func(static::$itemQuery);
    }

    public function invoices()
    {
        return call_user_func(static::$invoiceQuery);
    }

    public function customers()
    {
        return call_user_func(static::$customerQuery);
    }

    public static function setItemQuery(Closure $itemFunction)
    {
        static::$itemQuery = $itemFunction;
    }

    public static function setInvoiceQuery(Closure $invoiceFunction)
    {
        static::$invoiceQuery = $invoiceFunction;
    }

    public static function setCustomerQuery(Closure $customerFunction)
    {
        static::$customerQuery = $customerFunction;
    }

    public static function setItemFilter(Closure $itemFilter)
    {
        static::$itemFilter = $itemFilter;
    }

    public static function setInvoiceFilter(Closure $invoiceFilter)
    {
        static::$invoiceFilter = $invoiceFilter;
    }

    public static function setCustomerFilter(Closure $customerFilter)
    {
        static::$customerFilter = $customerFilter;
    }

    public function applyItemFilter($query)
    {
        return call_user_func(static::$itemFilter, $query);
    }
    
    public function applyInvoiceFilter($query)
    {
        return call_user_func(static::$invoiceFilter, $query);
    }

    public function applyCustomerFilter($query)
    {
        return call_user_func(static::$customerFilter, $query);
    }

    public function __construct()
    {
        $recentConnectedUser = DB::table('quickbooks_tokens')->orderBy('refresh_token_expires_at', 'DESC')->first();
        if (!$recentConnectedUser) {
            return;
        }

        Auth::logInUsingId($recentConnectedUser->user_id);
        $this->app = app('Quickbooks');
        $this->dataService = $this->app->getDataService();
        $this->dataService->setMinorVersion("34");
    }

    public function find($tableName, $Id, $primaryColumn = 'Id')
    {
        try {
            return $this->dsCall('Query', "SELECT * FROM {$tableName} WHERE {$primaryColumn}='{$Id}'");
        } catch (\Exception $e) {
            Log::channel('quickbook')->error(__METHOD__ . $e->getMessage());
        }
    }

    public function dsCall($method, ...$args)
    {
        $result = $this->dataService->$method(...$args);

        if ($error = $this->dataService->getLastError()) {
            $message = '';
            if ($callable = debug_backtrace()[1]) {
                $message .= "{$callable['class']}@{$callable['function']} ==> REQ ==> " . json_encode($callable['args']) . PHP_EOL;
            }

            $message .= $error->getIntuitErrorDetail();
            $message .= " {$error->getHttpStatusCode()}; {$error->getOAuthHelperError()}; {$error->getResponseBody()}";

            Log::channel('quickbook')->error($message);
        }
        return $result;
    }
}

<?php

namespace Codemonkey76\Quickbooks\Commands;

use Codemonkey76\Quickbooks\Services\QuickBookHelper;

use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Log;

use QuickBooksOnline\API\Facades\Customer;

class QBCustomer extends Command
{
    const MAX_FAILED = 3;
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'qb:customer {--id=*} {--limit=20} {--force}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync configured model to quickbooks customer';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $query = config('quickbooks.customer.model')::query();

        collect(config('quickbooks.customer.conditions'))
            ->each(function($params, $condition) use (&$query) {
                if (method_exists($query, $condition))
                    $query->$condition(...$params);
            });

        if ($ids = $this->option('id')) {
             $query->whereIn('id', $ids);
        } else {
             $query
                ->whereNull(config('quickbooks.customer.qb_customer_id'))
                ->limit($this->option('limit'));
        }

        $this->info($query->toSql());


        // $users = $query->get();

        // $quickbooks = new QuickBookHelper();

        // foreach ($users as $user) {
        //     try {
        //         $this->info("User Sync #{$user->id}");
        //         $params = [];
        //         $params[] = $this->prepareData($user);
        //         $objMethod = 'create';
        //         $apiMethod = 'Add';
        //         $customerId = $user->qb_customer_id;
        //         if (!$customerId) {
        //             $client = $user->client;
        //             if (!$client->firstName || !$client->lastName) {
        //                 $this->info("Client record is empty. so won't sync.");
        //                 continue;
        //             }
        //             try {
        //                 $result = $quickbooks->dsCall('Query', "SELECT Id FROM Customer WHERE GivenName='{$client->firstName}' AND FamilyName='{$client->lastName}'");
        //                 if ($result) {
        //                     $customerId = @$result[0]->Id;
        //                 }
        //             } catch (\Exception $e) {
        //             }
        //         }

        //         if ($customerId) {
        //             $targetCustomerArray = $quickbooks->find('Customer', $customerId);
        //             if (!empty($targetCustomerArray) && sizeof($targetCustomerArray) == 1) {
        //                 $theCustomer = current($targetCustomerArray);
        //                 $objMethod = 'update';
        //                 $apiMethod = 'Update';
        //                 array_unshift($params, $theCustomer);
        //             } else {
        //                 // If customer not exists and not forced, then skip new customer create, otherwise create as new customer
        //                 if (!$this->option('force')) {
        //                     $message = "Customer Not Exists #{$customerId} for user #{$user->id}";
        //                     $this->info("Error: {$message}");
        //                     Log::channel('quickbook')->error($message);
        //                     continue;
        //                 }
        //                 $user->qb_customer_id = null; // Reset for update new customer id
        //             }
        //         }

        //         $QBCustomer = Customer::$objMethod(...$params);
        //         $result = $quickbooks->dsCall($apiMethod, $QBCustomer);

        //         if ($result && !$user->qb_customer_id) {
        //             $user->update(['qb_customer_id' => $result->Id]);
        //         }
        //     } catch (\Exception $e) {
        //         $user->increment('sync_failed');
        //         $message = "{$e->getFile()}@{$e->getLine()} ==> {$e->getMessage()} for user #{$user->id}";
        //         $this->info("Error: {$message}");
        //         Log::channel('quickbook')->error($message);
        //     }
        // }
    }

    private function prepareData($customerModel)
    {
        return [
            "FullyQualifiedName" => $customerModel[config('quickbooks.customer.fully_qualified_name')] ?? null,
            "PrimaryEmailAddr" => [
                "Address" => $customerModel[config('quickbooks.customer.email_address')] ?? null
            ],
            "PrimaryPhone" => [
                "FreeFormNumber" => $customerModel[config('quickbooks.customer.phone')] ?? null
            ],
            "DisplayName" => $customerModel[config('quickbooks.customer.display_name')] ?? null,
            "GivenName" => $customerModel[config('quickbooks.customer.given_name')] ?? null,
            "FamilyName" => $customerModel[config('quickbooks.customer.family_name')] ?? null,
            "CompanyName" => $customerModel[config('quickbooks.customer.company_name')] ?? null,
            "BillAddr" => [
                "Line1" => $customerModel[config('quickbooks.customer.address_line_1')] ?? null,
                "City" => $customerModel[config('quickbooks.customer.city')] ?? null,
                "CountrySubDivisionCode" => $customerModel[config('quickbooks.customer.suburb')] ?? null,
                "PostalCode" => $customerModel[config('quickbooks.customer.postcode')] ?? null,
                "Country" => $customerModel[config('quickbooks.customer.country')] ?? null
            ]
        ];
    }
}

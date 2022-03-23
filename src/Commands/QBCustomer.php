<?php

namespace Codemonkey76\Quickbooks\Commands;

use Codemonkey76\Quickbooks\Services\QuickBookHelper;
use Exception;
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
        $config=config('quickbooks.customer');

        $query = $config('model')::query();

        collect($config('conditions'))
            ->each(function($params, $condition) use (&$query) {
                if (method_exists($query, $condition))
                    $query->$condition(...$params);
            });

        if ($ids = $this->option('id')) {
             $query->whereIn('id', $ids);
        } else {
             $query
                ->whereNull($config('qb_customer_id'))
                ->limit($this->option('limit'));
        }

        $customers = $query->get();

        $quickbooks = new QuickBookHelper();


        foreach ($customers as $customer)
        {
            try
            {
                $this->info("Customer sync #{$customer->id}");
                $params = $this->prepareData($customer);
                $objMethod = 'create';
                $apiMethod = 'Add';
                $customerId = $customer[$config('qb_customer_id')] ?? null;

                if (!$customerId)
                {
                    $firstName = data_get($customer, $config('given_name'));
                    $lastName = data_get($customer, $config('family_name'));

                    if (!$firstName || !$lastName)
                    {
                        $this->info("record is empty. so won't sync.");
                        continue;
                    }

                    try {
                        $result = $quickbooks->dsCall('Query', "SELECT Id FROM Customer WHERE GivenName='{$firstName}' AND FamilyName='{$lastName}'");
                        if ($result) {
                            $customerId = data_get($result, '0.Id');
                        }
                    } catch (Exception $e) {
                        $this->error("Exception occurred!");
                        $this->error($e->getMessage());
                    }
                }

                if ($customerId)
                {
                    $targetCustomerArray = $quickbooks->find('Customer', $customerId);
                    if (!empty($targetCustomerArray) && sizeof($targetCustomerArray) == 1)
                    {
                        $theCustomer = current($targetCustomerArray);
                        $objMethod = 'update';
                        $apiMethod = 'Update';
                        array_unshift($params, $theCustomer);
                    } else {
                        if (!$this->option('force'))
                        {
                            $message = "Customer not exists #{$customerId} for user #{$customer->id}";
                            $this->info("Error: {$message}");
                            Log::channel('quickbooks')->error($message);
                            continue;
                        }
                        $customer->qb_customer_id = null;
                    }
                }
                $QBCustomer = Customer::$objMethod(...$params);
                $result = $quickbooks->dsCall($apiMethod, $QBCustomer);

                if ($result && $customer[$config('qb_customer_id')])
                    $customer->update([$config('qb_customer_id') => $result->Id]);

            } catch (Exception $e) {
                $customer->increment('sync_failed');
                $message = "{$e->getFile()}@{$e->getLine()} ==> {$e->getMessage()} for user #{$customer->id}";
                $this->info("Error: {$message}");
                Log::channel('quickbook')->error($message);
            }

        }
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

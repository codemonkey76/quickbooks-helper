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

        $query = $config['model']::query();

        collect($config['conditions'])
            ->each(function($params, $condition) use (&$query) {
                if (method_exists($query, $condition))
                    $query->$condition(...$params);
            });

        if ($ids = $this->option('id')) {
             $query->whereIn('id', $ids);
        } else {
             $query
                ->whereNull($config['qb_customer_id'])
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
                $customerId = $customer[$config['qb_customer_id']] ?? null;

                if (!$customerId)
                {
                    $firstName = data_get($customer, $config['given_name']);
                    $lastName = data_get($customer, $config['family_name']);

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

                if ($result && $customer[$config['qb_customer_id']])
                    $customer->update([$config['qb_customer_id'] => $result->Id]);

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
        $config = config('quickbooks.customer');

        return [
            "PrimaryEmailAddr" => [
                "Address" => data_get($customerModel, $config['email_address'])
            ],
            "PrimaryPhone" => [
                "FreeFormNumber" => data_get($customerModel, $config['phone'])
            ],
            "DisplayName" => data_get($customerModel, $config['display_name']),
            "GivenName" => data_get($customerModel, $config['given_name']),
            "FamilyName" => data_get($customerModel, $config['family_name']),
            "CompanyName" => data_get($customerModel, $config['company_name']),
            "BillAddr" => [
                "Line1" => data_get($customerModel, $config['address_line_1']),
                "City" => data_get($customerModel, $config['city']),
                "CountrySubDivisionCode" => data_get($customerModel, $config['suburb']),
                "PostalCode" => data_get($customerModel, $config['postcode']),
                "Country" => data_get($customerModel, $config['country'])
            ]
        ];
    }
}

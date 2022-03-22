<?php

namespace Codemonkey76\Quickbooks\Commands;

use Codemonkey76\Quickbooks\Services\QuickBookHelper;

use Illuminate\Console\Command;
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
    protected $description = 'Sync customers to quickbook customer';

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
        $query = null;
        // User::query()->with('client')->role(User::ROLE_APPROVED);
        if ($ids = $this->option('id')) {
            $query->whereIn('id', $ids);
        } else {
            $query->has('orders')->whereNull('qb_customer_id')->where('sync_failed', '<', self::MAX_FAILED)->limit($this->option('limit'));
        }

        $users = $query->get();

        $quickbooks = new QuickBookHelper();

        foreach ($users as $user) {
            try {
                $this->info("User Sync #{$user->id}");
                $params = [];
                $params[] = $this->prepareData($user);
                $objMethod = 'create';
                $apiMethod = 'Add';
                $customerId = $user->qb_customer_id;
                if (!$customerId) {
                    $client = $user->client;
                    if (!$client->firstName || !$client->lastName) {
                        $this->info("Client record is empty. so won't sync.");
                        continue;
                    }
                    try {
                        $result = $quickbooks->dsCall('Query', "SELECT Id FROM Customer WHERE GivenName='{$client->firstName}' AND FamilyName='{$client->lastName}'");
                        if ($result) {
                            $customerId = @$result[0]->Id;
                        }
                    } catch (\Exception $e) {
                    }
                }

                if ($customerId) {
                    $targetCustomerArray = $quickbooks->find('Customer', $customerId);
                    if (!empty($targetCustomerArray) && sizeof($targetCustomerArray) == 1) {
                        $theCustomer = current($targetCustomerArray);
                        $objMethod = 'update';
                        $apiMethod = 'Update';
                        array_unshift($params, $theCustomer);
                    } else {
                        // If customer not exists and not forced, then skip new customer create, otherwise create as new customer
                        if (!$this->option('force')) {
                            $message = "Customer Not Exists #{$customerId} for user #{$user->id}";
                            $this->info("Error: {$message}");
                            Log::channel('quickbook')->error($message);
                            continue;
                        }
                        $user->qb_customer_id = null; // Reset for update new customer id
                    }
                }

                $QBCustomer = Customer::$objMethod(...$params);
                $result = $quickbooks->dsCall($apiMethod, $QBCustomer);

                if ($result && !$user->qb_customer_id) {
                    $user->update(['qb_customer_id' => $result->Id]);
                }
            } catch (\Exception $e) {
                $user->increment('sync_failed');
                $message = "{$e->getFile()}@{$e->getLine()} ==> {$e->getMessage()} for user #{$user->id}";
                $this->info("Error: {$message}");
                Log::channel('quickbook')->error($message);
            }
        }
    }

    private function prepareData($user)
    {
        $client = $user->client;
        return [
            "FullyQualifiedName" => @$client->name,
            "PrimaryEmailAddr" => [
                "Address" => $user->email
            ],
            "PrimaryPhone" => [
                "FreeFormNumber" => @$client->phone
            ],
            "DisplayName" => @$client->name,
            "GivenName" => @$client->firstName,
            "FamilyName" => @$client->lastName,
            "CompanyName" => @$client->businessName,
            "BillAddr" => [
                "Line1" => @$client->address,
                "City" => @$client->city,
                "CountrySubDivisionCode" => @$client->suburb,
                "PostalCode" => @$client->postcode,
                "Country" => @$client->country
            ]
        ];
    }
}

<?php

namespace Codemonkey76\Quickbooks\Commands;

use Codemonkey76\Quickbooks\Services\QuickbooksHelper;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

use QuickBooksOnline\API\Facades\CreditMemo;
use QuickBooksOnline\API\Facades\Invoice;
use QuickBooksOnline\API\Facades\Payment;

class QBInvoice extends Command
{
    const MAX_FAILED = 3;
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'qb:invoice {--id=*} {--limit=20} {--force}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync configured model to quickbook invoices';


    private $orderTaxcode = null;

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
        $config = config('quickbooks.invoice.attributeMap');
        $qb_helper = new QuickbooksHelper();

        $query = $qb_helper->invoices();

        if ($ids = $this->option('id'))
            $query->whereIn('id', $ids);
        else
            $qb_helper->applyInvoiceFilter($query);

        $invoices = $query->get();


        foreach ($invoices as $invoice) {
            try {
                $this->info("Invoice #{$invoice->id}....");

                $customer = data_get($invoice, $config['customer']);

                $this->orderTaxcode = data_get($invoice, $config['billing_country']) === 'AU' ? config('quickbooks.invoice.settings.austax') : config('quickbooks.invoice.settings.overseastax');

                if (!data_get($customer, config('quickbooks.customer.attributeMap.qb_customer_id'))) {
                    $message = "Customer not yet synced, Please approve the order customer or sync `php artisan qb:customer --id={$customer->id}` for invoice #{$invoice->id}";
                    $this->error("Error: {$message}");
                    Log::channel('quickbook')->error($message);
                    continue;
                }

                $invoice_params[] = $this->prepareData($invoice);
                $this->info(json_encode($invoice_params));
                return 0;
                $objMethod = 'create';
                $apiMethod = 'Add';

                $qb_invoice_id = data_get($invoice, $config['qb_invoice_id']);
                if ($qb_invoice_id) {
                    $targetInvoiceArray = $qb_helper->find('Invoice', $qb_invoice_id);
                    if (!empty($targetInvoiceArray) && sizeof($targetInvoiceArray) == 1) {
                        $theInvoice = current($targetInvoiceArray);
                        $objMethod = 'update';
                        $apiMethod = 'Update';
                        array_unshift($invoice_params, $theInvoice);
                    } else {
                        // If invoice not exists and not forced, then skip new invoice create, otherwise create as new invoice
                        if (!$this->option('force')) {
                            $message = "Invoice Not Exists #{$qb_invoice_id}  for order #{$invoice->id}";
                            $this->error("Error: {$message}");
                            Log::channel('quickbook')->error($message);
                            continue;
                        }
                        $invoice->$config['qb_invoice_id'] = null; // Reset for update new invoice id
                    }
                }

                $QBInvoice = Invoice::$objMethod(...$invoice_params);
                $result = $qb_helper->dsCall($apiMethod, $QBInvoice);

                if ($result) {
                    $this->info("Quickbooks Invoice Id #{$result->Id}");
                    $invoice->update([$config['qb_invoice_id'] => $result->Id]);
                }

                $invoice->$config['sync'] = 0;
                $invoice->save();
            } catch (\Exception $e) {
                $invoice->increment('sync_failed');
                $message = "{$e->getFile()}@{$e->getLine()} ==> {$e->getMessage()} for order #{$invoice->id}";
                $this->info("Error: {$message}");
                Log::channel('quickbook')->error($message);
            }
        }
    }

    private function prepareData($invoice)
    {
        $settings = config('quickbooks.invoice.settings');
        $itemMapping = config('quickbooks.items.attributeMap');
        $lines = [];

        foreach ($order->items as $item) {
            $qb_item_id = null;

            if ($item_variant = $item->variant()) {
                if ($item_variant->qb_item_id) {
                    $qb_item_id = $item_variant->qb_item_id;
                }
            }

            if (!$qb_item_id) {
                $qb_item_id = @$item->product->qb_item_id;
            }

            if (!$qb_item_id) {
                $qb_item_id = $this->settings['quickbook.defaultitem'];
            }

            $lines[] = [
                "DetailType" => "SalesItemLineDetail",
                "Description" => $item->name,
                "Amount" => $item->total,
                "SalesItemLineDetail" => [
                    "ItemRef" => [
                        "name" => $item->name,
                        "value" => $qb_item_id,
                    ],
                    "TaxCodeRef" => [
                        "value" => $this->orderTaxcode
                    ],
                    "Qty" => $item->quantity,
                ],
            ];
        }

        if ($order->shippingfee > 0 && $shippingFee = $order->shippingfee) {
            $shippingFeeExclTax = number_format(($shippingFee / (($this->settings['quickbook.shiptax'] / 100) + 1)), 2, '.', '');
            $lines[] = [
                "Description" => "Shipping Fee",
                "Amount" => $shippingFeeExclTax,
                "DetailType" => "SalesItemLineDetail",
                "SalesItemLineDetail" => [
                    "ItemRef" => [
                        "name" => "Shipping Fee",
                        "value" => $this->settings['quickbook.shipitem'],
                    ],
                    "TaxCodeRef" => [
                        "value" => $this->settings['quickbook.shippingtax']
                    ],
                    "Qty" => 1,
                ],
            ];
        }

        return [
            "Line" => $lines,
            "GlobalTaxCalculation" => "TaxInclusive",
            "CustomerMemo" => [
                "value" => "We would like to THANK YOU for choosing us! Website Order Number: {$order->invoiceno}. {$order->instructions}"
            ],
            "CustomerRef" => [
                "value" => $order->user->qb_customer_id,
                "name" => $order->user->name
            ],
            "BillEmail" => [
                "Address" => $order->user->email
            ]
        ];
    }

}

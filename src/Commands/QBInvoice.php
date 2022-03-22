<?php

namespace Codemonkey76\Quickbooks\Commands;

use Codemonkey76\Quickbooks\Services\QuickBookHelper;

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
    protected $description = 'Sync orders to quickbook invoice';

    private $orderTaxcode = NULL;

    private $settings = [];

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
        $this->settings = Setting::query()->get()->pluck('meta_value', 'meta_key');

        if (!isset($this->settings['quickbook.austax']) ||
            !isset($this->settings['quickbook.overseastax']) ||
            !isset($this->settings['quickbook.defaultitem']) ||
            !isset($this->settings['quickbook.paymentaccount']) ||
            !isset($this->settings['quickbook.shippingtax']) ||
            !isset($this->settings['quickbook.shipitem'])
        ) {
            $message = "Before sync please setup the quickbooks configuration from backend";
            $this->info("Error: {$message}");
            Log::channel('quickbook')->error($message);
            return;
        }

        $query = Order::query()->with(['user', 'items'])->whereHas('user', function ($q) {
            $q->whereNotNull('qb_customer_id');
        })->whereNotNull('paymentid');
        if ($ids = $this->option('id')) {
            $query->whereIn('id', $ids);
        } else {
            $query->where(function ($q) {
                $q->whereNull('qb_invoice_id')->orWhereNull('qb_payment_id')->orWhere('sync', 1);
            });
            $query->where('sync_failed', '<', self::MAX_FAILED)->limit($this->option('limit'));
        }

        $orders = $query->get();
        $quickbooks = new QuickBookHelper();

        foreach ($orders as $order) {
            try {
                $this->info("Order #{$order->id}....");
                $user = $order->user;
                $this->orderTaxcode = (@$order->billing_country === 'AU') ? $this->settings['quickbook.austax'] : $this->settings['quickbook.overseastax'];
                if (!$user->qb_customer_id) {
                    $message = "Customer not yet synced, Please approve the order customer or sync `php artisan qb:customer --id={$user->id}` for order #{$order->id}";
                    $this->info("Error: {$message}");
                    Log::channel('quickbook')->error($message);
                    continue;
                }

                $invoice_params = $payment_params = $creditmemo_params = [];
                $invoice_params[] = $this->prepareData($order);
                $objMethod = $paymentObjMethod = $memoObjMethod = 'create';
                $apiMethod = $paymentApiMethod = $memoApiMethod = 'Add';

                if ($order->qb_invoice_id) {
                    $targetInvoiceArray = $quickbooks->find('Invoice', $order->qb_invoice_id);
                    if (!empty($targetInvoiceArray) && sizeof($targetInvoiceArray) == 1) {
                        $theInvoice = current($targetInvoiceArray);
                        $objMethod = 'update';
                        $apiMethod = 'Update';
                        array_unshift($invoice_params, $theInvoice);
                    } else {
                        // If invoice not exists and not forced, then skip new invoice create, otherwise create as new invoice
                        if (!$this->option('force')) {
                            $message = "Invoice Not Exists #{$order->qb_invoice_id}  for order #{$order->id}";
                            $this->info("Error: {$message}");
                            Log::channel('quickbook')->error($message);
                            continue;
                        }
                        $order->qb_invoice_id = null; // Reset for update new invoice id
                    }
                }

                if ($order->qb_payment_id) {
                    $targetPaymentArray = $quickbooks->find('Payment', $order->qb_payment_id);
                    if (!empty($targetPaymentArray) && sizeof($targetPaymentArray) == 1) {
                        $thePayment = current($targetPaymentArray);
                        $paymentObjMethod = 'update';
                        $paymentApiMethod = 'Update';
                        $payment_params[] = $thePayment;
                    } else {
                        // If payment not exists and not forced, then skip new customer create, otherwise create as new payment
                        if (!$this->option('force')) {
                            $message = "Payment Not Exists #{$order->qb_payment_id}  for order #{$order->id}";
                            $this->info("Error: {$message}");
                            Log::channel('quickbook')->error($message);
                            continue;
                        }
                        $order->qb_payment_id = null; // Reset for update new payment id
                    }
                }

                if ($order->qb_creditmemo_id) {
                    $targetMemoArray = $quickbooks->find('CreditMemo', $order->qb_creditmemo_id);
                    if (!empty($targetMemoArray) && sizeof($targetMemoArray) == 1) {
                        $theMemo = current($targetMemoArray);
                        $memoObjMethod = 'update';
                        $memoApiMethod = 'Update';
                        $creditmemo_params[] = $theMemo;
                    } else {
                        // If memo not exists and not forced, then skip new customer create, otherwise create as new memo
                        if (!$this->option('force')) {
                            $message = "Memo Not Exists #{$order->qb_creditmemo_id}  for order #{$order->id}";
                            $this->info("Error: {$message}");
                            Log::channel('quickbook')->error($message);
                            continue;
                        }
                        $order->qb_creditmemo_id = null; // Reset for update new memo id
                    }
                }

                $QBInvoice = Invoice::$objMethod(...$invoice_params);
                $result = $quickbooks->dsCall($apiMethod, $QBInvoice);

                if ($result) {
                    $this->info("Order Invoice Id #{$result->Id}");
                    $order->qb_invoice_id = $result->Id;
                    if ($order->paymentid) {
                        $payment_params[] = $this->preparePaymentData($order, $result);
                        $QBPayment = Payment::$paymentObjMethod(...$payment_params);
                        $paymentResult = $quickbooks->dsCall($paymentApiMethod, $QBPayment);
                        if ($paymentResult) {
                            $this->info("Order Payment Id #{$paymentResult->Id}");
                            $order->qb_payment_id = $paymentResult->Id;
                        }
                    }

                    if ($order->credits > 0) {
                        $creditmemo_params[] = $this->prepareCreditMemoData($order, $result);
                        $QBMemo = CreditMemo::$memoObjMethod(...$creditmemo_params);
                        $memoResult = $quickbooks->dsCall($memoApiMethod, $QBMemo);
                        if ($memoResult) {
                            $this->info("Order Credit Memo Id #{$memoResult->Id}");
                            $order->qb_creditmemo_id = $memoResult->Id;
                        }
                    }
                }
                $order->sync = 0;
                $order->save();
            } catch (\Exception $e) {
                $order->increment('sync_failed');
                $message = "{$e->getFile()}@{$e->getLine()} ==> {$e->getMessage()} for order #{$order->id}";
                $this->info("Error: {$message}");
                Log::channel('quickbook')->error($message);
            }
        }
    }

    private function prepareData($order)
    {
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

    private function preparePaymentData($order, $invoice)
    {
        return [
            "DepositToAccountRef" => [
                "value" => $this->settings['quickbook.paymentaccount']
            ],
            "TxnDate" => $order->created_at->toDateString(),
            "TotalAmt" => $order->total,
            "Line" => [
                [
                    "Amount" => $order->total,
                    "LinkedTxn" => [
                        ["TxnId" => $invoice->Id, "TxnType" => "Invoice"]
                    ]
                ]
            ],
            "CustomerRef" => [
                "value" => $order->user->qb_customer_id
            ],
            "PaymentMethodRef" => [
                "value" => ($order->gateway == Order::GATEWAY_STRIPE) ? $this->settings['quickbook.stripemethod'] : $this->settings['quickbook.paypalmethod']
            ],
            "PrivateNote" => "GatewayID {$order->paymentid}"
        ];
    }

    private function prepareCreditMemoData($order, $invoice)
    {
        return [
            'CustomerRef' => [
                'value' => $order->user->qb_customer_id
            ],
            'InvoiceRef' => [
                'value' => $invoice->Id
            ],
            'TotalAmt' => $order->credits,
            'TxnDate' => $order->created_at->toDateString(),
            'Line' => [
                [
                    'Description' => 'Credits Applied From Website',
                    "Amount" => $order->credits,
                    "DetailType" => "SalesItemLineDetail",
                    "SalesItemLineDetail" => [
                        "ItemRef" => [
                            "name" => "Website Credits",
                            "value" => $this->settings['quickbook.creditmemoitem'],
                        ],
                        "TaxCodeRef" => [
                            "value" => $this->orderTaxcode
                        ],
                        "Qty" => 1,
                    ],
                ]
            ]
        ];
    }
}

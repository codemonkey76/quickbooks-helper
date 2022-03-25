<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Codemonkey76\Quickbooks\Services\QuickbooksHelper;
use Illuminate\Support\Facades\Log;
use QuickBooksOnline\API\Facades\Item;

class QBItem extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'qb:item';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync product types to quickbooks';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $itemMap = config('quickbooks.items.attributeMap');
        $qb_helper = new QuickbooksHelper();

        $query = $qb_helper->items();

        if ($ids = $this->option('id'))
            $query->whereIn('id', $ids);
        else
            $qb_helper->applyItemFilter($query);

        $items = $query->get();


        foreach ($items as $item)
        {
            try
            {
                $this->info("Item #{$item->id}...");

                $item_params[] =$this->prepareData($item);

                $objMethod = 'create';
                $apiMethod = 'Add';

                $qb_item_id = data_get($item, $itemMap['qb_item_id']);
                if ($qb_item_id)
                {
                    $targetItemArray = $qb_helper->find('Item', $qb_item_id);

                    if (!empty($targetItemArray) && sizeof($targetItemArray) === 1)
                    {
                        $theItem = current($targetItemArray);
                        $objMethod = 'update';
                        $apiMethod = 'Update';
                        array_unshift($item_params, $theItem);
                    } else {
                        if (!$this->option('force'))
                        {
                            $message = "QbItem does not exist #{$qb_item_id} for item id: #{$item->id}";
                            $this->error("Error: {$message}");
                            Log::channel('quickbook')->error($message);
                            continue;
                        }
                        $item->$itemMap['qb_item_id'] = null;
                    }
                }

                $QbItem = Item::$objMethod(...$item_params);
                $result = $qb_helper->dsCall($apiMethod, $QbItem);

                if ($result)
                {
                    $this->info("Quickbooks Item ID #{$result->Id}");
                    $item->update([$itemMap['qb_item_id'] => $result->Id]);
                }

                $item->$itemMap['sync'] = 0;
                $item->save();
            } catch(\Exception $e) {
                $item->increment($itemMap['sync_failed']);
                $message = "{$e->getFile()}@{$e->getLine()} ==> {$e->getMessage()} for order #{$item->id}";
                $this->error("Error: {$message}");
                Log::channel('quickbook')->error($message);
            }
        }
        return 0;
    }

    public function prepareData($item)
    {
        $itemMap = config('quickbooks.items.attributeMap');

        return [
            'Name' => data_get($item, $itemMap['name']),
            'Description' => data_get($item, $itemMap['description']),
            'Active' => data_get($item, $itemMap['active']),
            'FullyQualifiedName' => data_get($item, $itemMap['fully_qualified_name']),
            'Taxable' => data_get($item, $itemMap['taxable']),
            'SalesTaxIncluded' => data_get($item, $itemMap['sales_tax_included']),
            'UnitPrice' => data_get($item, $itemMap['unit_price']),
            'IncomeAccountRef' => data_get($item, $itemMap['income_account_ref']),
            'Type' => data_get($item, $itemMap['type']),
            'PurchaseTaxIncluded' => data_get($item, $itemMap['puchase_tax_included']),
            'PurchaseCost' => data_get($item, $itemMap['purchase_cost']),
            'TrackQtyOnHand' => data_get($item, $itemMap['track_qty_on_hand']),
            'SalesTaxCodeRef' => data_get($item, $itemMap['sales_tax_code_ref'])
        ];
    }

}

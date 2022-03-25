<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Codemonkey76\Quickbooks\Services\QuickbooksHelper;


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

        $startPosition = 1;
        $maxResults = 100;

        $model = config('quickbooks.items.model');

        do
        {
            $rows = $qb_helper->dsCall('Query', "SELECT * FROM Item WHERE Active=true STARTPOSITION {$startPosition} MAXRESULTS {$maxResults}");
            if ($rows) {
                collect($rows)->each(function ($row) use ($itemMap, $model) {
                    $model::updateOrCreate([$itemMap['qb_item_id'] => $row->Id],[
                        $itemMap['name'] => $row->Name,
                        $itemMap['description'] => $row->Description,
                        $itemMap['active'] => $row->Active === 'true',
                        $itemMap['fully_qualified_name'] => $row->FullyQualifiedName,
                        $itemMap['taxable'] => $row->Taxable === 'true',
                        $itemMap['sales_tax_included'] => $row->SalesTaxIncluded === 'true',
                        $itemMap['unit_price'] => $row->UnitPrice ?? 0,
                        $itemMap['income_account_ref'] => $row->IncomeAccountRef,
                        $itemMap['type'] => $row->Type,
                        $itemMap['purchase_tax_included'] => $row->PurchaseTaxIncluded === 'true',
                        $itemMap['purchase_cost'] => $row->PurchaseCost ?? 0,
                        $itemMap['track_qty_on_hand'] => $row->TrackQtyOnHand === 'true',
                        $itemMap['sales_tax_code_ref'] => $row->SalesTaxCodeRef
                    ]);
                });
            }
            $startPosition += $maxResults;

        } while(!is_null($rows) && is_array($rows) && count($rows) >= $maxResults);


        return 0;
    }
}

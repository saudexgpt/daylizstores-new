<?php

namespace App\Http\Controllers;

use App\Models\Invoice\DeliveryTripExpense;
use App\Models\Invoice\Invoice;
use App\Models\Invoice\Waybill;
use App\Models\Logistics\VehicleExpense;
use App\Models\Stock\ItemStockSubBatch;
use App\Models\Stock\ReturnedProduct;
use App\Models\Transfers\TransferRequestWaybill;
use Illuminate\Http\Request;

class AuditConfirmsController extends Controller
{
    public function confirmStockedItems(ItemStockSubBatch $item_stock_sub_batch)
    {
        $user = $this->getUser();
        $item_stock_sub_batch->confirmed_by = $user->id;
        $confirmed = 'failed';
        if ($item_stock_sub_batch->save()) {
            $confirmed = 'success';
        }
        $title = "Stock confirmation by auditor";
        $description = "Product with batch number: $item_stock_sub_batch->batch_no was confirmed by $user->name ($user->phone)";
        //log this activity
        $roles = ['assistant admin', 'warehouse manager', 'stock officer'];
        $this->logUserActivity($title, $description, $roles);
        return response()->json(['confirmed' => $confirmed, 'confirmed_by' => $user->name], 200);
    }
    public function confirmReturnedItems(ReturnedProduct $returned_product)
    {
        $user = $this->getUser();
        $returned_product->confirmed_by = $user->id;
        $confirmed = 'failed';
        if ($returned_product->save()) {
            $confirmed = 'success';
        }
        $title = "Goods returned confirmation by auditor";
        $description = "Goods returned with batch number: $returned_product->batch_no was confirmed by $user->name ($user->phone)";
        //log this activity
        $roles = ['assistant admin', 'warehouse manager', 'stock officer'];
        $this->logUserActivity($title, $description, $roles);
        return response()->json(['confirmed' => $confirmed, 'confirmed_by' => $user->name], 200);
    }

    public function confirmWaybill(Request $request, Waybill $waybill)
    {
        $user = $this->getUser();
        $waybill_items = $waybill->waybillItems;
        foreach ($waybill_items as $waybill_item) {
            $waybill_item->is_confirmed = '1';
            $waybill_item->save();
        }
        $waybill->confirmed_by = $user->id;
        $confirmed = 'failed';
        if ($waybill->save()) {
            $confirmed = 'success';
        }
        $title = "Outbound/Waybill goods confirmation by auditor";
        $description = "Waybill goods with waybill number: $waybill->waybill_no was confirmed by $user->name ($user->phone)";
        //log this activity
        $roles = ['assistant admin', 'warehouse manager', 'stock officer'];
        $this->logUserActivity($title, $description, $roles);
        return response()->json(['confirmed' => $confirmed, 'confirmed_by' => $user->name], 200);
    }
    public function confirmInvoice(Request $request, Invoice $invoice)
    {
        $user = $this->getUser();
        $invoice_items = $invoice->invoiceItems;
        foreach ($invoice_items as $invoice_item) {
            $invoice_item->is_confirmed = 1;
            $invoice_item->save();
        }
        $invoice->confirmed_by = $user->id;
        $confirmed = 'failed';
        if ($invoice->save()) {
            $confirmed = 'success';
        }
        $title = "Invoice confirmation by auditor";
        $description = "Invoice with invoice number: $invoice->invoice_number was confirmed by $user->name ($user->phone)";
        //log this activity
        $roles = ['assistant admin', 'warehouse manager', 'stock officer'];
        $this->logUserActivity($title, $description, $roles);
        return response()->json(['confirmed' => $confirmed, 'confirmed_by' => $user->name], 200);
    }

    public function confirmTransferWaybill(Request $request, TransferRequestWaybill $waybill)
    {
        $user = $this->getUser();
        $waybill_items = $waybill->waybillItems;
        foreach ($waybill_items as $waybill_item) {
            $waybill_item->is_confirmed = '1';
            $waybill_item->save();
        }
        $waybill->confirmed_by = $user->id;
        $confirmed = 'failed';
        if ($waybill->save()) {
            $confirmed = 'success';
        }
        $title = "Transfer goods waybill confirmation by auditor";
        $description = "Waybill goods with waybill number: $waybill->transfer_request_waybill_no was confirmed by $user->name ($user->phone)";
        //log this activity
        $roles = ['assistant admin', 'warehouse manager', 'stock officer'];
        $this->logUserActivity($title, $description, $roles);
        return response()->json(['confirmed' => $confirmed, 'confirmed_by' => $user->name], 200);
    }


    public function confirmDeliveryCost(Request $request, DeliveryTripExpense $delivery_cost_expense)
    {
        $user = $this->getUser();
        $extra = '';
        if (isset($request->is_extra)) {
            $extra = 'Extra';
        }
        $delivery_cost_expense->confirmed_by = $user->id;
        $confirmed = 'failed';
        if ($delivery_cost_expense->save()) {
            $confirmed = 'success';
        }
        $title = "$extra Delivery cost confirmation by auditor";
        $description = "$extra Delivery cost with trip number: " . $delivery_cost_expense->deliveryTrip->trip_no . " was confirmed by $user->name ($user->phone)";
        //log this activity
        $roles = ['assistant admin', 'warehouse manager'];
        $this->logUserActivity($title, $description, $roles);
        return response()->json(['confirmed' => $confirmed, 'confirmed_by' => $user->name], 200);
    }
    public function confirmVehicleExpense(Request $request, VehicleExpense $vehicle_expense)
    {
        $user = $this->getUser();
        $extra = '';
        if (isset($request->is_extra)) {
            $extra = 'Extra';
        }
        $vehicle_expense->confirmed_by = $user->id;
        $confirmed = 'failed';
        if ($vehicle_expense->save()) {
            $confirmed = 'success';
        }
        $title = "$extra Vehicle expense confirmation by auditor";
        $description = "$extra Vehicle expenses for vehicle number: " . $vehicle_expense->vehicle->plate_no . " was confirmed by $user->name ($user->phone)";
        //log this activity
        $roles = ['assistant admin', 'warehouse manager'];
        $this->logUserActivity($title, $description, $roles);
        return response()->json(['confirmed' => $confirmed, 'confirmed_by' => $user->name], 200);
    }
}

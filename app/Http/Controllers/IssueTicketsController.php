<?php

namespace App\Http\Controllers;

use App\IssueTicket;
use App\Models\Stock\ItemStockSubBatch;
use Illuminate\Http\Request;

class IssueTicketsController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $tickets = IssueTicket::with('raisedBy')->orderBy('status')->get();
        return response()->json(compact('tickets'), 200);
    }
    public function pendingTickets()
    {
        $tickets = IssueTicket::where('status', 'pending')->get();
        return response()->json(compact('tickets'), 200);
    }
    public function myTickets()
    {
        $user  = $this->getUser();
        $tickets = IssueTicket::where(['raised_by' => $user->id, 'status' => 'pending'])->get();
        return response()->json(compact('tickets'), 200);
    }

    public function createTicket(Request $request)
    {
        $user  = $this->getUser();
        $ticket = new IssueTicket();
        $ticket->raised_by = $user->id;
        $ticket->title = $request->title;
        $ticket->old_quantity = $request->old_quantity;
        $ticket->new_quantity = $request->new_quantity;
        $ticket->details = $request->details;
        $ticket->table_name = $request->table_name;
        $ticket->table_id = $request->table_id;
        if ($ticket->save()) {
            $ticket->ticket_no = $this->getUniqueNo('#', $ticket->id);
            $ticket->save();

            $title = "Issue Ticket Raised";
            $description = $request->title . " was made by $user->name ($user->email)";

            //log this activity
            $roles = [];
            $this->logUserActivity($title, $description, $roles);
            return 'success';
        }
        return 'failed';
    }

    public function approveTicket(Request $request)
    {
        $user  = $this->getUser();
        $request_id  = $request->request_id;
        $ticket = IssueTicket::find($request_id);
        $table_name = $ticket->table_name;
        switch ($table_name) {
            case 'item_stock_sub_batches':
                $item_in_stock = ItemStockSubBatch::find($ticket->table_id);
                $difference_in_stock = $ticket->new_quantity - $ticket->old_quantity;
                $item_in_stock->quantity = $ticket->new_quantity;
                $item_in_stock->balance += $difference_in_stock;
                $item_in_stock->save();

                break;

            default:
                # code...
                break;
        }
        $ticket->status = 'solved';
        $ticket->solved_by = $user->id;
        $ticket->save();
        return $this->index();
    }


    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\IssueTicket  $issueTicket
     * @return \Illuminate\Http\Response
     */
    public function deleteTicket(IssueTicket $ticket)
    {
        //
        $ticket->delete();
        return response()->json([], 204);
    }
}

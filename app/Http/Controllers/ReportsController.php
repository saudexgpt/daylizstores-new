<?php

namespace App\Http\Controllers;

use App\Notification;
use Carbon\Carbon;
use Illuminate\Http\Request;

class ReportsController extends Controller
{
    public function auditTrails(Request $request)
    {
        $date_from = Carbon::now()->startOfWeek();
        $date_to = Carbon::now()->endOfWeek();
        $panel = 'week';
        if (isset($request->from, $request->to, $request->panel)) {
            $date_from = date('Y-m-d', strtotime($request->from)) . ' 00:00:00';
            $date_to = date('Y-m-d', strtotime($request->to)) . ' 23:59:59';
            $panel = $request->panel;
        }
        $activity_logs = Notification::where('created_at', '>=', $date_from)->where('created_at', '<=', $date_to)->orderBy('created_at', 'DESC')->paginate(20);
        return response()->json(compact('activity_logs'), 200);
    }
    public function markAsRead()
    {
        $user = $this->getUser();
        $user->unreadNotifications->markAsRead();
        return response()->json([], 204);
    }
    public function backUps()
    {
        $date = Date('Y-m-d', strtotime('now'));
        $file_name = 'gpl_db_backup_' . $date . '.sql';
        $url = 'storage/bkup/db/' . $file_name;

        return response()->json(compact('url', 'date'), 200);
    }
}

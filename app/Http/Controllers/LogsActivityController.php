<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\ActivityLog;
class LogsActivityController extends Controller
{
    public function index(){
        $logs = ActivityLog::all();
        return response()->json($logs);
    }
    public function show(ActivityLog $activityLog){
        return $activityLog;
    }
}

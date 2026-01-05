<?php

namespace App\Http\Controllers\ai;

use App\Http\Controllers\Controller;
use App\Services\CalendarSummaryService;
use Carbon\Carbon;
class CalanderSummaryController extends Controller
{
  protected $calendarService;

    public function __construct(CalendarSummaryService $calendarService)
    {
        $this->calendarService = $calendarService;
    }

    public function getDailyAiSummaries()
    {
        $aiRun = $this->calendarService->getDailySummaries();

        if (!$aiRun) {
            return response()->json(['status' => 'error', 'message' => 'No AI summary found.'], 404);
        }

        return response()->json([
            'status' => $aiRun->status,
            'date' => $aiRun->ran_at->toDateString(),
            'summaries' => $aiRun->output_data
        ]);
    }

}
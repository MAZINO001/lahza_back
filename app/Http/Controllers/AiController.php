<?php

namespace App\Http\Controllers;

use Gemini\Laravel\Facades\Gemini;
use Illuminate\Http\Request;
use App\Models\User;
use Carbon\Carbon;
use App\Models\Event;
use Illuminate\Support\Facades\Log;

class AiController extends Controller
{
   public function calendarSummary(Request $request)
{
    $todayDate = Carbon::today()->toDateString();

    // 1. Get all users and their events first
    $teamUsersWithEvents = User::whereHas('teamUser')
        ->with(['teamUser', 'events' => function($query) use ($todayDate) {
            $query->where('start_date', $todayDate)->orderBy('start_hour', 'asc');
        }])
        ->whereHas('events', function($query) use ($todayDate) {
            $query->where('start_date', $todayDate);
        })
        ->get();

    if ($teamUsersWithEvents->isEmpty()) {
        return response()->json([
            'status' => 'success',
            'summaries' => ['message' => 'No events scheduled for today.']
        ]);
    }

    // 2. Build one large string containing ALL users
    $megaPromptData = "";
    foreach ($teamUsersWithEvents as $user) {
        $analysis = $this->analyzeEvents($user->events);
        $userContext = $this->buildEventPrompt($todayDate, $user->events, $user->teamUser, $user, $analysis);
        
        $megaPromptData .= "--- START USER: {$user->id} ({$user->name}) ---\n";
        $megaPromptData .= $userContext . "\n";
        $megaPromptData .= "--- END USER: {$user->id} ---\n\n";
    }

    // 3. One single AI call
    try {
        $response = Gemini::generativeModel(model: 'gemini-2.5-flash')
            ->generateContent($this->getBatchAIPrompt($megaPromptData));

        $cleanJson = $this->cleanAiJson($response->text());
        $summaries = json_decode($cleanJson, true);

        return response()->json([
            'status' => 'success',
            'date' => $todayDate,
            'summaries' => $summaries
        ]);
    } catch (\Exception $e) {
        Log::error('Batch AI Failed', ['error' => $e->getMessage()]);
        return response()->json(['status' => 'error', 'message' => 'AI failed to process batch'], 500);
    }
}
    /**
     * Analyze events to extract meaningful patterns and priorities
     */
    private function analyzeEvents($events)
    {
        $analysis = [
            'has_urgent' => false,
            'urgent_count' => 0,
            'high_priority_events' => [],
            'categories' => [],
            'total_minutes' => 0,
            'longest_gap_minutes' => 0,
            'back_to_back' => false,
            'types' => [],
            'work_categories' => ['Work', 'Finance', 'Meeting'],
            'personal_categories' => ['Health & Fitness', 'Health', 'Social', 'Leisure'],
            'work_event_count' => 0,
            'personal_event_count' => 0,
            'metrics' => []
        ];

        $totalMinutes = 0;
        $previousEnd = null;
        $maxGap = 0;

        foreach ($events as $event) {
            // Categorize as work or personal
            if (in_array($event->category, $analysis['work_categories']) || 
                $event->category === 'Work') {
                $analysis['work_event_count']++;
            } else if (in_array($event->category, $analysis['personal_categories'])) {
                $analysis['personal_event_count']++;
            }

            // Track urgency (normalize different urgency values)
            $urgency = strtolower($event->urgency ?? '');
            if (in_array($urgency, ['high', 'urgent'])) {
                $analysis['has_urgent'] = true;
                $analysis['urgent_count']++;
                $analysis['high_priority_events'][] = $event;
            }

            // Track categories
            if ($event->category) {
                $analysis['categories'][] = $event->category;
            }

            // Track types
            if ($event->type) {
                $analysis['types'][] = $event->type;
            }

            // Calculate duration
            $start = Carbon::parse($event->start_hour);
            $end = Carbon::parse($event->end_hour);
            $duration = $start->diffInMinutes($end);
            $totalMinutes += $duration;

            // Detect gaps and back-to-back meetings
            if ($previousEnd) {
                $gap = $previousEnd->diffInMinutes($start);
                $maxGap = max($maxGap, $gap);
                
                if ($gap <= 5) {
                    $analysis['back_to_back'] = true;
                }
            }
            $previousEnd = $end;
        }

        $analysis['total_minutes'] = $totalMinutes;
        $analysis['longest_gap_minutes'] = $maxGap;
        $analysis['categories'] = array_unique($analysis['categories']);
        $analysis['types'] = array_unique($analysis['types']);

        // Create metrics summary
        $analysis['metrics'] = [
            'total_hours' => round($totalMinutes / 60, 1),
            'event_count' => $events->count(),
            'work_events' => $analysis['work_event_count'],
            'personal_events' => $analysis['personal_event_count'],
            'urgent_count' => $analysis['urgent_count'],
            'has_back_to_back' => $analysis['back_to_back'],
            'max_break_minutes' => $maxGap
        ];

        return $analysis;
    }

    /**
     * Build enriched event prompt with full context
     */
    private function buildEventPrompt($date, $events, $teamUser, $user, $analysis)
    {
        $dayOfWeek = Carbon::parse($date)->format('l');
        $eventCount = $events->count();
        $totalHours = round($analysis['total_minutes'] / 60, 1);

        $content = "CONTEXT:\n";
        $content .= "Date: {$dayOfWeek}, {$date}\n";
        $content .= "Person: {$user->name} ({$teamUser->poste})\n";
        $content .= "Schedule: {$eventCount} events, {$totalHours}h total\n";
        
        if ($analysis['work_event_count'] > 0) {
            $content .= "Work Events: {$analysis['work_event_count']} | Personal: {$analysis['personal_event_count']}\n";
        }
        
        if ($analysis['has_urgent']) {
            $content .= "⚠️ URGENT ITEMS: {$analysis['urgent_count']} high-priority event(s)\n";
        }
        
        if ($analysis['back_to_back']) {
            $content .= "⚠️ Back-to-back meetings detected\n";
        }

        if (!empty($analysis['categories'])) {
            $content .= "Categories: " . implode(', ', $analysis['categories']) . "\n";
        }

        $content .= "\nSCHEDULED EVENTS:\n";
        
        foreach ($events as $index => $event) {
            $startTime = Carbon::parse($event->start_hour)->format('H:i');
            $endTime = Carbon::parse($event->end_hour)->format('H:i');
            $duration = Carbon::parse($event->start_hour)->diffInMinutes(Carbon::parse($event->end_hour));
            
            // Priority indicator
            $urgencyFlag = '';
            $urgency = strtolower($event->urgency ?? '');
            if (in_array($urgency, ['high', 'urgent'])) {
                $urgencyFlag = ' [URGENT]';
            } elseif ($urgency === 'medium') {
                $urgencyFlag = ' [Important]';
            }
            
            // $content .= "\n" . ($index + 1) . ". {$startTime}-{$endTime} ({$duration}min){$urgencyFlag}\n";
            $content .= "\n" . ($index + 1) . ". Event Duration: {$duration} minutes{$urgencyFlag}\n";


            // Event type/title
            if ($event->title) {
                $content .= "   Title: {$event->title}\n";
            }
            
            // Category
            if ($event->category) {
                $content .= "   Category: {$event->category}\n";
            }
            
            // Type
            if ($event->type) {
                $content .= "   Type: {$event->type}\n";
            }
            
            // Description
            $content .= "   Description: {$event->description}\n";
            
            // Additional notes
            if ($event->other_notes) {
                $content .= "   Notes: {$event->other_notes}\n";
            }
            
            // Status
            if ($event->status) {
                $content .= "   Status: {$event->status}\n";
            }
            
            // URL (for virtual meetings)
            if ($event->url) {
                $content .= "   Meeting Link: Available\n";
            }
            
            // Other attendees
            $otherGuests = $event->guests()
                ->where('users.id', '!=', $user->id)
                ->with('teamUser')
                ->get();
            
            if ($otherGuests->isNotEmpty()) {
                $attendeesList = $otherGuests->map(function($guest) {
                    $role = $guest->teamUser ? " ({$guest->teamUser->poste})" : '';
                    return $guest->name . $role;
                })->implode(', ');
                $content .= "   Attendees: {$attendeesList}\n";
            }
        }

        // Add schedule gaps analysis
        if ($analysis['longest_gap_minutes'] > 60) {
            $gapHours = round($analysis['longest_gap_minutes'] / 60, 1);
            $content .= "\nSCHEDULE NOTE: Longest break is {$gapHours}h - potential focus time\n";
        }

        return $content;
    }

    /**
     * Improved AI prompt with urgency awareness
     */
    private function getBatchAIPrompt($allUsersData)
    {
        return <<<PROMPT
You are an executive assistant. I will provide schedule data for MULTIPLE team members. 
Your task is to generate a brief for EACH person separately.

STRICT OUTPUT RULES:
1. Return ONLY a valid JSON array.
2. Each object in the array must have these keys: "team_user_id", "name", "summary_text".
3. "summary_text" should follow the format: **Priority Focus**, **Key Actions**, **Watch For**, and **Day Intensity**.
4. Max 80 words per person.

DATA TO PROCESS:
{$allUsersData}

Return the JSON array now:
PROMPT;
    }
    private function cleanAiJson($text) 
{
    // Removes potential markdown code blocks (```json ... ```) that AI often adds
    return preg_replace('/^```json|```$/m', '', $text);
}
}
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
public function calendarSummary()
{
    $todayDate = Carbon::today()->toDateString();

    // Eager load users and their teamUser relationship
    $events = Event::where('start_date', $todayDate)
        ->with(['guests.teamUser']) // This is correct - guests are Users
        ->orderBy('start_hour', 'asc')
        ->get();
    
    Log::info('Events found:', ['count' => $events->count()]);

    if ($events->isEmpty()) {
        return response()->json([
            'status' => 'success',
            'date' => $todayDate,
            'summaries' => ['message' => 'No events scheduled for today.']
        ]);
    }

    $teamUserEvents = [];

    foreach ($events as $event) {
        // Explicitly load guests via the relation query to avoid
        // attribute/relationship name conflicts (there is a JSON
        // `guests` column on the `events` table).
        $guests = $event->guests()->with('teamUser')->get();

        if ($guests->isEmpty()) {
            Log::info('Event has no guests', ['event_id' => $event->id]);
            continue;
        }

        Log::info('Event guests:', [
            'event_id' => $event->id,
            'guests_count' => $guests->count()
        ]);

        // Iterate explicit relation results
        foreach ($guests as $user) {
            Log::info('Guest details:', [
                'user_id' => $user->id,
                'user_type' => $user->user_type ?? 'not set',
                'has_teamUser' => !is_null($user->teamUser)
            ]);

            // Skip if user doesn't have a teamUser relationship
            if (!$user->teamUser) {
                Log::info('Skipping user - no teamUser', ['user_id' => $user->id]);
                continue;
            }

            $teamUserId = $user->teamUser->id;

            // Initialize array if this is the first time we see this team user
            if (!isset($teamUserEvents[$teamUserId])) {
                $teamUserEvents[$teamUserId] = [
                    'team_user' => $user->teamUser,
                    'user' => $user,
                    'events' => []
                ];
            }

            // Add event to this team user's list
            $teamUserEvents[$teamUserId]['events'][] = $event;
        }
    }

    Log::info('Team users found:', ['count' => count($teamUserEvents)]);

    $summaries = [];

    foreach ($teamUserEvents as $data) {
        $teamUser = $data['team_user'];
        $user = $data['user'];
        $userEvents = collect($data['events'])->unique('id');

        Log::info('Generating summary for team user', [
            'team_user_id' => $teamUser->id,
            'user_name' => $user->name,
            'events_count' => $userEvents->count()
        ]);

        $content = $this->buildEventPrompt(
            $todayDate,
            $userEvents,
            $teamUser
        );

        try {
            $response = Gemini::generativeModel(model: 'gemini-2.5-flash-lite')
                ->generateContent($this->getAIPrompt($content));

            $summaries[] = [
                'team_user_id' => $teamUser->id,
                'name' => $user->name,
                'role' => $teamUser->poste ?? 'Team Member',
                'summary' => $response->text()
            ];
        } catch (\Exception $e) {
            Log::error('AI Summary Failed', [
                'team_user_id' => $teamUser->id,
                'error' => $e->getMessage()
            ]);

            $summaries[] = [
                'team_user_id' => $teamUser->id,
                'name' => $user->name,
                'role' => $teamUser->poste ?? 'Team Member',
                'summary' => null,
                'error' => 'Failed to generate summary'
            ];
        }
    }

    return response()->json([
        'status' => 'success',
        'date' => $todayDate,
        'summaries' => $summaries
    ]);
}

/**
 * Build the prompt content from events
 */
private function buildEventPrompt($date, $events, $teamUser)
{
    $content = "Date: {$date}\n";
    $content .= "Team Member Role: {$teamUser->poste}\n\n";

    foreach ($events as $event) {
        $content .= "Event Context:\n";
        $content .= "- Description: {$event->description}\n";

        if ($event->title) {
            $content .= "- Nature: {$event->title}\n";
        }

        $content .= "\n";
    }

    return $content;
}

/**
 * Get the AI prompt template
 */
private function getAIPrompt($content)
{
    return <<<PROMPT
You are an AI executive assistant generating a DAILY PRIORITY BRIEF.

Your task is to interpret events, not list them.

STRICT RULES:
- Do NOT include event titles or times unless absolutely necessary
- Do NOT format the response like a schedule
- Do NOT say "None identified" — always evaluate risk
- Do NOT treat routine or daily meetings as priorities

Assumptions:
- All events have MEDIUM urgency unless stated otherwise
- Deployment, client feedback, or decision-making work implies risk by default

Your response MUST include:

1. Today's Priority Focus (1–2 sentences)
   - Rank what matters most

2. Actions That Need Attention
   - Only actions that could impact delivery, quality, or timelines

3. Risks & Watchpoints
   - Even moderate risks must be mentioned

4. Day Load Assessment
   - Light / Normal / Busy + one reason

Constraints:
- Max 120 words
- No greetings
- No bullet points unless necessary
- Professional, judgment-based tone

DATA:
{$content}

Generate the priority brief now.
PROMPT;
}


/**
 * Optional: Store the daily summary in the database
 */
private function storeDailySummary($date, $summary)
{
    // You would need to create a DailySummary model and migration
    // Example:
    /*
    DailySummary::updateOrCreate(
        ['date' => $date],
        [
            'summary' => $summary,
            'generated_at' => now()
        ]
    );
    */
}
    }
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <title>New Project{{ isset($project_count) && $project_count > 1 ? 's' : '' }} Created</title>
</head>

<body style="margin:0; padding:0; background:#f8f9fa; font-family: Arial, sans-serif;">

<table width="100%" cellspacing="0" cellpadding="0" style="padding: 40px 0;">
<tr>
<td align="center">

<table width="600" cellspacing="0" cellpadding="0"
       style="background:#ffffff; padding:30px; border-radius:16px;
              box-shadow:0 4px 20px rgba(0,0,0,0.06); border:1px solid #e5e7eb;">

<tr>
<td style="text-align:center; padding-bottom:20px;">
<h2 style="margin:0; font-size:24px; color:#111827; font-weight:700;">
🎉 New Project{{ isset($project_count) && $project_count > 1 ? 's' : '' }} Created
</h2>
<p style="margin-top:8px; color:#6b7280; font-size:14px;">
@if(isset($project_count) && $project_count > 1)
{{ $project_count }} new projects have been generated automatically.
@else
A new project has been generated automatically.
@endif
</p>
</td>
</tr>

@php
$projectsList = isset($projects)
    ? $projects
    : [['project' => $project, 'tasks' => $tasks ?? [], 'assigned_team' => $assigned_team ?? null]];
@endphp

@foreach($projectsList as $index => $projectData)

{{-- ✅ FIX: spacer instead of margin-top --}}
@if($index > 0)
<tr>
<td height="20" style="line-height:20px; font-size:0;">&nbsp;</td>
</tr>
@endif

@php
$currentProject = $projectData['project'];
$currentTasks = $projectData['tasks'] ?? [];
$currentAssignedTeam = $projectData['assigned_team'] ?? null;
@endphp

<tr>
<td style="padding:20px; background:#f9fafb; border-radius:12px; border:1px solid #e5e7eb;">
<h3 style="margin:0 0 12px; font-size:18px; color:#111827;">
📁 Project {{ isset($project_count) && $project_count > 1 ? '#' . ($index + 1) : '' }} Details
</h3>
<p style="margin:4px 0; color:#4b5563;"><strong>ID:</strong> {{ $currentProject->id }}</p>
<p style="margin:4px 0; color:#4b5563;"><strong>Name:</strong> {{ $currentProject->name }}</p>
<p style="margin:4px 0; color:#4b5563;"><strong>Status:</strong> {{ $currentProject->status }}</p>
<p style="margin:4px 0; color:#4b5563;"><strong>Description:</strong> {{ $currentProject->description }}</p>
<p style="margin:4px 0; color:#4b5563;"><strong>Start Date:</strong> {{ $currentProject->start_date }}</p>
<p style="margin:4px 0; color:#4b5563;"><strong>Estimated End:</strong> {{ $currentProject->estimated_end_date }}</p>
</td>
</tr>

@if(count($currentTasks) > 0)
<tr>
<td style="padding:20px 0 0;">
<h3 style="margin:0 0 12px; font-size:18px; color:#111827;">
📝 Project #{{ $currentProject->id }} Tasks
</h3>
<table width="100%" style="background:#f9fafb; padding:20px; border-radius:12px; border:1px solid #e5e7eb;">
@foreach($currentTasks as $task)
<tr>
<td style="padding:10px 0; border-bottom:1px solid #e5e7eb;">
<strong style="color:#111827;">{{ $task->title }}</strong><br>
<span style="color:#6b7280; font-size:13px;">
{{ $task->description }}
</span>
</td>
</tr>
@endforeach
</table>
</td>
</tr>
@endif

<tr>
<td style="padding:20px 0 0;">
<h3 style="margin:0 0 12px; font-size:18px; color:#111827;">
👥 Assigned Team Member {{ isset($project_count) && $project_count > 1 ? '(Project #' . $currentProject->id . ')' : '' }}
</h3>

@if($currentAssignedTeam && $currentAssignedTeam->teamUser)
<table width="100%" style="background:#f9fafb; padding:20px; border-radius:12px; border:1px solid #e5e7eb;">
<tr><td style="color:#4b5563;"><strong>Name:</strong> {{ $currentAssignedTeam->teamUser->name }}</td></tr>
<tr><td style="color:#4b5563;"><strong>Email:</strong> {{ $currentAssignedTeam->teamUser->email }}</td></tr>
</table>
@else
<p style="color:#6b7280;">No team member assigned.</p>
@endif
</td>
</tr>

@if(isset($project_count) && $project_count > 1 && $index < count($projectsList) - 1)
<tr>
<td style="padding:20px 0;">
<hr style="border:none; border-top:2px solid #e5e7eb; margin:0;">
</td>
</tr>
@endif

@endforeach

<tr>
<td style="text-align:center; padding-top:30px;">
<p style="color:#9ca3af; font-size:12px; margin:0;">
© {{ date('Y') }} Your System. All rights reserved.
</p>
</td>
</tr>

</table>

</td>
</tr>
</table>

</body>
</html>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <title>New Project Created</title>
</head>

<body style="margin:0; padding:0; background:#f8f9fa; font-family: Arial, sans-serif;">

    <table width="100%" cellspacing="0" cellpadding="0" style="padding: 40px 0;">
        <tr>
            <td align="center">

                <!-- CARD -->
                <table width="600" cellspacing="0" cellpadding="0" 
                       style="background:#ffffff; padding:30px; border-radius:16px; 
                              box-shadow:0 4px 20px rgba(0,0,0,0.06); border:1px solid #e5e7eb;">

                    <!-- Header -->
                    <tr>
                        <td style="text-align:center; padding-bottom:20px;">
                            <h2 style="margin:0; font-size:24px; color:#111827; font-weight:700;">
                                🎉 New Project Created
                            </h2>
                            <p style="margin-top:8px; color:#6b7280; font-size:14px;">
                                A new project has been generated automatically.
                            </p>
                        </td>
                    </tr>

                    <!-- Project Info -->
                    <tr>
                        <td style="padding:20px; background:#f9fafb; border-radius:12px; border:1px solid #e5e7eb;">
                            <h3 style="margin:0 0 12px; font-size:18px; color:#111827;">📁 Project Details</h3>
                            <p style="margin:4px 0; color:#4b5563;"><strong>ID:</strong> {{ $project->id }}</p>
                            <p style="margin:4px 0; color:#4b5563;"><strong>Name:</strong> {{ $project->name }}</p>
                            <p style="margin:4px 0; color:#4b5563;"><strong>Status:</strong> {{ $project->statu }}</p>
                            <p style="margin:4px 0; color:#4b5563;"><strong>Description:</strong> {{ $project->description }}</p>
                            <p style="margin:4px 0; color:#4b5563;"><strong>Start Date:</strong> {{ $project->start_date }}</p>
                            <p style="margin:4px 0; color:#4b5563;"><strong>Estimated End:</strong> {{ $project->estimated_end_date }}</p>
                        </td>
                    </tr>

                    <!-- Client Info -->
                    <tr>
                        <td style="padding:20px 0 0;">
                            <h3 style="margin:0 0 12px; font-size:18px; color:#111827;">👤 Client Information</h3>
                            <table width="100%" style="background:#f9fafb; padding:20px; border-radius:12px; border:1px solid #e5e7eb;">
                                <tr><td style="color:#4b5563;"><strong>Name:</strong> {{ $client->name }}</td></tr>
                                <tr><td style="color:#4b5563;"><strong>Email:</strong> {{ $client->email ?? 'N/A' }}</td></tr>
                                <tr><td style="color:#4b5563;"><strong>Phone:</strong> {{ $client->phone ?? 'N/A' }}</td></tr>
                            </table>
                        </td>
                    </tr>

                    <!-- Invoice Info -->
                    <tr>
                        <td style="padding:20px 0 0;">
                            <h3 style="margin:0 0 12px; font-size:18px; color:#111827;">📄 Invoice Details</h3>
                            <table width="100%" style="background:#f9fafb; padding:20px; border-radius:12px; border:1px solid #e5e7eb;">
                                <tr><td style="color:#4b5563;"><strong>Invoice ID:</strong> {{ $invoice->id }}</td></tr>
                                <tr><td style="color:#4b5563;"><strong>Total:</strong> {{ $invoice->total }}</td></tr>
                                <tr><td style="color:#4b5563;"><strong>Status:</strong> {{ $invoice->status }}</td></tr>
                            </table>
                        </td>
                    </tr>

                    <!-- Tasks -->
                    <tr>
                        <td style="padding:20px 0 0;">
                            <h3 style="margin:0 0 12px; font-size:18px; color:#111827;">📝 Project Tasks</h3>
                            <table width="100%" style="background:#f9fafb; padding:20px; border-radius:12px; border:1px solid #e5e7eb;">
                                @foreach($tasks as $task)
                                    <tr>
                                        <td style="padding:10px 0; border-bottom:1px solid #e5e7eb;">
                                            <strong style="color:#111827;">{{ $task->title }}</strong><br>
                                            <span style="color:#6b7280; font-size:13px;">
                                                {{ $task->description }}  
                                                — {{ $task->percentage }}%  
                                                — {{ $task->estimated_time }}h
                                            </span>
                                        </td>
                                    </tr>
                                @endforeach
                            </table>
                        </td>
                    </tr>

                    <!-- Assigned Team -->
                    <tr>
                        <td style="padding:20px 0 0;">
                            <h3 style="margin:0 0 12px; font-size:18px; color:#111827;">👥 Assigned Team Member</h3>

                            @if($assigned_team && $assigned_team->teamUser)
                                <table width="100%" style="background:#f9fafb; padding:20px; border-radius:12px; border:1px solid #e5e7eb;">
                                    <tr><td style="color:#4b5563;"><strong>Name:</strong> {{ $assigned_team->teamUser->name }}</td></tr>
                                    <tr><td style="color:#4b5563;"><strong>Email:</strong> {{ $assigned_team->teamUser->email }}</td></tr>
                                </table>
                            @else
                                <p style="color:#6b7280;">No team member assigned.</p>
                            @endif
                        </td>
                    </tr>

                    <!-- Footer -->
                    <tr>
                        <td style="text-align:center; padding-top:30px;">
                            <p style="color:#9ca3af; font-size:12px; margin:0;">
                                © {{ date('Y') }} Your System. All rights reserved.
                            </p>
                        </td>
                    </tr>

                </table>
                <!-- END CARD -->

            </td>
        </tr>
    </table>

</body>
</html>

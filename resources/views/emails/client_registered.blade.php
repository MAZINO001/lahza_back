<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <title>Welcome to Our Platform</title>
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
                                🎉 Welcome to Our Platform!
                            </h2>
                            <p style="margin-top:8px; color:#6b7280; font-size:14px;">
                                Your account has been successfully created.
                            </p>
                        </td>
                    </tr>

                    <!-- Account Info -->
                    <tr>
                        <td style="padding:20px; background:#f9fafb; border-radius:12px; border:1px solid #e5e7eb;">
                            <h3 style="margin:0 0 12px; font-size:18px; color:#111827;">👤 Your Account Details</h3>
                            <p style="margin:4px 0; color:#4b5563;"><strong>Name:</strong> {{ $user->name }}</p>
                            <p style="margin:4px 0; color:#4b5563;"><strong>Email:</strong> {{ $user->email }}</p>
                            @if(isset($client) && $client->client_number)
                                <p style="margin:4px 0; color:#4b5563;"><strong>Client Number:</strong> {{ $client->client_number }}</p>
                            @endif
                        </td>
                    </tr>

                    @if(isset($client))
                        <!-- Client Information -->
                        <tr>
                            <td style="padding:20px 0 0;">
                                <h3 style="margin:0 0 12px; font-size:18px; color:#111827;">📋 Client Information</h3>
                                <table width="100%" style="background:#f9fafb; padding:20px; border-radius:12px; border:1px solid #e5e7eb;">
                                    @if($client->company)
                                        <tr><td style="color:#4b5563;"><strong>Company:</strong> {{ $client->company }}</td></tr>
                                    @endif
                                    @if($client->phone)
                                        <tr><td style="color:#4b5563;"><strong>Phone:</strong> {{ $client->phone }}</td></tr>
                                    @endif
                                    @if($client->address)
                                        <tr><td style="color:#4b5563;"><strong>Address:</strong> {{ $client->address }}</td></tr>
                                    @endif
                                    @if($client->city)
                                        <tr><td style="color:#4b5563;"><strong>City:</strong> {{ $client->city }}</td></tr>
                                    @endif
                                    @if($client->country)
                                        <tr><td style="color:#4b5563;"><strong>Country:</strong> {{ $client->country }}</td></tr>
                                    @endif
                                    @if($client->client_type)
                                        <tr><td style="color:#4b5563;"><strong>Client Type:</strong> {{ ucfirst($client->client_type) }}</td></tr>
                                    @endif
                                </table>
                            </td>
                        </tr>
                    @endif

                    <!-- Welcome Message -->
                    <tr>
                        <td style="padding:20px 0 0;">
                            <p style="color:#4b5563; line-height:1.6;">
                                Thank you for registering with us! We're excited to have you on board. 
                                You can now access your account and start managing your projects.
                            </p>
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


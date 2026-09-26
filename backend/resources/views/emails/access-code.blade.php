<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Your LinkForge Access Code</title>
</head>
<body style="margin: 0; padding: 0; background-color: #f4f5f9; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;">

    <!-- Wrapper -->
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color: #f4f5f9; padding: 40px 20px;">
        <tr>
            <td align="center">

                <!-- Card -->
                <table role="presentation" width="560" cellpadding="0" cellspacing="0" style="max-width: 560px; width: 100%; background-color: #ffffff; border-radius: 16px; overflow: hidden; box-shadow: 0 4px 24px rgba(0,0,0,0.06);">

                    <!-- Header bar -->
                    <tr>
                        <td style="background: linear-gradient(135deg, #7167ff, #5146e5); padding: 32px 40px; text-align: center;">
                            <table role="presentation" cellpadding="0" cellspacing="0" style="margin: 0 auto;">
                                <tr>
                                    <td style="width: 34px; height: 34px; background: rgba(255,255,255,0.2); border-radius: 10px; text-align: center; vertical-align: middle; padding: 0;">
                                        <span style="color: #ffffff; font-size: 18px; font-weight: bold;">⟁</span>
                                    </td>
                                    <td style="padding-left: 10px; color: #ffffff; font-size: 18px; font-weight: 700; letter-spacing: -0.02em;">
                                        LinkForge
                                    </td>
                                </tr>
                            </table>
                            <p style="margin: 14px 0 0; color: rgba(255,255,255,0.8); font-size: 13px; font-weight: 500;">
                                Private URL Shortening Service
                            </p>
                        </td>
                    </tr>

                    <!-- Body -->
                    <tr>
                        <td style="padding: 36px 40px;">

                            <h1 style="margin: 0 0 8px; color: #1a1d2e; font-size: 22px; font-weight: 700; letter-spacing: -0.03em;">
                                Your Access Code
                            </h1>

                            <p style="margin: 0 0 28px; color: #6b7280; font-size: 14px; line-height: 1.6;">
                                You've been granted access to create short URLs on LinkForge.
                                Use the code below when generating links.
                            </p>

                            <!-- Code box -->
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                                <tr>
                                    <td style="background-color: #f8f7ff; border: 2px dashed #c4bfff; border-radius: 12px; padding: 22px; text-align: center;">
                                        <p style="margin: 0 0 6px; color: #6b7280; font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.1em;">
                                            Your Secret Code
                                        </p>
                                        <p style="margin: 0; color: #5146e5; font-size: 28px; font-weight: 800; letter-spacing: 0.06em; font-family: 'Courier New', monospace;">
                                            {{ $code }}
                                        </p>
                                    </td>
                                </tr>
                            </table>

                            @if($description)
                            <!-- Description -->
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-top: 20px;">
                                <tr>
                                    <td style="background-color: #f9fafb; border-radius: 10px; padding: 16px 18px;">
                                        <p style="margin: 0 0 4px; color: #6b7280; font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.08em;">
                                            About This Code
                                        </p>
                                        <p style="margin: 0; color: #374151; font-size: 14px; line-height: 1.55;">
                                            {{ $description }}
                                        </p>
                                    </td>
                                </tr>
                            </table>
                            @endif

                            <!-- Details -->
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-top: 24px; border-top: 1px solid #f0f0f5; padding-top: 20px;">
                                <tr>
                                    <td style="padding: 6px 0; color: #9ca3af; font-size: 12px; font-weight: 600;">
                                        Assigned to
                                    </td>
                                    <td style="padding: 6px 0; color: #374151; font-size: 12px; font-weight: 600; text-align: right;">
                                        {{ $email }}
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding: 6px 0; color: #9ca3af; font-size: 12px; font-weight: 600;">
                                        Expires
                                    </td>
                                    <td style="padding: 6px 0; color: #374151; font-size: 12px; font-weight: 600; text-align: right;">
                                        {{ $expiresAt ? $expiresAt->format('M d, Y') : 'Never' }}
                                    </td>
                                </tr>
                            </table>

                            <!-- CTA button -->
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-top: 28px;">
                                <tr>
                                    <td align="center">
                                        <a href="{{ $appUrl }}"
                                           style="display: inline-block; padding: 14px 32px; background: linear-gradient(135deg, #7167ff, #5146e5); color: #ffffff; font-size: 14px; font-weight: 700; text-decoration: none; border-radius: 10px;">
                                            Create Short URL →
                                        </a>
                                    </td>
                                </tr>
                            </table>

                        </td>
                    </tr>

                    <!-- Footer -->
                    <tr>
                        <td style="padding: 24px 40px; background-color: #fafbfc; border-top: 1px solid #f0f0f5; text-align: center;">
                            <p style="margin: 0 0 6px; color: #9ca3af; font-size: 11px;">
                                This is a private access code. Do not share it with unauthorized individuals.
                            </p>
                            <p style="margin: 0; color: #d1d5db; font-size: 10px;">
                                © {{ date('Y') }} LinkForge · Private URL Shortening Service
                            </p>
                        </td>
                    </tr>

                </table>

            </td>
        </tr>
    </table>

</body>
</html>

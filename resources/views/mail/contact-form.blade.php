<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contact Form Submission</title>
</head>
<body style="margin: 0; padding: 0; background-color: #0d1b2a; font-family: 'Courier New', Courier, monospace;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color: #0d1b2a; padding: 40px 20px;">
        <tr>
            <td align="center">
                <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="max-width: 600px; width: 100%;">
                    <tr>
                        <td style="padding: 30px 40px; border: 1px solid #1e3a5f; border-bottom: none; border-radius: 8px 8px 0 0; background-color: #11243b;">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                                <tr>
                                    <td>
                                        <span style="color: #607B96; font-size: 14px;">// new message from</span>
                                        <br>
                                        <span style="font-size: 22px; color: #ffffff; font-weight: bold;">{{ $name }}</span>
                                    </td>
                                    <td align="right" style="vertical-align: top;">
                                        <span style="color: #7b5ea7; font-size: 13px;">reno.sh</span>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding: 30px 40px; border-left: 1px solid #1e3a5f; border-right: 1px solid #1e3a5f; background-color: #0f1d30;">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-bottom: 20px;">
                                <tr>
                                    <td style="padding: 12px 16px; background-color: #11243b; border-radius: 6px; border-left: 3px solid #7b5ea7;">
                                        <span style="color: #607B96; font-size: 12px; text-transform: uppercase; letter-spacing: 1px;">From</span>
                                        <br>
                                        <span style="color: #ffffff; font-size: 14px;">{{ $name }}</span>
                                        <span style="color: #607B96; font-size: 14px;">&lt;{{ $email }}&gt;</span>
                                    </td>
                                </tr>
                            </table>

                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-bottom: 20px;">
                                <tr>
                                    <td style="padding: 12px 16px; background-color: #11243b; border-radius: 6px; border-left: 3px solid #43d9ad;">
                                        <span style="color: #607B96; font-size: 12px; text-transform: uppercase; letter-spacing: 1px;">Subject</span>
                                        <br>
                                        <span style="color: #ffffff; font-size: 14px;">{{ $contactSubject }}</span>
                                    </td>
                                </tr>
                            </table>

                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                                <tr>
                                    <td style="padding: 12px 16px; background-color: #11243b; border-radius: 6px; border-left: 3px solid #4d5bce;">
                                        <span style="color: #607B96; font-size: 12px; text-transform: uppercase; letter-spacing: 1px;">Message</span>
                                        <br><br>
                                        <span style="color: #c5c8d4; font-size: 14px; line-height: 1.7; white-space: pre-wrap;">{{ $contactMessage }}</span>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding: 20px 40px; border: 1px solid #1e3a5f; border-top: none; border-radius: 0 0 8px 8px; background-color: #11243b; text-align: center;">
                            <span style="color: #607B96; font-size: 12px;">
                                Reply directly to this email to respond to {{ $name }}.
                            </span>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>

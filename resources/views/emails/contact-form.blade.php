<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>{{ $websiteName }} | New Form Submission</title>
</head>
<body style="margin:0;padding:24px;background:#f5f5f5;font-family:Arial,sans-serif;color:#1a1a1a;">
    <div style="max-width:640px;margin:0 auto;background:#ffffff;border-radius:12px;overflow:hidden;border:1px solid #e5e5e5;">
        <div style="background:#111111;color:#ffffff;padding:24px 28px;">
            <div style="font-size:12px;letter-spacing:.08em;text-transform:uppercase;opacity:.75;">Website Name</div>
            <h1 style="margin:8px 0 0;font-size:28px;line-height:1.2;">{{ $websiteName }}</h1>
        </div>
        <div style="padding:28px;">
            <p style="margin:0 0 16px;font-size:16px;line-height:1.6;">A new contact form submission was received from <strong>{{ $websiteName }}</strong>.</p>
            <table style="width:100%;border-collapse:collapse;font-size:15px;line-height:1.6;">
                <tr>
                    <td style="padding:10px 0;border-bottom:1px solid #ececec;width:160px;"><strong>Website</strong></td>
                    <td style="padding:10px 0;border-bottom:1px solid #ececec;">{{ $websiteUrl }}</td>
                </tr>
                <tr>
                    <td style="padding:10px 0;border-bottom:1px solid #ececec;"><strong>Page</strong></td>
                    <td style="padding:10px 0;border-bottom:1px solid #ececec;">{{ $pageTitle }}</td>
                </tr>
                <tr>
                    <td style="padding:10px 0;border-bottom:1px solid #ececec;"><strong>Name</strong></td>
                    <td style="padding:10px 0;border-bottom:1px solid #ececec;">{{ $senderName }}</td>
                </tr>
                <tr>
                    <td style="padding:10px 0;border-bottom:1px solid #ececec;"><strong>Email</strong></td>
                    <td style="padding:10px 0;border-bottom:1px solid #ececec;">{{ $senderEmail }}</td>
                </tr>
            </table>
            <div style="margin-top:24px;">
                <div style="font-size:14px;font-weight:bold;margin-bottom:8px;">Message</div>
                <div style="padding:16px;background:#fafafa;border:1px solid #ececec;border-radius:10px;font-size:15px;line-height:1.7;">{!! $senderMessage !!}</div>
            </div>
        </div>
    </div>
</body>
</html>

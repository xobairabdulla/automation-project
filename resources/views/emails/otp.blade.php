<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>Your OTP</title>
</head>
<body>
    <div style="font-family: system-ui, -apple-system, 'Segoe UI', Roboto, 'Helvetica Neue', Arial; max-width:600px; margin:auto; padding:20px;">
        <h2 style="margin-bottom:0.25rem;">Your verification code</h2>
        <p style="margin-top:0.25rem; color:#555;">Use the code below to sign in. It expires in {{ $expires }} minutes.</p>

        <div style="font-size:32px; font-weight:700; letter-spacing:6px; margin:24px 0; padding:20px 24px; border-radius:10px; background:#f3f4f6; display:inline-block; color:#1a1a2e;">
            {{ $code }}
        </div>

        <p style="color:#777; font-size:13px; margin-top:1rem;">
            If you didn't request this, you can safely ignore this email.
        </p>
    </div>
</body>
</html>

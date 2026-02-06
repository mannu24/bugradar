<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Integration Successful - BugRadar</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .container {
            background: white;
            border-radius: 20px;
            padding: 40px;
            max-width: 500px;
            width: 100%;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            text-align: center;
        }
        .success-icon {
            width: 80px;
            height: 80px;
            background: #10b981;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            animation: scaleIn 0.5s ease-out;
        }
        .success-icon svg {
            width: 50px;
            height: 50px;
            stroke: white;
            stroke-width: 3;
            fill: none;
        }
        h1 {
            color: #1f2937;
            font-size: 28px;
            margin-bottom: 10px;
        }
        .platform {
            color: #667eea;
            font-weight: bold;
        }
        p {
            color: #6b7280;
            font-size: 16px;
            line-height: 1.6;
            margin-bottom: 20px;
        }
        .info-box {
            background: #f3f4f6;
            border-radius: 10px;
            padding: 20px;
            margin: 20px 0;
            text-align: left;
        }
        .info-item {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid #e5e7eb;
        }
        .info-item:last-child {
            border-bottom: none;
        }
        .info-label {
            color: #6b7280;
            font-weight: 500;
        }
        .info-value {
            color: #1f2937;
            font-weight: 600;
        }
        .button {
            display: inline-block;
            background: #667eea;
            color: white;
            padding: 12px 30px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            margin-top: 20px;
            transition: background 0.3s;
        }
        .button:hover {
            background: #5568d3;
        }
        .note {
            background: #fef3c7;
            border-left: 4px solid #f59e0b;
            padding: 15px;
            margin-top: 20px;
            text-align: left;
            border-radius: 5px;
        }
        .note strong {
            color: #92400e;
        }
        @keyframes scaleIn {
            from {
                transform: scale(0);
            }
            to {
                transform: scale(1);
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="success-icon">
            <svg viewBox="0 0 24 24">
                <polyline points="20 6 9 17 4 12"></polyline>
            </svg>
        </div>
        
        <h1>✅ <span class="platform">{{ $platform }}</span> Connected!</h1>
        <p>Your {{ $platform }} account has been successfully connected to BugRadar.</p>
        
        <div class="info-box">
            <div class="info-item">
                <span class="info-label">Platform:</span>
                <span class="info-value">{{ $platform }}</span>
            </div>
            <div class="info-item">
                <span class="info-label">Username:</span>
                <span class="info-value">{{ $username }}</span>
            </div>
            <div class="info-item">
                <span class="info-label">Integration ID:</span>
                <span class="info-value">#{{ $integration_id }}</span>
            </div>
            <div class="info-item">
                <span class="info-label">Status:</span>
                <span class="info-value">🟢 Active</span>
            </div>
        </div>
        
        <div class="note">
            <strong>📊 Data Sync Started</strong><br>
            Your pull requests, issues, and reviews are being synced in the background. This may take a few moments.
        </div>
        
        <a href="http://localhost:8006/api/integrations" class="button">View All Integrations</a>
        
        <p style="margin-top: 30px; font-size: 14px; color: #9ca3af;">
            You can now close this window and use the BugRadar API or mobile app.
        </p>
    </div>
</body>
</html>

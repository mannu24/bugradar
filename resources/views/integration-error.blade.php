<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Integration Error - BugRadar</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
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
        .error-icon {
            width: 80px;
            height: 80px;
            background: #ef4444;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            animation: shake 0.5s ease-out;
        }
        .error-icon svg {
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
            color: #ef4444;
            font-weight: bold;
        }
        p {
            color: #6b7280;
            font-size: 16px;
            line-height: 1.6;
            margin-bottom: 20px;
        }
        .error-box {
            background: #fef2f2;
            border: 2px solid #fecaca;
            border-radius: 10px;
            padding: 20px;
            margin: 20px 0;
            text-align: left;
        }
        .error-title {
            color: #991b1b;
            font-weight: 600;
            margin-bottom: 10px;
        }
        .error-message {
            color: #dc2626;
            font-family: monospace;
            font-size: 14px;
            background: white;
            padding: 10px;
            border-radius: 5px;
            word-break: break-word;
        }
        .solutions {
            background: #f3f4f6;
            border-radius: 10px;
            padding: 20px;
            margin: 20px 0;
            text-align: left;
        }
        .solutions h3 {
            color: #1f2937;
            font-size: 18px;
            margin-bottom: 15px;
        }
        .solutions ul {
            list-style: none;
            padding: 0;
        }
        .solutions li {
            padding: 10px 0;
            padding-left: 25px;
            position: relative;
            color: #4b5563;
        }
        .solutions li:before {
            content: "→";
            position: absolute;
            left: 0;
            color: #667eea;
            font-weight: bold;
        }
        .button {
            display: inline-block;
            background: #667eea;
            color: white;
            padding: 12px 30px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            margin: 10px 5px;
            transition: background 0.3s;
        }
        .button:hover {
            background: #5568d3;
        }
        .button-secondary {
            background: #6b7280;
        }
        .button-secondary:hover {
            background: #4b5563;
        }
        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-10px); }
            75% { transform: translateX(10px); }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="error-icon">
            <svg viewBox="0 0 24 24">
                <line x1="18" y1="6" x2="6" y2="18"></line>
                <line x1="6" y1="6" x2="18" y2="18"></line>
            </svg>
        </div>
        
        <h1>❌ <span class="platform">{{ $platform }}</span> Connection Failed</h1>
        <p>We couldn't connect your {{ $platform }} account to BugRadar.</p>
        
        <div class="error-box">
            <div class="error-title">Error Details:</div>
            <div class="error-message">{{ $error }}</div>
        </div>
        
        <div class="solutions">
            <h3>💡 Possible Solutions:</h3>
            <ul>
                <li>Make sure you're logged in to BugRadar first</li>
                <li>Try logging in again: <a href="http://localhost:8006/api/auth/google">Login with Google</a></li>
                <li>Check if your {{ $platform }} OAuth app is configured correctly</li>
                <li>Verify the redirect URI matches in {{ $platform }} settings</li>
                <li>Check the Laravel logs for more details</li>
            </ul>
        </div>
        
        <a href="http://localhost:8006/api/auth/google" class="button">Login First</a>
        <a href="http://localhost:8006/api/integrations/{{ strtolower($platform) }}/connect" class="button button-secondary">Try Again</a>
        
        <p style="margin-top: 30px; font-size: 14px; color: #9ca3af;">
            Need help? Check the logs: <code>storage/logs/laravel.log</code>
        </p>
    </div>
</body>
</html>

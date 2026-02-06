<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Successful - BugRadar</title>
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
            max-width: 400px;
            width: 100%;
            text-align: center;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
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
            stroke-linecap: round;
            stroke-linejoin: round;
            fill: none;
        }
        
        h1 {
            color: #1f2937;
            font-size: 24px;
            margin-bottom: 10px;
        }
        
        p {
            color: #6b7280;
            font-size: 16px;
            margin-bottom: 30px;
        }
        
        .button {
            display: inline-block;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            text-decoration: none;
            padding: 15px 40px;
            border-radius: 10px;
            font-weight: 600;
            font-size: 16px;
            transition: transform 0.2s, box-shadow 0.2s;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
        }
        
        .button:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.6);
        }
        
        .button:active {
            transform: translateY(0);
        }
        
        .countdown {
            color: #9ca3af;
            font-size: 14px;
            margin-top: 20px;
        }
        
        @keyframes scaleIn {
            from {
                transform: scale(0);
            }
            to {
                transform: scale(1);
            }
        }
        
        .spinner {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid rgba(255, 255, 255, 0.3);
            border-radius: 50%;
            border-top-color: white;
            animation: spin 1s linear infinite;
            margin-left: 10px;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
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
        
        <h1>Login Successful!</h1>
        <p>You've been authenticated successfully.</p>
        
        <a href="{{ $deepLink }}" class="button" id="returnButton">
            Return to BugRadar App
            <span class="spinner" id="spinner" style="display: none;"></span>
        </a>
        
        <p class="countdown" id="countdown">Redirecting automatically in <span id="seconds">3</span> seconds...</p>
    </div>
    
    <script>
        const deepLink = '{{ $deepLink }}';
        let seconds = 3;
        const countdownEl = document.getElementById('countdown');
        const secondsEl = document.getElementById('seconds');
        const buttonEl = document.getElementById('returnButton');
        const spinnerEl = document.getElementById('spinner');
        
        // Countdown timer
        const timer = setInterval(() => {
            seconds--;
            secondsEl.textContent = seconds;
            
            if (seconds <= 0) {
                clearInterval(timer);
                redirect();
            }
        }, 1000);
        
        // Manual button click
        buttonEl.addEventListener('click', (e) => {
            e.preventDefault();
            clearInterval(timer);
            redirect();
        });
        
        function redirect() {
            countdownEl.textContent = 'Opening app...';
            spinnerEl.style.display = 'inline-block';
            
            // Try to open the app
            window.location.href = deepLink;
            
            // Fallback: If app doesn't open in 2 seconds, show message
            setTimeout(() => {
                countdownEl.textContent = 'If the app didn\'t open, please tap the button above.';
                spinnerEl.style.display = 'none';
            }, 2000);
        }
    </script>
</body>
</html>

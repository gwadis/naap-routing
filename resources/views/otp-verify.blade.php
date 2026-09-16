<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NAAP Routing - OTP Verification</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        :root {
            --blue: #1e3a8a;
            --bg: #071230;
            --panel: rgba(10, 14, 38, .85);
            --neon: #3b82f6;
            --neon2: #1d4ed8;
            --text: #eff3ff;
            --panel-border: rgba(70,157,255,.22);
            --body-bg: radial-gradient(circle at top left, rgba(0,240,255,.16), transparent 34%), 
                       radial-gradient(circle at bottom right, rgba(180,0,255,.14), transparent 32%), 
                       linear-gradient(140deg, #060c28 0%, #091644 55%, #040a21 100%);
        }
        * { box-sizing: border-box; }
        body {
            margin:0;
            min-height:100vh;
            font-family:'Poppins', 'Inter', sans-serif;
            color: var(--text);
            background: var(--body-bg);
            display:grid;
            place-items:center;
        }
        .wrapper { 
            width:min(450px, 92vw); 
            padding: 2.2rem; 
            background: var(--panel); 
            border:1px solid var(--panel-border); 
            border-radius:16px; 
            box-shadow:0 20px 50px rgba(0,0,0,.35); 
            text-align: center;
        }
        h1 { margin:0 0 0.8rem; font-size:1.8rem; color: #c6e5ff; letter-spacing: -0.5px; }
        p.subtitle { margin:0 0 1.5rem; color:#94b7d9; font-size: 0.95rem; line-height: 1.4; }
        .info-box { 
            background: rgba(59, 130, 246, 0.1); 
            border: 1px solid rgba(59, 130, 246, 0.3); 
            border-radius: 10px; 
            padding: 1rem; 
            margin-bottom: 1.5rem; 
            color: #a8c5e0; 
            font-size: 0.85rem; 
            line-height: 1.5; 
            text-align: left;
        }
        .otp-input-container {
            display: flex;
            gap: 0.5rem;
            justify-content: center;
            margin: 1.8rem 0;
        }
        .otp-digit {
            width: 50px;
            height: 55px;
            border: 1px solid rgba(139,171,255,.27);
            border-radius: 8px;
            background: rgba(24,40,82,.64);
            color: #e9f3ff;
            font-size: 1.6rem;
            text-align: center;
            transition: 0.3s;
            font-weight: 700;
        }
        .otp-digit:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 12px rgba(59,130,246,.3);
        }
        .btn { 
            width:100%; 
            padding:0.9rem; 
            border:none; 
            border-radius:10px; 
            background:linear-gradient(90deg, var(--neon) 0%, var(--neon2) 100%); 
            color:#ffffff; 
            font-weight:700; 
            cursor:pointer; 
            transition:.25s; 
            margin-top: 0.5rem; 
            font-size: 1rem;
        }
        .btn:hover { 
            transform:translateY(-2px); 
            box-shadow: 0 8px 25px rgba(59,130,246,.4); 
        }
        .btn:disabled { 
            opacity: 0.6; 
            cursor: not-allowed; 
            transform: none; 
            box-shadow: none;
        }
        .resend-container {
            margin-top: 1.5rem;
            font-size: 0.9rem;
            color: #94b7d9;
        }
        .resend-btn {
            background: none;
            border: none;
            color: #3b82f6;
            font-weight: 600;
            cursor: pointer;
            padding: 0;
            text-decoration: underline;
        }
        .resend-btn:disabled {
            color: #647b9b;
            cursor: not-allowed;
            text-decoration: none;
        }
        .errors { 
            margin:0 0 1.2rem; 
            color:#ff8ba7; 
            text-align:center; 
            background: rgba(255, 139, 167, 0.1); 
            padding: 0.7rem; 
            border-radius: 8px; 
            font-size: 0.85rem; 
            border: 1px solid rgba(255, 139, 167, 0.2); 
        }
        .success-box {
            margin:0 0 1.2rem; 
            color:#10b981; 
            text-align:center; 
            background: rgba(16, 185, 129, 0.1); 
            padding: 0.7rem; 
            border-radius: 8px; 
            font-size: 0.85rem; 
            border: 1px solid rgba(16, 185, 129, 0.2);
        }
        .footer-text { 
            margin-top:1.5rem; 
            font-size:0.75rem; 
            color:#647b9b; 
            border-top: 1px solid rgba(255,255,255,0.05); 
            padding-top: 1.2rem; 
        }
        .footer-text a {
            color: #86b2e4;
            text-decoration: none;
        }
        .footer-text a:hover {
            color: var(--neon);
        }
    </style>
</head>
<body>
    <div class="wrapper">
        <h1><i class="bi bi-shield-lock-fill me-2" style="color: #3b82f6;"></i>Email Verification</h1>
        <p class="subtitle">Secure One-Time Password Verification</p>

        <div class="info-box">
            <strong><i class="bi bi-envelope-fill me-1"></i> Check your email:</strong> We've sent a 6-digit OTP code to your registered Gmail address. It expires in 5 minutes.
        </div>

        @if(session('error'))
            <div class="errors">{{ session('error') }}</div>
        @endif

        @if(session('success'))
            <div class="success-box">{{ session('success') }}</div>
        @endif

        <form method="POST" action="{{ route('login.otp.verify.submit') }}" id="otpForm">
            @csrf
            
            <div class="otp-input-container">
                <input type="text" class="otp-digit" maxlength="1" required autofocus pattern="[0-9]" inputmode="numeric">
                <input type="text" class="otp-digit" maxlength="1" required pattern="[0-9]" inputmode="numeric">
                <input type="text" class="otp-digit" maxlength="1" required pattern="[0-9]" inputmode="numeric">
                <input type="text" class="otp-digit" maxlength="1" required pattern="[0-9]" inputmode="numeric">
                <input type="text" class="otp-digit" maxlength="1" required pattern="[0-9]" inputmode="numeric">
                <input type="text" class="otp-digit" maxlength="1" required pattern="[0-9]" inputmode="numeric">
            </div>
            
            <!-- Hidden input to store combined code -->
            <input type="hidden" name="otp" id="combinedOtp">

            <button type="submit" class="btn" id="submitBtn">Verify OTP Code</button>
        </form>

        <div class="resend-container">
            Didn't receive the code? 
            <form method="POST" action="{{ route('login.otp.resend') }}" style="display:inline;" id="resendForm">
                @csrf
                <button type="submit" class="resend-btn" id="resendBtn" disabled>Resend Code</button>
            </form>
            <span id="countdownText">(wait <span id="timer">60</span>s)</span>
        </div>

        <div class="footer-text">
            Need help? <a href="{{ route('login') }}">Return to Login</a>
        </div>
    </div>

    <script>
        const digits = document.querySelectorAll('.otp-digit');
        const combinedOtp = document.getElementById('combinedOtp');
        const otpForm = document.getElementById('otpForm');

        // Focus navigation logic
        digits.forEach((digit, index) => {
            digit.addEventListener('input', (e) => {
                if (digit.value.length === 1 && index < digits.length - 1) {
                    digits[index + 1].focus();
                }
                combineDigits();
            });

            digit.addEventListener('keydown', (e) => {
                if (e.key === 'Backspace' && digit.value.length === 0 && index > 0) {
                    digits[index - 1].focus();
                }
            });

            // Prevent non-numeric chars
            digit.addEventListener('keypress', (e) => {
                if (isNaN(e.key)) {
                    e.preventDefault();
                }
            });
        });

        function combineDigits() {
            let code = '';
            digits.forEach(d => {
                code += d.value;
            });
            combinedOtp.value = code;
            
            // Auto submit when 6 digits are filled
            if (code.length === 6) {
                setTimeout(() => {
                    otpForm.dispatchEvent(new Event('submit'));
                    otpForm.submit();
                }, 150);
            }
        }

        // Form loading state
        otpForm.addEventListener('submit', function() {
            const submitBtn = document.getElementById('submitBtn');
            submitBtn.disabled = true;
            submitBtn.innerText = 'Verifying...';
            digits.forEach(d => d.readOnly = true);
            const resendBtn = document.getElementById('resendBtn');
            if (resendBtn) {
                resendBtn.disabled = true;
            }
        });

        // Resend code countdown timer
        const timerSpan = document.getElementById('timer');
        const countdownText = document.getElementById('countdownText');
        const resendBtn = document.getElementById('resendBtn');
        let timeLeft = 60;

        function startTimer() {
            const timerInterval = setInterval(() => {
                timeLeft--;
                timerSpan.textContent = timeLeft;
                if (timeLeft <= 0) {
                    clearInterval(timerInterval);
                    resendBtn.disabled = false;
                    countdownText.style.display = 'none';
                }
            }, 1000);
        }

        startTimer();
    </script>
</body>
</html>

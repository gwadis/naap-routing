<!DOCTYPE html>
<html lang="en" data-theme="light">
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
            --blue: #1E3A8A;
            --bg: #071230;
            --panel: rgba(10, 14, 38, .85);
            --panel-border: rgba(70,157,255,.22);
            --panel-shadow: rgba(0,0,0,.35);
            --neon: #3B82F6;
            --neon2: #1E3A8A;
            --text: #eff3ff;
            --heading: #c6e5ff;
            --subtitle: #94b7d9;
            --label: #aac8ff;
            --input-bg: rgba(24,40,82,.64);
            --input-text: #e9f3ff;
            --btn-text: #ffffff;
            --helper: #86b2e4;
            --helper-hover: var(--neon);
            --error-text: #ff8ba7;
            --error-bg: rgba(255, 139, 167, 0.1);
            --placeholder: rgba(255,255,255,0.65);
            --small: #647b9b;
            --info-text: #a8c5e0;
            --info-bg: rgba(0, 215, 255, 0.1);
            --info-border: rgba(0, 215, 255, 0.2);
            --body-bg: radial-gradient(circle at top left, rgba(0,240,255,.16), transparent 34%), radial-gradient(circle at bottom right, rgba(180,0,255,.14), transparent 32%), linear-gradient(140deg, #060c28 0%, #091644 55%, #040a21 100%);
        }
        html[data-theme='light'] {
            --bg: #f8fafc;
            --panel: rgba(248, 250, 252, 0.92);
            --panel-border: rgba(15, 23, 42, 0.08);
            --panel-shadow: rgba(15, 23, 42, 0.12);
            --text: #0f172a;
            --heading: #1e3a8a;
            --subtitle: #475569;
            --label: #475569;
            --input-bg: rgba(255, 255, 255, 0.95);
            --input-text: #0f172a;
            --btn-text: #ffffff;
            --helper: #475569;
            --helper-hover: #1e3a8a;
            --error-text: #991b1b;
            --error-bg: rgba(254, 202, 202, 0.4);
            --placeholder: rgba(15, 23, 42, 0.5);
            --small: #64748b;
            --info-text: #1e3a8a;
            --info-bg: rgba(219, 234, 254, 0.7);
            --info-border: rgba(148, 163, 184, 0.4);
            --body-bg: linear-gradient(140deg, #f8fafc 0%, #e2e8f0 55%, #cbd5e1 100%);
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
            box-shadow:0 20px 50px var(--panel-shadow); 
            text-align: center;
        }
        h1 { margin:0 0 0.8rem; font-size:1.8rem; color: var(--heading); letter-spacing: -0.5px; }
        p.subtitle { margin:0 0 1.5rem; color: var(--subtitle); font-size: 0.95rem; line-height: 1.4; }
        .info-box { 
            background: var(--info-bg); 
            border: 1px solid var(--info-border); 
            border-radius: 10px; 
            padding: 1rem; 
            margin-bottom: 1.5rem; 
            color: var(--info-text); 
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
            background: var(--input-bg);
            color: var(--input-text);
            font-size: 1.6rem;
            text-align: center;
            transition: 0.3s;
            font-weight: 700;
        }
        html[data-theme='light'] .otp-digit {
            border: 1px solid rgba(15, 23, 42, 0.15);
        }
        .otp-digit:focus {
            outline: none;
            border-color: #1e3a8a;
            box-shadow: 0 0 12px rgba(30, 58, 138, .2);
        }
        .btn { 
            width:100%; 
            padding:0.9rem; 
            border:none; 
            border-radius:10px; 
            background:linear-gradient(90deg, var(--neon) 0%, var(--neon2) 100%); 
            color: var(--btn-text) !important; 
            font-weight:700; 
            cursor:pointer; 
            transition:.25s; 
            margin-top: 0.5rem; 
            font-size: 1rem;
        }
        .btn:hover { 
            transform:translateY(-2px); 
            box-shadow: 0 8px 25px rgba(30, 58, 138, .3); 
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
            color: var(--subtitle);
        }
        .resend-btn {
            background: none;
            border: none;
            color: var(--helper-hover);
            font-weight: 600;
            cursor: pointer;
            padding: 0;
            text-decoration: underline;
        }
        .resend-btn:disabled {
            color: var(--small);
            cursor: not-allowed;
            text-decoration: none;
        }
        .errors { 
            margin:0 0 1.2rem; 
            color: var(--error-text); 
            text-align:center; 
            background: var(--error-bg); 
            padding: 0.7rem; 
            border-radius: 8px; 
            font-size: 0.85rem; 
            border: 1px solid rgba(255, 139, 167, 0.2); 
        }
        .success-box {
            margin:0 0 1.2rem; 
            color: #047857; 
            text-align:center; 
            background: rgba(16, 185, 129, 0.15); 
            padding: 0.7rem; 
            border-radius: 8px; 
            font-size: 0.85rem; 
            border: 1px solid rgba(16, 185, 129, 0.25);
        }
        .footer-text { 
            margin-top:1.5rem; 
            font-size:0.75rem; 
            color: var(--small); 
            border-top: 1px solid rgba(15, 23, 42, 0.08); 
            padding-top: 1.2rem; 
        }
        html:not([data-theme='light']) .footer-text {
            border-top: 1px solid rgba(255,255,255,0.05);
        }
        .footer-text a {
            color: var(--helper);
            text-decoration: none;
        }
        .footer-text a:hover {
            color: var(--helper-hover);
        }
    </style>
</head>
<body>
    <div class="wrapper">
        <h1><i class="bi bi-shield-lock-fill me-2" style="color: var(--blue);"></i>Email Verification</h1>
        <p class="subtitle">Secure One-Time Password Verification</p>

        <div class="info-box">
            <strong style="color: var(--blue);"><i class="bi bi-envelope-fill me-1"></i> Check your email:</strong> We've sent a 6-digit OTP code to your registered Gmail address. It expires in 5 minutes.
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

        const resendForm = document.getElementById('resendForm');
        if (resendForm) {
            resendForm.addEventListener('submit', function() {
                resendBtn.disabled = true;
                resendBtn.innerText = 'Sending...';
            });
        }

        startTimer();
    </script>
</body>
</html>

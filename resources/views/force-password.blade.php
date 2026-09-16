<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NAAP Routing - Force Password Reset</title>
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
            width:min(480px, 92vw); 
            padding: 2.2rem; 
            background: var(--panel); 
            border:1px solid var(--panel-border); 
            border-radius:16px; 
            box-shadow:0 20px 50px rgba(0,0,0,.35); 
        }
        h1 { margin:0 0 0.8rem; font-size:1.8rem; color: #c6e5ff; letter-spacing: -0.5px; text-align: center; }
        p.subtitle { margin:0 0 1.5rem; color:#94b7d9; font-size: 0.95rem; line-height: 1.4; text-align: center; }
        
        .field { margin-bottom: 1.2rem; }
        .field label { display:block; margin-bottom:0.4rem; color:#aac8ff; font-weight:500; font-size: 0.9rem; }
        .field input { width:100%; padding:0.85rem; border:1px solid rgba(139,171,255,.27); border-radius:10px; background: rgba(24,40,82,.64); color: #e9f3ff; transition: 0.3s; }
        .field input:focus { outline:none; border-color:#3b82f6; box-shadow:0 0 12px rgba(59,130,246,.3); }
        
        /* Strength meter */
        .strength-meter {
            height: 6px;
            background-color: rgba(255,255,255,0.1);
            border-radius: 3px;
            margin-top: 0.5rem;
            overflow: hidden;
            display: flex;
        }
        .strength-bar {
            width: 0%;
            height: 100%;
            transition: width 0.3s ease, background-color 0.3s ease;
        }
        .strength-text {
            font-size: 0.8rem;
            margin-top: 0.3rem;
            color: #94b7d9;
            text-align: right;
            font-weight: 600;
        }

        /* Policy list */
        .policy-list {
            margin: 1.2rem 0;
            padding-left: 0;
            list-style: none;
            font-size: 0.85rem;
            color: #94b7d9;
        }
        .policy-item {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 5px;
        }
        .policy-item.valid {
            color: #10b981;
        }
        .policy-item.valid i {
            color: #10b981;
        }
        .policy-item.invalid i {
            color: #ef4444;
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
    </style>
</head>
<body>
    <div class="wrapper">
        <h1><i class="bi bi-key-fill me-2" style="color: #3b82f6;"></i>Password Setup</h1>
        <p class="subtitle">Please define your secure account credentials to continue.</p>

        @if(session('error'))
            <div class="errors">{{ session('error') }}</div>
        @endif

        @if($errors->any())
            <div class="errors">
                @foreach($errors->all() as $error)
                    <div>{{ $error }}</div>
                @endforeach
            </div>
        @endif

        <form method="POST" action="{{ route('login.password.update') }}" id="passwordForm">
            @csrf
            
            <div class="field">
                <label for="new_password">New Password</label>
                <input id="new_password" name="new_password" type="password" placeholder="Define new password" required autocomplete="off">
                <div class="strength-meter">
                    <div class="strength-bar" id="strengthBar"></div>
                </div>
                <div class="strength-text" id="strengthText">Strength: Weak</div>
            </div>

            <div class="field">
                <label for="new_password_confirmation">Confirm Password</label>
                <input id="new_password_confirmation" name="new_password_confirmation" type="password" placeholder="Retype to confirm" required autocomplete="off">
            </div>

            <ul class="policy-list">
                <li class="policy-item invalid" id="ruleLength"><i class="bi bi-x-circle-fill"></i> Minimum 10 characters</li>
                <li class="policy-item invalid" id="ruleUpper"><i class="bi bi-x-circle-fill"></i> At least one uppercase letter</li>
                <li class="policy-item invalid" id="ruleLower"><i class="bi bi-x-circle-fill"></i> At least one lowercase letter</li>
                <li class="policy-item invalid" id="ruleNumber"><i class="bi bi-x-circle-fill"></i> At least one number</li>
                <li class="policy-item invalid" id="ruleSpecial"><i class="bi bi-x-circle-fill"></i> At least one special character</li>
            </ul>

            <button type="submit" class="btn" id="submitBtn" disabled>Change Password</button>
        </form>
    </div>

    <script>
        const newPassword = document.getElementById('new_password');
        const confirmPassword = document.getElementById('new_password_confirmation');
        const submitBtn = document.getElementById('submitBtn');

        const ruleLength = document.getElementById('ruleLength');
        const ruleUpper = document.getElementById('ruleUpper');
        const ruleLower = document.getElementById('ruleLower');
        const ruleNumber = document.getElementById('ruleNumber');
        const ruleSpecial = document.getElementById('ruleSpecial');

        const strengthBar = document.getElementById('strengthBar');
        const strengthText = document.getElementById('strengthText');

        newPassword.addEventListener('input', validatePassword);
        confirmPassword.addEventListener('input', validatePassword);

        function validatePassword() {
            const pwd = newPassword.value;
            const conf = confirmPassword.value;

            // Live validation rules
            const hasLength = pwd.length >= 10;
            const hasUpper = /[A-Z]/.test(pwd);
            const hasLower = /[a-z]/.test(pwd);
            const hasNumber = /[0-9]/.test(pwd);
            const hasSpecial = /[^A-Za-z0-9]/.test(pwd);

            toggleRule(ruleLength, hasLength);
            toggleRule(ruleUpper, hasUpper);
            toggleRule(ruleLower, hasLower);
            toggleRule(ruleNumber, hasNumber);
            toggleRule(ruleSpecial, hasSpecial);

            // Strength Calculation
            let score = 0;
            if (hasLength) score++;
            if (hasUpper) score++;
            if (hasLower) score++;
            if (hasNumber) score++;
            if (hasSpecial) score++;

            let strength = 'Weak';
            let color = '#ef4444';
            let width = '20%';

            if (score >= 5) {
                strength = 'Strong (Excellent)';
                color = '#10b981';
                width = '100%';
            } else if (score >= 4) {
                strength = 'Good';
                color = '#3b82f6';
                width = '80%';
            } else if (score >= 3) {
                strength = 'Fair';
                color = '#f59e0b';
                width = '60%';
            } else if (score >= 2) {
                strength = 'Weak';
                color = '#ef4444';
                width = '40%';
            }

            strengthBar.style.width = width;
            strengthBar.style.backgroundColor = color;
            strengthText.textContent = 'Strength: ' + strength;
            strengthText.style.color = color;

            // Enable submit button only if all rules are satisfied AND passwords match
            const valid = hasLength && hasUpper && hasLower && hasNumber && hasSpecial && pwd === conf && pwd.length > 0;
            submitBtn.disabled = !valid;
        }

        function toggleRule(element, isValid) {
            if (isValid) {
                element.className = 'policy-item valid';
                element.querySelector('i').className = 'bi bi-check-circle-fill';
            } else {
                element.className = 'policy-item invalid';
                element.querySelector('i').className = 'bi bi-x-circle-fill';
            }
        }
    </script>
</body>
</html>

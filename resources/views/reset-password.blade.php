<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NAAP Document Routing - Reset Password</title>
    
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
            padding: 2rem 1rem;
        }
        .wrapper { width:min(460px, 94vw); padding: 1.8rem; background: var(--panel); border:1px solid var(--panel-border); border-radius:16px; box-shadow:0 20px 50px var(--panel-shadow); }
        h1 { margin:0 0 0.6rem; font-size:1.8rem; text-align:center; color: var(--heading); letter-spacing: -0.5px; }
        p.subtitle { margin:0 0 1.2rem; text-align:center; color: var(--subtitle); font-size: 0.92rem; line-height: 1.4; }
        .field { margin-bottom:1rem; }
        .field label { display:block; margin-bottom:0.4rem; color: var(--label); font-weight:500; font-size: 0.9rem; }
        .field input { width:100%; padding:0.82rem; border:1px solid rgba(139,171,255,.27); border-radius:10px; background: var(--input-bg); color: var(--input-text); transition: 0.3s; font-size: 0.95rem; }
        .field input::placeholder { color: var(--placeholder); }
        .field input:focus { outline:none; border-color:#1e3a8a; box-shadow:0 0 12px rgba(30,58,138,.2); }
        
        /* Strength meter */
        .strength-meter {
            height: 6px;
            background-color: rgba(148, 163, 184, 0.2);
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
            color: var(--subtitle);
            text-align: right;
            font-weight: 600;
        }

        /* Policy list */
        .policy-list {
            margin: 0.8rem 0;
            padding-left: 0;
            list-style: none;
            font-size: 0.82rem;
            color: var(--subtitle);
        }
        .policy-item {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 4px;
        }
        .policy-item.valid {
            color: #059669;
        }
        .policy-item.valid i {
            color: #059669;
        }
        .policy-item.invalid i {
            color: #dc2626;
        }

        .btn { width:100%; padding:0.85rem; border:none; border-radius:10px; background:linear-gradient(90deg, var(--neon) 0%, var(--neon2) 100%); color: var(--btn-text) !important; font-weight:700; cursor:pointer; transition:.25s; margin-top: 0.5rem; }
        .btn:hover { transform:translateY(-2px); box-shadow: 0 8px 25px rgba(30,58,138,.3); }
        .btn:disabled { opacity: 0.6; cursor: not-allowed; transform: none; }
        .helper { margin-top:1rem; text-align:center; font-size:0.85rem; color: var(--helper); }
        .helper a { color: var(--helper); text-decoration: none; font-weight: 500; }
        .helper a:hover { color: var(--helper-hover); }
        .errors { margin:0 0 1rem; color: var(--error-text); text-align:center; background: var(--error-bg); padding: 0.6rem; border-radius: 8px; font-size: 0.85rem; border: 1px solid rgba(255, 139, 167, 0.2); }
        .success-box { margin:0 0 1rem; color: #10b981; text-align:center; background: rgba(16, 185, 129, 0.12); padding: 0.65rem 0.85rem; border-radius: 8px; font-size: 0.85rem; border: 1px solid rgba(16, 185, 129, 0.25); line-height: 1.4; }
        html[data-theme='light'] .success-box { color: #065f46; background: #ecfdf5; border-color: #a7f3d0; }
        .small { margin-top:1.2rem; font-size:0.75rem; color: var(--small); text-align: center; border-top: 1px solid rgba(255,255,255,0.05); padding-top: 1rem; }
        .info-box { background: var(--info-bg); border: 1px solid var(--info-border); border-radius: 8px; padding: 0.8rem; margin-bottom: 1.2rem; font-size: 0.8rem; color: var(--info-text); line-height: 1.4; }
    </style>
</head>
<body>
    <div class="wrapper">
        <h1>Set New Password</h1>
        <p class="subtitle">Create a new secure password for your NAAP account.</p>

        {{-- Success/Error Alerts --}}
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

        <form method="POST" action="{{ route('password.update') }}" id="resetForm">
            @csrf
            <input type="hidden" name="token" value="{{ $token }}">

            <div class="field">
                <label for="email">Account Email</label>
                <input id="email" name="email" type="email" placeholder="user@naap.org" value="{{ old('email', $email ?? '') }}" required>
            </div>

            <div class="field">
                <label for="password">New Password</label>
                <input id="password" name="password" type="password" placeholder="••••••••••••" required autofocus>
                
                <div class="strength-meter">
                    <div class="strength-bar" id="strengthBar"></div>
                </div>
                <div class="strength-text" id="strengthText">Enter password</div>

                <ul class="policy-list">
                    <li class="policy-item" id="rule-length"><i class="bi bi-x-circle-fill"></i> At least 12 characters</li>
                    <li class="policy-item" id="rule-upper"><i class="bi bi-x-circle-fill"></i> At least one uppercase letter (A-Z)</li>
                    <li class="policy-item" id="rule-lower"><i class="bi bi-x-circle-fill"></i> At least one lowercase letter (a-z)</li>
                    <li class="policy-item" id="rule-num"><i class="bi bi-x-circle-fill"></i> At least one number (0-9)</li>
                    <li class="policy-item" id="rule-special"><i class="bi bi-x-circle-fill"></i> At least one special character (!@#$%^&*...)</li>
                </ul>
            </div>

            <div class="field">
                <label for="password_confirmation">Confirm New Password</label>
                <input id="password_confirmation" name="password_confirmation" type="password" placeholder="••••••••••••" required>
                <div id="matchText" style="font-size: 0.8rem; margin-top: 0.3rem; display: none;"></div>
            </div>

            <button type="submit" class="btn" id="submitBtn">Update Password</button>
        </form>

        <div class="helper"><a href="{{ route('login') }}">← Back to Sign In</a></div>
        <div class="small">© 2024 NAAP. All rights reserved.</div>
    </div>

    <script>
        const passwordInput = document.getElementById('password');
        const confirmInput = document.getElementById('password_confirmation');
        const strengthBar = document.getElementById('strengthBar');
        const strengthText = document.getElementById('strengthText');
        const matchText = document.getElementById('matchText');
        const submitBtn = document.getElementById('submitBtn');
        const resetForm = document.getElementById('resetForm');

        const rules = {
            length: document.getElementById('rule-length'),
            upper: document.getElementById('rule-upper'),
            lower: document.getElementById('rule-lower'),
            num: document.getElementById('rule-num'),
            special: document.getElementById('rule-special')
        };

        function updateRule(element, isValid) {
            const icon = element.querySelector('i');
            if (isValid) {
                element.className = 'policy-item valid';
                icon.className = 'bi bi-check-circle-fill';
            } else {
                element.className = 'policy-item invalid';
                icon.className = 'bi bi-x-circle-fill';
            }
        }

        function checkStrength(pass) {
            let score = 0;
            const hasLength = pass.length >= 12;
            const hasUpper = /[A-Z]/.test(pass);
            const hasLower = /[a-z]/.test(pass);
            const hasNum = /[0-9]/.test(pass);
            const hasSpecial = /[^A-Za-z0-9]/.test(pass);

            updateRule(rules.length, hasLength);
            updateRule(rules.upper, hasUpper);
            updateRule(rules.lower, hasLower);
            updateRule(rules.num, hasNum);
            updateRule(rules.special, hasSpecial);

            if (hasLength) score++;
            if (hasUpper) score++;
            if (hasLower) score++;
            if (hasNum) score++;
            if (hasSpecial) score++;

            if (pass.length === 0) {
                strengthBar.style.width = '0%';
                strengthText.innerText = 'Enter password';
                strengthText.style.color = 'var(--subtitle)';
                return;
            }

            switch(score) {
                case 1:
                case 2:
                    strengthBar.style.width = '30%';
                    strengthBar.style.backgroundColor = '#ef4444';
                    strengthText.innerText = 'Weak';
                    strengthText.style.color = '#ef4444';
                    break;
                case 3:
                case 4:
                    strengthBar.style.width = '70%';
                    strengthBar.style.backgroundColor = '#f59e0b';
                    strengthText.innerText = 'Moderate';
                    strengthText.style.color = '#f59e0b';
                    break;
                case 5:
                    strengthBar.style.width = '100%';
                    strengthBar.style.backgroundColor = '#10b981';
                    strengthText.innerText = 'Strong';
                    strengthText.style.color = '#10b981';
                    break;
            }
        }

        function checkMatch() {
            if (confirmInput.value.length === 0) {
                matchText.style.display = 'none';
                return;
            }
            matchText.style.display = 'block';
            if (passwordInput.value === confirmInput.value) {
                matchText.innerText = '✓ Passwords match';
                matchText.style.color = '#059669';
            } else {
                matchText.innerText = '✗ Passwords do not match';
                matchText.style.color = '#dc2626';
            }
        }

        passwordInput.addEventListener('input', () => {
            checkStrength(passwordInput.value);
            checkMatch();
        });

        confirmInput.addEventListener('input', checkMatch);

        resetForm.addEventListener('submit', function() {
            submitBtn.disabled = true;
            submitBtn.innerText = 'Updating Password...';
        });
    </script>
</body>
</html>

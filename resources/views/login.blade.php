<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NAAP Document Routing - Login</title>
    
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
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
        .wrapper { width:min(440px, 92vw); padding: 1.8rem; background: var(--panel); border:1px solid var(--panel-border); border-radius:16px; box-shadow:0 20px 50px var(--panel-shadow); }
        h1 { margin:0 0 0.8rem; font-size:1.8rem; text-align:center; color: var(--heading); letter-spacing: -0.5px; }
        p.subtitle { margin:0 0 1.5rem; text-align:center; color: var(--subtitle); font-size: 0.95rem; line-height: 1.4; }
        .field { margin-bottom:1rem; }
        .field label { display:block; margin-bottom:0.4rem; color: var(--label); font-weight:500; font-size: 0.9rem; }
        .field input { width:100%; padding:0.85rem; border:1px solid rgba(139,171,255,.27); border-radius:10px; background: var(--input-bg); color: var(--input-text); transition: 0.3s; }
        .field input::placeholder { color: var(--placeholder); }
        .field input:focus { outline:none; border-color:#1e3a8a; box-shadow:0 0 12px rgba(30,58,138,.2); }
        .btn { width:100%; padding:0.85rem; border:none; border-radius:10px; background:linear-gradient(90deg, var(--neon) 0%, var(--neon2) 100%); color: var(--btn-text) !important; font-weight:700; cursor:pointer; transition:.25s; margin-top: 0.5rem; }
        .btn:hover { transform:translateY(-2px); box-shadow: 0 8px 25px rgba(30,58,138,.3); }
        .btn:disabled { opacity: 0.6; cursor: not-allowed; transform: none; }
        .helper { margin-top:1rem; text-align:right; font-size:0.85rem; color: var(--helper); }
        .helper a { color: var(--helper); text-decoration: none; }
        .helper a:hover { color: var(--helper-hover); }
        .errors { margin:0 0 1rem; color: var(--error-text); text-align:center; background: var(--error-bg); padding: 0.6rem; border-radius: 8px; font-size: 0.85rem; border: 1px solid rgba(255, 139, 167, 0.2); }
        .small { margin-top:1.2rem; font-size:0.75rem; color: var(--small); text-align: center; border-top: 1px solid rgba(255,255,255,0.05); padding-top: 1rem; }
        .info-box { background: var(--info-bg); border: 1px solid var(--info-border); border-radius: 8px; padding: 0.8rem; margin-bottom: 1.5rem; font-size: 0.8rem; color: var(--info-text); line-height: 1.4; }
    </style>
</head>
<body>
    <div class="wrapper">
        <h1>NAAP Document Routing</h1>
        <p class="subtitle">Secure document routing system for your organization</p>

        <div class="info-box">
            <strong style="color: var(--blue);"><i class="bi bi-shield-lock-fill me-1"></i> Password Requirements:</strong><br>
            • Minimum 12 characters • Uppercase, lowercase, numbers & special chars<br>
            • 2FA enabled for maximum security
        </div>

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

        <form method="POST" action="{{ route('login.submit') }}" id="loginForm">
            @csrf
            <div class="field">
                <label for="username">Username or Email</label>
                <input id="username" name="username" type="text" placeholder="admin or admin@naap.org" value="{{ old('username') }}" required autofocus>
            </div>
            <div class="field">
                <label for="password">Password</label>
                <input id="password" name="password" type="password" placeholder="••••••••••" required>
            </div>
            
            @if(env('RECAPTCHA_ENABLED', false) && isset($showRecaptcha) && $showRecaptcha)
                <script src="https://www.google.com/recaptcha/api.js" async defer></script>
                <div class="field" style="display: flex; justify-content: center; margin-bottom: 1rem;">
                    <div class="g-recaptcha" data-sitekey="{{ env('RECAPTCHA_SITE_KEY', '6LeIxAcTAAAAAJcZVRqyHh71UMIEGNQ_MXjiZKhI') }}"></div>
                </div>
            @endif

            <button type="submit" class="btn" id="submitBtn">Sign In</button>
        </form>

        <div class="helper"><a href="#">Forgot Password?</a></div>
        <div class="small">© 2024 NAAP. All rights reserved.</div>
    </div>


    <script>
        // Prevent multiple clicks which can cause CSRF token mismatch
        const loginForm = document.getElementById('loginForm');
        const submitBtn = document.getElementById('submitBtn');

        loginForm.addEventListener('submit', function() {
            submitBtn.disabled = true;
            submitBtn.innerText = 'Authenticating...';
        });
    </script>
</body>
</html>
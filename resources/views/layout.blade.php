<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>NAAP Admin - @yield('title')</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    @yield('head')

    <style>
        :root {
            --bg: #0b1228;
            --sidebar-bg: #161e31;
            --sidebar-width: 280px;
            --accent-cyan: #22d3ee;
            --accent-purple: #a855f7;
            --text-dim: #94a3b8;
            --panel: rgba(30, 41, 59, 0.45);
            --panel-border: rgba(255, 255, 255, 0.08);
        }

        body { 
            background-color: var(--bg); 
            color: #f8fafc; 
            font-family: 'Inter', sans-serif;
            margin: 0;
            overflow-x: hidden;
            display: flex;
            min-height: 100vh;
        }

        /* --- SIDEBAR --- */
        .sidebar { 
            width: var(--sidebar-width); 
            background: var(--sidebar-bg); 
            border-right: 1px solid var(--panel-border);
            display: flex;
            flex-direction: column;
            padding: 10px 12px;
            position: fixed; 
            height: 100vh; 
            z-index: 2000;
            transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        /* --- OVERLAY --- */
        #sidebarOverlay {
            position: fixed; inset: 0;
            background: rgba(0, 0, 0, 0.6);
            backdrop-filter: blur(4px);
            z-index: 1500; 
            display: none;
        }

        /* --- BRANDING --- */
        .brand-section {
            display: flex; align-items: center; justify-content: space-between;
            margin-bottom: 12px; padding-left: 10px;
        }

        .logo-box {
            width: 40px; height: 40px;
            background: linear-gradient(135deg, var(--accent-cyan), var(--accent-purple));
            border-radius: 10px;
            display: grid; place-items: center;
            font-weight: 800; color: white;
            box-shadow: 0 4px 12px rgba(34, 211, 238, 0.3);
        }

        /* --- NAVIGATION --- */
        nav { flex: 1; overflow-y: auto; }

        .nav-link { 
            color: var(--text-dim); 
            text-decoration: none; 
            display: flex; align-items: center; gap: 12px;
            padding: 12px 16px; border-radius: 12px; margin-bottom: 4px;
            transition: 0.2s; font-size: 0.95rem;
        }

        .nav-link:hover { background: rgba(255,255,255,0.05); color: white; }
        .nav-link.active { 
            background: rgba(34, 211, 238, 0.1); 
            color: var(--accent-cyan); 
            font-weight: 600;
        }

        /* --- PROFILE CARD --- */
        .user-profile-card {
            background: var(--panel);
            border-radius: 16px;
            padding: 12px;
            margin-bottom: 10px;
            display: flex; align-items: center; gap: 10px;
            border: 1px solid var(--panel-border);
        }

        .avatar {
            width: 35px; height: 35px;
            background: linear-gradient(to bottom right, var(--accent-purple), #6366f1);
            border-radius: 8px;
            display: grid; place-items: center;
            font-weight: bold; font-size: 0.8rem; color: white;
        }

        /* --- MAIN CONTAINER --- */
        .main-container { 
            flex: 1; 
            margin-left: var(--sidebar-width); 
            min-height: 100vh; 
            transition: margin-left 0.3s ease; 
        }

        /* --- TOGGLE BUTTONS --- */
        .hamburger-btn {
            background: var(--panel);
            border: 1px solid var(--panel-border);
            color: var(--accent-cyan);
            width: 42px; height: 42px;
            border-radius: 10px;
            display: flex; align-items: center; justify-content: center;
            cursor: pointer;
        }

        .notif-btn {
            background: var(--panel);
            border: 1px solid var(--panel-border);
            color: var(--accent-cyan);
            width: 42px; height: 42px;
            border-radius: 10px;
            display: flex; align-items: center; justify-content: center;
            position: relative;
        }

        .notif-badge {
            position: absolute; top: -5px; right: -5px;
            background: #fb7185; color: white;
            font-size: 0.65rem; padding: 2px 6px; border-radius: 20px;
        }

        /* --- RESPONSIVE LOGIC --- */
        @media (max-width: 992px) {
            .sidebar { transform: translateX(-100%); }
            .main-container { margin-left: 0; }
            body.sidebar-open .sidebar { transform: translateX(0); }
            body.sidebar-open #sidebarOverlay { display: block; }
        }

        .logout-link {
            color: #fb7185; text-decoration: none; font-size: 0.85rem;
            padding: 8px 16px; display: flex; align-items: center; gap: 8px;
            font-weight: 600;
        }
    </style>
</head>
<body>

    <div id="sidebarOverlay"></div>

    <aside class="sidebar" id="sidebar">
        <div class="brand-section">
            <div class="logo-group d-flex align-items-center gap-2">
                <div class="logo-box">NA</div>
                <div>
                    <h5 class="m-0 fw-bold text-white">NAAP</h5>
                    <small class="text-info" style="font-size: 0.6rem; letter-spacing: 1px; font-weight: 700;">DOC ROUTING</small>
                </div>
            </div>
            <button class="btn d-lg-none text-white p-0" id="closeSidebar">
                <i class="bi bi-x-lg fs-4"></i>
            </button>
        </div>

        @php
            $currentUser = \App\Models\User::find(session('user_id'));
            $roleLabel = match(session('user_role')) {
                'ADMIN' => 'Admin',
                'Super Admin' => 'Super Admin',
                'Sender' => 'Sender',
                'Receiver' => 'Receiver',
                default => session('user_role') ?? 'User'
            };
        @endphp

        <!-- Sidebar Profile Card -->
        <div class="px-2 py-2 border-bottom border-slate-800 mb-1">
            <div class="d-flex align-items-center gap-3 mb-0 p-2 rounded" style="background: rgba(30, 41, 59, 0.4); border: 1px solid rgba(255,255,255,0.05);">
                <div class="position-relative" style="flex-shrink: 0;">
                    @if($currentUser && $currentUser->avatar)
                        <img src="{{ asset('storage/' . $currentUser->avatar) }}" alt="Avatar" class="rounded-circle" style="width: 42px; height: 42px; object-fit: cover; border: 2px solid var(--accent-cyan);">
                    @else
                        <div class="rounded-circle d-flex align-items-center justify-content-center fw-bold text-white" style="width: 42px; height: 42px; background: var(--accent-navy); border: 2px solid var(--accent-cyan); font-size: 16px;">
                            {{ substr(session('user_name', 'A'), 0, 1) }}
                        </div>
                    @endif
                    <!-- Online Status Indicator -->
                    <span class="position-absolute bottom-0 end-0 bg-success border border-white rounded-circle" style="width: 11px; height: 11px; border-width: 2px !important;"></span>
                </div>
                <div class="overflow-hidden" style="flex: 1;">
                    <div class="fw-bold text-white text-truncate small" style="line-height: 1.2;">{{ session('user_name') }}</div>
                    <small class="text-slate-400 text-truncate d-block" style="font-size: 10.5px;">{{ $roleLabel }}</small>
                </div>
            </div>
        </div>

        <nav>
            <a href="{{ route('dashboard') }}" class="nav-link {{ request()->routeIs('dashboard') ? 'active' : '' }}">
                <i class="bi bi-grid-fill"></i> Dashboard
            </a>
            <a href="{{ route('documents.index') }}" class="nav-link {{ request()->routeIs('documents.*') ? 'active' : '' }}">
                <i class="bi bi-file-earmark-text-fill"></i> Documents
            </a>
            <a href="{{ route('qr.index') }}" class="nav-link {{ request()->routeIs('qr.*') ? 'active' : '' }}">
                <i class="bi bi-qr-code-scan"></i> QR Scanner
            </a>
            <a href="{{ route('track.index') }}" class="nav-link {{ request()->routeIs('track.*') ? 'active' : '' }}">
                <i class="bi bi-geo-alt-fill"></i> Tracking
            </a>

            @if(session('user_role') === 'ADMIN' || session('user_role') === 'Administrator' || session('user_role') === 'Super Administrator')
                <div class="text-uppercase text-slate-500 fw-bold mt-2 mb-1 px-3" style="font-size: 10px; letter-spacing: 0.8px; opacity: 0.6;">Management</div>
                <a href="{{ route('offices.index') }}" class="nav-link {{ request()->routeIs('offices.*') ? 'active' : '' }}">
                    <i class="bi bi-building"></i> Offices
                </a>
                <a href="{{ route('users.index') }}" class="nav-link {{ request()->routeIs('users.*') ? 'active' : '' }}">
                    <i class="bi bi-people-fill"></i> User Accounts
                </a>
                <a href="{{ route('reports.index') }}" class="nav-link {{ request()->routeIs('reports.*') ? 'active' : '' }}">
                    <i class="bi bi-graph-up-arrow"></i> Reports
                </a>
                <a href="{{ route('security.dashboard') }}" class="nav-link {{ request()->routeIs('security.dashboard') ? 'active' : '' }}">
                    <i class="bi bi-shield-fill-check"></i> Security Console
                </a>
                <a href="{{ route('activity.index') }}" class="nav-link {{ request()->routeIs('activity.*') ? 'active' : '' }}">
                    <i class="bi bi-clock-history"></i> Audit Logs
                </a>
                <a href="{{ route('settings.index') }}" class="nav-link {{ request()->routeIs('settings.*') ? 'active' : '' }}">
                    <i class="bi bi-gear-fill"></i> System Settings
                </a>
            @endif

            <div class="text-uppercase text-slate-500 fw-bold mt-2 mb-1 px-3" style="font-size: 10px; letter-spacing: 0.8px; opacity: 0.6;">Profile</div>
            <a href="{{ route('profile') }}" class="nav-link {{ request()->routeIs('profile') ? 'active' : '' }}">
                <i class="bi bi-person-circle"></i> My Profile
            </a>
            <a href="{{ route('logout') }}" class="nav-link logout-link" style="color: #fb7185 !important;">
                <i class="bi bi-box-arrow-left"></i> Sign Out
            </a>
        </nav>
    </aside>

    <div class="main-container">
        <header class="container-fluid px-4 py-3 d-flex justify-content-between align-items-center border-bottom border-secondary border-opacity-10">
            <div class="d-flex align-items-center gap-3">
                <button class="hamburger-btn d-lg-none" id="hamburgerMenu">
                    <i class="bi bi-list fs-4"></i>
                </button>
                <h4 class="mb-0 fw-bold text-white">@yield('title')</h4>
            </div>

            <div class="d-flex align-items-center gap-3">
                <div class="notif-btn">
                    <i class="bi bi-bell"></i>
                    <span class="notif-badge">3</span>
                </div>
            </div>
        </header>

        <main class="container-fluid px-4 py-4">
            @if(session('success'))
                <div class="alert alert-success border-0 shadow-sm mb-4" style="background: rgba(34, 211, 238, 0.1); color: var(--accent-cyan); border-radius: 12px;">
                    <i class="bi bi-check-circle-fill me-2"></i> {{ session('success') }}
                </div>
            @endif

            @yield('content')
        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    @yield('scripts')
    
    <script>
        const body = document.body;
        const hamburgerMenu = document.getElementById('hamburgerMenu');
        const closeSidebar = document.getElementById('closeSidebar');
        const overlay = document.getElementById('sidebarOverlay');

        // Toggle Sidebar
        hamburgerMenu.onclick = () => body.classList.add('sidebar-open');
        closeSidebar.onclick = () => body.classList.remove('sidebar-open');
        overlay.onclick = () => body.classList.remove('sidebar-open');

        // Handle window resize
        window.onresize = () => {
            if (window.innerWidth > 992) {
                body.classList.remove('sidebar-open');
            }
        };
    </script>
</body>
</html>
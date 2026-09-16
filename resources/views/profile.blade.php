@extends('layouts.app')

@section('title', 'User Profile')

@section('content')
<style>
    .profile-header {
        background: var(--panel);
        border: 1px solid var(--panel-border);
        border-radius: 12px;
        padding: 30px;
        margin-bottom: 25px;
        display: flex;
        align-items: center;
        gap: 20px;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
    }

    .profile-avatar-lg {
        width: 80px; height: 80px;
        background: linear-gradient(135deg, var(--accent-cyan), var(--accent-purple));
        border-radius: 12px;
        display: grid; place-items: center;
        font-size: 2rem; font-weight: 800; color: white;
        box-shadow: 0 4px 10px rgba(0,0,0,0.1);
    }

    .info-card {
        background: var(--panel);
        border: 1px solid var(--panel-border);
        border-radius: 12px;
        padding: 24px;
        height: 100%;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -1px rgba(0, 0, 0, 0.02);
    }

    .alert-custom-close {
        border: none;
        background: rgba(0, 0, 0, 0.05);
        color: var(--text-main);
        width: 34px;
        height: 34px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 50%;
        position: absolute;
        top: 12px;
        right: 12px;
        opacity: 0.9;
    }

    .alert-custom-close:hover {
        opacity: 1;
        background: rgba(0, 0, 0, 0.1);
    }

    .alert-success.alert-dismissible,
    .alert-info.alert-dismissible {
        position: relative;
        padding-right: 5rem;
    }

    .info-label {
        color: var(--text-dim);
        font-size: 0.75rem;
        text-transform: uppercase;
        letter-spacing: 1px;
        margin-bottom: 8px;
        font-weight: 700;
    }

    #sig-canvas {
        border: 2px dashed var(--panel-border);
        border-radius: 8px;
        cursor: crosshair;
        background: var(--panel);
        width: 100%;
        height: 180px;
        touch-action: none;
    }

    @media (max-width: 768px) {
        .profile-header { flex-direction: column; text-align: center; }
    }
</style>

<div class="container-fluid p-0">
    @if(session('success'))
        <div class="alert alert-success border-0 bg-success text-white rounded-3 mb-4">
            {{ session('success') }}
        </div>
    @endif

    <div class="profile-header">
        <div class="profile-avatar-lg" style="display: flex; align-items: center; justify-content: center; overflow: hidden; background: var(--bg);">
            @if($user->avatar)
                <img src="{{ asset('storage/' . $user->avatar) }}" alt="Avatar" style="width: 100%; height: 100%; object-fit: cover;">
            @else
                {{ substr($user->email, 0, 1) }}
            @endif
        </div>
        <div class="flex-grow-1 text-start">
            <h2 class="fw-bold mb-1">{{ $user->name }}</h2>
            <p class="mb-0 small" style="color: var(--accent-cyan) !important;"><i class="bi bi-shield-check me-1"></i> {{ strtoupper($user->role) }}</p>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-12 col-xl-4 text-start">
            <div class="info-card">
                <h6 class="fw-bold mb-4"><i class="bi bi-person-gear me-2"></i>Edit Information</h6>
                <form action="{{ route('profile.update') }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    <div class="mb-3">
                        <label class="info-label">Profile Picture</label>
                        <input type="file" name="avatar" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label class="info-label">Full Name</label>
                        <input type="text" name="name" class="form-control" value="{{ $user->name }}" required>
                    </div>
                    <div class="mb-3">
                        <label class="info-label">Email Address</label>
                        <input type="email" name="email" class="form-control" value="{{ $user->email }}" required>
                    </div>
                    @php
                        $role = session('user_role');
                        $isAdmin = in_array($role, ['ADMIN', 'Administrator', 'Super Administrator']);
                    @endphp
                    <div class="mb-3">
                        <label class="info-label">Employee ID</label>
                        <input type="text" name="employee_id" class="form-control" value="{{ $user->employee_id }}" placeholder="e.g. EMP-1234" @disabled(!$isAdmin)>
                    </div>
                    <div class="mb-3">
                        <label class="info-label">Position</label>
                        <input type="text" name="position" class="form-control" value="{{ $user->position }}" placeholder="e.g. Registrar Officer" @disabled(!$isAdmin)>
                    </div>
                    <div class="mb-3">
                        <label class="info-label">Contact Number</label>
                        <input type="text" name="phone" class="form-control" value="{{ $user->phone }}" placeholder="e.g. 09123456789">
                    </div>
                    <div class="mb-3">
                        <label class="info-label">Department</label>
                        <select name="department_id" class="form-select" @disabled(!$isAdmin)>
                            <option value="">-- Select Department --</option>
                            @foreach($departments as $dept)
                                <option value="{{ $dept->id }}" {{ $user->department_id == $dept->id ? 'selected' : '' }}>{{ $dept->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="info-label">Office</label>
                        <select name="office_id" class="form-select" @disabled(!$isAdmin)>
                            <option value="">-- Select Office --</option>
                            @foreach(\App\Models\Office::orderBy('name', 'asc')->get() as $off)
                                <option value="{{ $off->id }}" {{ $user->office_id == $off->id ? 'selected' : '' }}>{{ $off->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary w-100 fw-bold" style="height: 44px !important;">Save Changes</button>
                </form>
            </div>
        </div>

        <div class="col-12 col-xl-4 text-start">
            <div class="info-card">
                <h6 class="fw-bold mb-4"><i class="bi bi-pen me-2"></i>Digital Signature</h6>
                
                @if($user->signature)
                <div class="alert alert-info alert-dismissible fade show mb-3" role="alert" style="background: rgba(59, 130, 246, 0.1); color: var(--text-main); border: 1px solid var(--panel-border);">
                    <i class="bi bi-check-circle me-2" style="color: var(--accent-cyan);"></i>
                    <strong style="color: var(--text-main);">Signature Saved!</strong> <span style="color: var(--text-main);">You can edit or upload a new one below.</span>
                    <button type="button" class="btn alert-custom-close" data-bs-dismiss="alert" aria-label="Close">
                        <i class="bi bi-x-lg" style="color: var(--text-main);"></i>
                    </button>
                </div>
                <div style="border: 1px solid var(--panel-border); border-radius: 12px; padding: 16px; margin-bottom: 16px; text-align: center; background: var(--bg);">
                    <img src="{{ asset('storage/' . $user->signature) }}" alt="Your Signature" style="max-width: 100%; max-height: 120px; border-radius: 8px;">
                    <p class="small text-secondary mt-2 mb-0">Your current digital signature</p>
                </div>
                @endif
                
                <ul class="nav nav-pills nav-justified mb-3 rounded-3 p-1 border" style="background: var(--bg); border-color: var(--panel-border) !important;">
                    <li class="nav-item">
                        <button class="nav-link active small py-1" data-bs-toggle="pill" data-bs-target="#draw-sig">Draw</button>
                    </li>
                    <li class="nav-item">
                        <button class="nav-link small py-1" data-bs-toggle="pill" data-bs-target="#upload-sig">Upload</button>
                    </li>
                </ul>
                
                <div class="tab-content">
                    <div class="tab-pane fade show active" id="draw-sig">
                        <canvas id="sig-canvas"></canvas>
                        <form id="sig-form" action="{{ route('profile.signature') }}" method="POST">
                            @csrf
                            <input type="hidden" name="signature_data" id="signature_data">
                            <div class="d-flex gap-2 mt-2">
                                <button type="button" class="btn btn-sm btn-outline-secondary w-50" id="sig-clear" style="height: 38px !important;">Clear</button>
                                <button type="submit" class="btn btn-sm btn-primary w-50 text-white fw-bold" style="height: 38px !important;">Save Signature</button>
                            </div>
                        </form>
                    </div>
                    <div class="tab-pane fade" id="upload-sig">
                        <form action="{{ route('profile.signature') }}" method="POST" enctype="multipart/form-data">
                            @csrf
                            <div class="py-4 text-center border border-dashed rounded-3" style="background: var(--bg); border-color: var(--panel-border) !important;">
                                <i class="bi bi-cloud-arrow-up fs-2 text-secondary"></i>
                                <input type="file" name="sig_file" class="form-control form-control-sm mt-2 bg-transparent border-0">
                            </div>
                            <button type="submit" class="btn btn-primary btn-sm w-100 mt-3" style="height: 38px !important;">Upload File</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12 col-xl-4 text-start">
            <div class="info-card">
                <h6 class="fw-bold mb-4"><i class="bi bi-key me-2"></i>Security Settings</h6>
                <form action="{{ route('profile.password') }}" method="POST">
                    @csrf
                    <div class="mb-3">
                        <label class="info-label">New Password</label>
                        <input type="password" name="password" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="info-label">Confirm Password</label>
                        <input type="password" name="password_confirmation" class="form-control" required>
                    </div>
                    <button type="submit" class="btn btn-outline-primary w-100 fw-bold" style="height: 44px !important;">Update Password</button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
    // --- SIGNATURE DRAWING LOGIC ---
    const canvas = document.getElementById('sig-canvas');
    const ctx = canvas.getContext('2d');
    let drawing = false;

    // Adjust canvas resolution
    function resizeCanvas() {
        const ratio = Math.max(window.devicePixelRatio || 1, 1);
        canvas.width = canvas.offsetWidth * ratio;
        canvas.height = canvas.offsetHeight * ratio;
        ctx.scale(ratio, ratio);
    }
    window.addEventListener('resize', resizeCanvas);
    resizeCanvas();

    function getMousePos(e) {
        const rect = canvas.getBoundingClientRect();
        const clientX = e.touches ? e.touches[0].clientX : e.clientX;
        const clientY = e.touches ? e.touches[0].clientY : e.clientY;
        return { x: clientX - rect.left, y: clientY - rect.top };
    }

    function startDrawing(e) { drawing = true; draw(e); }
    function stopDrawing() { drawing = false; ctx.beginPath(); }

    function draw(e) {
        if (!drawing) return;
        const pos = getMousePos(e);
        ctx.lineWidth = 2;
        ctx.lineCap = 'round';
        ctx.strokeStyle = '#22d3ee';
        ctx.lineTo(pos.x, pos.y);
        ctx.stroke();
        ctx.beginPath();
        ctx.moveTo(pos.x, pos.y);
    }

    canvas.addEventListener('mousedown', startDrawing);
    canvas.addEventListener('mousemove', draw);
    window.addEventListener('mouseup', stopDrawing);

    canvas.addEventListener('touchstart', startDrawing);
    canvas.addEventListener('touchmove', draw);
    canvas.addEventListener('touchend', stopDrawing);

    document.getElementById('sig-clear').addEventListener('click', () => {
        ctx.clearRect(0, 0, canvas.width, canvas.height);
    });

    document.getElementById('sig-form').addEventListener('submit', function() {
        document.getElementById('signature_data').value = canvas.toDataURL();
        showNotification('Signature saved successfully!', 'success');
    });

    // --- SHOW SUCCESS NOTIFICATION IF SESSION MESSAGE ---
    @if(session('success'))
        document.addEventListener('DOMContentLoaded', function() {
            showNotification('{{ session('success') }}', 'success');
        });
    @endif
</script>
@endsection
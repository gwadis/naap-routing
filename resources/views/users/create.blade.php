@extends('layouts.app')

@section('title', 'Register User')

@section('content')
<style>
    .register-card {
        background: var(--panel);
        border: 1px solid var(--panel-border);
        border-radius: 20px;
        padding: 2rem;
        max-width: 720px;
        margin: 0 auto;
    }
    .form-label-custom { color: var(--text-dim); font-size: 0.9rem; font-weight: 600; }
    .form-control-custom,
    .form-select.form-control-custom { background: var(--bg) !important; border: 1px solid var(--panel-border) !important; color: var(--text-main) !important; border-radius: 12px; appearance: none !important; -webkit-appearance: none !important; -moz-appearance: none !important; }
    .form-control-custom::placeholder {
        color: var(--text-muted) !important;
    }
    .form-select.form-control-custom option {
        color: var(--text-main) !important;
        background: var(--panel) !important;
    }
    .form-select.form-control-custom option:disabled {
        color: rgba(75,85,99,0.75) !important;
    }
    .form-control-custom:focus,
    .form-select.form-control-custom:focus { border-color: var(--accent-cyan) !important; box-shadow: 0 0 18px rgba(34, 211, 238, 0.12); }
</style>

<div class="register-card">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="fw-bold mb-1">Register New User</h2>
            <p class="text-dim mb-0">Create a new account using the same dashboard layout.</p>
        </div>
        <a href="{{ route('users.index') }}" class="btn btn-outline-secondary btn-sm">Back to Users</a>
    </div>

    @if(session('success'))
        <div class="alert alert-success border-0 shadow-sm mb-4" style="background: rgba(34, 211, 238, 0.1); color: var(--accent-cyan); border-radius: 12px;">
            {{ session('success') }}
        </div>
    @endif

    @if(session('error'))
        <div class="alert alert-danger border-0 shadow-sm mb-4" style="background: rgba(251, 113, 133, 0.1); color: #fb7185; border-radius: 12px;">
            {{ session('error') }}
        </div>
    @endif

    <form action="{{ route('users.store') }}" method="POST">
        @csrf

        <div class="mb-3">
            <label class="form-label-custom">Full Name</label>
            <input type="text" name="name" value="{{ old('name') }}" class="form-control form-control-custom @error('name') is-invalid @enderror" placeholder="e.g. John Doe" required>
            @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>

        <div class="mb-3">
            <label class="form-label-custom">Email</label>
            <input type="email" name="email" value="{{ old('email') }}" class="form-control form-control-custom @error('email') is-invalid @enderror" placeholder="admin@naap.edu" required>
            @error('email') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>

        <div class="mb-3">
            <label class="form-label-custom">Employee ID</label>
            <input type="text" name="employee_id" value="{{ old('employee_id') }}" class="form-control form-control-custom @error('employee_id') is-invalid @enderror" placeholder="e.g. EMP-1234">
            @error('employee_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>

        <div class="mb-3">
            <label class="form-label-custom">Position</label>
            <input type="text" name="position" value="{{ old('position') }}" class="form-control form-control-custom @error('position') is-invalid @enderror" placeholder="e.g. Registrar Officer">
            @error('position') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>

        <div class="mb-3">
            <label class="form-label-custom">Role</label>
            <select name="role" class="form-select form-control-custom @error('role') is-invalid @enderror" required>
                <option value="" disabled {{ old('role') ? '' : 'selected' }}>Select role</option>
                <option value="ADMIN" {{ old('role') === 'ADMIN' ? 'selected' : '' }}>ADMIN</option>
                <option value="USER" {{ old('role') === 'USER' ? 'selected' : '' }}>USER</option>
            </select>
            @error('role') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>

        <div class="mb-3">
            <label class="form-label-custom">Department</label>
            <select name="department_id" class="form-select form-control-custom @error('department_id') is-invalid @enderror" required>
                <option value="" disabled {{ old('department_id') ? '' : 'selected' }}>Select department</option>
                @foreach($departments as $department)
                    <option value="{{ $department->id }}" {{ old('department_id') == $department->id ? 'selected' : '' }}>{{ $department->name }}</option>
                @endforeach
            </select>
            @error('department_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>

        <div class="mb-3">
            <label class="form-label-custom">Office</label>
            <select name="office_id" class="form-select form-control-custom @error('office_id') is-invalid @enderror">
                <option value="" selected>Select office</option>
                @foreach($offices as $office)
                    <option value="{{ $office->id }}" {{ old('office_id') == $office->id ? 'selected' : '' }}>{{ $office->name }}</option>
                @endforeach
            </select>
            @error('office_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>

        <div class="mb-3">
            <label class="form-label-custom">Status</label>
            <select name="status" class="form-select form-control-custom @error('status') is-invalid @enderror" required>
                <option value="active" {{ old('status') === 'active' ? 'selected' : '' }}>Active</option>
                <option value="inactive" {{ old('status') === 'inactive' ? 'selected' : '' }}>Inactive</option>
            </select>
            @error('status') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>

        <div class="mb-3">
            <label class="form-label-custom">Password</label>
            <input type="password" name="password" class="form-control form-control-custom @error('password') is-invalid @enderror" placeholder="••••••••••••" required>
            <small class="text-muted mt-1" style="display: block;">
                Min 12 chars • Uppercase • Lowercase • Number • Any special char (!@#$%^&* etc)
            </small>
            @error('password') <div class="invalid-feedback" style="display: block;">{{ $message }}</div> @enderror
        </div>

        <button type="submit" class="btn btn-primary w-100 py-2">Create User</button>
    </form>
</div>
@endsection

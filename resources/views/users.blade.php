@extends('layouts.app')

@section('title', 'User Management')

@section('content')
<style>
    .mgmt-card {
        background: var(--panel);
        border: 1px solid var(--panel-border);
        border-radius: 12px;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -1px rgba(0, 0, 0, 0.02);
    }

    .custom-table {
        background: var(--panel) !important;
        color: var(--text-main) !important;
    }
    .custom-table thead {
        background: var(--bg);
    }
    .custom-table th {
        font-size: 0.75rem;
        text-transform: uppercase;
        letter-spacing: 1px;
        color: var(--text-dim) !important;
        padding: 15px;
        border-bottom: 1px solid var(--panel-border);
        background: var(--bg) !important;
    }
    .custom-table td {
        padding: 15px;
        vertical-align: middle;
        border-bottom: 1px solid var(--panel-border);
        background: transparent !important;
        color: var(--text-main) !important;
    }
    .custom-table td.text-dim {
        color: var(--text-dim) !important;
    }
    .table-responsive {
        background: transparent;
        width: 100%;
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
    }
    .custom-table th, .custom-table td {
        white-space: nowrap;
    }
    #tableSearch {
        background: var(--panel) !important;
        border: 1px solid var(--panel-border) !important;
        color: var(--text-main) !important;
        border-radius: 8px;
        height: 44px;
    }
    #tableSearch::placeholder {
        color: var(--text-dim) !important;
    }

    .user-avatar {
        width: 38px; height: 38px;
        border-radius: 8px;
        background: linear-gradient(135deg, var(--accent-cyan), var(--accent-purple));
        display: flex; align-items: center; justify-content: center;
        font-weight: bold; font-size: 0.9rem; color: #FFFFFF;
    }

    .form-label { font-weight: 600; font-size: 0.85rem; color: var(--text-dim); background: transparent; }
    .form-control-custom,
    .form-select.form-control-custom {
        background: var(--panel) !important;
        border: 1px solid var(--panel-border) !important;
        color: var(--text-main) !important;
        border-radius: 8px;
        padding: 12px;
        height: 44px;
        box-shadow: none !important;
    }
    .form-control-custom::placeholder {
        color: var(--text-dim) !important;
    }
    .form-select.form-control-custom option {
        color: var(--text-main) !important;
        background: var(--panel) !important;
    }
    .form-control-custom:focus,
    .form-select.form-control-custom:focus {
        border-color: var(--accent-cyan) !important;
        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.15) !important;
    }

    .btn-action {
        width: 35px; height: 35px;
        display: inline-flex; align-items: center; justify-content: center;
        border-radius: 8px; transition: 0.2s;
        background: var(--panel);
        border: 1px solid var(--panel-border);
        color: var(--text-dim);
    }
    .btn-action:hover {
        background: var(--bg);
        color: var(--text-main);
    }
    .btn-edit:hover { color: var(--accent-cyan); border-color: var(--accent-cyan); }
    .btn-delete:hover { color: #fb7185; border-color: #fb7185; }

    /* Custom button overrides to match your theme */
    .btn-theme-cyan {
        background: var(--accent-navy) !important;
        color: #FFFFFF !important;
        border: none;
        font-weight: 600;
        height: 44px;
        border-radius: 8px !important;
    }
    .btn-theme-cyan:hover {
        background: #1D4ED8 !important;
        color: #FFFFFF !important;
        box-shadow: 0 4px 12px rgba(30, 58, 138, 0.15);
    }

    .btn-theme-purple {
        background: var(--panel) !important;
        color: var(--text-dim) !important;
        border: 1px solid var(--panel-border) !important;
        font-weight: 600;
        height: 44px;
        border-radius: 8px !important;
    }
    .btn-theme-purple:hover {
        background: var(--bg) !important;
        color: var(--text-main) !important;
    }
</style>

{{-- ALERT NOTIFICATIONS --}}
@if(session('success'))
    <div class="alert alert-success bg-success bg-opacity-10 text-success border-success border-opacity-25 mb-4" style="border-radius:12px;">
        <i class="bi bi-check-circle-fill me-2"></i> {{ session('success') }}
    </div>
@endif

@if(session('error'))
    <div class="alert alert-danger bg-danger bg-opacity-10 text-danger border-danger border-opacity-25 mb-4" style="border-radius:12px;">
        <i class="bi bi-exclamation-triangle-fill me-2"></i> {{ session('error') }}
    </div>
@endif

<div class="row g-4">
    <div class="col-xl-9 col-lg-8 col-md-7 col-12">
        <div class="mgmt-card h-100">
            <div class="p-4 d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 border-bottom border-secondary border-opacity-10">
                <div>
                    <h5 class="mb-0 fw-bold"><i class="bi bi-people me-2" style="color: var(--accent-cyan);"></i>Users</h5>
                    <small class="text-dim">Manage system access</small>
                </div>
                <div class="d-flex gap-2">
                    <input type="text" id="tableSearch" class="form-control form-control-sm search-input" placeholder="Search users..." style="border: 1px solid var(--panel-border); border-radius: 10px; max-width: 300px;">
                    <div class="badge bg-dark border border-secondary px-3 py-2 rounded-pill d-flex align-items-center">
                        Total: {{ $users->count() }}
                    </div>
                </div>
            </div>
            
            <div class="table-responsive">
                <table class="table custom-table table-borderless mb-0">
                    <thead>
                        <tr>
                            <th>User</th>
                            <th>Email Address</th>
                            <th>Employee ID</th>
                            <th>Position</th>
                            <th>Role</th>
                            <th>Department</th>
                            <th>Office</th>
                            <th>Status</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="userTableBody">
                        @foreach($users as $user)
                        <tr>
                            <td>
                                <div class="d-flex align-items-center gap-3">
                                    <div class="user-avatar">{{ substr($user->name, 0, 1) }}</div>
                                    <span class="fw-semibold">{{ $user->name }}</span>
                                </div>
                            </td>
                            <td class="text-dim">{{ $user->email }}</td>
                            <td class="text-dim">{{ $user->employee_id ?? 'N/A' }}</td>
                            <td class="text-dim">{{ $user->position ?? 'N/A' }}</td>
                            <td>{{ $user->role }}</td>
                            <td class="text-dim">{{ $user->department->name ?? 'Unassigned' }}</td>
                            <td class="text-dim">{{ $user->office->name ?? 'Unassigned' }}</td>
                            <td>
                                @if($user->status === 'active')
                                    <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 px-2.5 py-1 rounded-pill small">Active</span>
                                @else
                                    <span class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25 px-2.5 py-1 rounded-pill small">Inactive</span>
                                @endif
                            </td>
                            <td class="text-end">
                                <button class="btn-action btn-edit me-1" 
                                        onclick="prepareEdit({{ json_encode($user) }})">
                                    <i class="bi bi-pencil-square"></i>
                                </button>
                                <form action="{{ route('users.destroy', $user->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Permanently delete this user?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn-action btn-delete">
                                        <i class="bi bi-trash3"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-xl-3 col-lg-4 col-md-5 col-12">
        <div class="mgmt-card sticky-top" style="top: 20px;">
            <div class="p-4 border-bottom border-secondary border-opacity-10">
                <h5 class="mb-0 fw-bold" id="formTitle">Add New User</h5>
            </div>
            <div class="p-4">
                <form id="userForm" action="{{ route('users.store') }}" method="POST">
                    @csrf
                    <div id="methodField"></div> {{-- Placeholder for @method('PUT') --}}
                    
                    <div class="mb-3">
                        <label class="form-label">Full Name</label>
                        <input type="text" name="name" id="userNameInput" value="{{ old('name') }}" class="form-control form-control-custom @error('name') is-invalid @enderror" placeholder="e.g. John Doe" required>
                        @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" id="userEmailInput" value="{{ old('email') }}" class="form-control form-control-custom @error('email') is-invalid @enderror" placeholder="admin@naap.edu" required>
                        @error('email') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Employee ID</label>
                        <input type="text" name="employee_id" id="userEmployeeIdInput" value="{{ old('employee_id') }}" class="form-control form-control-custom @error('employee_id') is-invalid @enderror" placeholder="e.g. EMP-1234">
                        @error('employee_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Position</label>
                        <input type="text" name="position" id="userPositionInput" value="{{ old('position') }}" class="form-control form-control-custom @error('position') is-invalid @enderror" placeholder="e.g. Registrar Officer">
                        @error('position') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Role</label>
                        <select name="role" id="userRoleInput" class="form-select form-control-custom @error('role') is-invalid @enderror" required>
                            <option value="" disabled {{ old('role') ? '' : 'selected' }}>Select role</option>
                            <option value="Super Administrator" {{ old('role') === 'Super Administrator' ? 'selected' : '' }}>Super Administrator</option>
                            <option value="Administrator" {{ old('role') === 'Administrator' ? 'selected' : '' }}>Administrator</option>
                            <option value="Office Head" {{ old('role') === 'Office Head' ? 'selected' : '' }}>Office Head</option>
                            <option value="Staff" {{ old('role') === 'Staff' ? 'selected' : '' }}>Staff</option>
                            <option value="Employee" {{ old('role') === 'Employee' ? 'selected' : '' }}>Employee</option>
                            <option value="ADMIN" {{ old('role') === 'ADMIN' ? 'selected' : '' }}>ADMIN (Legacy)</option>
                            <option value="USER" {{ old('role') === 'USER' ? 'selected' : '' }}>USER (Legacy)</option>
                        </select>
                        @error('role') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Department</label>
                        <select name="department_id" id="userDepartmentInput" class="form-select form-control-custom @error('department_id') is-invalid @enderror" required>
                            <option value="" disabled {{ old('department_id') ? '' : 'selected' }}>Select department</option>
                            @foreach($departments as $department)
                                <option value="{{ $department->id }}" {{ old('department_id') == $department->id ? 'selected' : '' }}>{{ $department->name }}</option>
                            @endforeach
                        </select>
                        @error('department_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Office</label>
                        <select name="office_id" id="userOfficeInput" class="form-select form-control-custom @error('office_id') is-invalid @enderror">
                            <option value="" selected>Select office</option>
                            @foreach($offices as $office)
                                <option value="{{ $office->id }}" {{ old('office_id') == $office->id ? 'selected' : '' }}>{{ $office->name }}</option>
                            @endforeach
                        </select>
                        @error('office_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Status</label>
                        <select name="status" id="userStatusInput" class="form-select form-control-custom @error('status') is-invalid @enderror" required>
                            <option value="active" {{ old('status') === 'active' ? 'selected' : '' }}>Active</option>
                            <option value="inactive" {{ old('status') === 'inactive' ? 'selected' : '' }}>Inactive</option>
                        </select>
                        @error('status') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="mb-4">
                        <label class="form-label">Password</label>
                        <input type="password" name="password" id="userPassInput" class="form-control form-control-custom @error('password') is-invalid @enderror" placeholder="••••••••••••" required>
                        <small class="text-muted mt-1" id="passHint" style="display: block;">
                            Min 12 chars • Uppercase • Lowercase • Number • Any special char (!@#$%^&* etc)
                        </small>
                        <small class="text-muted mt-1 d-none" id="passHintEdit">Leave blank to keep current</small>
                        @error('password') <div class="invalid-feedback" style="display: block;">{{ $message }}</div> @enderror
                    </div>

                    <button type="submit" class="btn btn-theme-cyan w-100 fw-bold py-2 rounded-3" id="submitBtn">
                        Create User Account
                    </button>
                    <button type="button" class="btn btn-link w-100 text-dim mt-2 text-decoration-none d-none" id="cancelBtn" onclick="resetUI()">
                        Cancel & Reset
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
    // --- SEARCH LOGIC ---
    document.getElementById('tableSearch').oninput = (e) => {
        const term = e.target.value.toLowerCase();
        const rows = document.querySelectorAll('#userTableBody tr');
        rows.forEach(row => {
            const text = row.innerText.toLowerCase();
            row.style.display = text.includes(term) ? '' : 'none';
        });
    };

    // --- PREPARE EDIT ---
    window.prepareEdit = (user) => {
        const form = document.getElementById('userForm');
        const methodField = document.getElementById('methodField');
        
        document.getElementById('formTitle').innerText = 'Update User Details';
        document.getElementById('submitBtn').innerText = 'Save Changes';
        // Switching to the Purple theme for "Edit Mode"
        document.getElementById('submitBtn').classList.replace('btn-theme-cyan', 'btn-theme-purple');
        
        document.getElementById('cancelBtn').classList.remove('d-none');
        document.getElementById('passHint').classList.remove('d-none');
        
        form.action = `/users/${user.id}`;
        methodField.innerHTML = `@method('PUT')`;
        
        document.getElementById('userNameInput').value = user.name;
        document.getElementById('userEmailInput').value = user.email;
        document.getElementById('userEmployeeIdInput').value = user.employee_id || '';
        document.getElementById('userPositionInput').value = user.position || '';
        document.getElementById('userRoleInput').value = user.role || 'USER';
        document.getElementById('userDepartmentInput').value = user.department_id || '';
        document.getElementById('userOfficeInput').value = user.office_id || '';
        document.getElementById('userStatusInput').value = user.status || 'active';
        document.getElementById('userPassInput').required = false;
        document.getElementById('passHint').classList.add('d-none');
        document.getElementById('passHintEdit').classList.remove('d-none');
        document.getElementById('userNameInput').focus();
    };

    window.resetUI = () => {
        const form = document.getElementById('userForm');
        const methodField = document.getElementById('methodField');
        
        document.getElementById('formTitle').innerText = 'Add New User';
        document.getElementById('submitBtn').innerText = 'Create User Account';
        // Switching back to Cyan for "Add Mode"
        document.getElementById('submitBtn').classList.replace('btn-theme-purple', 'btn-theme-cyan');
        
        document.getElementById('cancelBtn').classList.add('d-none');
        document.getElementById('passHint').classList.remove('d-none');
        document.getElementById('passHintEdit').classList.add('d-none');
        
        form.action = "{{ route('users.store') }}";
        methodField.innerHTML = '';
        form.reset();
        document.getElementById('userEmployeeIdInput').value = '';
        document.getElementById('userPositionInput').value = '';
        document.getElementById('userPassInput').required = true;
        document.getElementById('userRoleInput').value = '';
        document.getElementById('userDepartmentInput').value = '';
        document.getElementById('userOfficeInput').value = '';
        document.getElementById('userStatusInput').value = 'active';
    };
</script>
@endsection
@extends('layouts.app')

@section('title','New Document Routing')

@section('content')
<div class="container-fluid">

    <h2 class="mb-4">New Document Routing</h2>

    <div class="row g-4">

        <!-- Document Information -->
        <div class="col-md-6">
            <div class="card p-4" style="background: var(--panel) !important; border: 1px solid var(--panel-border) !important;">

                <form action="{{ route('documents.store') }}" method="POST" enctype="multipart/form-data">
                    @csrf

                    <div class="mb-3">
                        <label class="form-label">Document Title *</label>
                        <input type="text" name="title" class="form-control" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea name="description" class="form-control" rows="3"></textarea>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Document Type</label>
                        <input type="text" name="type" class="form-control">
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Priority Level *</label>
                        <div class="d-flex gap-2">
                            @foreach(['Low','Medium','High'] as $p)
                                <button type="button" class="btn btn-outline-secondary priority-btn">{{ $p }}</button>
                                <input type="radio" name="priority" value="{{ $p }}" hidden {{ $p == 'Medium' ? 'checked' : '' }}>
                            @endforeach
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">SLA</label>
                        <select name="sla" class="form-control @error('sla') is-invalid @enderror" required>
                            <option value="" disabled {{ old('sla') ? '' : 'selected' }}>Select SLA</option>
                            <option value="Standard" {{ old('sla') === 'Standard' ? 'selected' : '' }}>Standard</option>
                            <option value="Expedited" {{ old('sla') === 'Expedited' ? 'selected' : '' }}>Expedited</option>
                            <option value="Critical" {{ old('sla') === 'Critical' ? 'selected' : '' }}>Critical</option>
                        </select>
                        @error('sla')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                        <small class="text-secondary">SLA determines due date, alerts, and risk status.</small>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Upload File *</label>
                        <input type="file" name="file" class="form-control" required>
                    </div>

                    <h6 class="mt-4">Routing Information</h6>
                    <div class="mb-3">
                        <label class="form-label">Origin Office *</label>
                        <select name="origin_office_id" class="form-control" required>
                            <option value="" disabled selected>Select origin office</option>
                            @foreach($offices as $office)
                                <option value="{{ $office->id ?? $office }}">{{ $office->name ?? $office }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Destination Office *</label>
                        <select name="destination_office_id" id="createOfficeSelect" class="form-control" required>
                            <option value="" disabled selected>Select destination office</option>
                            @foreach($offices as $office)
                                <option value="{{ $office->id ?? $office }}">{{ $office->name ?? $office }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Receiver User *</label>
                        <select name="receiver_user_ids[]" id="createReceiverSelect" class="form-control" required disabled>
                            <option value="">-- Select Office First --</option>
                        </select>
                        <small class="text-secondary">Select the active receiver assigned to this office.</small>
                    </div>

                    <button class="btn btn-primary w-100 mt-3">Route Document</button>
                </form>

            </div>
        </div>

        <!-- QR Scanner & Routing Flow -->
        <div class="col-md-6 d-flex flex-column gap-4">

            <div class="card p-4 text-center" style="background: var(--panel) !important; border: 1px solid var(--panel-border) !important;">
                <h6>QR Scanner</h6>
                <video id="qr-video" style="width:100%;height:250px;background:#0b1228;border-radius:10px;"></video>
                <button id="start-camera" class="btn btn-info mt-3 w-100">Start Camera</button>
            </div>

            <div class="card p-4" style="background: var(--panel) !important; border: 1px solid var(--panel-border) !important;">
                <h6>Routing Flow</h6>
                <ol class="ms-3 mt-2">
                    <li>Document Created (Origin Office)</li>
                    <li>In Transit</li>
                    <li>Received (Destination)</li>
                </ol>
            </div>

        </div>

    </div>

</div>
@endsection

@section('scripts')
<script src="https://unpkg.com/html5-qrcode"></script>
<script>
document.querySelectorAll('.priority-btn').forEach((btn,i)=>{
    btn.addEventListener('click',()=>{
        document.querySelectorAll('input[name="priority"]')[i].checked = true;
        document.querySelectorAll('.priority-btn').forEach(b=>b.classList.remove('btn-warning'));
        btn.classList.add('btn-warning');
    });
});

let cameraStarted = false;
document.getElementById('start-camera').addEventListener('click', ()=>{
    if(cameraStarted) return;
    cameraStarted = true;

    const html5QrCode = new Html5Qrcode("qr-video");

    Html5Qrcode.getCameras().then(cameras => {
        if(cameras && cameras.length) {
            html5QrCode.start(
                cameras[0].id,
                { fps: 10, qrbox: 250 },
                qrCodeMessage => { alert("QR Code scanned: " + qrCodeMessage); },
                errorMessage => {}
            );
        }
    }).catch(err => console.error(err));
});

// Dynamic receiver loading for New Document
const createOfficeSelect = document.getElementById('createOfficeSelect');
const createReceiverSelect = document.getElementById('createReceiverSelect');
if (createOfficeSelect && createReceiverSelect) {
    createOfficeSelect.addEventListener('change', async function() {
        const officeId = this.value;
        createReceiverSelect.innerHTML = '<option value="">-- Loading Staff --</option>';
        createReceiverSelect.disabled = true;
        
        if (!officeId) {
            createReceiverSelect.innerHTML = '<option value="">-- Select Office First --</option>';
            return;
        }
        
        try {
            const response = await fetch(`/api/offices/${officeId}/staff`);
            const data = await response.json();
            const staff = data.staff || [];
            
            if (staff.length === 0) {
                createReceiverSelect.innerHTML = '<option value="">No available receivers for this office.</option>';
            } else {
                createReceiverSelect.innerHTML = '<option value="">-- Select Receiver --</option>';
                staff.forEach(user => {
                    if (user.id != "{{ session('user_id') }}") {
                        const opt = document.createElement('option');
                        opt.value = user.id;
                        opt.textContent = `${user.name} (${user.role})`;
                        createReceiverSelect.appendChild(opt);
                    }
                });
                createReceiverSelect.disabled = false;
            }
        } catch(e) {
            console.error(e);
            createReceiverSelect.innerHTML = '<option value="">Error loading staff</option>';
        }
    });
}
</script>
@endsection
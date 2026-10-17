<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Smart Farm Cabai - Dashboard v2.1 (Laravel)</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <style>
        .calendar-grid {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 5px;
            text-align: center;
        }
        .calendar-day {
            padding: 8px;
            border-radius: 5px;
            background-color: #f8f9fa;
            font-size: 0.9rem;
        }
        .calendar-day.sprayed {
            background-color: #d1e7dd;
            color: #0f5132;
            font-weight: bold;
        }
        .calendar-day.today {
            background-color: #cff4fc;
            color: #055160;
            font-weight: bold;
        }
    </style>
</head>
<body class="bg-light">
    <div class="container py-4">
        <!-- Header: hijau untuk admin, biru untuk user -->
        <div class="card shadow-sm p-4 bg-white rounded-3 mb-4 @if(($role ?? 'admin') === 'admin') border-start border-4 border-success @else border-start border-4 border-primary @endif">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="mb-1 @if(($role ?? 'admin') === 'admin') text-success @else text-primary @endif">🌱 Smart Farm Cabai - Dashboard v2.1</h1>
                    <p class="text-muted mb-0">Sistem Tracking Penyemprotan Pestisida & Kontrol IoT Portabel</p>
                </div>
                <div>
                    <span class="badge @if(($role ?? 'admin') === 'admin') bg-success @else bg-primary @endif p-2 me-2">@if(($role ?? 'admin') === 'admin')🔑 @endif👤 {{ $username ?? 'admin' }}</span>
                    <a href="/logout" class="btn btn-danger btn-sm">Logout</a>
                </div>
            </div>
        </div>

        <!-- Notifikasi Pengingat Penyemprotan Per Bedengan -->
        <div class="row mb-4">
            <div class="col-md-12">
                <div class="card shadow-sm p-3 bg-white rounded-3 border-start border-4 border-warning">
                    <h5 class="mb-3"><i class="bi bi-bell-fill text-warning"></i> Pengingat Jadwal Penyemprotan (Interval 7 Hari / Bedengan)</h5>
                    <div id="bedenganReminderContainer" class="row g-2">
                        <!-- Dynamic Bedengan Status -->
                    </div>
                </div>
            </div>
        </div>

        <!-- Kontrol Perangkat IoT (tiap ON/OFF tercatat di umpan aktivitas) -->
        <div class="row mb-4">
            <div class="col-md-12">
                <div class="card shadow-sm p-4 bg-white rounded-3">
                    <h4 class="mb-3"><i class="bi bi-cpu"></i> Kontrol Perangkat IoT (Auto-Off Timer)</h4>
                    <div class="row">
                        <!-- Lampu LED -->
                        <div class="col-md-6 mb-3 mb-md-0">
                            <div class="p-3 border rounded bg-light">
                                <h5 class="mb-2">💡 Lampu LED (Penerangan)</h5>
                                <button id="btnLight" onclick="toggleDevice('light')" class="btn btn-secondary btn-lg w-100 mb-2" @if(($role ?? 'admin') !== 'admin') disabled @endif>
                                    Status: <span id="statusLight">OFF</span>
                                </button>
                                <div class="text-muted small">Auto-off: 30 Menit | Timer: <span id="timerLight" class="fw-bold text-dark">--:--</span></div>
                            </div>
                        </div>
                        <!-- Mist Maker -->
                        <div class="col-md-6">
                            <div class="p-3 border rounded bg-light">
                                <h5 class="mb-2">💨 Mist Maker (Penyemprotan)</h5>
                                <button id="btnMist" onclick="toggleDevice('mist')" class="btn btn-secondary btn-lg w-100 mb-2" @if(($role ?? 'admin') !== 'admin') disabled @endif>
                                    Status: <span id="statusMist">OFF</span>
                                </button>
                                <div class="text-muted small">Auto-off: 5 Menit | Timer: <span id="timerMist" class="fw-bold text-dark">--:--</span></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        @if(($role ?? 'admin') === 'admin')
        <!-- Umpan Aktivitas Aktuator ON/OFF (khusus Admin; dilaporkan ESP & tombol web) -->
        <div class="row mb-4">
            <div class="col-md-12">
                <div class="card shadow-sm p-4 bg-white rounded-3">
                    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                        <h4 class="mb-0"><i class="bi bi-activity"></i> Aktivitas Aktuator (ON/OFF)</h4>
                        <span class="small text-muted">Dilaporkan ESP saat aktuator berubah • refresh otomatis 10 detik</span>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-striped table-sm align-middle mb-0">
                            <thead class="table-dark">
                                <tr>
                                    <th>Waktu</th>
                                    <th>Perangkat</th>
                                    <th>Aktuators</th>
                                    <th>Status</th>
                                    <th>Sumber</th>
                                </tr>
                            </thead>
                            <tbody id="activityFeedBody"></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        @endif

        <div class="row mb-4">
            <!-- Form Catat Penyemprotan (Admin & User boleh input) -->
            <div class="col-md-6 mb-4 mb-md-0">
                <div class="card shadow-sm p-4 bg-white rounded-3 h-100">
                    <h4 class="mb-3"><i class="bi bi-journal-plus"></i> Catat Penyemprotan Pestisida</h4>
                    <form id="sprayForm" onsubmit="saveHistory(event)">
                        <input type="hidden" id="editHistoryId" value="">
                        <div class="mb-3">
                            <label class="form-label">Tanggal</label>
                            <input type="date" id="inputDate" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Waktu</label>
                            <input type="time" id="inputTime" class="form-control" value="06:30" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Bedengan Target</label>
                            <select id="inputBedengan" class="form-select" required></select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Durasi (Menit)</label>
                            <input type="number" id="inputDuration" class="form-control" value="5" min="1" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Catatan Opsional</label>
                            <textarea id="inputNotes" class="form-control" rows="2" placeholder="Kondisi tanaman atau jenis pestisida..."></textarea>
                        </div>
                        <button type="submit" id="spraySubmitBtn" class="btn btn-success w-100">💾 Simpan Catatan Penyemprotan</button>
                        <button type="button" id="sprayCancelBtn" onclick="cancelEditHistory()" class="btn btn-secondary w-100 mt-2 d-none">Batal Edit</button>
                    </form>
                </div>
            </div>

            <!-- Kalender & Jadwal Berikutnya -->
            <div class="col-md-6">
                <div class="card shadow-sm p-4 bg-white rounded-3 h-100">
                    <h4 class="mb-3" id="calendarTitle"><i class="bi bi-calendar-check"></i> Kalender</h4>
                    <div class="alert alert-info py-2 mb-3 small">
                        <strong>Status Kalender:</strong> Menampilkan penanda tanggal penyemprotan aktif.
                    </div>
                    <div class="calendar-grid mb-2 text-muted fw-bold">
                        <div>Min</div><div>Sen</div><div>Sel</div><div>Rab</div><div>Kam</div><div>Jum</div><div>Sab</div>
                    </div>
                    <div class="calendar-grid" id="calendarDays">
                        <!-- Generated via JS -->
                    </div>
                    <div class="mt-3 small text-muted">
                        <span class="badge bg-success">&nbsp;</span> Sudah Semprot &nbsp;&nbsp;
                        <span class="badge bg-info">&nbsp;</span> Hari Ini
                    </div>
                </div>
            </div>
        </div>

        <!-- Form Input Observasi Hama (Admin & User boleh input) -->
        <div class="row mb-4">
            <div class="col-md-12">
                <div class="card shadow-sm p-4 bg-white rounded-3">
                    <h4 class="mb-3"><i class="bi bi-bug"></i> Input Observasi Hama (Sticky Trap)</h4>
                    <form id="observationForm" onsubmit="saveObservation(event)" class="row g-3">
                        <input type="hidden" id="editObsId" value="">
                        <div class="col-md-3">
                            <label class="form-label">Tanggal</label>
                            <input type="date" id="obsDate" class="form-control" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Bedengan</label>
                            <select id="obsBedengan" class="form-select"></select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Tingkat Serangan</label>
                            <select id="obsSeverity" class="form-select">
                                <option>Ringan</option>
                                <option>Sedang</option>
                                <option>Berat</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Jumlah Serangga (Sticky Trap)</label>
                            <input type="number" id="obsCount" class="form-control" placeholder="Contoh: 20" min="0" required>
                        </div>
                        <div class="col-12">
                            <button type="submit" id="obsSubmitBtn" class="btn btn-primary">💾 Simpan Observasi Hama</button>
                            <button type="button" id="obsCancelBtn" onclick="cancelEditObs()" class="btn btn-secondary d-none ms-2">Batal Edit</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Riwayat Penyemprotan (edit milik sendiri, hapus khusus Admin) -->
        <div class="row mb-4">
            <div class="col-md-12">
                <div class="card shadow-sm p-4 bg-white rounded-3">
                    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                        <h4 class="mb-0"><i class="bi bi-clock-history"></i> Riwayat Penyemprotan Pestisida</h4>
                        @if(($role ?? 'admin') === 'admin')
                        <div class="d-flex gap-2 align-items-center">
                            <label class="small text-muted mb-0">Filter user (audit):</label>
                            <select id="filterHistoryUser" class="form-select form-select-sm" style="width:auto" onchange="renderHistory()">
                                <option value="">Semua user</option>
                            </select>
                        </div>
                        @endif
                    </div>
                    <div class="table-responsive">
                        <table class="table table-striped align-middle">
                            <thead class="table-dark">
                                <tr>
                                    <th>Tanggal & Waktu</th>
                                    <th>Bedengan</th>
                                    <th>Durasi</th>
                                    <th>Catatan</th>
                                    <th>Diinput oleh</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody id="historyTableBody">
                                <!-- Dynamic rows -->
                            </tbody>
                        </table>
                    </div>
                    <div class="small text-muted">User hanya bisa edit data miliknya sendiri. Hapus hanya Admin.</div>
                </div>
            </div>
        </div>

        <!-- Data Observasi Hama (edit milik sendiri, hapus khusus Admin) -->
        <div class="row mb-4">
            <div class="col-md-12">
                <div class="card shadow-sm p-4 bg-white rounded-3">
                    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                        <h4 class="mb-0"><i class="bi bi-bug"></i> Data Observasi Hama</h4>
                        @if(($role ?? 'admin') === 'admin')
                        <div class="d-flex gap-2 align-items-center">
                            <label class="small text-muted mb-0">Filter user (audit):</label>
                            <select id="filterObsUser" class="form-select form-select-sm" style="width:auto" onchange="renderObservations()">
                                <option value="">Semua user</option>
                            </select>
                        </div>
                        @endif
                    </div>
                    <div class="table-responsive">
                        <table class="table table-striped align-middle">
                            <thead class="table-dark">
                                <tr>
                                    <th>Tanggal</th>
                                    <th>Bedengan</th>
                                    <th>Tingkat</th>
                                    <th>Jumlah</th>
                                    <th>Diinput oleh</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody id="obsTableBody"></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        @if(($role ?? 'admin') === 'admin')
        <!-- Kelola Bedengan + Kelola Pengguna: hanya Admin yang melihat menu ini -->
        <div class="row mb-4">
            <div class="col-md-6 mb-4 mb-md-0">
                <div class="card shadow-sm p-4 bg-white rounded-3 h-100 border-start border-4 border-success">
                    <h4 class="mb-3">🔑 <i class="bi bi-grid"></i> Kelola Bedengan (Admin)</h4>
                    <form onsubmit="addBedengan(event)" class="d-flex gap-2 mb-3">
                        <input type="text" id="newBedenganName" class="form-control" placeholder="Nama bedengan baru" required maxlength="50">
                        <button type="submit" class="btn btn-success">Tambah</button>
                    </form>
                    <ul id="bedenganList" class="list-group"></ul>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card shadow-sm p-4 bg-white rounded-3 h-100 border-start border-4 border-success">
                    <h4 class="mb-3">🔑 <i class="bi bi-people"></i> Kelola Pengguna (Admin)</h4>
                    <div class="table-responsive mb-3">
                        <table class="table table-sm align-middle">
                            <thead class="table-light"><tr><th>Nama</th><th>Username</th><th>Role</th><th></th></tr></thead>
                            <tbody id="userTableBody"></tbody>
                        </table>
                    </div>
                    <h6 class="small fw-bold">Tambah user baru (tanpa email)</h6>
                    <form onsubmit="addUser(event)" class="row g-2 mb-3">
                        <div class="col-md-3"><input type="text" id="newUserName" class="form-control form-control-sm" placeholder="Nama" required maxlength="50"></div>
                        <div class="col-md-3"><input type="text" id="newUsername" class="form-control form-control-sm" placeholder="Username (huruf/angka)" required maxlength="50" pattern="[A-Za-z0-9_]+"></div>
                        <div class="col-md-3"><input type="text" id="newUserPass" class="form-control form-control-sm" placeholder="Password (min 6)" required minlength="6"></div>
                        <div class="col-md-3">
                            <select id="newUserRole" class="form-select form-select-sm"><option value="user">user</option><option value="admin">admin</option></select>
                        </div>
                        <div class="col-12"><button type="submit" class="btn btn-success btn-sm w-100">+ Tambah User</button></div>
                    </form>
                    <h6 class="small fw-bold">Reset password</h6>
                    <form onsubmit="resetPassword(event)" class="row g-2">
                        <div class="col-md-5"><input type="text" id="resetUsername" class="form-control form-control-sm" placeholder="Username user" required></div>
                        <div class="col-md-4"><input type="text" id="resetPass" class="form-control form-control-sm" placeholder="Password baru (min 6)" required minlength="6"></div>
                        <div class="col-md-3"><button type="submit" class="btn btn-warning btn-sm w-100">Reset</button></div>
                    </form>
                </div>
            </div>
        </div>
        @endif

        <!-- Grafik Analisis -->
        <div class="row">
            <div class="col-md-6 mb-4">
                <div class="card shadow-sm p-3 bg-white rounded-3">
                    <h5 class="card-title">📈 Tren Populasi Hama (Sticky Trap)</h5>
                    <canvas id="pestChart"></canvas>
                </div>
            </div>
            <div class="col-md-6 mb-4">
                <div class="card shadow-sm p-3 bg-white rounded-3">
                    <h5 class="card-title">📊 Riwayat Durasi Penyemprotan (Menit)</h5>
                    <canvas id="sprayChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        let historyData = [];
        let observationData = [];
        let bedenganData = [];
        const userRole = "{{ $role ?? 'admin' }}";
        const currentUsername = "{{ $username ?? 'admin' }}";
        const isAdmin = (userRole === 'admin');

        document.addEventListener('DOMContentLoaded', () => {
            const todayStr = new Date().toISOString().split('T')[0];
            document.getElementById('inputDate').value = todayStr;
            document.getElementById('obsDate').value = todayStr;
        });

        function loadData() {
            fetch('/api/bedengans')
                .then(res => res.json())
                .then(data => {
                    bedenganData = Array.isArray(data) ? data : [];
                    populateBedenganSelects();
                    renderBedenganList();
                    // history butuh daftar bedengan untuk reminder, jadi load setelahnya
                    loadHistory();
                })
                .catch(() => loadHistory());

            fetch('/api/observations')
                .then(res => res.json())
                .then(data => {
                    observationData = Array.isArray(data) ? data : [];
                    renderObservations();
                    updatePestChart();
                    populateUserFilters();
                });

            if (isAdmin) loadUsers();
            refreshDevicePanels();
        }

        let deviceCache = [];

        // Daftar perangkat tetap dimuat diam-diam untuk resolusi nama saat mencatat toggle web
        function loadDevices() {
            fetch('/api/devices')
                .then(res => res.json())
                .then(data => { deviceCache = Array.isArray(data) ? data : []; })
                .catch(() => { deviceCache = []; });
        }

        function loadActivities() {
            if (!document.getElementById('activityFeedBody')) return; // panel khusus Admin
            fetch('/api/device-activities')
                .then(res => res.json())
                .then(data => renderActivities(Array.isArray(data) ? data : []))
                .catch(() => renderActivities([]));
        }

        function renderActivities(items) {
            const tbody = document.getElementById('activityFeedBody');
            if (!tbody) return;
            if (!items.length) {
                tbody.innerHTML = '<tr><td colspan="5" class="text-muted small">Belum ada aktivitas. ESP melaporkan otomatis saat aktuator ON/OFF.</td></tr>';
                return;
            }
            const icon = a => a === 'lampu' ? '💡' : (a === 'mist' ? '💨' : '🔧');
            tbody.innerHTML = items.map(a => `
                <tr>
                    <td class="small">${a.created_at ? new Date(a.created_at).toLocaleString('id-ID') : '-'}</td>
                    <td>${a.device_name}</td>
                    <td>${icon(a.actuator)} ${a.actuator}</td>
                    <td><span class="badge ${a.status === 'on' ? 'bg-success' : 'bg-secondary'}">${a.status === 'on' ? 'ON' : 'OFF'}</span></td>
                    <td>${a.source === 'esp' ? '<span class="badge bg-info">ESP</span>' : `<span class="badge bg-warning text-dark">WEB${a.actor ? ' • ' + a.actor : ''}</span>`}</td>
                </tr>
            `).join('');
        }

        function refreshDevicePanels() { loadDevices(); loadActivities(); }

        setInterval(refreshDevicePanels, 10000);

        function loadHistory() {
            fetch('/api/history')
                .then(res => res.json())
                .then(data => {
                    historyData = Array.isArray(data) ? data : [];
                    renderHistory();
                    updateSprayChart();
                    populateUserFilters();
                });
        }

        function populateBedenganSelects() {
            const names = bedenganData.length ? bedenganData.map(b => b.name) : ['Bedengan 1', 'Bedengan 2', 'Bedengan 3'];
            const sel1 = document.getElementById('inputBedengan');
            const sel2 = document.getElementById('obsBedengan');
            const keep1 = sel1.value, keep2 = sel2.value;
            sel1.innerHTML = names.map(n => `<option value="${n}">${n}</option>`).join('');
            sel2.innerHTML = names.map(n => `<option>${n}</option>`).join('');
            if ([...sel1.options].some(o => o.value === keep1)) sel1.value = keep1;
            if ([...sel2.options].some(o => o.value === keep2)) sel2.value = keep2;
        }

        function populateUserFilters() {
            const users = [...new Set([...historyData.map(h => h.user), ...observationData.map(o => o.user)].filter(Boolean))];
            ['filterHistoryUser', 'filterObsUser'].forEach(id => {
                const sel = document.getElementById(id);
                if (!sel) return;
                const keep = sel.value;
                sel.innerHTML = '<option value="">Semua user</option>' + users.map(u => `<option value="${u}">${u}</option>`).join('');
                sel.value = keep;
            });
        }

        // Kontrol IoT: status di browser + tiap perubahan ON/OFF tercatat ke umpan aktivitas
        let devices = {
            light: { active: false, remaining: 1800, duration: 1800, actuator: 'lampu' },
            mist: { active: false, remaining: 300, duration: 300, actuator: 'mist' }
        };

        function logWebActivity(key, status) {
            const match = deviceCache.find(d => d.type === devices[key].actuator);
            fetch('/api/device-activities/web', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                body: JSON.stringify({
                    actuator: devices[key].actuator,
                    status: status,
                    device_name: match ? match.name : null
                })
            }).then(res => res.json()).then(result => {
                if (result.success) loadActivities();
            }).catch(() => {});
        }

        function toggleDevice(key) {
            if (userRole !== 'admin') {
                alert('Hanya Admin yang dapat mengontrol perangkat IoT.');
                return;
            }
            devices[key].active = !devices[key].active;
            if (devices[key].active) devices[key].remaining = devices[key].duration;
            updateDeviceUI(key);
            logWebActivity(key, devices[key].active ? 'on' : 'off');
        }

        function updateDeviceUI(key) {
            let dev = devices[key];
            let btn = document.getElementById(key === 'light' ? 'btnLight' : 'btnMist');
            let statusSpan = document.getElementById(key === 'light' ? 'statusLight' : 'statusMist');
            
            if (dev.active) {
                btn.className = key === 'light' ? 'btn btn-warning btn-lg w-100 mb-2 text-dark fw-bold' : 'btn btn-primary btn-lg w-100 mb-2 fw-bold';
                statusSpan.innerText = 'ON';
            } else {
                btn.className = 'btn btn-secondary btn-lg w-100 mb-2';
                statusSpan.innerText = 'OFF';
                document.getElementById(key === 'light' ? 'timerLight' : 'timerMist').innerText = '--:--';
            }
        }

        setInterval(() => {
            for (let key in devices) {
                if (devices[key].active && devices[key].remaining > 0) {
                    devices[key].remaining--;
                    let m = Math.floor(devices[key].remaining / 60);
                    let s = devices[key].remaining % 60;
                    document.getElementById(key === 'light' ? 'timerLight' : 'timerMist').innerText = `${String(m).padStart(2,'0')}:${String(s).padStart(2,'0')}`;
                    if (devices[key].remaining === 0) {
                        devices[key].active = false;
                        updateDeviceUI(key);
                        if (userRole === 'admin') logWebActivity(key, 'off');
                    }
                }
            }
        }, 1000);

        function canEdit(itemUser) {
            return isAdmin || itemUser === currentUsername;
        }

        function renderHistory() {
            let tbody = document.getElementById('historyTableBody');
            tbody.innerHTML = '';
            const filterSel = document.getElementById('filterHistoryUser');
            const filterUser = filterSel ? filterSel.value : '';
            let sorted = [...historyData].sort((a,b) => new Date(b.date) - new Date(a.date));
            if (filterUser) sorted = sorted.filter(h => h.user === filterUser);
            sorted.forEach(item => {
                const editable = canEdit(item.user);
                tbody.innerHTML += `
                    <tr>
                        <td>${item.date} ${item.time ?? ''}</td>
                        <td>${item.bedengan}</td>
                        <td>${item.duration} menit</td>
                        <td>${item.notes ?? ''}</td>
                        <td><span class="badge bg-secondary">${item.user ?? '-'}</span></td>
                        <td>
                            ${editable ? `<button onclick="editHistory(${item.id})" class="btn btn-warning btn-sm me-1">Edit</button>` : ''}
                            ${isAdmin ? `<button onclick="deleteHistory(${item.id})" class="btn btn-danger btn-sm">Hapus</button>` : ''}
                            ${!editable && !isAdmin ? '<span class="text-muted small">-</span>' : ''}
                        </td>
                    </tr>
                `;
            });
            renderCalendar();
            updateBedenganReminders();
        }

        function saveHistory(e) {
            e.preventDefault();
            const editId = document.getElementById('editHistoryId').value;
            const payload = {
                date: document.getElementById('inputDate').value,
                time: document.getElementById('inputTime').value,
                bedengan: document.getElementById('inputBedengan').value,
                duration: parseInt(document.getElementById('inputDuration').value),
                notes: document.getElementById('inputNotes').value
            };
            const url = editId ? `/api/history/update/${editId}` : '/api/history/add';

            fetch(url, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                body: JSON.stringify(payload)
            })
            .then(res => res.json())
            .then(result => {
                if(result.success) {
                    cancelEditHistory();
                    loadHistory();
                } else if (result.error) {
                    alert(result.error);
                }
            });
        }
        // Kompatibilitas nama lama
        function addHistory(e) { saveHistory(e); }

        function editHistory(id) {
            const item = historyData.find(h => h.id === id);
            if (!item) return;
            if (!canEdit(item.user)) { alert('Hanya pemilik data / admin yang boleh edit.'); return; }
            document.getElementById('editHistoryId').value = id;
            document.getElementById('inputDate').value = item.date;
            document.getElementById('inputTime').value = item.time ?? '06:30';
            document.getElementById('inputBedengan').value = item.bedengan;
            document.getElementById('inputDuration').value = item.duration;
            document.getElementById('inputNotes').value = item.notes ?? '';
            document.getElementById('spraySubmitBtn').innerText = '✏️ Update Catatan';
            document.getElementById('sprayCancelBtn').classList.remove('d-none');
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }

        function cancelEditHistory() {
            document.getElementById('editHistoryId').value = '';
            const f = document.getElementById('sprayForm');
            if (f) f.reset();
            document.getElementById('inputDate').value = new Date().toISOString().split('T')[0];
            document.getElementById('spraySubmitBtn').innerText = '💾 Simpan Catatan Penyemprotan';
            document.getElementById('sprayCancelBtn').classList.add('d-none');
        }

        function deleteHistory(id) {
            if (!isAdmin) { alert('Hanya Admin yang dapat menghapus.'); return; }
            if(!confirm('Hapus catatan ini? Aksi tercatat di audit log.')) return;
            fetch(`/api/history/delete/${id}`, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' }
            })
            .then(res => res.json())
            .then(result => {
                if(result.success) { loadHistory(); }
                else if (result.error) { alert(result.error); }
            });
        }

        function renderObservations() {
            const tbody = document.getElementById('obsTableBody');
            if (!tbody) return;
            tbody.innerHTML = '';
            const filterSel = document.getElementById('filterObsUser');
            const filterUser = filterSel ? filterSel.value : '';
            let sorted = [...observationData].sort((a,b) => new Date(b.date) - new Date(a.date));
            if (filterUser) sorted = sorted.filter(o => o.user === filterUser);
            sorted.forEach(item => {
                const editable = canEdit(item.user);
                tbody.innerHTML += `
                    <tr>
                        <td>${item.date}</td>
                        <td>${item.bedengan}</td>
                        <td>${item.severity}</td>
                        <td>${item.count}</td>
                        <td><span class="badge bg-secondary">${item.user ?? '-'}</span></td>
                        <td>
                            ${editable ? `<button onclick="editObs(${item.id})" class="btn btn-warning btn-sm me-1">Edit</button>` : ''}
                            ${isAdmin ? `<button onclick="deleteObs(${item.id})" class="btn btn-danger btn-sm">Hapus</button>` : ''}
                            ${!editable && !isAdmin ? '<span class="text-muted small">-</span>' : ''}
                        </td>
                    </tr>
                `;
            });
        }

        function saveObservation(e) {
            e.preventDefault();
            const editId = document.getElementById('editObsId').value;
            const payload = {
                date: document.getElementById('obsDate').value,
                bedengan: document.getElementById('obsBedengan').value,
                severity: document.getElementById('obsSeverity').value,
                count: parseInt(document.getElementById('obsCount').value)
            };
            const url = editId ? `/api/observations/update/${editId}` : '/api/observations/add';

            fetch(url, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                body: JSON.stringify(payload)
            })
            .then(res => res.json())
            .then(result => {
                if(result.success) {
                    cancelEditObs();
                    loadData();
                    if (!editId) alert('Observasi hama berhasil disimpan ke Database!');
                } else if (result.error) {
                    alert(result.error);
                }
            });
        }
        function addObservation(e) { saveObservation(e); }

        function editObs(id) {
            const item = observationData.find(o => o.id === id);
            if (!item) return;
            if (!canEdit(item.user)) { alert('Hanya pemilik data / admin yang boleh edit.'); return; }
            document.getElementById('editObsId').value = id;
            document.getElementById('obsDate').value = item.date;
            document.getElementById('obsBedengan').value = item.bedengan;
            document.getElementById('obsSeverity').value = item.severity;
            document.getElementById('obsCount').value = item.count;
            document.getElementById('obsSubmitBtn').innerText = '✏️ Update Observasi';
            document.getElementById('obsCancelBtn').classList.remove('d-none');
        }

        function cancelEditObs() {
            document.getElementById('editObsId').value = '';
            const f = document.getElementById('observationForm');
            if (f) f.reset();
            document.getElementById('obsDate').value = new Date().toISOString().split('T')[0];
            document.getElementById('obsSubmitBtn').innerText = '💾 Simpan Observasi Hama';
            document.getElementById('obsCancelBtn').classList.add('d-none');
        }

        function deleteObs(id) {
            if (!isAdmin) { alert('Hanya Admin yang dapat menghapus.'); return; }
            if(!confirm('Hapus observasi ini?')) return;
            fetch(`/api/observations/delete/${id}`, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' }
            })
            .then(res => res.json())
            .then(result => { if(result.success) loadData(); else if (result.error) alert(result.error); });
        }

        function renderCalendar() {
            let container = document.getElementById('calendarDays');
            let titleEl = document.getElementById('calendarTitle');
            if (!container) return;
            container.innerHTML = '';
            
            let now = new Date();
            let year = now.getFullYear();
            let month = now.getMonth(); // 0 - 11
            let todayDate = now.getDate();
            let todayMonth = now.getMonth();
            let todayYear = now.getFullYear();

            const monthNames = ["Januari", "Februari", "Maret", "April", "Mei", "Juni", "Juli", "Agustus", "September", "Oktober", "November", "Desember"];
            if (titleEl) {
                titleEl.innerHTML = `<i class="bi bi-calendar-check"></i> Kalender ${monthNames[month]} ${year}`;
            }

            let firstDay = new Date(year, month, 1).getDay();
            let totalDays = new Date(year, month + 1, 0).getDate();
            let sprayedDates = historyData.map(h => h.date);
            
            for(let i=0; i<firstDay; i++) {
                container.innerHTML += `<div></div>`;
            }

            for(let day=1; day<=totalDays; day++) {
                let mStr = String(month + 1).padStart(2, '0');
                let dStr = String(day).padStart(2, '0');
                let dayStr = `${year}-${mStr}-${dStr}`;
                
                let isSprayed = sprayedDates.includes(dayStr);
                let isToday = (day === todayDate && month === todayMonth && year === todayYear);
                
                let cls = 'calendar-day';
                if (isSprayed) cls += ' sprayed';
                if (isToday) cls += ' today';

                container.innerHTML += `<div class="${cls}">${day}</div>`;
            }
        }

        // --- Kelola Bedengan & Pengguna (Admin) ---
        function renderBedenganList() {
            const ul = document.getElementById('bedenganList');
            if (!ul) return;
            ul.innerHTML = '';
            bedenganData.forEach(b => {
                ul.innerHTML += `<li class="list-group-item d-flex justify-content-between align-items-center">${b.name}<button onclick="deleteBedengan(${b.id})" class="btn btn-danger btn-sm">Hapus</button></li>`;
            });
            if (!bedenganData.length) ul.innerHTML = '<li class="list-group-item text-muted">Belum ada bedengan.</li>';
        }

        function addBedengan(e) {
            e.preventDefault();
            const name = document.getElementById('newBedenganName').value.trim();
            if (!name) return;
            fetch('/api/bedengans/add', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                body: JSON.stringify({ name })
            }).then(res => res.json()).then(result => {
                if (result.success) { document.getElementById('newBedenganName').value = ''; loadData(); }
                else if (result.error) alert(result.error);
                else if (result.message) alert(result.message);
            });
        }

        function deleteBedengan(id) {
            if (!confirm('Hapus bedengan ini?')) return;
            fetch(`/api/bedengans/delete/${id}`, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' }
            }).then(res => res.json()).then(result => { if (result.success) loadData(); });
        }

        function loadUsers() {
            fetch('/api/users').then(res => res.json()).then(data => {
                const tbody = document.getElementById('userTableBody');
                if (!tbody) return;
                tbody.innerHTML = '';
                (Array.isArray(data) ? data : []).forEach(u => {
                    tbody.innerHTML += `<tr><td>${u.name}</td><td>${u.username}</td><td><span class="badge ${u.role === 'admin' ? 'bg-success' : 'bg-primary'}">${u.role}</span></td><td>${u.username !== 'admin' ? `<button onclick="deleteUser(${u.id})" class="btn btn-danger btn-sm">Hapus</button>` : ''}</td></tr>`;
                });
            });
        }

        function addUser(e) {
            e.preventDefault();
            const payload = {
                name: document.getElementById('newUserName').value.trim(),
                username: document.getElementById('newUsername').value.trim(),
                password: document.getElementById('newUserPass').value,
                role: document.getElementById('newUserRole').value
            };
            fetch('/api/users/add', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                body: JSON.stringify(payload)
            }).then(res => res.json()).then(result => {
                if (result.success) { alert('User baru berhasil ditambah.'); e.target.reset(); loadUsers(); }
                else if (result.error) alert(result.error);
                else if (result.message) alert(result.message);
            });
        }

        function deleteUser(id) {
            if (!confirm('Hapus user ini?')) return;
            fetch(`/api/users/delete/${id}`, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' }
            }).then(res => res.json()).then(result => {
                if (result.success) loadUsers();
                else if (result.error) alert(result.error);
            });
        }

        function resetPassword(e) {
            e.preventDefault();
            const payload = {
                username: document.getElementById('resetUsername').value.trim(),
                new_password: document.getElementById('resetPass').value
            };
            fetch('/api/users/reset-password', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                body: JSON.stringify(payload)
            }).then(res => res.json()).then(result => {
                if (result.success) { alert('Password berhasil direset.'); e.target.reset(); }
                else if (result.error) alert(result.error);
                else if (result.message) alert(result.message);
            });
        }

        function updateBedenganReminders() {
            const bedengans = bedenganData.length ? bedenganData.map(b => b.name) : ['Bedengan 1', 'Bedengan 2', 'Bedengan 3'];
            const container = document.getElementById('bedenganReminderContainer');
            container.innerHTML = '';

            const today = new Date();

            bedengans.forEach(bedengan => {
                // Cari history terakhir untuk bedengan ini
                let lastRecord = historyData
                    .filter(h => h.bedengan.includes(bedengan))
                    .sort((a, b) => new Date(b.date) - new Date(a.date))[0];

                let badgeHtml = '';
                if (!lastRecord) {
                    badgeHtml = `<span class="badge bg-secondary">Belum ada riwayat penyemprotan</span>`;
                } else {
                    let lastDate = new Date(lastRecord.date);
                    // Tambah 7 hari
                    let nextDueDate = new Date(lastDate);
                    nextDueDate.setDate(lastDate.getDate() + 7);

                    let diffTime = nextDueDate - today;
                    let diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));

                    let dateStr = nextDueDate.toISOString().split('T')[0];

                    if (diffDays < 0) {
                        badgeHtml = `<span class="badge bg-danger">⚠️ Terlambat ${Math.abs(diffDays)} hari! (Jadwal: ${dateStr})</span>`;
                    } else if (diffDays === 0) {
                        badgeHtml = `<span class="badge bg-warning text-dark">🔔 Waktunya semprot HARI INI!</span>`;
                    } else {
                        badgeHtml = `<span class="badge bg-success">✅ Berikutnya dalam ${diffDays} hari (${dateStr})</span>`;
                    }
                }

                container.innerHTML += `
                    <div class="col-md-4">
                        <div class="p-2 border rounded bg-light">
                            <strong>${bedengan}</strong><br>
                            <div class="mt-1">${badgeHtml}</div>
                        </div>
                    </div>
                `;
            });
        }

        // Init
        loadData();

        // Charts Setup
        const ctxPest = document.getElementById('pestChart').getContext('2d');
        const pestChart = new Chart(ctxPest, {
            type: 'line',
            data: {
                labels: observationData.map(o => o.date),
                datasets: [{
                    label: 'Jumlah Serangga Terperangkap',
                    data: observationData.map(o => o.count),
                    borderColor: 'rgb(255, 99, 132)',
                    backgroundColor: 'rgba(255, 99, 132, 0.1)',
                    fill: true,
                    tension: 0.1
                }]
            }
        });

        const ctxSpray = document.getElementById('sprayChart').getContext('2d');
        const sprayChart = new Chart(ctxSpray, {
            type: 'bar',
            data: {
                labels: historyData.map(h => h.date),
                datasets: [{
                    label: 'Durasi (Menit)',
                    data: historyData.map(h => h.duration),
                    backgroundColor: 'rgb(54, 162, 235)'
                }]
            }
        });

        function updatePestChart() {
            let sortedObs = [...observationData].sort((a,b) => new Date(a.date) - new Date(b.date));
            pestChart.data.labels = sortedObs.map(o => o.date);
            pestChart.data.datasets[0].data = sortedObs.map(o => o.count);
            pestChart.update();
        }

        function updateSprayChart() {
            let sortedHist = [...historyData].sort((a,b) => new Date(a.date) - new Date(b.date));
            sprayChart.data.labels = sortedHist.map(h => h.date);
            sprayChart.data.datasets[0].data = sortedHist.map(h => h.duration);
            sprayChart.update();
        }
    </script>
</body>
</html>

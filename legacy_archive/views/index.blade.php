@extends('layouts.app')

@section('title', 'Smart Farm Cabai - Dashboard')

@section('content')
<div class="grid grid-cols-1 lg:grid-cols-3 gap-4">

    {{-- 2. Pengingat Jadwal Penyemprotan (Interval 7 Hari / Bedengan) --}}
    <section class="bg-white rounded shadow p-4 lg:col-span-1">
        <h2 class="font-bold text-green-800 mb-2">Pengingat Jadwal Penyemprotan<br><span class="text-xs font-normal text-gray-500">(Interval 7 Hari / Bedengan)</span></h2>
        <ul class="space-y-2 text-sm">
            @forelse ($reminderItems as $item)
                <li class="border rounded p-2">
                    <div class="font-semibold">{{ $item['bedengan']->name }}</div>
                    @if ($item['last_date'])
                        <div>Terakhir: {{ $item['last_date']->toDateString() }}</div>
                        <div>
                            Berikutnya
                            @if ($item['days_remaining'] > 0)
                                dalam {{ $item['days_remaining'] }} hari ({{ $item['next_date']->toDateString() }})
                            @elseif ($item['days_remaining'] === 0)
                                hari ini ({{ $item['next_date']->toDateString() }})
                            @else
                                <span class="text-red-600 font-semibold">terlambat {{ abs($item['days_remaining']) }} hari ({{ $item['next_date']->toDateString() }})</span>
                            @endif
                        </div>
                    @else
                        <div class="text-gray-500">Belum ada riwayat penyemprotan.</div>
                    @endif
                </li>
            @empty
                <li class="text-sm text-gray-500">Belum ada bedengan.</li>
            @endforelse
        </ul>
    </section>

    {{-- 3. Kontrol Perangkat IoT --}}
    <section class="bg-white rounded shadow p-4 lg:col-span-2">
        <h2 class="font-bold text-green-800 mb-2">Kontrol Perangkat IoT</h2>
        @foreach ($devices as $device)
            <div class="text-xs text-gray-500 mb-2">
                {{ $device->name }} ({{ $device->device_id }}) —
                Status: <span class="font-semibold">{{ $device->status->value }}</span>
                @if ($device->last_seen) — Terakhir terlihat: {{ $device->last_seen->diffForHumans() }} @endif
            </div>
        @endforeach
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            @foreach ($actuatorPanels as $panel)
                @php $act = $panel['actuator']; @endphp
                <div class="border rounded p-3" data-actuator-panel data-remaining="{{ $panel['remaining_seconds'] ?? '' }}">
                    <div class="font-semibold">
                        @if ($act->actuator_type === 'UV_LIGHT')
                            Lampu UV (Atraktan Hama)
                        @elseif ($act->actuator_type === 'MIST_MAKER')
                            Mist Maker (Penyemprotan)
                        @else
                            {{ $act->actuator_type }}
                        @endif
                    </div>
                    <div class="text-sm">Status: <span class="font-bold" data-state>{{ $act->state->value }}</span></div>
                    <div class="text-sm">Auto-off: {{ $panel['default_duration'] }} Menit</div>
                    <div class="text-sm">Timer: <span class="font-mono" data-timer>{{ $panel['remaining_seconds'] !== null ? gmdate('i:s', $panel['remaining_seconds']) : '--:--' }}</span></div>
                    @if ($panel['last_command'])
                        <div class="text-xs text-gray-500">Command terakhir: {{ $panel['last_command']->command }} ({{ $panel['last_command']->status->value }})</div>
                    @endif
                    <div class="flex gap-2 mt-2">
                        <form method="POST" action="{{ route('actuators.commands.store', $act) }}">
                            @csrf
                            <input type="hidden" name="command" value="ON">
                            <input type="hidden" name="duration" value="{{ $panel['default_duration'] }}">
                            <button type="submit" class="bg-green-600 hover:bg-green-700 text-white text-sm px-3 py-1.5 rounded">TURN ON</button>
                        </form>
                        <form method="POST" action="{{ route('actuators.commands.store', $act) }}">
                            @csrf
                            <input type="hidden" name="command" value="OFF">
                            <button type="submit" class="bg-gray-500 hover:bg-gray-600 text-white text-sm px-3 py-1.5 rounded">TURN OFF</button>
                        </form>
                    </div>
                </div>
            @endforeach
        </div>

        <h3 class="font-semibold text-sm mt-4 mb-1">5 Aktivitas Terbaru</h3>
        <div class="overflow-x-auto">
            <table class="w-full text-xs border">
                <thead class="bg-gray-100">
                    <tr><th class="border px-2 py-1">Waktu</th><th class="border px-2 py-1">Device</th><th class="border px-2 py-1">Actuator</th><th class="border px-2 py-1">Command</th><th class="border px-2 py-1">Status</th></tr>
                </thead>
                <tbody>
                    @forelse ($commandHistory as $cmd)
                        <tr>
                            <td class="border px-2 py-1">{{ $cmd->created_at }}</td>
                            <td class="border px-2 py-1">{{ $cmd->device->device_id ?? '-' }}</td>
                            <td class="border px-2 py-1">{{ $cmd->actuator->actuator_type ?? '-' }}</td>
                            <td class="border px-2 py-1">{{ $cmd->command }}{{ $cmd->duration ? " ({$cmd->duration} mnt)" : '' }}</td>
                            <td class="border px-2 py-1">{{ $cmd->status->value }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="border px-2 py-1 text-center text-gray-500">Belum ada command.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-4 mt-4">
    {{-- 5. Input Penyemprotan --}}
    <section class="bg-white rounded shadow p-4">
        <h2 class="font-bold text-green-800 mb-2">Input Penyemprotan</h2>
        <form method="POST" action="{{ route('spraying-records.store') }}">
            @csrf
            <input type="hidden" name="project_id" value="{{ $project->id }}">
            <label class="block text-sm font-medium">Tanggal</label>
            <input type="date" name="spraying_date" value="{{ old('spraying_date', date('Y-m-d')) }}" required class="w-full border rounded px-3 py-2 mb-2">
            <label class="block text-sm font-medium">Waktu</label>
            <input type="time" name="spraying_time" value="{{ old('spraying_time', '06:30') }}" required class="w-full border rounded px-3 py-2 mb-2">
            <label class="block text-sm font-medium">Bedengan Target</label>
            <select name="bedengan_id" required class="w-full border rounded px-3 py-2 mb-2">
                @foreach ($project->bedengans as $bedengan)
                    <option value="{{ $bedengan->id }}" @selected(old('bedengan_id') == $bedengan->id)>{{ $bedengan->name }}</option>
                @endforeach
            </select>
            <label class="block text-sm font-medium">Durasi (Menit)</label>
            <input type="number" name="duration_minutes" min="1" value="{{ old('duration_minutes', 5) }}" required class="w-full border rounded px-3 py-2 mb-2">
            <label class="block text-sm font-medium">Catatan Opsional</label>
            <textarea name="notes" rows="2" placeholder="Kondisi tanaman atau jenis pestisida..." class="w-full border rounded px-3 py-2 mb-2">{{ old('notes') }}</textarea>
            <button type="submit" class="w-full bg-green-700 hover:bg-green-800 text-white font-semibold py-2 rounded">Simpan Catatan Penyemprotan</button>
        </form>
    </section>

    {{-- 6. Input Observasi Hama --}}
    <section class="bg-white rounded shadow p-4">
        <h2 class="font-bold text-green-800 mb-2">Input Observasi Hama (Sticky Trap)</h2>
        <form method="POST" action="{{ route('pest-observations.store') }}">
            @csrf
            <input type="hidden" name="project_id" value="{{ $project->id }}">
            <label class="block text-sm font-medium">Tanggal</label>
            <input type="date" name="observation_date" value="{{ old('observation_date', date('Y-m-d')) }}" required class="w-full border rounded px-3 py-2 mb-2">
            <label class="block text-sm font-medium">Bedengan</label>
            <select name="bedengan_id" required class="w-full border rounded px-3 py-2 mb-2">
                @foreach ($project->bedengans as $bedengan)
                    <option value="{{ $bedengan->id }}" @selected(old('bedengan_id') == $bedengan->id)>{{ $bedengan->name }}</option>
                @endforeach
            </select>
            <label class="block text-sm font-medium">Tingkat Serangan</label>
            <select name="attack_level" required class="w-full border rounded px-3 py-2 mb-2">
                <option value="RINGAN">Ringan</option>
                <option value="SEDANG">Sedang</option>
                <option value="BERAT">Berat</option>
            </select>
            <label class="block text-sm font-medium">Jumlah Serangga (Sticky Trap)</label>
            <input type="number" name="insect_count" min="0" placeholder="Contoh: 20" value="{{ old('insect_count') }}" required class="w-full border rounded px-3 py-2 mb-2">
            <label class="block text-sm font-medium">Catatan</label>
            <textarea name="notes" rows="2" placeholder="Opsional" class="w-full border rounded px-3 py-2 mb-2">{{ old('notes') }}</textarea>
            <button type="submit" class="w-full bg-green-700 hover:bg-green-800 text-white font-semibold py-2 rounded">Simpan Observasi</button>
        </form>
    </section>

    {{-- 4. Kalender Penyemprotan --}}
    <section class="bg-white rounded shadow p-4">
        <h2 class="font-bold text-green-800 mb-2">Kalender Penyemprotan</h2>
        <div class="flex items-center justify-between text-sm mb-2">
            <a class="text-green-700 underline" href="{{ route('dashboard', ['cal_year' => $calMonth === 1 ? $calYear - 1 : $calYear, 'cal_month' => $calMonth === 1 ? 12 : $calMonth - 1]) }}">&larr; Prev</a>
            <span class="font-semibold">{{ $monthStart->format('F Y') }}</span>
            <a class="text-green-700 underline" href="{{ route('dashboard', ['cal_year' => $calMonth === 12 ? $calYear + 1 : $calYear, 'cal_month' => $calMonth === 12 ? 1 : $calMonth + 1]) }}">Next &rarr;</a>
        </div>
        <div class="grid grid-cols-7 gap-1 text-center text-xs">
            @foreach (['Sn','Sl','Rb','Km','Jm','Sb','Mn'] as $d)
                <div class="font-semibold text-gray-500">{{ $d }}</div>
            @endforeach
            @foreach ($calendarCells as $cell)
                @php
                    $key = $cell['date']->toDateString();
                    $isToday = $key === today()->toDateString();
                    $hasSpray = isset($sprayingDates[$key]);
                @endphp
                <a href="{{ route('dashboard', ['cal_year' => $calYear, 'cal_month' => $calMonth, 'selected_date' => $key]) }}"
                   class="border rounded py-1 {{ $cell['in_month'] ? '' : 'text-gray-300' }} {{ $isToday ? 'bg-green-700 text-white font-bold' : '' }} {{ $hasSpray && !$isToday ? 'ring-2 ring-green-500 font-bold' : '' }}">
                    {{ $cell['date']->day }}
                </a>
            @endforeach
        </div>
        <div class="text-xs text-gray-500 mt-2">Kotak hijau tebal = ada penyemprotan. Hijau penuh = hari ini.</div>
        @if ($selectedDate)
            <div class="mt-2 text-sm">
                <div class="font-semibold">Penyemprotan {{ $selectedDate }}:</div>
                @forelse ($selectedSprayings as $s)
                    <div class="border rounded p-1 mt-1">{{ $s->spraying_time }} — {{ $s->bedengan->name }} ({{ $s->duration_minutes }} mnt)</div>
                @empty
                    <div class="text-gray-500">Tidak ada penyemprotan pada tanggal ini.</div>
                @endforelse
            </div>
        @endif
    </section>
</div>

{{-- 11. Riwayat Penyemprotan Pestisida --}}
<section class="bg-white rounded shadow p-4 mt-4">
    <h2 class="font-bold text-green-800 mb-2">Riwayat Penyemprotan Pestisida</h2>
    <div class="overflow-x-auto">
        <table class="w-full text-sm border">
            <thead class="bg-gray-100">
                <tr>
                    <th class="border px-2 py-1">Tanggal &amp; Waktu</th>
                    <th class="border px-2 py-1">Bedengan</th>
                    <th class="border px-2 py-1">Durasi</th>
                    <th class="border px-2 py-1">Catatan</th>
                    <th class="border px-2 py-1">User</th>
                    <th class="border px-2 py-1">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($sprayingHistory as $row)
                    <tr>
                        <td class="border px-2 py-1">{{ $row->spraying_date->toDateString() }} {{ $row->spraying_time }}</td>
                        <td class="border px-2 py-1">{{ $row->bedengan->name }}</td>
                        <td class="border px-2 py-1">{{ $row->duration_minutes }} mnt</td>
                        <td class="border px-2 py-1">{{ $row->notes ?? '-' }}</td>
                        <td class="border px-2 py-1">{{ $row->user->name ?? '-' }}</td>
                        <td class="border px-2 py-1">
                            <form method="POST" action="{{ route('spraying-records.destroy', $row) }}" onsubmit="return confirm('Hapus riwayat ini?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="bg-red-600 hover:bg-red-700 text-white text-xs px-2 py-1 rounded">Hapus</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="border px-2 py-1 text-center text-gray-500">Belum ada riwayat.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</section>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mt-4">
    {{-- 12. Trend Populasi Hama (agregasi SUM per tanggal) --}}
    <section class="bg-white rounded shadow p-4">
        <div class="flex items-center justify-between mb-2">
            <h2 class="font-bold text-green-800">Trend Populasi Hama (Sticky Trap)</h2>
            <form method="GET" action="{{ route('dashboard') }}">
                <select name="bedengan_id" onchange="this.form.submit()" class="text-sm border rounded px-2 py-1">
                    <option value="">Semua Bedengan</option>
                    @foreach ($project->bedengans as $bedengan)
                        <option value="{{ $bedengan->id }}" @selected($bedenganFilter == $bedengan->id)>{{ $bedengan->name }}</option>
                    @endforeach
                </select>
            </form>
        </div>
        <canvas id="pestChart" height="120"></canvas>
        <p class="text-xs text-gray-500 mt-1">Agregasi: SUM(insect_count) per observation_date.</p>
    </section>

    {{-- 13. Riwayat Durasi Penyemprotan (dari spraying_records) --}}
    <section class="bg-white rounded shadow p-4">
        <h2 class="font-bold text-green-800 mb-2">Riwayat Durasi Penyemprotan (Menit)</h2>
        <canvas id="sprayChart" height="120"></canvas>
        <p class="text-xs text-gray-500 mt-1">Sumber: spraying_records (SUM(duration_minutes) per spraying_date). Bukan actuator_commands.</p>
    </section>
</div>
@endsection

@push('scripts')
<script>
document.querySelectorAll('[data-actuator-panel]').forEach(function (panel) {
    let remaining = parseInt(panel.dataset.remaining || '', 10);
    if (isNaN(remaining)) return;
    const timerEl = panel.querySelector('[data-timer]');
    const stateEl = panel.querySelector('[data-state]');
    const tick = () => {
        if (remaining <= 0) {
            timerEl.textContent = '--:--';
            if (stateEl) stateEl.textContent = 'OFF';
            clearInterval(iv);
            return;
        }
        const m = String(Math.floor(remaining / 60)).padStart(2, '0');
        const s = String(remaining % 60).padStart(2, '0');
        timerEl.textContent = m + ':' + s;
        remaining -= 1;
    };
    const iv = setInterval(tick, 1000);
    tick();
});

new Chart(document.getElementById('pestChart'), {
    type: 'line',
    data: {
        labels: {!! json_encode($pestTrend->pluck('date')) !!},
        datasets: [{ label: 'Jumlah Serangga', data: {!! json_encode($pestTrend->pluck('total')) !!}, borderColor: '#15803d', tension: 0.3 }]
    },
    options: { responsive: true, scales: { y: { beginAtZero: true } } }
});

new Chart(document.getElementById('sprayChart'), {
    type: 'bar',
    data: {
        labels: {!! json_encode($sprayingDurations->pluck('date')) !!},
        datasets: [{ label: 'Durasi (menit)', data: {!! json_encode($sprayingDurations->pluck('total')) !!}, backgroundColor: '#16a34a' }]
    },
    options: { responsive: true, scales: { y: { beginAtZero: true } } }
});
</script>
@endpush

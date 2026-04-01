<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #1a1a1a; margin: 0; padding: 0; }
        .container { width: 90%; margin: 20px auto; }
        .header { text-align: center; margin-bottom: 16px; }
        .logo { font-size: 22px; font-weight: bold; color: #4f46e5; }
        .logo span { color: #e11d48; }
        .company { font-size: 10px; margin: 4px 0; }
        .title { font-size: 13px; font-weight: bold; text-transform: uppercase; border-top: 2px solid #1a1a1a; border-bottom: 2px solid #1a1a1a; padding: 5px 0; margin: 10px 0; text-align: center; }
        .section { margin: 12px 0; }
        .row { display: table; width: 100%; margin: 6px 0; }
        .label { display: table-cell; width: 200px; font-weight: bold; vertical-align: bottom; }
        .value { display: table-cell; border-bottom: 1px solid #555; padding-bottom: 2px; }
        .checkbox-row { margin: 12px 0; }
        .checkbox { display: inline-block; width: 14px; height: 14px; border: 1px solid #333; margin-right: 6px; text-align: center; line-height: 14px; font-size: 10px; }
        .divider { border-top: 1px dashed #666; margin: 14px 0; }
        .approval-section { margin: 12px 0; }
        .sig-row { margin: 6px 0; }
        .approved-stamp { color: #16a34a; font-weight: bold; font-size: 12px; }
        .rejected-stamp { color: #dc2626; font-weight: bold; font-size: 12px; }
        .notes { font-size: 10px; margin-top: 14px; }
        .notes ol { margin: 4px 0; padding-left: 18px; }
    </style>
</head>
<body>
<div class="container">
    <div class="header">
        <div class="logo"><span>Be</span>Daie</div>
        <div class="company">Online Islamic Education Platform by <strong>DAKWAH DIGITAL NETWORK SDN BHD (1431941-W)</strong></div>
    </div>

    <div class="title">Borang Permohonan Kebenaran Meninggalkan Pejabat Dalam Waktu Bekerja</div>

    <div class="section">
        <div class="row">
            <span class="label">Kepada:</span>
            <span class="value">{{ $permission->addressed_to }}</span>
        </div>
        <p style="margin:6px 0;">Saya dengan ini memohon kebenaran pihak Tuan/Puan sepertimana di atas untuk meninggalkan pejabat bagi tujuan:</p>
        <div style="border-bottom:1px solid #555; min-height:20px; margin:4px 0; padding-bottom:4px;">{{ $permission->purpose }}</div>
    </div>

    <div class="checkbox-row">
        <span class="checkbox">{{ $permission->errand_type === 'company' ? '/' : '' }}</span> Urusan Syarikat
        &nbsp;&nbsp;&nbsp;
        <span class="checkbox">{{ $permission->errand_type === 'personal' ? '/' : '' }}</span> Urusan Peribadi
    </div>

    <div class="section">
        <div class="row">
            <span class="label">Tempoh/jam yang diperlukan:</span>
            <span class="value">{{ \Carbon\Carbon::parse($permission->exit_time)->format('h:i A') }} hingga {{ \Carbon\Carbon::parse($permission->return_time)->format('h:i A') }}</span>
        </div>
        <div class="row">
            <span class="label">Nama Penuh Pemohon:</span>
            <span class="value">{{ $permission->employee->full_name }}</span>
        </div>
        <div class="sig-row">
            <div class="row"><span class="label">Tandatangan Pemohon:</span><span class="value">&nbsp;</span></div>
            <div class="row"><span class="label">Jawatan:</span><span class="value">{{ $permission->employee->position?->name ?? '—' }}</span></div>
            <div class="row"><span class="label">Tarikh:</span><span class="value">{{ $permission->exit_date->format('d/m/Y') }}</span></div>
        </div>
    </div>

    <div class="divider"></div>

    <div class="approval-section">
        <p>Permohonan pelepasan waktu bekerja *<strong>{{ $permission->status === 'approved' ? 'diluluskan' : ($permission->status === 'rejected' ? 'tidak diluluskan' : '___________') }}</strong></p>
        <p style="font-size:10px;color:#666;">*potong mana yang tidak berkenaan</p>

        @if($permission->isApproved())
        <p class="approved-stamp">&#10003; DILULUSKAN</p>
        @elseif($permission->status === 'rejected')
        <p class="rejected-stamp">&#10007; TIDAK DILULUSKAN &mdash; {{ $permission->rejection_reason }}</p>
        @endif

        <div class="row"><span class="label">Nama Penuh:</span><span class="value">{{ $permission->approver?->name ?? '—' }}</span></div>
        <div class="row"><span class="label">Jawatan:</span><span class="value">&nbsp;</span></div>
        <div class="row"><span class="label">Tandatangan:</span><span class="value">&nbsp;</span></div>
        <div class="row"><span class="label">Tarikh:</span><span class="value">{{ $permission->approved_at?->format('d/m/Y') ?? '—' }}</span></div>
    </div>

    <div class="divider"></div>

    <div class="notes">
        <strong>Catatan:</strong>
        <ol>
            <li>Sebarang urusan peribadi dalam waktu bekerja hendaklah dimaklumkan dan mendapat kebenaran terlebih dahulu daripada pihak pengurusan.</li>
            <li>Masa yang digunakan atas urusan rasmi syarikat dianggap sebagai waktu bekerja dan akan direkodkan seperti biasa.</li>
        </ol>
    </div>

    <p style="font-size:9px; color:#999; text-align:right; margin-top:16px;">
        Generated: {{ now()->format('d M Y H:i') }} | {{ $permission->permission_number }}
    </p>
</div>
</body>
</html>

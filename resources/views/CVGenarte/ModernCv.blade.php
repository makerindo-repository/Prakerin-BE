@php
    $ACCENT_COLOR = '#3b82f6';
    $BG_COLOR = '#1f2937';
    $TEXT_COLOR = '#4b5563';
    $LIGHT_GRAY = '#e5e7eb';
    $DARK_TEXT = '#374151';

    $work_experience = $data['work_experience'] ?? [];
    $education = $data['education'] ?? [];
    $skills = $data['skills'] ?? [];

    $fullName = $data['full_name'] ?? "Nama Lengkap Anda";
    $jobTitle = $work_experience[0]['job_title'] ?? "Peserta Magang";
    $email = $data['email'] ?? "";
    $phone = $data['phone_number'] ?? "";
    $linkedin = $data['linkedin_url'] ?? "";
    $summary = $data['summary'] ?? "";
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <style>
    @page { margin: 0px; }
    body { margin: 0; padding: 0; font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; font-size: 10pt; background-color: #ffffff; }
    .cv-table { display: table; width: 100%; height: 100%; border-collapse: collapse; table-layout: fixed; }
    .cv-row { display: table-row; }
    .sidebar { display: table-cell; width: 33%; background-color: {{ $BG_COLOR }}; color: #ffffff; padding: 30px 20px; vertical-align: top; }
    .main-content { display: table-cell; width: 67%; padding: 35px 30px; color: {{ $TEXT_COLOR }}; vertical-align: top; }

    .header-h1 { font-size: 20pt; font-weight: bold; color: #ffffff; margin: 0 0 4px 0; word-wrap: break-word; }
    .header-h2 { font-size: 11pt; color: {{ $ACCENT_COLOR }}; font-weight: 300; margin: 0 0 20px 0; }
    .section-title { font-size: 10pt; font-weight: bold; color: {{ $ACCENT_COLOR }}; text-transform: uppercase; letter-spacing: 0.1em; padding-bottom: 4px; border-bottom: 1px solid {{ $ACCENT_COLOR }}; margin-top: 20px; margin-bottom: 10px; }
    .main-title { font-size: 13pt; font-weight: bold; color: {{ $BG_COLOR }}; border-bottom: 2px solid {{ $ACCENT_COLOR }}; padding-bottom: 4px; margin-top: 0; margin-bottom: 12px; text-transform: uppercase; }

    .contact-item { margin-bottom: 8px; font-size: 8.5pt; color: #e5e7eb; word-wrap: break-word; }
    .skill-tag { background-color: {{ $ACCENT_COLOR }}; color: #ffffff; font-size: 8pt; font-weight: 500; padding: 3px 8px; border-radius: 4px; margin-right: 4px; margin-bottom: 6px; display: inline-block; }

    .timeline-item { position: relative; padding-left: 15px; border-left: 2px solid {{ $LIGHT_GRAY }}; margin-bottom: 16px; }

    ul.job-desc { list-style-type: disc; margin-left: 16px; padding: 0; font-size: 9pt; color: {{ $DARK_TEXT }}; }
    ul.job-desc li { margin-bottom: 4px; }
  </style>
</head>
<body>

<div class="cv-table">
  <div class="cv-row">
    <!-- SIDEBAR -->
    <div class="sidebar">
        <div style="text-align: center; margin-bottom: 25px;">
            @if(isset($data['photo_profile']))
                <img src="{{ $data['photo_profile'] }}" alt="Profile Photo" style="width: 80px; height: 80px; border-radius: 50%; border: 3px solid {{ $ACCENT_COLOR }}; margin: 0 auto 12px; display: block;">
            @else
                <div style="width: 70px; height: 70px; background-color: {{ $ACCENT_COLOR }}; border-radius: 50%; margin: 0 auto 12px; line-height: 70px; text-align: center; font-size: 22pt; font-weight: bold; color: #ffffff;">
                    {{ strtoupper(substr($fullName, 0, 1)) }}
                </div>
            @endif
            <h1 class="header-h1">{{ $fullName }}</h1>
            <h2 class="header-h2">{{ $jobTitle }}</h2>
        </div>

        @if($email || $phone || $linkedin)
        <div style="margin-bottom: 20px;">
            <div class="section-title" style="margin-top: 0;">Kontak</div>
            <div style="margin-top: 8px;">
                @if($email)<div class="contact-item"><strong>Email:</strong><br>{{ $email }}</div>@endif
                @if($phone)<div class="contact-item"><strong>Telepon:</strong><br>{{ $phone }}</div>@endif
                @if($linkedin)<div class="contact-item"><strong>LinkedIn:</strong><br>{{ $linkedin }}</div>@endif
            </div>
        </div>
        @endif

        @if(!empty($skills))
        <div style="margin-bottom: 20px;">
            <div class="section-title">Keterampilan</div>
            <div style="margin-top: 8px;">
                @foreach($skills as $skill)
                    <span class="skill-tag">{{ $skill }}</span>
                @endforeach
            </div>
        </div>
        @endif

        @if(!empty($education))
        <div>
            <div class="section-title">Pendidikan</div>
            <div style="margin-top: 8px;">
                @foreach($education as $edu)
                    <div style="font-size: 8.5pt; margin-bottom: 10px;">
                        <div style="font-weight: bold; color: #ffffff;">{{ $edu['institution'] ?? '' }}</div>
                        <div style="color: #d1d5db;">{{ $edu['degree'] ?? '' }} ({{ $edu['field_of_study'] ?? '' }})</div>
                        <div style="color: #9ca3af; font-size: 8pt; font-style: italic;">Lulus: {{ $edu['graduation_year'] ?? '' }}</div>
                    </div>
                @endforeach
            </div>
        </div>
        @endif
    </div>

    <!-- MAIN CONTENT -->
    <div class="main-content">
        @if($summary)
        <div style="margin-bottom: 25px;">
            <div class="main-title">Ringkasan</div>
            <p style="font-size: 9.5pt; line-height: 1.5; margin: 0; text-align: justify;">{{ $summary }}</p>
        </div>
        @endif

        @if(!empty($work_experience))
        <div>
            <div class="main-title" style="margin-bottom: 16px;">Pengalaman Kerja</div>
            <div>
                @foreach($work_experience as $job)
                    <div class="timeline-item">
                        <div style="font-size: 8.5pt; color: #6b7280; margin: 0;">{{ $job['start_date'] ?? '' }} - {{ $job['end_date'] ?? 'Sekarang' }}</div>
                        <div style="font-size: 10.5pt; font-weight: bold; color: {{ $ACCENT_COLOR }}; margin: 2px 0;">{{ $job['job_title'] ?? '' }}</div>
                        <div style="font-size: 9.5pt; font-weight: 600; color: {{ $TEXT_COLOR }}; margin: 0 0 6px 0;">{{ $job['company'] ?? '' }}</div>

                        @if(!empty($job['description_points']))
                        <ul class="job-desc">
                            @foreach($job['description_points'] as $point)
                                <li>{{ $point }}</li>
                            @endforeach
                        </ul>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>
        @endif
    </div>
  </div>
</div>

</body>
</html>

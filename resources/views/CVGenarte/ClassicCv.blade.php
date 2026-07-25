@php
    $work_experience = $data['work_experience'] ?? [];
    $education = $data['education'] ?? [];
    $skills = $data['skills'] ?? [];

    $fullName = $data['full_name'] ?? 'Nama Lengkap Anda';
    $jobTitle = $work_experience[0]['job_title'] ?? 'Peserta Magang';
    $email = $data['email'] ?? '';
    $phone = $data['phone_number'] ?? '';
    $linkedin = $data['linkedin_url'] ?? '';
    $summary = $data['summary'] ?? '';
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <style>
    @page { margin: 35px; }
    body { font-family: 'Georgia', Times, 'Times New Roman', serif; font-size: 10pt; color: #1a202c; line-height: 1.6; margin: 0; padding: 0; }
    .header { text-align: left; margin-bottom: 20px; border-bottom: 2px solid #1a202c; padding-bottom: 15px; }
    .name { font-size: 24pt; font-weight: bold; color: #1a202c; margin: 0 0 4px 0; font-family: 'Georgia', serif; }
    .title { font-size: 12pt; color: #4a5568; font-style: italic; margin-bottom: 8px; }
    .contact-info { font-size: 9pt; color: #4a5568; font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; }
    .contact-info span { margin-right: 12px; }
    .section-title { font-size: 12pt; font-weight: bold; color: #1a202c; border-bottom: 1px solid #a0aec0; padding-bottom: 3px; margin-top: 20px; margin-bottom: 12px; font-family: 'Georgia', serif; text-transform: uppercase; letter-spacing: 0.5px; }
    .job-item, .edu-item { margin-bottom: 14px; }
    .flex-between { display: table; width: 100%; margin-bottom: 2px; }
    .flex-left { display: table-cell; text-align: left; }
    .flex-right { display: table-cell; text-align: right; font-style: italic; color: #718096; font-size: 9pt; font-family: 'Helvetica Neue', sans-serif; }
    .job-title { font-size: 10.5pt; font-weight: bold; color: #1a202c; }
    .company-name { font-style: italic; color: #4a5568; }
    ul.desc-list { margin: 4px 0 0 18px; padding: 0; font-size: 9.5pt; color: #2d3748; }
    ul.desc-list li { margin-bottom: 4px; }
    .skill-tag { display: inline-block; background-color: #edf2f7; color: #2d3748; padding: 3px 8px; margin-right: 6px; margin-bottom: 6px; font-size: 8.5pt; font-family: 'Helvetica Neue', sans-serif; border-radius: 4px; }
  </style>
</head>
<body>

  <div class="header">
    <h1 class="name">{{ $fullName }}</h1>
    <div class="title">{{ $jobTitle }}</div>
    <div class="contact-info">
      @if($email)<span>Email: {{ $email }}</span>@endif
      @if($phone)<span>Telepon: {{ $phone }}</span>@endif
      @if($linkedin)<span>LinkedIn: {{ $linkedin }}</span>@endif
    </div>
  </div>

  @if($summary)
  <div class="section">
    <div class="section-title">Ringkasan Profesional</div>
    <p style="margin: 0; text-align: justify; font-size: 9.5pt;">{{ $summary }}</p>
  </div>
  @endif

  @if(!empty($work_experience))
  <div class="section">
    <div class="section-title">Pengalaman Kerja</div>
    @foreach($work_experience as $job)
      <div class="job-item">
        <div class="flex-between">
          <div class="flex-left">
            <span class="job-title">{{ $job['job_title'] ?? '-' }}</span> — <span class="company-name">{{ $job['company'] ?? '-' }}</span>
          </div>
          <div class="flex-right">
            {{ $job['start_date'] ?? '' }} - {{ $job['end_date'] ?? 'Sekarang' }}
          </div>
        </div>
        @if(!empty($job['description_points']))
          <ul class="desc-list">
            @foreach($job['description_points'] as $point)
              <li>{{ $point }}</li>
            @endforeach
          </ul>
        @endif
      </div>
    @endforeach
  </div>
  @endif

  @if(!empty($education))
  <div class="section">
    <div class="section-title">Pendidikan</div>
    @foreach($education as $edu)
      <div class="edu-item">
        <div class="flex-between">
          <div class="flex-left">
            <strong style="color: #1a202c;">{{ $edu['institution'] ?? '-' }}</strong> — {{ $edu['degree'] ?? '' }} di {{ $edu['field_of_study'] ?? '' }}
          </div>
          <div class="flex-right">
            Lulus: {{ $edu['graduation_year'] ?? '' }}
          </div>
        </div>
      </div>
    @endforeach
  </div>
  @endif

  @if(!empty($skills))
  <div class="section">
    <div class="section-title">Keterampilan</div>
    <div style="margin-top: 4px;">
      @foreach($skills as $skill)
        <span class="skill-tag">{{ $skill }}</span>
      @endforeach
    </div>
  </div>
  @endif

</body>
</html>

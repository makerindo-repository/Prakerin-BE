@php
    // Definisikan konstanta warna
    $ACCENT_COLOR = '#3b82f6';
    $BG_COLOR = '#1f2937';
    $TEXT_COLOR = '#4b5563';
    $LIGHT_GRAY = '#e5e7eb';
    $DARK_TEXT = '#374151';

    // Data setup (sesuai struktur CVResult)
    $work_experience = $data['work_experience'] ?? [];
    $education = $data['education'] ?? [];
    $skills = $data['skills'] ?? [];

    $fullName = $data['full_name'] ?? "Nama Lengkap Anda";
    $jobTitle = $work_experience[0]['job_title'] ?? "Frontend Developer";
    $email = $data['email'] ?? "email@anda.com";
    $phone = $data['phone_number'] ?? "081234567890";
    $linkedin = $data['linkedin_url'] ?? "linkedin.com/in/username";
    $summary = $data['summary'] ?? "Ringkasan profesional Anda akan ditempatkan di sini. Jelaskan pengalaman, keterampilan, dan tujuan karier Anda secara singkat dan padat untuk menarik perhatian perekrut.";

    // Ikon SVG Lucide (digunakan langsung sebagai HTML)
    $mailIcon = '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right: 8px;"><rect width="20" height="16" x="2" y="4" rx="2"/><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/></svg>';
    $phoneIcon = '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right: 8px;"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.63A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/></svg>';
    $linkedinIcon = '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right: 8px;"><path d="M16 8a6 6 0 0 1 6 6v7h-4v-7a2 2 0 0 0-2-2 2 2 0 0 0-2 2v7h-4v-7a6 6 0 0 1 6-6z"/><rect width="4" height="12" x="2" y="9"/><circle cx="4" cy="4" r="2"/></svg>';
@endphp

{{-- Gaya Internal untuk Dompdf --}}
<style>
    body { margin: 0; padding: 0; font-family: Arial, sans-serif; font-size: 10pt; }
    .cv-container { display: flex; min-height: 1123px; background-color: #ffffff; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1); }
    .sidebar { width: 33.333333%; background-color: {{ $BG_COLOR }}; color: #ffffff; padding: 32px; box-sizing: border-box; }
    .main-content { width: 66.666667%; padding: 40px; color: {{ $TEXT_COLOR }}; box-sizing: border-box; }

    .header-h1 { font-size: 24pt; font-weight: bold; color: #ffffff; margin: 0 0 4px 0; }
    .header-h2 { font-size: 12pt; color: {{ $ACCENT_COLOR }}; font-weight: 300; margin: 0; }
    .section-title { font-size: 10pt; font-weight: bold; color: {{ $ACCENT_COLOR }}; text-transform: uppercase; letter-spacing: 0.1em; padding-bottom: 4px; border-bottom: 1px solid {{ $ACCENT_COLOR }}; margin-top: 0; margin-bottom: 10px; }
    .main-title { font-size: 14pt; font-weight: bold; color: {{ $BG_COLOR }}; border-bottom: 2px solid {{ $ACCENT_COLOR }}; padding-bottom: 4px; margin-top: 0; margin-bottom: 12px; text-transform: uppercase; }

    .contact-item { display: flex; align-items: center; margin-bottom: 8px; font-size: 9pt; }
    .skill-tag { background-color: {{ $ACCENT_COLOR }}33; color: #ffffff; font-size: 8pt; font-weight: 500; padding: 3px 8px; border-radius: 12px; margin-right: 6px; margin-bottom: 6px; display: inline-block; }

    .timeline-item { position: relative; padding-left: 20px; border-left: 2px solid {{ $LIGHT_GRAY }}; margin-bottom: 20px; }
    .timeline-dot { position: absolute; left: -5px; top: 0; width: 10px; height: 10px; background-color: {{ $ACCENT_COLOR }}; border-radius: 50%; border: 2px solid #ffffff; }

    ul.job-desc { list-style-type: disc; list-style-position: outside; margin-left: 16px; padding: 0; font-size: 9pt; color: {{ $DARK_TEXT }}; }
    ul.job-desc li { margin-bottom: 4px; }
</style>

<div class="cv-container">
    {{-- --- SIDEBAR KIRI --- --}}
    <aside class="sidebar">
        <header style="text-align: center; margin-bottom: 48px;">
            <div style="width: 100px; height: 100px; background-color: {{ $ACCENT_COLOR }}; border-radius: 50%; margin: 0 auto 16px;">
                {{--  --}}
            </div>
            <h1 class="header-h1">{{ $fullName }}</h1>
            <h2 class="header-h2">{{ $jobTitle }}</h2>
        </header>

        {{-- --- KONTAK --- --}}
        <section style="margin-bottom: 30px;">
            <h3 class="section-title">Kontak</h3>
            <div style="margin-top: 10px;">
                <div class="contact-item">{!! $mailIcon !!}<p style="margin: 0;">{{ $email }}</p></div>
                <div class="contact-item">{!! $phoneIcon !!}<p style="margin: 0;">{{ $phone }}</p></div>
                <div class="contact-item">{!! $linkedinIcon !!}<p style="margin: 0;">{{ $linkedin }}</p></div>
            </div>
        </section>

        {{-- --- KETERAMPILAN --- --}}
        <section style="margin-bottom: 30px;">
            <h3 class="section-title">Keterampilan</h3>
            <div style="margin-top: 10px;">
                @foreach(array_slice($skills, 0, 10) as $skill)
                    <span class="skill-tag">{{ $skill }}</span>
                @endforeach
            </div>
        </section>

        {{-- --- PENDIDIKAN --- --}}
        <section>
            <h3 class="section-title">Pendidikan</h3>
            <div style="margin-top: 10px;">
                @foreach($education as $edu)
                    <div style="font-size: 9pt; margin-bottom: 12px;">
                        <p style="font-weight: bold; margin: 0;">{{ $edu['degree'] ?? '' }}</p>
                        <p style="color: #d1d5db; margin: 0;">{{ $edu['institution'] ?? '' }}</p>
                        <p style="color: #9ca3af; font-size: 8pt; font-style: italic; margin: 0;">{{ $edu['graduation_year'] ?? '' }}</p>
                    </div>
                @endforeach
            </div>
        </section>
    </aside>

    {{-- --- KONTEN UTAMA --- --}}
    <main class="main-content">
        {{-- --- RINGKASAN --- --}}
        <section style="margin-bottom: 30px;">
            <h2 class="main-title">Ringkasan</h2>
            <p style="font-size: 10pt; line-height: 1.5;">{{ $summary }}</p>
        </section>

        {{-- --- PENGALAMAN KERJA --- --}}
        <section>
            <h2 class="main-title" style="margin-bottom: 20px;">Pengalaman Kerja</h2>
            <div>
                @foreach($work_experience as $job)
                    <div class="timeline-item">
                         {{-- Timeline dot --}}
                         <div class="timeline-dot"></div>

                        <p style="font-size: 9pt; color: #6b7280; margin: 0;">{{ $job['start_date'] ?? '' }} - {{ $job['end_date'] ?? '' }}</p>
                        <h3 style="font-size: 11pt; font-weight: bold; color: {{ $ACCENT_COLOR }}; margin: 4px 0 2px 0;">{{ $job['job_title'] ?? '' }}</h3>
                        <p style="font-size: 10pt; font-weight: 500; color: {{ $TEXT_COLOR }}; margin: 0 0 8px 0;">{{ $job['company'] ?? '' }}</p>

                        <ul class="job-desc">
                            @foreach($job['description_points'] ?? [] as $point)
                                <li>{{ $point }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endforeach
            </div>
        </section>
    </main>
</div>

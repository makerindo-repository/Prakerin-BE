<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>CV ATS</title>
  <link rel="stylesheet" href="{{ asset('css/app.css') }}">
  @vite('resources/css/app.css')
</head>
<body class="bg-white text-gray-800 p-8 font-sans text-sm">

  {{-- HEADER --}}
  <header class="text-center mb-6">
    <h1 class="text-3xl font-bold tracking-wider uppercase">
      {{ $data->full_name ?? 'Nama Lengkap Anda' }}
    </h1>
    <p class="text-md mt-1">
      {{ $data->work_experience[0]->job_title ?? 'Frontend Developer' }}
    </p>
    <div class="flex justify-center space-x-4 text-xs mt-2">
      <span>{{ $data->phone_number ?? '081234567890' }}</span>
      <span>|</span>
      <span>{{ $data->email ?? 'email@anda.com' }}</span>
      <span>|</span>
      <span>{{ $data->linkedin_url ?? 'linkedin.com/in/username' }}</span>
    </div>
  </header>

  <main>
    {{-- RINGKASAN --}}
    <section class="mb-6">
      <h2 class="text-lg font-bold uppercase border-b-2 border-gray-400 pb-1 mb-2">Ringkasan</h2>
      <p class="text-justify">
        {{ $data->summary ?? 'Belum ada ringkasan yang ditulis.' }}
      </p>
    </section>

    {{-- PENGALAMAN KERJA --}}
    <section class="mb-6">
      <h2 class="text-lg font-bold uppercase border-b-2 border-gray-400 pb-1 mb-2">Pengalaman Kerja</h2>
      @foreach($data->work_experience ?? [] as $job)
        <div class="mb-4">
          <h3 class="text-md font-bold">{{ $job['job_title'] ?? '-' }}</h3>
          <div class="flex justify-between text-sm">
            <p class="font-semibold">{{ $job['company'] ?? '-' }}</p>
            <p class="italic">{{ $job['start_date'] ?? '-' }} - {{ $job->end_date ?? 'Sekarang' }}</p>
          </div>
          @if(!empty($job['description_points']))
            <ul class="list-disc list-inside mt-1 text-sm space-y-1">
              @foreach($job['description_points'] as $point)
                <li>{{ $point }}</li>
              @endforeach
            </ul>
          @endif
        </div>
      @endforeach
    </section>

    {{-- PENDIDIKAN --}}
    <section class="mb-6">
      <h2 class="text-lg font-bold uppercase border-b-2 border-gray-400 pb-1 mb-2">Pendidikan</h2>
      @foreach($data->education ?? [] as $edu)
        <div class="mb-2">
          <h3 class="text-md font-bold">{{ $edu['institution'] ?? '-' }}</h3>
          <p>{{ $edu['degree'] ?? '-' }}, {{ $edu['field_of_study'] ?? '-' }}</p>
          <p class="text-sm italic">Lulus: {{ $edu['graduation_year'] ?? '-' }}</p>
        </div>
      @endforeach
    </section>

    {{-- KETERAMPILAN --}}
    <section>
      <h2 class="text-lg font-bold uppercase border-b-2 border-gray-400 pb-1 mb-2">Keterampilan</h2>
      <p>
        @if(!empty($data->skills))
          {{ implode(', ', $data->skills) }}
        @else
          Belum ada keterampilan yang ditambahkan.
        @endif
      </p>
    </section>
  </main>

</body>
</html>

<!doctype html>
<html lang="id">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>CV Modern</title>
    {{-- <link rel="stylesheet" href="{{ asset('css/app.css') }}"> --}}
    @php
        $manifest = json_decode(file_get_contents(public_path('build/manifest.json')), true);
        $cssFile = $manifest['resources/css/app.css']['file'];
        $cssPath = public_path('build/' . $cssFile);
    @endphp

    <style>
        {!! file_get_contents($cssPath) !!}
    </style>

</head>

<body>
    <div class="flex min-h-[1123px] bg-white shadow-lg font-sans">

        {{-- === SIDEBAR KIRI === --}}
        <aside class="w-1/3 bg-gray-800 text-white p-8">
            {{-- HEADER --}}
            <header class="text-center mb-12">
                <div class="w-32 h-32 bg-accent rounded-full mx-auto mb-4"></div>
                <h1 class="text-3xl font-bold text-white">
                    {{ $data->full_name ?? 'Nama Lengkap Anda' }}
                </h1>
                <h2 class="text-lg text-accent font-light">
                    {{ $data->work_experience[0]->job_title ?? 'Frontend Developer' }}
                </h2>
            </header>

            {{-- KONTAK --}}
            <section class="mb-10">
                <h3 class="text-lg font-semibold text-accent uppercase tracking-wider mb-3">Kontak</h3>
                <div class="space-y-3 text-sm">
                    <div class="flex items-center gap-3">
                        {{-- Icon mail --}}
                        <x-lucide-mail class="w-4 h-4" />
                        <p>{{ $data->email ?? 'email@anda.com' }}</p>
                    </div>
                    <div class="flex items-center gap-3">
                        {{-- Icon phone --}}
                        <x-lucide-phone class="w-4 h-4" />
                        <p>{{ $data->phone_number ?? '081234567890' }}</p>
                    </div>
                    <div class="flex items-center gap-3">
                        {{-- Icon linkedin --}}
                        <x-lucide-linkedin class="w-4 h-4" />
                        <p>{{ $data->linkedin_url ?? 'linkedin.com/in/username' }}</p>
                    </div>
                </div>
            </section>

            {{-- KETERAMPILAN --}}
            <section class="mb-10">
                <h3 class="text-lg font-semibold text-accent uppercase tracking-wider mb-3">Keterampilan</h3>
                <div class="flex flex-wrap gap-2">
                    @foreach ($data->skills ?? [] as $index => $skill)
                        @if ($index < 10)
                            <span
                                class="bg-accent/20 text-white text-xs font-medium px-3 py-1 rounded-full">{{ $skill }}</span>
                        @endif
                    @endforeach
                </div>
            </section>

            {{-- PENDIDIKAN --}}
            <section>
                <h3 class="text-lg font-semibold text-accent uppercase tracking-wider mb-3">Pendidikan</h3>
                @foreach ($data->education ?? [] as $edu)
                    <div class="text-sm mb-3">
                        <p class="font-bold">{{ $edu->degree ?? '-' }}</p>
                        <p class="text-gray-300">{{ $edu->institution ?? '-' }}</p>
                        <p class="text-gray-400 text-xs italic">{{ $edu->graduation_year ?? '-' }}</p>
                    </div>
                @endforeach
            </section>
        </aside>

        {{-- === KONTEN UTAMA === --}}
        <main class="w-2/3 p-10 text-gray-800">

            {{-- RINGKASAN --}}
            <section class="mb-8">
                <h2 class="text-2xl font-bold text-gray-800 border-b-2 border-accent pb-2 mb-4">Ringkasan</h2>
                <p class="text-base leading-relaxed">
                    {{ $data->summary ?? 'Belum ada ringkasan yang ditulis.' }}
                </p>
            </section>

            {{-- PENGALAMAN KERJA --}}
            <section>
                <h2 class="text-2xl font-bold text-gray-800 border-b-2 border-accent pb-2 mb-6">Pengalaman Kerja</h2>
                <div class="space-y-6">
                    @foreach ($data->work_experience ?? [] as $job)
                        <div class="relative pl-6 border-l-2 border-gray-200">
                            <div
                                class="absolute -left-[9px] top-1 w-4 h-4 bg-accent rounded-full border-4 border-white">
                            </div>
                            <p class="text-xs text-gray-500">
                                {{ $job->start_date ?? '-' }} - {{ $job->end_date ?? 'Sekarang' }}
                            </p>
                            <h3 class="text-lg font-semibold text-accent-dark">{{ $job->job_title ?? '-' }}</h3>
                            <p class="text-md font-medium text-gray-600 mb-2">{{ $job->company ?? '-' }}</p>
                            @if (!empty($job->description_points))
                                <ul class="list-disc list-outside ml-4 text-sm space-y-1 text-gray-700">
                                    @foreach ($job->description_points as $point)
                                        <li>{{ $point }}</li>
                                    @endforeach
                                </ul>
                            @endif
                        </div>
                    @endforeach
                </div>
            </section>

        </main>
    </div>
</body>

</html>

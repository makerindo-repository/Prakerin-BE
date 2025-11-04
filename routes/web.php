<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    $data['data'] = json_decode(`{
    "summary": "Pengembang Frontend Senior dengan pengalaman luas dalam membangun dan memelihara aplikasi web yang scalable dan berkinerja tinggi. Berpengalaman dalam memimpin tim, berkolaborasi dengan berbagai departemen, dan memberikan solusi inovatif untuk meningkatkan pengalaman pengguna. Ahli dalam JavaScript, React.js, dan Next.js, serta memiliki pemahaman mendalam tentang praktik terbaik pengembangan web.",
    "work_experience": [
        {
            "job_title": "Senior Frontend Developer",
            "company": "PT Teknologi Maju Bersama",
            "start_date": "Januari 2022",
            "end_date": "Sekarang",
            "description_points": [
                "Memimpin pengembangan dan pemeliharaan user interface aplikasi web e-commerce utama menggunakan React dan Next.js, menghasilkan peningkatan pengalaman pengguna yang signifikan.",
                "Berkolaborasi erat dengan tim UI/UX dan backend untuk integrasi API yang efisien, memastikan penyampaian fitur baru yang tepat waktu dan berkualitas tinggi.",
                "Bertanggung jawab atas code review dan mentoring developer junior, meningkatkan kualitas kode tim secara keseluruhan dan mempromosikan praktik terbaik pengembangan.",
                "Berhasil meningkatkan performa website sebesar 20% melalui optimasi kode dan implementasi teknik caching yang efektif, yang berdampak positif pada metrik bisnis utama.",
                "Secara proaktif mengidentifikasi dan menyelesaikan bottleneck kinerja, menghasilkan peningkatan yang nyata dalam waktu muat halaman dan responsivitas aplikasi."
            ]
        },
        {
            "job_title": "Frontend Developer",
            "company": "Startup Cepat Koding",
            "start_date": "Juni 2019",
            "end_date": "Desember 2021",
            "description_points": [
                "Merancang dan membangun komponen UI yang reusable, berkontribusi pada standardisasi tampilan dan nuansa di seluruh aplikasi.",
                "Menerjemahkan desain dari Figma menjadi kode HTML, CSS, dan JavaScript yang akurat dan efisien, memastikan implementasi visual yang sempurna.",
                "Mengintegrasikan layanan REST API ke aplikasi frontend, memungkinkan komunikasi data yang lancar dan meningkatkan fungsionalitas aplikasi."
            ]
        }
    ],
    "education": [
        {
            "institution": "Universitas Gadjah Mada",
            "degree": "Sarjana Komputer",
            "field_of_study": "Ilmu Komputer",
            "graduation_year": "2019"
        }
    ],
    "skills": [
        "JavaScript",
        "TypeScript",
        "React.js",
        "Next.js",
        "Node.js",
        "Tailwind CSS",
        "Git",
        "REST API",
        "Kepemimpinan Tim",
        "Kolaborasi",
        "Pemecahan Masalah",
        "Optimasi Performa"
    ],
    "email": "budi.santoso.dev@email.com",
    "full_name": "Budi Santoso",
    "linkedin_url": "https://linkedin.com/in/budisantoso-dev",
    "phone_number": "+62 812 3456 7890"
}`);
    return view('CVGenarte.ModernCv', $data);
});


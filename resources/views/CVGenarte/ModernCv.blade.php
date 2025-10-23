<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>CV Modern - Demo</title>
  {{-- <link rel="stylesheet" href="ModernCV.css" /> --}}
</head>
<body>
  <div class="cv-container" id="cv-root">
    <aside class="cv-aside">
      <header class="aside-header">
        <div class="avatar"></div>
        <h1 id="fullName">Nama Lengkap Anda</h1>
        <h2 id="jobTitle">Frontend Developer</h2>
      </header>

      <section class="aside-section">
        <h3>Kontak</h3>
        <div class="contact">
          <div><strong>Email:</strong> <span id="email">email@anda.com</span></div>
          <div><strong>Telepon:</strong> <span id="phone">081234567890</span></div>
          <div><strong>LinkedIn:</strong> <span id="linkedin">linkedin.com/in/username</span></div>
        </div>
      </section>

      <section class="aside-section">
        <h3>Keterampilan</h3>
        <div id="skills" class="skills"></div>
      </section>

      <section class="aside-section">
        <h3>Pendidikan</h3>
        <div id="education" class="education"></div>
      </section>
    </aside>

    <main class="cv-main">
      <section class="main-section">
        <h2>Ringkasan</h2>
        <p id="summary">Ringkasan singkat tentang profesionalitas Anda.</p>
      </section>

      <section class="main-section">
        <h2>Pengalaman Kerja</h2>
        <div id="work" class="work-list"></div>
      </section>
    </main>
  </div>

  <script>
    // contoh data sesuai interface CVResult (src/models/CV.ts)
    const cvData = {
      full_name: "Andi Pratama",
      email: "andi@example.com",
      phone_number: "081298765432",
      linkedin_url: "linkedin.com/in/andipratama",
      summary: "Frontend developer berpengalaman membangun aplikasi web dengan React dan TypeScript.",
      skills: ["React", "TypeScript", "HTML", "CSS", "Tailwind", "Node.js"],
      work_experience: [
        {
          job_title: "Frontend Developer",
          company: "PT Contoh Teknologi",
          start_date: "Jan 2022",
          end_date: "Sekarang",
          description_points: [
            "Membangun UI responsif menggunakan React + Tailwind.",
            "Mengoptimalkan performa aplikasi sehingga waktu load turun 30%.",
            "Koordinasi dengan tim desain untuk implementasi pixel-perfect."
          ]
        },
        {
          job_title: "Junior Frontend",
          company: "Startup ABC",
          start_date: "Jul 2020",
          end_date: "Des 2021",
          description_points: [
            "Mengembangkan modul form & validasi.",
            "Menulis unit test untuk komponen penting."
          ]
        }
      ],
      education: [
        {
          degree: "S1 Teknik Informatika",
          field_of_study: "Teknik Informatika",
          graduation_year: "2020",
          institution: "Universitas Contoh"
        }
      ]
    };

    // fungsi bantu rendering
    (function renderCV(data) {
      if (!data) return;
      document.getElementById('fullName').textContent = data.full_name || '';
      const firstJob = (data.work_experience && data.work_experience[0]) || {};
      document.getElementById('jobTitle').textContent = firstJob.job_title || 'Frontend Developer';
      document.getElementById('email').textContent = data.email || '';
      document.getElementById('phone').textContent = data.phone_number || '';
      document.getElementById('linkedin').textContent = data.linkedin_url || '';
      document.getElementById('summary').textContent = data.summary || '';

      const skillsWrap = document.getElementById('skills');
      skillsWrap.innerHTML = '';
      (data.skills || []).slice(0,10).forEach(s => {
        const el = document.createElement('span');
        el.className = 'skill-chip';
        el.textContent = s;
        skillsWrap.appendChild(el);
      });

      const eduWrap = document.getElementById('education');
      eduWrap.innerHTML = '';
      (data.education || []).forEach(edu => {
        const div = document.createElement('div');
        div.className = 'edu-item';
        div.innerHTML = `<div class="edu-degree">${edu.degree || ''}</div>
                         <div class="edu-inst">${edu.institution || ''}</div>
                         <div class="edu-year">${edu.graduation_year || ''}</div>`;
        eduWrap.appendChild(div);
      });

      const workWrap = document.getElementById('work');
      workWrap.innerHTML = '';
      (data.work_experience || []).forEach(job => {
        const jobEl = document.createElement('div');
        jobEl.className = 'job-item';
        const points = (job.description_points || []).map(p => `<li>${p}</li>`).join('');
        jobEl.innerHTML = `
          <div class="job-head">
            <div class="job-dates">${job.start_date || ''} - ${job.end_date || ''}</div>
            <h3 class="job-title">${job.job_title || ''}</h3>
            <div class="job-company">${job.company || ''}</div>
          </div>
          <ul class="job-points">${points}</ul>
        `;
        workWrap.appendChild(jobEl);
      });
    })(cvData);
  </script>
</body>
</html>

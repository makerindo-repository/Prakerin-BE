<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>CV ATS - Demo</title>
  <link rel="stylesheet" href="AtsCV.css" />
</head>
<body>
  <div class="cv-ats-card cv-card" id="cv-root">
    <header class="cv-ats-header cv-header">
      <h1 id="fullName">Nama Lengkap Anda</h1>
      <p id="jobTitle" class="job-title">Frontend Developer</p>
      <div class="contact" id="contactLine">
        <span id="phone">081234567890</span>
        <span class="sep">|</span>
        <span id="email">email@anda.com</span>
        <span class="sep">|</span>
        <span id="linkedin">linkedin.com/in/username</span>
      </div>
    </header>

    <main class="cv-main">
      <section class="section">
        <h2>Ringkasan</h2>
        <p id="summary">Ringkasan singkat tentang pengalaman profesional Anda.</p>
      </section>

      <section class="section">
        <h2>Pengalaman Kerja</h2>
        <div id="workList" class="work-list"></div>
      </section>

      <section class="section">
        <h2>Pendidikan</h2>
        <div id="educationList" class="edu-list"></div>
      </section>

      <section class="section">
        <h2>Keterampilan</h2>
        <div id="skillsList" class="skills-list"></div>
      </section>
    </main>
  </div>

  <script>
    // contoh data sesuai interface CVResult
    const cvData = {
      full_name: "Siti Nurhaliza",
      email: "siti@example.com",
      phone_number: "081234567890",
      linkedin_url: "linkedin.com/in/sitinur",
      summary: "Frontend developer dengan pengalaman membuat UI aksesibel dan performa tinggi.",
      skills: ["React", "TypeScript", "Accessibility", "Testing"],
      work_experience: [
        {
          job_title: "Frontend Developer",
          company: "PT Teknologi Contoh",
          start_date: "Feb 2023",
          end_date: "Sekarang",
          description_points: [
            "Membangun interface SPA menggunakan React + TypeScript.",
            "Mengimplementasikan test unit & e2e untuk komponen kritis."
          ]
        }
      ],
      education: [
        {
          degree: "S1 Teknik Informatika",
          field_of_study: "Teknik Informatika",
          graduation_year: "2022",
          institution: "Universitas Contoh"
        }
      ]
    };

    (function renderCV(data) {
      if (!data) return;
      document.getElementById('fullName').textContent = data.full_name || '';
      const firstJob = (data.work_experience && data.work_experience[0]) || {};
      document.getElementById('jobTitle').textContent = firstJob.job_title || 'Frontend Developer';
      document.getElementById('phone').textContent = data.phone_number || '';
      document.getElementById('email').textContent = data.email || '';
      document.getElementById('linkedin').textContent = data.linkedin_url || '';
      document.getElementById('summary').textContent = data.summary || '';

      const skillsWrap = document.getElementById('skillsList');
      skillsWrap.innerHTML = '';
      (data.skills || []).slice(0, 10).forEach(s => {
        const el = document.createElement('span');
        el.className = 'skill-chip';
        el.textContent = s;
        skillsWrap.appendChild(el);
      });

      const eduWrap = document.getElementById('educationList');
      eduWrap.innerHTML = '';
      (data.education || []).forEach(edu => {
        const div = document.createElement('div');
        div.className = 'edu-item';
        div.innerHTML = `<div class="edu-institution">${edu.institution || ''}</div>
                         <div class="edu-degree">${edu.degree || ''} — ${edu.field_of_study || ''}</div>
                         <div class="edu-year">Lulus: ${edu.graduation_year || ''}</div>`;
        eduWrap.appendChild(div);
      });

      const workWrap = document.getElementById('workList');
      workWrap.innerHTML = '';
      (data.work_experience || []).forEach(job => {
        const jobEl = document.createElement('div');
        jobEl.className = 'job-item';
        const points = (job.description_points || []).map(p => `<li>${p}</li>`).join('');
        jobEl.innerHTML = `
          <div class="job-head">
            <div class="job-company">${job.company || ''}</div>
            <div class="job-dates">${job.start_date || ''} - ${job.end_date || ''}</div>
          </div>
          <h3 class="job-title">${job.job_title || ''}</h3>
          <ul class="job-points">${points}</ul>
        `;
        workWrap.appendChild(jobEl);
      });
    })(cvData);
  </script>
</body>
</html>

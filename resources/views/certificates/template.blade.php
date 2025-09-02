<!DOCTYPE html>
<html lang="id">

<head>
  <meta charset="UTF-8" />
  <title>Sertifikat Magang</title>
  <style>
    @page {
      size: A4 landscape;
      margin: 0;
    }

    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }

    body {
      font-family: Arial, sans-serif;
      background: white;
      width: 297mm;
      height: 210mm;
      margin: 0;
      padding: 0;
      display: block;
    }

    .certificate-container {
      width: 297mm;
      height: 210mm;
      background: white;
      display: block;
      position: relative;
    }

    /* HEADER */
    .certificate-header {
      background: #002D37;
      padding: 15px 30px;
      display: table;
      width: 100%;
      position: relative;
      height: 60px;
      table-layout: fixed;
    }

    .header-left {
      display: table-cell;
      vertical-align: middle;
      width: calc(100% - 80px);
    }

    .certificate-logos {
      display: inline-block;
      vertical-align: middle;
    }

    .logo {
      width: 60px;
      height: 60px;
      border-radius: 50%;
      background: white;
      display: inline-block;
      text-align: center;
      vertical-align: middle;
      margin-right: 12px;
      border: 2px solid white;
      position: relative;
      overflow: hidden;
    }

    .logo img {
      width: 54px;
      height: 54px;
      border-radius: 50%;
      object-fit: cover;
      position: absolute;
      top: 3px;
      left: 3px;
    }

    .certificate-title-header {
      color: white;
      font-size: 36px;
      font-weight: bold;
      text-shadow: 1px 1px 2px rgba(0, 0, 0, 0.5);
      letter-spacing: 1px;
      margin-left: 25px;
      display: inline-block;
      vertical-align: middle;
    }

    .header-right {
      display: table-cell;
      vertical-align: middle;
      width: 80px;
      text-align: right;
    }

    .header-qr {
      width: 55px;
      height: 55px;
      background: white;
      border-radius: 6px;
      padding: 3px;
      display: inline-block;
    }

    .header-qr svg {
      width: 49px;
      height: 49px;
    }

    /* BODY */
    .certificate-body {
      padding: 25px 40px;
      background: white;
      height: calc(210mm - 170px);
      position: relative;
    }

    .certificate-title {
      text-align: center;
      margin-bottom: 15px;
    }

    .certificate-title h3 {
      font-size: 36px;
      color: #002D37;
      margin-bottom: 8px;
      font-weight: bold;
    }

    .title-divider {
      width: 150px;
      height: 3px;
      background: #00809D;
      margin: 8px auto;
    }

    .certificate-title p {
      font-size: 15px;
      color: #555;
      font-style: italic;
      max-width: 700px;
      margin: 8px auto;
      line-height: 1.3;
    }

    .period-info {
      text-align: center;
      background: #f0f9fb;
      padding: 6px 12px;
      margin: 12px auto;
      border-radius: 4px;
      border: 1px dashed #00809D;
      font-size: 13px;
      font-weight: 500;
      max-width: 400px;
    }

    .period-info span {
      color: #00809D;
      font-weight: bold;
    }

    .recipient-section {
      text-align: center;
      margin: 15px 0;
    }

    .recipient-name {
      font-size: 28px;
      font-weight: bold;
      color: #002D37;
      border-top: 2px solid #00809D;
      border-bottom: 2px solid #00809D;
      display: inline-block;
      padding: 6px 25px;
      margin: 0 auto;
    }

    .appreciation {
      font-size: 14px;
      color: #444;
      max-width: 750px;
      margin: 12px auto;
      line-height: 1.4;
      text-align: center;
    }

    /* SIGNATURE */
    .signature-section {
      display: table;
      width: 100%;
      margin-top: 15px;
      table-layout: fixed;
    }

    .signature {
      display: table-cell;
      width: 50%;
      text-align: center;
      vertical-align: top;
      padding: 0 20px;
      border: 1px solid black;

    }

    .left-signature .date {
      margin-bottom: 5px;
      font-weight: 500;
      color: #002D37;
      font-size: 12px;
    }

    .signature-image {
      height: 140px;
      margin-bottom: 4px;
      display: block;
      text-align: center;
    }

    .signature-image img {
      max-height: 140px;
      max-width: 240px;
      object-fit: contain;
      display: block;
      margin: 0 auto;
    }

    .right-signature .signature-image {
      height: 120px;
      margin-bottom: 8px;
    }

    .signature h4 {
      font-size: 16px;
      color: #002D37;
      margin-top: 3px;
      font-weight: bold;
      text-align: center;
    }

    .signature p {
      font-size: 13px;
      color: #555;
      margin: 1px 0;
      line-height: 1.2;
      text-align: center;
      word-wrap: break-word;
      overflow-wrap: break-word;
    }

    /* FOOTER */
    .footer {
      background: #002D37;
      color: white;
      text-align: center;
      padding: 12px 15px;
      font-size: 11px;
      position: absolute;
      bottom: 0;
      left: 0;
      right: 0;
      height: 40px;
    }

    .footer strong {
      font-weight: bold;
    }

    /* Responsif untuk print */
    @media print {
      body {
        margin: 0;
        padding: 0;
      }

      .certificate-container {
        page-break-inside: avoid;
      }
    }
  </style>
</head>

<body>
  <div class="certificate-container">

    <div class="certificate-header">
      <div class="header-left">
        <div class="certificate-logos">
          <div class="logo">
            <img src="img/maker.png" alt="Logo Prakerin" />
          </div>
          <div class="logo">
            <img src="img/ginyard.webp" alt="Logo Perusahaan" />
          </div>
        </div>
        <div class="certificate-title-header">Sertifikat Magang</div>
      </div>

      <div class="header-right">
        <div class="header-qr">
          <img src="{{ $qrPath }}" alt="QR Code" style="width: 100%; object-fit: contain;">
        </div>
      </div>
    </div>

    <div class="certificate-body">
      <div class="certificate-title">
        <h3>Diberikan Kepada</h3>
        <div class="title-divider"></div>
        <p>
          Diberikan kepada individu yang telah menyelesaikan program magang
          di <strong>{{ $certificate->internship->internshipApplication->jobOpening->company->name }}</strong> dengan
          dedikasi dan prestasi luar biasa
        </p>
      </div>

      <div class="period-info">
        Periode Magang: <span>{{ \Carbon\Carbon::parse($certificate->internship->start_date)
  ->locale('id')
  ->translatedFormat('d F Y') }}</span> - <span>{{ 
                    \Carbon\Carbon::parse($certificate->internship->end_date)
    ->locale('id')
    ->translatedFormat('d F Y') }}</span>
      </div>

      <div class="recipient-section">
        <div class="recipient-name">
          {{ $certificate->internship->internshipApplication->curriculumVitae->student->name }}
        </div>
      </div>

      <div class="appreciation">
        Atas dedikasi, kerja keras, dan kontribusi berharga selama program magang
        berlangsung di
        <strong>{{ $certificate->internship->internshipApplication->jobOpening->company->name }}</strong>. Telah
        menunjukkan
        kemampuan teknis yang luar biasa, semangat belajar yang tinggi, serta
        integritas dalam menyelesaikan setiap tugas yang diberikan.
      </div>

      <div class="signature-section">

        <div class="signature left-signature">
          <h4>{{ \Carbon\Carbon::parse($certificate->updated_at)->locale('id')->translatedFormat('d F Y') }}</h4>

          <div class="signature-image">
            <img src="img/ttdmaker.png" alt="Tanda Tangan Prakerin" />
          </div>
          <h4>Nama</h4>
          <p>Jabatan</p>
          <p>PrakerinID</p>
        </div>

        <div class="signature right-signature">
          <div class="signature-image">

          </div>
          <h4>Nama</h4>
          <p>Jabatan</p>
          <p>{{ $certificate->internship->internshipApplication->jobOpening->company->name }}</p>
        </div>
      </div>
    </div>

    <div class="footer">
      Sertifikat ini diterbitkan oleh <strong>Prakerin.id</strong> | Solusi magang terbaik
      untuk siswa dan perusahaan | Validasi kode: <strong>{{ $certificate->id }}</strong>
    </div>
  </div>
</body>

</html>
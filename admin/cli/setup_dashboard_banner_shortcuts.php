<?php
define('CLI_SCRIPT', true);
@error_reporting(E_ALL & ~E_WARNING & ~E_NOTICE);
require(__DIR__ . '/../../config.php');

mtrace("==================================================");
mtrace("🚀 MEMASANG DASHBOARD HEADER BANNER, SHORTCUTS, & RUNNING TEXT");
mtrace("==================================================");

$combined_html = <<<HTML
<!-- USH INTERNATIONAL MULTI-LANGUAGE SWITCHER & DASHBOARD ENHANCEMENT WIDGET -->
<style>
  /* 1. Multi-Language Switcher */
  .ush-lang-bar {
    position: fixed;
    top: 12px;
    right: 120px;
    z-index: 99999;
    display: flex;
    align-items: center;
    gap: 6px;
    background: rgba(255, 255, 255, 0.92);
    backdrop-filter: blur(10px);
    -webkit-backdrop-filter: blur(10px);
    border: 1px solid rgba(0, 51, 153, 0.15);
    padding: 4px 10px;
    border-radius: 30px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
    font-size: 13px;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
  }
  .ush-lang-bar:hover {
    box-shadow: 0 6px 25px rgba(0, 51, 153, 0.18);
    transform: translateY(-1px);
    background: #ffffff;
  }
  .ush-lang-label {
    font-weight: 700;
    color: #002B7F;
    font-size: 11px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-right: 4px;
    display: flex;
    align-items: center;
    gap: 4px;
  }
  .ush-lang-btn {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 4px 10px;
    border-radius: 20px;
    text-decoration: none !important;
    color: #334155;
    font-weight: 600;
    font-size: 12px;
    transition: all 0.2s ease;
    border: 1px solid transparent;
  }
  .ush-lang-btn:hover {
    background: #f1f5f9;
    color: #0f172a;
  }
  .ush-lang-btn.active {
    background: #002B7F;
    color: #ffffff !important;
    box-shadow: 0 2px 8px rgba(0, 43, 127, 0.3);
  }
  .ush-flag { font-size: 14px; line-height: 1; }

  /* 2. Dashboard Custom Banner Styling */
  .ush-dash-header {
    background: linear-gradient(135deg, #002B7F 0%, #1E50BC 50%, #0284C7 100%);
    border-radius: 16px;
    padding: 24px 28px;
    color: #ffffff;
    margin-bottom: 20px;
    box-shadow: 0 10px 30px rgba(0, 43, 127, 0.25);
    position: relative;
    overflow: hidden;
  }
  .ush-dash-header::before {
    content: '';
    position: absolute;
    top: -50%;
    right: -20%;
    width: 350px;
    height: 350px;
    background: radial-gradient(circle, rgba(255,255,255,0.12) 0%, rgba(255,255,255,0) 70%);
    border-radius: 50%;
    pointer-events: none;
  }
  .ush-dash-title {
    font-size: 22px;
    font-weight: 800;
    margin: 0 0 6px 0;
    color: #ffffff;
    display: flex;
    align-items: center;
    gap: 8px;
  }
  .ush-dash-subtitle {
    font-size: 13.5px;
    opacity: 0.92;
    margin: 0 0 18px 0;
    line-height: 1.5;
  }
  .ush-stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
    gap: 12px;
    margin-top: 16px;
  }
  .ush-stat-card {
    background: rgba(255, 255, 255, 0.15);
    backdrop-filter: blur(12px);
    -webkit-backdrop-filter: blur(12px);
    border: 1px solid rgba(255, 255, 255, 0.25);
    border-radius: 12px;
    padding: 12px 14px;
    display: flex;
    align-items: center;
    gap: 12px;
  }
  .ush-stat-icon {
    font-size: 22px;
    background: rgba(255, 255, 255, 0.2);
    width: 42px;
    height: 42px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 10px;
  }
  .ush-stat-val { font-size: 16px; font-weight: 700; color: #ffffff; line-height: 1.2; }
  .ush-stat-lbl { font-size: 11px; opacity: 0.85; text-transform: uppercase; letter-spacing: 0.3px; }

  /* 3. Shortcuts Bar */
  .ush-shortcuts-bar {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    margin-bottom: 20px;
  }
  .ush-shortcut-btn {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    background: #ffffff;
    border: 1px solid #e2e8f0;
    padding: 9px 16px;
    border-radius: 30px;
    color: #1e293b !important;
    font-weight: 600;
    font-size: 12.5px;
    text-decoration: none !important;
    box-shadow: 0 2px 5px rgba(0,0,0,0.04);
    transition: all 0.2s ease;
  }
  .ush-shortcut-btn:hover {
    border-color: #002B7F;
    color: #002B7F !important;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,43,127,0.12);
  }

  /* 4. News Ticker */
  .ush-news-ticker {
    background: #fffbe6;
    border: 1px solid #ffe58f;
    border-radius: 10px;
    padding: 8px 16px;
    margin-bottom: 18px;
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 12.5px;
    color: #873800;
  }
  .ush-news-badge {
    background: #faad14;
    color: #ffffff;
    font-weight: 700;
    font-size: 10.5px;
    padding: 2px 8px;
    border-radius: 12px;
    text-transform: uppercase;
    white-space: nowrap;
  }

  @media (max-width: 768px) {
    .ush-lang-bar { top: auto; bottom: 15px; right: 15px; }
  }
</style>

<!-- LANG SWITCHER -->
<div class="ush-lang-bar" id="ushLangSwitcher">
  <span class="ush-lang-label">🌐 Global USH:</span>
  <a href="javascript:void(0);" onclick="ushSwitchLang('en')" class="ush-lang-btn" id="langBtnEN">
    <span class="ush-flag">🇬🇧</span> English
  </a>
  <a href="javascript:void(0);" onclick="ushSwitchLang('id')" class="ush-lang-btn" id="langBtnID">
    <span class="ush-flag">🇮🇩</span> Indonesia
  </a>
  <a href="javascript:void(0);" onclick="ushSwitchLang('zh_cn')" class="ush-lang-btn" id="langBtnZH">
    <span class="ush-flag">🇨🇳</span> 中文 (Mandarin)
  </a>
</div>

<script>
  function ushSwitchLang(langCode) {
    const url = new URL(window.location.href);
    url.searchParams.set('lang', langCode);
    window.location.href = url.toString();
  }

  document.addEventListener('DOMContentLoaded', function() {
    // 1. Language switcher active state
    const urlParams = new URLSearchParams(window.location.search);
    const currentLang = urlParams.get('lang') || 'en';
    document.querySelectorAll('.ush-lang-btn').forEach(btn => btn.classList.remove('active'));
    if (currentLang === 'id') {
      document.getElementById('langBtnID')?.classList.add('active');
    } else if (currentLang.includes('zh')) {
      document.getElementById('langBtnZH')?.classList.add('active');
    } else {
      document.getElementById('langBtnEN')?.classList.add('active');
    }

    // 2. Inject Dashboard Header & Shortcuts on /my/ page
    const isDashboard = window.location.pathname.includes('/my/') || window.location.pathname.includes('/my/index.php');
    if (isDashboard) {
      const targetContainer = document.querySelector('#maincontent') || document.querySelector('.main-content') || document.querySelector('#region-main');
      if (targetContainer && !document.getElementById('ushDashBanner')) {
        const userName = document.querySelector('.userbutton .username')?.textContent || 'Sivitas Akademika USH';
        
        const bannerHTML = `
          <div class="ush-dash-header" id="ushDashBanner">
            <h2 class="ush-dash-title">🎓 Selamat Datang Kembali, ${userName}! 👋</h2>
            <p class="ush-dash-subtitle">Selamat belajar di Portal Pembelajaran Digital Universitas Sugeng Hartono. Pantau materi perkuliahan, RPS resmi, dan pengumuman akademik Anda di sini.</p>
            <div class="ush-stats-grid">
              <div class="ush-stat-card">
                <div class="ush-stat-icon">📚</div>
                <div><div class="ush-stat-val">Terintegrasi</div><div class="ush-stat-lbl">SIAKAD Kampus</div></div>
              </div>
              <div class="ush-stat-card">
                <div class="ush-stat-icon">📄</div>
                <div><div class="ush-stat-val">RPS Resmi</div><div class="ush-stat-lbl">Digital Download</div></div>
              </div>
              <div class="ush-stat-card">
                <div class="ush-stat-icon">📜</div>
                <div><div class="ush-stat-val">Kurikulum</div><div class="ush-stat-lbl">OBE & KKNI</div></div>
              </div>
              <div class="ush-stat-card">
                <div class="ush-stat-icon">🌐</div>
                <div><div class="ush-stat-val">Multibahasa</div><div class="ush-stat-lbl">International USH</div></div>
              </div>
            </div>
          </div>

          <div class="ush-news-ticker">
            <span class="ush-news-badge">📢 INFOKOM USH</span>
            <marquee behavior="scroll" direction="left" scrollamount="5">
              Selamat datang di Semester Ganjil TA 2026/2027 Universitas Sugeng Hartono! • Jadwal perkuliahan terintegrasi dengan SIAKAD • Pengunduhan dokumen RPS resmi dapat diakses di bagian atas setiap mata kuliah.
            </marquee>
          </div>

          <div class="ush-shortcuts-bar">
            <a href="https://siakad.sugenghartono.ac.id" target="_blank" class="ush-shortcut-btn">🏛️ Portal SIAKAD USH</a>
            <a href="https://library.sugenghartono.ac.id" target="_blank" class="ush-shortcut-btn">📚 Perpustakaan Digital</a>
            <a href="http://localhost/moodle/my/courses.php" class="ush-shortcut-btn">🎓 Daftar Mata Kuliah</a>
            <a href="https://sugenghartono.ac.id/contact" target="_blank" class="ush-shortcut-btn">💬 Layanan & Helpdesk</a>
          </div>
        `;
        targetContainer.insertAdjacentHTML('afterbegin', bannerHTML);
      }
    }
  });
</script>
<!-- END USH WIDGET -->
HTML;

set_config('additionalhtmltopofbody', $combined_html);

rebuild_course_cache(0, true);
purge_all_caches();

mtrace("==================================================");
mtrace("🎉 SELESAI MEMASANG DASHBOARD HEADER, SHORTCUTS, & RUNNING TEXT!");
mtrace("==================================================");

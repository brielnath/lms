<?php
define('CLI_SCRIPT', true);
@error_reporting(E_ALL & ~E_WARNING & ~E_NOTICE);
require(__DIR__ . '/../../config.php');

mtrace("==================================================");
mtrace("🔧 FIXING NAVBAR OVERLAY & ENHANCING DOSEN DASHBOARD");
mtrace("==================================================");

// 1. Clean CSS & JS for Language Switcher (NO POSITION FIXED, INLINE IN NAVBAR)
$clean_suite_html = <<<HTML
<!-- CLEAN USH INTERNATIONAL SUITE (INLINE NAVBAR & DOSEN DASHBOARD) -->
<style>
  /* Fix Language Switcher - Inline in Top Bar, NEVER Overlap Navbar Elements */
  .ush-lang-inline {
    display: inline-flex !important;
    align-items: center !important;
    gap: 6px !important;
    background: rgba(255, 255, 255, 0.2) !important;
    border: 1px solid rgba(255, 255, 255, 0.4) !important;
    padding: 3px 8px !important;
    border-radius: 20px !important;
    margin-right: 15px !important;
    vertical-align: middle !important;
  }
  .ush-lang-inline a {
    color: #ffffff !important;
    font-size: 11.5px !important;
    font-weight: 600 !important;
    text-decoration: none !important;
    padding: 2px 6px !important;
    border-radius: 12px !important;
  }
  .ush-lang-inline a:hover, .ush-lang-inline a.active {
    background: #ffffff !important;
    color: #002B7F !important;
  }

  /* Dosen Executive Hero Banner */
  .ush-dosen-hero-container {
    background: linear-gradient(135deg, #0f172a 0%, #1e293b 50%, #0284c7 100%) !important;
    border-radius: 16px !important;
    padding: 24px 28px !important;
    color: #ffffff !important;
    margin: 15px 0 25px 0 !important;
    box-shadow: 0 10px 30px rgba(15, 23, 42, 0.25) !important;
    position: relative !important;
    border-left: 6px solid #38bdf8 !important;
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif !important;
  }

  .ush-dosen-badge {
    background: #0284c7 !important;
    color: #ffffff !important;
    font-size: 10.5px !important;
    font-weight: 800 !important;
    padding: 3px 10px !important;
    border-radius: 20px !important;
    text-transform: uppercase !important;
    letter-spacing: 0.5px !important;
    display: inline-block !important;
    margin-bottom: 8px !important;
  }

  .ush-dosen-title {
    font-size: 24px !important;
    font-weight: 800 !important;
    color: #ffffff !important;
    margin: 0 0 6px 0 !important;
  }

  .ush-dosen-desc {
    font-size: 13px !important;
    color: #94a3b8 !important;
    margin: 0 0 16px 0 !important;
  }

  .ush-dosen-grid {
    display: grid !important;
    grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)) !important;
    gap: 10px !important;
  }

  .ush-dosen-card {
    background: rgba(255, 255, 255, 0.08) !important;
    border: 1px solid rgba(255, 255, 255, 0.15) !important;
    border-radius: 10px !important;
    padding: 10px 12px !important;
    display: flex !important;
    align-items: center !important;
    gap: 10px !important;
  }

  .ush-dosen-card-icon {
    font-size: 20px !important;
    background: rgba(56, 189, 248, 0.2) !important;
    width: 38px !important;
    height: 38px !important;
    border-radius: 8px !important;
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
    color: #38bdf8 !important;
  }

  .ush-dosen-card-val { font-size: 14px !important; font-weight: 700 !important; color: #ffffff !important; }
  .ush-dosen-card-lbl { font-size: 10px !important; color: #94a3b8 !important; text-transform: uppercase !important; }

  .ush-dosen-tools {
    display: flex !important;
    flex-wrap: wrap !important;
    gap: 10px !important;
    margin-top: 16px !important;
  }

  .ush-dosen-btn {
    display: inline-flex !important;
    align-items: center !important;
    gap: 6px !important;
    background: #ffffff !important;
    border: 1px solid #cbd5e1 !important;
    padding: 8px 16px !important;
    border-radius: 20px !important;
    color: #0f172a !important;
    font-weight: 700 !important;
    font-size: 12px !important;
    text-decoration: none !important;
    transition: all 0.2s ease !important;
  }

  .ush-dosen-btn:hover {
    background: #0284c7 !important;
    color: #ffffff !important;
    border-color: #0284c7 !important;
  }
</style>

<script>
  function ushSwitchLang(langCode) {
    const url = new URL(window.location.href);
    url.searchParams.set('lang', langCode);
    window.location.href = url.toString();
  }

  function ushApplyDosenTheme() {
    const isDashboard = window.location.pathname.includes('/my/') || window.location.pathname.includes('/my/index.php');
    if (!isDashboard || document.getElementById('ushDosenHeroContainer')) return;

    // Detect user menu
    const userMenu = document.querySelector('.userbutton .username') || document.querySelector('.usermenu') || document.querySelector('.usertext');
    const userName = userMenu?.textContent?.trim() || '';

    // Detect if user is Dosen (Yulaikha or titles)
    const isDosen = userName.toLowerCase().includes('yulaikha') ||
                    userName.toLowerCase().includes('dosen') ||
                    userName.toLowerCase().includes('dr.') || 
                    userName.toLowerCase().includes('m.kom') || 
                    userName.toLowerCase().includes('s.e') || 
                    userName.toLowerCase().includes('m.m') ||
                    userName.toLowerCase().includes('m.sc') ||
                    userName.toLowerCase().includes('m.gz') ||
                    userName.toLowerCase().includes('m.h') ||
                    userName.toLowerCase().includes('ph.d');

    if (isDosen) {
      document.body.classList.add('is-dosen-user');

      // Find place to insert hero banner right at top of dashboard
      const targetArea = document.querySelector('.dashboard-card-deck') || 
                         document.querySelector('#region-main') || 
                         document.querySelector('[role="main"]') ||
                         document.querySelector('#page-content');

      if (targetArea) {
        const heroHTML = `
          <div class="ush-dosen-hero-container" id="ushDosenHeroContainer">
            <span class="ush-dosen-badge">👨‍🏫 PORTAL DOSEN PENGAMPU USH</span>
            <h1 class="ush-dosen-title">Selamat Datang, Ibu ${userName}! 👋</h1>
            <p class="ush-dosen-desc">Workspace Pengajaran Digital Universitas Sugeng Hartono. Kelola jadwal perkuliahan, evaluasi tugas mahasiswa, serta rekapitulasi presensi terintegrasi SIAKAD.</p>
            
            <div class="ush-dosen-grid">
              <div class="ush-dosen-card">
                <div class="ush-dosen-card-icon">📚</div>
                <div>
                  <div class="ush-dosen-card-val">Mengampu Kelas</div>
                  <div class="ush-dosen-card-lbl">Terverifikasi SIAKAD</div>
                </div>
              </div>
              <div class="ush-dosen-card">
                <div class="ush-dosen-card-icon">📝</div>
                <div>
                  <div class="ush-dosen-card-val">Input Nilai & Kuis</div>
                  <div class="ush-dosen-card-lbl">Fitur Dosen</div>
                </div>
              </div>
              <div class="ush-dosen-card">
                <div class="ush-dosen-card-icon">📄</div>
                <div>
                  <div class="ush-dosen-card-val">Dokumen RPS</div>
                  <div class="ush-dosen-card-lbl">Resmi Kampus</div>
                </div>
              </div>
              <div class="ush-dosen-card">
                <div class="ush-dosen-card-icon">📊</div>
                <div>
                  <div class="ush-dosen-card-val">Kurikulum OBE</div>
                  <div class="ush-dosen-card-lbl">Standar USH</div>
                </div>
              </div>
            </div>

            <div class="ush-dosen-tools">
              <a href="https://siakad.sugenghartono.ac.id" target="_blank" class="ush-dosen-btn">🏛️ Input Nilai SIAKAD Dosen</a>
              <a href="http://localhost/moodle/my/courses.php" class="ush-dosen-btn">📚 Kelas Pengajaran Dosen</a>
              <a href="https://library.sugenghartono.ac.id" target="_blank" class="ush-dosen-btn">📖 E-Library & Jurnal Riset</a>
              <a href="https://sugenghartono.ac.id/contact" target="_blank" class="ush-dosen-btn">💬 Support Dosen</a>
            </div>
          </div>
        `;

        targetArea.insertAdjacentHTML('beforebegin', heroHTML);

        // Customize section titles for Dosen
        document.querySelectorAll('h1, h2, h3, h4, h5').forEach(h => {
          const txt = h.textContent.trim();
          if ((txt.includes('Hi,') || txt.includes('Welcome,')) && !h.classList.contains('ush-dosen-title')) {
            h.style.display = 'none'; // Hide small simple hi text
          }
          if (txt === 'Course overview') {
            h.textContent = '📚 Kelas Pengajaran Dosen Anda (Teaching Schedule)';
            h.style.fontSize = '20px';
            h.style.fontWeight = '800';
            h.style.color = '#0f172a';
          }
        });
      }
    }

    // Insert inline language switcher into top bar address-head if not exists
    const addressHead = document.querySelector('.address-head');
    if (addressHead && !document.getElementById('ushInlineLangBar')) {
      const langHTML = `
        <span class="ush-lang-inline" id="ushInlineLangBar">
          <span style="color:#ffffff; font-size:11px; font-weight:700; margin-right:4px;">🌐 GLOBAL:</span>
          <a href="javascript:void(0);" onclick="ushSwitchLang('en')">🇬🇧 EN</a>
          <a href="javascript:void(0);" onclick="ushSwitchLang('id')">🇮🇩 ID</a>
          <a href="javascript:void(0);" onclick="ushSwitchLang('zh_cn')">🇨🇳 中文</a>
        </span>
      `;
      addressHead.insertAdjacentHTML('beforeend', langHTML);
    }
  }

  document.addEventListener('DOMContentLoaded', ushApplyDosenTheme);
  window.addEventListener('load', ushApplyDosenTheme);
  setTimeout(ushApplyDosenTheme, 200);
  setTimeout(ushApplyDosenTheme, 800);
</script>
<!-- END CLEAN USH SUITE -->
HTML;

set_config('additionalhtmltopofbody', $clean_suite_html);

rebuild_course_cache(0, true);
purge_all_caches();

mtrace("==================================================");
mtrace("🎉 SELESAI MEMPERBAIKI INLINE NAVBAR & DASHBOARD DOSEN!");
mtrace("==================================================");

<?php
define('CLI_SCRIPT', true);
@error_reporting(E_ALL & ~E_WARNING & ~E_NOTICE);
require(__DIR__ . '/../../config.php');

mtrace("==================================================");
mtrace("🚀 MEMASANG TEMA DASHBOARD EXCLUSIVE UNTUK DOSEN");
mtrace("==================================================");

$dosen_theme_script = <<<HTML
<!-- EXECUTIVE DOSEN DASHBOARD THEME OVERRIDE -->
<style>
  /* 1. Dosen Executive Theme Styling */
  body.is-dosen-user #page {
    background: #f8fafc !important;
  }
  
  body.is-dosen-user .ush-dosen-hero {
    background: linear-gradient(135deg, #0f172a 0%, #1e293b 50%, #0369a1 100%);
    border-radius: 16px;
    padding: 28px 32px;
    color: #ffffff;
    margin: 10px 0 24px 0;
    box-shadow: 0 12px 35px rgba(15, 23, 42, 0.25);
    position: relative;
    overflow: hidden;
    border: 1px solid rgba(255, 255, 255, 0.1);
  }

  body.is-dosen-user .ush-dosen-hero::after {
    content: '👨‍🏫';
    position: absolute;
    right: 20px;
    bottom: -10px;
    font-size: 110px;
    opacity: 0.12;
    pointer-events: none;
  }

  body.is-dosen-user .ush-dosen-badge {
    background: linear-gradient(90deg, #0284c7 0%, #38bdf8 100%);
    color: #ffffff;
    font-size: 11px;
    font-weight: 800;
    padding: 4px 14px;
    border-radius: 20px;
    text-transform: uppercase;
    letter-spacing: 0.8px;
    display: inline-block;
    margin-bottom: 12px;
    box-shadow: 0 4px 12px rgba(2, 132, 199, 0.3);
  }

  body.is-dosen-user .ush-dosen-name {
    font-size: 26px;
    font-weight: 800;
    color: #ffffff;
    margin: 0 0 8px 0;
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
  }

  body.is-dosen-user .ush-dosen-desc {
    font-size: 14px;
    color: #94a3b8;
    margin: 0 0 20px 0;
    line-height: 1.6;
    max-width: 750px;
  }

  body.is-dosen-user .ush-dosen-stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
    gap: 14px;
    margin-top: 20px;
  }

  body.is-dosen-user .ush-dosen-stat-box {
    background: rgba(255, 255, 255, 0.08);
    backdrop-filter: blur(10px);
    -webkit-backdrop-filter: blur(10px);
    border: 1px solid rgba(255, 255, 255, 0.15);
    border-radius: 12px;
    padding: 14px 16px;
    display: flex;
    align-items: center;
    gap: 12px;
  }

  body.is-dosen-user .ush-dosen-stat-icon {
    font-size: 24px;
    background: rgba(56, 189, 248, 0.2);
    width: 44px;
    height: 44px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #38bdf8;
  }

  body.is-dosen-user .ush-dosen-stat-num {
    font-size: 16px;
    font-weight: 700;
    color: #ffffff;
    line-height: 1.2;
  }

  body.is-dosen-user .ush-dosen-stat-sub {
    font-size: 11px;
    color: #94a3b8;
    text-transform: uppercase;
    letter-spacing: 0.5px;
  }

  /* Toolbar Tombol Dosen */
  body.is-dosen-user .ush-dosen-tools {
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
    margin-bottom: 24px;
  }

  body.is-dosen-user .ush-dosen-tool-btn {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    background: #ffffff;
    border: 1px solid #cbd5e1;
    padding: 10px 18px;
    border-radius: 30px;
    color: #0f172a !important;
    font-weight: 700;
    font-size: 13px;
    text-decoration: none !important;
    box-shadow: 0 2px 6px rgba(0,0,0,0.04);
    transition: all 0.2s ease;
  }

  body.is-dosen-user .ush-dosen-tool-btn:hover {
    background: #0284c7;
    border-color: #0284c7;
    color: #ffffff !important;
    transform: translateY(-2px);
    box-shadow: 0 6px 18px rgba(2, 132, 199, 0.25);
  }

  /* Modifikasi Judul Kelas Dosen */
  body.is-dosen-user .block-myoverview h5,
  body.is-dosen-user [data-region="filter"] h5 {
    font-size: 18px !important;
    font-weight: 800 !important;
    color: #0f172a !important;
  }
</style>

<script>
  function ushTransformDosenDashboard() {
    const isDashboard = window.location.pathname.includes('/my/') || window.location.pathname.includes('/my/index.php');
    if (!isDashboard) return;

    // Detect if logged in user is a Dosen
    const userMenu = document.querySelector('.userbutton .username') || document.querySelector('.usermenu');
    const userName = userMenu?.textContent?.trim() || '';

    // Dosen check logic
    const isDosen = userName.toLowerCase().includes('dosen') || 
                    userName.toLowerCase().includes('yulaikha') ||
                    userName.toLowerCase().includes('dr.') || 
                    userName.toLowerCase().includes('m.kom') || 
                    userName.toLowerCase().includes('s.e') || 
                    userName.toLowerCase().includes('m.m') ||
                    userName.toLowerCase().includes('m.sc') ||
                    userName.toLowerCase().includes('m.gz');

    if (isDosen) {
      document.body.classList.add('is-dosen-user');

      const targetArea = document.querySelector('#region-main') || 
                         document.querySelector('[role="main"]') || 
                         document.querySelector('#page-content');

      if (targetArea && !document.getElementById('ushDosenHero')) {
        // Build Lecturer Executive Banner
        const heroHTML = `
          <div class="ush-dosen-hero" id="ushDosenHero">
            <span class="ush-dosen-badge">👨‍🏫 PORTAL DOSEN PENGAMPU USH</span>
            <h1 class="ush-dosen-name">Selamat Datang, Bapak/Ibu ${userName}! 👋</h1>
            <p class="ush-dosen-desc">Workspace Pengajaran Akademik Universitas Sugeng Hartono. Kelola jadwal perkuliahan, kuis, evaluasi tugas mahasiswa, serta rekapitulasi presensi terintegrasi SIAKAD.</p>
            
            <div class="ush-dosen-stats">
              <div class="ush-dosen-stat-box">
                <div class="ush-dosen-stat-icon">📚</div>
                <div>
                  <div class="ush-dosen-stat-num">Mengampu Kelas</div>
                  <div class="ush-dosen-stat-sub">Terverifikasi SIAKAD</div>
                </div>
              </div>
              <div class="ush-dosen-stat-box">
                <div class="ush-dosen-stat-icon">📝</div>
                <div>
                  <div class="ush-dosen-stat-num">Input Nilai & Kuis</div>
                  <div class="ush-dosen-stat-sub">Fitur Dosen</div>
                </div>
              </div>
              <div class="ush-dosen-stat-box">
                <div class="ush-dosen-stat-icon">📄</div>
                <div>
                  <div class="ush-dosen-stat-num">Dokumen RPS</div>
                  <div class="ush-dosen-stat-sub">Resmi Kampus</div>
                </div>
              </div>
              <div class="ush-dosen-stat-box">
                <div class="ush-dosen-stat-icon">📊</div>
                <div>
                  <div class="ush-dosen-stat-num">Kurikulum OBE</div>
                  <div class="ush-dosen-stat-sub">Standar USH</div>
                </div>
              </div>
            </div>
          </div>

          <div class="ush-dosen-tools">
            <a href="https://siakad.sugenghartono.ac.id" target="_blank" class="ush-dosen-tool-btn">🏛️ Input Nilai SIAKAD</a>
            <a href="http://localhost/moodle/my/courses.php" class="ush-dosen-tool-btn">📚 Daftar Mata Kuliah Mengajar</a>
            <a href="https://library.sugenghartono.ac.id" target="_blank" class="ush-dosen-tool-btn">📖 E-Library & Jurnal Riset</a>
            <a href="https://sugenghartono.ac.id/contact" target="_blank" class="ush-dosen-tool-btn">💬 Support Dosen</a>
          </div>
        `;

        targetArea.insertAdjacentHTML('afterbegin', heroHTML);

        // Customize header text "Welcome, Yulaikha!" -> Dosen Workspace
        const welcomeHeaders = document.querySelectorAll('h1, h2, h3');
        welcomeHeaders.forEach(h => {
          if (h.textContent.includes('Welcome,') && !h.classList.contains('ush-dosen-name')) {
            h.style.display = 'none'; // Hide duplicate simple welcome text
          }
          if (h.textContent.trim() === 'Course overview') {
            h.textContent = '📚 Mata Kuliah Pengajaran Anda (Teaching Schedule)';
            h.style.fontSize = '20px';
            h.style.fontWeight = '800';
            h.style.color = '#0f172a';
          }
        });
      }
    }
  }

  document.addEventListener('DOMContentLoaded', ushTransformDosenDashboard);
  window.addEventListener('load', ushTransformDosenDashboard);
  setTimeout(ushTransformDosenDashboard, 300);
  setTimeout(ushTransformDosenDashboard, 1000);
</script>
<!-- END DOSEN DASHBOARD THEME OVERRIDE -->
HTML;

set_config('additionalhtmltopofbody', $dosen_theme_script);

rebuild_course_cache(0, true);
purge_all_caches();

mtrace("==================================================");
mtrace("🎉 SELESAI MEMASANG TEMA DASHBOARD EXCLUSIVE DOSEN!");
mtrace("==================================================");

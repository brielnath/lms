<?php
define('CLI_SCRIPT', true);
@error_reporting(E_ALL & ~E_WARNING & ~E_NOTICE);
require(__DIR__ . '/../../config.php');

mtrace("==================================================");
mtrace("🚀 MEMPERBAIKI TARGET DOM DASHBOARD BANNER DOSEN & MAHASISWA");
mtrace("==================================================");

$role_dashboard_script = <<<HTML
<!-- ROLE-SPECIFIC DASHBOARD WIDGET (ROBUST TARGETING) -->
<style>
  .ush-dash-header-dosen {
    background: linear-gradient(135deg, #0f172a 0%, #1e293b 50%, #334155 100%);
    border-radius: 16px;
    padding: 24px 28px;
    color: #ffffff;
    margin: 15px 0 20px 0;
    box-shadow: 0 10px 30px rgba(15, 23, 42, 0.3);
    position: relative;
    overflow: hidden;
    border-left: 6px solid #38bdf8;
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
  }
  .ush-dash-header-dosen::before {
    content: '';
    position: absolute;
    top: -50%;
    right: -20%;
    width: 350px;
    height: 350px;
    background: radial-gradient(circle, rgba(56,189,248,0.15) 0%, rgba(255,255,255,0) 70%);
    border-radius: 50%;
    pointer-events: none;
  }
  .ush-badge-role {
    display: inline-block;
    background: rgba(56,189,248,0.2);
    border: 1px solid rgba(56,189,248,0.4);
    color: #38bdf8;
    font-size: 11px;
    font-weight: 700;
    padding: 4px 12px;
    border-radius: 20px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 8px;
  }
  .ush-dash-title {
    font-size: 22px;
    font-weight: 800;
    margin: 0 0 6px 0;
    color: #ffffff;
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
    background: rgba(255, 255, 255, 0.12);
    backdrop-filter: blur(12px);
    -webkit-backdrop-filter: blur(12px);
    border: 1px solid rgba(255, 255, 255, 0.2);
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
  .ush-stat-val { font-size: 15px; font-weight: 700; color: #ffffff; line-height: 1.2; }
  .ush-stat-lbl { font-size: 11px; opacity: 0.85; text-transform: uppercase; letter-spacing: 0.3px; }

  .ush-shortcuts-bar {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    margin-bottom: 20px;
  }
  .ush-shortcut-dosen-btn {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    background: #1e293b;
    border: 1px solid #334155;
    padding: 9px 16px;
    border-radius: 30px;
    color: #38bdf8 !important;
    font-weight: 600;
    font-size: 12.5px;
    text-decoration: none !important;
    box-shadow: 0 2px 5px rgba(0,0,0,0.2);
    transition: all 0.2s ease;
  }
  .ush-shortcut-dosen-btn:hover {
    background: #38bdf8;
    color: #0f172a !important;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(56,189,248,0.3);
  }
  .ush-news-ticker {
    background: #e0f2fe;
    border: 1px solid #7dd3fc;
    border-radius: 10px;
    padding: 8px 16px;
    margin-bottom: 18px;
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 12.5px;
    color: #0369a1;
  }
  .ush-news-badge {
    background: #0284c7;
    color: #ffffff;
    font-weight: 700;
    font-size: 10.5px;
    padding: 2px 8px;
    border-radius: 12px;
    text-transform: uppercase;
    white-space: nowrap;
  }
</style>

<script>
  function ushRenderDashboardHeader() {
    const isDashboard = window.location.pathname.includes('/my/') || window.location.pathname.includes('/my/index.php');
    if (!isDashboard || document.getElementById('ushRoleDashBanner')) return;

    // Multi-selector to find the main dashboard content area
    const targetContainer = document.querySelector('#region-main') || 
                            document.querySelector('[role="main"]') || 
                            document.querySelector('.dashboard-card-deck') ||
                            document.querySelector('#page-content') ||
                            document.querySelector('#maincontent')?.parentNode;

    if (targetContainer) {
      const userBtn = document.querySelector('.userbutton .username') || document.querySelector('.usermenu');
      const userName = userBtn?.textContent?.trim() || 'Dosen USH';
      
      const dosenBannerHTML = `
        <div class="ush-dash-header-dosen" id="ushRoleDashBanner">
          <span class="ush-badge-role">👨‍🏫 PORTAL DOSEN PENGAMPU USH</span>
          <h2 class="ush-dash-title">Selamat Datang, Bapak/Ibu ${userName}! 👋</h2>
          <p class="ush-dash-subtitle">Workspace Manajemen Pengajaran Digital Universitas Sugeng Hartono. Kelola materi perkuliahan, kuis, evaluasi tugas mahasiswa, serta dokumen RPS resmi secara terintegrasi.</p>
          <div class="ush-stats-grid">
            <div class="ush-stat-card">
              <div class="ush-stat-icon">🎓</div>
              <div><div class="ush-stat-val">Dosen Pengampu</div><div class="ush-stat-lbl">Terverifikasi SIAKAD</div></div>
            </div>
            <div class="ush-stat-card">
              <div class="ush-stat-icon">📥</div>
              <div><div class="ush-stat-val">Evaluasi Tugas</div><div class="ush-stat-lbl">Perlu Dinilai</div></div>
            </div>
            <div class="ush-stat-card">
              <div class="ush-stat-icon">📄</div>
              <div><div class="ush-stat-val">Dokumen RPS</div><div class="ush-stat-lbl">Terintegrasi Kampus</div></div>
            </div>
            <div class="ush-stat-card">
              <div class="ush-stat-icon">🌐</div>
              <div><div class="ush-stat-val">Standar OBE</div><div class="ush-stat-lbl">Kurikulum USH</div></div>
            </div>
          </div>
        </div>

        <div class="ush-news-ticker">
          <span class="ush-news-badge">📢 INFO DOSEN USH</span>
          <marquee behavior="scroll" direction="left" scrollamount="5">
            Portal Pengajaran Dosen USH • Input nilai kuis & presensi terintegrasi langsung dengan SIAKAD • Pastikan dokumen RPS di setiap mata kuliah telah diperbarui.
          </marquee>
        </div>

        <div class="ush-shortcuts-bar">
          <a href="https://siakad.sugenghartono.ac.id" target="_blank" class="ush-shortcut-dosen-btn">🏛️ Portal SIAKAD Dosen</a>
          <a href="http://localhost/moodle/my/courses.php" class="ush-shortcut-dosen-btn">📚 Daftar Mata Kuliah Mengajar</a>
          <a href="https://library.sugenghartono.ac.id" target="_blank" class="ush-shortcut-dosen-btn">📖 E-Library & Jurnal Riset</a>
          <a href="https://sugenghartono.ac.id/contact" target="_blank" class="ush-shortcut-dosen-btn">💬 Support & Helpdesk Dosen</a>
        </div>
      `;

      targetContainer.insertAdjacentHTML('afterbegin', dosenBannerHTML);
    }
  }

  document.addEventListener('DOMContentLoaded', ushRenderDashboardHeader);
  window.addEventListener('load', ushRenderDashboardHeader);
  setTimeout(ushRenderDashboardHeader, 500);
  setTimeout(ushRenderDashboardHeader, 1500);
</script>
<!-- END ROLE-SPECIFIC DASHBOARD WIDGET -->
HTML;

set_config('additionalhtmltopofbody', $role_dashboard_script);

rebuild_course_cache(0, true);
purge_all_caches();

mtrace("==================================================");
mtrace("🎉 SELESAI MEMPERBAIKI DOM DASHBOARD BANNER DOSEN!");
mtrace("==================================================");

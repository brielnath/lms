<?php
define('CLI_SCRIPT', true);
@error_reporting(E_ALL & ~E_WARNING & ~E_NOTICE);
require(__DIR__ . '/../../config.php');

mtrace("==================================================");
mtrace("🚀 MEMASANG DASHBOARD KHUSUS DOSEN & MAHASISWA");
mtrace("==================================================");

$role_dashboard_script = <<<HTML
<!-- ROLE-SPECIFIC DASHBOARD WIDGET (DOSEN VS MAHASISWA) -->
<style>
  .ush-dash-header-dosen {
    background: linear-gradient(135deg, #0f172a 0%, #1e293b 50%, #334155 100%);
    border-radius: 16px;
    padding: 24px 28px;
    color: #ffffff;
    margin-bottom: 20px;
    box-shadow: 0 10px 30px rgba(15, 23, 42, 0.3);
    position: relative;
    overflow: hidden;
    border-left: 6px solid #38bdf8;
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
    padding: 3px 10px;
    border-radius: 20px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 8px;
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
</style>

<script>
  document.addEventListener('DOMContentLoaded', function() {
    const isDashboard = window.location.pathname.includes('/my/') || window.location.pathname.includes('/my/index.php');
    if (isDashboard) {
      const targetContainer = document.querySelector('#maincontent') || document.querySelector('.main-content') || document.querySelector('#region-main');
      
      if (targetContainer && !document.getElementById('ushRoleDashBanner')) {
        // Detect if current user is Dosen (by checking user button text or role class)
        const userBtn = document.querySelector('.userbutton .username');
        const userName = userBtn?.textContent || 'Pengguna USH';
        
        // Check if user is Dosen
        const isDosen = userName.toLowerCase().includes('dosen') || 
                        userName.toLowerCase().includes('dr.') || 
                        userName.toLowerCase().includes('m.kom') || 
                        userName.toLowerCase().includes('s.e') || 
                        userName.toLowerCase().includes('m.m') ||
                        userName.toLowerCase().includes('m.sc') ||
                        document.body.classList.contains('role-teacher') ||
                        document.body.classList.contains('role-editingteacher');

        if (isDosen) {
          // Render DASHBOARD DOSEN (LECTURER WORKSPACE)
          const dosenBannerHTML = `
            <div class="ush-dash-header-dosen" id="ushRoleDashBanner">
              <span class="ush-badge-role">👨‍🏫 PORTAL DOSEN PENGAMPU USH</span>
              <h2 class="ush-dash-title" style="margin-top: 4px;">Selamat Datang, Bapak/Ibu ${userName}! 👋</h2>
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

            <div class="ush-news-ticker" style="background: #e0f2fe; border-color: #7dd3fc; color: #0369a1;">
              <span class="ush-news-badge" style="background: #0284c7;">📢 INFO DOSEN USH</span>
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
    }
  });
</script>
<!-- END ROLE-SPECIFIC DASHBOARD WIDGET -->
HTML;

$current_html = get_config('core', 'additionalhtmltopofbody');
if (strpos($current_html, 'ush-dash-header-dosen') === false) {
    set_config('additionalhtmltopofbody', $current_html . "\n" . $role_dashboard_script);
}

rebuild_course_cache(0, true);
purge_all_caches();

mtrace("✅ Dashboard Khusus Dosen & Mahasiswa Berhasil Dipasang!");
mtrace("==================================================");

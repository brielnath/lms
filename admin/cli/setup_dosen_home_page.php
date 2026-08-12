<?php
define('CLI_SCRIPT', true);
@error_reporting(E_ALL & ~E_WARNING & ~E_NOTICE);
require(__DIR__ . '/../../config.php');

mtrace("==================================================");
mtrace("🚀 MEMASANG HALAMAN HOME KHUSUS DOSEN USH");
mtrace("==================================================");

$dosen_home_script = <<<HTML
<!-- DOSEN SITE HOME OVERRIDE -->
<style>
  /* Styling khusus Home Dosen */
  body.is-dosen-user .ush-dosen-home-banner {
    background: linear-gradient(135deg, #0f172a 0%, #1e293b 50%, #0369a1 100%) !important;
    border-radius: 20px !important;
    padding: 35px 40px !important;
    color: #ffffff !important;
    margin: 20px 0 30px 0 !important;
    box-shadow: 0 15px 40px rgba(15, 23, 42, 0.3) !important;
    position: relative !important;
    overflow: hidden !important;
    border-left: 8px solid #38bdf8 !important;
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif !important;
  }
  .ush-dosen-home-banner::after {
    content: '🏛️';
    position: absolute;
    right: 30px;
    bottom: -20px;
    font-size: 140px;
    opacity: 0.1;
    pointer-events: none;
  }
  .ush-dosen-home-badge {
    background: #0284c7 !important;
    color: #ffffff !important;
    font-size: 11.5px !important;
    font-weight: 800 !important;
    padding: 4px 14px !important;
    border-radius: 20px !important;
    text-transform: uppercase !important;
    letter-spacing: 0.8px !important;
    display: inline-block !important;
    margin-bottom: 12px !important;
  }
  .ush-dosen-home-title {
    font-size: 28px !important;
    font-weight: 800 !important;
    color: #ffffff !important;
    margin: 0 0 10px 0 !important;
  }
  .ush-dosen-home-desc {
    font-size: 14.5px !important;
    color: #cbd5e1 !important;
    margin: 0 0 24px 0 !important;
    line-height: 1.6 !important;
    max-width: 800px !important;
  }
  .ush-dosen-home-grid {
    display: grid !important;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)) !important;
    gap: 14px !important;
  }
  .ush-dosen-home-card {
    background: rgba(255, 255, 255, 0.08) !important;
    border: 1px solid rgba(255, 255, 255, 0.16) !important;
    border-radius: 12px !important;
    padding: 14px 18px !important;
    display: flex !important;
    align-items: center !important;
    gap: 12px !important;
  }
  .ush-dosen-home-card-icon {
    font-size: 24px !important;
    background: rgba(56, 189, 248, 0.2) !important;
    width: 44px !important;
    height: 44px !important;
    border-radius: 10px !important;
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
    color: #38bdf8 !important;
  }
  .ush-dosen-home-card-val { font-size: 14.5px !important; font-weight: 700 !important; color: #ffffff !important; }
  .ush-dosen-home-card-lbl { font-size: 11px !important; color: #94a3b8 !important; text-transform: uppercase !important; }

  .ush-dosen-home-tools {
    display: flex !important;
    flex-wrap: wrap !important;
    gap: 12px !important;
    margin-top: 20px !important;
  }
  .ush-dosen-home-btn {
    display: inline-flex !important;
    align-items: center !important;
    gap: 8px !important;
    background: #ffffff !important;
    border: 1px solid #cbd5e1 !important;
    padding: 10px 20px !important;
    border-radius: 30px !important;
    color: #0f172a !important;
    font-weight: 700 !important;
    font-size: 13px !important;
    text-decoration: none !important;
    transition: all 0.2s ease !important;
    box-shadow: 0 4px 12px rgba(0,0,0,0.06) !important;
  }
  .ush-dosen-home-btn:hover {
    background: #0284c7 !important;
    color: #ffffff !important;
    border-color: #0284c7 !important;
    transform: translateY(-2px) !important;
  }
</style>

<script>
  function ushApplyDosenHomeTheme() {
    const url = window.location.href;
    const isHomePage = (window.location.pathname === '/' || window.location.pathname.endsWith('/index.php') || url.includes('/?redirect=0')) && !url.includes('/my/');

    // Detect user menu
    const userMenu = document.querySelector('.userbutton .username') || document.querySelector('.usermenu') || document.querySelector('.usertext');
    const userName = userMenu?.textContent?.trim() || '';

    // Check if user is Dosen
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

    if (isHomePage && isDosen && !document.getElementById('ushDosenHomeBanner')) {
      document.body.classList.add('is-dosen-user');

      const targetArea = document.querySelector('#region-main') || 
                         document.querySelector('[role="main"]') || 
                         document.querySelector('#page-content') ||
                         document.querySelector('.main-inner');

      if (targetArea) {
        const homeHTML = `
          <div class="ush-dosen-home-banner" id="ushDosenHomeBanner">
            <span class="ush-dosen-home-badge">🏛️ PORTAL AKADEMIK DOSEN USH</span>
            <h1 class="ush-dosen-home-title">Portal Pengajaran & Riset Dosen Universitas Sugeng Hartono</h1>
            <p class="ush-dosen-home-desc">Selamat datang di Beranda Utama Pembelajaran Dosen. Akses panduan kurikulum OBE, portal input nilai SIAKAD, repositori RPS digital, serta jurnal riset internasional secara terpadu.</p>

            <div class="ush-dosen-home-grid">
              <div class="ush-dosen-home-card">
                <div class="ush-dosen-home-card-icon">📚</div>
                <div>
                  <div class="ush-dosen-home-card-val">Kurikulum OBE</div>
                  <div class="ush-dosen-home-card-lbl">Standar Akademik USH</div>
                </div>
              </div>
              <div class="ush-dosen-home-card">
                <div class="ush-dosen-home-card-icon">🏛️</div>
                <div>
                  <div class="ush-dosen-home-card-val">SIAKAD Terintegrasi</div>
                  <div class="ush-dosen-home-card-lbl">Sync Real-Time</div>
                </div>
              </div>
              <div class="ush-dosen-home-card">
                <div class="ush-dosen-home-card-icon">📖</div>
                <div>
                  <div class="ush-dosen-home-card-val">Jurnal & Riset</div>
                  <div class="ush-dosen-home-card-lbl">SINTA / Scopus</div>
                </div>
              </div>
              <div class="ush-dosen-home-card">
                <div class="ush-dosen-home-card-icon">🌐</div>
                <div>
                  <div class="ush-dosen-home-card-val">Kampus Internasional</div>
                  <div class="ush-dosen-home-card-lbl">Multibahasa</div>
                </div>
              </div>
            </div>

            <div class="ush-dosen-home-tools">
              <a href="http://localhost/moodle/my/" class="ush-dosen-home-btn">📊 Masuk ke Dashboard Dosen</a>
              <a href="https://siakad.sugenghartono.ac.id" target="_blank" class="ush-dosen-home-btn">🏛️ Portal SIAKAD Dosen</a>
              <a href="https://library.sugenghartono.ac.id" target="_blank" class="ush-dosen-home-btn">📖 Perpustakaan & E-Journal</a>
              <a href="https://sugenghartono.ac.id/contact" target="_blank" class="ush-dosen-home-btn">💬 Layanan Kampus & Helpdesk</a>
            </div>
          </div>
        `;

        targetArea.insertAdjacentHTML('afterbegin', homeHTML);
      }
    }
  }

  document.addEventListener('DOMContentLoaded', ushApplyDosenHomeTheme);
  window.addEventListener('load', ushApplyDosenHomeTheme);
  setTimeout(ushApplyDosenHomeTheme, 300);
  setTimeout(ushApplyDosenHomeTheme, 1000);
</script>
<!-- END DOSEN SITE HOME OVERRIDE -->
HTML;

$current_html = get_config('core', 'additionalhtmltopofbody');
if (strpos($current_html, 'ushDosenHomeBanner') === false) {
    set_config('additionalhtmltopofbody', $current_html . "\n" . $dosen_home_script);
}

rebuild_course_cache(0, true);
purge_all_caches();

mtrace("==================================================");
mtrace("🎉 HALAMAN HOME KHUSUS DOSEN SELESAI DIPASANG!");
mtrace("==================================================");

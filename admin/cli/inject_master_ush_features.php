<?php
define('CLI_SCRIPT', true);
@error_reporting(E_ALL & ~E_WARNING & ~E_NOTICE);
require(__DIR__ . '/../../config.php');

mtrace("==================================================");
mtrace("🚀 MEMASANG MASTER WIDGET & DASHBOARD DOSEN EKSKLUSIF");
mtrace("==================================================");

$master_html = <<<HTML
<!-- MASTER USH INTERNATIONAL LMS ENHANCEMENT SUITE -->
<style>
  /* 1. Multi-Language Switcher */
  .ush-lang-bar {
    position: fixed;
    top: 12px;
    right: 130px;
    z-index: 99999;
    display: flex;
    align-items: center;
    gap: 6px;
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(10px);
    -webkit-backdrop-filter: blur(10px);
    border: 1px solid rgba(0, 51, 153, 0.18);
    padding: 4px 12px;
    border-radius: 30px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.12);
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
    font-size: 13px;
  }
  .ush-lang-label { font-weight: 700; color: #002B7F; font-size: 11px; text-transform: uppercase; margin-right: 4px; }
  .ush-lang-btn { display: inline-flex; align-items: center; gap: 5px; padding: 4px 10px; border-radius: 20px; text-decoration: none !important; color: #334155; font-weight: 600; font-size: 12px; }
  .ush-lang-btn:hover { background: #f1f5f9; color: #0f172a; }
  .ush-lang-btn.active { background: #002B7F; color: #ffffff !important; box-shadow: 0 2px 8px rgba(0, 43, 127, 0.3); }

  /* 2. Dosen Executive Theme */
  .ush-dosen-hero {
    background: linear-gradient(135deg, #0f172a 0%, #1e293b 50%, #0284c7 100%) !important;
    border-radius: 16px !important;
    padding: 28px 32px !important;
    color: #ffffff !important;
    margin: 15px 0 25px 0 !important;
    box-shadow: 0 12px 35px rgba(15, 23, 42, 0.28) !important;
    position: relative !important;
    overflow: hidden !important;
    border-left: 6px solid #38bdf8 !important;
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif !important;
  }
  .ush-dosen-hero::after {
    content: '👨‍🏫';
    position: absolute;
    right: 25px;
    bottom: -15px;
    font-size: 120px;
    opacity: 0.12;
    pointer-events: none;
  }
  .ush-dosen-badge {
    background: linear-gradient(90deg, #0284c7 0%, #38bdf8 100%) !important;
    color: #ffffff !important;
    font-size: 11px !important;
    font-weight: 800 !important;
    padding: 4px 14px !important;
    border-radius: 20px !important;
    text-transform: uppercase !important;
    letter-spacing: 0.8px !important;
    display: inline-block !important;
    margin-bottom: 12px !important;
    box-shadow: 0 4px 12px rgba(2, 132, 199, 0.3) !important;
  }
  .ush-dosen-name {
    font-size: 25px !important;
    font-weight: 800 !important;
    color: #ffffff !important;
    margin: 0 0 8px 0 !important;
  }
  .ush-dosen-desc {
    font-size: 13.5px !important;
    color: #cbd5e1 !important;
    margin: 0 0 20px 0 !important;
    line-height: 1.6 !important;
  }
  .ush-dosen-stats {
    display: grid !important;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)) !important;
    gap: 12px !important;
  }
  .ush-dosen-stat-box {
    background: rgba(255, 255, 255, 0.1) !important;
    backdrop-filter: blur(10px) !important;
    -webkit-backdrop-filter: blur(10px) !important;
    border: 1px solid rgba(255, 255, 255, 0.18) !important;
    border-radius: 12px !important;
    padding: 12px 14px !important;
    display: flex !important;
    align-items: center !important;
    gap: 12px !important;
  }
  .ush-dosen-stat-icon {
    font-size: 22px !important;
    background: rgba(56, 189, 248, 0.2) !important;
    width: 42px !important;
    height: 42px !important;
    border-radius: 10px !important;
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
    color: #38bdf8 !important;
  }
  .ush-dosen-stat-num { font-size: 15px !important; font-weight: 700 !important; color: #ffffff !important; line-height: 1.2 !important; }
  .ush-dosen-stat-sub { font-size: 10.5px !important; color: #94a3b8 !important; text-transform: uppercase !important; }

  .ush-dosen-tools {
    display: flex !important;
    flex-wrap: wrap !important;
    gap: 10px !important;
    margin-bottom: 24px !important;
  }
  .ush-dosen-tool-btn {
    display: inline-flex !important;
    align-items: center !important;
    gap: 8px !important;
    background: #ffffff !important;
    border: 1px solid #cbd5e1 !important;
    padding: 9px 16px !important;
    border-radius: 30px !important;
    color: #0f172a !important;
    font-weight: 700 !important;
    font-size: 12.5px !important;
    text-decoration: none !important;
    box-shadow: 0 2px 6px rgba(0,0,0,0.05) !important;
    transition: all 0.2s ease !important;
  }
  .ush-dosen-tool-btn:hover {
    background: #0284c7 !important;
    border-color: #0284c7 !important;
    color: #ffffff !important;
    transform: translateY(-2px) !important;
    box-shadow: 0 6px 18px rgba(2, 132, 199, 0.25) !important;
  }
</style>

<!-- LANG SWITCHER BAR -->
<div class="ush-lang-bar" id="ushLangSwitcher">
  <span class="ush-lang-label">🌐 Global USH:</span>
  <a href="javascript:void(0);" onclick="ushSwitchLang('en')" class="ush-lang-btn" id="langBtnEN">🇬🇧 English</a>
  <a href="javascript:void(0);" onclick="ushSwitchLang('id')" class="ush-lang-btn" id="langBtnID">🇮🇩 Indonesia</a>
  <a href="javascript:void(0);" onclick="ushSwitchLang('zh_cn')" class="ush-lang-btn" id="langBtnZH">🇨🇳 中文 (Mandarin)</a>
</div>

<script>
  function ushSwitchLang(langCode) {
    const url = new URL(window.location.href);
    url.searchParams.set('lang', langCode);
    window.location.href = url.toString();
  }

  function ushApplyDosenMasterTheme() {
    const isDashboard = window.location.pathname.includes('/my/') || window.location.pathname.includes('/my/index.php');
    if (!isDashboard || document.getElementById('ushDosenHeroMaster')) return;

    // Detect user name
    const userMenu = document.querySelector('.userbutton .username') || document.querySelector('.usermenu') || document.querySelector('.usertext');
    const userName = userMenu?.textContent?.trim() || '';

    // Check if user is Dosen (Yulaikha, titles, or username dosen_)
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
      // Find insertion point right above Course Overview or Dashboard content
      const targetArea = document.querySelector('.dashboard-card-deck') || 
                         document.querySelector('#region-main') || 
                         document.querySelector('[role="main"]') ||
                         document.querySelector('.main-content');

      if (targetArea) {
        const heroHTML = `
          <div class="ush-dosen-hero" id="ushDosenHeroMaster">
            <span class="ush-dosen-badge">👨‍🏫 PORTAL DOSEN PENGAMPU USH</span>
            <h1 class="ush-dosen-name">Selamat Datang, Ibu ${userName}! 👋</h1>
            <p class="ush-dosen-desc">Workspace Pengajaran Digital Universitas Sugeng Hartono. Kelola jadwal perkuliahan, evaluasi tugas mahasiswa, serta rekapitulasi presensi terintegrasi SIAKAD.</p>
            
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
            <a href="https://siakad.sugenghartono.ac.id" target="_blank" class="ush-dosen-tool-btn">🏛️ Input Nilai SIAKAD Dosen</a>
            <a href="http://localhost/moodle/my/courses.php" class="ush-dosen-tool-btn">📚 Kelas Pengajaran Dosen</a>
            <a href="https://library.sugenghartono.ac.id" target="_blank" class="ush-dosen-tool-btn">📖 E-Library & Jurnal Riset</a>
            <a href="https://sugenghartono.ac.id/contact" target="_blank" class="ush-dosen-tool-btn">💬 Helpdesk & Support Dosen</a>
          </div>
        `;

        targetArea.insertAdjacentHTML('beforebegin', heroHTML);

        // Customize section titles for Dosen
        document.querySelectorAll('h1, h2, h3, h4, h5').forEach(h => {
          const txt = h.textContent.trim();
          if ((txt.includes('Hi,') || txt.includes('Welcome,')) && !h.classList.contains('ush-dosen-name')) {
            h.style.display = 'none'; // Hide duplicate simple hi text
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
  }

  document.addEventListener('DOMContentLoaded', ushApplyDosenMasterTheme);
  window.addEventListener('load', ushApplyDosenMasterTheme);
  setTimeout(ushApplyDosenMasterTheme, 200);
  setTimeout(ushApplyDosenMasterTheme, 800);
  setTimeout(ushApplyDosenMasterTheme, 1500);
</script>
<!-- END MASTER USH SUITE -->
HTML;

set_config('additionalhtmltopofbody', $master_html);

rebuild_course_cache(0, true);
purge_all_caches();

mtrace("==================================================");
mtrace("🎉 SELESAI MEMASANG MASTER WIDGET & TEMA EKSKLUSIF DOSEN!");
mtrace("==================================================");

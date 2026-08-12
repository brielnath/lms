<?php
define('CLI_SCRIPT', true);
@error_reporting(E_ALL & ~E_WARNING & ~E_NOTICE);
require(__DIR__ . '/../../config.php');

mtrace("==================================================");
mtrace("🚀 MEMASANG MULTI-LANGUAGE SWITCHER WIDGET");
mtrace("==================================================");

$widget_html = <<<HTML
<!-- USH INTERNATIONAL MULTI-LANGUAGE SWITCHER WIDGET -->
<style>
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

  .ush-flag {
    font-size: 14px;
    line-height: 1;
  }

  @media (max-width: 768px) {
    .ush-lang-bar {
      top: auto;
      bottom: 15px;
      right: 15px;
    }
  }
</style>

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
  });
</script>
<!-- END USH INTERNATIONAL MULTI-LANGUAGE SWITCHER WIDGET -->
HTML;

// Set in additionalhtmltopofbody
set_config('additionalhtmltopofbody', $widget_html);

rebuild_course_cache(0, true);
purge_all_caches();

mtrace("✅ Multi-Language Switcher Widget terpasang di seluruh halaman Moodle!");
mtrace("==================================================");

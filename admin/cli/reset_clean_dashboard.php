<?php
define('CLI_SCRIPT', true);
@error_reporting(E_ALL & ~E_WARNING & ~E_NOTICE);
require(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/blocklib.php');

mtrace("==================================================");
mtrace("🧹 SHARPENING & RESETTING DASHBOARD LAYOUT FOR ALL USERS");
mtrace("==================================================");

// Set default dashboard blocks
$DB->delete_records('block_instances', ['pagetypepattern' => 'my-index']);

// Create clean dashboard block layout for /my/
$sysctx = context_system::instance();

// 1. My Overview (Course Overview Cards)
$block1 = new stdClass();
$block1->blockname = 'myoverview';
$block1->parentcontextid = $sysctx->id;
$block1->showinsubcontexts = 0;
$block1->pagetypepattern = 'my-index';
$block1->subpagepattern = null;
$block1->defaultregion = 'content';
$block1->defaultweight = 1;
$block1->configdata = '';
$block1->timecreated = time();
$block1->timemodified = time();
$DB->insert_record('block_instances', $block1);
mtrace("  ✅ Block added: Course Overview (myoverview)");

// 2. Timeline (Upcoming deadlines)
$block2 = new stdClass();
$block2->blockname = 'timeline';
$block2->parentcontextid = $sysctx->id;
$block2->showinsubcontexts = 0;
$block2->pagetypepattern = 'my-index';
$block2->subpagepattern = null;
$block2->defaultregion = 'content';
$block2->defaultweight = 2;
$block2->configdata = '';
$block2->timecreated = time();
$block2->timemodified = time();
$DB->insert_record('block_instances', $block2);
mtrace("  ✅ Block added: Timeline Deadlines (timeline)");

// 3. Recently Accessed Courses
$block3 = new stdClass();
$block3->blockname = 'recentlyaccesseditems';
$block3->parentcontextid = $sysctx->id;
$block3->showinsubcontexts = 0;
$block3->pagetypepattern = 'my-index';
$block3->subpagepattern = null;
$block3->defaultregion = 'side-post';
$block3->defaultweight = 1;
$block3->configdata = '';
$block3->timecreated = time();
$block3->timemodified = time();
$DB->insert_record('block_instances', $block3);
mtrace("  ✅ Block added: Recently Accessed Items (recentlyaccesseditems)");

// 4. Calendar Month
$block4 = new stdClass();
$block4->blockname = 'calendar_month';
$block4->parentcontextid = $sysctx->id;
$block4->showinsubcontexts = 0;
$block4->pagetypepattern = 'my-index';
$block4->subpagepattern = null;
$block4->defaultregion = 'side-post';
$block4->defaultweight = 2;
$block4->configdata = '';
$block4->timecreated = time();
$block4->timemodified = time();
$DB->insert_record('block_instances', $block4);
mtrace("  ✅ Block added: Calendar Month (calendar_month)");

// Reset custom user dashboard preferences so everyone sees the updated layout
$DB->delete_records('user_preferences', ['name' => 'my_pages_page']);

rebuild_course_cache(0, true);
purge_all_caches();

mtrace("\n==================================================");
mtrace("🎉 DASHBOARD SUDAH DIPERBARUI & DIRAPIKAN TOTAL!");
mtrace("==================================================");

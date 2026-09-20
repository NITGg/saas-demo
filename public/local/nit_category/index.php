<?php
require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->libdir . '/filelib.php');

// Respect the site's forced-login policy: if the site requires login to browse,
// gate this catalogue page too (core_course_category visibility checks below
// still apply either way).
if (!empty($CFG->forcelogin)) {
    require_login();
}

$categoryid = required_param('id', PARAM_INT);      // Parent category (drives header + labels).
$subid      = optional_param('sub', 0, PARAM_INT);  // 0 = "All" (every subcategory as its own section).

// Parent category.
$category = core_course_category::get($categoryid, MUST_EXIST);
$context  = $category->get_context();

// Direct subcategories -> the clickable label bar.
$subcategories = $category->get_children();

// Which category's courses are we listing? "All" -> parent; otherwise the chosen child.
$targetcat = $category;
if ($subid) {
    $found = null;
    foreach ($subcategories as $sc) {
        if ((int) $sc->id === $subid) {
            $found = $sc;
            break;
        }
    }
    if ($found) {
        $targetcat = $found;
    } else {
        $subid = 0; // Unknown sub id -> behave as "All".
    }
}

$PAGE->set_url(new moodle_url('/local/nit_category/index.php', ['id' => $categoryid, 'sub' => $subid]));
$PAGE->set_context($context);
$PAGE->set_title($category->get_formatted_name());
$PAGE->set_heading($category->get_formatted_name());
// NIT full-width layout: navbar + footer only, no page heading / secondary nav.
$PAGE->set_pagelayout('nit_fullwidth');

// Build the sections to render. Each section = one category header + the cards of
// its courses, so courses always sit under their own category name:
//   * "All" (sub = 0)  -> one section per direct subcategory, plus a leading
//                         section for any course sitting directly under the parent.
//   * a chosen subcat  -> just that one section.
//   * no subcategories -> a single section for the (leaf) parent itself.
// Each course's category is thus its enclosing section, matching the header above it.
// Sort order from the catalog control (?sort=). Real, functional orderings —
// nothing is decorative-only.
$sort = optional_param('sort', 'recommended', PARAM_ALPHA);
$sortmap = [
    'recommended' => ['sortorder' => 1],       // the site's curated order
    'newest'      => ['timecreated' => -1],     // most recently created first
    'az'          => ['fullname' => 1],         // alphabetical
];
$sortorder = $sortmap[$sort] ?? $sortmap['recommended'];
if (!isset($sortmap[$sort])) {
    $sort = 'recommended';
}
$fetchcourses = function (core_course_category $cat, bool $recursive) use ($sortorder): array {
    return $cat->get_courses([
        'recursive'      => $recursive,
        'sort'           => $sortorder,
        'summary'        => true,
        'coursecontacts' => true,
    ]);
};

// Build a category "node": its own (direct) courses plus a node for every child
// category, recursively. This lets each subcategory — at any depth — render as its
// own titled group under its parent, instead of a parent lumping every descendant
// course into one flat list.
$buildnode = function (core_course_category $cat) use (&$buildnode, $fetchcourses): array {
    $children = [];
    foreach ($cat->get_children() as $child) {
        $children[] = $buildnode($child);
    }
    return [
        'cat'      => $cat,
        'courses'  => $fetchcourses($cat, false), // direct only; descendants are child nodes
        'children' => $children,
    ];
};

// Total courses in a node's whole subtree (its own + every descendant's).
$counttree = function (array $node) use (&$counttree): int {
    $n = count($node['courses']);
    foreach ($node['children'] as $child) {
        $n += $counttree($child);
    }
    return $n;
};

$rootnodes = [];
if (empty($subcategories)) {
    // Flat category (no children): just its own courses.
    $rootnodes[] = $buildnode($category);
} else if ($subid) {
    // One subcategory selected: render that subtree (its courses + nested subcategories).
    $rootnodes[] = $buildnode($targetcat);
} else {
    // "All": courses that live directly under the parent (not inside any child) get
    // their own section first so nothing is dropped, then every subcategory subtree.
    $directcourses = $fetchcourses($category, false);
    if (!empty($directcourses)) {
        $rootnodes[] = ['cat' => $category, 'courses' => $directcourses, 'children' => []];
    }
    foreach ($subcategories as $sc) {
        $rootnodes[] = $buildnode($sc);
    }
}

// Drop empty subtrees and tally the visible total.
$rootnodes = array_values(array_filter($rootnodes, static fn($n) => $counttree($n) > 0));
$totalcourses = 0;
foreach ($rootnodes as $n) {
    $totalcourses += $counttree($n);
}

// Hero banner always shows the grand total for the whole parent category, regardless
// of any subcategory filter currently selected.
$bannertotal = $category->get_courses_count(['recursive' => true]);

// Category image: Moodle categories have no image of their own, so fall back to
// the site logo ("if the category has no image, show the site logo").
$logo = $OUTPUT->get_logo_url() ?: $OUTPUT->get_compact_logo_url();
$categoryimage = $logo ? $logo->out(false) : '';

// Colour palette: this page reads entirely from the site Brand Colors palette
// (theme_nit's --nit-brand-* custom properties), so it re-skins with the rest of
// the site and honours RTL/LTR + dark/light automatically. The 8 local slots map
// to brand roles by job: backgrounds -> Background/Surface, the call-to-action fill
// -> Primary, text -> Text primary/secondary, accent TEXT (hero counts, stat numbers,
// labels) -> Accent Text (--ctext3), and non-text accent tints/pills/borders -> Accent
// (--caccent). --cbg3 (the tile behind category/course images) is Surface lifted a
// touch so logos read cleanly.
$stylevars =
    '--cbg1: var(--nit-brand-background); '
  . '--cbg2: var(--nit-brand-surface); '
  . '--cbg3: color-mix(in srgb, var(--nit-brand-surface) 88%, var(--nit-brand-textprimary)); '
  . '--cbg4: var(--nit-brand-primary); '
  . '--ctext1: var(--nit-brand-textprimary); '
  . '--ctext2: var(--nit-brand-textsecondary); '
  . '--ctext3: var(--nit-brand-accenttext); '
  . '--caccent: var(--nit-brand-accent); '
  . '--ctext4: var(--nit-brand-textprimary); '
  . '--cborder: var(--nit-brand-borderprimary); '
  . '--csuccess: var(--nit-brand-success); ';

// Brand group for this category (gallery "Category styles" tab). Group 1 is the
// default layer (no class); groups 2/3 add the .nit-brand-2 / .nit-brand-3 switch
// class to the wrapper, so every --nit-brand-* the page reads (and hence every
// --cbg*/--ctext* above) resolves from that group instead.
$brandgroupclass = '';
if (function_exists('theme_nit_category_brand_group')) {
    $brandgroupclass = theme_nit_brand_group_class(theme_nit_category_brand_group((int) $category->id));
}

// Bilingual inline helper (site is en/ar); mirrors the theme's {mlang} pairs.
$isar = (strpos(current_language(), 'ar') === 0);
$t = function (string $en, string $ar) use ($isar) {
    return $isar ? $ar : $en;
};

// Subcategory filter buttons reuse the site's gallery button components (Components
// tab): the active filter is a solid .btn-primary, the rest are .btn-outline-primary.
$pill = function (moodle_url $url, string $label, bool $active): string {
    $cls = $active ? 'btn btn-primary' : 'btn btn-outline-primary';
    return '<a href="' . $url->out() . '" class="' . $cls . ' fw-bold">' . $label . '</a>';
};

$description  = format_text($category->description, $category->descriptionformat, ['context' => $context]);
$categoryname = $category->get_formatted_name();

// NIT: checkout modal + course offer/price support (guarded — degrade if the plugins are absent).
$nitcheckout = class_exists('\local_payments\price_resolver')
    && file_exists($CFG->dirroot . '/local/nit_commerce/lib.php')
    && class_exists('\local_nit_commerce\discount_manager');
if ($nitcheckout) {
    require_once($CFG->dirroot . '/local/nit_commerce/lib.php');
    $PAGE->requires->js(new moodle_url('/local/nit_commerce/checkout_modal.js'), true);
}
// Per-course state for a card: enrolment, subscription coverage, pricing, offer.
$nitcourseinfo = function ($courseid) use ($nitcheckout) {
    global $USER;
    $out = ['enrolled' => false, 'covered' => false, 'free' => true, 'haspricing' => false,
        'price' => 0.0, 'offerlabel' => '', 'offerfinal' => 0.0];
    $uid = (int) ($USER->id ?? 0);
    $ctx = context_course::instance($courseid);
    $out['enrolled'] = $uid > 0 && is_enrolled($ctx, $uid, '', true);

    if (!$nitcheckout) {
        return $out;
    }
    $out['haspricing'] = (bool) \local_payments\price_resolver::has_pricing($courseid);
    $out['free'] = !$out['haspricing'];

    // Covered by an active subscription (grants access without buying). Only relevant when not
    // already enrolled and the course is paid (a free course is just "enrol").
    if (!$out['enrolled'] && $out['haspricing']
            && class_exists('\local_nit_subscriptions\subscription_purchase_manager')) {
        $out['covered'] = (bool) \local_payments\price_resolver::is_covered_by_active_subscription($courseid, $uid);
    }

    if ($out['haspricing']) {
        try {
            $pricing = \local_payments\price_resolver::resolve($courseid, $uid);
            $base = (float) $pricing->price;
            $out['price'] = $base;
            $summary = \local_nit_commerce\discount_manager::offer_summary('course', (int) $courseid, $base);
            if ($summary) {
                $out['offerlabel'] = $summary['label'];   // e.g. "-40%"
                $out['offerfinal'] = (float) $summary['final'];
            }
        } catch (\Throwable $e) {
            // Leave defaults on any pricing error.
        }
    }
    return $out;
};

echo $OUTPUT->header();
?>
<style>
  .nit-cat{
    /* T1 keeps its LIGHT structure (like the homepage templates); only the accent
       comes from the academy brand. This keeps the T1 look consistent on any brand. */
    --t-accent: var(--nit-brand-primary); --t-accent-2: var(--nit-brand-accent); --t-on: var(--nit-brand-on-primary, #fff);
    --t-accent-soft: color-mix(in srgb, var(--nit-brand-primary) 9%, transparent);
    --t-ink: #16191D; --t-bg: #FFFFFF; --t-surface: #FAFAF8;
    --t-muted: #6E7781; --t-border: #EDEDE9;
    font-family:'Manrope','IBM Plex Sans Arabic',system-ui,sans-serif; background:var(--t-bg); color:var(--t-ink);
    width:100vw; max-width:100vw; margin-inline:calc(50% - 50vw); min-height:100vh;
  }
  .nit-cat a{ text-decoration:none; }
  .nit-cat-wrap{ max-width:1240px; margin:0 auto; padding:clamp(20px,3vw,40px) 20px 60px; }
  .nit-cat-crumbs{ font-size:13px; color:var(--t-muted); display:flex; gap:8px; align-items:center; }
  .nit-cat-crumbs a{ color:var(--t-muted); } .nit-cat-crumbs a:hover{ color:var(--t-accent); }
  .nit-cat-head{ display:flex; align-items:flex-end; justify-content:space-between; gap:20px; flex-wrap:wrap; margin-top:18px; padding-bottom:26px; border-bottom:1px solid var(--t-border); }
  .nit-cat-eyebrow{ font-size:12px; letter-spacing:.18em; text-transform:uppercase; color:var(--t-accent); font-weight:700; }
  .nit-cat-h1{ margin:12px 0 0; font-size:clamp(30px,4vw,48px); font-weight:250; letter-spacing:-0.03em; }
  .nit-cat-desc{ margin:12px 0 0; font-size:15px; line-height:1.7; color:var(--t-muted); max-width:640px; }
  .nit-cat-desc *{ font-size:15px !important; color:var(--t-muted); }
  .nit-cat-count{ margin-top:14px; font-size:13px; color:var(--t-muted2, var(--t-muted)); }
  .nit-cat-sort{ display:flex; align-items:center; gap:8px; }
  .nit-cat-sort label{ font-size:12px; color:var(--t-muted); }
  .nit-cat-sort select{ border:1px solid var(--t-border); border-radius:10px; padding:9px 12px; font-size:14px; font-weight:600; background:var(--t-bg); color:var(--t-ink); font-family:inherit; cursor:pointer; }
  .nit-cat-body{ display:grid; grid-template-columns:240px 1fr; gap:32px; margin-top:30px; align-items:start; }
  @media (max-width: 900px){ .nit-cat-body{ grid-template-columns:1fr; } .nit-cat-side{ position:static !important; } }
  .nit-cat-side{ position:sticky; top:16px; border:1px solid var(--t-border); border-radius:16px; padding:18px; }
  .nit-cat-side-head{ display:flex; align-items:center; justify-content:space-between; font-size:12px; letter-spacing:.1em; text-transform:uppercase; color:var(--t-muted); font-weight:700; margin-bottom:12px; }
  .nit-cat-side-head a{ font-size:12px; color:var(--t-accent); font-weight:600; text-transform:none; letter-spacing:0; }
  .nit-cat-filter{ display:flex; align-items:center; justify-content:space-between; gap:8px; padding:9px 12px; border-radius:10px; color:var(--t-ink); font-size:14px; }
  .nit-cat-filter:hover{ background:var(--t-accent-soft); }
  .nit-cat-filter.on{ background:var(--t-accent-soft); color:var(--t-accent); font-weight:700; }
  .nit-cat-filter span{ font-size:12px; color:var(--t-muted); }
  .nit-cat-filter.on span{ color:var(--t-accent); }
  .nit-cat-pills{ display:flex; flex-wrap:wrap; gap:10px; margin-bottom:22px; }
  .nit-cat-pill{ font-size:13px; font-weight:600; padding:8px 16px; border-radius:40px; border:1px solid var(--t-border); color:var(--t-ink); }
  .nit-cat-pill.on{ background:var(--t-ink); color:var(--t-bg); border-color:var(--t-ink); }
  .nit-cat-secttitle{ font-size:clamp(20px,2.2vw,26px); font-weight:250; letter-spacing:-0.02em; margin:8px 0 20px; display:flex; align-items:baseline; gap:10px; }
  .nit-cat-secttitle .c{ font-size:14px; color:var(--t-muted); font-weight:400; }
  .nit-cat-grid{ display:grid; grid-template-columns:repeat(auto-fill, minmax(260px,1fr)); gap:24px; align-items:stretch; }
  .nit-cat-block{ margin-bottom:40px; }
  .nit-cat-block--nested{ margin-inline-start:14px; }
  .nit-t1-card{ background:var(--t-bg); border:1px solid var(--t-border); border-radius:16px; overflow:hidden; display:flex; flex-direction:column; box-shadow:0 14px 34px rgba(20,24,28,0.05); transition:box-shadow .25s ease, transform .25s ease; }
  .nit-t1-card:hover{ box-shadow:0 22px 48px rgba(20,24,28,0.12); transform:translateY(-3px); }
  .nit-t1-thumb{ aspect-ratio:16/9; background:repeating-linear-gradient(135deg,#EFEFEC 0 11px,#F7F7F5 11px 22px) center/cover no-repeat; position:relative; }
  .nit-t1-badge{ position:absolute; top:12px; inset-inline-start:12px; background:var(--t-bg); color:var(--t-accent); font-size:11px; font-weight:700; letter-spacing:.06em; text-transform:uppercase; padding:5px 9px; border-radius:6px; box-shadow:0 4px 12px rgba(20,24,28,.12); }
  .nit-t1-cb{ padding:20px; display:flex; flex-direction:column; flex:1; }
  .nit-t1-title{ font-size:18px; font-weight:550; line-height:1.35; letter-spacing:-0.015em; margin:0; color:var(--t-ink); display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical; overflow:hidden; }
  .nit-t1-teacher{ display:flex; align-items:center; gap:8px; margin-top:12px; font-size:13px; color:var(--t-muted); }
  .nit-t1-foot{ margin-top:auto; padding-top:16px; border-top:1px solid color-mix(in srgb, var(--t-border) 80%, transparent); }
  .nit-t1-priceslot{ min-height:26px; display:flex; align-items:center; flex-wrap:wrap; gap:8px; margin-bottom:12px; font-weight:650; }
  .nit-t1-strike{ font-size:13px; color:var(--t-muted); text-decoration:line-through; opacity:.7; font-weight:400; }
  .nit-t1-price{ font-size:17px; font-weight:650; letter-spacing:-0.02em; color:var(--t-ink); }
  .nit-t1-offer{ background:var(--t-accent); color:var(--t-on); font-size:11px; font-weight:700; padding:3px 9px; border-radius:50px; }
  .nit-t1-free{ font-size:13px; font-weight:700; color:var(--nit-brand-success); }
  .nit-t1-chip{ display:inline-flex; align-items:center; gap:5px; font-size:12px; font-weight:700; padding:4px 12px; border-radius:50px; }
  .nit-t1-chip--enr{ background:color-mix(in srgb, var(--nit-brand-success) 15%, transparent); color:var(--nit-brand-success); }
  .nit-t1-chip--cov{ background:var(--t-accent-soft); color:var(--t-accent); }
  .nit-cat .nit-t1-foot .btn{ border-radius:10px !important; font-weight:600 !important; }
  .nit-cat .nit-t1-foot .btn-primary{ background:var(--t-accent) !important; border-color:var(--t-accent) !important; color:var(--t-on) !important; }
  .nit-cat .nit-t1-foot .btn-outline-primary{ border:1px solid var(--t-border) !important; color:var(--t-ink) !important; }
  .nit-cat .lp-card-badge{ display:none !important; }
</style>
<div dir="auto" class="nit-cat<?= $brandgroupclass !== '' ? ' ' . $brandgroupclass : '' ?>" style="<?= $stylevars ?>">
  <div class="nit-cat-wrap">
    <nav class="nit-cat-crumbs">
      <a href="<?= (new moodle_url('/'))->out() ?>"><?= $t('Home', 'الرئيسية') ?></a> /
      <a href="<?= (new moodle_url('/course/index.php'))->out() ?>"><?= $t('Courses', 'الدورات') ?></a> /
      <span style="color:var(--t-ink);"><?= $categoryname ?></span>
    </nav>

    <div class="nit-cat-head">
      <div>
        <div class="nit-cat-eyebrow"><?= $t('Catalog', 'الكتالوج') ?></div>
        <h1 class="nit-cat-h1"><?= $categoryname ?></h1>
        <?php if (trim(strip_tags($description)) !== ''): ?>
          <div class="nit-cat-desc"><?= $description ?></div>
        <?php endif; ?>
        <div class="nit-cat-count"><strong><?= $totalcourses ?></strong> <?= $t('courses', 'دورة') ?> · <?= $categoryname ?></div>
      </div>
      <form method="get" class="nit-cat-sort" action="<?= (new moodle_url('/local/nit_category/index.php'))->out(false) ?>">
        <input type="hidden" name="id" value="<?= (int) $categoryid ?>">
        <?php if ($subid): ?><input type="hidden" name="sub" value="<?= (int) $subid ?>"><?php endif; ?>
        <label for="nit-sort"><?= $t('Sort', 'ترتيب') ?></label>
        <select id="nit-sort" name="sort" onchange="this.form.submit()">
          <option value="recommended" <?= $sort === 'recommended' ? 'selected' : '' ?>><?= $t('Recommended', 'موصى به') ?></option>
          <option value="newest" <?= $sort === 'newest' ? 'selected' : '' ?>><?= $t('Newest', 'الأحدث') ?></option>
          <option value="az" <?= $sort === 'az' ? 'selected' : '' ?>><?= $t('A – Z', 'أ – ي') ?></option>
        </select>
      </form>
    </div>

    <div class="nit-cat-body">
      <aside class="nit-cat-side">
        <div class="nit-cat-side-head">
          <span><?= $t('Categories', 'التصنيفات') ?></span>
          <?php if ($subid): ?><a href="<?= (new moodle_url('/local/nit_category/index.php', ['id' => $categoryid, 'sort' => $sort]))->out() ?>"><?= $t('Clear', 'مسح') ?></a><?php endif; ?>
        </div>
        <a class="nit-cat-filter<?= $subid === 0 ? ' on' : '' ?>" href="<?= (new moodle_url('/local/nit_category/index.php', ['id' => $categoryid, 'sort' => $sort]))->out() ?>">
          <span style="font-size:14px;color:inherit;"><?= $t('All', 'الكل') ?></span>
          <span><?= (int) $bannertotal ?></span>
        </a>
        <?php foreach ($subcategories as $sc): ?>
          <?php $sccount = (int) $sc->get_courses_count(['recursive' => true]); ?>
          <a class="nit-cat-filter<?= $subid === (int) $sc->id ? ' on' : '' ?>" href="<?= (new moodle_url('/local/nit_category/index.php', ['id' => $categoryid, 'sub' => $sc->id, 'sort' => $sort]))->out() ?>">
            <span style="font-size:14px;color:inherit;"><?= $sc->get_formatted_name() ?></span>
            <span><?= $sccount ?></span>
          </a>
        <?php endforeach; ?>
      </aside>

      <main class="nit-cat-main">
        <?php
          // T1 course card — restyled; the pricing/offer/enrolment display and the
          // checkout trigger (data-nit-buy-course) are IDENTICAL to before.
          $rendercard = function (core_course_list_element $course, string $sectionname) use ($t, $nitcourseinfo) {
              $courseurl  = new moodle_url('/course/view.php', ['id' => $course->id]);
              $coursename = $course->get_formatted_name();
              $price      = function_exists('theme_nit_course_price') ? theme_nit_course_price((int) $course->id) : '';
              $teacher    = function_exists('theme_nit_course_teacher') ? theme_nit_course_teacher((int) $course->id) : '';
              $pricelabel = $price !== '' ? $price : $t('Free', 'مجانًا');
              $info       = $nitcourseinfo($course->id);
              $detailsurl = $courseurl->out();
              $enrolurl   = (new moodle_url('/local/nit_subscriptions/enrol.php',
                  ['courseid' => $course->id, 'sesskey' => sesskey()]))->out(false);
              // Course thumbnail from the overview files (falls back to the striped placeholder).
              $img = '';
              foreach ($course->get_course_overviewfiles() as $f) {
                  if ($f->is_valid_image()) {
                      $img = moodle_url::make_pluginfile_url($f->get_contextid(), $f->get_component(),
                          $f->get_filearea(), $f->get_itemid() ?: null, $f->get_filepath(), $f->get_filename())->out(false);
                      break;
                  }
              }
          ?>
          <div class="nit-t1-card">
            <div class="nit-t1-thumb"<?= $img !== '' ? ' style="background-image:url(\'' . s($img) . '\');"' : '' ?>>
              <span class="nit-t1-badge"><?= $sectionname ?></span>
            </div>
            <div class="nit-t1-cb">
              <a href="<?= $detailsurl ?>" style="color:inherit;"><h3 class="nit-t1-title"><?= $coursename ?></h3></a>
              <?php if ($teacher !== ''): ?><div class="nit-t1-teacher">👤 <?= s($teacher) ?></div><?php endif; ?>
              <div class="nit-t1-foot">
                <div class="nit-t1-priceslot">
                  <?php if ($info['enrolled']): ?>
                    <span class="nit-t1-chip nit-t1-chip--enr">✓ <?= $t('Enrolled', 'مُسجَّل') ?></span>
                  <?php elseif ($info['covered']): ?>
                    <span class="nit-t1-chip nit-t1-chip--cov">★ <?= $t('In your subscription', 'ضمن اشتراكك') ?></span>
                  <?php elseif ($info['offerlabel'] !== '' && $info['offerfinal'] > 0): ?>
                    <span class="nit-t1-strike"><?= s($pricelabel) ?></span>
                    <span class="nit-t1-price"><?= s(number_format($info['offerfinal'], 0)) ?> <?= $t('EGP', 'ج.م') ?></span>
                    <span class="nit-t1-offer"><?= s($info['offerlabel']) ?></span>
                  <?php elseif ($info['haspricing']): ?>
                    <span class="nit-t1-price"><?= s($pricelabel) ?></span>
                  <?php else: ?>
                    <span class="nit-t1-free"><?= $t('Free', 'مجانًا') ?></span>
                  <?php endif; ?>
                </div>
                <div class="d-grid gap-2">
                  <?php if ($info['enrolled']): ?>
                    <a href="<?= $detailsurl ?>" class="btn btn-outline-primary fw-bold"><?= $t('Course details', 'تفاصيل الكورس') ?></a>
                  <?php elseif ($info['covered']): ?>
                    <a href="<?= $enrolurl ?>" class="btn btn-primary fw-bold"><?= $t('Enroll', 'التحاق') ?></a>
                    <a href="<?= $detailsurl ?>" class="btn btn-outline-primary fw-bold"><?= $t('Course details', 'تفاصيل الكورس') ?></a>
                  <?php elseif ($info['haspricing']): ?>
                    <button type="button" class="btn btn-primary fw-bold" data-nit-buy-course
                      data-courseid="<?= (int) $course->id ?>" data-name="<?= s($coursename) ?>"
                      data-price="<?= s((string) $info['price']) ?>"><?= $t('Buy now', 'اشترِ الآن') ?></button>
                    <a href="<?= $detailsurl ?>" class="btn btn-outline-primary fw-bold"><?= $t('Course details', 'تفاصيل الكورس') ?></a>
                  <?php else: ?>
                    <a href="<?= $enrolurl ?>" class="btn btn-primary fw-bold"><?= $t('Enroll', 'التحاق') ?></a>
                    <a href="<?= $detailsurl ?>" class="btn btn-outline-primary fw-bold"><?= $t('Course details', 'تفاصيل الكورس') ?></a>
                  <?php endif; ?>
                </div>
              </div>
            </div>
          </div>
          <?php
          };

          // Section renderer: a light T1 heading + the course grid, children nested.
          $rendernode = function (array $node, int $depth) use (&$rendernode, $rendercard, $counttree): void {
              $cat   = $node['cat'];
              $name  = $cat->get_formatted_name();
              $count = $counttree($node);
          ?>
          <div class="nit-cat-block<?= $depth > 0 ? ' nit-cat-block--nested' : '' ?>">
            <h2 class="nit-cat-secttitle"><?= $name ?> <span class="c">(<?= $count ?>)</span></h2>
            <?php if (!empty($node['courses'])): ?>
              <div class="nit-cat-grid">
                <?php foreach ($node['courses'] as $course): ?><?php $rendercard($course, $name); ?><?php endforeach; ?>
              </div>
            <?php endif; ?>
            <?php if (!empty($node['children'])): ?>
              <div class="nit-cat-children" style="margin-top:24px;">
                <?php foreach ($node['children'] as $child): ?>
                  <?php if ($counttree($child) > 0): ?><?php $rendernode($child, $depth + 1); ?><?php endif; ?>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          </div>
          <?php
          };

          if (!empty($rootnodes)) {
              foreach ($rootnodes as $node) {
                  $rendernode($node, 0);
              }
          } else {
              echo '<div style="text-align:center; color:var(--t-muted); padding:40px;">' . $t('No courses found in this category.', 'لا توجد دورات في هذا التصنيف.') . '</div>';
          }
        ?>
      </main>
    </div>
  </div>
</div>
<?php

// NIT: wire the course Buy buttons to the shared checkout modal (coupon + auto offer → Kashier).
if ($nitcheckout) {
    $costr = local_nit_commerce_string_map([
        'co_title', 'co_intro', 'co_total', 'co_total_sub', 'co_offer', 'co_coupon', 'co_apply', 'co_discount',
        'co_secure', 'co_proceed', 'co_cancel', 'co_loading', 'co_coupon_failed', 'co_currency',
    ]);
    echo html_writer::script('window.NIT_CO = ' . json_encode([
        'wwwroot'  => $CFG->wwwroot,
        'sesskey'  => sesskey(),
        'commerce' => '/local/nit_commerce/api.php',
        'str'      => $costr,
        'loggedin' => isloggedin() && !isguestuser(),
    ]) . ';');
    echo html_writer::script(<<<'JS'
(function () {
    function init() {
        if (!window.NitCheckout || !window.NIT_CO) { return; }
        NitCheckout.init(window.NIT_CO);
        document.addEventListener('click', function (ev) {
            var btn = ev.target.closest('[data-nit-buy-course]');
            if (!btn) { return; }
            ev.preventDefault();
            if (!window.NIT_CO.loggedin) { window.location.href = window.NIT_CO.wwwroot + '/login/index.php'; return; }
            var id = btn.getAttribute('data-courseid');
            NitCheckout.open({
                itemType: 'course',
                itemId: parseInt(id, 10),
                name: btn.getAttribute('data-name'),
                price: parseFloat(btn.getAttribute('data-price')) || 0,
                proceed: function (code) {
                    window.location.href = window.NIT_CO.wwwroot + '/local/payments/checkout.php?courseid=' + id +
                        '&sesskey=' + encodeURIComponent(window.NIT_CO.sesskey) + '&coupon_code=' + encodeURIComponent(code);
                }
            });
        });
    }
    if (document.readyState !== 'loading') { init(); }
    else { document.addEventListener('DOMContentLoaded', init); }
})();
JS
    );
}

echo $OUTPUT->footer();

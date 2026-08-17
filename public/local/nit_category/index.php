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
$fetchcourses = function (core_course_category $cat, bool $recursive): array {
    return $cat->get_courses([
        'recursive'      => $recursive,
        'sort'           => ['sortorder' => 1],
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

<div dir="auto" class="nit-cat-details<?= $brandgroupclass !== '' ? ' ' . $brandgroupclass : '' ?>" style="<?= $stylevars ?>background: var(--cbg1); min-height: 100vh; padding-bottom: 40px; width: 100vw; max-width: 100vw; margin-inline: calc(50% - 50vw); margin-top: 0;">

  <!-- Category Hero Banner (X-Trade style) -->
  <style>
    @keyframes nit-gridshift { 0% { transform: translateY(0); } 100% { transform: translateY(60px); } }
    @keyframes nit-hpulse { 0%, 100% { opacity: 1; } 50% { opacity: 0.3; } }
    @keyframes nit-fadeup { from { opacity: 0; transform: translateY(30px); } to { opacity: 1; transform: translateY(0); } }
    @keyframes nit-fadedown { from { opacity: 0; transform: translateY(-20px); } to { opacity: 1; transform: translateY(0); } }

    .nit-hero {
      background: var(--cbg2);
      min-height: 85vh;
      display: flex; align-items: center; justify-content: center; flex-direction: column;
      text-align: center;
      padding: 120px 5% 80px;
      position: relative; overflow: hidden;
      border-bottom: 1px solid color-mix(in srgb, var(--cbg4) 20%, transparent);
    }
    .nit-hero__grid {
      content: ''; position: absolute; inset: 0; pointer-events: none;
      background-image:
        linear-gradient(color-mix(in srgb, var(--cbg4) 6%, transparent) 1px, transparent 1px),
        linear-gradient(90deg, color-mix(in srgb, var(--cbg4) 6%, transparent) 1px, transparent 1px);
      background-size: 60px 60px;
      animation: nit-gridshift 20s linear infinite;
    }
    .nit-hero__glow-a {
      position: absolute; top: -30%; left: 50%; transform: translateX(-50%);
      width: 80%; height: 80%; pointer-events: none;
      background: radial-gradient(ellipse 80% 60% at 50% 0%, color-mix(in srgb, var(--cborder) 30%, transparent) 0%, transparent 70%);
    }
    .nit-hero__glow-b {
      position: absolute; bottom: -20%; inset-inline-end: -5%;
      width: 35%; height: 70%; pointer-events: none;
      background: radial-gradient(ellipse 40% 40% at 80% 80%, color-mix(in srgb, var(--cbg4) 12%, transparent) 0%, transparent 60%);
    }
    .nit-hero__inner { max-width: 860px; margin: 0 auto; position: relative; z-index: 1; }

    /* Badge — X-Trade .hero-badge */
    .nit-hero__badge {
      display: inline-flex; align-items: center; gap: 0.5rem;
      background: color-mix(in srgb, var(--cbg4) 12%, transparent);
      border: 1px solid color-mix(in srgb, var(--cbg4) 30%, transparent);
      border-radius: 50px; padding: 6px 19px;
      font-size: 14px; color: var(--ctext3); font-weight: 600;
      margin-bottom: 2rem;
      animation: nit-fadedown 0.8s ease both;
    }
    .nit-hero__badge-dot {
      width: 8px; height: 8px; background: var(--csuccess); border-radius: 50%;
      animation: nit-hpulse 2s infinite; flex-shrink: 0;
    }

    /* H1 — X-Trade .hero h1 : clamp(2.4rem, 6vw, 4.5rem) @16px root = 38/72px */
    .nit-hero__title {
      font-size: clamp(38px, 6vw, 72px);
      font-weight: 800; line-height: 1.15; margin: 0;
      color: var(--ctext1);
      animation: nit-fadeup 0.9s ease 0.1s both;
    }
    .nit-hero__title .nit-hero__n1 { color: var(--ctext3); }
    .nit-hero__title .nit-hero__n2 { color: var(--ctext1); }

    /* Description — X-Trade .hero-sub : clamp(1rem, 2vw, 1.25rem) = 16/20px.
       format_text() wraps this in its own <div>/<p> that carries the theme's
       default size, so force every descendant to the intended size. */
    .nit-hero__sub {
      max-width: 680px; margin: 1.5rem auto;
      animation: nit-fadeup 0.9s ease 0.25s both;
    }
    .nit-hero__sub,
    .nit-hero__sub * {
      font-size: clamp(16px, 2vw, 20px) !important;
      color: var(--ctext2);
      line-height: 1.8;
    }
    .nit-hero__sub p, .nit-hero__sub div { margin: 0; }

    /* Stats — X-Trade .hero-stats : gap 3rem, .stat-num 2.2rem, .stat-label 0.8rem */
    .nit-hero__stats {
      display: flex; gap: 48px; margin: 2.5rem 0;
      justify-content: center; flex-wrap: wrap;
      animation: nit-fadeup 0.9s ease 0.4s both;
    }
    .nit-hero__stat-num { font-size: 35px; font-weight: 800; color: var(--ctext3); display: block; line-height: 1; }
    .nit-hero__stat-label { font-size: 13px; color: var(--ctext2); font-weight: 500; }

    /* Buttons — reuse the site's .btn components (gallery.php); only size/shape
       here, colour + hover come from the theme's Bootstrap button tokens. */
    .nit-hero__btns {
      display: flex; gap: 16px; flex-wrap: wrap; justify-content: center;
      animation: nit-fadeup 0.9s ease 0.55s both;
    }
    .nit-hero__btns .btn {
      padding: 14px 40px; border-radius: 8px;
      font-size: 16px; font-weight: 700;
    }
  </style>
  <div class="nit-hero">
    <div class="nit-hero__grid"></div>
    <div class="nit-hero__glow-a"></div>
    <div class="nit-hero__glow-b"></div>

    <div class="nit-hero__inner">

      <!-- Badge: category name with pulsing dot -->
      <div class="nit-hero__badge">
        <span class="nit-hero__badge-dot"></span>
        <?= $categoryname ?>
      </div>

      <!-- H1: count in accent colour, subtitle with secondary accent -->
      <h1 class="nit-hero__title">
        <span class="nit-hero__n1"><?= $bannertotal ?> <?= $t('Training programs', 'برنامجًا تدريبيًا') ?></span><br>
        <span><?= $t('Diplomas and certificates', 'دبلومات وشهادات') ?></span> <span class="nit-hero__n2"><?= $t('professional', 'احترافية') ?></span>
      </h1>

      <!-- Description -->
      <?php if (trim(strip_tags($description)) !== ''): ?>
      <div class="nit-hero__sub"><?= $description ?></div>
      <?php endif; ?>

      <!-- Stats: floating flex, no border box -->
      <div class="nit-hero__stats">
        <div>
          <span class="nit-hero__stat-num"><?= $bannertotal ?></span>
          <span class="nit-hero__stat-label"><?= $t('Courses and diplomas', 'دورة ودبلوم') ?></span>
        </div>
        <div>
          <span class="nit-hero__stat-num"><?= count($subcategories) ?></span>
          <span class="nit-hero__stat-label"><?= $t('Main specializations', 'تخصص رئيسي') ?></span>
        </div>
        <div>
          <span class="nit-hero__stat-num">4</span>
          <span class="nit-hero__stat-label"><?= $t('Educational levels', 'مستويات تعليمية') ?></span>
        </div>
      </div>

      <!-- Buttons: the site's own .btn components -->
      <div class="nit-hero__btns">
        <a href="#nit-cat-filters" class="btn btn-primary"
           onclick="event.preventDefault(); document.getElementById('nit-cat-filters').scrollIntoView({behavior:'smooth'});">
          <?= $t('Explore specializations', 'استكشف التخصصات') ?>
        </a>
        <a href="#nit-cat-filters" class="btn btn-outline-primary"
           onclick="event.preventDefault(); document.getElementById('nit-cat-filters').scrollIntoView({behavior:'smooth'});">
          <?= $t('Flexible plans', 'خطط مرنة') ?>
        </a>
      </div>

    </div>
  </div>

  <!-- Subcategory Filter Bar (All + children) -->
  <?php if (!empty($subcategories)): ?>
  <div id="nit-cat-filters" style="padding: 32px 16px 0;">
    <div style="max-width: 1200px; margin: 0 auto; display: flex; flex-wrap: wrap; justify-content: center; gap: 12px;">
      <?php
        $allurl = new moodle_url('/local/nit_category/index.php', ['id' => $categoryid]);
        echo $pill($allurl, $t('All', 'الكل'), $subid === 0);
        foreach ($subcategories as $sc) {
            $suburl = new moodle_url('/local/nit_category/index.php', ['id' => $categoryid, 'sub' => $sc->id]);
            echo $pill($suburl, $sc->get_formatted_name(), $subid === (int) $sc->id);
        }
      ?>
    </div>

    <?php
      // When a subcategory is selected, show its description.
      if ($subid) {
          $subdescription = format_text($targetcat->description, $targetcat->descriptionformat, ['context' => $targetcat->get_context()]);
          if (trim(strip_tags($subdescription)) !== '') {
              echo '<div style="max-width: 900px; margin: 24px auto 0; text-align: center; color: var(--ctext2); font-size: 15px; line-height: 1.7;">' . $subdescription . '</div>';
          }
      }
    ?>
  </div>
  <?php endif; ?>

  <!-- Courses Section -->
  <div style="padding: 32px 16px 16px;">
    <div style="max-width: 1200px; margin: 0 auto;">

      <?php
        // One card renderer, shared by every section. $sectionname is the category the
        // card lives under (its header), so the card can show that category's name.
        $rendercard = function (core_course_list_element $course, string $sectionname) use ($t, $nitcourseinfo) {
            $courseurl  = new moodle_url('/course/view.php', ['id' => $course->id]);
            $coursename = $course->get_formatted_name();

            // Short plain-text summary (no course image is used in this design).
            $summary = '';
            if ($course->has_summary()) {
                $coursecontext = context_course::instance($course->id);
                $plain = html_to_text(
                    format_text($course->summary, $course->summaryformat, ['context' => $coursecontext, 'noclean' => true]),
                    0,
                    false
                );
                $summary = shorten_text(trim($plain), 160);
            }

            $price      = function_exists('theme_nit_course_price') ? theme_nit_course_price((int) $course->id) : '';
            $teacher    = function_exists('theme_nit_course_teacher') ? theme_nit_course_teacher((int) $course->id) : '';
            $pricelabel = $price !== '' ? $price : $t('Free', 'مجانًا');
            $info       = $nitcourseinfo($course->id);

            $detailsurl = $courseurl->out();
            $enrolurl   = (new moodle_url('/local/nit_subscriptions/enrol.php',
                ['courseid' => $course->id, 'sesskey' => sesskey()]))->out(false);
        ?>
        <!-- Course Card: fixed min-height + stretch grid => every card is the same size. -->
        <div style="background: var(--cbg2); border: 1px solid color-mix(in srgb, var(--cborder) 55%, transparent); border-radius: 16px; padding: 22px; display: flex; flex-direction: column; height: 100%; min-height: 320px; transition: box-shadow 0.3s ease;" onmouseover="this.style.boxShadow='0 12px 28px rgba(0,0,0,0.38)';" onmouseout="this.style.boxShadow='none';">

          <!-- Category name pill: rounded tint + circle icon (matches nested titles) -->
          <div class="nit-card-cat">
            <span class="nit-card-cat-dot"></span>
            <span><?= $sectionname ?></span>
          </div>

          <!-- Course name -->
          <h3 style="font-size: 18px; font-weight: bold; color: var(--ctext1); margin: 0 0 10px; line-height: 1.4;">
            <?= $coursename ?>
          </h3>

          <?php if ($teacher !== ''): ?>
          <div style="font-size: 12px; color: var(--ctext2); margin: 0 0 10px;">
            👤 <?= s($teacher) ?>
          </div>
          <?php endif; ?>

          <!-- Course description -->
          <?php if ($summary !== ''): ?>
          <p style="font-size: 13px; color: var(--ctext2); line-height: 1.7; margin: 0; display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden;">
            <?= s($summary) ?>
          </p>
          <?php endif; ?>

          <!-- Footer: pinned to the bottom. A fixed-height status/price row sits above the
               buttons so the buttons never move — a free course simply leaves it empty,
               a paid course shows its price in the SAME reserved slot. -->
          <div style="margin-top: auto; padding-top: 18px;">
            <div style="min-height: 30px; display: flex; align-items: center; flex-wrap: wrap; gap: 8px; margin-bottom: 12px;">
              <?php if ($info['enrolled']): ?>
                <span style="display: inline-flex; align-items: center; gap: 5px; background: color-mix(in srgb, var(--csuccess) 16%, transparent); color: var(--csuccess); border: 1px solid color-mix(in srgb, var(--csuccess) 45%, transparent); font-size: 12px; font-weight: bold; padding: 4px 12px; border-radius: 50px;">
                  ✓ <?= $t('Enrolled', 'مُسجَّل') ?>
                </span>
              <?php elseif ($info['covered']): ?>
                <span style="display: inline-flex; align-items: center; gap: 5px; background: color-mix(in srgb, var(--caccent) 16%, transparent); color: var(--ctext3); border: 1px solid color-mix(in srgb, var(--caccent) 45%, transparent); font-size: 12px; font-weight: bold; padding: 4px 12px; border-radius: 50px;">
                  ★ <?= $t('In your subscription', 'ضمن اشتراكك') ?>
                </span>
              <?php elseif ($info['offerlabel'] !== '' && $info['offerfinal'] > 0): ?>
                <span style="font-size: 13px; color: var(--ctext2); text-decoration: line-through; opacity: 0.7;"><?= s($pricelabel) ?></span>
                <span style="font-size: 16px; font-weight: bold; color: var(--ctext1);"><?= s(number_format($info['offerfinal'], 0)) ?> <?= $t('EGP', 'ج.م') ?></span>
                <span style="background: var(--cbg4); color: var(--ctext4); font-size: 11px; font-weight: bold; padding: 3px 10px; border-radius: 50px;"><?= s($info['offerlabel']) ?></span>
              <?php elseif ($info['haspricing']): ?>
                <span style="font-size: 16px; font-weight: bold; color: var(--ctext1);"><?= s($pricelabel) ?></span>
              <?php else: // Free course: the slot stays empty (reserved) so buttons stay put. ?>
                <span style="font-size: 13px; font-weight: bold; color: var(--csuccess);"><?= $t('Free', 'مجانًا') ?></span>
              <?php endif; ?>
            </div>

            <!-- Actions: gallery button components (.btn-primary / .btn-outline-primary).
                 Enrolled shows one button; every other state shows two. -->
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
              <?php else: // Free course. ?>
                <a href="<?= $enrolurl ?>" class="btn btn-primary fw-bold"><?= $t('Enroll', 'التحاق') ?></a>
                <a href="<?= $detailsurl ?>" class="btn btn-outline-primary fw-bold"><?= $t('Course details', 'تفاصيل الكورس') ?></a>
              <?php endif; ?>
            </div>
          </div>
        </div>
        <?php
        };

        // Recursive section renderer: each category (at any depth) gets an X-Trade
        // style "specialty" title — pin icon + gradient text + a coloured start-border —
        // then its own course grid, then its child subcategories nested underneath with
        // the same title UI (indented to show the hierarchy).
        $rendernode = function (array $node, int $depth) use (&$rendernode, $rendercard, $counttree): void {
            $cat   = $node['cat'];
            $name  = $cat->get_formatted_name();
            $count = $counttree($node);
            $blockclass = 'nit-spec-block' . ($depth > 0 ? ' nit-spec-block--nested' : '');
        ?>
        <div class="<?= $blockclass ?>">
          <div class="nit-spec-head">
            <?php if ($depth === 0): ?>
            <!-- Top-level subcategory: pin + gradient text + coloured start-border. -->
            <h3 class="nit-spec-title">
              <span class="nit-spec-pin">📌</span>
              <span class="nit-spec-name"><?= $name ?></span>
              <span class="nit-spec-count">(<?= $count ?>)</span>
            </h3>
            <?php else: ?>
            <!-- Nested subcategory: rounded tint pill + circle icon. -->
            <h3 class="nit-spec-title nit-spec-title--sub">
              <span class="nit-spec-dot"></span>
              <span class="nit-spec-subname"><?= $name ?></span>
              <span class="nit-spec-count">(<?= $count ?>)</span>
            </h3>
            <?php endif; ?>
          </div>

          <?php if (!empty($node['courses'])): ?>
          <div class="nit-spec-grid">
            <?php foreach ($node['courses'] as $course): ?>
              <?php $rendercard($course, $name); ?>
            <?php endforeach; ?>
          </div>
          <?php endif; ?>

          <?php if (!empty($node['children'])): ?>
          <div class="nit-spec-children">
            <?php foreach ($node['children'] as $child): ?>
              <?php if ($counttree($child) > 0): ?>
                <?php $rendernode($child, $depth + 1); ?>
              <?php endif; ?>
            <?php endforeach; ?>
          </div>
          <?php endif; ?>
        </div>
        <?php
        };
      ?>

      <style>
        /* The local_payments course_cards.js appends its own price badge to the end
           of every card with a /course/view.php link. This page already renders the
           price in its footer status row (above the buttons), so that injected badge
           is a redundant duplicate here — hide it (scoped to this page only, the
           shared badge is untouched everywhere else). */
        .nit-cat-details .lp-card-badge { display: none !important; }

        /* Top-level subcategory title — X-Trade .specialty-title, on brand vars. */
        .nit-spec-block { margin-bottom: 44px; }
        .nit-spec-head { margin-bottom: 22px; }
        .nit-spec-title {
          display: inline-flex; align-items: center; gap: 10px;
          margin: 0; font-size: 29px; font-weight: 800; line-height: 1.3;
          border-inline-start: 4px solid var(--cbg4);
          padding-inline-start: 14px;
        }
        .nit-spec-title .nit-spec-name {
          color: var(--ctext3);
        }
        .nit-spec-title .nit-spec-pin { font-size: 24px; line-height: 1; }
        .nit-spec-title .nit-spec-count { font-size: 15px; font-weight: 700; color: var(--ctext3); }

        /* Nested subcategory title — rounded tint pill (50% of the accent) + circle
           icon instead of the pin; no gradient / start-border so it reads as a chip. */
        .nit-spec-title--sub {
          border-inline-start: none; padding: 8px 20px; border-radius: 50px;
          background: color-mix(in srgb, var(--caccent) 70%, transparent);
          font-size: 20px; color: var(--ctext1);
        }
        .nit-spec-title--sub .nit-spec-subname { color: var(--ctext1); }
        .nit-spec-title--sub .nit-spec-count { color: var(--ctext1); font-size: 14px; }
        .nit-spec-title--sub .nit-spec-dot {
          width: 12px; height: 12px; border-radius: 50%;
          background: var(--ctext1); flex: 0 0 auto;
        }

        .nit-spec-grid {
          display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
          gap: 24px; align-items: stretch;
        }
        /* Nested subcategory groups sit indented under their parent. */
        .nit-spec-children { margin-top: 28px; display: flex; flex-direction: column; gap: 8px; }
        .nit-spec-block--nested {
          margin-bottom: 32px;
          margin-inline-start: 20px; padding-inline-start: 16px;
          border-inline-start: 2px solid color-mix(in srgb, var(--cbg4) 18%, transparent);
        }

        /* Course-card category chip — same tint pill + circle icon as nested titles. */
        .nit-card-cat {
          align-self: flex-start; display: inline-flex; align-items: center; gap: 8px;
          background: color-mix(in srgb, var(--caccent) 70%, transparent);
          color: var(--ctext1); padding: 6px 14px; border-radius: 4px;
          font-size: 12px; font-weight: bold; margin-bottom: 16px;
        }
        .nit-card-cat-dot {
          width: 9px; height: 9px; border-radius: 50%;
          background: var(--ctext1); flex: 0 0 auto;
        }
      </style>

      <?php if (!empty($rootnodes)): ?>
        <?php foreach ($rootnodes as $node): ?>
          <?php $rendernode($node, 0); ?>
        <?php endforeach; ?>
      <?php else: ?>
      <div style="text-align: center; color: var(--ctext2); padding: 40px;">
        <?= $t('No courses found in this category.', 'لا توجد دورات في هذا التصنيف.') ?>
      </div>
      <?php endif; ?>

    </div>
  </div>
</div>

<?php
// NIT: wire the course Buy buttons to the shared checkout modal (coupon + auto offer → Kashier).
if ($nitcheckout) {
    $costr = local_nit_commerce_string_map([
        'co_title', 'co_intro', 'co_total', 'co_offer', 'co_coupon', 'co_apply', 'co_discount',
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

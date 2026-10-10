<?php
require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->libdir . '/filelib.php');
require_once($CFG->dirroot . '/local/nit_category/lib.php');

// Respect the site's forced-login policy: if the site requires login to browse,
// gate this catalogue page too (core_course_category visibility checks below
// still apply either way).
if (!empty($CFG->forcelogin)) {
    require_login();
}

$categoryid = optional_param('id', 0, PARAM_INT);   // Parent category; 0 = the whole catalogue (all courses).
$subid      = optional_param('sub', 0, PARAM_INT);  // 0 = "All" (every subcategory as its own section).
// Search text (?q=; the navbar search box's "all results" lands here): kept as typed for
// the box, matched lower-cased on the course names and summary (catalogue::matches_text()).
$qtext      = core_text::substr(trim(optional_param('q', '', PARAM_TEXT)), 0, 100);

// Parent category. id=0 → the top category, i.e. the global "All courses" catalogue
// (every top-level category becomes a section).
$istop    = ($categoryid === 0);
$category = $istop ? core_course_category::top() : core_course_category::get($categoryid, MUST_EXIST);
$context  = $istop ? context_system::instance() : $category->get_context();

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

$PAGE->set_url(new moodle_url('/local/nit_category/index.php',
    ['id' => $categoryid, 'sub' => $subid] + ($qtext !== '' ? ['q' => $qtext] : [])));
$PAGE->set_context($context);
$pagetitle = $istop ? get_string('courses') : $category->get_formatted_name();
$PAGE->set_title($pagetitle);
$PAGE->set_heading($pagetitle);
// NIT full-width layout: navbar + footer only, no page heading / secondary nav.
$PAGE->set_pagelayout('nit_fullwidth');

// Build the sections to render. Each section = one category header + the cards of
// its courses, so courses always sit under their own category name:
//   * "All" (sub = 0)  -> one section per direct subcategory, plus a leading
//                         section for any course sitting directly under the parent.
//   * a chosen subcat  -> just that one section.
//   * no subcategories -> a single section for the (leaf) parent itself.
// Each course's category is thus its enclosing section, matching the header above it.
// Filters from the catalog controls (?sort= ?price= ?level= ?rating=) — all REAL,
// Moodle-backed data, validated and applied by local_nit_category\catalogue (shared
// with the mobile API, so both always agree):
//   sort   recommended | newest | az
//   price  all | free | paid  (paid iff local_payments has pricing; no-op without it)
//   level  the "level" course custom field (only offered when the field exists)
//   rating minimum average stars from local_nit_reviews (only when present)
$filters = \local_nit_category\catalogue::normalise_filters(
    optional_param('sort', 'recommended', PARAM_ALPHA),
    optional_param('price', 'all', PARAM_ALPHA),
    optional_param('level', '', PARAM_TEXT),
    optional_param('rating', 0, PARAM_INT)
);
$sort         = $filters['sort'];
$pricefilter  = $filters['price'];
$levelfilter  = $filters['level'];
$ratingfilter = $filters['rating'];
$filters['q'] = \local_nit_category\catalogue::normalise_query($qtext);
$leveloptions = \local_nit_category\course_meta::level_options();
$hasreviews   = \local_nit_category\catalogue::has_reviews();

// Total courses in a node's whole subtree (its own + every descendant's).
$counttree = static fn(array $node): int => \local_nit_category\catalogue::count_tree($node);

// The category "nodes" to render (each subcategory, at any depth, is its own titled
// group): filtered, with empty subtrees dropped.
$rootnodes = \local_nit_category\catalogue::root_nodes($category, $subcategories,
    $subid ? $targetcat : null, $filters);

// Tally the visible total.
$totalcourses = 0;
foreach ($rootnodes as $n) {
    $totalcourses += $counttree($n);
}

// Hero banner always shows the grand total for the whole parent category, regardless
// of any subcategory filter currently selected.
$bannertotal = $category->get_courses_count(['recursive' => true]);

// Category image + icon (set on the category's "Category image & icon" tab, image.php).
// The image walks uploaded → first image in the description → nearest parent's; the page
// head shows it only when there is a real one (a site logo on every category is noise).
$categoryimage = $istop ? '' : local_nit_category_get_image_url((int) $category->id);
$categoryicon  = $istop ? '' : local_nit_category_render_icon((int) $category->id, 'nit-cat-icon nit-cat-icon--h1',
    $category->get_formatted_name());

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
if (!$istop && function_exists('theme_nit_category_brand_group')) {
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

$description  = $istop ? '' : format_text($category->description, $category->descriptionformat, ['context' => $context]);
$categoryname = $istop ? $t('All courses', 'كل الدورات') : $category->get_formatted_name();

// Course pricing present → the price filter is offered.
$nitcheckout = \local_nit_category\catalogue::checkout_available();

// Every link of the page keeps the search text.
$withq = static fn(array $p): array => $qtext !== '' ? $p + ['q' => $qtext] : $p;

// The courses are drawn with THE site course card (local_academy/course_card — the same
// card as the home, teacher and subject pages), from one course query for the page.
$cardids = [];
$collectids = static function (array $node) use (&$collectids, &$cardids): void {
    foreach ($node['courses'] as $c) {
        $cardids[] = (int) $c->id;
    }
    foreach ($node['children'] as $child) {
        $collectids($child);
    }
};
array_map($collectids, $rootnodes);
$courserecords = $cardids ? $DB->get_records_list('course', 'id', $cardids) : [];

echo $OUTPUT->header();
?>
<style>
  .nit-cat{
    /* Structure (--t-*) is inherited from the ACTIVE TEMPLATE on :root (Phase 2),
       with T1 literals as fallbacks; only the accent comes from the academy brand.
       So the catalog matches whichever homepage template the academy runs. */
    --t-accent: var(--nit-brand-primary); --t-accent-2: var(--nit-brand-accent); --t-on: var(--nit-brand-on-primary, #fff);
    --t-accent-soft: color-mix(in srgb, var(--nit-brand-primary) 9%, transparent);
    font-family:'Manrope','IBM Plex Sans Arabic',system-ui,sans-serif;
    background:var(--t-bg, #FFFFFF); color:var(--t-ink, #16191D);
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
  /* The cards are local_academy/course_card (styles: theme/nit/scss/components/_bthcoursecard.scss). */
  .nit-cat-grid{ display:grid; grid-template-columns:repeat(auto-fill, minmax(260px,1fr)); gap:24px; align-items:stretch; }
  .nit-cat-block{ margin-bottom:40px; }
  .nit-cat-block--nested{ margin-inline-start:14px; }
  .nit-cat .lp-card-badge{ display:none !important; }
  /* Category image + icon (image.php). One rule for both icon kinds the renderer emits:
     an <img> (uploaded icon) or a <span> holding an emoji. */
  .nit-cat-heroimg{ width:140px; height:140px; flex:none; object-fit:cover; border-radius:20px; border:1px solid var(--t-border); background:var(--cbg3); }
  .nit-cat-headtext{ flex:1 1 320px; min-width:0; }
  .nit-cat-h1{ display:flex; align-items:center; gap:12px; }
  .nit-cat-icon{ display:inline-block; flex:none; object-fit:contain; text-align:center; line-height:1; }
  .nit-cat-icon--h1{ width:44px; height:44px; font-size:38px; }
  .nit-cat-icon--pill{ width:20px; height:20px; font-size:17px; }
  .nit-cat-icon--sect, .nit-cat-sectimg{ width:30px; height:30px; font-size:26px; align-self:center; }
  .nit-cat-sectimg{ object-fit:cover; border-radius:8px; }
  .nit-cat .nit-cat-filter .nit-cat-filtername{ display:inline-flex; align-items:center; gap:8px; font-size:14px; color:inherit; }
  @media (max-width:600px){ .nit-cat-heroimg{ width:96px; height:96px; border-radius:16px; } }
  /* Search box (same look as the navbar search pill). */
  .nit-cat-search{ display:flex; align-items:center; gap:8px; margin-top:22px; max-width:560px; }
  .nit-cat-search-box{ position:relative; flex:1; min-width:0; }
  .nit-cat-search-box svg{ position:absolute; inset-inline-start:14px; top:50%; transform:translateY(-50%); width:18px; height:18px; color:var(--t-muted); pointer-events:none; }
  .nit-cat .nit-cat-search input{ width:100%; height:46px; border:1px solid var(--t-border) !important; border-radius:9999px !important; padding-inline:42px 16px; font:inherit; font-size:15px;
    background:var(--t-bg) !important; color:var(--t-ink) !important; outline:none; transition:border-color .2s ease, box-shadow .2s ease; }
  .nit-cat .nit-cat-search input:focus{ border-color:var(--t-accent) !important; box-shadow:0 0 0 3px var(--t-accent-soft); }
  .nit-cat-search button{ height:46px; padding:0 22px; border:0; border-radius:9999px; background:var(--t-accent); color:var(--t-on); font:inherit; font-size:15px; font-weight:700; cursor:pointer; white-space:nowrap; }
  .nit-cat-search button:hover{ filter:brightness(.92); }
  .nit-cat-searchnote{ margin-top:10px; font-size:14px; color:var(--t-muted); }
  .nit-cat-searchnote a{ color:var(--t-accent); font-weight:600; margin-inline-start:6px; }
</style>
<div dir="auto" class="nit-cat<?= $brandgroupclass !== '' ? ' ' . $brandgroupclass : '' ?>" style="<?= $stylevars ?>">
  <div class="nit-cat-wrap">
    <nav class="nit-cat-crumbs">
      <a href="<?= (new moodle_url('/'))->out() ?>"><?= $t('Home', 'الرئيسية') ?></a> /
      <?php if ($istop): ?>
        <span style="color:var(--t-ink);"><?= $categoryname ?></span>
      <?php else: ?>
        <a href="<?= (new moodle_url('/local/nit_category/index.php'))->out() ?>"><?= $t('All courses', 'كل الدورات') ?></a> /
        <span style="color:var(--t-ink);"><?= $categoryname ?></span>
      <?php endif; ?>
    </nav>

    <div class="nit-cat-head">
      <?php if ($categoryimage !== ''): ?>
        <img class="nit-cat-heroimg" src="<?= s($categoryimage) ?>" alt="">
      <?php endif; ?>
      <div class="nit-cat-headtext">
        <div class="nit-cat-eyebrow"><?= $t('Catalog', 'الكتالوج') ?></div>
        <h1 class="nit-cat-h1"><?= $categoryicon ?><span><?= $categoryname ?></span></h1>
        <?php if (trim(strip_tags($description)) !== ''): ?>
          <div class="nit-cat-desc"><?= $description ?></div>
        <?php endif; ?>
        <div class="nit-cat-count"><strong><?= $totalcourses ?></strong> <?= $t('courses', 'دورة') ?> · <?= $categoryname ?></div>
        <form method="get" class="nit-cat-search" role="search" action="<?= (new moodle_url('/local/nit_category/index.php'))->out(false) ?>">
          <?php if ($categoryid): ?><input type="hidden" name="id" value="<?= (int) $categoryid ?>"><?php endif; ?>
          <?php if ($subid): ?><input type="hidden" name="sub" value="<?= (int) $subid ?>"><?php endif; ?>
          <?php if ($sort !== 'recommended'): ?><input type="hidden" name="sort" value="<?= s($sort) ?>"><?php endif; ?>
          <?php if ($pricefilter !== 'all'): ?><input type="hidden" name="price" value="<?= s($pricefilter) ?>"><?php endif; ?>
          <?php if ($levelfilter !== ''): ?><input type="hidden" name="level" value="<?= s($levelfilter) ?>"><?php endif; ?>
          <?php if ($ratingfilter > 0): ?><input type="hidden" name="rating" value="<?= (int) $ratingfilter ?>"><?php endif; ?>
          <div class="nit-cat-search-box">
            <svg viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M15.5 14h-.79l-.28-.27A6.47 6.47 0 0 0 16 9.5A6.5 6.5 0 1 0 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5S14 7.01 14 9.5S11.99 14 9.5 14"/></svg>
            <input type="search" name="q" value="<?= s($qtext) ?>" maxlength="100" autocomplete="off"
              placeholder="<?= s($t('Search courses…', 'ابحث عن كورس…')) ?>" aria-label="<?= s($t('Search courses', 'ابحث عن كورس')) ?>">
          </div>
          <button type="submit"><?= $t('Search', 'بحث') ?></button>
        </form>
        <?php if ($qtext !== ''): ?>
          <div class="nit-cat-searchnote">
            <?= $t('Results for', 'نتائج البحث عن') ?> «<?= s($qtext) ?>»
            <a href="<?= (new moodle_url('/local/nit_category/index.php', array_filter(['id' => $categoryid, 'sub' => $subid, 'sort' => $sort !== 'recommended' ? $sort : null, 'price' => $pricefilter !== 'all' ? $pricefilter : null, 'level' => $levelfilter !== '' ? $levelfilter : null, 'rating' => $ratingfilter ?: null])))->out() ?>"><?= $t('Clear search', 'مسح البحث') ?></a>
          </div>
        <?php endif; ?>
      </div>
      <form method="get" class="nit-cat-sort" action="<?= (new moodle_url('/local/nit_category/index.php'))->out(false) ?>">
        <input type="hidden" name="id" value="<?= (int) $categoryid ?>">
        <?php if ($subid): ?><input type="hidden" name="sub" value="<?= (int) $subid ?>"><?php endif; ?>
        <?php if ($pricefilter !== 'all'): ?><input type="hidden" name="price" value="<?= s($pricefilter) ?>"><?php endif; ?>
        <?php if ($levelfilter !== ''): ?><input type="hidden" name="level" value="<?= s($levelfilter) ?>"><?php endif; ?>
        <?php if ($ratingfilter > 0): ?><input type="hidden" name="rating" value="<?= (int) $ratingfilter ?>"><?php endif; ?>
        <?php if ($qtext !== ''): ?><input type="hidden" name="q" value="<?= s($qtext) ?>"><?php endif; ?>
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
          <?php if ($subid): ?><a href="<?= (new moodle_url('/local/nit_category/index.php', $withq(array_filter(['id' => $categoryid, 'sort' => $sort, 'price' => $pricefilter !== 'all' ? $pricefilter : null, 'level' => $levelfilter !== '' ? $levelfilter : null, 'rating' => $ratingfilter ?: null]))))->out() ?>"><?= $t('Clear', 'مسح') ?></a><?php endif; ?>
        </div>
        <?php
          // Preserve the active price / level / rating filters across category switches.
          $caturl = function (?int $sub) use ($withq, $categoryid, $sort, $pricefilter, $levelfilter, $ratingfilter): string {
              $p = ['id' => $categoryid, 'sort' => $sort];
              if ($sub) { $p['sub'] = $sub; }
              if ($pricefilter !== 'all') { $p['price'] = $pricefilter; }
              if ($levelfilter !== '') { $p['level'] = $levelfilter; }
              if ($ratingfilter > 0) { $p['rating'] = $ratingfilter; }
              return (new moodle_url('/local/nit_category/index.php', $withq($p)))->out();
          };
        ?>
        <a class="nit-cat-filter<?= $subid === 0 ? ' on' : '' ?>" href="<?= $caturl(null) ?>">
          <span style="font-size:14px;color:inherit;"><?= $t('All', 'الكل') ?></span>
          <span><?= (int) $bannertotal ?></span>
        </a>
        <?php foreach ($subcategories as $sc): ?>
          <?php $sccount = (int) $sc->get_courses_count(['recursive' => true]); ?>
          <a class="nit-cat-filter<?= $subid === (int) $sc->id ? ' on' : '' ?>" href="<?= $caturl((int) $sc->id) ?>">
            <span class="nit-cat-filtername"><?= local_nit_category_render_icon((int) $sc->id, 'nit-cat-icon nit-cat-icon--pill', $sc->get_formatted_name()) ?><?= $sc->get_formatted_name() ?></span>
            <span><?= $sccount ?></span>
          </a>
        <?php endforeach; ?>

        <?php if ($nitcheckout): // Price filter only makes sense when pricing exists. ?>
          <div class="nit-cat-side-head" style="margin-top:24px;"><span><?= $t('Price', 'السعر') ?></span></div>
          <?php
            $priceopts = ['all' => $t('All', 'الكل'), 'free' => $t('Free', 'مجانًا'), 'paid' => $t('Paid', 'مدفوعة')];
            $priceurl = function (string $pk) use ($withq, $categoryid, $subid, $sort, $levelfilter, $ratingfilter): string {
                $p = ['id' => $categoryid, 'sort' => $sort];
                if ($subid) { $p['sub'] = $subid; }
                if ($pk !== 'all') { $p['price'] = $pk; }
                if ($levelfilter !== '') { $p['level'] = $levelfilter; }
                if ($ratingfilter > 0) { $p['rating'] = $ratingfilter; }
                return (new moodle_url('/local/nit_category/index.php', $withq($p)))->out();
            };
            foreach ($priceopts as $pk => $plabel): ?>
              <a class="nit-cat-filter<?= $pricefilter === $pk ? ' on' : '' ?>" href="<?= $priceurl($pk) ?>">
                <span style="font-size:14px;color:inherit;"><?= $plabel ?></span>
              </a>
          <?php endforeach; ?>
        <?php endif; ?>

        <?php if (!empty($leveloptions)): // Level filter — only when the field exists. ?>
          <div class="nit-cat-side-head" style="margin-top:24px;"><span><?= get_string('level', 'local_nit_category') ?></span></div>
          <?php
            $levelurl = function (string $lv) use ($withq, $categoryid, $subid, $sort, $pricefilter, $ratingfilter): string {
                $p = ['id' => $categoryid, 'sort' => $sort];
                if ($subid) { $p['sub'] = $subid; }
                if ($pricefilter !== 'all') { $p['price'] = $pricefilter; }
                if ($lv !== '') { $p['level'] = $lv; }
                if ($ratingfilter > 0) { $p['rating'] = $ratingfilter; }
                return (new moodle_url('/local/nit_category/index.php', $withq($p)))->out();
            };
          ?>
          <a class="nit-cat-filter<?= $levelfilter === '' ? ' on' : '' ?>" href="<?= $levelurl('') ?>">
            <span style="font-size:14px;color:inherit;"><?= $t('All', 'الكل') ?></span>
          </a>
          <?php foreach ($leveloptions as $lv): ?>
            <a class="nit-cat-filter<?= $levelfilter === $lv ? ' on' : '' ?>" href="<?= $levelurl($lv) ?>">
              <span style="font-size:14px;color:inherit;"><?= s($lv) ?></span>
            </a>
          <?php endforeach; ?>
        <?php endif; ?>

        <?php if ($hasreviews): // Rating filter — only when the reviews plugin is present. ?>
          <div class="nit-cat-side-head" style="margin-top:24px;"><span><?= get_string('rating', 'local_nit_reviews') ?></span></div>
          <?php
            $ratingurl = function (int $rv) use ($withq, $categoryid, $subid, $sort, $pricefilter, $levelfilter): string {
                $p = ['id' => $categoryid, 'sort' => $sort];
                if ($subid) { $p['sub'] = $subid; }
                if ($pricefilter !== 'all') { $p['price'] = $pricefilter; }
                if ($levelfilter !== '') { $p['level'] = $levelfilter; }
                if ($rv > 0) { $p['rating'] = $rv; }
                return (new moodle_url('/local/nit_category/index.php', $withq($p)))->out();
            };
            $ratingopts = [0 => $t('All', 'الكل'), 4 => '★★★★ ' . $t('& up', 'فأكثر'), 3 => '★★★ ' . $t('& up', 'فأكثر')];
            foreach ($ratingopts as $rv => $rlabel): ?>
              <a class="nit-cat-filter<?= $ratingfilter === $rv ? ' on' : '' ?>" href="<?= $ratingurl($rv) ?>">
                <span style="font-size:14px;color:inherit;"><?= $rlabel ?></span>
              </a>
          <?php endforeach; ?>
        <?php endif; ?>
      </aside>

      <main class="nit-cat-main" data-bthtp>
        <?php
          // THE site course card (local_academy/course_card), drawn from the course record
          // like on the home, teacher and subject pages; the year label is the course's
          // own category, as there.
          $rendercard = function (core_course_list_element $course, string $sectionname) use ($courserecords, $OUTPUT): void {
              $record = $courserecords[(int) $course->id] ?? null;
              if (!$record) {
                  return;
              }
              $card = \local_academy\local\home_data::course_card($record, $sectionname);
              echo $OUTPUT->render_from_template('local_academy/course_card', \local_academy\local\home_data::card_view($card));
          };

          // Section renderer: a light T1 heading + the course grid, children nested.
          $rendernode = function (array $node, int $depth) use (&$rendernode, $rendercard, $counttree): void {
              $cat   = $node['cat'];
              $name  = $cat->get_formatted_name();
              $count = $counttree($node);
          ?>
          <div class="nit-cat-block<?= $depth > 0 ? ' nit-cat-block--nested' : '' ?>">
            <?php
              // Beside the name: the category's own icon, else its own image (no inheriting —
              // every subcategory of an imaged parent would look the same), else nothing.
              $secicon = local_nit_category_render_icon((int) $cat->id, 'nit-cat-icon nit-cat-icon--sect', $name);
              $secimage = $secicon === '' ? local_nit_category_get_image_url((int) $cat->id, false) : '';
            ?>
            <h2 class="nit-cat-secttitle"><?= $secicon ?><?php if ($secimage !== ''): ?><img class="nit-cat-sectimg" src="<?= s($secimage) ?>" alt=""><?php endif; ?><?= $name ?> <span class="c">(<?= $count ?>)</span></h2>
            <?php if (!empty($node['courses'])): ?>
              <div class="nit-cat-grid nit-brand-18">
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
          } else if ($qtext !== '') {
              echo '<div style="text-align:center; color:var(--t-muted); padding:40px;">'
                  . $t('No courses match your search.', 'مفيش كورسات مطابقة لبحثك.') . '</div>';
          } else {
              echo '<div style="text-align:center; color:var(--t-muted); padding:40px;">' . $t('No courses found in this category.', 'لا توجد دورات في هذا التصنيف.') . '</div>';
          }
        ?>
      </main>
    </div>
  </div>
</div>
<?php

// "… عرض باقي التفاصيل" on the cards (local_academy/course_cards).
echo $OUTPUT->render_from_template('local_academy/course_cards_js', []);

echo $OUTPUT->footer();

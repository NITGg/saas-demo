<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Fill a development site with demo Years (categories), teachers and courses.
 *
 * - Years = course categories (REQUIREMENTS: "Categories = Year only"): the three
 *   preparatory and three secondary years, bilingual names ({mlang}).
 * - Demo teachers (auth "nologin" — they cannot sign in; no passwords) with a
 *   "لقب المدرس" title.
 * - Courses per year with a summary, a few lesson pages, a teacher, a study
 *   system / division, a price (local_payments) and "is-special" on some.
 * - Content: course names / summaries and teacher titles / bios in Arabic and
 *   English, a drawn cover picture (SVG) per demo course and a drawn photo (PNG)
 *   per teacher — also for the site's own test teacher and its course.
 *
 * Idempotent: categories are matched by ID number, teachers by username and
 * courses by short name — running it again creates nothing twice and only
 * fills what is missing. Nothing existing is deleted.
 *
 *   php public/local/academy/cli/seed_demo.php
 *
 * @package    local_academy
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');
require_once($CFG->libdir . '/testing/generator/lib.php'); // The core data generator (also used by tool_generator).
require_once($CFG->dirroot . '/user/profile/lib.php');
require_once($CFG->dirroot . '/course/lib.php');

[$options, $unrecognised] = cli_get_params(['help' => false], ['h' => 'help']);
if ($options['help']) {
    echo "Fill a development site with demo years (categories), teachers and courses.\n\n"
        . "  php public/local/academy/cli/seed_demo.php\n";
    exit(0);
}

\core\session\manager::set_user(get_admin());
$gen = new testing_data_generator();
$mlang = static fn(string $en, string $ar): string => '{mlang en}' . $en . '{mlang}{mlang ar}' . $ar . '{mlang}';

// --- Years (categories) ------------------------------------------------------
$years = [
    'year-prep1' => ['First Year of Middle School', 'الصف الاول الاعدادى'],
    'year-prep2' => ['Second Year of Middle School', 'الصف الثاني الاعدادي'],
    'year-prep3' => ['Third Year of Middle School', 'الصف الثالث الاعدادي'],
    'year-sec1' => ['First Year of Secondary School', 'الصف الأول الثانوي'],
    'year-sec2' => ['Second Year of Secondary School', 'الصف الثاني الثانوي'],
    'year-sec3' => ['Third Year of Secondary School', 'الصف الثالث الثانوي'],
];
$catids = [];
foreach ($years as $idnumber => [$en, $ar]) {
    $name = $mlang($en, $ar);
    $id = $DB->get_field('course_categories', 'id', ['idnumber' => $idnumber]);
    if (!$id) {
        // Reuse a top-level category that already has this name (e.g. the one made by hand).
        $id = $DB->get_field('course_categories', 'id', ['name' => $name, 'parent' => 0]);
        if ($id) {
            core_course_category::get($id, MUST_EXIST, true)->update(['idnumber' => $idnumber]);
            cli_writeln("Year: tagged \"$ar\" (#$id)");
        } else {
            $id = core_course_category::create(['name' => $name, 'idnumber' => $idnumber, 'parent' => 0])->id;
            cli_writeln("Year: created \"$ar\" (#$id)");
        }
    }
    $catids[$idnumber] = (int) $id;
}

// --- Teachers ----------------------------------------------------------------
// username => [first, last, title ar, title en, gender, bio ar, bio en]
$teachers = [
    'demo_teacher1' => ['أحمد', 'سمير', 'أستاذ اللغة العربية', 'Arabic teacher', 'm',
        'خبرة أكثر من 15 سنة في تدريس اللغة العربية للمرحلة الثانوية، بأسلوب بسيط يخلّي النحو والبلاغة سهلين.',
        '15+ years teaching Arabic to secondary students, with a simple style that makes grammar and rhetoric easy.'],
    'demo_teacher2' => ['منى', 'عادل', 'أستاذة اللغة الإنجليزية', 'English teacher', 'f',
        'متخصصة في تبسيط قواعد اللغة الإنجليزية ومهارات الكتابة، وبتدرّب الطلاب على أسئلة الامتحان من أول حصة.',
        'Makes English grammar and writing simple, and trains students on exam-style questions from the first lesson.'],
    'demo_teacher3' => ['هشام', 'فؤاد', 'دكتور الفيزياء', 'Physics teacher', 'm',
        'دكتور فيزياء بيشرح بالتجارب والرسومات، وبيحل كل فكرة بمسائل متدرّجة من السهل للصعب.',
        'A physics doctor who teaches with experiments and drawings, and solves every idea with step-by-step problems.'],
    'demo_teacher4' => ['سارة', 'نبيل', 'أستاذة الكيمياء والعلوم', 'Chemistry & science teacher', 'f',
        'بتربط الكيمياء بالحياة اليومية، ومعاها ملخصات وخرائط ذهنية لكل باب.',
        'Connects chemistry to everyday life, with summaries and mind maps for every chapter.'],
    'demo_teacher5' => ['عمرو', 'حسن', 'أستاذ التاريخ والدراسات', 'History teacher', 'm',
        'بيحكي التاريخ كقصة متسلسلة سهلة الحفظ، مع مراجعات مركّزة قبل الامتحانات.',
        'Tells history as an easy-to-remember story, with focused revisions before the exams.'],
    'demo_teacher6' => ['ياسر', 'كمال', 'أستاذ الرياضيات', 'Mathematics teacher', 'm',
        'بيبني أساس قوي في الجبر والهندسة، وبيدرّب على حل المسائل بأكتر من طريقة.',
        'Builds strong foundations in algebra and geometry, and practises solving problems in more than one way.'],
];
$teacherids = [];
$mlangtext = static fn(string $ar, string $en): string => '{mlang ar}' . $ar . '{mlang}{mlang en}' . $en . '{mlang}';
foreach ($teachers as $username => [$first, $last, $titlear, $titleen, $gender, $bioar, $bioen]) {
    $user = $DB->get_record('user', ['username' => $username, 'deleted' => 0]);
    if (!$user) {
        // "nologin": a demo account nobody can sign in to (no password is set).
        $user = $gen->create_user(['username' => $username, 'firstname' => $first, 'lastname' => $last,
            'email' => $username . '@example.com', 'auth' => 'nologin', 'lang' => 'ar']);
        cli_writeln("Teacher: created $first $last ($username)");
    }
    // Title and bio in both languages — only where still empty or the first (Arabic-only) demo text.
    $current = \local_academy\local\user_fields::values((int) $user->id)['teachertitle'] ?? '';
    if ($current === '' || $current === $titlear) {
        profile_save_custom_fields($user->id, ['teachertitle' => $mlangtext($titlear, $titleen)]);
    }
    if (trim(strip_tags((string) $user->description)) === '') {
        $DB->update_record('user', (object) ['id' => $user->id, 'description' => '<p>' . $mlangtext($bioar, $bioen) . '</p>',
            'descriptionformat' => FORMAT_HTML]);
    }
    seed_demo_avatar($user, $gender);
    $teacherids[$username] = (int) $user->id;
}

// --- Courses -------------------------------------------------------------------
// shortname => [year, fullname, teacher, system, division ('' = every division), price, is-special, summary, lessons]
$courses = [
    'demo-ar-sec3' => ['year-sec3', 'اللغة العربية - الصف الثالث الثانوي', 'demo_teacher1', 'عام', '', 175, 1,
        'شرح كامل لمنهج اللغة العربية للصف الثالث الثانوي: النحو والبلاغة والأدب والقراءة، مع امتحانات شاملة على كل جزء.',
        ['النحو: الاستثناء', 'البلاغة: التشبيه', 'الأدب: مدرسة الإحياء']],
    'demo-phy-sec3' => ['year-sec3', 'الفيزياء - الصف الثالث الثانوي', 'demo_teacher3', 'عام', 'علمى رياضة', 200, 1,
        'الكهربية التيارية والكهرومغناطيسية والفيزياء الحديثة بأسلوب مبسط وتجارب محلولة خطوة بخطوة.',
        ['قانون أوم', 'دوائر التيار الكهربي', 'الحث الكهرومغناطيسي', 'ازدواجية الموجة والجسيم']],
    'demo-chem-sec3' => ['year-sec3', 'الكيمياء - الصف الثالث الثانوي', 'demo_teacher4', 'عام', 'علمى علوم', 190, 0,
        'العناصر الانتقالية والتحليل الكيميائي والاتزان والكيمياء العضوية مع مراجعات ليلة الامتحان.',
        ['العناصر الانتقالية', 'التحليل الكيميائي', 'الاتزان الكيميائي']],
    'demo-hist-sec3' => ['year-sec3', 'التاريخ - الصف الثالث الثانوي', 'demo_teacher5', 'عام', 'ادبى', 150, 1,
        'تاريخ مصر الحديث والمعاصر في قصص سهلة الحفظ، وخرائط ذهنية لكل فصل.',
        ['محمد علي وبناء الدولة', 'الثورة العرابية', 'ثورة 1919']],
    'demo-en-sec3' => ['year-sec3', 'اللغة الإنجليزية - الصف الثالث الثانوي', 'demo_teacher2', 'عام', '', 160, 0,
        'Grammar, vocabulary and the novel, with exam-style practice for every unit.',
        ['Unit 1: Vocabulary', 'Unit 1: Grammar', 'The novel: chapters 1-3']],
    'demo-ar-sec2' => ['year-sec2', 'اللغة العربية - الصف الثاني الثانوي', 'demo_teacher1', 'عام', '', 150, 1,
        'منهج اللغة العربية للصف الثاني الثانوي كاملًا مع تدريبات على كل درس.',
        ['النحو: الحال', 'البلاغة: الاستعارة']],
    'demo-phy-sec2' => ['year-sec2', 'الفيزياء - الصف الثاني الثانوي', 'demo_teacher3', 'عام', 'علمى رياضة', 175, 0,
        'الموجات والضوء والموائع والحرارة مع مسائل محلولة من الامتحانات السابقة.',
        ['الحركة الموجية', 'الضوء', 'الموائع الساكنة']],
    'demo-en-sec2' => ['year-sec2', 'اللغة الإنجليزية - الصف الثاني الثانوي', 'demo_teacher2', 'عام', '', 140, 0,
        'Units, grammar and writing skills for second secondary.',
        ['Unit 1', 'Unit 2']],
    'demo-ar-sec1' => ['year-sec1', 'اللغة العربية - الصف الأول الثانوي', 'demo_teacher1', 'عام', '', 125, 0,
        'بداية قوية في المرحلة الثانوية: النحو والقراءة والنصوص.',
        ['النحو: المبتدأ والخبر', 'القراءة']],
    'demo-sci-sec1' => ['year-sec1', 'العلوم المتكاملة - الصف الأول الثانوي', 'demo_teacher4', 'عام', '', 130, 1,
        'العلوم المتكاملة للصف الأول الثانوي بأسلوب عملي وتجارب مصورة.',
        ['المادة وتركيبها', 'الطاقة وتحولاتها']],
    'demo-math-prep3' => ['year-prep3', 'الرياضيات - الصف الثالث الاعدادي', 'demo_teacher6', '', '', 100, 0,
        'الجبر والهندسة وحساب المثلثات للشهادة الإعدادية.',
        ['المعادلات', 'حساب المثلثات']],
    'demo-ar-prep2' => ['year-prep2', 'اللغة العربية - الصف الثاني الاعدادي', 'demo_teacher1', '', '', 90, 0,
        'النحو والقراءة والتعبير للصف الثاني الإعدادي.',
        ['النحو: كان وأخواتها']],
    'demo-math-prep1' => ['year-prep1', 'الرياضيات - الصف الأول الاعدادي', 'demo_teacher6', '', '', 90, 0,
        'الأعداد النسبية والجبر والهندسة للصف الأول الإعدادي.',
        ['الأعداد النسبية', 'المقادير الجبرية']],
];

$structure = \local_academy\local\academic_structure::get();
$systems = array_column($structure['systems'], 'name');
$divisions = \local_academy\local\academic_structure::all_divisions($structure);
$handler = \core_course\customfield\course_handler::create();
$editingteacher = (int) $DB->get_field('role', 'id', ['shortname' => 'editingteacher']);
// Position of an Arabic name in a list whose entries may be "{mlang ar}…{mlang}{mlang en}…{mlang}".
$position = static function(string $name, array $list) {
    foreach ($list as $i => $option) {
        if ($name !== '' && ($option === $name || str_contains($option, '{mlang ar}' . $name . '{mlang}'))) {
            return $i;
        }
    }
    return false;
};

foreach ($courses as $shortname => [$year, $fullname, $teacher, $system, $division, $price, $special, $summary, $lessons]) {
    $course = $DB->get_record('course', ['shortname' => $shortname]);
    if (!$course) {
        $course = $gen->create_course(['shortname' => $shortname, 'fullname' => $fullname, 'category' => $catids[$year],
            'summary' => $summary, 'summaryformat' => FORMAT_HTML, 'format' => 'topics', 'numsections' => 1]);
        cli_writeln("Course: created \"$fullname\" (#$course->id)");
    }
    // Lesson pages — only into a course that has no activities yet.
    if (!$DB->record_exists('course_modules', ['course' => $course->id])) {
        foreach ($lessons as $i => $lesson) {
            $gen->create_module('page', ['course' => $course->id, 'section' => 1,
                'name' => 'الدرس ' . ($i + 1) . ': ' . $lesson, 'intro' => '', 'content' => '<p>' . s($lesson) . '</p>']);
        }
        cli_writeln("Course: added " . count($lessons) . " lessons to \"$fullname\"");
    }
    $gen->enrol_user($teacherids[$teacher], $course->id, $editingteacher);

    // Course custom fields: is-special, study system, division (dropdown answers are 1-based positions).
    $data = ['id' => $course->id, 'customfield_' . \local_academy\local\course_fields::SPECIAL => $special];
    $pos = $position($system, $systems);
    $data['customfield_' . \local_academy\local\academic_structure::COURSE_SYSTEM] = $pos === false ? 0 : $pos + 1;
    $pos = $position($division, $divisions);
    $data['customfield_' . \local_academy\local\academic_structure::COURSE_DIVISION] = $pos === false ? 0 : $pos + 1;
    $handler->instance_form_save((object) $data);

    // Price (local_payments default rule, EGP), only when the course has none.
    if ($DB->get_manager()->table_exists('local_payments_course_prices')
            && !$DB->record_exists('local_payments_course_prices', ['courseid' => $course->id])) {
        $DB->insert_record('local_payments_course_prices', (object) ['courseid' => $course->id, 'country' => '*',
            'currency' => 'EGP', 'price' => $price, 'is_default' => 1, 'is_active' => 1, 'priority' => 0,
            'created_by' => get_admin()->id, 'timecreated' => time(), 'timemodified' => time()]);
    }
}


// --- Content: both languages + pictures ----------------------------------------
// Only text that is still the first (Arabic-only) demo text is replaced, and a picture is
// added only where there is none, so edits made on the site are never overwritten.
$subjects = [ // short-name prefix => [Arabic, English, colour from, colour to]
    'ar' => ['اللغة العربية', 'Arabic Language', '#0f766e', '#14b8a6'],
    'phy' => ['الفيزياء', 'Physics', '#1d4ed8', '#38bdf8'],
    'chem' => ['الكيمياء', 'Chemistry', '#7c3aed', '#c084fc'],
    'hist' => ['التاريخ', 'History', '#b45309', '#f59e0b'],
    'en' => ['اللغة الإنجليزية', 'English Language', '#be123c', '#fb7185'],
    'sci' => ['العلوم المتكاملة', 'Integrated Sciences', '#15803d', '#4ade80'],
    'math' => ['الرياضيات', 'Mathematics', '#1e3a8a', '#6366f1'],
];
$yearnames = [ // year => [Arabic, English]
    'year-prep1' => ['الصف الأول الإعدادي', 'First Preparatory'],
    'year-prep2' => ['الصف الثاني الإعدادي', 'Second Preparatory'],
    'year-prep3' => ['الصف الثالث الإعدادي', 'Third Preparatory'],
    'year-sec1' => ['الصف الأول الثانوي', 'First Secondary'],
    'year-sec2' => ['الصف الثاني الثانوي', 'Second Secondary'],
    'year-sec3' => ['الصف الثالث الثانوي', 'Third Secondary'],
];
$summaries = [ // shortname => [Arabic, English] (null = the summary above is already that language)
    'demo-ar-sec3' => [null, 'The full Arabic curriculum for third secondary: grammar, rhetoric, literature and reading, with a full exam on every part.'],
    'demo-phy-sec3' => [null, 'Electric circuits, electromagnetism and modern physics, explained simply with experiments solved step by step.'],
    'demo-chem-sec3' => [null, 'Transition elements, chemical analysis, equilibrium and organic chemistry, with night-before-the-exam revisions.'],
    'demo-hist-sec3' => [null, 'The modern history of Egypt as easy-to-remember stories, with a mind map for every chapter.'],
    'demo-en-sec3' => ['القواعد والمفردات والقصة، مع تدريب على أسئلة الامتحان في كل وحدة.', null],
    'demo-ar-sec2' => [null, 'The whole Arabic curriculum for second secondary, with exercises on every lesson.'],
    'demo-phy-sec2' => [null, 'Waves, light, fluids and heat, with problems solved from previous exams.'],
    'demo-en-sec2' => ['الوحدات والقواعد ومهارات الكتابة للصف الثاني الثانوي.', null],
    'demo-ar-sec1' => [null, 'A strong start in secondary school: grammar, reading and texts.'],
    'demo-sci-sec1' => [null, 'Integrated sciences for first secondary, practical and with illustrated experiments.'],
    'demo-math-prep3' => [null, 'Algebra, geometry and trigonometry for the preparatory certificate.'],
    'demo-ar-prep2' => [null, 'Grammar, reading and writing for second preparatory.'],
    'demo-math-prep1' => [null, 'Rational numbers, algebra and geometry for first preparatory.'],
];
$lessonsen = [ // shortname => the lessons above in English
    'demo-ar-sec3' => ['Grammar: exception', 'Rhetoric: simile', 'Literature: the Revival school'],
    'demo-phy-sec3' => ["Ohm's law", 'Electric circuits', 'Electromagnetic induction', 'Wave–particle duality'],
    'demo-chem-sec3' => ['Transition elements', 'Chemical analysis', 'Chemical equilibrium'],
    'demo-hist-sec3' => ['Muhammad Ali and building the state', 'The Urabi revolt', 'The 1919 revolution'],
    'demo-en-sec3' => ['الوحدة 1: المفردات', 'الوحدة 1: القواعد', 'القصة: الفصول 1-3'], // Arabic: these lessons are in English.
    'demo-ar-sec2' => ['Grammar: the circumstantial accusative', 'Rhetoric: metaphor'],
    'demo-phy-sec2' => ['Wave motion', 'Light', 'Static fluids'],
    'demo-en-sec2' => ['الوحدة 1', 'الوحدة 2'],
    'demo-ar-sec1' => ['Grammar: subject and predicate', 'Reading'],
    'demo-sci-sec1' => ['Matter and its structure', 'Energy and its forms'],
    'demo-math-prep3' => ['Equations', 'Trigonometry'],
    'demo-ar-prep2' => ['Grammar: kana and its sisters'],
    'demo-math-prep1' => ['Rational numbers', 'Algebraic expressions'],
];
$fs = get_file_storage();
foreach ($courses as $shortname => [$year, $fullname, $teacher, , , , , $summary, $lessons]) {
    $course = $DB->get_record('course', ['shortname' => $shortname], '*', MUST_EXIST);
    // Lesson pages: "الدرس 1: …" / "Lesson 1: …" (for the English course the lesson titles are English).
    $english = str_starts_with($shortname, 'demo-en-');
    foreach ($lessons as $i => $lesson) {
        $page = $DB->get_record('page', ['course' => $course->id, 'name' => 'الدرس ' . ($i + 1) . ': ' . $lesson]);
        if (!$page) {
            continue;
        }
        $other = $lessonsen[$shortname][$i];
        [$ar, $en] = $english ? [$other, $lesson] : [$lesson, $other];
        $DB->update_record('page', (object) ['id' => $page->id, 'timemodified' => time(),
            'name' => $mlangtext('الدرس ' . ($i + 1) . ': ' . $ar, 'Lesson ' . ($i + 1) . ': ' . $en),
            'content' => '<p>' . $mlangtext(s($ar), s($en)) . '</p>']);
    }
    if ($course->lang === 'ar') {
        // A course language forces the whole course into Arabic; the site language should decide.
        $DB->set_field('course', 'lang', '', ['id' => $course->id]);
    }
    $subject = explode('-', $shortname)[1];
    [$subjar, $subjen, $from, $to] = $subjects[$subject];
    [$yearar, $yearen] = $yearnames[$year];
    $update = [];
    if ($course->fullname === $fullname) {
        $update['fullname'] = $mlangtext($fullname, "$subjen — $yearen");
    }
    if ($course->summary === $summary) {
        [$sumar, $sumen] = $summaries[$shortname];
        $update['summary'] = '<p>' . $mlangtext($sumar ?? $summary, $sumen ?? $summary) . '</p>';
        $update['summaryformat'] = FORMAT_HTML;
    }
    if ($update) {
        $DB->update_record('course', (object) (['id' => $course->id] + $update));
        cli_writeln("Course: \"$fullname\" now in Arabic and English");
    }
    $context = context_course::instance($course->id);
    if ($fs->is_area_empty($context->id, 'course', 'overviewfiles', 0)) {
        $name = $teachers[$teacher][0] . ' ' . $teachers[$teacher][1];
        $fs->create_file_from_string(['contextid' => $context->id, 'component' => 'course', 'filearea' => 'overviewfiles',
            'itemid' => 0, 'filepath' => '/', 'filename' => 'cover.svg'],
            seed_demo_cover($subject, $subjar, $subjen, $yearar, $name, $from, $to));
        cache::make('core', 'course_image')->delete($course->id);
        cli_writeln("Course: cover picture for \"$fullname\"");
    }
    rebuild_course_cache($course->id, true);
}

// The site's own test teacher (made by hand) and its course: translate, add a bio and a photo.
if ($course = $DB->get_record('course', ['shortname' => 'mab1'])) {
    if ($course->fullname === 'الإدارة والأعمال 1') {
        $DB->update_record('course', (object) ['id' => $course->id,
            'fullname' => $mlangtext('الإدارة والأعمال 1', 'Business Administration 1'),
            'summary' => '<p>' . $mlangtext('دراسة الإدارة وريادة الأعمال للشركات والمؤسسات',
                'Management and entrepreneurship for companies and organisations') . '</p>']);
        rebuild_course_cache($course->id, true);
        cli_writeln('Course: "الإدارة والأعمال 1" now in Arabic and English');
    }
}
if ($user = $DB->get_record('user', ['username' => 'nit_test_teacher', 'deleted' => 0])) {
    $current = \local_academy\local\user_fields::values((int) $user->id)['teachertitle'] ?? '';
    if ($current === '' || $current === 'أستاذ الإدارة والأعمال') {
        profile_save_custom_fields($user->id, ['teachertitle' => $mlangtext('أستاذ الإدارة والأعمال', 'Business teacher')]);
    }
    if (trim(strip_tags((string) $user->description)) === '') {
        $DB->update_record('user', (object) ['id' => $user->id, 'descriptionformat' => FORMAT_HTML,
            'description' => '<p>' . $mlangtext('بيشرح الإدارة وريادة الأعمال بأمثلة من شركات حقيقية ومشاريع عملية.',
                'Teaches management and entrepreneurship with examples from real companies and hands-on projects.') . '</p>']);
    }
    seed_demo_avatar($user, 'm');
}

\local_academy\local\academic_structure::sync_years();
purge_all_caches();
cli_writeln('Done.');

/**
 * A course cover picture (SVG, 800x600): subject colours, an icon, the subject and the year.
 *
 * @param string $subject short-name subject key (ar, phy, chem, hist, en, sci, math)
 * @param string $title Arabic subject name
 * @param string $titleen English subject name
 * @param string $year Arabic year name
 * @param string $teacher teacher's name
 * @param string $from gradient start colour
 * @param string $to gradient end colour
 * @return string SVG
 */
function seed_demo_cover(string $subject, string $title, string $titleen, string $year, string $teacher,
        string $from, string $to): string {
    $icons = [
        'ar' => '<path d="M-90 -50 Q-45 -70 0 -50 V60 Q-45 40 -90 60 Z M90 -50 Q45 -70 0 -50 V60 Q45 40 90 60 Z"/>'
            . '<path d="M-70 -25 H-20 M-70 0 H-20 M-70 25 H-20 M20 -25 H70 M20 0 H70 M20 25 H70"/>',
        'phy' => '<ellipse rx="95" ry="34"/><ellipse rx="95" ry="34" transform="rotate(60)"/>'
            . '<ellipse rx="95" ry="34" transform="rotate(120)"/><circle r="12" fill="#fff"/>',
        'chem' => '<path d="M-25 -75 H25 M-15 -75 V-20 L-70 65 Q-75 75 -62 75 H62 Q75 75 70 65 L15 -20 V-75"/>'
            . '<path d="M-45 30 H45"/><circle cx="-10" cy="50" r="7" fill="#fff"/><circle cx="18" cy="42" r="5" fill="#fff"/>',
        'hist' => '<path d="M-95 -35 L0 -85 L95 -35 Z M-95 75 H95 M-85 60 H85"/>'
            . '<path d="M-65 -20 V50 M-22 -20 V50 M22 -20 V50 M65 -20 V50"/>',
        'en' => '<rect x="-100" y="-70" width="200" height="140" rx="24"/>'
            . '<text y="26" font-size="78" font-weight="700" fill="#fff" stroke="none" text-anchor="middle"'
            . ' font-family="Segoe UI, Arial, sans-serif">ABC</text>',
        'sci' => '<circle r="48"/><ellipse rx="100" ry="28" transform="rotate(-20)"/>'
            . '<circle cx="62" cy="-62" r="10" fill="#fff"/>',
        'math' => '<rect x="-90" y="-80" width="180" height="160" rx="22"/><path d="M0 -80 V80 M-90 0 H90"/>'
            . '<path d="M-60 -40 H-30 M-45 -55 V-25 M30 -40 H60 M-58 25 L-32 55 M-32 25 L-58 55 M30 40 H60"/>'
            . '<circle cx="45" cy="28" r="4" fill="#fff"/><circle cx="45" cy="52" r="4" fill="#fff"/>',
    ];
    $font = 'Tajawal, Segoe UI, Tahoma, Arial, sans-serif';
    return '<?xml version="1.0" encoding="UTF-8"?>'
        . '<svg xmlns="http://www.w3.org/2000/svg" width="800" height="600" viewBox="0 0 800 600">'
        . '<defs><linearGradient id="g" x1="0" y1="0" x2="1" y2="1">'
        . '<stop offset="0" stop-color="' . $from . '"/><stop offset="1" stop-color="' . $to . '"/></linearGradient></defs>'
        . '<rect width="800" height="600" fill="url(#g)"/>'
        . '<circle cx="720" cy="80" r="190" fill="#fff" fill-opacity=".08"/>'
        . '<circle cx="60" cy="560" r="220" fill="#fff" fill-opacity=".07"/>'
        . '<circle cx="640" cy="520" r="60" fill="#fff" fill-opacity=".09"/>'
        . '<g transform="translate(400 190)" fill="none" stroke="#fff" stroke-width="10" stroke-linecap="round"'
        . ' stroke-linejoin="round">' . $icons[$subject] . '</g>'
        . '<text x="400" y="380" font-family="' . $font . '" font-size="62" font-weight="700" fill="#fff"'
        . ' text-anchor="middle" direction="rtl">' . s($title) . '</text>'
        . '<text x="400" y="430" font-family="' . $font . '" font-size="28" fill="#fff" fill-opacity=".85"'
        . ' text-anchor="middle">' . s($titleen) . '</text>'
        . '<rect x="250" y="462" width="300" height="56" rx="28" fill="#fff" fill-opacity=".18"/>'
        . '<text x="400" y="499" font-family="' . $font . '" font-size="28" font-weight="700" fill="#fff"'
        . ' text-anchor="middle" direction="rtl">' . s($year) . '</text>'
        . '<text x="400" y="566" font-family="' . $font . '" font-size="24" fill="#fff" fill-opacity=".85"'
        . ' text-anchor="middle" direction="rtl">' . s($teacher) . '</text>'
        . '</svg>';
}

/**
 * Give a user a drawn avatar (GD) — only when they have no picture yet.
 *
 * @param stdClass $user user record
 * @param string $gender 'm' or 'f' (hijab)
 */
function seed_demo_avatar(stdClass $user, string $gender): void {
    global $CFG, $DB;
    require_once($CFG->libdir . '/gdlib.php');
    if (!empty($user->picture)) {
        return;
    }
    $palette = [['#dbeafe', '#3b82f6'], ['#fce7f3', '#db2777'], ['#dcfce7', '#16a34a'], ['#ede9fe', '#7c3aed'],
        ['#ffedd5', '#ea580c'], ['#cffafe', '#0891b2']];
    [$bg, $accent] = $palette[$user->id % count($palette)];
    $n = 1024;
    $im = imagecreatetruecolor($n, $n);
    $c = static function(string $hex) use ($im): int {
        return imagecolorallocate($im, hexdec(substr($hex, 1, 2)), hexdec(substr($hex, 3, 2)), hexdec(substr($hex, 5, 2)));
    };
    imagefill($im, 0, 0, $c($bg));
    imagefilledellipse($im, 512, 512, 860, 860, $c($accent));
    $skin = $c('#f2c9a0');
    $suit = $c($gender === 'f' ? '#334155' : '#1f2937');
    if ($gender === 'f') {
        // Hijab: a rounded veil behind the face that falls over the shoulders.
        $veil = $c('#f8fafc');
        imagefilledellipse($im, 512, 1010, 760, 560, $suit);
        imagefilledellipse($im, 512, 430, 380, 440, $veil);
        imagefilledpolygon($im, [322, 450, 702, 450, 760, 760, 264, 760], $veil);
        imagefilledellipse($im, 512, 760, 496, 160, $veil);
        imagefilledellipse($im, 512, 450, 250, 300, $skin);
    } else {
        imagefilledellipse($im, 512, 1010, 780, 560, $suit);
        imagefilledpolygon($im, [432, 735, 592, 735, 512, 900], $c('#ffffff'));
        imagefilledpolygon($im, [497, 760, 527, 760, 537, 880, 512, 910, 487, 880], $c($accent));
        imagefilledrectangle($im, 462, 560, 562, 740, $skin);
        imagefilledellipse($im, 512, 740, 100, 40, $skin);
        imagefilledellipse($im, 512, 440, 270, 320, $skin);
        $hair = $c('#2d1b10');
        imagefilledellipse($im, 512, 340, 290, 170, $hair);
        imagefilledrectangle($im, 377, 330, 400, 430, $hair);
        imagefilledrectangle($im, 624, 330, 647, 430, $hair);
    }
    // Eyes and smile.
    $dark = $c('#3f2a1d');
    imagefilledellipse($im, 462, 450, 26, 30, $dark);
    imagefilledellipse($im, 562, 450, 26, 30, $dark);
    imagesetthickness($im, 10);
    imagearc($im, 512, 520, 110, 70, 20, 160, $dark);

    // Drawn at double size and scaled down, which smooths the edges.
    $small = imagecreatetruecolor(512, 512);
    imagecopyresampled($small, $im, 0, 0, 0, 0, 512, 512, $n, $n);
    $path = make_request_directory() . '/avatar.png';
    imagepng($small, $path);
    $picture = process_new_icon(context_user::instance($user->id), 'user', 'icon', 0, $path);
    if ($picture) {
        $DB->set_field('user', 'picture', $picture, ['id' => $user->id]);
        cli_writeln("Teacher: photo for $user->firstname $user->lastname");
    }
}

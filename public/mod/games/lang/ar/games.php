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
 * Arabic strings for the Game activity.
 *
 * @package    mod_games
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'لعبة';
$string['modulename'] = 'لعبة';
$string['modulenameplural'] = 'الألعاب';
$string['modulename_help'] = 'نشاط «لعبة» بيحطّ لعبة واحدة من ركن الألعاب جوّه كورس.

تختار اللعبة وأنت بتضيف النشاط. الأطفال بيلعبوها في صفحة النشاط زي ما بيلعبوها في الركن بالظبط — نفس اللعبة ونفس الأصوات ونفس الشارات — والنقاط اللي بيجمّعوها بتتحسب في رصيدهم على مستوى الموقع كله.

اللي النشاط بيزوّده هو نص الصورة الخاص بالكورس: مين في الفصل لعبها، وعمل إيه فيها، ولو حبّيت، شرط إكمال زي «العب تلات جولات».';
$string['pluginadministration'] = 'إدارة نشاط اللعبة';

// Capabilities.
$string['games:addinstance'] = 'إضافة نشاط لعبة جديد';
$string['games:view'] = 'عرض نشاط اللعبة';
$string['games:play'] = 'لعب اللعبة وتسجيل الجولة';
$string['games:viewreports'] = 'الاطلاع على ما لعبه الفصل';

// The settings form.
$string['gamesname'] = 'اسم النشاط';
$string['thegame'] = 'اللعبة';
$string['choosegame'] = 'اللعبة';
$string['choosegame_help'] = 'أي لعبة من ركن الألعاب النشاط ده هيلعبها. الألعاب الجاهزة والشغّالة بس هي اللي بتظهر — المشرف يقدر يوقف لعبة من إدارة الموقع > الإضافات > الإضافات المحلية > التحكّم في الألعاب، وساعتها بتختفي من هنا.';
$string['nogamesavailable'] = 'مافيش ألعاب قابلة للّعب دلوقتي. كل لعبة يا إما لسّه بتتبني يا إما اتوقفت من لوحة التحكّم في الألعاب.';
$string['errorpickagame'] = 'اختار لعبة النشاط ده يلعبها.';
$string['showhublink'] = 'اعرض ركن الألعاب كله';
$string['showhublink_help'] = 'بيحطّ لينك لركن الألعاب تحت اللعبة، عشان الطفل اللي خلّص يقدر يروح يلعب الباقي. اقفله عشان تخلّي الفصل على اللعبة اللي حددتها بس.';

// Completion.
$string['completionplays'] = 'عدد الجولات:';
$string['completionplaysgroup'] = 'جولات ملعوبة';
$string['completionscore'] = 'النتيجة المطلوبة في جولة واحدة:';
$string['completionscoregroup'] = 'نتيجة متحققة';
$string['completiondetail:plays'] = 'إنهاء {$a} جولة';
$string['completiondetail:score'] = 'الوصول لـ{$a} في جولة واحدة';

// The activity page.
$string['backtocourse'] = 'رجوع للكورس';
$string['gotohub'] = 'شوف كل الألعاب';
$string['yourstanding'] = 'لعبت دي {$a->plays} مرة. أحسن جولة ليك هنا: {$a->bestscore}.';
$string['errormissinggame'] = 'النشاط ده متظبّط على لعبة مابقتش موجودة على الموقع. عدّل النشاط واختار لعبة تانية.';
$string['errorgameoff'] = '«{$a}» اتوقفت من المشرف، فمش ممكن تتلعب دلوقتي.';

// The report.
$string['report'] = 'مين لعب';
$string['reportfor'] = 'مين لعب: {$a}';
$string['viewreport'] = 'شوف مين لعب';
$string['backtoactivity'] = 'رجوع للّعبة';
$string['reportsummary'] = 'بيلعبوا {$a->game}. {$a->played} من {$a->total} طالب لعبوها مرة على الأقل.';
$string['noplayers'] = 'مافيش لحد دلوقتي طلاب مسجّلين يقدروا يلعبوا النشاط ده.';
$string['notenrolledheading'] = 'لعبوا، بس مش في القايمة';
$string['notenrolledintro'] = 'الجولات دي لعبها حد مش مسجّل حالياً بصلاحية اللعب — يا إما اتشال من الكورس، يا إما مدرّس كان بيجرّب النشاط. مذكورين هنا عشان الجولات ماتضيعش، وهم مش محسوبين في الرقم اللي فوق.';
$string['neverplayed'] = 'لسّه';
$string['colplays'] = 'الجولات';
$string['colpoints'] = 'الإجابات الصح';
$string['colbest'] = 'أحسن جولة';
$string['colstreak'] = 'أطول سلسلة';
$string['collastplayed'] = 'آخر لعب';

// Course index.
$string['noinstances'] = 'مافيش أنشطة ألعاب في الكورس ده.';

// Privacy.
$string['privacy:metadata:play'] = 'اللي عمله متعلّم واحد في نشاط لعبة واحد. نقاطه وشاراته على مستوى الموقع كله بتاعة ركن الألعاب وبتتسجّل لوحدها.';
$string['privacy:metadata:play:userid'] = 'المتعلّم اللي لعب.';
$string['privacy:metadata:play:plays'] = 'عدد الجولات اللي خلّصها في النشاط ده.';
$string['privacy:metadata:play:points'] = 'عدد الإجابات الصح في النشاط ده.';
$string['privacy:metadata:play:bestscore'] = 'أحسن جولة ليه في النشاط ده.';
$string['privacy:metadata:play:beststreak'] = 'أطول سلسلة إجابات صح في النشاط ده.';
$string['privacy:metadata:play:timemodified'] = 'وقت آخر جولة في النشاط ده.';

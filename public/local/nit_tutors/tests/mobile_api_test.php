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

namespace local_nit_tutors;

use local_nit_lessons\local\profile_section;
use local_nit_lessons\service\teacher_service;
use local_nit_tutors\local\application;
use local_nit_tutors\local\invalid_signup;
use local_nit_tutors\local\mobile_api;
use local_nit_tutors\local\qualifications;
use local_nit_tutors\local\review_view;

/**
 * The teachers API of the app (api.php): the teacher profile, the search and the reviewer.
 *
 * @package    local_nit_tutors
 * @copyright  2026 NIT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_nit_tutors\local\mobile_api
 * @covers     \local_nit_tutors\local\review_view
 * @covers     \local_nit_tutors\local\profile_save
 */
final class mobile_api_test extends \advanced_testcase {

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        require_once(__DIR__ . '/application_test.php');
    }

    /**
     * The saved data as the app sends it back, with changes.
     *
     * @param int $uid
     * @param array $override
     * @return array teacher_input values
     */
    private function in(int $uid, array $override = []): array {
        $q = qualifications::list($uid)[0];
        return $override + [
            'headline' => 'IELTS coach', 'bio' => 'I teach English.', 'subjects' => ['English'],
            'videotype' => 'link', 'videourl' => 'https://youtu.be/dQw4w9WgXcQ',
            'qualid' => [(int) $q->id], 'qualtitle' => [$q->title], 'qualinstitution' => [$q->institution],
            'qualyear' => [(int) $q->year], 'qualdelete' => [], 'field' => [],
        ];
    }

    /** @return array no files */
    private function nofiles(): array {
        return ['videofile' => null, 'qualfile' => [], 'fieldfile' => []];
    }

    public function test_an_approved_teachers_save_waits_but_the_price_saves_at_once(): void {
        $uid = application_test::complete_applicant();
        application::approve($uid, (int) get_admin()->id);
        $this->setUser($uid);
        $card = profile_section::card(true, 'Grammar expert', ['English'], [], '5');
        $out = mobile_api::save($uid, $this->in($uid), $this->nofiles(), $card, false);

        $this->assertTrue($out['waitsforreview']);
        $this->assertTrue($out['application']['haspending']);
        $this->assertSame('Grammar expert', $out['application']['values']['headline'], 'the teacher sees the change');
        $profile = (new teacher_service())->profile($uid);
        $this->assertSame('IELTS coach', $profile['headline'], 'students do not, yet');
        $this->assertSame(5, (int) $profile['lesson_flex'], 'the price is not reviewed');
    }

    public function test_field_errors_come_back_by_field(): void {
        $uid = application_test::complete_applicant();
        $this->setUser($uid);
        $card = profile_section::card(true, '', ['Astrology'], [['dayofweek' => 1, 'starttime' => '10:00',
            'endtime' => '10:10']], '0');
        try {
            mobile_api::save($uid, $this->in($uid, ['videourl' => 'javascript:x']), $this->nofiles(), $card, false);
            $this->fail('bad values were saved');
        } catch (invalid_signup $e) {
            $this->assertEqualsCanonicalizing(['subjects', 'video', 'hours', 'lessonflex'], array_keys($e->errors));
        }
        $this->assertSame('IELTS coach', (new teacher_service())->profile($uid)['headline'], 'nothing was saved');
    }

    public function test_send_submits_a_complete_draft(): void {
        $uid = application_test::complete_applicant();
        $this->setUser($uid);
        $out = mobile_api::save($uid, $this->in($uid), $this->nofiles(), null, true);
        $this->assertTrue($out['submitted']);
        $this->assertSame(application::PENDING, $out['application']['status']);
        $this->assertFalse($out['application']['editable'], 'pending: the reviewer has it');
    }

    public function test_the_reviewer_sees_the_change_and_decides(): void {
        $uid = application_test::complete_applicant();
        application::approve($uid, (int) get_admin()->id);
        $this->setUser($uid);
        mobile_api::save($uid, $this->in($uid, ['headline' => 'Grammar expert']), $this->nofiles(), null, false);
        $this->setAdminUser();

        $list = mobile_api::admin_list('updates', '', 0, 30);
        $this->assertSame([$uid], array_column($list['rows'], 'userid'));
        $this->assertSame(1, array_column($list['tabs'], 'count', 'status')['updates']);

        $data = mobile_api::admin_get($uid);
        $this->assertSame('updates', $data['mode']);
        $this->assertTrue($data['headlinenew']);
        $this->assertSame('Grammar expert', $data['headline']);

        try {
            review_view::decide($uid, 'reject', '', (int) get_admin()->id);
            $this->fail('rejected without a reason');
        } catch (\moodle_exception $e) {
            $this->assertSame('err_notereq', $e->errorcode);
        }
        $out = mobile_api::admin_decide($uid, 'approve', '', (int) get_admin()->id);
        $this->assertSame('approved', $out['application']['mode']);
        $this->assertSame('Grammar expert', (new teacher_service())->profile($uid)['headline']);

        try {
            review_view::decide($uid, 'approve', '', (int) get_admin()->id);
            $this->fail('approve offered for an approved teacher without changes');
        } catch (\moodle_exception $e) {
            $this->assertSame('err_decisionnotoffered', $e->errorcode);
        }
    }

    public function test_search_lists_only_approved_teachers(): void {
        $approved = application_test::complete_applicant();
        application::approve($approved, (int) get_admin()->id);
        $waiting = application_test::complete_applicant();
        application::submit($waiting);
        $out = mobile_api::search([], 0, 0, 24);
        $this->assertSame(1, $out['total']);
        $this->assertSame($approved, $out['teachers'][0]['id']);
        $this->assertSame(['English'], $out['teachers'][0]['subjects']);
        $this->assertNotEmpty(mobile_api::filters()['sorts']);
    }
}

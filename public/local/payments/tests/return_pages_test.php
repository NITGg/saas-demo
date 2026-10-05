<?php
namespace local_payments;

use local_payments\local\return_pages;

/**
 * "Try again" / back links after a Kashier payment, for every item type.
 *
 * @package    local_payments
 * @covers     \local_payments\local\return_pages
 */
final class return_pages_test extends \basic_testcase {

    /**
     * A transaction row as callback.php reads it.
     *
     * @param string $type item_type
     * @param int $courseid
     * @param string $returnurl metadata return_url
     * @return \stdClass
     */
    private function tx(string $type, int $courseid = 0, string $returnurl = ''): \stdClass {
        return (object) ['courseid' => $courseid,
            'metadata' => json_encode(['item_type' => $type, 'return_url' => $returnurl])];
    }

    /** Path of a moodle_url, relative to wwwroot. */
    private function path(\moodle_url $url): string {
        return $url->out_as_local_url(false);
    }

    public function test_course_retries_its_buy_page(): void {
        $this->assertSame('/local/payments/buy.php?courseid=7', $this->path(return_pages::retry($this->tx('course', 7))));
    }

    public function test_items_without_a_course_go_back_where_they_started(): void {
        // Before: every non-course item got buy.php?courseid=0 (a broken page).
        $this->assertSame('/local/nit_lessons/student.php?tab=mysubs',
            $this->path(return_pages::retry($this->tx('subscription', 0, '/local/nit_lessons/student.php?tab=mysubs'))));
        $this->assertSame('/', $this->path(return_pages::retry($this->tx('subscription'))));
        $this->assertSame('/local/nit_flex/packages.php', $this->path(return_pages::retry($this->tx('package'))));
        $this->assertSame('/local/nit_finance/wallet.php', $this->path(return_pages::retry($this->tx('wallet_topup'))));
    }

    public function test_only_pages_of_this_site_are_followed(): void {
        $this->assertNull(return_pages::started_from($this->tx('subscription', 0, '//evil.example/x')));
        $this->assertNull(return_pages::started_from($this->tx('subscription', 0, 'https://evil.example/x')));
        $this->assertSame('/', $this->path(return_pages::retry(null)));
    }
}

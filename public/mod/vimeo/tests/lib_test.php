<?php
namespace mod_vimeo;

/**
 * Unit tests for the video id / privacy hash a teacher pastes into a Vimeo
 * activity ({@see vimeo_resolve_videoid()}, {@see vimeo_resolve_videohash()}).
 *
 * An unlisted video plays only with its hash (?h=…); a pasted
 * "vimeo.com/<id>/<hash>" link used to lose it, so the video did not play.
 *
 * @package    mod_vimeo
 * @covers     ::vimeo_resolve_videoid
 * @covers     ::vimeo_resolve_videohash
 */
final class lib_test extends \basic_testcase {

    public static function setUpBeforeClass(): void {
        global $CFG;
        parent::setUpBeforeClass();
        require_once($CFG->dirroot . '/mod/vimeo/lib.php');
    }

    /**
     * @return array<string, array{string, string, string}> pasted text, id, hash
     */
    public static function pasted_provider(): array {
        return [
            'bare id' => ['76979871', '76979871', ''],
            'public link' => ['https://vimeo.com/76979871', '76979871', ''],
            'unlisted link' => ['https://vimeo.com/1012345678/a1b2c3d4e5', '1012345678', 'a1b2c3d4e5'],
            'player link' => ['https://player.vimeo.com/video/1012345678?h=a1b2c3d4e5&badge=0', '1012345678', 'a1b2c3d4e5'],
            'channel link' => ['https://vimeo.com/channels/staffpicks/76979871', '76979871', ''],
            'manage link' => ['https://vimeo.com/manage/videos/1012345678/a1b2c3d4e5', '1012345678', 'a1b2c3d4e5'],
            'embed code' => ['<iframe src="https://player.vimeo.com/video/1012345678?h=abc123def&amp;badge=0"></iframe>',
                '1012345678', 'abc123def'],
        ];
    }

    /**
     * @dataProvider pasted_provider
     */
    public function test_pasted_video(string $pasted, string $id, string $hash): void {
        $data = (object) ['videoid' => $pasted, 'videohash' => ''];
        $this->assertSame($id, vimeo_resolve_videoid($data));
        $this->assertSame($hash, vimeo_resolve_videohash($data));
    }

    public function test_typed_hash_wins(): void {
        $data = (object) ['videoid' => 'https://vimeo.com/1012345678/a1b2c3d4e5', 'videohash' => ' fff111 '];
        $this->assertSame('fff111', vimeo_resolve_videohash($data));
    }
}

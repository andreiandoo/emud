<?php

namespace Tests\Unit\Workshops;

use App\Workshops\Classification\KeywordServiceClassifier;
use App\Workshops\Data\SourceRecordData;
use App\Workshops\Web\RobotsTxt;
use PHPUnit\Framework\TestCase;

class RobotsAndClassifierTest extends TestCase
{
    public function test_robots_txt_uses_our_own_group_when_there_is_one(): void
    {
        $robots = RobotsTxt::parse("User-agent: *\nDisallow: /admin\nAllow: /admin/public\n\nUser-agent: eMUD-WorkshopRegistry\nDisallow: /private\n");

        $this->assertTrue($robots->allows('/admin', 'emud-workshopregistry'));
        $this->assertFalse($robots->allows('/private/page', 'emud-workshopregistry'));
        $this->assertFalse($robots->allows('/admin/settings', 'otherbot'));
        $this->assertTrue($robots->allows('/admin/public/page', 'otherbot'));
        $this->assertTrue($robots->allows('/', 'otherbot'));
    }

    public function test_robots_txt_wildcards_and_end_anchors(): void
    {
        $robots = RobotsTxt::parse("User-agent: *\nDisallow: /*.pdf$\nDisallow:\n");

        $this->assertFalse($robots->allows('/docs/brosura.pdf', 'bot'));
        $this->assertTrue($robots->allows('/docs/brosura.pdf?v=2', 'bot'));
        $this->assertTrue($robots->allows('/docs/brosura.html', 'bot'));
        $this->assertFalse(RobotsTxt::disallowAll()->allows('/', 'bot'));
        $this->assertTrue(RobotsTxt::allowAll()->allows('/anything', 'bot'));
    }

    public function test_workshop_phrases_map_to_services_with_their_evidence(): void
    {
        $found = (new KeywordServiceClassifier)->classify(
            'Reparații cutii automate și cutii de transfer (reductor). Diferențial, 4x4 și tracțiune integrală. '.
            'Înălțare suspensie, lift kit, montaj troliu și snorkel. Diagnoză computerizată și geometrie roți.'
        );

        foreach (['automatic_transmission', 'transfer_case', 'differential', '4x4_drivetrain', 'offroad_suspension', 'winch_installation', 'snorkel_installation', 'diagnostics', 'wheel_alignment'] as $service) {
            $this->assertArrayHasKey($service, $found, "{$service} was not recognised");
            $this->assertNotEmpty($found[$service]['snippets']);
            $this->assertGreaterThan(0, $found[$service]['confidence']);
        }

        $this->assertSame([], (new KeywordServiceClassifier)->classify('Florărie și cadouri, livrăm buchete.'));
    }

    public function test_content_hashes_ignore_key_order_but_not_list_order(): void
    {
        $a = new SourceRecordData('authorization', 'X', ['a' => 1, 'b' => ['x' => 1, 'y' => 2]]);
        $b = new SourceRecordData('authorization', 'X', ['b' => ['y' => 2, 'x' => 1], 'a' => 1]);
        $c = new SourceRecordData('authorization', 'X', ['a' => 1, 'b' => ['x' => 1, 'y' => 3]]);

        $this->assertSame($a->contentHash(), $b->contentHash());
        $this->assertNotSame($a->contentHash(), $c->contentHash());
        $this->assertNotSame((new SourceRecordData('t', 'x', [1, 2]))->contentHash(), (new SourceRecordData('t', 'x', [2, 1]))->contentHash());
        $this->assertSame(64, strlen($a->contentHash()));
    }
}

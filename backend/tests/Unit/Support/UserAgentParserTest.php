<?php

namespace Tests\Unit\Support;

use App\Support\UserAgentParser;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class UserAgentParserTest extends TestCase
{
    public static function agents(): array
    {
        return [
            'windows chrome desktop' => [
                'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0 Safari/537.36',
                ['browser' => 'Chrome', 'os' => 'Windows', 'device_type' => 'desktop'],
            ],
            'macos safari desktop' => [
                'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Safari/605.1.15',
                ['browser' => 'Safari', 'os' => 'macOS', 'device_type' => 'desktop'],
            ],
            'iphone safari mobile' => [
                'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Mobile/15E148 Safari/604.1',
                ['browser' => 'Safari', 'os' => 'iOS', 'device_type' => 'mobile'],
            ],
            'android chrome mobile' => [
                'Mozilla/5.0 (Linux; Android 13; Pixel 7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0 Mobile Safari/537.36',
                ['browser' => 'Chrome', 'os' => 'Android', 'device_type' => 'mobile'],
            ],
            'ipad safari tablet' => [
                'Mozilla/5.0 (iPad; CPU OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Safari/604.1',
                ['browser' => 'Safari', 'os' => 'iOS', 'device_type' => 'tablet'],
            ],
            'android tablet no mobile token' => [
                'Mozilla/5.0 (Linux; Android 13; SM-X700) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0 Safari/537.36',
                ['browser' => 'Chrome', 'os' => 'Android', 'device_type' => 'tablet'],
            ],
            'edge windows desktop' => [
                'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0 Safari/537.36 Edg/120.0',
                ['browser' => 'Edge', 'os' => 'Windows', 'device_type' => 'desktop'],
            ],
            'firefox linux desktop' => [
                'Mozilla/5.0 (X11; Linux x86_64; rv:120.0) Gecko/20100101 Firefox/120.0',
                ['browser' => 'Firefox', 'os' => 'Linux', 'device_type' => 'desktop'],
            ],
        ];
    }

    #[DataProvider('agents')]
    public function test_classifies_known_user_agents(string $ua, array $expected): void
    {
        $this->assertSame($expected, UserAgentParser::parse($ua));
    }

    public function test_detects_bots_by_device_type(): void
    {
        $result = UserAgentParser::parse('Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)');

        $this->assertSame('bot', $result['device_type']);
    }

    public function test_null_user_agent_degrades_gracefully(): void
    {
        $this->assertSame(
            ['browser' => 'Unknown', 'os' => 'Unknown', 'device_type' => 'unknown'],
            UserAgentParser::parse(null),
        );
    }

    public function test_empty_user_agent_degrades_gracefully(): void
    {
        $this->assertSame(
            ['browser' => 'Unknown', 'os' => 'Unknown', 'device_type' => 'unknown'],
            UserAgentParser::parse('   '),
        );
    }

    public function test_unrecognized_user_agent_does_not_throw(): void
    {
        $result = UserAgentParser::parse('totally-made-up-agent');

        $this->assertSame('Unknown', $result['browser']);
        $this->assertSame('Unknown', $result['os']);
    }
}

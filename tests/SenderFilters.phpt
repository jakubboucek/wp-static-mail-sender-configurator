<?php

/**
 * Sender filter warnings must never execute registered callbacks.
 * @testCase
 */

use JakubBoucek\WpPlugin\MailSenderConfig\StaticMailSenderConfigurator;
use Tester\Assert;

require __DIR__ . '/bootstrap.php';

class SenderFiltersTest extends Tester\TestCase
{
    /** @param list<string> $hooks */
    private function checkWarnings(array $hooks): void
    {
        wp_set_current_user(1);
        $calls = 0;
        $callback = static function (string $value) use (&$calls): string {
            $calls++;
            return $value;
        };
        foreach ($hooks as $hook) {
            add_filter($hook, $callback, 0);
        }
        ob_start();
        try {
            StaticMailSenderConfigurator::render_admin_page();
            $html = ob_get_contents();
        } finally {
            ob_end_clean();
            foreach ($hooks as $hook) {
                remove_filter($hook, $callback, 0);
            }
        }
        if ($html === false) {
            throw new RuntimeException('Cannot read the admin page output.');
        }
        Assert::same(0, $calls);
        $document = new DOMDocument();
        $document->loadHTML($html);
        $warnings = (new DOMXPath($document))->query('//div[contains(@class, "notice-warning")]/p');
        if ($warnings === false) {
            throw new RuntimeException('Cannot query sender filter warnings.');
        }
        $actual = [];
        foreach ($warnings as $warning) {
            if (!$warning instanceof DOMElement) {
                throw new RuntimeException('Expected a warning paragraph.');
            }
            $actual[] = $warning->textContent;
        }
        $descriptions = [
            'wp_mail_from' => 'A registered filter may override the sender email address.',
            'wp_mail_from_name' => 'A registered filter may override the sender display name.',
        ];
        $expected = [];
        foreach ($hooks as $hook) {
            $expected[] = $hook . ': ' . $descriptions[$hook]
                . ' This page does not run the filter or determine its result.';
        }
        Assert::same($expected, $actual);
    }

    public function testNoFilters(): void
    {
        $this->checkWarnings([]);
    }

    public function testAddressFilter(): void
    {
        $this->checkWarnings(['wp_mail_from']);
    }

    public function testNameFilter(): void
    {
        $this->checkWarnings(['wp_mail_from_name']);
    }

    public function testBothFilters(): void
    {
        $this->checkWarnings(['wp_mail_from', 'wp_mail_from_name']);
    }
}

(new SenderFiltersTest())->run();

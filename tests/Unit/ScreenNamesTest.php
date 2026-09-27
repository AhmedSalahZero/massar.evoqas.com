<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

// ══════════════════════════════════════════════════════════════════
//  Massar — ScreenNamesTest
//  Location: tests/Unit/ScreenNamesTest.php
//
//  A screen (.vue) must not use the same name for something the server
//  sends (a prop) and for its own value or function. The screen's own
//  one silently wins: in Step 7 a "teach" action hid the "teach"
//  permission, so the Teach buttons never showed. This check reads
//  every screen and names any clash.
// ══════════════════════════════════════════════════════════════════

class ScreenNamesTest extends TestCase
{
    public function test_no_screen_hides_a_prop_behind_its_own_name(): void
    {
        $root = dirname(__DIR__, 2).'/resources/js';
        $clashes = [];
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS));
        foreach ($files as $file) {
            if ($file->getExtension() !== 'vue') {
                continue;
            }
            $src = file_get_contents($file->getPathname());
            if (! preg_match('/defineProps\(\{(.*?)\n\}\)/s', $src, $m)) {
                continue;
            }
            preg_match_all('/^\s{4}(\w+)\s*:/m', $m[1], $props);
            preg_match_all('/^(?:const|let|function)\s+(\w+)/m', $src, $locals);
            $same = array_diff(array_intersect($props[1], $locals[1]), ['props']);
            if ($same) {
                $clashes[] = str_replace($root.'/', '', $file->getPathname()).': '.implode(', ', $same);
            }
        }
        $this->assertSame([], $clashes, 'These screens use a prop name for their own value too.');
    }
}

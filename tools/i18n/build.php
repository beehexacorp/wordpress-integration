<?php
// Regenerates languages/hexasyncintegration.pot, -vi.po and -vi.mo from the PHP source.
// Translations are read back from the existing -vi.po, so edit msgstr there and re-run.
// Usage: php tools/i18n/build.php [wp_root]   (wp_root defaults to the WordPress install this plugin sits in)
$dir = dirname(__DIR__, 2);
$wp = rtrim($argv[1] ?? dirname($dir, 3), '/');
require $wp . '/wp-includes/pomo/po.php';
require $wp . '/wp-includes/pomo/mo.php';

$strings = json_decode(shell_exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__DIR__ . '/extract.php') . ' ' . escapeshellarg($dir)), true);
// Plugin header strings WordPress translates on the Plugins screen.
$main = file_get_contents("$dir/hexasyncintegration.php");
foreach (['Plugin Name', 'Description'] as $h) {
    preg_match('/^' . $h . ':\s*(.+)$/m', $main, $m);
    array_unshift($strings, ['msgid' => trim($m[1]), 'plural' => null, 'refs' => ['hexasyncintegration.php'], 'comment' => "$h of the plugin"]);
}
$vi = [];
$old = new PO();
if (is_file("$dir/languages/hexasyncintegration-vi.po") && $old->import_from_file("$dir/languages/hexasyncintegration-vi.po")) {
    foreach ($old->entries as $e) if (array_filter($e->translations)) $vi[$e->singular] = $e->translations;
}
$missing = array_filter($strings, fn($s) => !isset($vi[$s['msgid']]));
foreach ($missing as $s) { fwrite(STDERR, "UNTRANSLATED (vi): {$s['msgid']}\n"); $vi[$s['msgid']] = ['']; }
foreach (array_diff(array_keys($vi), array_column($strings, 'msgid')) as $s) fwrite(STDERR, "DROPPED (no longer in source): $s\n");

$ver = preg_match('/^Version:\s*(\S+)/m', $main, $m) ? $m[1] : '';
$now = gmdate('Y-m-d H:i') . '+0000';
$common = [
    'Project-Id-Version' => "HexaSync SaaS Integration Platform by Beehexa $ver",
    'Report-Msgid-Bugs-To' => 'https://wordpress.org/support/plugin/hexasyncintegration',
    'MIME-Version' => '1.0',
    'Content-Type' => 'text/plain; charset=UTF-8',
    'Content-Transfer-Encoding' => '8bit',
    'POT-Creation-Date' => $now,
    'X-Domain' => 'hexasyncintegration',
];

function build($strings, $headers, $vi = null) {
    $po = new PO(); $mo = new MO();
    foreach ([$po, $mo] as $t) $t->set_headers($headers);
    foreach ($strings as $s) {
        $e = ['singular' => $s['msgid'], 'references' => $s['refs']];
        if ($s['comment']) $e['extracted_comments'] = $s['comment'];
        if ($s['plural']) $e['plural'] = $s['plural'];
        if ($vi) $e['translations'] = (array) $vi[$s['msgid']];
        $entry = new Translation_Entry($e);
        $po->add_entry($entry);
        if ($vi && array_filter($entry->translations)) $mo->add_entry($entry);
    }
    return [$po, $mo];
}

@mkdir("$dir/languages");
[$pot] = build($strings, $common + ['PO-Revision-Date' => 'YEAR-MO-DA HO:MI+ZONE', 'Language-Team' => 'LANGUAGE <LL@li.org>']);
$pot->export_to_file("$dir/languages/hexasyncintegration.pot");

[$po, $mo] = build($strings, $common + [
    'PO-Revision-Date' => $now,
    'Language-Team' => 'Vietnamese',
    'Language' => 'vi',
    'Plural-Forms' => 'nplurals=1; plural=0;',
], $vi);
$po->export_to_file("$dir/languages/hexasyncintegration-vi.po");
$mo->export_to_file("$dir/languages/hexasyncintegration-vi.mo");
echo count($strings) . ' strings, ' . (count($strings) - count($missing)) . " translated to vi\n";

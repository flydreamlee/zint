<?php
/* Generate lookup table from unicode.org mapping file (SHIFTJIS.TXT by default). */
/*
    libzint - the open source barcode library
    Copyright (C) 2008-2019 Robin Stuart <rstuart114@gmail.com>
*/
/* To create backend/tests/test_sjis_tab.h (from backend/tests/build directory):
 *
 *   php ../tools/gen_test_tab.php
 *
 * To create backend/tests/test_gb2312_tab.h;
 *
 *   php ../tools/gen_test_tab.php -f GB2312.TXT -s gb2312_tab
 *
 * To create backend/tests/test_gb18030_tab.h (note that backend/tests/tools/data/GB18030.TXT
 * will have to be downloaded first from https://haible.de/bruno/charsets/conversion-tables/GB18030.html
 * using the version libiconv-1.11/GB18030.TXT):
 *
 *   php ../tools/gen_test_tab.php -f GB18030.TXT -s gb18030_tab
 */
/* vim: set ts=4 sw=4 et : */

$basename = basename(__FILE__);
$dirname = dirname(__FILE__);

$opts = getopt('d:f:o:s:');
$data_dirname = isset($opts['d']) ? $opts['d'] : ($dirname . '/data'); // Where to load file from.
$file_name = isset($opts['f']) ? $opts['f'] : 'SHIFTJIS.TXT'; // Name of file.
$out_dirname = isset($opts['o']) ? $opts['o'] : ($dirname . '/..'); // Where to put output.
$suffix_name = isset($opts['s']) ? $opts['s'] : 'sjis_tab'; // Suffix of table and output file.

$file = $data_dirname . '/' . $file_name;

// Read the file.

if (($get = file_get_contents($file)) === false) {
    error_log($error = "$basename: ERROR: Could not read mapping file \"$file\"");
    exit($error . PHP_EOL);
}

$lines = explode("\n", $get);

// Parse the file.

$tab_lines = array();
$sort = array();
foreach ($lines as $line) {
    $line = trim($line);
    if ($line === '' || strncmp($line, '0x', 2) !== 0) {
        continue;
    }
    if (preg_match('/^0x([0-9A-F]{2,8})[ \t]+0x([0-9A-F]{5})/', $line)) { // Exclude U+10000..10FFFF to save space
        continue;
    }
    $tab_lines[] = preg_replace_callback('/^0x([0-9A-F]{2,8})[ \t]+0x([0-9A-F]{4}).*$/', function ($matches) {
        global $sort;
        $mb = hexdec($matches[1]);
        $unicode = hexdec($matches[2]);
        $sort[] = $unicode;
        return sprintf("    0x%04X, 0x%04X,", $mb, $unicode);
    }, $line);
}

array_multisort($sort, $tab_lines);

// Output.

$out = array();
$out[] = '/* Generated by ' . $basename . ' from ' . $file_name . ' */';
$out[] = 'static const unsigned int test_' . $suffix_name . '[] = {';
$out = array_merge($out, $tab_lines);
$out[] = '};';

$out[] = '';
$out[] = 'static const unsigned int test_' . $suffix_name . '_ind[] = {';
$first = 0;
foreach ($sort as $ind => $unicode) {
    $div = (int)($unicode / 0x1000);
    while ($div >= $first) {
        $out[] = ($ind * 2) . ',';
        $first++;
    }
}
$out[] = '};';

file_put_contents($out_dirname . '/test_' . $suffix_name . '.h', implode("\n", $out) . "\n");

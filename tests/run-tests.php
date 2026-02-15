<?php
/**
 * Standalone test suite for Git it Write sync resilience fixes.
 * Run: php tests/run-tests.php
 * No WordPress installation required — uses stubs from bootstrap.php.
 */

require_once __DIR__ . '/bootstrap.php';

// ── Simple test framework ──

$total = 0;
$passed = 0;
$failed_tests = array();

function assert_true( $condition, $name ){
    global $total, $passed, $failed_tests;
    $total++;
    if( $condition ){
        $passed++;
        echo "  PASS: $name\n";
    } else {
        $failed_tests[] = $name;
        echo "  FAIL: $name\n";
    }
}

function assert_false( $condition, $name ){
    assert_true( !$condition, $name );
}

function assert_equals( $expected, $actual, $name ){
    global $total, $passed, $failed_tests;
    $total++;
    if( $expected === $actual ){
        $passed++;
        echo "  PASS: $name\n";
    } else {
        $failed_tests[] = $name;
        echo "  FAIL: $name\n";
        echo "    Expected: " . var_export( $expected, true ) . "\n";
        echo "    Actual:   " . var_export( $actual, true ) . "\n";
    }
}

function fixture( $name ){
    return file_get_contents( __DIR__ . '/fixtures/' . $name );
}

// ── Setup ──

$parsedown = new GIW_Parsedown();

// ══════════════════════════════════════════════
// Suite 1: YAML Parsing
// ══════════════════════════════════════════════

echo "\n=== Suite 1: YAML Parsing ===\n";

// Test 1: Valid basic front matter
GIW_Utils::clear_logs();
$result = $parsedown->parse_content( fixture( 'valid-basic.md' ) );
assert_equals( 'Basic Post', $result['front_matter']['title'], 'Valid basic: title parsed' );
assert_equals( 'publish', $result['front_matter']['post_status'], 'Valid basic: post_status parsed' );
assert_true( strpos( $result['markdown'], '## Hello World' ) !== false, 'Valid basic: markdown body present' );

// Test 2: Valid full front matter
GIW_Utils::clear_logs();
$result = $parsedown->parse_content( fixture( 'valid-full.md' ) );
assert_equals( 'Full Post', $result['front_matter']['title'], 'Valid full: title parsed' );
assert_equals( 'draft', $result['front_matter']['post_status'], 'Valid full: post_status parsed' );
assert_equals( 5, $result['front_matter']['menu_order'], 'Valid full: menu_order parsed' );
assert_equals( 'A full test post', $result['front_matter']['post_excerpt'], 'Valid full: post_excerpt parsed' );
assert_true( is_array( $result['front_matter']['taxonomy'] ), 'Valid full: taxonomy is array' );
assert_true( in_array( 'tech', $result['front_matter']['taxonomy']['category'] ), 'Valid full: taxonomy category contains tech' );

// Test 3: No front matter
GIW_Utils::clear_logs();
$result = $parsedown->parse_content( fixture( 'no-frontmatter.md' ) );
assert_equals( '', $result['front_matter']['title'], 'No front matter: defaults used (empty title)' );
assert_true( strpos( $result['markdown'], '## No Front Matter' ) !== false, 'No front matter: full content as markdown' );

// Test 4: Empty front matter — YAML parses empty string to null (not array)
GIW_Utils::clear_logs();
$result = $parsedown->parse_content( fixture( 'empty-frontmatter.md' ) );
assert_equals( 'yes', $result['front_matter']['skip_file'], 'Empty front matter: skip_file set (non-array guard)' );

// Test 5: Malformed nested quotes
GIW_Utils::clear_logs();
$result = $parsedown->parse_content( fixture( 'malformed-nested-quotes.md' ) );
// This may parse OK or throw — either way should not crash
assert_true( is_array( $result['front_matter'] ), 'Nested quotes: returns array (no crash)' );

// Test 6: Malformed bad indent
GIW_Utils::clear_logs();
$result = $parsedown->parse_content( fixture( 'malformed-bad-indent.md' ) );
assert_equals( 'yes', $result['front_matter']['skip_file'], 'Bad indent: skip_file set' );
$logs = GIW_Utils::get_logs();
$has_error_log = false;
foreach( $logs as $log ){
    if( strpos( $log, 'YAML parse error' ) !== false || strpos( $log, 'non-array' ) !== false ){
        $has_error_log = true;
    }
}
assert_true( $has_error_log, 'Bad indent: error logged' );

// Test 7: Malformed tabs
GIW_Utils::clear_logs();
$result = $parsedown->parse_content( fixture( 'malformed-tabs.md' ) );
assert_equals( 'yes', $result['front_matter']['skip_file'], 'Tabs in YAML: skip_file set' );

// Test 8: Scalar-only YAML
GIW_Utils::clear_logs();
$result = $parsedown->parse_content( fixture( 'malformed-scalar-only.md' ) );
assert_equals( 'yes', $result['front_matter']['skip_file'], 'Scalar YAML: skip_file set (non-array guard)' );
$logs = GIW_Utils::get_logs();
$has_non_array_log = false;
foreach( $logs as $log ){
    if( strpos( $log, 'non-array' ) !== false ){
        $has_non_array_log = true;
    }
}
assert_true( $has_non_array_log, 'Scalar YAML: non-array log message' );

// Test 9: Unclosed string
GIW_Utils::clear_logs();
$result = $parsedown->parse_content( fixture( 'malformed-unclosed-string.md' ) );
assert_true( is_array( $result['front_matter'] ), 'Unclosed string: returns array (no crash)' );

// Test 10: Colon in value (unquoted)
GIW_Utils::clear_logs();
$result = $parsedown->parse_content( fixture( 'malformed-colon-in-value.md' ) );
assert_true( is_array( $result['front_matter'] ), 'Colon in value: returns array (no crash)' );

// Test 11: Horizontal rule in body (not confused with front matter)
GIW_Utils::clear_logs();
$result = $parsedown->parse_content( fixture( 'horizontal-rule-in-body.md' ) );
assert_equals( 'Horizontal Rule Test', $result['front_matter']['title'], 'HR in body: title parsed correctly' );
assert_true( strpos( $result['markdown'], '## Section Two' ) !== false, 'HR in body: content after HR preserved' );

// Test 12: Error logging verification — bad indent should log raw YAML
GIW_Utils::clear_logs();
$parsedown->parse_content( fixture( 'malformed-bad-indent.md' ) );
$logs = GIW_Utils::get_logs();
$has_raw_yaml = false;
foreach( $logs as $log ){
    if( strpos( $log, 'Raw:' ) !== false || strpos( $log, 'non-array' ) !== false ){
        $has_raw_yaml = true;
    }
}
assert_true( $has_raw_yaml, 'Error logging: error details logged for malformed YAML' );

// Test 13: skip_file in fixture
GIW_Utils::clear_logs();
$result = $parsedown->parse_content( fixture( 'skip-file.md' ) );
assert_equals( 'yes', $result['front_matter']['skip_file'], 'skip_file: correctly parsed from front matter' );

// ══════════════════════════════════════════════
// Suite 2: Image Extension Filtering
// ══════════════════════════════════════════════

echo "\n=== Suite 2: Image Extension Filtering ===\n";

// The filter logic from publisher.php
function is_allowed_image_extension( $file_type ){
    $allowed = array( 'jpg', 'jpeg', 'jpe', 'png', 'gif', 'webp' );
    return in_array( strtolower( $file_type ), $allowed );
}

// Valid extensions
assert_true( is_allowed_image_extension( 'jpg' ), 'Image ext: jpg allowed' );
assert_true( is_allowed_image_extension( 'jpeg' ), 'Image ext: jpeg allowed' );
assert_true( is_allowed_image_extension( 'jpe' ), 'Image ext: jpe allowed' );
assert_true( is_allowed_image_extension( 'png' ), 'Image ext: png allowed' );
assert_true( is_allowed_image_extension( 'gif' ), 'Image ext: gif allowed' );
assert_true( is_allowed_image_extension( 'webp' ), 'Image ext: webp allowed' );
assert_true( is_allowed_image_extension( 'JPG' ), 'Image ext: JPG (uppercase) allowed' );
assert_true( is_allowed_image_extension( 'Png' ), 'Image ext: Png (mixed case) allowed' );

// Invalid extensions
assert_false( is_allowed_image_extension( 'gitkeep' ), 'Image ext: gitkeep rejected' );
assert_false( is_allowed_image_extension( '' ), 'Image ext: empty string rejected' );
assert_false( is_allowed_image_extension( 'DS_Store' ), 'Image ext: DS_Store rejected' );
assert_false( is_allowed_image_extension( 'md' ), 'Image ext: md rejected' );
assert_false( is_allowed_image_extension( 'txt' ), 'Image ext: txt rejected' );
assert_false( is_allowed_image_extension( 'svg' ), 'Image ext: svg rejected' );
assert_false( is_allowed_image_extension( 'bmp' ), 'Image ext: bmp rejected' );
assert_false( is_allowed_image_extension( 'tiff' ), 'Image ext: tiff rejected' );
assert_false( is_allowed_image_extension( 'ico' ), 'Image ext: ico rejected' );
assert_false( is_allowed_image_extension( 'pdf' ), 'Image ext: pdf rejected' );
assert_false( is_allowed_image_extension( 'php' ), 'Image ext: php rejected' );
assert_false( is_allowed_image_extension( 'html' ), 'Image ext: html rejected' );

// Edge cases with file_type as repository.php provides it
// repository.php line 80-88: file_type is always set, empty string if no extension
assert_false( is_allowed_image_extension( '' ), 'Image ext: no extension (empty) rejected' );

// ══════════════════════════════════════════════
// Suite 3: Item Slug Filtering
// ══════════════════════════════════════════════

echo "\n=== Suite 3: Item Slug Filtering ===\n";

function should_skip_item( $slug ){
    $first_character = substr( $slug, 0, 1 );
    return in_array( $first_character, array( '_', '.' ) );
}

assert_true( should_skip_item( '_images' ), 'Slug filter: _images skipped' );
assert_true( should_skip_item( '_templates' ), 'Slug filter: _templates skipped' );
assert_true( should_skip_item( '.gitignore' ), 'Slug filter: .gitignore skipped' );
assert_true( should_skip_item( '.hidden' ), 'Slug filter: .hidden skipped' );
assert_false( should_skip_item( 'my-post' ), 'Slug filter: my-post passes' );
assert_false( should_skip_item( 'index' ), 'Slug filter: index passes' );
assert_false( should_skip_item( 'About-Us' ), 'Slug filter: About-Us passes' );

// ══════════════════════════════════════════════
// Suite 4: Error Recovery Simulation
// ══════════════════════════════════════════════

echo "\n=== Suite 4: Error Recovery Simulation ===\n";

// Simulate processing a sequence: good file, bad file (throws), good file
// All should produce results without crash propagation
$sequence = array(
    'good-1' => fixture( 'valid-basic.md' ),
    'bad'    => fixture( 'malformed-bad-indent.md' ),
    'good-2' => fixture( 'valid-full.md' ),
);

$results = array();
$errors = 0;

foreach( $sequence as $slug => $content ){
    try {
        GIW_Utils::clear_logs();
        $result = $parsedown->parse_content( $content );
        $results[ $slug ] = $result;
    } catch( \Exception $e ){
        $errors++;
        $results[ $slug ] = 'error';
    }
}

assert_equals( 0, $errors, 'Error recovery: no uncaught exceptions' );
assert_equals( 3, count( $results ), 'Error recovery: all 3 items processed' );
assert_equals( 'Basic Post', $results['good-1']['front_matter']['title'], 'Error recovery: first good file OK' );
assert_equals( 'yes', $results['bad']['front_matter']['skip_file'], 'Error recovery: bad file got skip_file' );
assert_equals( 'Full Post', $results['good-2']['front_matter']['title'], 'Error recovery: second good file OK after bad' );

// ══════════════════════════════════════════════
// Suite 5: Error Message Quality
// ══════════════════════════════════════════════

echo "\n=== Suite 5: Error Message Quality ===\n";

GIW_Utils::clear_logs();
$parsedown->parse_content( fixture( 'malformed-bad-indent.md' ) );
$logs = GIW_Utils::get_logs();
$log_text = implode( "\n", $logs );
assert_true(
    strpos( $log_text, 'YAML parse error' ) !== false || strpos( $log_text, 'non-array' ) !== false,
    'Error quality: descriptive error type in log'
);

GIW_Utils::clear_logs();
$parsedown->parse_content( fixture( 'malformed-scalar-only.md' ) );
$logs = GIW_Utils::get_logs();
$log_text = implode( "\n", $logs );
assert_true(
    strpos( $log_text, 'non-array' ) !== false,
    'Error quality: scalar YAML triggers non-array message'
);

// ══════════════════════════════════════════════
// Suite 6: Markdown Rendering Smoke
// ══════════════════════════════════════════════

echo "\n=== Suite 6: Markdown Rendering Smoke ===\n";

$md_result = $parsedown->parse_content( fixture( 'valid-full.md' ) );
$html = $parsedown->text( $md_result['markdown'] );

assert_true( strpos( $html, '<h2>' ) !== false, 'Markdown: H2 rendered' );
assert_true( strpos( $html, '<strong>bold</strong>' ) !== false, 'Markdown: bold rendered' );
assert_true( strpos( $html, '<em>italic</em>' ) !== false, 'Markdown: italic rendered' );

// ══════════════════════════════════════════════
// Results
// ══════════════════════════════════════════════

echo "\n" . str_repeat( '=', 50 ) . "\n";
echo "Results: $passed / $total passed\n";

if( count( $failed_tests ) > 0 ){
    echo "\nFailed tests:\n";
    foreach( $failed_tests as $name ){
        echo "  - $name\n";
    }
    echo "\n";
    exit(1);
}

echo "All tests passed!\n";
exit(0);

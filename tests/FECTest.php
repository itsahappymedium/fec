<?php
use PHPUnit\Framework\TestCase;

class FECTest extends TestCase {
  public function setUp(): void {
    parent::setUp();

    if (!file_exists('tmp')) mkdir('tmp', 0777, true);

    register_shutdown_function(function() {
      $tmp_files = array_diff(scandir('tmp'), array('..', '.'));

      foreach($tmp_files as $tmp_file) {
        unlink('tmp/' . $tmp_file);
      }
    });
  }

  public $comment = "/*! Comment */\n";
  public $expected_css = ".red{color:red}.hello .world{content:\"World\"}";
  public $expected_css2 = "/*! Comment */\n.big{font-size:60px}\n/*! Comment */\n.bold{font-weight:700}";
  public $expected_js = "(function(){var hi='Hello World'
function hello(){console.log(hi)}
hello()})();(function(){var bye='Goodbye World'
function goodbye(){console.log(bye)}
bye()})()";

  /** @test */
  public function basic_css_test() {
    $output = `./bin/fec --css-output ./tmp/output.min.css ./tests/assets/input.scss 2>&1`;
    $css = file_get_contents('tmp/output.min.css');

    $this->assertEquals($css, $this->comment . $this->expected_css);
  }

  /** @test */
  public function basic_js_test() {
    $output = `./bin/fec --js-output ./tmp/output.min.js ./tests/assets/input.js ./tests/assets/input2.js 2>&1`;
    $js = file_get_contents('tmp/output.min.js');

    $this->assertEquals($js, $this->comment . $this->expected_js);
  }

  /** @test */
  public function combined_json_test() {
    $output = `./bin/fec --path ./tests/assets 2>&1`;

    $css = file_get_contents('tmp/output2.min.css');
    $js = file_get_contents('tmp/output2.min.js');

    $this->assertEquals($css, $this->comment . $this->expected_css);
    $this->assertEquals($js, $this->comment . $this->expected_js);
  }

  /** @test */
  public function combined_json_test_with_settings() {
    $output = `./bin/fec --path ./tests/assets/fec2.json 2>&1`;

    $css = file_get_contents('tmp/output2-compressed.min.css');
    $js = file_get_contents('tmp/output2-compressed.min.js');

    $this->assertEquals($css, $this->expected_css);
    $this->assertEquals($js, $this->expected_js);
  }

  /** @test */
  public function compressed_css_test() {
    $output = `./bin/fec --compress --css-output ./tmp/output-compressed.min.css ./tests/assets/input.scss 2>&1`;
    $css = file_get_contents('tmp/output-compressed.min.css');

    $this->assertEquals($css, $this->expected_css);
  }

  /** @test */
  public function compressed_js_test() {
    $output = `./bin/fec --compress --js-output ./tmp/output-compressed.min.js ./tests/assets/input.js ./tests/assets/input2.js 2>&1`;
    $js = file_get_contents('tmp/output-compressed.min.js');

    $this->assertEquals($js, $this->expected_js);
  }

  /** @test */
  public function invalid_json_source_css_file() {
    $output = `./bin/fec --path ./tests/assets/fec3.json 2>&1`;

    $this->assertEquals($output, "Could not find any CSS files matching nope.scss\n");
  }

  /** @test */
  public function invalid_json_source_js_file() {
    $output = `./bin/fec --path ./tests/assets/fec4.json 2>&1`;

    $this->assertEquals($output, "Could not find any JS files matching nope.js\n");
  }

  /** @test */
  public function invalid_inline_source_file() {
    $output = `./bin/fec nope.txt 2>&1`;

    $this->assertEquals($output, "Could not find any CSS or JS files matching nope.txt\n");
  }

  /** @test */
  public function invalid_inline_css_source_file() {
    $output = `./bin/fec nope.css 2>&1`;

    $this->assertEquals($output, "Could not find any CSS files matching nope.css\n");
  }

  /** @test */
  public function invalid_inline_js_source_file() {
    $output = `./bin/fec nope.js 2>&1`;

    $this->assertEquals($output, "Could not find any JS files matching nope.js\n");
  }

  /** @test */
  public function scss_custom_import_path() {
    $output = `./bin/fec --path ./tests/assets/fec5.json 2>&1`;
    $css = file_get_contents('tmp/output3.min.css');

    $this->assertEquals($css, $this->expected_css2);
  }

  /** @test */
  public function scss_inline_custom_import_path() {
    $output = `./bin/fec --scss-import-path ./tests/assets/includes --css-output ./tmp/output3-inline.min.css ./tests/assets/input3.scss 2>&1`;
    $css = file_get_contents('tmp/output3-inline.min.css');

    $this->assertEquals($css, $this->expected_css2);
  }
}
?>

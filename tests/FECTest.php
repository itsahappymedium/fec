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
  public $expected_css = ".red{color:red}.hello{content:\"World\"}";
  public $expected_js = "(function(){var hi='Hello World'
function hello(){console.log(hi)}
hello()})()
(function(){var bye='Goodbye World'
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
}
?>

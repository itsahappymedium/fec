<?php
use MatthiasMullie\Minify;
use ScssPhp\ScssPhp\Compiler as ScssCompiler;
use splitbrain\phpcli\CLI;

class FEC extends CLI {
  protected function setup($options) {
    $options->setHelp('A PHP Command Line tool that makes it easy to compile, concat, and minify front-end Javascript and CSS/SCSS dependencies.');
    $options->registerOption('css-output', 'Destination CSS file. (Only used when files are passed, otherwise files are not concated and original filenames are used)', 'c', true);
    $options->registerOption('js-output', 'Destination JavaScript file. (Only used when files are passed, otherwise files are not concated and original filenames are used)', 'j', true);
    $options->registerOption('path', 'Path to the fec.json file. (Only used when no files are passed)', 'p', true);
  }

  protected function main($options) {
    $files = $options->getArgs();
    $css_files = array();
    $js_files = array();

    if (empty($files)) {
      if ($path = $options->getOpt('path')) {
        if (file_exists($path)) {
          $json = @file_get_contents($path);
        } else {
          $this->print("<lightred>Error</lightred>: Could not find <yellow>$path</yellow>.");
          return;
        }
      } elseif (file_exists("./fec.json")) {
        $json = @file_get_contents("./fec.json");
      } elseif (file_exists("./gpm.json")) {
        $json = @file_get_contents("./gpm.json");
      } else {
        $this->print("<lightred>Error</lightred>: Could not find <yellow>fec.json</yellow> or <yellow>gpm.json</yellow>.");
        return;
      }

      if (!($info = @json_decode($json, true))) {
        $this->print("<lightred>Error</lightred>: An error occured while decoding JSON data.");
        return;
      }

      if (!isset($info['compile']) || !is_array($info['compile'])) {
        $this->print("<lightred>Error</lightred>: JSON data does not contain a compile item.");
        return;
      }

      $compile = $info['compile'];

      if (isset($compile['css'])) $css_files = $compile['css'];
      if (isset($compile['js'])) $js_files = $compile['js'];
    } else {
      $css_source_files = array_filter($files, function($file) {
        return preg_match('/\.s?css$/', $file);
      });

      if ($css_dest_file = $options->getOpt('css-output')) {
        $css_files[$css_dest_file] = $css_source_files;
      } else {
        foreach($css_source_files as $css_source_file) {
          foreach(glob($css_source_file) as $file) {
            $dest = preg_replace('/\.s?css$/', '.min.css', $file);
            $css_files[$dest] = $file;
          }
        }
      }

      $js_source_files = array_filter($files, function($file) {
        return preg_match('/\.js\$/', $file);
      });

      if ($js_dest_file = $options->getOpt('css-output')) {
        $js_files[$js_dest_file] = $js_source_files;
      } else {
        foreach($js_source_files as $js_source_file) {
          foreach(glob($js_source_file) as $file) {
            $dest = preg_replace('/\.js$/', '.min.js', $file);
            $js_files[$dest] = $file;
          }
        }
      }
    }

    if (!empty($css_files)) {
      foreach($css_files as $dest => $sources) {
        $css_minifier = new Minify\CSS();

        foreach($sources as $source) {
          foreach(glob($source) as $file) {
            if (substr(basename($file), 0, 1) === '_') continue;

            $this->print(" - <purple>Loading</purple> <brown>$file</brown>...");

            $ext = pathinfo($file, PATHINFO_EXTENSION);

            if ($ext === 'scss') {
              $scss_compiler = new ScssCompiler();
              $scss = file_get_contents($file);
              $scss_compiler->addImportPath(dirname($file));
              $css = $scss_compiler->compileString($scss)->getCss();
              $css_minifier->add($css);
            } else {
              $css_minifier->add($file);
            }
          }
        }

        $this->print(" - <cyan>Minifying</cyan> <brown>$dest</brown>...");
        $css_minifier->minify($dest);
      }
    }

    if (!empty($js_files)) {
      foreach($js_files as $dest => $sources) {
        $js_minifier = new Minify\JS();

        foreach($sources as $source) {
          foreach(glob($source) as $file) {
            $this->print(" - <purple>Loading</purple> <brown>$file</brown>...");
            $js_minifier->add($file);
          }
        }

        $this->print(" - <cyan>Minifying</cyan> <brown>$dest</brown>...");
        $js_minifier->minify($dest);
      }
    }

    $this->print('Done!');
  }

  protected function print($text, $channel = STDOUT) {
    $active_colors = array();

    $text = preg_replace_callback('/\<(.[^\>]*?)\>/', function ($matches) use (&$active_colors) {
      $new_color = $matches[1];
      $colors = array();

      if (substr($new_color, 0, 1) === '/') {
        array_pop($active_colors);
        $colors[] = 'reset';
      } else {
        $active_colors[] = $new_color;
      }

      $colors = array_merge($colors, $active_colors);

      return implode('', array_map(function ($color) {
        return $this->colors->getColorCode($color);
      }, $colors));
    }, $text);

    fwrite($channel, $text . "\n");

    if (end($active_colors) !== 'reset') {
      $this->colors->reset($channel);
    }
  }
}

$cli = new FEC();
$cli->run();
?>

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
    $options->registerOption('scss-import-path', 'Directory path to include when locating imported SCSS files. (Define multiple directories by seperating them with commas)', 's', true);
  }

  protected function main($options) {
    $files = $options->getArgs();
    $path_opt = $options->getOpt('path');
    $scss_import_paths = explode(',', $options->getOpt('scss-import-path', array()));
    $path = '.';
    $json_file = null;
    $css_files = array();
    $js_files = array();

    if ($path_opt) {
      if (file_exists($path_opt) && is_dir($path_opt)) {
        $path = rtrim($path_opt, '/');
      } else {
        $path = dirname($path_opt);
        $json_file = $path_opt;
      }
    }

    if (empty($files)) {
      if ($json_file) {
        if (file_exists($json_file)) {
          $json = @file_get_contents($json_file);
        } else {
          $this->print("<lightred>Error</lightred>: Could not find <yellow>$json_file</yellow>.");
          return;
        }
      } elseif (file_exists("$path/fec.json")) {
        $json = @file_get_contents("$path/fec.json");
      } elseif (file_exists("$path/gpm.json")) {
        $json = @file_get_contents("$path/gpm.json");
      } else {
        $this->print("<lightred>Error</lightred>: Could not find <yellow>$path/fec.json</yellow> or <yellow>$path/gpm.json</yellow>.");
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
          $files = glob("$path/" . preg_replace('/^\.?\//', '', $css_source_file));

          foreach($files as $file) {
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
          $files = glob("$path/" . preg_replace('/^\.?\//', '', $js_source_file));

          foreach($files as $file) {
            $dest = preg_replace('/\.js$/', '.min.js', $file);
            $js_files[$dest] = $file;
          }
        }
      }
    }

    if (!empty($css_files)) {
      foreach($css_files as $dest => $sources) {
        $css_minifier = new Minify\CSS();

        if (!is_array($sources)) $sources = array($sources);

        foreach($sources as $source) {
          $files = glob("$path/" . preg_replace('/^\.?\//', '', $source));

          foreach($files as $file) {
            if (substr(basename($file), 0, 1) === '_') continue;

            $this->print(" - <purple>Loading</purple> <brown>$file</brown>...");

            $ext = pathinfo($file, PATHINFO_EXTENSION);

            if ($ext === 'scss') {
              $scss_compiler = new ScssCompiler();
              $scss_compiler->setImportPaths(array_merge(array(dirname($file)), $scss_import_paths));
              $scss = file_get_contents($file);
              $css = $scss_compiler->compileString($scss)->getCss();

              $css_minifier->add($css);
            } else {
              $css_minifier->add($file);
            }
          }
        }

        $dest = "$path/$dest";
        $this->print(" - <cyan>Minifying</cyan> <brown>$dest</brown>...");
        $css_minifier->minify($dest);
      }
    }

    if (!empty($js_files)) {
      foreach($js_files as $dest => $sources) {
        $js_minifier = new Minify\JS();

        if (!is_array($sources)) $sources = array($sources);

        foreach($sources as $source) {
          $files = glob("$path/" . preg_replace('/^\.?\//', '', $source));

          foreach($files as $file) {
            $this->print(" - <purple>Loading</purple> <brown>$file</brown>...");
            $js_minifier->add($file);
          }
        }

        $dest = "$path/$dest";
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

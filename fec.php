<?php
use MatthiasMullie\Minify;
use ScssPhp\ScssPhp\Compiler as ScssCompiler;
use splitbrain\phpcli\CLI;

class FEC extends CLI {
  protected $css_files = array();
  protected $files = array();
  protected $js_files = array();
  protected $no_minify = false;
  protected $path = '.';
  protected $scss_import_paths = array();
  protected $remove_important_comments = false;
  protected $watcher = null;
  protected $watching = false;

  protected function setup($options) {
    $options->setHelp('A PHP Command Line tool that makes it easy to compile, concat, and minify front-end Javascript and CSS/SCSS dependencies.');

    $options->registerOption('css-output', 'Destination CSS file(s).', 'c', true);
    $options->registerOption('js-output', 'Destination JavaScript file(s).', 'j', true);
    $options->registerOption('no-minify', 'Only merge files, no minification.', 'n', false);
    $options->registerOption('path', 'Path to the fec.json file.', 'p', true);
    $options->registerOption('scss-import-path', 'Directory path(s) to include when locating imported SCSS files.', 's', true);
    $options->registerOption('remove-important-comments', 'Removes comments marked as important.', 'r', false);

    $options->registerCommand('watch', 'Watches for changes in files and compiles them when needed.');
    $options->registerOption('watcher', 'The path to point an EventSource to that will be injected into your JavaScript so that the page is refreshed when files are updated.', 'w', true, 'watch');
  }

  protected function main($options) {
    $this->setupOpts($options);

    switch($options->getCmd()) {
      case 'watch':
        $this->watching = true;
        $this->watch();
        break;
      default:
        $this->compile();
    }
  }

  protected function compile() {
    $this->compile_css();
    $this->compile_js();
    $this->print('Done!');
  }

  protected function compile_css($dest = null) {
    if (!$dest) {
      foreach($this->css_files as $dest => $sources) {
        $this->compile_css($dest);
      }

      return;
    }

    if (!$this->no_minify) {
      $css_minifier = new Minify\CSS();
    }

    $compiled_css = '';
    $sources = $this->css_files[$dest]['sources'];

    foreach($sources as $source) {
      if (substr(basename($source), 0, 1) === '_') continue;

      $this->print(" - <purple>Loading</purple> <brown>$source</brown>...");

      $ext = pathinfo($source, PATHINFO_EXTENSION);

      if ($ext === 'scss') {
        $scss_compiler = new ScssCompiler();
        $import_paths = array_merge(array(dirname($source)), $this->scss_import_paths);

        $scss_compiler->addImportPath(function($path) use($import_paths, $dest) {
          $new_path = null;

          if (ScssCompiler::isCssImport($path)) {
            return null;
          }

          foreach($import_paths as $import_path) {
            $check_path = $import_path . '/' . $path;
            $import_dir_name = dirname($check_path);
            $import_file_name = basename($check_path);


            foreach(array('', '.scss', '.css') as $ext) {
              if (file_exists($import_dir_name . '/' . $import_file_name . $ext)) {
                $new_path = $import_dir_name . '/' . $import_file_name . $ext;
                break;
              } elseif (file_exists($import_dir_name . '/_' . $import_file_name . $ext)) {
                $new_path = $import_dir_name . '/_' . $import_file_name . $ext;
                break;
              }
            }
          }

          if ($new_path) {
            $this->print(" - <purple>Loading</purple> <brown>$new_path</brown>...");

            if (!in_array($new_path, $this->css_files[$dest]['imports'])) {
              $this->css_files[$dest]['imports'][] = $new_path;
            }

            return $new_path;
          } else {
            $this->fatal("Could not find any CSS files matching $path");
          }

          return null;
        });

        $scss = file_get_contents($source);
        $css = $scss_compiler->compileString($scss)->getCss();
      } else {
        $css = file_get_contents($source);
      }

      if ($this->no_minify) {
        $compiled_css .= $css . "\n";
      } else {
        if ($this->should_remove_important_comments($source)) {
          $css = preg_replace('!/\*[^*]*\*+([^/][^*]*\*+)*/!', '', $css); // Remove important comments
        }

        $css_minifier->add($css);
      }
    }

    $dest = "$this->path/$dest";
    $this->print(" - <cyan>Compiling</cyan> <brown>$dest</brown>...");

    if (!$this->no_minify) {
      $compiled_css = $css_minifier->minify();
      $compiled_css = preg_replace('/(?!\/\*!)(?!.*)\n(?!.*)(?!\*\/)/', '', $compiled_css); // Remove extra line breaks
      $compiled_css = preg_replace('/(.+)(\/\*!.*)/', "$1\n$2", $compiled_css); // Make sure important comments start on their own line
    }

    file_put_contents($dest, $compiled_css);
  }

  protected function compile_js($dest = null) {
    if (!$dest) {
      foreach($this->js_files as $dest => $sources) {
        $this->compile_js($dest);
      }

      return;
    }

    if (!$this->no_minify) {
      $js_minifier = new Minify\JS();
    }

    $compiled_js = '';
    $sources = $this->js_files[$dest]['sources'];

    if ($this->watching && $this->watcher) {
      $watcher_js = "var fecWatcher = new EventSource('$this->watcher')
        fecWatcher.addEventListener('message', function (e) {
          if (e.data) {
            var data = JSON.parse(e.data)
            if (data.source && data.source === '$dest') {
              console.log('FEC Watcher: File change detected. Refreshing page...', data.file)
              location.reload()
            }
          }
        });";

      if ($this->no_minify) {
        $compiled_js .= $watcher_js . "\n";
      } else {
        $js_minifier->add($watcher_js);
      }
    }

    foreach($sources as $source) {
      $this->print(" - <purple>Loading</purple> <brown>$source</brown>...");

      $js = file_get_contents($source);

      if ($this->no_minify) {
        $compiled_js .= $js . ";\n";
      } else {
        if ($this->should_remove_important_comments($source)) {
          $js = preg_replace('/(?:(?:\/\*(?:[^*]|(?:\*+[^*\/]))*\*+\/)|(?:(?<!\:|\\\|\'|\")\/\/.*))/', '', $js); // Remove important comments
        }

        $js_minifier->add($js);
      }
    }

    $dest = "$this->path/$dest";
    $this->print(" - <cyan>Compiling</cyan> <brown>$dest</brown>...");

    if (!$this->no_minify) {
      $compiled_js = $js_minifier->minify();
      $compiled_js = preg_replace('/^\n+|^[\t\s]*\n+/', '', $compiled_js); // Remove extra line breaks
    }

    file_put_contents($dest, $compiled_js);
  }

  protected function setupOpts($options) {
    $this->files = $options->getArgs();
    $this->no_minify = $options->getOpt('no-minify', false);
    $this->remove_important_comments = $options->getOpt('remove-important-comments', false);
    $this->scss_import_paths = array_filter(explode(',', $options->getOpt('scss-import-path') ?: ''));
    $this->watcher = $options->getOpt('watcher', null);

    $path_opt = $options->getOpt('path');
    $json_file = null;
    $css_files = array();
    $js_files = array();

    if ($path_opt) {
      if (file_exists($path_opt) && is_dir($path_opt)) {
        $this->path = rtrim($path_opt, '/');
      } else {
        $this->path = dirname($path_opt);
        $json_file = $path_opt;
      }
    }

    if (empty($this->files)) {
      if ($json_file) {
        if (file_exists($json_file)) {
          $json = @file_get_contents($json_file);
        } else {
          $this->fatal("Could not find <yellow>$json_file</yellow>.");
        }
      } elseif (file_exists("$this->path/fec.json")) {
        $json = @file_get_contents("$this->path/fec.json");
      } else {
        $this->fatal("Could not find <yellow>$this->path/fec.json</yellow>.");
      }

      if (!($info = @json_decode($json, true))) {
        $this->fatal("An error occured while decoding JSON data.");
      }

      if (!isset($info['compile']) || !is_array($info['compile'])) {
        $this->fatal("JSON data does not contain a compile item.");
      }

      $compile = $info['compile'];

      if (isset($compile['css'])) $css_files = $compile['css'];
      if (isset($compile['js'])) $js_files = $compile['js'];

      if (isset($info['settings']) && isset($info['settings']['fec'])) {
        $settings = $info['settings']['fec'];

        if (isset($settings['no-minify']) && !$this->no_minify) {
          $this->no_minify = $settings['no-minify'];
        }

        if (isset($settings['remove-important-comments']) && !$this->remove_important_comments) {
          $this->remove_important_comments = $settings['remove-important-comments'];
        }

        if (isset($settings['scss-import-path'])) {
          if (is_array($settings['scss-import-path'])) {
            $settings_scss_import_paths = $settings['scss-import-path'];
          } else {
            $settings_scss_import_paths = explode(',', $settings['scss-import-path']);
          }

          array_walk($settings_scss_import_paths, function(&$path, $idx, $prefix) {
            $path = $prefix . '/' . ltrim($path, '/');
          }, $this->path);

          $this->scss_import_paths = array_merge($this->scss_import_paths, $settings_scss_import_paths);
        }

        if (isset($settings['watcher']) && !$this->watcher) {
          $this->watcher = $settings['watcher'];
        }
      }
    } else {
      $css_source_files = array_filter($this->files, function($file) {
        return preg_match('/\.s?css$/', $file);
      });

      if ($css_dest_file = $options->getOpt('css-output')) {
        $css_files[$css_dest_file] = $css_source_files;
      } else {
        foreach($css_source_files as $css_source_file) {
          $files = glob("$this->path/" . preg_replace('/^\.?\//', '', $css_source_file));

          if (empty($files)) {
            $this->fatal("Could not find any CSS files matching $css_source_file");
          }

          foreach($files as $file) {
            $dest = preg_replace('/\.s?css$/', '.min.css', $file);
            $css_files[$dest] = $file;
          }
        }
      }

      $js_source_files = array_filter($this->files, function($file) {
        return preg_match('/\.js$/', $file);
      });

      if ($js_dest_file = $options->getOpt('js-output')) {
        $js_files[$js_dest_file] = $js_source_files;
      } else {
        foreach($js_source_files as $js_source_file) {
          $files = glob("$this->path/" . preg_replace('/^\.?\//', '', $js_source_file));

          if (empty($files)) {
            $this->fatal("Could not find any JS files matching $js_source_file");
          }

          foreach($files as $file) {
            $dest = preg_replace('/\.js$/', '.min.js', $file);
            $js_files[$dest] = $file;
          }
        }
      }

      if (empty($css_files) && empty($js_files)) {
        $this->fatal("Could not find any CSS or JS files matching " . implode(' ', $this->files));
      }
    }

    if (!empty($css_files)) {
      foreach($css_files as $dest => $sources) {
        $this->css_files[$dest] = array(
          'imports' => array(),
          'sources' => array()
        );

        if (!is_array($sources)) $sources = array($sources);

        foreach($sources as $source_index => $source) {
          $source_files = glob("$this->path/" . preg_replace('/^\.?\//', '', $source));

          if (!empty($source_files)) {
            $this->css_files[$dest]['sources'] = array_merge($this->css_files[$dest]['sources'], $source_files);
          } else {
            $this->fatal("Could not find any CSS files matching $source");
          }
        }
      }
    }

    if (!empty($js_files)) {
      foreach($js_files as $dest => $sources) {
        $this->js_files[$dest] = array(
          'sources' => array()
        );

        if (!is_array($sources)) $sources = array($sources);

        foreach($sources as $source_index => $source) {
          $source_files = glob("$this->path/" . preg_replace('/^\.?\//', '', $source));

          if (!empty($source_files)) {
            $this->js_files[$dest]['sources'] = array_merge($this->js_files[$dest]['sources'], $source_files);
          } else {
            $this->fatal("Could not find any JS files matching $source");
          }
        }
      }
    }

    if (is_string($this->remove_important_comments)) {
      $this->remove_important_comments = explode(',', $this->remove_important_comments);
    }
  }

  protected function should_remove_important_comments($file) {
    if ($this->remove_important_comments === true) {
      return true;
    } elseif (is_array($this->remove_important_comments)) {
      $included = array_filter($this->remove_important_comments, function($matching_path) {
        return substr($matching_path, 0, 1) !== '!';
      });

      $excluded = array_filter($this->remove_important_comments, function($matching_path) {
        return substr($matching_path, 0, 1) === '!';
      });

      $file_included = array_filter($included, function($matching_path) use($file) {
        return substr($file, -strlen($matching_path)) === $matching_path;
      });

      $file_excluded = array_filter($excluded, function($matching_path) use($file) {
        return substr($file, -(strlen($matching_path) - 1)) === substr($matching_path, 1);
      });

      if (count($included)) {
        if (count($file_included) && !count($file_excluded)) {
          return true;
        }
      } elseif (count($excluded) && !count($file_excluded)) {
        return true;
      }
    }

    return false;
  }

  protected function watch() {
    set_time_limit(0);
    system('stty -icanon');
    stream_set_blocking(STDIN, false);

    $watching_msg = 'Watching for file changes... (Press any key to stop)';
    $stdin = fopen('php://stdin', 'r');

    $this->print('Doing an initial compile...');
    $this->compile();
    $this->print($watching_msg);

    while(true) {
      // Bail if any keys are pressed
      if (ord(fgetc($stdin))) {
        break;
      }

      clearstatcache();

      if (!empty($this->css_files)) {
        foreach($this->css_files as $dest => $info) {
          $sources = array_merge($info['sources'], $info['imports']);

          foreach($sources as $source) {
            $last_modified = filemtime($source);

            if ($last_modified >= time()) {
              $this->print("Change detected: $source");
              $this->compile_css($dest);
              sleep(1);
              $this->print($watching_msg);
            }
          }
        }
      }

      if (!empty($this->js_files)) {
        foreach($this->js_files as $dest => $info) {
          $sources = $info['sources'];

          foreach($sources as $source) {
            $last_modified = filemtime($source);

            if ($last_modified >= time()) {
              $this->print("Change detected: $source");
              $this->compile_js($dest);
              sleep(1);
              $this->print($watching_msg);
            }
          }
        }
      }
    }

    if ($this->watcher) {
      $this->print('Compiling one last time to remove watcher JS...');
      $this->watching = false;
      $this->compile();
    }

    $this->print('Bye!');
  }

  protected function print($text, $channel = STDOUT) {
    $active_colors = array();

    $text = preg_replace_callback('/\<(.[^\>]*?)\>/', function($matches) use(&$active_colors) {
      $new_color = $matches[1];
      $colors = array();

      if (substr($new_color, 0, 1) === '/') {
        array_pop($active_colors);
        $colors[] = 'reset';
      } else {
        $active_colors[] = $new_color;
      }

      $colors = array_merge($colors, $active_colors);

      return implode('', array_map(function($color) {
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

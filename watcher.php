<?php
class FEC_Watcher {
  function __construct($files, $path = null) {
    header('Content-Type: text/event-stream');
    header('Cache-Control: no-cache');

    $this->send_message(array(
      'message' => 'Checking for file modifications...'
    ));

    array_walk($files, function(&$tracked_files, $source) use($path) {
      if (is_string($tracked_files)) {
        $tracked_files = array($tracked_files);
      }

      $tracked_files[] = $source;

      if (isset($path)) {
        array_walk($tracked_files, function(&$file) use($path) {
          $file = rtrim($path, '/') . '/' . ltrim($file, '/');
        });
      }
    });

    foreach($files as $source => $files) {
      foreach($files as $file) {
        if (file_exists($file)) {
          $last_modified = filemtime($file);

          if ($last_modified >= time()) {
            $this->send_message(array(
              'action' => 'refresh',
              'file'   => $file,
              'source' => $source
            ));
          }
        }
      }
    }
  }

  function send_message($data) {
    ob_start();
    echo 'id: ' . time() . PHP_EOL;
    echo 'data: ' . json_encode($data) . PHP_EOL;
    echo 'retry: 500' . PHP_EOL;
    echo PHP_EOL;
    ob_flush();
    flush();
  }
}
?>

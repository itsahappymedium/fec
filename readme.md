# Front End Compiler

[![packagist package version](https://img.shields.io/packagist/v/itsahappymedium/fec.svg?style=flat-square)](https://packagist.org/packages/itsahappymedium/fec)
[![packagist package downloads](https://img.shields.io/packagist/dt/itsahappymedium/fec.svg?style=flat-square)](https://packagist.org/packages/itsahappymedium/fec)
[![license](https://img.shields.io/github/license/itsahappymedium/fec.svg?style=flat-square)](license.md)

A PHP Command Line tool that makes it easy to compile, concat, and minify front-end Javascript and CSS/SCSS dependencies.

The [minify](https://github.com/matthiasmullie/minify) and [scssphp](https://github.com/scssphp/scssphp) packages do all of the heavy lifting, this tool simply combines the two into a single CLI tool with some extra features.


## Installation

### To a package (local)

```
composer require-dev itsahappymedium/fec
./vendor/bin/fec help
```

### To your system (global)

```
composer global require itsahappymedium/fec
fec help
```


## Usage

Running FEC without any arguments will load files from a `fec.json` file that is structured like so:

```json
{
  "compile": {
    "css": {
      "build/main.min.css": "scss/main.scss",
      "build/vendor.min.css": [
        "gpm_modules/kazzkiq/balloon.css/src/balloon.scss",
        "scss/vendor/*.scss"
      ]
    },
    "js": {
      "build/main.min.js": "js/*.js",
      "build/vendor.min.js": [
        "gpm_modules/kraaden/autocomplete/autocomplete.js",
        "js/vendor/*.js"
      ]
    }
  }
}
```


### Options

File paths can be passed to FEC to compile those specific files.

  - `--css--output` / `-c` - Define the path to a single file to concat all CSS/SCSS files into one file, or define a path to a directory to process each file individually with the `.min.css` extension.

  - `--js--output` / `-j` - Define the path to a single file to concat all JS files into one file, or define a path to a directory to process each file individually with the `.min.js` extension.

  - `--no-minify` / `-n` - Don't do any minification, just concat files.

  - `--path` / `-p` - Defines a path to either a JSON file, or a directory where a `fec.json` file is located.

  - `--remove-important-comments` / `-r` - Remove all comments, including those that are marked as important. This option also accepts a file name, or the last part of it's file path to only remove important comments from that file. You can also prepend the file name with a `!` to remove important comments from all files but that file. You can pass multiple files by separating them with a comma (`,`).

  - `--scss-import-path` / `-s` -  Define an additional path to check for SCSS imports in. You can pass multiple paths by separating them with a comma (`,`).

  - `--watcher` / `-w` - Define a path to point the [EventSource](https://developer.mozilla.org/en-US/docs/Web/API/EventSource) to that will be injected into your JavaScript so that the page is refreshed when files are updated while running the `watch` command. (See `watch` command section below for more information)


#### Examples

```
fec --js-output build/main.min.js js/*.js

fec --css-output build/main.min.css --js-output build/main.min.js scss/*.scss js/*.js
```


### The `watch` command

Starts a process that watches for file changes and recompiles them as needed. This command will do an initial compile just to make sure everything is up to date.


#### The Watcher

FEC has the ability to automatically refresh your website while running the `watch` command (much like how [Browsersync](https://github.com/Browsersync/browser-sync) works). Simply create a `watcher.php` (or whatever you want to call it) somewhere that is accessible by your website, and place the following code in there:

```php
<?php
require_once('./vendor/autoload.php');
new FEC_Watcher(array(
  'build/main.min.js' => 'build/main.min.css'
), 'content/themes/my-theme');
?>
```

The `FEC_Watcher` class accepts two parameters:

 - `$files` (`String/Array`) - Should be passed the path to a JavaScript file that FEC will generate or an `Array` of JavaScript files. These files will have JavaScript injected into them by FEC to listen for changes. The array can also be multidimensional using the JavaScript file as the keys and additional files to watch for updates as the value (which accepts a `String` or an `Array` of Strings).

 - `$path` (`String`) - All paths used in the `$files` parameter should be relative to where the watcher script is placed. You can define a path with this parameter to prepend to all files listed in the `$files` parameter.

Last, but not least, you will need to set the `--watcher` option to the path where the watcher script is accessible on the server.

If a watcher is used, your files will be recompiled one last time after you are done to remove the injected JavaScript.


### Defining Settings with JSON

You can set most options above by defining them as settings in your JSON file like so:

```json
{
  "compile": {
    "css": {
      "build/main.min.css": "scss/main.scss"
    }
  },
  "settings": {
    "fec": {
      "compress": true,
      "remove-important-comments": true,
      "scss-import-path": "gpm_modules",
      "watcher": "/watcher.php"
    }
  }
}
```

The `scss-import-path` setting accepts a string or an array of strings, these paths are relative to the location of the JSON file.


## Related

 - [GPM](https://github.com/itsahappymedium/gpm) - A PHP Command Line tool that makes it easy to download dependencies from GitHub.


## License

MIT. See the [license.md file](license.md) for more info.

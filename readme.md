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

The `--path` or `-p` option can be passed to define a JSON file or a directory path to where a `fec.json` file is located to load a file list from.

File paths can be passed to FEC to compile those specific files.

The `--css-output` or `-c` option can be passed to define the file where CSS/SCSS input files will be concated and minified into otherwise if this option isn't used, CSS/SCSS files will all be concated and minified into their own individual filenames with their extension changed to `.min.css`.

The `--js-output` or `-j` option can be passed to define the file where JavaScript input files will be concated and minified into otherwise if this option isn't used, JavaScript files will all be concated and minified into their own individual filenames with their extension changed to `.min.js`.

The `--scss-import-path` or `-s` option can be passed to include additional directory paths to locate imported SCSS files. Separate multiple directories with a comma (,).

The `--compress` or `-x` option can be passed to additionally compress the minified files by removing all comments and line breaks.


### Examples

```
fec --js-output build/main.min.js js/*.js

fec --css-output build/main.min.css --js-output build/main.min.js scss/*.scss js/*.js
```


### Defining Settings with JSON

You can set the `scss-import-path` and/or `compress` options by defining them in your JSON file like so:

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
      "scss-import-path": "gpm_modules"
    }
  }
}
```

The `scss-import-path` setting accepts a string or an array of strings, these paths are relative to the location of the JSON file.


## Related

 - [GPM](https://github.com/itsahappymedium/gpm) - A PHP Command Line tool that makes it easy to download dependencies from GitHub.


## License

MIT. See the [license.md file](license.md) for more info.

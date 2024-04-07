<h1 align="center" style="font-size: 56px; margin: 0;">
    Prodtools
</h1>

<p align="center">
  <a href="https://packagist.org/packages/abdelhamiderrahmouni/prodtools"><img src="https://img.shields.io/packagist/dt/abdelhamiderrahmouni/prodtools.svg" alt="Total Downloads"></a>
  <a href="https://packagist.org/packages/abdelhamiderrahmouni/prodtools"><img src="https://img.shields.io/packagist/v/abdelhamiderrahmouni/prodtools.svg?label=stable" alt="Latest Stable Version"></a>
  <a href="https://packagist.org/packages/abdelhamiderrahmouni/prodtools"><img src="https://img.shields.io/packagist/l/abdelhamiderrahmouni/prodtools.svg" alt="License"></a>
</p>

<h4> <center>A set of command line tools to help me ship code faster, made with laravel zero.</center></h4>

Available functionalities :
- Lang files translation.
- Assets images optimization.
- files/folder/project compression
------

# Documentation
## General Use
### Installation
You can install the package via composer:
```bash
composer global require abdelhamiderrahmouni/prodtools
```
add it to your PATH:
```bash
export PATH="$PATH:$HOME/.composer/vendor/bin"
```

### Usage
the package offers three main commands:

#### translations
This command will translate your lang files from the source language to the target language or languages,
it supports multiple languages and can output the result in JSON format.
```bash
prodtools translate <from> <to:multiple languages> [--json]
```

example:
The following translates the php files holding the translations from `en` language to `fr` and `ar` languages
and outputs folders for `ar` and `fr` languages.
```bash
prodtools translate en fr ar
```

The following translates the JSON files holding the translations from `en` language to `fr` and `ar` languages
and outputs the translations to `ar.json` and `fr.json` files.
```bash
prodtools translate en fr ar --json
```
You can find more details on [superduper filament starter kit](https://github.com/riodwanto/superduper-filament-starter-kit)
@riodwanto is the original author of the command, I just added the JSON output feature.

#### Assets optimization
The idea behind this command came from the need to optimize the images in the assets folder before shipping the application.
As doing that manually is a tedious task, I decided to automate it.

This command will optimize the images in the assets folder.
```bash
prodtools images:comporess <path> # defaults to public/assets
```

#### Project compression

This command will compress the project files and folders into a single archive file.
```bash
prodtools zipper <path> # defaults to current directory
```

This commad has an optional `--exclude` flag to specify the files and folders to exclude from the archive.
```bash
prodtools zipper --exclude=".git,node_modules,.github,.idea,storage,.env,public/.htaccess"
```

## Development
### build standalone application
Run the following command to build a standalone application:
```bash
php production-tools app:build prodtools
```

You will then be able to execute it directly:
```bash
./builds/prodtools
```
or on Windows:
```bash
C:\application\path> php builds\prodtools # this is still not ready for windows
```

## License

Laravel Zero is an open-source software licensed under the MIT license.

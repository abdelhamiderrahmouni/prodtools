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
options:
- `path` is the path to the folder containing the images to optimize; by default it's public/assets.
flags:
- `--keep` to keep the original images; by default it's false.
- `--prefix` to add a prefix to the original folder's name; by default it's "old_".
- `--details` to display the optimization details; by default it's false.

#### Project compression

This command will compress the project files and folders into a single archive file.
```bash
prodtools compress <path> # defaults to current directory
```
options:
- `path` is the path to the folder to compress; by default it's the current directory.
flags:
- `--exclude` is the files and folders to exclude from the archive; by default it's ".git,node_modules".
- `--output` is the output file name; by default it's FolderName in snake case like "folder_name.zip".

This commad has an optional `--exclude` flag to specify the files and folders to exclude from the archive.
```bash
prodtools compress --exclude=".git,node_modules,.github,.idea,storage,.env,public/.htaccess"
```
#### Get Random Images from Unsplash
This command will download random images from unsplash and save them in the specified folder.
```bash
prodtools images:get <folder> --amount "<count>" --size "<width>x<height>" --terms "<search terms>" --multi-size --sizes "<width>x<height>,<width>x<height>,..." --amounts "<count>,<count>,..."
```
options:
- `folder` is the folder where the images will be saved; by default the command will create a folder named local_images in the current path.
flags:
- `--amount` is the number of images to download; by default it's 5.
- `--size` is the size of the images to download; by default it's 200x200.
- `--terms` is the search terms to use; by default it's empty.
- `--multi-size` is a flag to download multiple sizes of the images; by default it's false.
- `--sizes` is the sizes of the images to download; by default it's "200x200,1280x720".
- `--amounts` is the number of images to download for each size; by default it's "5,5"

example:
```bash
prodtools images:get public/assets/images --amount "10" --size "1920x1080" --terms "nature,animals"
```
```bash
prodtools images:get public/assets/images --multi-size --sizes "1920x1080,1280x720,640x480" --amounts "5,3,2" # make sure the amounts match the sizes count
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

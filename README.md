# aadhar-scan
This Repo is used to fetch aadhar card details.

### Dependency
```
$ composer require thiagoalessio/tesseract_ocr
```
More Info: https://github.com/thiagoalessio/tesseract-ocr-for-php

### Install
```
$ composer require espl/aadharscanner
```

#### Usage 

```
use Espl\Aadharscanner\Aadhar;

$image = $path.'/'. $image_name;
$aadharObj = new Aadhar();
$details = $aadharObj->extractDetails($image);
```

# aadhar-scan
This Repo is used to fetch aadhar card details.


#### Usage

use Espl\Aadharscanner\Aadhar;

$image = $path.'/'. $image_name;
$aadharObj = new Aadhar();
$details = $aadharObj->extractDetails($image);

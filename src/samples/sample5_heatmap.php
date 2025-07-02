<?php
require_once '../../vendor/autoload.php';

require_once '../MapData.php';
require_once '../LatLng.php';
require_once '../Polygon.php';
require_once '../Markers.php';
require_once '../OpenStreetMap.php';
require_once '../XY.php';
require_once '../Heatmap.php';
require_once '../Circle.php';

use PHPOSMStaticAPIHeatmap\OpenStreetMap;
use PHPOSMStaticAPIHeatmap\LatLng;
use PHPOSMStaticAPIHeatmap\Polygon;
use PHPOSMStaticAPIHeatmap\Markers;

function random_decimals($min, $max)
{
	 $decimals = max(strlen(substr(strrchr(rtrim(sprintf('%0.9f',$min),"0"), "."), 1)), strlen(substr(strrchr(rtrim(sprintf('%0.9f',$max),"0"), "."), 1)));
	 $factor = pow(10, $decimals);
	 return rand($min*$factor, $max*$factor) / $factor;
}

// generate some random points
$minlat = 44.351172;
$maxlat = 44.352887;
$minlon = 2.565672;
$maxlon = 2.571092;
$minweight = 1;
$maxweight = 10;
$maxpoints = 50;
$points = [];
for($i=0;$i<$maxpoints;$i++)
{
		$points[] = [random_decimals($minlat,$maxlat),random_decimals($minlon,$maxlon), rand($minweight,$maxweight)];
}

// add points to heatmap
$heatmap = new Heatmap(Heatmap::SHADES32,100,false,false,75);
foreach($points as $point)
{
		$heatmap->addPoint(new LatLng($point[0], $point[1]),$point[2]);
}

// generate the image
$res = (new OpenStreetMap(new LatLng(44.351933, 2.568113), 17, 600, 400))
					->addMarkers(
											 (new Markers(__DIR__ . '../resources/marker.png'))
												->setAnchor(Markers::ANCHOR_CENTER, Markers::ANCHOR_BOTTOM)
												->addMarker(new LatLng(44.351933, 2.568113))
												->addMarker(new LatLng(44.351510, 2.570020))
												->addMarker(new LatLng(44.351873, 2.566250))
											 )
					 ->addDraw($heatmap)
					 ->getImage()
           ->displayPNG();

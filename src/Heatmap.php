<?php	
namespace OpenStreetMapStaticAPIHeatmap;

use OpenStreetMapStaticAPIHeatmap\Interfaces\Draw;
use OpenStreetMapStaticAPIHeatmap\Image;
use OpenStreetMapStaticAPIHeatmap\GdGradientAlpha;

/**
 * DantSu\OpenStreetMapStaticAPI\Heatmap draw a heatmap on the map.
 *
 * Based on the code and resources of https://github.com/xird/gd-heatmap/blob/master/gd_heatmap.php
 *
 * @package OpenStreetMapStaticAPIHeatmap
 * @author Bas Vijfwinkel
 * @access public
 * @see https://github.com/DantSu/php-osm-static-api Github page of this project
 */
class Heatmap implements Draw
{
    const SHADES8  = 8;
    const SHADES16 = 16;
    const SHADES25 = 25;
    const SHADES32 = 32;

    private $points = [];

    /**
     * @var int 8 /16 / 25 / 32
     */
    private $numberofshades;

    /**
     * @var int
     */
    private $dotradius;

    /**
     * @var int
     */
    private $dither;

    /**
     * @var int
     */
    private $fill_with_smallest; // use smallest shade to fill the entire remaining area

    private $opacity;

    private $type = 'spots'; // 'squares'

    /**
     * Heatmap constructor.
     */
    public function __construct(int $numberofshades = self::SHADES32, int $dotradius = 50, bool $dither = false, bool $fill_with_smallest = false, $opacity = 30)
    {
        $this->numberofshades     = $numberofshades;
        $this->dotradius          = $dotradius;
        $this->dither             = $dither;
        $this->fill_with_smallest = $fill_with_smallest;
        $this->opacity            = $opacity;
        $this->type               = 'squares';
    }

    /**
     * Add a latitude and longitude to the heatmap
     * @param LatLng $latLng Latitude and longitude to add
     * @return $this Fluent interface
     */
    public function addPoint(LatLng $latLng, int $weight = 1): Heatmap
    {
        $this->points[] = ['latlon' => $latLng, 'weight' => $weight];
        return $this;
    }

    /**
     * Draw the heatmap on the map image.
     *
     * @see https://github.com/DantSu/php-image-editor See more about DantSu\PHPImageEditor\Image
     *
     * @param Image $image The map image (An instance of DantSu\PHPImageEditor\Image)
     * @param MapData $mapData Bounding box of the map
     * @return $this Fluent interface
     */
    public function draw(Image $mapImage, MapData $mapData): Heatmap
    {
        // convert the lat lon coordiantes to xy coordinates
        $cPoints = [];
        foreach($this->points as $point)
        {
            $xy = $mapData->convertLatLngToPxPosition($point['latlon']);
            $cPoints[] = [
                          'x'      => $xy->getX(),
                          'y'      => $xy->getY(), 
                          'weight' => $point['weight']
                         ];
        }

        // Find the maximum value from the given data.
        $max_data_value = max(array_column($cPoints,'weight'));

        // prepare a new canvas to draw on
        $canvas = Image::newCanvas($mapImage->getWidth(), $mapImage->getHeight());
        $canvasimage = $canvas->getImage();
        \imagefill($canvasimage, 0, 0, \imagecolorallocatealpha($canvasimage, 255, 255, 255, 0));
        \imagealphablending($canvasimage, true);
        \imagesavealpha($canvasimage, true);

if ($this->type == 'spots')
{
        // Create a separate spot image for each value to be shown, with different
        // amounts of black. Having 25 separate shades of colour looks like a decent
        // number.
        $spots = array();
        for ($i = 0; $i < $this->numberofshades; $i++)
        {
            // The gradient lib doesn't like too small values for $alpha_end, so we use
            // $numberofshades for that, which happens to work well.
            $alpha_end = $this->map($i, 0, $this->numberofshades - 1, $this->numberofshades, 255);
            $temp = new GdGradientAlpha($this->dotradius, $this->dotradius, 'ellipse','#000', 0x00, $alpha_end, 0);
            $spot = $temp->get_image();
            \imagealphablending($spot, true);
            \imagesavealpha($spot, true);
            $spots[$i] = $spot;
        }

        // Go through the data, and add appropriate spot images to the heatmap image.
        for ($i = 0; $i < count($cPoints); $i++)
        {
            $value = $cPoints[$i]['weight'];
            $value = $this->map($value, 1, $max_data_value, 0, $this->numberofshades - 1);
            \imagecopy($canvasimage , $spots[$value], $cPoints[$i]['x'], $cPoints[$i]['y'] , 0 , 0 , $this->dotradius , $this->dotradius);
        }
        \imagetruecolortopalette($canvasimage, $this->dither, $this->numberofshades);

        // Get the gradient from an image file
        $gi = 'gradient-' . $this->numberofshades . ($this->fill_with_smallest ? "-fill" : "") . '.png';
        $filepathname = realpath(dirname(__FILE__))."/resources/".$gi;
        if (!file_exists($filepathname))
        {
            $text = "Can't find gradient file " . $filepathname . " in resource folder";
            $im = \imagecreate(600, 480);
            $bg_color = \imagecolorallocate($im, 255, 0, 0);
            $text_color = \imagecolorallocate($im, 0, 0, 0);
            \imagestring($im, 3, 5, 5, $text , $text_color);
            $mapImage->pasteGdImageOn($im,600,480,0,0);
            return $this;
        }
        $gs = \imagecreatefrompng($filepathname);
        \imagetruecolortopalette($gs, TRUE, $this->numberofshades);

        // Get a list of different gray values in the image, and order them.
        $grays = array();
        for ($i = 0; $i < imagecolorstotal($canvasimage); $i++)
        {
            $c = \imagecolorsforindex($canvasimage, $i);
            $grays[] = str_pad(($c['red'] * 65536) + ($c['green'] * 256) + $c['blue'], 8, '0', STR_PAD_LEFT) . ':' . $i;
        }
        sort($grays);
        $indexes = array();
        foreach ($grays as $gray)
        {
            $indexes[] = substr($gray, strpos($gray, ':') + 1);
        }

        // Replace each shade of gray with the matching rainbow colour.
        $i = 0;
        foreach ($indexes as $index)
        {
            $fill_index = \imagecolorat($gs , $i, 0);
            $fill_color = \imagecolorsforindex($gs, $fill_index);
            \imagecolorset($canvasimage, $index, $fill_color['red'], $fill_color['green'], $fill_color['blue']);
            $i++;
        }

        if (!$this->fill_with_smallest)
        {
            // Finally switch from white background to transparent.
            $closest = \imagecolorclosest ($canvasimage, 255 , 255 , 255);
            \imagecolortransparent($canvasimage, $closest);
        }
}
else
{
// grid style heatmap
        $squaresize = 40;
        $horizontalsquares = $mapImage->getWidth() / $squaresize;
        $verticaltalsquares = $mapImage->getHeight() / $squaresize;

        // step 2  : Create a separate square image for each value to be shown, with different amounts of black.
        $spots = array();
        for ($i = 0; $i < $this->numberofshades; $i++)
        {
            // The gradient lib doesn't like too small values for $alpha_end, so we use
            // $numberofshades for that, which happens to work well.
            $alpha_end = $this->map($i, 0, $this->numberofshades - 1, $this->numberofshades, 255);

            $spot =  \imagecreatetruecolor($squaresize,$squaresize);
            \imagefill($spot, 0, 0, \imagecolorallocatealpha($spot, 0, 0, 0, $alpha_end));

            \imagealphablending($spot, true);
            \imagesavealpha($spot, true);
            $spots[$i] = $spot;
        }

        // step 3 : Go through the data, and add appropriate spot images to the heatmap image.
        for ($i = 0; $i < count($cPoints); $i++)
        {
            $value = $cPoints[$i]['weight'];
            $value = $this->map($value, 1, $max_data_value, 0, $this->numberofshades - 1);
            $gridx = round($cPoints[$i]['x'] / $squaresize) * $squaresize;
            $gridy = round($cPoints[$i]['y'] / $squaresize) * $squaresize;
            \imagecopy($canvasimage , $spots[$value], $gridx, $gridy, 0 , 0 , $squaresize , $squaresize);
        }
        \imagetruecolortopalette($canvasimage, $this->dither, $this->numberofshades);

        // Get the gradient from an image file
        $gi = 'gradient-' . $this->numberofshades . ($this->fill_with_smallest ? "-fill" : "") . '.png';
        $filepathname = realpath(dirname(__FILE__))."/resources/".$gi;
        if (!file_exists($filepathname))
        {
            $text = "Can't find gradient file " . $filepathname . " in resource folder";
            $im = \imagecreate(600, 480);
            $bg_color = \imagecolorallocate($im, 255, 0, 0);
            $text_color = \imagecolorallocate($im, 0, 0, 0);
            \imagestring($im, 3, 5, 5, $text , $text_color);
            $mapImage->pasteGdImageOn($im,600,480,0,0);
            return $this;
        }
        $gs = \imagecreatefrompng($filepathname);
        \imagetruecolortopalette($gs, TRUE, $this->numberofshades);

        // Get a list of different gray values in the image, and order them.
        $grays = array();
        for ($i = 0; $i < imagecolorstotal($canvasimage); $i++)
        {
            $c = \imagecolorsforindex($canvasimage, $i);
            $grays[] = str_pad(($c['red'] * 65536) + ($c['green'] * 256) + $c['blue'], 8, '0', STR_PAD_LEFT) . ':' . $i;
        }
        sort($grays);
        $indexes = array();
        foreach ($grays as $gray)
        {
            $indexes[] = substr($gray, strpos($gray, ':') + 1);
        }

        // Replace each shade of gray with the matching rainbow colour.
        $i = 0;
        foreach ($indexes as $index)
        {
            $fill_index = \imagecolorat($gs , $i, 0);
            $fill_color = \imagecolorsforindex($gs, $fill_index);
            \imagecolorset($canvasimage, $index, $fill_color['red'], $fill_color['green'], $fill_color['blue']);
            $i++;
        }

        if (!$this->fill_with_smallest)
        {
            // Finally switch from white background to transparent.
            $closest = \imagecolorclosest ($canvasimage, 255 , 255 , 255);
            \imagecolortransparent($canvasimage, $closest);
        }

}
        // paste canvas on the image
        $this->pasteOnOpacity($mapImage,$canvas,0,0,$this->opacity);

        return $this;
    }
// ================= This stuff should move to image.php in php-image-editor
    protected function pasteOnOpacity($mapImage,$canvas,$posX,$posY,$opacity)
    {
//        if (!$this->isImageDefined() || !static::isGdImage($image)) {
//            return $this;
//        }
        $canvasImage = $canvas->getImage();
        $image = $mapImage->getImage();
        $imageWidth = $mapImage->getWidth();
        $imageHeight = $mapImage->getHeight();
        $posX = $this->convertPosX($posX, $imageWidth);
        $posY = $this->convertPosY($posY, $imageHeight);

        \imagesavealpha($image, false);
        \imagealphablending($image, true);
        \imagecopymerge($image, $canvasImage, $posX, $posY, 0, 0, $imageWidth, $imageHeight,$opacity);
        \imagealphablending($image, false);
        \imagesavealpha($image, true);
    }

    /**
     * Convert horizontal `Image::ALIGN_...` to int position.
     *
     * @param int|string $posX Pixel position or `Image::ALIGN_...` constant
     * @param int $width Width of the element to align
     * @return int Horizontal pixel position
     */
    private function convertPosX($posX, int $width = 0): int
    {
        switch ($posX) {
            case Image::ALIGN_LEFT:
                return 0;
            case Image::ALIGN_CENTER:
                return \round($this->width / 2 - $width / 2);
            case Image::ALIGN_RIGHT:
                return $this->width - $width;
        }
        return \round($posX);
    }

    /**
     * Convert vertical `Image::ALIGN_...` to int position.
     *
     * @param int|string $posY Pixel position or `Image::ALIGN_...` constant
     * @param int $height Height of the element to align
     * @return int Vertical pixel position
     */
    private function convertPosY($posY, int $height = 0): int
    {
        switch ($posY) {
            case Image::ALIGN_TOP:
                return 0;
            case Image::ALIGN_MIDDLE:
                return \round($this->height / 2 - $height / 2);
            case Image::ALIGN_BOTTOM:
                return $this->height - $height;
        }
        return \round($posY);
    }
// ===========================================================

    /**
     * Get bounding box of the shape
     * @return LatLng[]
     */
    public function getBoundingBox(): array
    {
        return MapData::getBoundingBoxFromPoints($this->points);
    }

    /**
    * function for mapping values from one range to another.
    */
    protected function map($value, $fromLow, $fromHigh, $toLow, $toHigh)
    {
        $fromRange = $fromHigh - $fromLow;
        $toRange = $toHigh - $toLow;
        $scaleFactor = $toRange / $fromRange;
        $tmpValue = $value - $fromLow;
        $tmpValue *= $scaleFactor;
        return $tmpValue + $toLow;
    }
}

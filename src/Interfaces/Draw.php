<?php
namespace OpenStreetMapStaticAPIHeatmap\Interfaces;


use OpenStreetMapStaticAPIHeatmap\MapData;
use OpenStreetMapStaticAPIHeatmap\Image;

interface Draw
{
    public function getBoundingBox(): array;

    public function draw(Image $image, MapData $mapData);
}

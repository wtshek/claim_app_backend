<?php

class processing_bar
{
    public $height;
    public $width;
    public $canvas;
    public $font;

    function __construct($height, $width, $font)
    {
        $this->canvas = imagecreatetruecolor($height, $width);
        $this->font = $font;
    }

    function setBackGroundColor($r, $g, $b)
    {
        // Define the background color
        $background = imagecolorallocatealpha($this->canvas, $r, $g, $b, 127);
        imagefill($this->canvas, 0, 0, $background);
    }

    // Arguments for  drawPoint : ($path, $x, $y, $w, $h)
    // path: path of the image
    // x: the starting x poistion of element
    // y: the x position of element
    // w: width of image
    // h: height of image
    function drawPoint($path, $x, $y, $w, $h)
    {
        $point = imagecreatefrompng($path);
        imagecopy($this->canvas, $point, $x, $y, 0, 0, $w, $h);
    }

    // Arguments for  drawLine : ($x1, $y1, $x2, $y2, $r, $g, $b)
    // x1: the starting x1 poistion of element
    // x2: the ending x2 poisiton of element
    // y1: the y1 position of element
    // y2: the y2 position of element
    // r: red
    // g: green
    // b: blue
    function drawLine($x1, $y1, $x2, $y2, $r, $g, $b)
    {
        $color = imagecolorallocate($this->canvas, $r, $g, $b);
        imagefilledrectangle($this->canvas, $x1, $y1, $x2, $y2, $color);
    }

    function output()
    {
        imagesavealpha($this->canvas,true);
        imagepng($this->canvas);
    }

    // Arguments for  drawEmptyBar : ($mode,$path, $x, $x2, $y, $w, $h)
    // mode: mode of action
    // path: path of the image
    // x: the starting x poistion of element
    // x2: the ending x poisiton of element ( Only need for image to be scale)
    // y: the x position of element
    // w: width of image
    // h: height of image

    // $mode is used to determine the action, line action in drawEmptyBar will scale the image to make the longer line.
    // so you need to input the end position , otherwise you input null.
    // and you should always draw the scaled line before the head and tail
    function drawEmptyBar($mode,$path, $x, $x2, $y, $w, $h)
    {
        $temp = imagecreatefrompng($path);

        if($mode == 'head' || $mode == 'body' || $mode == 'tail')
        {
            imagecopy($this->canvas, $temp, $x, $y, 0, 0, $w, $h);
        }
        else if($mode == 'line')
        {
            $w = $x2-$x;
            $h = imagesy($temp);
            $newimage = imagecreatetruecolor($w, $h);
            imagealphablending($newimage, false);
            $transparent = imagecolorallocatealpha($newimage, 255, 255, 255, 127);
            imagefilledrectangle($newimage, 0, 0, $w, $h, $transparent);
            imagecopyresampled($newimage, $temp, 0, 0, 0, 0, $w, $h, imagesx($temp), imagesy($temp));
            imagecopy($this->canvas, $newimage, $x, $y, 0, 0, $w, $h);
        }
    }

    // Arguments for  drawMessageBox : ($mode,$path, $x, $x2, $y, $w, $h)
    // mode: mode of action
    // path: path of the image
    // x: the starting x poistion of element
    // x2: the ending x poisiton of element ( Only need for image to be scale)
    // y: the x position of element
    // w: width of image
    // h: height of image
    function drawMessageBox($mode, $path, $x, $x2, $y, $w, $h)
    {
        $temp = imagecreatefrompng($path);

        if($mode == 'head')
        {
            imagecopy($this->canvas, $temp, $x, $y, 0, 0, $w, $h);
        }
        else if($mode == 'tail')
        {
            $temp = imagerotate($temp, 180, 0);
            imagecopy($this->canvas, $temp, $x, $y-1, 0, 0, $w, $h);
        }
        else if($mode == 'body')
        {
            $w = $x2-$x;
            $h = imagesy($temp);
            $newimage = imagecreatetruecolor($w, $h);
            imagealphablending($newimage, false);
            $transparent = imagecolorallocatealpha($newimage, 255, 255, 255, 127);
            imagefilledrectangle($newimage, 0, 0, $w, $h, $transparent);
            imagecopyresampled($newimage, $temp, 0, 0, 0, 0, $w, $h, imagesx($temp), imagesy($temp));
            imagecopy($this->canvas, $newimage, $x, $y, 0, 0, $w, $h);
        }
        else if($mode == 'indicator')
        {
            imagecopy($this->canvas, $temp, $x, $y-1, 0, 0, $w, $h);
        }
    }

    function drawString($str, $x, $y)
    {
        $textcolor = imagecolorallocate($this->canvas, 90,90,90);
        imagettftext($this->canvas, 11, 0, $x, $y, $textcolor, $this->font, $str);
    }

    function getStringSize($str)
    {
        list($left,, $right) = imagettfbbox(11, 0,  $this->font, $str);
        $width = $right - $left;
        return $width;
    }
}
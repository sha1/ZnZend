<?php
/**
 * ZnZend
 *
 * @author Zion Ng <zion@intzone.com>
 * @link   http://github.com/zionsg/ZnZend for canonical source repository
 */

namespace ZnZend\Captcha;

use Zend\Captcha\AbstractWord;
use Zend\Session\Container;
use ZnZend\Captcha\Exception;
use ZnZend\Captcha\Service\QuestionServiceInterface;

/**
 * Image-based captcha adapter for custom questions and answers
 *
 * Takes in custom question (eg. 'What is the color of the sky?') and answer (eg. 'blue')
 * and generates inline image for question.
 *
 * Bulk of code from Zend\Captcha\Image with the following differences:
 *   - No need for imgDir as inline image is generated
 *   - Width and height are automatically calculated to fit the text
 *   - Captcha question is supplied by service, not generated internally
 *   - isValid() checks against supplied answer, not question
 */
class Question extends AbstractWord
{
    /**
     * Service for generating question and verifying answer - must be set
     *
     * @var QuestionServiceInterface
     */
    protected $service;

    /**
     * Fully qualified path to image font file - must be set
     *
     * @var string
     */
    protected $font;

    /**
     * Font size
     *
     * @var int
     */
    protected $fontSize = 24;

    /**
     * Padding (in pixels) around question in generated image
     *
     * @var int
     */
    protected $padding = 5;

    /**
     * Percentage of noise dots on image
     *
     * @var int
     */
    protected $dotNoisePercent = 10;

    /**
     * Number of noise lines on image
     *
     * @var int
     */
    protected $lineNoiseLevel = 5;

    /**
     * Flag for transforming image (wave transforms)
     *
     * @var bool
     */
    protected $transformImage = true;

    /**
     * Constructor
     *
     * @param  array|\Traversable $options
     * @throws Exception\ExtensionNotLoadedException
     */
    public function __construct($options = null)
    {
        if (!extension_loaded('gd')) {
            throw new Exception\ExtensionNotLoadedException('Question CAPTCHA requires GD extension');
        }

        if (!function_exists("imagepng")) {
            throw new Exception\ExtensionNotLoadedException('Question CAPTCHA requires PNG support');
        }

        if (!function_exists("imageftbbox")) {
            throw new Exception\ExtensionNotLoadedException('Question CAPTCHA requires FreeType fonts support');
        }

        if (!isset($options['font']) || !file_exists($options['font'])) {
            throw new Exception\NoFontProvidedException('Question CAPTCHA requires font');
        }

        parent::__construct($options);
    }

    /**
     * Generate captcha
     *
     * @return string captcha ID
     */
    public function generate()
    {
        $id = $this->generateRandomId();
        $this->setId($id);

        $service = $this->getService();
        $service->generate();

        $session = $this->getSession();
        $session->question = $service->getQuestion();
        $session->image = $this->generateImage();

        return $id;
    }

    /**
     * Get form view helper name used to render captcha
     *
     * @return string
     */
    public function getHelperName()
    {
        return 'znZendFormCaptchaQuestion';
    }

    /**
     * Get captcha question
     *
     * @return string
     */
    public function getQuestion()
    {
        return $this->getSession()->question;
    }

    /**
     * Get inline image generated for captcha question
     *
     * @return string
     */
    public function getImage()
    {
        return $this->getSession()->image;
    }

    /**
     * Set captcha question service
     *
     * @param  QuestionServiceInterface $service
     * @return Question
     */
    public function setService(QuestionServiceInterface $service)
    {
        $this->service = $service;
        return $this;
    }

    /**
     * Get captcha question service
     *
     * @return QuestionServiceInterface
     */
    public function getService()
    {
        return $this->service;
    }

    /**
     * Set captcha font
     *
     * @param  string $font
     * @return Question
     */
    public function setFont($font)
    {
        $this->font = $font;
        return $this;
    }

    /**
     * Get font to use when generating captcha
     *
     * @return string
     */
    public function getFont()
    {
        return $this->font;
    }

    /**
     * Set captcha font size
     *
     * @param  int $fontSize
     * @return Question
     */
    public function setFontSize($fontSize)
    {
        $this->fontSize = $fontSize;
        return $this;
    }

    /**
     * Get font size
     *
     * @return int
     */
    public function getFontSize()
    {
        return $this->fontSize;
    }

    /**
     * Set padding
     *
     * @param  int $padding
     * @return Question
     */
    public function setPadding($padding)
    {
        $this->padding = $padding;
        return $this;
    }

    /**
     * Get padding
     *
     * @return int
     */
    public function getPadding()
    {
        return $this->padding;
    }

    /**
     * @param int $dotNoisePercent
     * @return Question
     */
    public function setDotNoisePercent($dotNoisePercent)
    {
        $this->dotNoisePercent = $dotNoisePercent;
        return $this;
    }

    /**
     * @return int
     */
    public function getDotNoisePercent()
    {
        return $this->dotNoisePercent;
    }

    /**
     * @param int $lineNoiseLevel
     * @return Question
     */
    public function setLineNoiseLevel($lineNoiseLevel)
    {
        $this->lineNoiseLevel = $lineNoiseLevel;
        return $this;
    }

    /**
     * @return int
     */
    public function getLineNoiseLevel()
    {
        return $this->lineNoiseLevel;
    }

    /*
     * @param bool $transformImage
     * @return Question
     */
    public function setTransformImage($transformImage)
    {
        $this->transformImage = $transformImage;
        return $this;
    }

    /**
     * @return bool
     */
    public function getTransformImage()
    {
        return $this->transformImage;
    }

    /**
     * Generate random frequency
     *
     * @return float
     */
    protected function randomFreq()
    {
        return mt_rand(700000, 1000000) / 15000000;
    }

    /**
     * Generate random phase
     *
     * @return float
     */
    protected function randomPhase()
    {
        // random phase from 0 to pi
        return mt_rand(0, 3141592) / 1000000;
    }

    /**
     * Generate random character size
     *
     * @return int
     */
    protected function randomSize()
    {
        return mt_rand(300, 700) / 100;
    }

    /**
     * Generate inline image for captcha question
     *
     * Image format is in PNG
     * Wave transform from http://www.captcha.ru/captchas/multiwave/
     *
     * @return string
     * @throws Exception\NoFontProvidedException if no font was set
     * @throws Exception\ImageNotLoadableException if start image cannot be loaded
     */
    protected function generateImage()
    {
        $text     = $this->getQuestion();
        $font     = $this->getFont();
        $fontSize = $this->getFontSize();
        $padding  = $this->getPadding();
        $dotNoisePercent = $this->getDotNoisePercent();
        $lineNoiseLevel = $this->getLineNoiseLevel();

        if (empty($font)) {
            throw new Exception\NoFontProvidedException('Question CAPTCHA requires font');
        }

        // Retrieve bounding box and calculate text dimensions
        $typeSpace   = imagettfbbox($fontSize, 0, $font, $text);
        $width  = abs($typeSpace[4] - $typeSpace[0]) + (2 * $padding);
        $height = abs($typeSpace[5] - $typeSpace[1]) + (2 * $padding);

        // Create canvas
        $image      = imagecreatetruecolor($width, $height);
        $textColor  = imagecolorallocate($image, 0, 0, 0);
        $noiseColor = imagecolorallocate($image, 128, 128, 128);
        $bgColor    = imagecolorallocate($image, 255, 255, 255);
        imagefill($image, 0, 0, $bgColor);

        // Create text
        // $y is the font baseline, not the very bottom of the character, eg. "g", hence minus (1.5 * padding)
        $x = $padding;
        $y = $height - (1.5 * $padding);
        $noisePixels = ($dotNoisePercent / 100) * ($width * $height);
        imagettftext($image, $fontSize, 0, $x, $y, $textColor, $font, $text);

        // Add noise
        for ($i = 0; $i < $noisePixels; $i++) {
            imagesetpixel($image, mt_rand(0, $width), mt_rand(0, $height), $noiseColor);
        }
        for ($i = 0; $i < $this->lineNoiseLevel; $i++) {
            imageline(
                $image,
                mt_rand(0, $width), mt_rand(0, $height),
                mt_rand(0, $width), mt_rand(0, $height),
                $textColor
            );
        }

        // Transform image
        if ($this->getTransformImage()) {
            $transformedImage = imagecreatetruecolor($width, $height);
            $bgColor = imagecolorallocate($transformedImage, 255, 255, 255);
            imagefilledrectangle($transformedImage, 0, 0, $width - 1, $height - 1, $bgColor);

            // Apply wave transforms
            $freq1 = $this->randomFreq();
            $freq2 = $this->randomFreq();
            $freq3 = $this->randomFreq();
            $freq4 = $this->randomFreq();

            $ph1 = $this->randomPhase();
            $ph2 = $this->randomPhase();
            $ph3 = $this->randomPhase();
            $ph4 = $this->randomPhase();

            $szx = $this->randomSize();
            $szy = $this->randomSize();

            for ($x = 0; $x < $width; $x++) {
                for ($y = 0; $y < $height; $y++) {
                    $sx = $x + (sin($x*$freq1 + $ph1) + sin($y*$freq3 + $ph3)) * $szx;
                    $sy = $y + (sin($x*$freq2 + $ph2) + sin($y*$freq4 + $ph4)) * $szy;

                    if ($sx < 0 || $sy < 0 || $sx >= $width - 1 || $sy >= $height - 1) {
                        continue;
                    } else {
                        $color   = (imagecolorat($image, $sx, $sy) >> 16)         & 0xFF;
                        $colorX  = (imagecolorat($image, $sx + 1, $sy) >> 16)     & 0xFF;
                        $colorY  = (imagecolorat($image, $sx, $sy + 1) >> 16)     & 0xFF;
                        $colorXY = (imagecolorat($image, $sx + 1, $sy + 1) >> 16) & 0xFF;
                    }

                    if ($color == 255 && $colorX == 255 && $colorY == 255 && $colorXY == 255) {
                        // ignore background
                        continue;
                    } elseif ($color == 0 && $colorX == 0 && $colorY == 0 && $colorXY == 0) {
                        // transfer inside of the image as-is
                        $newcolor = 0;
                    } else {
                        // do anti-aliasing for border items
                        $fracX  = $sx - floor($sx);
                        $fracY  = $sy - floor($sy);
                        $fracX1 = 1 - $fracX;
                        $fracY1 = 1 - $fracY;

                        $newcolor = $color   * $fracX1 * $fracY1
                                  + $colorX  * $fracX  * $fracY1
                                  + $colorY  * $fracX1 * $fracY
                                  + $colorXY * $fracX  * $fracY;
                    }

                    imagesetpixel(
                        $transformedImage, $x, $y, imagecolorallocate($transformedImage, $newcolor, $newcolor, $newcolor)
                    );
                }
            }
        } // end transform image

        // Capture image output
        ob_start();
        if ($this->getTransformImage()) {
            imagepng($transformedImage);
            imagedestroy($transformedImage);
        } else {
            imagepng($image);
        }
        imagedestroy($image);
        $imageData = ob_get_contents();
        ob_end_clean();

        return 'data:image/png;base64,' . base64_encode($imageData);
    }

    /**
     * Validate the question
     *
     * Exact code as \Zend\Captcha\AbstractWord::isValid() except for last part
     * where it checks against the answer instead of the word/question.
     *
     * @see    \Zend\Validator\ValidatorInterface::isValid()
     * @param  mixed $value
     * @param  mixed $context
     * @return bool
     */
    public function isValid($value, $context = null)
    {
        if (!is_array($value)) {
            if (!is_array($context)) {
                $this->error(self::MISSING_VALUE);
                return false;
            }
            $value = $context;
        }

        $name = $this->getName();

        if (isset($value[$name])) {
            $value = $value[$name];
        }

        if (!isset($value['input'])) {
            $this->error(self::MISSING_VALUE);
            return false;
        }
        $input = strtolower($value['input']);
        $this->setValue($input);

        if (!isset($value['id'])) {
            $this->error(self::MISSING_ID);
            return false;
        }

        $this->id = $value['id'];
        if (!$this->getService()->verify($input)) {
            $this->error(self::BAD_CAPTCHA);
            return false;
        }

        return true;
    }
}

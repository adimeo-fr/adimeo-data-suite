<?php

namespace App\Model;

class Autopromote extends \AdimeoDataSuite\Model\Autopromote
{
    /** @var  string */
    private $button;

    public function __construct($id, $name, $url, $image, $body, $keywords, $button, $index, $analyzer)
    {
        $this->button = $button;
        parent::__construct($id, $name, $url, $image, $body, $keywords, $index, $analyzer);
    }

    /**
     * @return string
     */
    public function getButton()
    {
        return $this->button;
    }

    /**
     * @param string $button
     */
    public function setButton($button)
    {
        $this->button = $button;
    }
}

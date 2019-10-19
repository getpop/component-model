<?php
namespace PoP\ComponentModel\Hooks;

use PoP\Hooks\Contracts\HooksAPIInterface;
use PoP\Translation\Contracts\TranslationAPIInterface;

class AbstractHookSet
{
    protected $hooksAPI;
    protected $translationAPI;
    public function __construct(
        HooksAPIInterface $hooksAPI,
        TranslationAPIInterface $translationAPI
    ) {
        $this->hooksAPI = $hooksAPI;
        $this->translationAPI = $translationAPI;
    }
}

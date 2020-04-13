<?php

declare(strict_types=1);

namespace PoP\ComponentModel\Facades\Schema;

use PoP\ComponentModel\Schema\FeedbackMessageStoreInterface;
use PoP\Root\Container\ContainerBuilderFactory;

class FeedbackMessageStoreFacade
{
    public static function getInstance(): FeedbackMessageStoreInterface
    {
        return ContainerBuilderFactory::getInstance()->get('feedback_message_store');
    }
}

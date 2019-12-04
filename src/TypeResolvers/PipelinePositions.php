<?php
namespace PoP\ComponentModel\TypeResolvers;

/**
 * Defines the constants indicating where to place a directive in the directive execution pipeline
 * 2 directives are mandatory: Validate and ResolveAndMerge, which are executed in this order.
 * All other directives must indicate where to position themselves, using these 2 directives as anchors.
 * There are 3 positions:
 * 1. At the beginning, before the Validate pipeline
 * 2. In the middle, between the Validate and Resolve directives
 * 3. At the end, after the ResolveAndMerge directive
 */
class PipelinePositions
{
    public const FRONT = 'front';
    public const MIDDLE = 'middle';
    public const BACK = 'back';
}

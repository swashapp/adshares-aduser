<?php

namespace Adshares\Aduser\Data;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class ReCaptchaDataProvider extends AbstractDataProvider
{
    public const NAME = 'rec';

    /**
     * @param string $trackingId
     * @param Request $request
     * @return string|null
     */
    public function getPageUrl(string $trackingId, Request $request): ?string
    {
        return $this->generateUrl(
            'pixel_provider',
            [
                'provider' => self::NAME,
                'tracking' => $trackingId,
                'nonce' => self::generateNonce(),
                '_format' => 'html',
            ],
            UrlGeneratorInterface::ABSOLUTE_URL
        );
    }
}
<?php

namespace Jav\ApiTopiaBundle\GraphQL;

use RuntimeException;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Mercure\Authorization;
use Symfony\Component\Mercure\HubRegistry;

class MercureUrlGenerator
{
    public function __construct(
        private readonly ?HubRegistry $hubRegistry = null,
        private readonly ?Authorization $authorization = null,
        private readonly ?RequestStack $requestStack = null
    ) {
    }

    /**
     * @param array{hub?: string, subscribe?: string[]|string, publish?: string[]|string, additionalClaims?: array<string, mixed>, lastEventId?: string} $options The options to pass to the JWT factory
     * @throws RuntimeException
     */
    public function generate(string $topic, array $options): string
    {
        if ($this->hubRegistry === null) {
            throw new RuntimeException('The Mercure hub registry is not configured.');
        }

        $hub = $options['hub'] ?? null;
        $url = $this->hubRegistry->getHub($hub)->getPublicUrl();
        $url .= '?topic='.rawurlencode($topic);

        if ('' !== ($options['lastEventId'] ?? '')) {
            $url .= '&Last-Event-ID='.rawurlencode($options['lastEventId']);
        }

        if (
            null === $this->authorization ||
            null === $this->requestStack ||
            (!isset($options['subscribe']) && !isset($options['publish']) && !isset($options['additionalClaims'])) ||
            null === $request = method_exists($this->requestStack, 'getMainRequest') ? $this->requestStack->getMainRequest()
                : (method_exists($this->requestStack, 'getMasterRequest') ? $this->requestStack->getMasterRequest() : null)
        ) {
            return $url;
        }

        $this->authorization->setCookie($request, $options['subscribe'] ?? [], $options['publish'] ?? [], $options['additionalClaims'] ?? [], $hub);

        return $url;
    }
}

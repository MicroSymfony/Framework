<?php

namespace MicroSymfony\Framework\Controller;

use MicroSymfony\JWT\Exceptions\VerificationException;
use MicroSymfony\JWT\Verifier;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerAwareTrait;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

class ServiceController implements ContainerAwareInterface
{
    use ContainerAwareTrait;

    /** @var Verifier */
    protected $verifier;

    /**
     * @param Verifier $verifier
     */
    public function setVerifier(Verifier $verifier): void
    {
        $this->verifier = $verifier;
    }

    protected function json($content, $status = 200, $headers = []): JsonResponse
    {
        return new JsonResponse($content, $status, $headers);
    }

    protected function checkAuth(Request $request)
    {
        $defaultTokenHeader = 'X-Auth-Token';
        $defaultServiceHeader = 'X-Service';
        if (null !== $this->container) {
            try {
                $tokenHeader = $this->container->getParameter('symfony.micro_services.service_header');
                $serviceHeader = $this->container->getParameter('symfony.micro_services.token_header');
            } catch (\InvalidArgumentException $exception) {
                // custom headers not defined, stick to defaults
            }
        }

        if (empty($tokenHeader)) {
            $tokenHeader = $defaultTokenHeader;
        }
        if (empty($serviceHeader)) {
            $serviceHeader = $defaultServiceHeader;
        }

        $token = $request->headers->get($tokenHeader);
        $service = $request->headers->get($serviceHeader);

        $result = false;
        try {
            $result = $this->verifier->verify($token, $service);
        } catch (VerificationException $exception) {
            // failed to verify, $result will remain false
        }

        return $result;
    }

    protected function jsonError($error, $code = 200, $headers = []): JsonResponse
    {
        return $this->json(['error' => $error], $code, $headers);
    }

    protected function authFailed(): JsonResponse
    {
        return $this->jsonError('Service authentication failed', 403);
    }
}

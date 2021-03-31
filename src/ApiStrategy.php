<?php declare(strict_types=1);

namespace Reconmap;

use Fig\Http\Message\StatusCodeInterface;
use League\Route\Strategy\JsonStrategy;
use Monolog\Logger;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Reconmap\Services\ApplicationConfig;

class ApiStrategy extends JsonStrategy
{
    public function __construct(ResponseFactoryInterface $responseFactory,
                                private ApplicationConfig $config,
                                private Logger $logger)
    {
        parent::__construct($responseFactory);
    }

    public function getThrowableHandler(): MiddlewareInterface
    {
        return new class ($this->responseFactory->createResponse(), $this->logger) implements MiddlewareInterface {

            public function __construct(private ResponseInterface $response,
                                        private Logger $logger)
            {
            }

            public function process(
                ServerRequestInterface $request,
                RequestHandlerInterface $handler
            ): ResponseInterface
            {
                try {
                    return $handler->handle($request);
                } catch (\Throwable $exception) {
                    $this->logger->error($exception->getMessage());

                    $response = $this->response;

                    if ($exception instanceof Http\Exception) {
                        return $exception->buildJsonResponse($response);
                    }

                    $response->getBody()->write(json_encode([
                        'status_code' => StatusCodeInterface::STATUS_INTERNAL_SERVER_ERROR,
                        'reason_phrase' => 'Internal server error'
                    ]));

                    $response = $response->withAddedHeader('content-type', 'application/json');
                    return $response->withStatus(StatusCodeInterface::STATUS_INTERNAL_SERVER_ERROR, 'Internal server error');
                }
            }
        };
    }

    public function getOptionsCallable(array $methods): callable
    {
        $corsConfig = $this->config->getSettings('cors');
        $allowedOrigins = implode(',', $corsConfig['allowedOrigins']);

        $parentOptionsCallable = parent::getOptionsCallable($methods);
        return function () use ($parentOptionsCallable, $allowedOrigins, $methods): ResponseInterface {
            return $parentOptionsCallable()
                ->withHeader('Access-Control-Allow-Headers', 'Authorization,Bulk-Operation,Content-Type')
                ->withHeader('Access-Control-Allow-Origin', $allowedOrigins);
        };
    }
}

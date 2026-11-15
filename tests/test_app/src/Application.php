<?php
declare(strict_types=1);

namespace TestApp;

use Cake\Http\BaseApplication;
use Cake\Http\Middleware\BodyParserMiddleware;
use Cake\Http\Middleware\CsrfProtectionMiddleware;
use Cake\Http\MiddlewareQueue;
use Cake\Routing\Middleware\RoutingMiddleware;
use Cake\Routing\Route\DashedRoute;
use Cake\Routing\RouteBuilder;
use CsvIngest\CsvIngestPlugin;

/**
 * Minimal host application used by integration tests (CSRF + FormProtection on).
 */
class Application extends BaseApplication
{
    /**
     * @return void
     */
    public function bootstrap(): void
    {
        $this->addPlugin(CsvIngestPlugin::class);
    }

    /**
     * @param \Cake\Http\MiddlewareQueue $middlewareQueue Queue.
     * @return \Cake\Http\MiddlewareQueue
     */
    public function middleware(MiddlewareQueue $middlewareQueue): MiddlewareQueue
    {
        return $middlewareQueue
            ->add(new RoutingMiddleware($this))
            ->add(new BodyParserMiddleware())
            ->add(new CsrfProtectionMiddleware());
    }

    /**
     * @param \Cake\Routing\RouteBuilder $routes Builder.
     * @return void
     */
    public function routes(RouteBuilder $routes): void
    {
        $routes->setRouteClass(DashedRoute::class);
        $routes->scope('/', function (RouteBuilder $builder): void {
            $builder->fallbacks();
        });
        $routes->prefix('Admin', function (RouteBuilder $builder): void {
            $builder->fallbacks();
        });
    }
}

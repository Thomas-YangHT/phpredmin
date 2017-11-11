<?php

/**
 * This file is part of PHPRedmin project.
 *
 * (c) Sasan Rose <sasan.rose@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace PhpRedmin\Test\Middleware;

use PhpRedmin\Middleware\Access;
use PhpRedmin\Model\Group;
use PhpRedmin\Test\Phpunit\MiddlewareTestCase;
use PhpRedmin\Test\Phpunit\Traits;
use PhpRedmin\Url\UrlBuilderInterface;
use Pimple\Container;
use PSR7Sessions\Storageless\Http\SessionMiddleware;
use PSR7Sessions\Storageless\Session\SessionInterface;
use Redis as PhpRedis;

/**
 * @group middleware
 */
class AccessTest extends MiddlewareTestCase
{
    use Traits\Redis;

    protected $container;
    protected $logger;
    protected $model;
    protected $redis;
    protected $session;
    protected $url;

    public function setUp()
    {
        parent::setUp();

        $this->model = $this->createMock(Group::class);
        $this->redis = $this->createMock(PhpRedis::class);
        $this->url = $this->createMock(UrlBuilderInterface::class);
        $this->session = $this->createMock(SessionInterface::class);
        $this->container = new Container();

        $this->mockDefaultConnect();

        $this->request
            ->expects($this->once())
            ->method('getAttribute')
            ->with(SessionMiddleware::SESSION_ATTRIBUTE)
            ->willReturn($this->session);
    }

    public function testAdmin()
    {
        $this->session
            ->expects($this->once())
            ->method('get')
            ->with('email')
            ->willReturn('alpha@bravo.com');

        $this->model
            ->expects($this->once())
            ->method('isMember')
            ->with('administrators', 'alpha@bravo.com')
            ->willReturn(TRUE);

        $this->response
            ->expects($this->never())
            ->method('withRedirect');

        $middleware = new Access(
            $this->model,
            $this->url,
            $this->redis,
            $this->container
        );

        $middleware($this->request, $this->response, $this->next);
    }

    public function testNotAdmin()
    {
        $this->session
            ->expects($this->once())
            ->method('get')
            ->with('email')
            ->willReturn('alpha@bravo.com');

        $this->model
            ->expects($this->once())
            ->method('isMember')
            ->with('administrators', 'alpha@bravo.com')
            ->willReturn(FALSE);

        $this->url
            ->expects($this->once())
            ->method('setPath')
            ->with('access-denied');

        $this->url
            ->expects($this->once())
            ->method('toString')
            ->willReturn('test-uri');

        $this->response
            ->expects($this->once())
            ->method('withRedirect')
            ->with('test-uri');

        $middleware = new Access(
            $this->model,
            $this->url,
            $this->redis,
            $this->container
        );

        $middleware($this->request, $this->response, $this->next);
    }
}

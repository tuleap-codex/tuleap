<?php
/**
 * Copyright (c) Enalean, 2020-Present. All Rights Reserved.
 *
 * This file is a part of Tuleap.
 *
 * Tuleap is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * Tuleap is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Tuleap. If not, see <http://www.gnu.org/licenses/>.
 */

declare(strict_types=1);

use Laminas\HttpHandlerRunner\Emitter\SapiEmitter;
use Tuleap\Authentication\Scope\AuthenticationScopeBuilderFromClassNames;
use Tuleap\Authentication\SplitToken\PrefixedSplitTokenSerializer;
use Tuleap\Authentication\SplitToken\SplitTokenVerificationStringHasher;
use Tuleap\DB\DBFactory;
use Tuleap\DB\DBTransactionExecutorWithConnection;
use Tuleap\Http\HTTPFactoryBuilder;
use Tuleap\Http\Server\DisableCacheMiddleware;
use Tuleap\Http\Server\RejectNonHTTPSRequestMiddleware;
use Tuleap\OAuth2Server\AccessToken\OAuth2AccessTokenCreator;
use Tuleap\OAuth2Server\AccessToken\Scope\OAuth2AccessTokenScopeSaver;
use Tuleap\OAuth2Server\Grant\AuthCodeGrantController;
use Tuleap\OAuth2Server\Grant\AuthorizationCodeGrantResponseBuilder;
use Tuleap\OAuth2Server\ProjectAdmin\ListAppsController;
use Tuleap\Project\Admin\Navigation\NavigationItemPresenter;
use Tuleap\Project\Admin\Navigation\NavigationPresenter;
use Tuleap\Request\CollectRoutesEvent;
use Tuleap\Request\DispatchableWithRequest;
use Tuleap\User\OAuth2\AccessToken\OAuth2AccessTokenDAO;
use Tuleap\User\OAuth2\AccessToken\OAuth2AccessTokenVerifier;
use Tuleap\User\OAuth2\AccessToken\PrefixOAuth2AccessToken;
use Tuleap\User\OAuth2\AccessToken\Scope\OAuth2AccessTokenScopeDAO;
use Tuleap\User\OAuth2\AccessToken\Scope\OAuth2AccessTokenScopeRetriever;
use Tuleap\User\OAuth2\BearerTokenHeaderParser;
use Tuleap\User\OAuth2\Scope\DemoOAuth2Scope;
use Tuleap\User\PasswordVerifier;

require_once __DIR__ . '/../vendor/autoload.php';

// phpcs:ignore PSR1.Classes.ClassDeclaration.MissingNamespace,Squiz.Classes.ValidClassName.NotCamelCaps
final class oauth2_serverPlugin extends Plugin
{
    public function __construct($id)
    {
        parent::__construct($id);
        $this->setScope(self::SCOPE_SYSTEM);
        bindtextdomain('tuleap-oauth2_server', __DIR__ . '/../site-content');
    }

    public function getHooksAndCallbacks()
    {
        $this->addHook(NavigationPresenter::NAME);
        $this->addHook(CollectRoutesEvent::NAME);

        return parent::getHooksAndCallbacks();
    }

    public function getPluginInfo(): PluginInfo
    {
        if ($this->pluginInfo === null) {
            $plugin_info = new PluginInfo($this);
            $plugin_info->setPluginDescriptor(
                new PluginDescriptor(
                    dgettext('tuleap-oauth2_server', 'OAuth2 Server'),
                    file_get_contents(__DIR__ . '/../VERSION'),
                    dgettext('tuleap-oauth2_server', 'Delegate access to Tuleap resources')
                )
            );
            $this->pluginInfo = $plugin_info;
        }

        return $this->pluginInfo;
    }

    public function collectProjectAdminNavigationItems(NavigationPresenter $presenter): void
    {
        $project_id = urlencode((string)$presenter->getProjectId());
        $html_url   = $this->getPluginPath() . "/project/$project_id/admin";
        $presenter->addItem(
            new NavigationItemPresenter(
                dgettext('tuleap-oauth2_server', 'OAuth2 Apps'),
                $html_url,
                ListAppsController::PANE_SHORTNAME,
                $presenter->getCurrentPaneShortname()
            )
        );
    }

    public function collectRoutesEvent(CollectRoutesEvent $routes): void
    {
        $routes->getRouteCollector()->addGroup(
            $this->getPluginPath(),
            function (FastRoute\RouteCollector $r): void {
                $r->get(
                    '/project/{project_id:\d+}/admin',
                    $this->getRouteHandler('routeGetProjectAdmin')
                );
                $r->post(
                    '/project/{project_id:\d+}/admin/add-app',
                    $this->getRouteHandler('routePostProjectAdmin')
                );
                $r->post(
                    '/project/{project_id:\d+}/admin/delete-app',
                    $this->getRouteHandler('routeDeleteProjectAdmin')
                );
                $r->get(
                    '/testendpoint',
                    $this->getRouteHandler('routeTestEndpoint')
                );
                $r->post(
                    '/access_token',
                    $this->getRouteHandler('routeAccessTokenCreation')
                );
            }
        );
        $routes->getRouteCollector()->get(
            '/oauth2_server/authorize',
            $this->getRouteHandler('routeAuthorizationEndpointGet')
        );
    }

    public function routeGetProjectAdmin(): DispatchableWithRequest
    {
        return ListAppsController::buildSelf();
    }

    public function routePostProjectAdmin(): DispatchableWithRequest
    {
        return \Tuleap\OAuth2Server\ProjectAdmin\AddAppController::buildSelf();
    }

    public function routeDeleteProjectAdmin(): DispatchableWithRequest
    {
        return \Tuleap\OAuth2Server\ProjectAdmin\DeleteAppController::buildSelf();
    }

    public function routeAuthorizationEndpointGet(): DispatchableWithRequest
    {
        $response_factory = HTTPFactoryBuilder::responseFactory();
        $stream_factory   = HTTPFactoryBuilder::streamFactory();
        return \Tuleap\OAuth2Server\AuthorizationServer\AuthorizationEndpointGetController::buildSelf(
            $response_factory,
            $stream_factory,
            new SapiEmitter(),
            new RejectNonHTTPSRequestMiddleware($response_factory, $stream_factory),
            new DisableCacheMiddleware()
        );
    }

    public function routeTestEndpoint(): \Tuleap\OAuth2Server\TestEndpointController
    {
        $response_factory = HTTPFactoryBuilder::responseFactory();
        $stream_factory   = HTTPFactoryBuilder::streamFactory();
        $password_handler = \PasswordHandlerFactory::getPasswordHandler();
        return new \Tuleap\OAuth2Server\TestEndpointController(
            $response_factory,
            $stream_factory,
            UserManager::instance(),
            new SapiEmitter(),
            new RejectNonHTTPSRequestMiddleware($response_factory, $stream_factory),
            new \Tuleap\User\OAuth2\ResourceServer\OAuth2ResourceServerMiddleware(
                $response_factory,
                new BearerTokenHeaderParser(),
                new PrefixedSplitTokenSerializer(new PrefixOAuth2AccessToken()),
                new OAuth2AccessTokenVerifier(
                    new OAuth2AccessTokenDAO(),
                    new OAuth2AccessTokenScopeRetriever(
                        new OAuth2AccessTokenScopeDAO(),
                        new AuthenticationScopeBuilderFromClassNames(
                            DemoOAuth2Scope::class
                        )
                    ),
                    UserManager::instance(),
                    new SplitTokenVerificationStringHasher()
                ),
                DemoOAuth2Scope::fromItself(),
                new User_LoginManager(
                    EventManager::instance(),
                    UserManager::instance(),
                    new PasswordVerifier($password_handler),
                    new User_PasswordExpirationChecker(),
                    $password_handler
                )
            )
        );
    }

    public function routeAccessTokenCreation(): AuthCodeGrantController
    {
        $response_factory = HTTPFactoryBuilder::responseFactory();
        $stream_factory   = HTTPFactoryBuilder::streamFactory();
        return new AuthCodeGrantController(
            $response_factory,
            $stream_factory,
            new AuthorizationCodeGrantResponseBuilder(
                new OAuth2AccessTokenCreator(
                    new PrefixedSplitTokenSerializer(new PrefixOAuth2AccessToken()),
                    new SplitTokenVerificationStringHasher(),
                    new OAuth2AccessTokenDAO(),
                    new OAuth2AccessTokenScopeSaver(new OAuth2AccessTokenScopeDAO()),
                    new DateInterval('PT1H'),
                    new DBTransactionExecutorWithConnection(DBFactory::getMainTuleapDBConnection())
                )
            ),
            UserManager::instance(),
            new SapiEmitter(),
            new RejectNonHTTPSRequestMiddleware($response_factory, $stream_factory),
            new DisableCacheMiddleware()
        );
    }
}

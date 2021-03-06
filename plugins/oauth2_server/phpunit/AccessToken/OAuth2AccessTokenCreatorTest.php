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

namespace Tuleap\OAuth2Server\AccessToken;

use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;
use Tuleap\Authentication\Scope\AuthenticationScope;
use Tuleap\Authentication\SplitToken\SplitToken;
use Tuleap\Authentication\SplitToken\SplitTokenFormatter;
use Tuleap\Authentication\SplitToken\SplitTokenVerificationStringHasher;
use Tuleap\Cryptography\ConcealedString;
use Tuleap\OAuth2Server\AccessToken\Scope\OAuth2AccessTokenScopeSaver;
use Tuleap\Test\DB\DBTransactionExecutorPassthrough;
use Tuleap\User\OAuth2\AccessToken\OAuth2AccessTokenDAO;

final class OAuth2AccessTokenCreatorTest extends TestCase
{
    private const EXPECTED_EXPIRATION_DELAY_SECONDS = 30;

    use MockeryPHPUnitIntegration;

    /**
     * @var \Mockery\LegacyMockInterface|\Mockery\MockInterface|OAuth2AccessTokenDAO
     */
    private $access_token_dao;
    /**
     * @var \Mockery\LegacyMockInterface|\Mockery\MockInterface|OAuth2AccessTokenScopeSaver
     */
    private $scope_saver;

    /**
     * @var OAuth2AccessTokenCreator
     */
    private $token_creator;

    protected function setUp(): void
    {
        $this->access_token_dao = \Mockery::mock(OAuth2AccessTokenDAO::class);
        $this->scope_saver      = \Mockery::mock(OAuth2AccessTokenScopeSaver::class);

        $formatter = new class implements SplitTokenFormatter
        {
            public function getIdentifier(SplitToken $token) : ConcealedString
            {
                return $token->getVerificationString()->getString();
            }
        };

        $this->token_creator = new OAuth2AccessTokenCreator(
            $formatter,
            new SplitTokenVerificationStringHasher(),
            $this->access_token_dao,
            $this->scope_saver,
            new \DateInterval('PT' . self::EXPECTED_EXPIRATION_DELAY_SECONDS . 'S'),
            new DBTransactionExecutorPassthrough()
        );
    }

    public function testCanIssueANewAccessToken(): void
    {
        $current_time = new \DateTimeImmutable('@10');

        $this->access_token_dao->shouldReceive('create')->once()->andReturn(1);
        $this->scope_saver->shouldReceive('saveAccessTokenScopes')->once();

        $access_token = $this->token_creator->issueAccessToken(
            $current_time,
            new \PFUser(['language_id' => 'en']),
            [\Mockery::mock(AuthenticationScope::class)]
        );

        $this->assertEquals(
            $current_time->getTimestamp() + self::EXPECTED_EXPIRATION_DELAY_SECONDS,
            $access_token->getExpiration()->getTimestamp()
        );
    }

    public function testIssueNewAccessTokenIdentifierEachTime(): void
    {
        $current_time = new \DateTimeImmutable('@10');

        $this->access_token_dao->shouldReceive('create')->andReturn(1);
        $this->scope_saver->shouldReceive('saveAccessTokenScopes');
        $user   = new \PFUser(['language_id' => 'en']);
        $scopes = [\Mockery::mock(AuthenticationScope::class)];

        $access_token_1 = $this->token_creator->issueAccessToken($current_time, $user, $scopes);
        $access_token_2 = $this->token_creator->issueAccessToken($current_time, $user, $scopes);

        $this->assertNotEquals(
            $access_token_1->getIdentifier()->getString(),
            $access_token_2->getIdentifier()->getString()
        );
    }
}

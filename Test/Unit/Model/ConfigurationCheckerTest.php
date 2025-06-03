<?php
/**
 * Copyright © Performance, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Performance\Review\Test\Unit\Model;

use Magento\Framework\App\DeploymentConfig;
use Magento\Framework\App\State;
use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\Exception\LocalizedException;
use Performance\Review\Api\Data\IssueInterface;
use Performance\Review\Model\ConfigurationChecker;
use Performance\Review\Model\IssueFactory;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Unit tests for ConfigurationChecker
 *
 * @covers \Performance\Review\Model\ConfigurationChecker
 */
class ConfigurationCheckerTest extends TestCase
{
    /**
     * @var ConfigurationChecker
     */
    private ConfigurationChecker $configurationChecker;

    /**
     * @var DeploymentConfig|MockObject
     */
    private $deploymentConfigMock;

    /**
     * @var State|MockObject
     */
    private $appStateMock;

    /**
     * @var TypeListInterface|MockObject
     */
    private $cacheTypeListMock;

    /**
     * @var IssueFactory|MockObject
     */
    private $issueFactoryMock;

    /**
     * @var LoggerInterface|MockObject
     */
    private $loggerMock;

    /**
     * @inheritdoc
     */
    protected function setUp(): void
    {
        $this->deploymentConfigMock = $this->createMock(DeploymentConfig::class);
        $this->appStateMock = $this->createMock(State::class);
        $this->cacheTypeListMock = $this->createMock(TypeListInterface::class);
        $this->issueFactoryMock = $this->createMock(IssueFactory::class);
        $this->loggerMock = $this->createMock(LoggerInterface::class);

        $this->configurationChecker = new ConfigurationChecker(
            $this->deploymentConfigMock,
            $this->appStateMock,
            $this->cacheTypeListMock,
            $this->issueFactoryMock,
            $this->loggerMock
        );
    }

    /**
     * Test check configuration with developer mode
     *
     * @return void
     */
    public function testCheckConfigurationWithDeveloperMode(): void
    {
        $issueMock = $this->createMock(IssueInterface::class);
        
        $this->appStateMock->expects($this->once())
            ->method('getMode')
            ->willReturn(State::MODE_DEVELOPER);

        $this->issueFactoryMock->expects($this->once())
            ->method('create')
            ->with($this->arrayHasKey('priority'))
            ->willReturn($issueMock);

        $this->deploymentConfigMock->expects($this->once())
            ->method('get')
            ->with('cache')
            ->willReturn([]);

        $this->cacheTypeListMock->expects($this->once())
            ->method('getTypes')
            ->willReturn([]);

        $issues = $this->configurationChecker->checkConfiguration();
        
        $this->assertIsArray($issues);
        $this->assertCount(1, $issues);
        $this->assertInstanceOf(IssueInterface::class, $issues[0]);
    }

    /**
     * Test check configuration with production mode
     *
     * @return void
     */
    public function testCheckConfigurationWithProductionMode(): void
    {
        $this->appStateMock->expects($this->once())
            ->method('getMode')
            ->willReturn(State::MODE_PRODUCTION);

        $this->deploymentConfigMock->expects($this->once())
            ->method('get')
            ->with('cache')
            ->willReturn([
                'frontend' => [
                    'default' => ['backend' => 'Cm_Cache_Backend_Redis']
                ]
            ]);

        $this->cacheTypeListMock->expects($this->once())
            ->method('getTypes')
            ->willReturn([]);

        $this->issueFactoryMock->expects($this->never())
            ->method('create');

        $issues = $this->configurationChecker->checkConfiguration();
        
        $this->assertIsArray($issues);
        $this->assertEmpty($issues);
    }

    /**
     * Test check configuration with exception
     *
     * @return void
     */
    public function testCheckConfigurationWithException(): void
    {
        $exception = new \Exception('Test exception');
        
        $this->appStateMock->expects($this->once())
            ->method('getMode')
            ->willThrowException($exception);

        $this->loggerMock->expects($this->once())
            ->method('error')
            ->with('Configuration check failed: Test exception');

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('Failed to check configuration: Test exception');

        $this->configurationChecker->checkConfiguration();
    }

    /**
     * Test check configuration with disabled caches
     *
     * @return void
     */
    public function testCheckConfigurationWithDisabledCaches(): void
    {
        $cacheTypeMock1 = $this->createMock(\Magento\Framework\App\Cache\Type\FrontendPool::class);
        $cacheTypeMock1->method('getStatus')->willReturn(false);
        
        $cacheTypeMock2 = $this->createMock(\Magento\Framework\App\Cache\Type\FrontendPool::class);
        $cacheTypeMock2->method('getStatus')->willReturn(true);

        $this->appStateMock->expects($this->once())
            ->method('getMode')
            ->willReturn(State::MODE_PRODUCTION);

        $this->deploymentConfigMock->expects($this->once())
            ->method('get')
            ->with('cache')
            ->willReturn([]);

        $this->cacheTypeListMock->expects($this->once())
            ->method('getTypes')
            ->willReturn([
                'config' => $cacheTypeMock1,
                'layout' => $cacheTypeMock2
            ]);

        $issueMock = $this->createMock(IssueInterface::class);
        $this->issueFactoryMock->expects($this->exactly(2))
            ->method('create')
            ->willReturn($issueMock);

        $issues = $this->configurationChecker->checkConfiguration();
        
        $this->assertIsArray($issues);
        $this->assertCount(2, $issues);
    }
}
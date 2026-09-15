<?php
declare(strict_types=1);

namespace Tests\Unit\WebSocket;

use CallMetrics\WebSocket\EventBridge;
use CallMetrics\WebSocket\Server;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Unit tests for CallMetrics\WebSocket\EventBridge.
 *
 * EventBridge is a singleton that delegates broadcasts to Server.
 * We use reflection to reset the singleton between tests and
 * mock the Server (a regular object) via PHPUnit's createMock().
 */
class EventBridgeTest extends TestCase
{
    private ReflectionClass $reflection;

    protected function setUp(): void
    {
        $this->reflection = new ReflectionClass(EventBridge::class);

        // Reset singleton between tests
        $prop = $this->reflection->getProperty('instance');
        $prop->setAccessible(true);
        $prop->setValue(null, null);
    }

    // ── Helper ────────────────────────────────────────────────

    private function getServerProperty(): \ReflectionProperty
    {
        $prop = $this->reflection->getProperty('server');
        $prop->setAccessible(true);
        return $prop;
    }

    private function createInitializedBridge(?Server $server = null): EventBridge
    {
        $bridge = EventBridge::getInstance();
        if ($server !== null) {
            $prop = $this->getServerProperty();
            $prop->setValue($bridge, $server);
        }
        return $bridge;
    }

    // ══════════════════════════════════════════════════════════
    //  Singleton behavior
    // ══════════════════════════════════════════════════════════

    #[\PHPUnit\Framework\Attributes\Test]
    public function testGetInstanceReturnsSameInstance(): void
    {
        $first = EventBridge::getInstance();
        $second = EventBridge::getInstance();

        $this->assertSame($first, $second);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testIsReadyFalseBeforeInit(): void
    {
        $bridge = EventBridge::getInstance();

        $this->assertFalse($bridge->isReady());
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testIsReadyTrueAfterInit(): void
    {
        $mockServer = $this->createMock(Server::class);
        EventBridge::init($mockServer);

        $bridge = EventBridge::getInstance();

        $this->assertTrue($bridge->isReady());
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testInitStoresServerReference(): void
    {
        $mockServer = $this->createMock(Server::class);
        EventBridge::init($mockServer);

        $bridge = EventBridge::getInstance();
        $prop = $this->getServerProperty();

        $this->assertSame($mockServer, $prop->getValue($bridge));
    }

    // ══════════════════════════════════════════════════════════
    //  Broadcast delegation — when ready
    // ══════════════════════════════════════════════════════════

    #[\PHPUnit\Framework\Attributes\Test]
    public function testBroadcastCallEventDelegatesToServer(): void
    {
        $mockServer = $this->createMock(Server::class);
        $mockServer->expects($this->once())
            ->method('broadcastCallEvent')
            ->with(1, 'call_started', ['callid' => 'x']);

        $bridge = $this->createInitializedBridge($mockServer);
        $bridge->broadcastCallEvent(1, 'call_started', ['callid' => 'x']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testBroadcastPbxHealthDelegatesToServer(): void
    {
        $mockServer = $this->createMock(Server::class);
        $mockServer->expects($this->once())
            ->method('broadcastPbxHealth')
            ->with(5, ['cpu' => 80]);

        $bridge = $this->createInitializedBridge($mockServer);
        $bridge->broadcastPbxHealth(5, ['cpu' => 80]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testBroadcastQueueUpdateDelegatesToServer(): void
    {
        $mockServer = $this->createMock(Server::class);
        $mockServer->expects($this->once())
            ->method('broadcastQueueUpdate')
            ->with(3, ['waiting' => 2]);

        $bridge = $this->createInitializedBridge($mockServer);
        $bridge->broadcastQueueUpdate(3, ['waiting' => 2]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testBroadcastDashboardDelegatesToServer(): void
    {
        $mockServer = $this->createMock(Server::class);
        $mockServer->expects($this->once())
            ->method('broadcastDashboard')
            ->with(['kpi' => 42]);

        $bridge = $this->createInitializedBridge($mockServer);
        $bridge->broadcastDashboard(['kpi' => 42]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testBroadcastToChannelDelegatesToServer(): void
    {
        $mockServer = $this->createMock(Server::class);
        $mockServer->expects($this->once())
            ->method('broadcastToChannel')
            ->with('custom_channel', ['type' => 'raw']);

        $bridge = $this->createInitializedBridge($mockServer);
        $bridge->broadcastToChannel('custom_channel', ['type' => 'raw']);
    }

    // ══════════════════════════════════════════════════════════
    //  Broadcasts are no-ops when not ready
    // ══════════════════════════════════════════════════════════

    #[\PHPUnit\Framework\Attributes\Test]
    public function testBroadcastCallEventNoOpWhenNotReady(): void
    {
        $bridge = EventBridge::getInstance();
        // Should not throw — silently returns
        $bridge->broadcastCallEvent(1, 'call_started', []);
        $this->assertTrue(true);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testBroadcastPbxHealthNoOpWhenNotReady(): void
    {
        $bridge = EventBridge::getInstance();
        $bridge->broadcastPbxHealth(1, []);
        $this->assertTrue(true);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testBroadcastQueueUpdateNoOpWhenNotReady(): void
    {
        $bridge = EventBridge::getInstance();
        $bridge->broadcastQueueUpdate(1, []);
        $this->assertTrue(true);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testBroadcastDashboardNoOpWhenNotReady(): void
    {
        $bridge = EventBridge::getInstance();
        $bridge->broadcastDashboard([]);
        $this->assertTrue(true);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testBroadcastToChannelNoOpWhenNotReady(): void
    {
        $bridge = EventBridge::getInstance();
        $bridge->broadcastToChannel('channel', []);
        $this->assertTrue(true);
    }
}

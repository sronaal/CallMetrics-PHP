<?php
declare(strict_types=1);

namespace Tests\Unit\WebSocket;

use CallMetrics\WebSocket\Server;
use PHPUnit\Framework\TestCase;
use Ratchet\ConnectionInterface;

// ──────────────────────────────────────────────────────────────
// Test doubles defined in-file to avoid autoloading conflicts.
// ──────────────────────────────────────────────────────────────

/**
 * Simulates an HTTP request object with controllable headers.
 * Server accesses $conn->httpRequest->getHeaders().
 */
class MockHttpRequest
{
    /** @var array<string, list<string>> */
    private array $headers;

    /** @param array<string, list<string>> $headers */
    public function __construct(array $headers = [])
    {
        $this->headers = $headers;
    }

    /** @return array<string, list<string>> */
    public function getHeaders(): array
    {
        return $this->headers;
    }
}

/**
 * Lightweight ConnectionInterface double with captured state.
 */
class MockWsConnection implements ConnectionInterface
{
    public int $resourceId;
    public ?MockHttpRequest $httpRequest = null;
    /** @var string[] All messages sent via send() */
    public array $sentMessages = [];
    public bool $closed = false;

    public function send($data)
    {
        $this->sentMessages[] = $data;
        return $this;
    }

    public function close()
    {
        $this->closed = true;
    }

    public function lastSentPayload(): ?array
    {
        $last = end($this->sentMessages);
        return $last !== false ? json_decode($last, true) : null;
    }
}

// ──────────────────────────────────────────────────────────────
// ServerTest
// ──────────────────────────────────────────────────────────────

/**
 * Unit tests for CallMetrics\WebSocket\Server.
 *
 * Tests that involve Pbx / Database static calls use @runInSeparateProcess
 * and load fake doubles before the real classes are autoloaded.
 */
class ServerTest extends TestCase
{
    private Server $server;

    protected function setUp(): void
    {
        // Suppress echo output from Server methods
        ob_start();
        $this->server = new Server();
    }

    protected function tearDown(): void
    {
        ob_end_clean();
    }

    // ── Helper ────────────────────────────────────────────────

    private function makeConnection(
        int $id,
        ?MockHttpRequest $httpRequest = null
    ): MockWsConnection {
        $conn = new MockWsConnection();
        $conn->resourceId = $id;
        $conn->httpRequest = $httpRequest ?? new MockHttpRequest();
        return $conn;
    }

    // ══════════════════════════════════════════════════════════
    //  onOpen() — Client (no agent header)
    // ══════════════════════════════════════════════════════════

    #[\PHPUnit\Framework\Attributes\Test]
    public function testOnOpenClientWithoutAgentHeaderSendsAuthenticated(): void
    {
        $conn = $this->makeConnection(1);

        $this->server->onOpen($conn);

        $payload = $conn->lastSentPayload();
        $this->assertNotNull($payload);
        $this->assertSame('authenticated', $payload['action']);
        $this->assertSame('client', $payload['type']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testOnOpenClientDoesNotCloseConnection(): void
    {
        $conn = $this->makeConnection(1);
        $this->server->onOpen($conn);

        $this->assertFalse($conn->closed);
    }

    // ══════════════════════════════════════════════════════════
    //  onOpen() — Agent (separate process — needs FakePbx)
    // ══════════════════════════════════════════════════════════

    #[\PHPUnit\Framework\Attributes\Test]
    #[\PHPUnit\Framework\Attributes\RunInSeparateProcess]
    #[\PHPUnit\Framework\Attributes\PreserveGlobalState(false)]
    public function testOnOpenValidAgentSendsAuthenticatedWithTypeAgent(): void
    {
        require_once __DIR__ . '/../../Doubles/FakePbx.php';

        \CallMetrics\Models\Pbx::setFindByAgenteIdResult([
            'id' => 10,
            'tenant_id' => 1,
            'agente_id' => 'agent-abc',
            'activo' => true,
        ]);

        $conn = $this->makeConnection(5, new MockHttpRequest([
            'X-Agent-ID' => ['agent-abc'],
        ]));

        $this->server->onOpen($conn);

        $payload = $conn->lastSentPayload();
        $this->assertNotNull($payload);
        $this->assertSame('authenticated', $payload['action']);
        $this->assertSame('agent', $payload['type']);
        $this->assertSame('agent-abc', $payload['agente_id']);
        $this->assertSame(10, $payload['pbx_id']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    #[\PHPUnit\Framework\Attributes\RunInSeparateProcess]
    #[\PHPUnit\Framework\Attributes\PreserveGlobalState(false)]
    public function testOnOpenValidAgentAutoSubscribesToAgentChannel(): void
    {
        require_once __DIR__ . '/../../Doubles/FakePbx.php';

        \CallMetrics\Models\Pbx::setFindByAgenteIdResult([
            'id' => 10,
            'tenant_id' => 1,
            'agente_id' => 'agent-abc',
            'activo' => true,
        ]);

        $conn = $this->makeConnection(5, new MockHttpRequest([
            'X-Agent-ID' => ['agent-abc'],
        ]));

        $this->server->onOpen($conn);

        // First message = subscribed to agent channel, second = authenticated
        $this->assertCount(2, $conn->sentMessages);
        $subPayload = json_decode($conn->sentMessages[0], true);
        $this->assertSame('subscribed', $subPayload['action']);
        $this->assertSame('agent_agent-abc', $subPayload['channel']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    #[\PHPUnit\Framework\Attributes\RunInSeparateProcess]
    #[\PHPUnit\Framework\Attributes\PreserveGlobalState(false)]
    public function testOnOpenInvalidAgentSendsAuthFailedAndCloses(): void
    {
        require_once __DIR__ . '/../../Doubles/FakePbx.php';

        \CallMetrics\Models\Pbx::setFindByAgenteIdResult(null);

        $conn = $this->makeConnection(6, new MockHttpRequest([
            'X-Agent-ID' => ['bad-agent'],
        ]));

        $this->server->onOpen($conn);

        $payload = $conn->lastSentPayload();
        $this->assertNotNull($payload);
        $this->assertSame('AUTH_FAILED', $payload['code']);
        $this->assertTrue($conn->closed);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    #[\PHPUnit\Framework\Attributes\RunInSeparateProcess]
    #[\PHPUnit\Framework\Attributes\PreserveGlobalState(false)]
    public function testOnOpenInactiveAgentSendsAuthFailed(): void
    {
        require_once __DIR__ . '/../../Doubles/FakePbx.php';

        \CallMetrics\Models\Pbx::setFindByAgenteIdResult([
            'id' => 10,
            'tenant_id' => 1,
            'agente_id' => 'agent-abc',
            'activo' => false,
        ]);

        $conn = $this->makeConnection(7, new MockHttpRequest([
            'X-Agent-ID' => ['agent-abc'],
        ]));

        $this->server->onOpen($conn);

        $payload = $conn->lastSentPayload();
        $this->assertSame('AUTH_FAILED', $payload['code']);
        $this->assertTrue($conn->closed);
    }

    // ══════════════════════════════════════════════════════════
    //  onMessage() — Client actions
    // ══════════════════════════════════════════════════════════

    #[\PHPUnit\Framework\Attributes\Test]
    public function testOnMessageInvalidJsonReturnsError(): void
    {
        $conn = $this->makeConnection(1);
        $this->server->onOpen($conn);

        $this->server->onMessage($conn, 'not-json');

        $payload = $conn->lastSentPayload();
        $this->assertNotNull($payload);
        $this->assertArrayHasKey('error', $payload);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testOnMessageClientSubscribeReturnsSubscribedAndAddsToChannel(): void
    {
        $conn = $this->makeConnection(1);
        $this->server->onOpen($conn);

        $this->server->onMessage($conn, json_encode([
            'action' => 'subscribe',
            'channel' => 'tenant_1',
        ]));

        $payload = $conn->lastSentPayload();
        $this->assertNotNull($payload);
        $this->assertSame('subscribed', $payload['action']);
        $this->assertSame('tenant_1', $payload['channel']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testOnMessageClientUnsubscribeReturnsUnsubscribedAndRemoves(): void
    {
        $conn = $this->makeConnection(1);
        $this->server->onOpen($conn);

        // Subscribe first
        $this->server->onMessage($conn, json_encode([
            'action' => 'subscribe',
            'channel' => 'tenant_1',
        ]));

        // Then unsubscribe
        $this->server->onMessage($conn, json_encode([
            'action' => 'unsubscribe',
            'channel' => 'tenant_1',
        ]));

        $payload = $conn->lastSentPayload();
        $this->assertNotNull($payload);
        $this->assertSame('unsubscribed', $payload['action']);
        $this->assertSame('tenant_1', $payload['channel']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testOnMessageClientPingReturnsPongWithTimestamp(): void
    {
        $conn = $this->makeConnection(1);
        $this->server->onOpen($conn);

        $before = time();
        $this->server->onMessage($conn, json_encode(['action' => 'ping']));
        $after = time();

        $payload = $conn->lastSentPayload();
        $this->assertNotNull($payload);
        $this->assertSame('pong', $payload['action']);
        $this->assertIsInt($payload['time']);
        $this->assertGreaterThanOrEqual($before, $payload['time']);
        $this->assertLessThanOrEqual($after, $payload['time']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testOnMessageUnknownActionReturnsError(): void
    {
        $conn = $this->makeConnection(1);
        $this->server->onOpen($conn);

        $this->server->onMessage($conn, json_encode(['action' => 'bogus']));

        $payload = $conn->lastSentPayload();
        $this->assertNotNull($payload);
        $this->assertArrayHasKey('error', $payload);
    }

    // ══════════════════════════════════════════════════════════
    //  handleAgentMessage() via onMessage (separate process)
    // ══════════════════════════════════════════════════════════

    #[\PHPUnit\Framework\Attributes\Test]
    #[\PHPUnit\Framework\Attributes\RunInSeparateProcess]
    #[\PHPUnit\Framework\Attributes\PreserveGlobalState(false)]
    public function testAgentEventoLlamadaStoresEventBroadcastsAndSendsAck(): void
    {
        require_once __DIR__ . '/../../Doubles/FakePbx.php';
        require_once __DIR__ . '/../../Doubles/FakeDatabase.php';

        \CallMetrics\Models\Pbx::setFindByAgenteIdResult([
            'id' => 10,
            'tenant_id' => 1,
            'agente_id' => 'agent-1',
            'activo' => true,
        ]);

        $conn = $this->makeConnection(10, new MockHttpRequest([
            'X-Agent-ID' => ['agent-1'],
        ]));
        $this->server->onOpen($conn);

        // Subscribe a client to the tenant channel so we can verify broadcast
        $client = $this->makeConnection(20);
        $this->server->onOpen($client);
        $this->server->onMessage($client, json_encode([
            'action' => 'subscribe',
            'channel' => 'tenant_1',
        ]));

        // Agent sends a call event
        $event = [
            'tipo' => 'evento_llamada',
            'callid' => 'call-123',
            'datos' => ['estado' => 'RINGING', 'caller' => '100'],
        ];
        $this->server->onMessage($conn, json_encode($event));

        // Verify ack was sent to agent
        $ack = $conn->lastSentPayload();
        $this->assertSame('ack', $ack['action']);
        $this->assertSame('evento_llamada', $ack['tipo']);

        // Verify broadcast reached the tenant channel subscriber
        $broadcast = $client->lastSentPayload();
        $this->assertNotNull($broadcast);
        $this->assertSame('call_event', $broadcast['type']);
        $this->assertSame('call_ringing', $broadcast['event']);
        $this->assertSame('call-123', $broadcast['data']['callid']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    #[\PHPUnit\Framework\Attributes\RunInSeparateProcess]
    #[\PHPUnit\Framework\Attributes\PreserveGlobalState(false)]
    public function testAgentHeartbeatUpdatesDbBroadcastsAndSendsAck(): void
    {
        require_once __DIR__ . '/../../Doubles/FakePbx.php';
        require_once __DIR__ . '/../../Doubles/FakeDatabase.php';

        \CallMetrics\Models\Pbx::setFindByAgenteIdResult([
            'id' => 10,
            'tenant_id' => 1,
            'agente_id' => 'agent-1',
            'activo' => true,
        ]);

        $conn = $this->makeConnection(10, new MockHttpRequest([
            'X-Agent-ID' => ['agent-1'],
        ]));
        $this->server->onOpen($conn);

        // Subscribe a client to the PBX health channel
        $client = $this->makeConnection(20);
        $this->server->onOpen($client);
        $this->server->onMessage($client, json_encode([
            'action' => 'subscribe',
            'channel' => 'pbx_10',
        ]));

        $heartbeat = [
            'tipo' => 'heartbeat',
            'estado' => 'ONLINE',
            'tiempo_activo' => '3d 2h',
            'conexion_ami' => 'connected',
        ];
        $this->server->onMessage($conn, json_encode($heartbeat));

        // Verify ack
        $ack = $conn->lastSentPayload();
        $this->assertSame('ack', $ack['action']);
        $this->assertSame('heartbeat', $ack['tipo']);

        // Verify DB was called (UPDATE pbx SET ultimo_heartbeat...)
        $db = \CallMetrics\Core\Database::getInstance();
        $this->assertNotEmpty($db->executeCalls);
        $this->assertStringContainsString('ultimo_heartbeat', $db->executeCalls[0]['sql']);

        // Verify broadcast to PBX health channel
        $broadcast = $client->lastSentPayload();
        $this->assertNotNull($broadcast);
        $this->assertSame('pbx_health', $broadcast['type']);
        $this->assertSame('ONLINE', $broadcast['data']['estado']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    #[\PHPUnit\Framework\Attributes\RunInSeparateProcess]
    #[\PHPUnit\Framework\Attributes\PreserveGlobalState(false)]
    public function testAgentCdrCompletoStoresEventBroadcastsAndSendsAck(): void
    {
        require_once __DIR__ . '/../../Doubles/FakePbx.php';
        require_once __DIR__ . '/../../Doubles/FakeDatabase.php';

        \CallMetrics\Models\Pbx::setFindByAgenteIdResult([
            'id' => 10,
            'tenant_id' => 1,
            'agente_id' => 'agent-1',
            'activo' => true,
        ]);

        $conn = $this->makeConnection(10, new MockHttpRequest([
            'X-Agent-ID' => ['agent-1'],
        ]));
        $this->server->onOpen($conn);

        $client = $this->makeConnection(20);
        $this->server->onOpen($client);
        $this->server->onMessage($client, json_encode([
            'action' => 'subscribe',
            'channel' => 'tenant_1',
        ]));

        $cdr = [
            'tipo' => 'cdr_completo',
            'callid' => 'cdr-456',
            'datos' => ['duration' => 120, 'caller' => '100'],
        ];
        $this->server->onMessage($conn, json_encode($cdr));

        $ack = $conn->lastSentPayload();
        $this->assertSame('ack', $ack['action']);
        $this->assertSame('cdr_completo', $ack['tipo']);

        $broadcast = $client->lastSentPayload();
        $this->assertNotNull($broadcast);
        $this->assertSame('call_event', $broadcast['type']);
        $this->assertSame('call_ended', $broadcast['event']);
        $this->assertSame('cdr-456', $broadcast['data']['callid']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    #[\PHPUnit\Framework\Attributes\RunInSeparateProcess]
    #[\PHPUnit\Framework\Attributes\PreserveGlobalState(false)]
    public function testAgentCallEventWithMissingPbxReturnsAckWithoutBroadcast(): void
    {
        require_once __DIR__ . '/../../Doubles/FakePbx.php';
        require_once __DIR__ . '/../../Doubles/FakeDatabase.php';

        // Register agent for onOpen
        \CallMetrics\Models\Pbx::setFindByAgenteIdResult([
            'id' => 10,
            'tenant_id' => 1,
            'agente_id' => 'agent-1',
            'activo' => true,
        ]);

        $conn = $this->makeConnection(10, new MockHttpRequest([
            'X-Agent-ID' => ['agent-1'],
        ]));
        $this->server->onOpen($conn);

        // Subscribe a client to verify no broadcast happens
        $client = $this->makeConnection(20);
        $this->server->onOpen($client);
        $this->server->onMessage($client, json_encode([
            'action' => 'subscribe',
            'channel' => 'tenant_1',
        ]));

        // Now change the fake to simulate missing Pbx lookup on message
        \CallMetrics\Models\Pbx::setFindByAgenteIdResult(null);

        $this->server->onMessage($conn, json_encode([
            'tipo' => 'evento_llamada',
            'callid' => 'x',
        ]));

        // Ack is still sent (it's unconditional at the end of handleAgentMessage)
        $ack = $conn->lastSentPayload();
        $this->assertNotNull($ack);
        $this->assertSame('ack', $ack['action']);

        // No broadcast reached the client (Pbx was null, processAgentCallEvent returned early)
        // Client only has its own subscribe + authenticated messages, no broadcast
        $lastClientPayload = $client->lastSentPayload();
        $this->assertNotSame('call_event', $lastClientPayload['type'] ?? null);
    }

    // ══════════════════════════════════════════════════════════
    //  broadcastToChannel()
    // ══════════════════════════════════════════════════════════

    #[\PHPUnit\Framework\Attributes\Test]
    public function testBroadcastToChannelReachesAllSubscribers(): void
    {
        $c1 = $this->makeConnection(1);
        $c2 = $this->makeConnection(2);
        $this->server->onOpen($c1);
        $this->server->onOpen($c2);

        $this->server->onMessage($c1, json_encode(['action' => 'subscribe', 'channel' => 'tenant_1']));
        $this->server->onMessage($c2, json_encode(['action' => 'subscribe', 'channel' => 'tenant_1']));

        $this->server->broadcastToChannel('tenant_1', ['type' => 'test', 'msg' => 'hello']);

        $p1 = $c1->lastSentPayload();
        $p2 = $c2->lastSentPayload();

        $this->assertNotNull($p1);
        $this->assertNotNull($p2);
        $this->assertSame('hello', $p1['msg']);
        $this->assertSame('hello', $p2['msg']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testBroadcastToChannelDoesNotReachOtherChannels(): void
    {
        $inChannel = $this->makeConnection(1);
        $notInChannel = $this->makeConnection(2);
        $this->server->onOpen($inChannel);
        $this->server->onOpen($notInChannel);

        $this->server->onMessage($inChannel, json_encode(['action' => 'subscribe', 'channel' => 'tenant_1']));
        $this->server->onMessage($notInChannel, json_encode(['action' => 'subscribe', 'channel' => 'tenant_2']));

        $this->server->broadcastToChannel('tenant_1', ['type' => 'test', 'msg' => 'secret']);

        $payloadIn = $inChannel->lastSentPayload();
        $payloadOut = $notInChannel->lastSentPayload();

        $this->assertSame('secret', $payloadIn['msg']);
        // The other channel subscriber should NOT have received the broadcast.
        // lastSentPayload returns the authenticated message from onOpen, not the broadcast.
        $this->assertNotSame('secret', $payloadOut['msg'] ?? null);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testBroadcastToNonexistentChannelDoesNotError(): void
    {
        $this->server->broadcastToChannel('nonexistent', ['type' => 'test']);
        // Should not throw — just silently does nothing.
        $this->assertTrue(true);
    }

    // ══════════════════════════════════════════════════════════
    //  broadcastCallEvent / broadcastPbxHealth / broadcastQueueUpdate / broadcastDashboard
    // ══════════════════════════════════════════════════════════

    #[\PHPUnit\Framework\Attributes\Test]
    public function testBroadcastCallEventWrapsDataCorrectly(): void
    {
        $client = $this->makeConnection(1);
        $this->server->onOpen($client);
        $this->server->onMessage($client, json_encode(['action' => 'subscribe', 'channel' => 'tenant_5']));

        $this->server->broadcastCallEvent(5, 'call_started', ['callid' => 'x']);

        $payload = $client->lastSentPayload();
        $this->assertSame('call_event', $payload['type']);
        $this->assertSame('call_started', $payload['event']);
        $this->assertSame('x', $payload['data']['callid']);
        $this->assertArrayHasKey('timestamp', $payload);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testBroadcastPbxHealthWrapsDataCorrectly(): void
    {
        $client = $this->makeConnection(1);
        $this->server->onOpen($client);
        $this->server->onMessage($client, json_encode(['action' => 'subscribe', 'channel' => 'pbx_3']));

        $this->server->broadcastPbxHealth(3, ['cpu' => 42]);

        $payload = $client->lastSentPayload();
        $this->assertSame('pbx_health', $payload['type']);
        $this->assertSame(42, $payload['data']['cpu']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testBroadcastQueueUpdateWrapsDataCorrectly(): void
    {
        $client = $this->makeConnection(1);
        $this->server->onOpen($client);
        $this->server->onMessage($client, json_encode(['action' => 'subscribe', 'channel' => 'colas_7']));

        $this->server->broadcastQueueUpdate(7, ['waiting' => 5]);

        $payload = $client->lastSentPayload();
        $this->assertSame('queue_update', $payload['type']);
        $this->assertSame(5, $payload['data']['waiting']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function testBroadcastDashboardWrapsDataCorrectly(): void
    {
        $client = $this->makeConnection(1);
        $this->server->onOpen($client);
        $this->server->onMessage($client, json_encode(['action' => 'subscribe', 'channel' => 'dashboard']));

        $this->server->broadcastDashboard(['calls_today' => 100]);

        $payload = $client->lastSentPayload();
        $this->assertSame('dashboard_update', $payload['type']);
        $this->assertSame(100, $payload['data']['calls_today']);
    }

    // ══════════════════════════════════════════════════════════
    //  onClose()
    // ══════════════════════════════════════════════════════════

    #[\PHPUnit\Framework\Attributes\Test]
    public function testOnCloseDoesNotThrowAndCleansUp(): void
    {
        $conn = $this->makeConnection(1);
        $this->server->onOpen($conn);
        $this->server->onMessage($conn, json_encode(['action' => 'subscribe', 'channel' => 'tenant_1']));

        $countBefore = count($conn->sentMessages);

        // onClose should not throw and should clean up internal tracking
        $this->server->onClose($conn);

        // After close, the connection is still in channels (onClose doesn't remove from channels),
        // but subscriptions tracking is cleaned. Verify no crash and messages sent before close are intact.
        $this->assertSame($countBefore, count($conn->sentMessages));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    #[\PHPUnit\Framework\Attributes\RunInSeparateProcess]
    #[\PHPUnit\Framework\Attributes\PreserveGlobalState(false)]
    public function testOnCloseCleansUpAgentIdAndConnectionType(): void
    {
        require_once __DIR__ . '/../../Doubles/FakePbx.php';

        \CallMetrics\Models\Pbx::setFindByAgenteIdResult([
            'id' => 10,
            'tenant_id' => 1,
            'agente_id' => 'agent-1',
            'activo' => true,
        ]);

        $conn = $this->makeConnection(10, new MockHttpRequest([
            'X-Agent-ID' => ['agent-1'],
        ]));

        $this->server->onOpen($conn);

        // onClose should not throw and should clean up internal agent tracking
        $this->server->onClose($conn);

        // Verify the connection is no longer treated as an agent (connectionType cleaned).
        // Sending a message after close falls through to client path (no agent handling).
        // This does not throw — it's handled as an unknown client action.
        $this->server->onMessage($conn, json_encode(['tipo' => 'evento_llamada', 'callid' => 'x']));

        // The last message should be an error (client path), not an ack (agent path)
        $payload = $conn->lastSentPayload();
        $this->assertNotNull($payload);
        // Client path sends error for unknown action, not ack
        $this->assertNotSame('ack', $payload['action'] ?? null);
    }

    // ══════════════════════════════════════════════════════════
    //  onError()
    // ══════════════════════════════════════════════════════════

    #[\PHPUnit\Framework\Attributes\Test]
    public function testOnErrorClosesConnection(): void
    {
        $conn = $this->makeConnection(1);
        $exception = new \Exception('test error');

        $this->server->onError($conn, $exception);

        $this->assertTrue($conn->closed);
    }
}

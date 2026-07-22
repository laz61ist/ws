<?php

declare(strict_types=1);

namespace WABridge\Tests\Unit;

use WABridge\Auth\MagicLinkService;
use WABridge\Group\GroupRepository;
use WABridge\Mail\LogMailer;
use WABridge\Storage\Database;
use WABridge\Storage\Migrator;
use WABridge\Tests\TestCase;

final class MagicLinkTest extends TestCase
{
    private function setup(): array
    {
        $db = Database::inMemory();
        (new Migrator($db))->migrate(dirname(__DIR__, 2) . '/storage/migrations');
        $logPath = tempnam(sys_get_temp_dir(), 'wabridge_mail_');
        $mailer = new LogMailer($logPath);
        $groups = new GroupRepository($db);
        $service = new MagicLinkService($db, $mailer, $groups, 'http://localhost:8000', 20);
        return [$db, $service, $logPath];
    }

    private function extractToken(string $logPath): string
    {
        $content = (string) file_get_contents($logPath);
        preg_match('/token=([a-f0-9]+)/', $content, $m);
        return $m[1] ?? '';
    }

    public function testTokenNeverStoredAsPlaintext(): void
    {
        [$db, $service, $logPath] = $this->setup();
        $service->requestLink('veli@ornek.com');
        $token = $this->extractToken($logPath);
        $this->assertTrue($token !== '', 'Log dosyasından token okunabilmeli');

        $rows = $db->fetchAll('SELECT token_hash FROM magic_links');
        $this->assertCount(1, $rows);
        $this->assertFalse(
            $rows[0]['token_hash'] === $token,
            'DB\'deki token_hash, plaintext token ile ASLA aynı olmamalı',
        );
        $this->assertSame(hash('sha256', $token), $rows[0]['token_hash'], 'token_hash sha256(token) olmalı');
    }

    public function testValidTokenConsumedSuccessfully(): void
    {
        [, $service, $logPath] = $this->setup();
        $service->requestLink('veli@ornek.com');
        $token = $this->extractToken($logPath);

        $result = $service->consume($token);
        $this->assertTrue($result !== null, 'Geçerli token kabul edilmeli');
        $this->assertTrue($result['userId'] > 0);
        $this->assertTrue($result['groupId'] > 0);
    }

    public function testUsedTokenRejectedOnSecondConsume(): void
    {
        [, $service, $logPath] = $this->setup();
        $service->requestLink('veli@ornek.com');
        $token = $this->extractToken($logPath);

        $first = $service->consume($token);
        $second = $service->consume($token);

        $this->assertTrue($first !== null, 'İlk kullanım kabul edilmeli');
        $this->assertTrue($second === null, 'Kullanılmış token ikinci kez KABUL EDİLMEMELİ');
    }

    public function testExpiredTokenRejected(): void
    {
        [$db, $service, $logPath] = $this->setup();
        $service->requestLink('veli@ornek.com');
        $token = $this->extractToken($logPath);

        // Süreyi geçmişe çek.
        $db->execute("UPDATE magic_links SET expires_at = '2000-01-01T00:00:00Z'");

        $result = $service->consume($token);
        $this->assertTrue($result === null, 'Süresi geçmiş token reddedilmeli');
    }

    public function testInvalidTokenRejected(): void
    {
        [, $service] = $this->setup();
        $result = $service->consume('hic-var-olmayan-token');
        $this->assertTrue($result === null, 'Var olmayan token reddedilmeli');
    }

    public function testSecondLoginReusesExistingUserAndGroup(): void
    {
        [$db, $service, $logPath] = $this->setup();
        $service->requestLink('veli@ornek.com');
        $token1 = $this->extractToken($logPath);
        $result1 = $service->consume($token1);

        file_put_contents($logPath, '');
        $service->requestLink('veli@ornek.com');
        $token2 = $this->extractToken($logPath);
        $result2 = $service->consume($token2);

        $this->assertSame($result1['userId'], $result2['userId'], 'Aynı e-posta aynı kullanıcıya eşlenmeli');
        $this->assertSame($result1['groupId'], $result2['groupId'], 'İkinci girişte YENİ grup oluşturulmamalı');

        $userCount = $db->fetchOne('SELECT COUNT(*) AS n FROM users');
        $this->assertSame(1, (int) $userCount['n'], 'Tek kullanıcı satırı olmalı');
    }

    public function testInvalidEmailRejected(): void
    {
        [, $service] = $this->setup();
        $threw = false;
        try {
            $service->requestLink('gecersiz-eposta');
        } catch (\InvalidArgumentException $e) {
            $threw = true;
        }
        $this->assertTrue($threw, 'Geçersiz e-posta InvalidArgumentException fırlatmalı');
    }
}

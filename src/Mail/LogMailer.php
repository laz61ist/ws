<?php

declare(strict_types=1);

namespace WABridge\Mail;

/**
 * Dev/test sürücüsü: e-postayı GÖNDERMEZ, bir log dosyasına yazar.
 * E2E testler magic-link'i buradan okuyarak gerçek e-posta teslimine
 * bağımlı olmadan tüm akışı doğrulayabilir.
 */
final class LogMailer implements MailerInterface
{
    public function __construct(private readonly string $logPath)
    {
    }

    public function send(string $toEmail, string $subject, string $bodyText): void
    {
        $dir = dirname($this->logPath);
        if (!is_dir($dir) && !@mkdir($dir, 0770, true) && !is_dir($dir)) {
            throw new \RuntimeException('Mail log dizini oluşturulamadı: ' . $dir);
        }

        $entry = sprintf(
            "[%s] TO=%s SUBJECT=%s\n%s\n---\n",
            date('Y-m-d H:i:s'),
            $toEmail,
            $subject,
            $bodyText,
        );
        file_put_contents($this->logPath, $entry, FILE_APPEND | LOCK_EX);
    }
}

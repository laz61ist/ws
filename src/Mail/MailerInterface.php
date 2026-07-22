<?php

declare(strict_types=1);

namespace WABridge\Mail;

/**
 * Sözleşme: HeuristicClassifier/LlmClassifier ile aynı pluggable desen.
 * Prod: CurlMailer (gerçek transactional email API). Dev/test: LogMailer
 * (hiçbir ağ çağrısı yapmaz, dosyaya yazar — magic-link e2e testinde link
 * buradan okunur).
 */
interface MailerInterface
{
    public function send(string $toEmail, string $subject, string $bodyText): void;
}

<?php
/**
 * "আপডেট পেতে যুক্ত হোন" ফর্মের ইমেইল সংরক্ষণ।
 *
 * - ইমেইলগুলো data/subscribers.csv ফাইলে জমা হয় (data/.htaccess দিয়ে ব্রাউজার থেকে সুরক্ষিত)।
 * - NOTIFY_ADMIN = true থাকলে প্রতিটি নতুন সাবস্ক্রিপশনে ADMIN_EMAIL-এ মেইল যায়।
 */

declare(strict_types=1);

const ADMIN_EMAIL  = 'kishoreganjupazilasamity@gmail.com';
const NOTIFY_ADMIN = true;
const DATA_FILE    = __DIR__ . '/data/subscribers.csv';
const RATE_SECONDS = 20; // একই IP থেকে পরপর জমা দেওয়ার ন্যূনতম বিরতি

$wantsJson = isset($_SERVER['HTTP_X_REQUESTED_WITH'])
    || (isset($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json'));

function respond(bool $ok, string $message, int $status, bool $json): void
{
    if ($json) {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        echo json_encode(['ok' => $ok, 'message' => $message], JSON_UNESCAPED_UNICODE);
    } else {
        header('Location: index.html?subscribed=' . ($ok ? '1' : '0') . '#notify', true, 303);
    }
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Location: index.html', true, 302);
    exit;
}

// স্প্যাম বট লুকানো ঘর পূরণ করলে চুপচাপ সফল দেখাই
if (!empty($_POST['website'])) {
    respond(true, 'ধন্যবাদ! ওয়েবসাইট চালু হলে আপনাকে জানানো হবে।', 200, $wantsJson);
}

$email = trim((string)($_POST['email'] ?? ''));
if ($email === '' || strlen($email) > 254 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    respond(false, 'অনুগ্রহ করে একটি সঠিক ইমেইল ঠিকানা লিখুন।', 422, $wantsJson);
}
$email = strtolower($email);

$dir = dirname(DATA_FILE);
if (!is_dir($dir) && !mkdir($dir, 0750, true)) {
    respond(false, 'সার্ভারে সমস্যা হয়েছে। পরে আবার চেষ্টা করুন।', 500, $wantsJson);
}

$fp = fopen(DATA_FILE, 'c+');
if ($fp === false) {
    respond(false, 'সার্ভারে সমস্যা হয়েছে। পরে আবার চেষ্টা করুন।', 500, $wantsJson);
}

flock($fp, LOCK_EX);

$ip     = $_SERVER['REMOTE_ADDR'] ?? '';
$now    = time();
$exists = false;
$lastFromIp = 0;

rewind($fp);
while (($row = fgetcsv($fp, 0, ',', '"', '\\')) !== false) {
    if (!isset($row[0], $row[1])) {
        continue;
    }
    if ($row[0] === $email) {
        $exists = true;
    }
    if (($row[2] ?? '') === $ip) {
        $lastFromIp = max($lastFromIp, (int)strtotime($row[1]));
    }
}

if ($exists) {
    flock($fp, LOCK_UN);
    fclose($fp);
    respond(true, 'এই ইমেইলটি আগেই যুক্ত আছে। ধন্যবাদ!', 200, $wantsJson);
}

if ($ip !== '' && $now - $lastFromIp < RATE_SECONDS) {
    flock($fp, LOCK_UN);
    fclose($fp);
    respond(false, 'একটু পরে আবার চেষ্টা করুন।', 429, $wantsJson);
}

fseek($fp, 0, SEEK_END);
fputcsv($fp, [$email, date('Y-m-d H:i:s', $now), $ip], ',', '"', '\\');
fflush($fp);
flock($fp, LOCK_UN);
fclose($fp);

if (NOTIFY_ADMIN && function_exists('mail')) {
    $host    = preg_replace('/[^a-z0-9.\-]/i', '', $_SERVER['HTTP_HOST'] ?? 'localhost');
    $subject = '=?UTF-8?B?' . base64_encode('নতুন সাবস্ক্রিপশন: ' . $email) . '?=';
    $body    = "ওয়েবসাইটের আপডেট পেতে নতুন একজন যুক্ত হয়েছেন:\n\nইমেইল: {$email}\nসময়: " . date('Y-m-d H:i:s', $now) . "\n";
    $headers = "From: no-reply@{$host}\r\nReply-To: {$email}\r\nContent-Type: text/plain; charset=UTF-8\r\n";
    @mail(ADMIN_EMAIL, $subject, $body, $headers);
}

respond(true, 'ধন্যবাদ! ওয়েবসাইট চালু হলে আপনাকে জানানো হবে।', 200, $wantsJson);

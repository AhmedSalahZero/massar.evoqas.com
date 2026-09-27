<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use App\Notifications\SubscriptionEndingNotification;
use App\Notifications\VerifyEmailCodeNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Mime\Email;
use Tests\TestCase;

// ══════════════════════════════════════════════════════════════════
//  Massar — EmailsTest
//  Location: tests/Feature/EmailsTest.php
//
//  The emails the app sends — verification code, password reset,
//  subscription ending — in English and Arabic:
//    • each has the app's brand mark drawn in HTML and NO image (so
//      nothing to block or break), the right language and direction,
//      an inbox preview line, a plain-text twin, and no raw key or
//      unchosen "a|b" line anywhere
//    • numbers are worded correctly: "1 day", "يومين", "5 أيام",
//      "14 يومًا", "5 دقائق"
//    • they go out at once: with a database queue and no worker
//      running, the reset and reminder emails are still sent
//    • the reset link is on APP_URL, whatever Host the request carried
//    • sign-ins from the same address (an office, a mobile network) do
//      not use up the "forgot password" allowance: each throttled
//      route has its own count
// ══════════════════════════════════════════════════════════════════

class EmailsTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, MailMessage> the three emails, as a person with this language gets them */
    private function mails(string $lang): array
    {
        $user = (new User)->forceFill(['name' => $lang === 'ar' ? 'أحمد حسن' : 'Ahmed Hassan', 'email' => 'ahmed@example.com', 'language' => $lang]);
        $company = (new Company)->forceFill(['name' => 'Al Amal', 'subscription_ends_at' => now()->addDays(14)]);

        return [
            'verify'       => (new VerifyEmailCodeNotification('482913'))->toMail($user),
            'reset'        => (new ResetPasswordNotification('reset-token'))->toMail($user),
            'subscription' => (new SubscriptionEndingNotification($company, 14))->toMail($user),
        ];
    }

    /** The emails the (array) mailer has sent in this test. */
    private function sent(): array
    {
        return app('mailer')->getSymfonyTransport()->messages()->map(fn ($m) => $m->getOriginalMessage())->all();
    }

    public function test_every_email_has_the_brand_mark_no_image_a_preview_line_and_a_text_twin(): void
    {
        foreach (['en' => 'ltr', 'ar' => 'rtl'] as $lang => $dir) {
            foreach ($this->mails($lang) as $name => $mail) {
                $what = "{$name} ({$lang})";
                $html = (string) $mail->render();
                $text = view($mail->view[1], $mail->data())->render();

                $this->assertStringContainsString("<html lang=\"{$lang}\" dir=\"{$dir}\">", $html, $what);
                $this->assertStringContainsString('>M</td>', $html, $what);
                $this->assertStringContainsString('Massar <span', $html, $what);
                $this->assertStringNotContainsString('<img', $html, $what);
                $this->assertMatchesRegularExpression('/<div style="display:none;[^"]*">\s*\S/u', $html, $what);
                $this->assertNotSame('', trim($text), $what);
                foreach ([$html, $text, $mail->subject] as $part) {
                    $this->assertStringNotContainsString('emails.', $part, $what);
                    $this->assertStringNotContainsString('|', $part, $what);
                }
            }
        }

        // The code is in the inbox preview (and in the email).
        $this->assertStringContainsString('Your Massar verification code is 482913.', (string) $this->mails('en')['verify']->render());
    }

    public function test_numbers_are_worded_correctly_in_both_languages(): void
    {
        $subject = fn (int $days, string $lang) => (new SubscriptionEndingNotification((new Company)->forceFill(['name' => 'X']), $days))
            ->toMail((new User)->forceFill(['name' => 'A', 'language' => $lang]))->subject;

        $this->assertStringEndsWith('ends today', $subject(0, 'en'));
        $this->assertStringEndsWith('ends in 1 day', $subject(1, 'en'));
        $this->assertStringEndsWith('ends in 14 days', $subject(14, 'en'));
        $this->assertStringEndsWith('في مسار اليوم', $subject(0, 'ar'));
        $this->assertStringEndsWith('خلال يوم واحد', $subject(1, 'ar'));
        $this->assertStringEndsWith('خلال يومين', $subject(2, 'ar'));
        $this->assertStringEndsWith('خلال 5 أيام', $subject(5, 'ar'));
        $this->assertStringEndsWith('خلال 14 يومًا', $subject(14, 'ar'));

        $this->assertSame('ينتهي هذا الرمز خلال 5 دقائق.', trans_choice('emails.verify_code.expire', 5, [], 'ar'));
        $this->assertSame('ينتهي هذا الرمز خلال 15 دقيقة.', trans_choice('emails.verify_code.expire', 15, [], 'ar'));
        $this->assertSame('This link expires in 60 minutes.', trans_choice('emails.reset_password.expire', 60, [], 'en'));
    }

    public function test_the_reset_email_goes_out_at_once_and_its_link_is_on_app_url(): void
    {
        config(['queue.default' => 'database']);   // as on a server: queued mail would wait for a worker
        User::factory()->employee()->create(['email' => 'reset.me@example.com', 'language' => 'ar']);

        // The request says it is for another site: the link must not follow it.
        $this->post('http://evil.example/forgot-password', ['email' => 'reset.me@example.com'])->assertSessionHasNoErrors();

        $this->assertSame(0, DB::table('jobs')->count());
        [$email] = $this->sent();
        /** @var Email $email */
        $this->assertSame(__('emails.reset_password.subject', [], 'ar'), $email->getSubject());
        $this->assertSame('reset.me@example.com', $email->getTo()[0]->getAddress());
        $this->assertNotEmpty($email->getTextBody());
        $this->assertMatchesRegularExpression('/href="'.preg_quote(rtrim(config('app.url'), '/'), '/').'\/reset-password\//', $email->getHtmlBody());
        $this->assertStringNotContainsString('evil.example', $email->getHtmlBody().$email->getTextBody());
    }

    public function test_sign_ins_from_the_same_address_do_not_use_up_the_password_reset(): void
    {
        User::factory()->employee()->create(['email' => 'reset.me@example.com']);
        // Many people behind one address, signing in (some mistyping) in the same minute.
        for ($i = 0; $i < 6; $i++) {
            $this->post('/login', ['email' => "someone{$i}@example.com", 'password' => 'wrong-password']);
            $this->post('/sign-in', ['email' => "seeker{$i}@example.com", 'password' => 'wrong-password']);
        }

        $this->post('/forgot-password', ['email' => 'reset.me@example.com'])->assertSessionHasNoErrors();
        $this->assertCount(1, $this->sent());
    }

    public function test_the_subscription_reminder_goes_out_at_once(): void
    {
        config(['queue.default' => 'database']);
        $company = Company::factory()->create(['subscription_ends_at' => now()->addDays(2)->endOfDay()]);
        User::factory()->companyAdmin($company)->create(['email' => 'admin@example.com', 'language' => 'ar']);

        $this->artisan('subscriptions:notify-expiring')->assertSuccessful();

        $this->assertSame(0, DB::table('jobs')->count());
        [$email] = $this->sent();
        $this->assertSame('ينتهي اشتراككم في مسار خلال يومين', $email->getSubject());
        $this->assertStringContainsString('خلال يومين.', $email->getTextBody());
    }
}

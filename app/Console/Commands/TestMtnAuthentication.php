<?php
namespace App\Console\Commands;

use App\Models\MessagingSetting;
use App\Services\Messaging\MtnClient;
use App\Services\Messaging\RejectedSend;
use App\Services\Messaging\RetryableSend;
use Illuminate\Console\Command;

class TestMtnAuthentication extends Command
{
    protected $signature = 'messaging:test-mtn-auth';
    protected $description = 'Verify stored MTN login credentials without sending any messages';

    public function handle(MtnClient $client): int
    {
        try {
            $client->authenticate(MessagingSetting::current());
        } catch (RejectedSend | RetryableSend $error) {
            $this->error($error->getMessage());
            return 1;
        } catch (\Throwable $error) {
            $this->error('Unable to verify MTN authentication. Check configuration and connectivity.');
            return 1;
        }
        $this->info('MTN authentication succeeded. No SMS sent; sending settings unchanged.');
        return 0;
    }
}

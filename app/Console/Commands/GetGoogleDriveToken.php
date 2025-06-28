<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Google\Client;
use Google\Service\Drive;

class GetGoogleDriveToken extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'google:get-refresh-token';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate a new Google Drive refresh token.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting Google Drive token generation process...');

        try {
            $client = new Client();
            $client->setClientId(config('services.google_drive.client_id'));
            $client->setClientSecret(config('services.google_drive.client_secret'));
            $client->setRedirectUri('http://localhost');
            $client->setAccessType('offline');
            $client->setApprovalPrompt('force');
            $client->addScope(Drive::DRIVE);

            // Generate the authentication URL
            $authUrl = $client->createAuthUrl();
            $this->info('Please open the following URL in your browser, authorize the application, and copy the authorization code:');
            $this->line($authUrl);

            // Ask for the authorization code
            $this->warn('After authorizing, your browser will redirect to a non-existent page. Please copy the ENTIRE URL of that error page and paste it here.');
            $redirectUrl = $this->ask('Paste the full redirect URL here');

            if (empty($redirectUrl)) {
                $this->error('Redirect URL is required.');
                return 1;
            }

            // Extract the authorization code from the URL
            parse_str(parse_url($redirectUrl, PHP_URL_QUERY), $query);
            $authCode = $query['code'] ?? null;

            if (empty($authCode)) {
                $this->error('Could not find authorization code in the provided URL.');
                return 1;
            }

            // Exchange authorization code for an access token and refresh token
            $accessToken = $client->fetchAccessTokenWithAuthCode($authCode);

            if (isset($accessToken['error'])) {
                $this->error('Failed to retrieve access token: ' . $accessToken['error_description']);
                return 1;
            }
            
            if (!isset($accessToken['refresh_token'])) {
                $this->error('No refresh token was returned. This can happen if you have already authorized this app before.');
                $this->warn('Please go to your Google Account permissions page (https://myaccount.google.com/permissions), remove access for this app, and try again.');
                return 1;
            }

            $refreshToken = $accessToken['refresh_token'];

            $this->info('---');
            $this->info('✅ New Refresh Token generated successfully!');
            $this->warn('Copy the token below and paste it into your .env file for the GOOGLE_DRIVE_REFRESH_TOKEN variable.');
            $this->line('---');
            $this->line($refreshToken);
            $this->line('---');
            $this->info('After updating your .env file, remember to run "php artisan config:clear" to apply the changes.');

            return 0;

        } catch (\Exception $e) {
            $this->error('An error occurred: ' . $e->getMessage());
            return 1;
        }
    }
}

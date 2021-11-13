<?php

namespace Jakyeru\Larascord\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Validator;
use \GuzzleHttp;

class LarascordInstall extends Command
{
    protected $name = 'larascord:install';

    protected $description = 'Use this command to install Larascord.';

    protected $availableScopes = [
        'activities.read',
        'activities.write',
        'applications.builds.read',
        'applications.builds.upload',
        'applications.commands',
        'applications.commands.update',
        'applications.entitlements',
        'applications.store.update',
        'bot',
        'connections',
        'email',
        'gdm.join',
        'guilds.join',
        'identify',
        'messages.read',
        'relationships.read',
        'rpc',
        'rpc.activities.write',
        'rpc.notifications.read',
        'rpc.voice.read',
        'rpc.voice.write',
        'webhook.incoming'
    ];

    protected string $baseUrl = 'https://discord.com/api';

    private string|null $clientId;
    private string|null $clientSecret;
    private string|null $token;
    private string|null $redirectUri;
    private string|null $scopes;

    /**
     * Handle the command.
     * @throws \Exception
     */
    public function handle()
    {
        // Getting the user's input
        $this->clientId = $this->ask('What is your Discord application\'s client id?');
        $this->clientSecret = $this->ask('What is your Discord application\'s client secret?');
        $this->token = $this->ask('What is your Discord bot\'s token?');
        $this->redirectUri = $this->ask('What is your Discord application\'s redirect uri?', 'http://localhost:8000/larascord/callback');
        $this->scopes = $this->ask('What scopes do you want to use? (separated by a ampersand (&))', 'identify&email');

        // Validating the user's input
        try {
            $this->validateInput();
        } catch (\Exception $e) {
            $this->error($e->getMessage());
            return;
        }

        // Appending the secrets to the .env file
        $this->appendToEnvFile();

        // Creating the user migration files
        $this->createUserMigrationFiles();

        // Create the model files
        $this->createModelFiles();

        // Create the controller file
        $this->createControllerFiles();

        // Create the route files
        $this->createRouteFiles();

        // Create the view files
        $this->createViewFiles();

        // Asking the user to validate the provided data through Discord API
        if ($this->confirm('Do you want to validate the provided data through Discord API?', false)) {
            try {
                $this->validateDiscordApi();
            } catch (\Exception $e) {
                $this->error($e->getMessage());
                return;
            } catch (GuzzleHttp\Exception\GuzzleException $e) {
                $this->error($e->getMessage());
                return;
            }
        } else {
            $this->comment('You can validate the provided data through Discord API later by running the command:');
            $this->comment('php artisan larascord:validate');
        }

        // Asking the user to migrate the database
        if ($this->confirm('Do you want to run the migrations?', true)) {
            $this->call('migrate:fresh');
        } else {
            $this->comment('You can run the migrations later by running the command:');
            $this->comment('php artisan migrate');
        }

        $this->info('Larascord has been successfully installed!');

//        $this->comment('Please execute the "npm install && npm run dev" command to build your assets.');
    }

    /**
     * Validate the user's input.
     * @throws \Exception
     */
    protected function validateInput()
    {
        $rules = [
            'clientId' => ['required', 'numeric'],
            'clientSecret' => ['required', 'string'],
            'token' => ['required', 'string'],
            'redirectUri' => ['required', 'url'],
            'scopes' => ['required', 'array'],
        ];

        $validator = Validator::make([
            'clientId' => $this->clientId,
            'clientSecret' => $this->clientSecret,
            'token' => $this->token,
            'redirectUri' => $this->redirectUri,
            'scopes' => explode('&', $this->scopes),
        ], $rules);

        $validator->validate();

        // make sure scopes exists in the available scopes
        foreach ($validator->validated()['scopes'] as $scope) {
            if (!in_array($scope, $this->availableScopes)) {
                throw new \Exception('The scope '.$scope.' is not available. Available scopes: ' . implode(', ', $this->availableScopes) . '.');
            }
        }
    }

    /**
     * Append the secrets to the .env file.
     *
     * @return void
     */
    protected function appendToEnvFile()
    {

        (new Filesystem())->append('.env',PHP_EOL);

        (new Filesystem())->append('.env',PHP_EOL);
        (new Filesystem())->append('.env','DISCORD_CLIENT_ID='.$this->clientId);

        (new Filesystem())->append('.env',PHP_EOL);
        (new Filesystem())->append('.env','DISCORD_CLIENT_SECRET='.$this->clientSecret);

        (new Filesystem())->append('.env',PHP_EOL);
        (new Filesystem())->append('.env','DISCORD_GRANT_TYPE=authorization_code');

        (new Filesystem())->append('.env',PHP_EOL);
        (new Filesystem())->append('.env','DISCORD_BOT_TOKEN='.$this->token);

        (new Filesystem())->append('.env',PHP_EOL);
        (new Filesystem())->append('.env','DISCORD_REDIRECT_URI='.$this->redirectUri);

        (new Filesystem())->append('.env',PHP_EOL);
        (new Filesystem())->append('.env','DISCORD_SCOPE='.$this->scopes);
    }

    /**
     * Create the user migration files.
     *
     * @return void
     */
    public function createUserMigrationFiles()
    {
        (new Filesystem())->ensureDirectoryExists(database_path('migrations'));
        (new Filesystem())->copy(__DIR__ . '/../../database/migrations/2014_10_12_000000_create_users_table.php', database_path('migrations/2014_10_12_000000_create_users_table.php'));
    }

    /**
     * Create the user model files.
     *
     * @return void
     */
    public function createModelFiles()
    {
        (new Filesystem())->ensureDirectoryExists(app_path('Models'));
        (new Filesystem())->copy(__DIR__ . '/../../Models/User.php', app_path('Models/User.php'));
    }

    /**
     * Create the controller files.
     *
     * @return void
     */
    public function createControllerFiles()
    {
        (new Filesystem())->ensureDirectoryExists(app_path('Http/Controllers'));
        (new Filesystem())->copy(__DIR__ . '/../../Http/Controllers/DiscordController.php', app_path('Http/Controllers/DiscordController.php'));
    }

    /**
     * Create the route files.
     *
     * @return void
     */
    public function createRouteFiles() {
        (new Filesystem())->ensureDirectoryExists(base_path('routes'));
        (new Filesystem())->copyDirectory(__DIR__ . '/../../routes', base_path('routes'));
    }

    /**
     * Create the view files.
     *
     * @return void
     */
    public function createViewFiles()
    {
        (new Filesystem())->ensureDirectoryExists(resource_path('views'));
        (new Filesystem())->copyDirectory(__DIR__ . '/../../resources/views', resource_path('views'));
    }

    /**
     * Validate the provided data through Discord API.
     * @throws \Exception|GuzzleHttp\Exception\GuzzleException
     *
     * @return void
     */
    protected function validateDiscordApi() {

        $this->info('Validating the data through Discord API...');

        $client = new GuzzleHttp\Client();

        try {
            $response = $client->get($this->baseUrl.'/users/@me', [
                'headers' => [
                    'Authorization' => 'Bot '.$this->token,
                ],
            ]);

            $response = json_decode($response->getBody()->getContents());

            if ($response->id !== $this->clientId) {
                throw new \Exception('The provided client belongs to another application.');
            }

            $this->info('The provided data is valid!');
        } catch (\GuzzleHttp\Exception\ClientException $e) {
            $this->error('The provided data is not valid.');
            return;
        }
    }
}
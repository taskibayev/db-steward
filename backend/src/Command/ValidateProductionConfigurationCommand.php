<?php

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AsCommand(name: 'app:production:validate', description: 'Validate required production secrets without displaying them.')]
final class ValidateProductionConfigurationCommand extends Command
{
    public function __construct(
        #[Autowire('%kernel.environment%')] private readonly string $environment,
        #[Autowire(env: 'APP_SECRET')] private readonly string $appSecret,
        #[Autowire(env: 'DATABASE_URL')] private readonly string $databaseUrl,
        #[Autowire(env: 'MESSENGER_TRANSPORT_DSN')] private readonly string $messengerDsn,
        #[Autowire(env: 'GOOGLE_OAUTH_CLIENT_ID')] private readonly string $googleClientId,
        #[Autowire(env: 'GOOGLE_OAUTH_CLIENT_SECRET')] private readonly string $googleClientSecret,
        #[Autowire(env: 'CLIENT_CREDENTIALS_KEY')] private readonly string $credentialsKey,
        #[Autowire(env: 'CENTRIFUGO_API_KEY')] private readonly string $centrifugoApiKey,
        #[Autowire(env: 'CENTRIFUGO_TOKEN_SECRET')] private readonly string $centrifugoTokenSecret,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $errors = [];
        if ('prod' !== $this->environment) {
            $errors[] = 'APP_ENV must be prod.';
        }
        foreach ($this->secrets() as $name => $value) {
            if (strlen($value) < 32 || str_contains(strtolower($value), 'replace') || str_contains(strtolower($value), 'local-development')) {
                $errors[] = $name.' must be a non-default secret of at least 32 characters.';
            }
        }
        if ('' === trim($this->googleClientId) || str_contains(strtolower($this->googleClientId), 'replace')) {
            $errors[] = 'GOOGLE_OAUTH_CLIENT_ID must be configured.';
        }
        if (!str_starts_with($this->databaseUrl, 'mysql://')) {
            $errors[] = 'DATABASE_URL must use MySQL.';
        }
        if (!str_starts_with($this->messengerDsn, 'amqp://') && !str_starts_with($this->messengerDsn, 'amqps://')) {
            $errors[] = 'MESSENGER_TRANSPORT_DSN must use AMQP.';
        }
        $decodedKey = base64_decode($this->credentialsKey, true);
        if (false === $decodedKey || 32 !== strlen($decodedKey)) {
            $errors[] = 'CLIENT_CREDENTIALS_KEY must be base64 for exactly 32 bytes.';
        }
        if ([] !== $errors) {
            foreach ($errors as $error) {
                $output->writeln('<error>'.$error.'</error>');
            }

            return Command::FAILURE;
        }

        $output->writeln('<info>Production configuration is valid.</info>');

        return Command::SUCCESS;
    }

    /** @return array<string, string> */
    private function secrets(): array
    {
        return [
            'APP_SECRET' => $this->appSecret,
            'GOOGLE_OAUTH_CLIENT_SECRET' => $this->googleClientSecret,
            'CENTRIFUGO_API_KEY' => $this->centrifugoApiKey,
            'CENTRIFUGO_TOKEN_SECRET' => $this->centrifugoTokenSecret,
        ];
    }
}

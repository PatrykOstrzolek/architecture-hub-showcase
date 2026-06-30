<?php

declare(strict_types=1);

namespace App\Command;

use Doctrine\ORM\EntityManagerInterface;
use Sulu\Bundle\SecurityBundle\Entity\User;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactoryInterface;

#[AsCommand(
    name: 'app:user:set-password',
    description: 'Set the password for an existing Sulu user.',
)]
class SetUserPasswordCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly PasswordHasherFactoryInterface $passwordHasherFactory,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('username', InputArgument::REQUIRED, 'Username of the Sulu user');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $username = (string) $input->getArgument('username');

        /** @var User|null $user */
        $user = $this->entityManager->getRepository(User::class)->findOneBy(['username' => $username]);

        if (null === $user) {
            $io->error(sprintf('User "%s" not found.', $username));

            return Command::FAILURE;
        }

        /** @var QuestionHelper $helper */
        $helper = $this->getHelper('question');

        $question = new Question('New password: ');
        $question->setHidden(true);
        $question->setHiddenFallback(false);

        $password = $helper->ask($input, $output, $question);

        if (null === $password || '' === $password) {
            $io->error('Password cannot be empty.');

            return Command::FAILURE;
        }

        \assert(\is_string($password));

        $confirm = new Question('Confirm password: ');
        $confirm->setHidden(true);
        $confirm->setHiddenFallback(false);

        $confirmation = $helper->ask($input, $output, $confirm);

        if ($password !== $confirmation) {
            $io->error('Passwords do not match.');

            return Command::FAILURE;
        }

        $hash = $this->passwordHasherFactory->getPasswordHasher($user)->hash($password);
        $user->setPassword($hash);

        $this->entityManager->flush();

        $io->success(sprintf('Password updated for user "%s".', $username));

        return Command::SUCCESS;
    }
}

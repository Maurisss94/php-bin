<?php

declare(strict_types=1);

namespace Articstudio\PhpBin\Commands\Git\Subtree;

use Articstudio\PhpBin\Commands\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class Add extends Command
{

    use \Articstudio\PhpBin\Concerns\HasWriteComposer;
    use Concerns\HasSubtreesConfig;
    use Concerns\HasSubtreeBehaviour;


    protected $io;
    /**
     * Command name
     *
     * @var string
     */
    protected static $defaultName = 'git:subtree:add';

    protected function configure()
    {
        $this->addArgument('package_name', InputArgument::OPTIONAL, 'Nom del package:');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $packages           = $this->getSubtrees();
        $this->io           = $this->getStyle($output, $input);
        $input_package_name = $input->getArgument('package_name') ?? null;
        $input_repository   = null;
        $input_store        = null;
        $isMenu             = false;
        if ($input_package_name === null) {
            $menu_options       = array_keys($packages) + [
                'new' => 'New package',
            ];
            $user_choice        = $this->selectPackageMenu("Subtree packages", $menu_options);
            $input_package_name = is_int($user_choice) ? array_keys($packages)[$user_choice] : $user_choice;
            $isMenu             = true;
        }

        if ($input_package_name === 'back') {
            return $this->callCommandByName('git', [], $output);
        }

        if ($input_package_name === null) {
            return $this->exit($output, 1);
        }

        $input_repository = $packages[$input_package_name] ?? null;

        if ($input_package_name === 'new') {
            [$input_package_name, $input_repository, $input_store] = $this->showNewPackageQuestions();
        }

        if ($input_store) {
            $this->addSubtreeToComposer([$input_package_name => $input_repository]);
        }

        if (! $isMenu && ! $this->checkPackageInComposer($input_package_name)) {
            throw new \Articstudio\PhpBin\PhpBinException('Package ' . $input_package_name . ' configuration not found');
        }

        $txt = $this->addGitSubtree($input_package_name, $input_repository);
        $this->io->writeln($txt);

        return $this->exit($output, 0);
    }


    protected function showNewPackageQuestions(?bool $force_store = null)
    {
        $package_name   = $this->io->ask('Please enter the name of the package: ');
        $git_repository = $this->io->ask('Please enter the URL of the git repository: ');
        $store          = $force_store;
        if ($store === null) {
            $store = $this->io->confirm('Store this package/repository to the Composer config? ');
        }

        return [$package_name, $git_repository, $store];
    }

    protected function addGitSubtree($package_name, $git_repository)
    {

        $local_changes = $this->getLocalChanges();
        if ($local_changes) {
            $ask_commit     = "You need to commit changes before add a subtree. ";
            $commit_message = $this->io->ask($ask_commit . " \n Commit message: ", "wip");
            $commited       = $commit_message ? $this->commitChanges(
                $commit_message,
                '-a'
            ) : false;
            if (! $commited) {
                throw new \Articstudio\PhpBin\PhpBinException(
                    'Error adding the package '
                    . $package_name
                    . ' subtree from '
                    . $git_repository
                    . ' because have local changes to commit.'
                );
            }
        }

        if ($this->subtreeExists($package_name)) {
            throw new \Articstudio\PhpBin\PhpBinException(
                'Error adding the package '
                . $package_name
                . ' subtree from '
                . $git_repository
                . '. It already exists'
            );
        }

        $cmd_remote_add  = 'git remote add ' . $package_name . ' ' . $git_repository;
        $cmd_add_subtree = 'git subtree add --prefix=' . $package_name . '/ ' . $git_repository . ' master';

        $this->callShell($cmd_remote_add, false);
        [$exit_code, $output, $exit_code_txt, $error] = $this->callShell($cmd_add_subtree, false);

        if ($exit_code === 1) {
            throw new \Articstudio\PhpBin\PhpBinException(
                'Error adding the package '
                . $package_name
                . ' subtree from '
                . $git_repository
                . ''
            );
        }

        $error_msg = $exit_code_txt . "\n" . $error;

        return $output !== '' ? $output : $error_msg;
    }
}

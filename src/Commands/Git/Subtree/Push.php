<?php

declare(strict_types=1);

namespace Articstudio\PhpBin\Commands\Git\Subtree;

use Articstudio\PhpBin\Commands\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class Push extends Command
{

    use Concerns\HasSubtreesConfig;
    use Concerns\HasSubtreeBehaviour;

    protected $io;

    /**
     * Command name
     *
     * @var string
     */
    protected static $defaultName = 'git:subtree:push';

    protected function configure()
    {
        $this->addArgument('package_name', InputArgument::IS_ARRAY, 'Nom del package:');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $repositories = $this->getSubtrees();
        $this->io     = $this->getStyle($output, $input);

        $package_names = $input->getArgument('package_name') ?? [];

        if (count($package_names) < 1) {
            $menu_options = array_keys($repositories) + [
                'all' => 'All subtrees',
            ];
            $option       = $this->selectPackageMenu('Push subtrees', $menu_options);

            if ($option === 'back') {
                return $this->callCommandByName('git', [], $output);
            }

            if ($option === null) {
                return 1;
            }

            $package_names = is_int($option) ? [array_keys($repositories)[$option]] :
                ($option === 'all' ? array_keys($repositories) : []);
        }

        $result = $this->pushSubtree($repositories, $package_names);
        $this->showResume($result, $this->io);

        return $this->exit($output, 0);
    }

    private function pushSubtree(array $repositories, $package_names)
    {
        $result = [
            'skipped'   => [],
            'done'      => [],
            'error'     => [],
            'not_found' => [],
        ];

        foreach ($repositories as $repo_package => $repo_url) {
            if (count($package_names) > 0 && ! in_array($repo_package, $package_names, true)) {
                $result['skipped'][] = $repo_package;
                continue;
            }
            if (! $this->subtreeExists($repo_package)) {
                $result['not_found'][] = $repo_package;
                unset($repositories[$repo_package]);
                continue;
            }
            $cmd = 'git subtree push --prefix=' . $repo_package . '/ ' . $repo_url . ' master';
            [$exit_code, , , ] = $this->callShell($cmd, false);
            $key            = $exit_code === 0 ? 'done' : 'error';
            $result[$key][] = $repo_package;
        }

        return $result;
    }
}

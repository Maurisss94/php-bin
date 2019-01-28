<?php

namespace Articstudio\PhpBin\Commands\Git\Subtree;

use Articstudio\PhpBin\Commands\AbstractCommand as PhpBinCommand;
use Articstudio\PhpBin\PhpBinException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;

class Remove extends PhpBinCommand
{

    use Concerns\HasSubtreesConfig;
    use \Articstudio\PhpBin\Concerns\HasWriteComposer;
    use Concerns\HasSelectBehaviour;

    /**
     * Command name
     *
     * @var string
     */
    protected static $defaultName = 'git:subtree:remove';

    protected function configure()
    {
        $this->addArgument('package_name', InputArgument::IS_ARRAY, 'Nom del package:');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {

        $repositories        = $this->getSubtrees();
        $input_store         = null;
        $remove_package_name = null;
        $package_names       = $input->getArgument('package_name') ?: array();

        if (empty($package_names)) {
            $menu_options = array_keys($repositories) + [
                    'all' => 'All subtrees'
                ];
            $option       = $this->showMenu('Remove Subtrees', $menu_options);
            if ($option === null) {
                return 1;
            }

            $package_names = is_int($option) ? array(array_keys($repositories)[$option]) : ($option === 'all' ? $repositories : array());
        }

        $subtree_exists = $this->subtreeExists($package_names);
        if ( ! empty($subtree_exists)) {
            $package_names = array_diff($subtree_exists, $package_names);
        }


        $result = $this->removeDirAndRemoteSubtree($repositories, $package_names);

        $input_store = $this->showNewPackageQuestions();

        if ($input_store) {
            $this->removeSubtreeToComposer($remove_package_name);
        }

        $this->showResume($result);
    }

    protected function showNewPackageQuestions(?bool $force_store = null)
    {
        if ($force_store === null) {
            $force_store = $this->confirmation('Remove this package/repository of the Composer config? ');
        }

        return $force_store;
    }

    private function subtreeExists(array $package_names)
    {

        $result = array();
        foreach ($package_names as $p_name) {
            $cmd = 'find -type d -name " ' . $p_name . '"';
            list($exit_code, $output, $exit_code_txt, $error) = $this->callShell($cmd, false);
            $exit_code === 0 ? array_push($result, $p_name) : null;
        }

        return $result;

    }

    private function removeDirAndRemoteSubtree(array $repositories, $package_names)
    {

        $result = array(
            'skipped'   => [],
            'done'      => [],
            'error'     => [],
            'not_found' => [],
        );

        foreach ($repositories as $repo_package => $repo_url) {
            if (empty($package_names) || in_array($repo_package, $package_names)) {
                $cmd = 'git remote rm ' . $repo_package;
                $this->callShell($cmd, false);
                $cmd = 'git rm -r ' . $repo_package . '/';
                $this->callShell($cmd, false);
                $cmd = 'rm -r ' . $repo_package . '/';
                $this->callShell($cmd, false);
                $cmd = 'git commit -m "Removing ' . $repo_package . ' subtree"';
                list($exit_code, $output, $exit_code_txt, $error) = $this->callShell($cmd, false);
                $key            = $exit_code === 0 ? 'done' : 'error';
                $result[$key][] = $repo_package;
                continue;
            }
            $result['skipped'][] = $repo_package;
        }

        foreach ($package_names as $package_name) {
            if ( ! isset($repositories[$package_name])) {
                $result['not_found'][] = $package_name;
            }
        }

        return $result;
    }
}

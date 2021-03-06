<?php

declare(strict_types=1);

namespace Articstudio\PhpBin\Commands\Php;

use Articstudio\PhpBin\Commands\MenuCommand as PhpBinMenuCommand;

class Menu extends PhpBinMenuCommand
{

    /**
     * Menu title
     *
     * @var string
     */
    protected $menuTitle = 'PHP';

    /**
     * Menu options
     *
     * @var array
     */
    protected $menuOptions = [
        'php:lint' => 'Lint',
        'php:metrics' => 'Metrics',
        'php:style' => 'Style',
        'php:style:fix' => 'Fix Style',
        'php:insights' => 'Insights',
        'php:test' => 'Testing',
    ];

    /**
     * Back command name
     *
     * @var string
     */
    protected $backOption = 'phpbin';

    /**
     * Command name
     *
     * @var string
     */
    protected static $defaultName = 'php';

    /**
     * Configure command
     */
    protected function configure()
    {
        $this->setAliases([
            'php:menu',
        ]);
    }
}

<?php

/**
 * @copyright Copyright (C) eZ Systems AS. All rights reserved.
 * @license   For full copyright and license information view LICENSE file distributed with this source code.
 */

declare(strict_types=1);

namespace eZ\Launchpad\Core;

use eZ\Launchpad\Configuration\Project as ProjectConfiguration;
use eZ\Launchpad\Core\Client\Docker as DockerClient;
use Novactive\Collection\Collection;
use RuntimeException;
use Symfony\Component\Process\Process;

class TaskExecutor
{
    /**
     * @var DockerClient
     */
    protected $dockerClient;

    /**
     * @var ProjectConfiguration
     */
    protected $projectConfiguration;

    /**
     * @var Collection
     */
    protected $recipes;

    public function __construct(DockerClient $dockerClient, ProjectConfiguration $configuration, Collection $recipes)
    {
        $this->dockerClient = $dockerClient;
        $this->projectConfiguration = $configuration;
        $this->recipes = $recipes;
    }

    protected function checkRecipeAvailability(string $recipe): void
    {
        if (!$this->recipes->contains($recipe)) {
            throw new RuntimeException("Recipe {$recipe} is not available.");
        }
    }

    /**
     * @return Process[]
     */
    public function composerInstall(): array
    {
        $recipe = 'composer_install';
        $this->checkRecipeAvailability($recipe);

        $processes = [];
        // composer install
        $processes[] = $this->execute("{$recipe}.bash");

        // Composer Configuration
        $httpBasics = $this->projectConfiguration->get('composer.http_basic');
        if (\is_array($httpBasics)) {
            foreach ($httpBasics as $auth) {
                if (!isset($auth['host'], $auth['login'], $auth['password'])) {
                    continue;
                }
                $processes[] = $this->globalExecute(
                    '/usr/local/bin/composer config --global'.
                    " http-basic.{$auth['host']} {$auth['login']} {$auth['password']}"
                );
            }
        }

        $tokens = $this->projectConfiguration->get('composer.token');
        if (\is_array($tokens)) {
            foreach ($tokens as $auth) {
                if (!isset($auth['host'], $auth['value'])) {
                    continue;
                }
                $processes[] = $this->globalExecute(
                    '/usr/local/bin/composer config --global'." github-oauth.{$auth['host']} {$auth['value']}"
                );
            }
        }

        return $processes;
    }

    public function eZInstall(string $version, string $repository, string $initialData): Process
    {
        $recipe = 'ez_install';
        $this->checkRecipeAvailability($recipe);

        return $this->execute("{$recipe}.bash {$repository} {$version} {$initialData}");
    }

    public function eZInstallSolr(): Process
    {
        $recipe = 'ez_install_solr';
        $this->checkRecipeAvailability($recipe);

        return $this->execute(
            "{$recipe}.bash {$this->projectConfiguration->get('provisioning.folder_name')} COMPOSER_INSTALL"
        );
    }

    public function indexSolr(): Process
    {
        $recipe = 'ez_install_solr';
        $this->checkRecipeAvailability($recipe);

        return $this->execute(
            "{$recipe}.bash {$this->projectConfiguration->get('provisioning.folder_name')} INDEX"
        );
    }

    public function createCore(): Process
    {
        $recipe = 'ez_install_solr';
        $this->checkRecipeAvailability($recipe);

        $provisioningFolder = $this->projectConfiguration->get('provisioning.folder_name');

        return $this->execute(
            "{$recipe}.bash {$provisioningFolder} CREATE_CORE",
            'solr',
            'solr'
        );
    }

    public function eZCreate(): Process
    {
        $recipe = 'ez_create';
        $this->checkRecipeAvailability($recipe);

        return $this->execute("{$recipe}.bash");
    }

    public function dumpData(): Process
    {
        $recipe = 'create_dump';
        $this->checkRecipeAvailability($recipe);

        return $this->execute("{$recipe}.bash");
    }

    public function importData(): Process
    {
        $recipe = 'import_dump';
        $this->checkRecipeAvailability($recipe);

        return $this->execute("{$recipe}.bash");
    }

    public function runSymfomyCommand(string $arguments): Process
    {
        $consolePath = $this->dockerClient->isEzPlatform2x() ? 'bin/console' : 'app/console';

        return $this->execute("ezplatform/{$consolePath} {$arguments}");
    }

    public function runComposerCommand(string $arguments, string $symfonyEnv = 'dev'): Process
    {
        return $this->globalExecute(
            "/usr/local/bin/composer --working-dir={$this->dockerClient->getProjectPathContainer()}/ezplatform ".
            $arguments, $symfonyEnv
        );
    }

    protected function execute(string $command, string $symfonyEnv = 'dev', string $user = 'www-data', string $service = 'engine')
    {
        $command = $this->dockerClient->getProjectPathContainer().'/'.$command;

        return $this->globalExecute($command, $symfonyEnv, $user, $service);
    }

    protected function globalExecute(string $command, string $symfonyEnv = 'dev', string $user = 'www-data', string $service = 'engine')
    {
        return $this->dockerClient->exec($command, [
            '--user', $user,
            '--env', "SYMFONY_ENV={$symfonyEnv}"
        ], $service);
    }
}

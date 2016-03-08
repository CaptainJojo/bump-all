<?php

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class BumpCommand extends Command
{
    protected function configure()
    {
        $this->setName('composer:update');
        $this->setDescription('Composer update new dependcy');
        $this->addArgument('token', InputArgument::REQUIRED, 'token github');
        $this->addArgument('orga', InputArgument::REQUIRED, 'name of organisation');
        $this->addArgument('package', InputArgument::REQUIRED, 'name of package');
        $this->addArgument('version', InputArgument::REQUIRED, 'new version');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $package = $input->getArgument('package');
        $version = $input->getArgument('version');
        $orga = $input->getArgument('orga');

        $client = new \Github\Client(
            new \Github\HttpClient\CachedHttpClient(array('cache_dir' => 'app/cache/github-api-cache'))
        );

        $client->authenticate($input->getArgument('token'), \Github\Client::AUTH_HTTP_TOKEN);

        $organizationApi = $client->api('organization');

        $paginator = new \Github\ResultPager($client);
        $parameters = array($orga, 'all');
        $result = $paginator->fetchAll($organizationApi, 'repositories', $parameters);

        do {
            foreach ($result as $repos) {
                try {
                    $content = $client->api('repo')->contents()->show($orga, $repos['name'], 'composer.json', 'master');
                } catch (\Exception $e) {
                    continue;
                }

                try {
                    $composerLock = $client->api('repo')->contents()->show($orga, $repos['name'], 'composer.lock', 'master');
                } catch (\Exception $e) {
                    $composerLock = null;
                }

                if (!isset($content['download_url'])) {
                    $output->writeln('Error Github : ' . $e->getMessage());
                    continue;
                }

                $composer = json_decode(file_get_contents($content['download_url']), true);

                foreach ($composer['require'] as $requireProject => $requireRelease) {
                    if ($requireProject === $package) {
                        $composer['require'][$requireProject] = $version;

                        try {
                            $repo = $client->api('repo')->show($orga, $repos['name']);
                        } catch (\Exception $e) {
                            $output->writeln('Error Github : ' . $e->getMessage());
                            continue;
                        }

                        try {
                            $master = $client->api('repo')->branches($orga, $repos['name'], 'master');
                        } catch (\Exception $e) {
                            $output->writeln('Error Github : ' . $e->getMessage());
                            continue;
                        }

                        if (!isset($repos['name']) && !isset($repo['ssh_url'])) {
                            $output->writeln('Error Github : ' . $e->getMessage());
                            continue;
                        }

                        exec('rm -rf /tmp/' . $repos['name']);
                        exec('git clone ' . $repo['ssh_url'] . ' /tmp/' . $repos['name']);
                        file_put_contents('/tmp/' . $repos['name'] . '/composer.json', json_encode($composer, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
                        exec('composer update --no-interaction --no-scripts', $outputExec, $returnExec);

                        if ($returnExec !== 0) {
                            $output->writeln('Error Exec : ' . $outputExec);
                            continue;
                        }

                        $branch = 'bump-' . $package . '-' . $version;

                        try {
                            $client->api('git')->references()->create($orga, $repos['name'],
                                array(
                                    'ref' => 'refs/heads/' . $branch,
                                    'sha' => $master['commit']['sha']
                                )
                            );
                        } catch (\Exception $e) {
                            $output->writeln('Error Github : ' . $e->getMessage());
                        }

                        try {
                            $client->api('repo')->contents()->update(
                                 $orga,
                                 $repos['name'],
                                 'composer.json',
                                 file_get_contents('/tmp/' . $repos['name'] . '/composer.json'),
                                 'bump: ' . $package . ' to ' . $version,
                                 $content['sha'],
                                 'refs/heads/' . $branch
                             );
                        } catch (\Exception $e) {
                            $output->writeln('Error Github : ' . $e->getMessage());
                        }

                        try {
                            if (!is_null($composerLock)) {
                                $client->api('repo')->contents()->update(
                                    $orga,
                                    $repos['name'],
                                    'composer.lock',
                                    file_get_contents('/tmp/' . $repos['name'] . '/composer.lock'),
                                    'bump: ' . $package . ' to ' . $version,
                                    $composerLock['sha'],
                                    $branch
                                );
                            }
                        } catch (\Exception $e) {
                            $output->writeln('Error Github : ' . $e->getMessage());
                        }

                        try {
                            $request = $client->api('pull_request')->create($orga, $repos['name'], array(
                                'base'  => 'master',
                                'head'  => $branch,
                                'title' => 'Bump: ' . $package . 'to' . $version,
                                'body'  => "# Description \n Mise à jour de " . $package . ' en ' . $version
                            ));

                            if (isset($request['number'])) {
                                $client->api('issue')->update($orga, $repos['name'], $request['number'],
                                    array('labels' => array('chore'))
                                );
                            }

                        } catch (\Exception $e) {
                            $output->writeln('Error Github : ' . $e->getMessage());
                            continue;
                        }
                    }
                }
            }

            $result = $paginator->fetchNext();
        } while ($paginator->hasNext());
    }
}

<?php

namespace Jav\ApiTopiaBundle\Command;

use Jav\ApiTopiaBundle\GraphQL\SchemaBuilder;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'apitopia:build-schema',
    description: 'Builds the GraphQL schema',)]
final class BuildSchemaCommand extends Command
{
    public function __construct(private readonly SchemaBuilder $schemaBuilder)
    {
        parent::__construct();
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->schemaBuilder->build();

        return self::SUCCESS;
    }
}

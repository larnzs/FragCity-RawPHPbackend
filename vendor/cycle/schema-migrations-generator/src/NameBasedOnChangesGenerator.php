<?php

declare(strict_types=1);

namespace Cycle\Schema\Generator\Migrations;

use Cycle\Migrations\Atomizer\Atomizer;
use Cycle\Schema\Generator\Migrations\Changes\ChangeType;
use Cycle\Schema\Generator\Migrations\Changes\Collector;

final class NameBasedOnChangesGenerator implements NameGeneratorInterface
{
    public function generate(Atomizer $atomizer): string
    {
        $collector = new Collector();
        return \implode(
            '_',
            \array_map(
                fn(array $pair): string => $this->changeToString($pair[0], $pair[1]),
                $collector->collect($atomizer),
            ),
        );
    }

    private function changeToString(ChangeType $change, string $name): string
    {
        return sprintf(
            '%s_%s',
            match ($change) {
                ChangeType::CreateTable => 'create',
                ChangeType::DropTable => 'drop',
                ChangeType::RenameTable => 'rename',
                ChangeType::ChangeTable => 'change',
                ChangeType::AddColumn => 'add',
                ChangeType::DropColumn => 'rm',
                ChangeType::AlterColumn => 'alter',
                ChangeType::AddIndex => 'add_index',
                ChangeType::DropIndex => 'rm_index',
                ChangeType::AlterIndex => 'alter_index',
                ChangeType::AddFk => 'add_fk',
                ChangeType::DropFk => 'rm_fk',
                ChangeType::AlterFk => 'alter_fk',
            },
            $name,
        );
    }
}

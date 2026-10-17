<?php

declare(strict_types=1);

namespace Cycle\Schema\Generator\Migrations;

use Cycle\Migrations\Atomizer\Atomizer;
use Cycle\Schema\Generator\Migrations\Changes\ChangeType;
use Cycle\Schema\Generator\Migrations\Changes\Collector;

/**
 * Generates migration name based on changes count.
 *
 * Like:
 *      ct1_dt1_t2_c3_i3_fk3
 * There are:
 * - 1 create table
 * - 1 drop table
 * - 2 table changes
 * - 3 column changes
 * - 3 index changes
 * - 3 foreign key changes
 */
final class ChangesCountNameGenerator implements NameGeneratorInterface
{
    public function generate(Atomizer $atomizer): string
    {
        $collector = new Collector();
        $map = [];
        foreach ($collector->collect($atomizer) as $pair) {
            $key = $this->changeToString($pair[0]);
            $map[$key] ??= 0;
            $map[$key]++;
        }

        $result = [];
        foreach ($map as $key => $cnt) {
            $result[] = "{$key}{$cnt}";
        }

        return \implode('_', $result);
    }

    private function changeToString(ChangeType $change): string
    {
        return match ($change) {
            ChangeType::CreateTable => 'ct',
            ChangeType::DropTable => 'dt',
            ChangeType::RenameTable,
            ChangeType::ChangeTable => 't',
            ChangeType::AddColumn,
            ChangeType::DropColumn,
            ChangeType::AlterColumn => 'c',
            ChangeType::AddIndex,
            ChangeType::DropIndex,
            ChangeType::AlterIndex => 'i',
            ChangeType::AddFk,
            ChangeType::DropFk,
            ChangeType::AlterFk => 'fk',
        };
    }
}

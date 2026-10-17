<?php

declare(strict_types=1);

namespace Cycle\Schema\Generator\Migrations\Changes;

use Cycle\Database\Schema\AbstractTable;
use Cycle\Migrations\Atomizer\Atomizer;

final class Collector
{
    /**
     * @return array<array{ChangeType, non-empty-string}>
     */
    public function collect(Atomizer $atomizer): array
    {
        $result = [];

        foreach ($atomizer->getTables() as $table) {
            if ($table->getStatus() === AbstractTable::STATUS_NEW) {
                $result[] = [ChangeType::CreateTable, $table->getName()];
                continue;
            }

            if ($table->getStatus() === AbstractTable::STATUS_DECLARED_DROPPED) {
                $result[] = [ChangeType::DropTable, $table->getName()];
                continue;
            }

            if ($table->getComparator()->isRenamed()) {
                $result[] = [ChangeType::RenameTable, $table->getInitialName()];
                continue;
            }

            $result[] = [ChangeType::ChangeTable, $table->getName()];

            $comparator = $table->getComparator();

            foreach ($comparator->addedColumns() as $column) {
                $result[] = [ChangeType::AddColumn, $column->getName()];
            }

            foreach ($comparator->droppedColumns() as $column) {
                $result[] = [ChangeType::DropColumn, $column->getName()];
            }

            foreach ($comparator->alteredColumns() as $column) {
                $result[] = [ChangeType::AlterColumn, $column[0]->getName()];
            }

            foreach ($comparator->addedIndexes() as $index) {
                $result[] = [ChangeType::AddIndex, $index->getName()];
            }

            foreach ($comparator->droppedIndexes() as $index) {
                $result[] = [ChangeType::DropIndex, $index->getName()];
            }

            foreach ($comparator->alteredIndexes() as $index) {
                $result[] = [ChangeType::AlterIndex, $index[0]->getName()];
            }

            foreach ($comparator->addedForeignKeys() as $fk) {
                $result[] = [ChangeType::AddFk, $fk->getName()];
            }

            foreach ($comparator->droppedForeignKeys() as $fk) {
                $result[] = [ChangeType::DropFk, $fk->getName()];
            }

            foreach ($comparator->alteredForeignKeys() as $fk) {
                $result[] = [ChangeType::AlterFk, $fk[0]->getName()];
            }
        }

        return $result;
    }
}

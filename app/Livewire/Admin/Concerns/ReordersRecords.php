<?php

namespace App\Livewire\Admin\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

trait ReordersRecords
{
    /**
     * Move $record one step up (-1) or down (+1) among $siblings, then
     * renumber the siblings 1..n so sort_order never has gaps or ties.
     */
    protected function moveInOrder(Builder $siblings, Model $record, int $direction): void
    {
        $ids = $siblings->orderBy('sort_order')->orderBy('id')->pluck('sort_order', 'id');
        $order = $ids->keys()->all();

        $index = array_search($record->getKey(), $order, true);
        $target = $index === false ? false : $index + $direction;

        if ($target === false || ! isset($order[$target])) {
            return;
        }

        [$order[$index], $order[$target]] = [$order[$target], $order[$index]];

        foreach ($order as $position => $id) {
            if ((int) $ids[$id] !== $position + 1) {
                $record->newQuery()->whereKey($id)->update(['sort_order' => $position + 1]);
            }
        }
    }
}

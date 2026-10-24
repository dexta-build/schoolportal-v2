<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Models\SchoolClass;
use App\Models\SchoolLevel;
use Livewire\Attributes\Locked;
use Livewire\Component;

class ClassSelector extends Component
{
    public ?int $levelId = null;

    public ?int $classId = null;

    public string $search = '';

    /** @var array<int> Only show classes the user has access to (empty = all). Locked: a teacher must not widen it. */
    #[Locked]
    public array $restrictClassIds = [];

    public function mount(?int $levelId = null, ?int $classId = null, array $restrictClassIds = []): void
    {
        $this->restrictClassIds = $restrictClassIds;
        $this->levelId = $levelId;
        $this->classId = $classId;
    }

    public function updatedLevelId(): void
    {
        $this->classId = null;
        $this->search = '';
    }

    public function updatedSearch(): void
    {
        $this->classId = null;
    }

    public function getLevelsProperty()
    {
        $query = SchoolLevel::where('is_active', true)->orderBy('sort_order');

        if (! empty($this->restrictClassIds)) {
            $levelIds = SchoolClass::whereIn('id', $this->restrictClassIds)->pluck('level_id')->unique();
            $query->whereIn('id', $levelIds);
        }

        return $query->get();
    }

    public function getClassesProperty()
    {
        $query = SchoolClass::with('level:id,name')->where('is_active', true)->orderBy('sort_order');

        if ($this->levelId) {
            $query->where('level_id', $this->levelId);
        }

        if (! empty($this->restrictClassIds)) {
            $query->whereIn('id', $this->restrictClassIds);
        }

        if (trim($this->search) !== '') {
            $term = '%'.trim($this->search).'%';
            $query->where('name', 'like', $term);
        }

        return $query->get();
    }

    public function getSelectedClassProperty(): ?SchoolClass
    {
        if (! $this->classId) {
            return null;
        }

        return SchoolClass::with('level:id,name')
            ->where('id', $this->classId)
            ->first();
    }

    public function selectClass(int $id): void
    {
        $this->classId = $id;
        $this->search = '';
    }

    public function clearClass(): void
    {
        $this->classId = null;
        $this->search = '';
    }

    public function render()
    {
        return view('livewire.class-selector');
    }
}

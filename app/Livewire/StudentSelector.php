<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Models\SchoolClass;
use App\Models\SchoolLevel;
use App\Models\User;
use Livewire\Attributes\Locked;
use Livewire\Component;

class StudentSelector extends Component
{
    public ?int $levelId = null;

    public ?int $classId = null;

    public ?int $studentId = null;

    public string $search = '';

    /** @var array<int> Only show classes the user has access to (empty = all). Locked: a teacher must not widen it. */
    #[Locked]
    public array $restrictClassIds = [];

    public function mount(?int $levelId = null, ?int $classId = null, ?int $studentId = null, array $restrictClassIds = []): void
    {
        $this->restrictClassIds = $restrictClassIds;
        $this->levelId = $levelId;
        $this->classId = $classId;
        $this->studentId = $studentId;
    }

    public function updatedLevelId(): void
    {
        $this->classId = null;
        $this->studentId = null;
        $this->search = '';
    }

    public function updatedClassId(): void
    {
        $this->studentId = null;
        $this->search = '';
    }

    public function updatedSearch(): void
    {
        $this->studentId = null;
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
        if (! $this->levelId) {
            return collect();
        }

        $query = SchoolClass::where('level_id', $this->levelId)
            ->where('is_active', true)
            ->orderBy('sort_order');

        if (! empty($this->restrictClassIds)) {
            $query->whereIn('id', $this->restrictClassIds);
        }

        return $query->get();
    }

    public function getStudentsProperty()
    {
        if (! $this->classId) {
            return collect();
        }

        // classId comes from the browser — keep it within the classes this user may see
        if (! empty($this->restrictClassIds) && ! in_array($this->classId, array_map('intval', $this->restrictClassIds), true)) {
            return collect();
        }

        $query = User::where('role', 'student')
            ->where('is_active', true)
            ->whereHas('studentProfile', fn ($q) => $q->where('class_id', $this->classId));

        if (trim($this->search) !== '') {
            $term = '%'.trim($this->search).'%';
            $query->where(function ($q) use ($term) {
                $q->where('name', 'like', $term)
                    ->orWhere('username', 'like', $term)
                    ->orWhereHas('studentProfile', fn ($q2) => $q2->where('admission_number', 'like', $term));
            });
        }

        return $query->with('studentProfile:id,user_id,admission_number')->orderBy('name')->limit(100)->get();
    }

    public function getSelectedStudentProperty(): ?User
    {
        if (! $this->studentId) {
            return null;
        }

        return User::with('studentProfile:id,user_id,admission_number')
            ->where('id', $this->studentId)
            ->where('role', 'student')
            ->first();
    }

    public function selectStudent(int $id): void
    {
        $this->studentId = $id;
        $this->search = '';
    }

    public function clearStudent(): void
    {
        $this->studentId = null;
        $this->search = '';
    }

    public function render()
    {
        return view('livewire.student-selector');
    }
}

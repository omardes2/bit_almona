<?php

namespace App\Rules;

use App\Models\Category;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Prevents a category from becoming its own parent, or a child of one of
 * its own descendants (which would create a cycle in the tree).
 */
class ValidCategoryParent implements ValidationRule
{
    public function __construct(private readonly ?Category $category) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (blank($value) || $this->category === null || ! $this->category->exists) {
            return;
        }

        if ((int) $value === $this->category->id) {
            $fail('لا يمكن أن يكون القسم قسمًا رئيسيًا لنفسه.');

            return;
        }

        if (in_array((int) $value, $this->category->selfAndDescendantIds(), true)) {
            $fail('لا يمكن نقل القسم تحت أحد أقسامه الفرعية.');
        }
    }
}

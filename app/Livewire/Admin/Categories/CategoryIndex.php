<?php

namespace App\Livewire\Admin\Categories;

use App\Actions\Catalog\DeleteCategory;
use App\Livewire\Admin\Concerns\AuthorizesCatalog;
use App\Livewire\Admin\Concerns\ReordersRecords;
use App\Livewire\Admin\Concerns\Toasts;
use App\Models\Category;
use App\Support\ArabicText;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Categories are shown as a tree (not paginated): a supermarket has tens,
 * not thousands, of categories, and the whole tree is loaded in one query.
 */
#[Layout('layouts.admin')]
#[Title('الأقسام')]
class CategoryIndex extends Component
{
    use AuthorizesCatalog, ReordersRecords, Toasts;

    #[Url(except: '')]
    public string $search = '';

    #[Url(except: 'sort_order')]
    public string $sort = 'sort_order';

    public function toggleActive(int $id): void
    {
        $category = Category::findOrFail($id);
        $category->update(['is_active' => ! $category->is_active]);

        $this->toast($category->is_active ? 'تم تفعيل القسم.' : 'تم تعطيل القسم.');
    }

    public function move(int $id, int $direction): void
    {
        $category = Category::findOrFail($id);

        $this->moveInOrder(
            Category::query()->where('parent_id', $category->parent_id),
            $category,
            $direction > 0 ? 1 : -1,
        );
    }

    public function delete(int $id, DeleteCategory $deleteCategory): void
    {
        $category = Category::findOrFail($id);

        try {
            $deleteCategory->handle($category);
        } catch (ValidationException $e) {
            $this->toast($e->validator->errors()->first(), 'error');

            return;
        }

        $this->toast('تم حذف القسم «'.$category->name.'».');
    }

    public function render()
    {
        $query = Category::query()->withCount(['products', 'children']);

        $query = $this->sort === 'name'
            ? $query->orderBy('name')
            : $query->orderBy('sort_order')->orderBy('id');

        $categories = $query->get();

        if (($term = ArabicText::normalize($this->search)) !== '') {
            $categories = $categories->filter(
                fn (Category $c) => str_contains(ArabicText::normalize($c->name.' '.$c->slug), $term)
            );
        }

        return view('livewire.admin.categories.index', [
            'rows' => Category::flattenTree($categories->values()),
            'total' => Category::count(),
        ]);
    }
}

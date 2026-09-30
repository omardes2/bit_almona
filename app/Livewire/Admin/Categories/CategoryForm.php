<?php

namespace App\Livewire\Admin\Categories;

use App\Livewire\Admin\Concerns\AuthorizesCatalog;
use App\Livewire\Admin\Concerns\Toasts;
use App\Models\Category;
use App\Rules\ValidCategoryParent;
use App\Services\Media\ImageStorage;
use App\Support\ImageRules;
use App\Support\Slug;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

#[Layout('layouts.admin')]
class CategoryForm extends Component
{
    use AuthorizesCatalog, Toasts, WithFileUploads;

    public ?Category $category = null;

    public string $name = '';

    public string $slug = '';

    public int|string|null $parent_id = null;

    public string $description = '';

    public int|string $sort_order = 0;

    public bool $is_active = true;

    /** @var TemporaryUploadedFile|null */
    public $image = null;

    public function mount(?Category $category = null): void
    {
        if ($category?->exists) {
            $this->category = $category;
            $this->fill($category->only(['name', 'slug', 'parent_id', 'sort_order', 'is_active']));
            $this->description = (string) $category->description;
        } else {
            $this->parent_id = request()->integer('parent') ?: null;
            $this->sort_order = (int) Category::where('parent_id', $this->parent_id)->max('sort_order') + 1;
        }
    }

    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:100'],
            'slug' => ['nullable', 'string', 'max:150', 'regex:'.Slug::PATTERN, Rule::unique(Category::class, 'slug')->ignore($this->category?->id)],
            'parent_id' => ['nullable', 'integer', Rule::exists(Category::class, 'id'), new ValidCategoryParent($this->category)],
            'description' => ['nullable', 'string', 'max:1000'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:100000'],
            'is_active' => ['boolean'],
            'image' => ImageRules::rules(),
        ];
    }

    protected function validationAttributes(): array
    {
        return ['name' => 'اسم القسم', 'slug' => 'الرابط المختصر (slug)'];
    }

    protected function messages(): array
    {
        return ['slug.regex' => 'الرابط المختصر يقبل حروفًا عربية أو إنجليزية صغيرة وأرقامًا وشرطات (-) فقط.'];
    }

    public function updatedImage(): void
    {
        $this->validateOnly('image');
    }

    public function removeImage(ImageStorage $images): void
    {
        if ($this->category?->image) {
            $old = $this->category->image;
            $this->category->update(['image' => null]);
            $images->delete($old);
            $this->toast('تم حذف صورة القسم.');
        }
    }

    public function save(ImageStorage $images): void
    {
        $this->slug = Slug::make($this->slug);
        $data = $this->validate();

        $category = $this->category ?? new Category;
        $oldImage = $category->image;
        $newImage = $this->image ? $images->store($this->image, 'categories') : null;

        DB::transaction(function () use ($category, $data, $newImage) {
            $category->fill([
                'name' => trim($data['name']),
                'slug' => $data['slug'] ?: Slug::unique(Category::class, $data['name'], $category->id),
                'parent_id' => $data['parent_id'] ?: null,
                'description' => $data['description'] ?: null,
                'sort_order' => (int) $data['sort_order'],
                'is_active' => $data['is_active'],
            ]);

            if ($newImage) {
                $category->image = $newImage;
            }

            $category->save();
        });

        // Only remove the old file once the new one is safely saved.
        if ($newImage && $oldImage) {
            $images->delete($oldImage);
        }

        $this->flashToast($this->category ? 'تم حفظ تعديلات القسم.' : 'تم إنشاء القسم «'.$category->name.'».');

        $this->redirectRoute('admin.categories.index', navigate: true);
    }

    public function render()
    {
        $exclude = $this->category ? $this->category->selfAndDescendantIds() : [];

        $parents = Category::flattenTree(
            Category::query()->whereNotIn('id', $exclude)->orderBy('sort_order')->orderBy('id')->get(['id', 'parent_id', 'name', 'sort_order'])
        );

        return view('livewire.admin.categories.form', ['parents' => $parents])
            ->title($this->category ? 'تعديل قسم' : 'إضافة قسم');
    }
}

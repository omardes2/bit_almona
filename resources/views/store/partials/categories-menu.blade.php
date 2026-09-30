@if ($categories->isEmpty())
    <p class="p-3 text-sm text-gray-500">لا توجد أقسام بعد.</p>
@else
    <ul class="space-y-0.5">
        @foreach ($categories as $category)
            <li>
                <a href="{{ $category->url() }}" wire:navigate class="flex items-center gap-3 rounded-xl p-2.5 font-bold hover:bg-brand-50 focus-visible:bg-brand-50">
                    <x-admin.thumb :url="$category->thumbnailUrl()" alt="" size="size-10" />
                    {{ $category->name }}
                </a>
                @if ($category->children->isNotEmpty())
                    <ul class="mb-1 ms-14 flex flex-wrap gap-1.5">
                        @foreach ($category->children as $child)
                            <li><a href="{{ $child->url() }}" wire:navigate class="inline-block rounded-full bg-gray-100 px-3 py-1.5 text-sm hover:bg-brand-50">{{ $child->name }}</a></li>
                        @endforeach
                    </ul>
                @endif
            </li>
        @endforeach
    </ul>
@endif

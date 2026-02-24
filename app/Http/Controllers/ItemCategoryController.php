<?php

namespace App\Http\Controllers;

use App\Models\ItemCategory;
use Illuminate\Http\Request;

/** CRUD категорий товаров. */
class ItemCategoryController extends Controller
{
    public function index()
    {
        $categories = ItemCategory::with('parent')->orderBy('name')->paginate(20);

        return view('item-categories.index', compact('categories'));
    }

    public function create()
    {
        $parents = ItemCategory::orderBy('name')->get();

        return view('item-categories.create', compact('parents'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'parent_id' => 'nullable|exists:item_categories,id',
            'evaluation_config' => 'nullable|array',
            'evaluation_config.ai_prompt_suffix' => 'nullable|string|max:4000',
        ]);
        $config = null;
        $suffix = trim((string) ($data['evaluation_config']['ai_prompt_suffix'] ?? ''));
        if ($suffix !== '') {
            $config = ['ai_prompt_suffix' => $suffix];
        }
        ItemCategory::create([
            'name' => $data['name'],
            'parent_id' => $data['parent_id'] ?? null,
            'evaluation_config' => $config,
        ]);

        return redirect()->route('item-categories.index')->with('success', 'Категория создана.');
    }

    public function edit(ItemCategory $itemCategory)
    {
        $parents = ItemCategory::where('id', '!=', $itemCategory->id)->orderBy('name')->get();

        return view('item-categories.edit', compact('itemCategory', 'parents'));
    }

    public function update(Request $request, ItemCategory $itemCategory)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'parent_id' => 'nullable|exists:item_categories,id',
            'evaluation_config' => 'nullable|array',
            'evaluation_config.ai_prompt_suffix' => 'nullable|string|max:4000',
        ]);
        $config = $itemCategory->evaluation_config ?? [];
        $suffix = trim((string) ($data['evaluation_config']['ai_prompt_suffix'] ?? ''));
        $config['ai_prompt_suffix'] = $suffix !== '' ? $suffix : null;
        if (empty(array_filter($config))) {
            $config = null;
        }
        $itemCategory->update([
            'name' => $data['name'],
            'parent_id' => $data['parent_id'] ?? null,
            'evaluation_config' => $config,
        ]);

        return redirect()->route('item-categories.index')->with('success', 'Категория обновлена.');
    }

    public function destroy(ItemCategory $itemCategory)
    {
        $itemCategory->delete();

        return redirect()->route('item-categories.index')->with('success', 'Категория удалена.');
    }
}

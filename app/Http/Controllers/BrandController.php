<?php

namespace App\Http\Controllers;

use App\Models\Brand;
use Illuminate\Http\Request;

/** CRUD брендов. */
class BrandController extends Controller
{
    public function index()
    {
        $brands = Brand::orderBy('name')->paginate(20);

        return view('brands.index', compact('brands'));
    }

    public function create()
    {
        return view('brands.create');
    }

    public function store(Request $request)
    {
        $data = $request->validate(['name' => 'required|string|max:255']);
        Brand::create($data);

        return redirect()->route('brands.index')->with('success', 'Бренд создан.');
    }

    public function edit(Brand $brand)
    {
        return view('brands.edit', compact('brand'));
    }

    public function update(Request $request, Brand $brand)
    {
        $data = $request->validate(['name' => 'required|string|max:255']);
        $brand->update($data);

        return redirect()->route('brands.index')->with('success', 'Бренд обновлён.');
    }

    public function destroy(Brand $brand)
    {
        $brand->delete();

        return redirect()->route('brands.index')->with('success', 'Бренд удалён.');
    }
}

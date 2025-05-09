<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Category;
use App\Services\OdooService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ProductController extends Controller
{
    protected $odooService;

    public function __construct(OdooService $odooService)
    {
        $this->odooService = $odooService;
    }

    public function index()
    {
        $products = Product::with('category')->paginate(15);
        return view('products.index', compact('products'));
    }

    public function create()
    {
        $categories = Category::all();
        return view('products.create', compact('categories'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'sku' => 'required|string|max:50|unique:products',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'cost' => 'required|numeric|min:0',
            'quantity' => 'required|integer|min:0',
            'category_id' => 'required|exists:categories,id',
            'image' => 'nullable|image|max:2048',
            'status' => 'required|in:active,inactive'
        ]);

        // Handle image upload
        if ($request->hasFile('image')) {
            $path = $request->file('image')->store('products', 'public');
            $validated['image_path'] = $path;
        }

        // Get Odoo category ID
        $category = Category::find($validated['category_id']);
        $validated['odoo_category_id'] = $category->odoo_category_id;

        // Create product in Odoo
        $odooProductId = $this->odooService->createProduct($validated);
        
        if ($odooProductId) {
            $validated['odoo_product_id'] = $odooProductId;
            Product::create($validated);
            
            return redirect()->route('products.index')
                ->with('success', 'Product created successfully.');
        }

        return back()->with('error', 'Failed to create product in Odoo.');
    }

    public function show(Product $product)
    {
        $product->load('category', 'transactions');
        return view('products.show', compact('product'));
    }

    public function edit(Product $product)
    {
        $categories = Category::all();
        return view('products.edit', compact('product', 'categories'));
    }

    public function update(Request $request, Product $product)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'sku' => 'required|string|max:50|unique:products,sku,' . $product->id,
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'cost' => 'required|numeric|min:0',
            'quantity' => 'required|integer|min:0',
            'category_id' => 'required|exists:categories,id',
            'image' => 'nullable|image|max:2048',
            'status' => 'required|in:active,inactive'
        ]);

        // Handle image upload
        if ($request->hasFile('image')) {
            // Delete old image if exists
            if ($product->image_path) {
                Storage::disk('public')->delete($product->image_path);
            }
            
            $path = $request->file('image')->store('products', 'public');
            $validated['image_path'] = $path;
        }

        // Update product in Odoo
        $result = $this->odooService->updateProduct($product->odoo_product_id, $validated);
        
        if ($result) {
            // Update stock if quantity changed
            if ($product->quantity != $validated['quantity']) {
                $this->odooService->updateStock(
                    $product->odoo_product_id, 
                    $validated['quantity'], 
                    'Stock adjustment from web interface'
                );
            }
            
            $product->update($validated);
            
            return redirect()->route('products.index')
                ->with('success', 'Product updated successfully.');
        }

        return back()->with('error', 'Failed to update product in Odoo.');
    }

    public function destroy(Product $product)
    {
        // In a real application, you might want to archive the product in Odoo
        // rather than deleting it completely
        
        // Delete image if exists
        if ($product->image_path) {
            Storage::disk('public')->delete($product->image_path);
        }
        
        $product->delete();
        
        return redirect()->route('products.index')
            ->with('success', 'Product deleted successfully.');
    }

    public function sync()
    {
        $result = $this->odooService->syncProducts();
        
        if ($result) {
            return redirect()->route('products.index')
                ->with('success', 'Products synchronized successfully.');
        }
        
        return back()->with('error', 'Failed to synchronize products from Odoo.');
    }
}
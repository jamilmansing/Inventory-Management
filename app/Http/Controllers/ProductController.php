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

    public function index(Request $request)
    {
        // Start with a base query
        $query = Product::with('category');
        
        // Apply search filter
        if ($request->has('search') && !empty($request->search)) {
            $searchTerm = $request->search;
            $query->where(function($q) use ($searchTerm) {
                $q->where('name', 'like', "%{$searchTerm}%")
                  ->orWhere('sku', 'like', "%{$searchTerm}%")
                  ->orWhere('description', 'like', "%{$searchTerm}%");
            });
        }
        
        // Apply category filter
        if ($request->has('category') && !empty($request->category)) {
            $query->where('category_id', $request->category);
        }
        
        // Apply status filter
        if ($request->has('status') && !empty($request->status)) {
            $query->where('status', $request->status);
        }
        
        // Get all categories for the filter dropdown
        $categories = Category::all();
        
        // Execute the query with pagination
        $products = $query->paginate(15)->withQueryString();
        
        return view('products.index', compact('products', 'categories'));
    }

    // Other methods remain unchanged...

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
        // Convert image to base64 for Odoo
        $imageContents = Storage::disk('public')->get($path);
        $validated['image_1920'] = base64_encode($imageContents); // Key for Odoo's image field

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

            // Convert image to base64 for Odoo
            $imageContents = Storage::disk('public')->get($path);
            $validated['image_1920'] = base64_encode($imageContents);
        } else {
            // If no new image, reuse existing image
            if ($product->image_path) {
                $imageContents = Storage::disk('public')->get($product->image_path);
                $validated['image_1920'] = base64_encode($imageContents);
            }
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
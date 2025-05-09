<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use App\Models\Product;
use App\Services\OdooService;
use Illuminate\Http\Request;

class TransactionController extends Controller
{
    protected $odooService;

    public function __construct(OdooService $odooService)
    {
        $this->odooService = $odooService;
    }

    public function index()
    {
        $transactions = Transaction::with('product')->latest()->paginate(15);
        return view('transactions.index', compact('transactions'));
    }

    public function create()
    {
        $products = Product::where('status', 'active')->get();
        return view('transactions.create', compact('products'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'product_id' => 'required|exists:products,id',
            'type' => 'required|in:purchase,sale,adjustment',
            'quantity' => 'required|integer|not_in:0',
            'unit_price' => 'required|numeric|min:0',
            'reference' => 'required|string|max:255',
            'notes' => 'nullable|string'
        ]);

        $validated['total_price'] = $validated['quantity'] * $validated['unit_price'];
        
        $product = Product::find($validated['product_id']);
        
        // Update product quantity
        $newQuantity = $product->quantity;
        if ($validated['type'] == 'purchase' || ($validated['type'] == 'adjustment' && $validated['quantity'] > 0)) {
            $newQuantity += abs($validated['quantity']);
        } else {
            $newQuantity -= abs($validated['quantity']);
            
            // Prevent negative stock
            if ($newQuantity < 0) {
                return back()->with('error', 'Insufficient stock available.');
            }
        }
        
        // Update stock in Odoo
        $result = $this->odooService->updateStock(
            $product->odoo_product_id, 
            $newQuantity, 
            $validated['reference']
        );
        
        if ($result) {
            // Update local product quantity
            $product->quantity = $newQuantity;
            $product->save();
            
            // Create transaction record
            Transaction::create($validated);
            
            return redirect()->route('transactions.index')
                ->with('success', 'Transaction recorded successfully.');
        }
        
        return back()->with('error', 'Failed to update stock in Odoo.');
    }

    public function show(Transaction $transaction)
    {
        $transaction->load('product');
        return view('transactions.show', compact('transaction'));
    }

    public function sync()
    {
        $result = $this->odooService->syncTransactions();
        
        if ($result) {
            return redirect()->route('transactions.index')
                ->with('success', 'Transactions synchronized successfully.');
        }
        
        return back()->with('error', 'Failed to synchronize transactions from Odoo.');
    }
}
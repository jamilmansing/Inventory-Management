<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index()
    {
        // Get total products count
        $totalProducts = Product::count();
        
        // Get total stock value
        $stockValue = Product::sum(DB::raw('price * quantity'));
        
        // Get low stock products (less than 10 items)
        $lowStockProducts = Product::where('quantity', '<', 10)->count();
        
        // Get recent transactions
        $recentTransactions = Transaction::with('product')
            ->latest()
            ->take(5)
            ->get();
        
        // Get sales data for chart (last 7 days)
        $salesData = Transaction::where('type', 'sale')
            ->where('created_at', '>=', now()->subDays(7))
            ->select(
                DB::raw('DATE(created_at) as date'),
                DB::raw('SUM(total_price) as total')
            )
            ->groupBy('date')
            ->get();
        
        // Get top selling products
        $topProducts = Transaction::where('type', 'sale')
            ->select('product_id', DB::raw('SUM(quantity) as total_sold'))
            ->with('product')
            ->groupBy('product_id')
            ->orderBy('total_sold', 'desc')
            ->take(5)
            ->get();
        
        return view('dashboard', compact(
            'totalProducts',
            'stockValue',
            'lowStockProducts',
            'recentTransactions',
            'salesData',
            'topProducts'
        ));
    }
}
<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Transaction;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Barryvdh\DomPDF\Facade\Pdf as PDF;

class ReportController extends Controller
{
    public function index()
    {
        return view('reports.index');
    }
    
    public function inventory(Request $request)
    {
        $query = Product::with('category');
        
        // Apply filters
        if ($request->has('category_id') && $request->category_id) {
            $query->where('category_id', $request->category_id);
        }
        
        if ($request->has('status') && $request->status) {
            $query->where('status', $request->status);
        }
        
        if ($request->has('stock_status')) {
            if ($request->stock_status == 'low') {
                $query->where('quantity', '<', 10);
            } elseif ($request->stock_status == 'out') {
                $query->where('quantity', 0);
            }
        }
        
        $products = $query->get();
        $categories = Category::all();
        
        // Generate PDF if requested
        if ($request->has('export') && $request->export == 'pdf') {
            $pdf = PDF::loadView('reports.inventory_pdf', compact('products'));
            return $pdf->download('inventory_report.pdf');
        }
        
        return view('reports.inventory', compact('products', 'categories'));
    }
    
    public function transactions(Request $request)
    {
        $query = Transaction::with('product');
        
        // Apply filters
        if ($request->has('product_id') && $request->product_id) {
            $query->where('product_id', $request->product_id);
        }
        
        if ($request->has('type') && $request->type) {
            $query->where('type', $request->type);
        }
        
        if ($request->has('date_from') && $request->date_from) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }
        
        if ($request->has('date_to') && $request->date_to) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }
        
        $transactions = $query->latest()->get();
        $products = Product::all();
        
        // Calculate totals
        $totals = [
            'purchases' => $transactions->where('type', 'purchase')->sum('total_price'),
            'sales' => $transactions->where('type', 'sale')->sum('total_price'),
            'adjustments' => $transactions->where('type', 'adjustment')->sum('total_price')
        ];
        
        // Generate PDF if requested
        if ($request->has('export') && $request->export == 'pdf') {
            $pdf = PDF::loadView('reports.transactions_pdf', compact('transactions', 'totals'));
            return $pdf->download('transactions_report.pdf');
        }
        
        return view('reports.transactions', compact('transactions', 'products', 'totals'));
    }
    
    public function sales(Request $request)
    {
        // Default to last 30 days if no dates provided
        $dateFrom = $request->date_from ?? now()->subDays(30)->format('Y-m-d');
        $dateTo = $request->date_to ?? now()->format('Y-m-d');
        
        // Get daily sales
        $dailySales = Transaction::where('type', 'sale')
            ->whereDate('created_at', '>=', $dateFrom)
            ->whereDate('created_at', '<=', $dateTo)
            ->select(
                DB::raw('DATE(created_at) as date'),
                DB::raw('SUM(total_price) as total')
            )
            ->groupBy('date')
            ->orderBy('date')
            ->get();
        
        // Get product category sales
        $categorySales = Transaction::where('type', 'sale')
            ->whereDate('transactions.created_at', '>=', $dateFrom)  // Fixed
            ->whereDate('transactions.created_at', '<=', $dateTo)    // Fixed
            ->join('products', 'transactions.product_id', '=', 'products.id')
            ->join('categories', 'products.category_id', '=', 'categories.id')
            ->select(
                'categories.name as category',
                DB::raw('SUM(transactions.total_price) as total')
            )
            ->groupBy('categories.name')
            ->orderBy('total', 'desc')
            ->get();
        
        // Get top selling products
        $topProducts = Transaction::where('type', 'sale')
            ->whereDate('created_at', '>=', $dateFrom)
            ->whereDate('created_at', '<=', $dateTo)
            ->select(
                'product_id',
                DB::raw('SUM(quantity) as total_quantity'),
                DB::raw('SUM(total_price) as total_price')
            )
            ->with('product')
            ->groupBy('product_id')
            ->orderBy('total_quantity', 'desc')
            ->take(10)
            ->get();
        
        // Generate PDF if requested
        if ($request->has('export') && $request->export == 'pdf') {
            $pdf = PDF::loadView('reports.sales_pdf', compact('dailySales', 'categorySales', 'topProducts', 'dateFrom', 'dateTo'));
            return $pdf->download('sales_report.pdf');
        }
        
        return view('reports.sales', compact('dailySales', 'categorySales', 'topProducts', 'dateFrom', 'dateTo'));
    }
}
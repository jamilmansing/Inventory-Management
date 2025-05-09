<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('Transaction Details') }}
            </h2>
            <a href="{{ route('transactions.index') }}" class="inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 active:bg-gray-900 focus:outline-none focus:border-gray-900 focus:ring ring-gray-300 disabled:opacity-25 transition ease-in-out duration-150">
                Back to Transactions
            </a>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm rounded-lg">
                <div class="p-6 bg-white border-b border-gray-200">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <h3 class="text-lg font-medium text-gray-900 mb-4">Transaction Information</h3>
                            
                            <div class="grid grid-cols-1 gap-4">
                                <div>
                                    <p class="text-sm text-gray-500">Transaction Type</p>
                                    <p class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium 
                                        @if($transaction->type == 'purchase') bg-green-100 text-green-800 
                                        @elseif($transaction->type == 'sale') bg-blue-100 text-blue-800 
                                        @else bg-yellow-100 text-yellow-800 @endif">
                                        {{ ucfirst($transaction->type) }}
                                    </p>
                                </div>
                                
                                <div>
                                    <p class="text-sm text-gray-500">Quantity</p>
                                    <p class="font-medium">{{ $transaction->quantity }}</p>
                                </div>
                                
                                <div>
                                    <p class="text-sm text-gray-500">Unit Price</p>
                                    <p class="font-medium">${{ number_format($transaction->unit_price, 2) }}</p>
                                </div>
                                
                                <div>
                                    <p class="text-sm text-gray-500">Total Price</p>
                                    <p class="font-medium">${{ number_format($transaction->total_price, 2) }}</p>
                                </div>
                                
                                <div>
                                    <p class="text-sm text-gray-500">Reference</p>
                                    <p class="font-medium">{{ $transaction->reference }}</p>
                                </div>
                                
                                <div>
                                    <p class="text-sm text-gray-500">Date</p>
                                    <p class="font-medium">{{ $transaction->created_at->format('M d, Y H:i') }}</p>
                                </div>
                                
                                @if($transaction->odoo_transaction_id)
                                    <div>
                                        <p class="text-sm text-gray-500">Odoo Transaction ID</p>
                                        <p class="font-medium">{{ $transaction->odoo_transaction_id }}</p>
                                    </div>
                                @endif
                                
                                @if($transaction->notes)
                                    <div>
                                        <p class="text-sm text-gray-500">Notes</p>
                                        <p class="font-medium">{{ $transaction->notes }}</p>
                                    </div>
                                @endif
                            </div>
                        </div>
                        
                        <div>
                            <h3 class="text-lg font-medium text-gray-900 mb-4">Product Information</h3>
                            
                            <div class="flex items-center mb-4">
                                <div class="flex-shrink-0 h-16 w-16">
                                    @if($transaction->product->image_path)
                                        <img class="h-16 w-16 rounded-md object-cover" src="{{ asset('storage/' . $transaction->product->image_path) }}" alt="{{ $transaction->product->name }}">
                                    @else
                                        <div class="h-16 w-16 rounded-md bg-gray-200 flex items-center justify-center">
                                            <svg class="h-8 w-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                                            </svg>
                                        </div>
                                    @endif
                                </div>
                                <div class="ml-4">
                                    <h4 class="text-lg font-semibold">{{ $transaction->product->name }}</h4>
                                    <p class="text-sm text-gray-500">SKU: {{ $transaction->product->sku }}</p>
                                </div>
                            </div>
                            
                            <div class="grid grid-cols-1 gap-4">
                                <div>
                                    <p class="text-sm text-gray-500">Category</p>
                                    <p class="font-medium">{{ $transaction->product->category->name }}</p>
                                </div>
                                
                                <div>
                                    <p class="text-sm text-gray-500">Current Stock</p>
                                    <p class="font-medium @if($transaction->product->quantity < 10) text-red-600 @endif">
                                        {{ $transaction->product->quantity }}
                                        @if($transaction->product->quantity < 10)
                                            <span class="text-xs bg-red-100 text-red-800 px-2 py-0.5 rounded-full ml-2">Low Stock</span>
                                        @endif
                                    </p>
                                </div>
                                
                                <div>
                                    <p class="text-sm text-gray-500">Current Price</p>
                                    <p class="font-medium">${{ number_format($transaction->product->price, 2) }}</p>
                                </div>
                                
                                <div>
                                    <p class="text-sm text-gray-500">Status</p>
                                    <p class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium 
                                        @if($transaction->product->status == 'active') bg-green-100 text-green-800 @else bg-red-100 text-red-800 @endif">
                                        {{ ucfirst($transaction->product->status) }}
                                    </p>
                                </div>
                                
                                <div class="mt-4">
                                    <a href="{{ route('products.show', $transaction->product) }}" class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 active:bg-indigo-900 focus:outline-none focus:border-indigo-900 focus:ring ring-indigo-300 disabled:opacity-25 transition ease-in-out duration-150">
                                        View Product Details
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
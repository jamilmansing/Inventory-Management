<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Dashboard') }}
        </h2>
    </x-slot>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
        <!-- Total Products Card -->
        <div class="bg-white overflow-hidden shadow-sm rounded-lg dashboard-card">
            <div class="p-6">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-indigo-100 text-indigo-500">
                        <svg class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4"></path>
                        </svg>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500">Total Products</p>
                        <p class="text-2xl font-semibold text-gray-700">{{ $totalProducts ?? 0 }}</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Stock Value Card -->
        <div class="bg-white overflow-hidden shadow-sm rounded-lg dashboard-card">
            <div class="p-6">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-green-100 text-green-500">
                        <svg class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500">Total Stock Value</p>
                        <p class="text-2xl font-semibold text-gray-700">${{ number_format($stockValue ?? 0, 2) }}</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Low Stock Card -->
        <div class="bg-white overflow-hidden shadow-sm rounded-lg dashboard-card">
            <div class="p-6">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-red-100 text-red-500">
                        <svg class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                        </svg>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500">Low Stock Items</p>
                        <p class="text-2xl font-semibold text-gray-700">{{ $lowStockProducts ?? 0 }}</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
        <!-- Sales Chart -->
        <div class="bg-white overflow-hidden shadow-sm rounded-lg">
            <div class="p-6">
                <h3 class="text-lg font-semibold text-gray-700 mb-4">Sales Last 7 Days</h3>
                <div id="chart-container" style="position: relative; height: 200px;">
                    <canvas id="salesChart"></canvas>
                </div>
            </div>
        </div>

        <!-- Top Products -->
        <div class="bg-white overflow-hidden shadow-sm rounded-lg">
            <div class="p-6">
                <h3 class="text-lg font-semibold text-gray-700 mb-4">Top Selling Products</h3>
                @if(isset($topProducts) && $topProducts->count() > 0)
                    <div class="space-y-4">
                        @foreach($topProducts as $product)
                            <div class="flex items-center">
                                <div class="w-12 h-12 flex-shrink-0 bg-gray-200 rounded-md overflow-hidden">
                                    @if(isset($product->product) && $product->product->image_path && file_exists(public_path('storage/' . $product->product->image_path)))
                                        <img src="{{ asset('storage/' . $product->product->image_path) }}" alt="{{ $product->product->name }}" class="w-full h-full object-cover">
                                    @else
                                        <div class="w-full h-full flex items-center justify-center text-gray-500">
                                            <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                                            </svg>
                                        </div>
                                    @endif
                                </div>
                                <div class="ml-4 flex-1">
                                    <p class="text-sm font-medium text-gray-900">{{ $product->product->name ?? 'Product' }}</p>
                                    <p class="text-sm text-gray-500">{{ $product->total_sold ?? 0 }} units sold</p>
                                </div>
                                <div class="text-right">
                                    <p class="text-sm font-medium text-gray-900">${{ isset($product->product) ? number_format($product->product->price, 2) : '0.00' }}</p>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <p class="text-gray-500">No sales data available.</p>
                @endif
            </div>
        </div>
    </div>

    <!-- Recent Transactions -->
    <div class="bg-white overflow-hidden shadow-sm rounded-lg">
        <div class="p-6">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-semibold text-gray-700">Recent Transactions</h3>
                <a href="{{ route('transactions.index') }}" class="text-sm text-indigo-600 hover:text-indigo-900">View All</a>
            </div>
            
            @if(isset($recentTransactions) && $recentTransactions->count() > 0)
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Product</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Quantity</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @foreach($recentTransactions as $transaction)
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex items-center">
                                            <div class="text-sm font-medium text-gray-900">
                                                {{ $transaction->product->name ?? 'Unknown Product' }}
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                            @if($transaction->type == 'purchase') bg-green-100 text-green-800 
                                            @elseif($transaction->type == 'sale') bg-blue-100 text-blue-800 
                                            @else bg-yellow-100 text-yellow-800 @endif">
                                            {{ ucfirst($transaction->type) }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        {{ $transaction->quantity }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        ${{ number_format($transaction->total_price, 2) }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        {{ $transaction->created_at->format('M d, Y') }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <p class="text-gray-500">No recent transactions.</p>
            @endif
        </div>
    </div>

    @push('scripts')
    <script>
        // Optimized Chart.js implementation to avoid requestAnimationFrame violations
        document.addEventListener('DOMContentLoaded', function() {
            // Delay chart initialization slightly to avoid blocking the main thread
            setTimeout(function() {
                initializeChart();
            }, 100);
        });

        function initializeChart() {
            const chartElement = document.getElementById('salesChart');
            if (!chartElement) return;
            
            try {
                // Check if salesData is defined
                const salesData = @json($salesData ?? []);
                
                // If no data, show a message instead of an empty chart
                if (!salesData || salesData.length === 0) {
                    document.getElementById('chart-container').innerHTML = 
                        '<div class="flex items-center justify-center h-full bg-gray-50 rounded-lg">' +
                        '<p class="text-gray-500">No sales data available</p>' +
                        '</div>';
                    return;
                }
                
                const labels = salesData.map(item => item.date);
                const data = salesData.map(item => item.total);
                
                // Use a simpler configuration to improve performance
                const ctx = chartElement.getContext('2d');
                new Chart(ctx, {
                    type: 'line',
                    data: {
                        labels: labels,
                        datasets: [{
                            label: 'Sales',
                            data: data,
                            backgroundColor: 'rgba(79, 70, 229, 0.2)',
                            borderColor: 'rgba(79, 70, 229, 1)',
                            borderWidth: 2,
                            tension: 0.3,
                            pointBackgroundColor: 'rgba(79, 70, 229, 1)',
                            // Reduce the number of points displayed
                            pointRadius: 3,
                            pointHoverRadius: 4
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        animation: {
                            duration: 0 // Disable animations for better performance
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                ticks: {
                                    callback: function(value) {
                                        return '$' + value;
                                    },
                                    maxTicksLimit: 5 // Limit the number of ticks
                                }
                            },
                            x: {
                                ticks: {
                                    maxTicksLimit: 7 // Limit the number of ticks
                                }
                            }
                        },
                        plugins: {
                            tooltip: {
                                callbacks: {
                                    label: function(context) {
                                        return '$' + context.parsed.y;
                                    }
                                }
                            },
                            legend: {
                                display: false // Hide legend for simplicity
                            }
                        },
                        elements: {
                            line: {
                                tension: 0.3 // Smoother lines
                            }
                        }
                    }
                });
            } catch (error) {
                console.error('Error initializing chart:', error);
                // Replace the chart canvas with an error message
                document.getElementById('chart-container').innerHTML = 
                    '<div class="p-4 bg-red-50 text-red-500 rounded">' +
                    'Unable to load chart data: ' + error.message +
                    '</div>';
            }
        }
    </script>
    @endpush
</x-app-layout>
<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\Product;
use App\Models\Transaction;
use App\Models\Category;

class OdooService
{
    protected $url;
    protected $db;
    protected $username;
    protected $password;
    protected $uid;

    public function __construct()
    {
        $this->url = env('ODOO_URL');
        $this->db = env('ODOO_DB');
        $this->username = env('ODOO_USERNAME');
        $this->password = env('ODOO_PASSWORD');
        $this->authenticate();
    }

    private function authenticate()
    {
        try {
            $response = Http::post($this->url . '/jsonrpc', [
                'jsonrpc' => '2.0',
                'method' => 'call',
                'params' => [
                    'service' => 'common',
                    'method' => 'login',
                    'args' => [$this->db, $this->username, $this->password]
                ],
                'id' => rand(0, 1000000)
            ]);

            $result = $response->json();
            
            if (isset($result['result'])) {
                $this->uid = $result['result'];
                return true;
            } else {
                Log::error('Odoo authentication failed: ' . json_encode($result));
                return false;
            }
        } catch (\Exception $e) {
            Log::error('Odoo authentication exception: ' . $e->getMessage());
            return false;
        }
    }

    public function executeKw($model, $method, $args = [], $kwargs = [])
    {
        try {
            $response = Http::post($this->url . '/jsonrpc', [
                'jsonrpc' => '2.0',
                'method' => 'call',
                'params' => [
                    'service' => 'object',
                    'method' => 'execute_kw',
                    'args' => [
                        $this->db,
                        $this->uid,
                        $this->password,
                        $model,
                        $method,
                        $args,
                        $kwargs
                    ]
                ],
                'id' => rand(0, 1000000)
            ]);

            $result = $response->json();
            
            if (isset($result['result'])) {
                return $result['result'];
            } else {
                Log::error('Odoo API call failed: ' . json_encode($result));
                return null;
            }
        } catch (\Exception $e) {
            Log::error('Odoo API exception: ' . $e->getMessage());
            return null;
        }
    }

    public function syncProducts()
    {
        $products = $this->executeKw('product.product', 'search_read', [
            [['active', '=', true]],
            ['id', 'name', 'default_code', 'list_price', 'standard_price', 'qty_available', 'categ_id', 'description']
        ]);

        if (!$products) {
            return false;
        }

        foreach ($products as $odooProduct) {
            $product = Product::updateOrCreate(
                ['odoo_product_id' => $odooProduct['id']],
                [
                    'name' => $odooProduct['name'],
                    'sku' => $odooProduct['default_code'] ?? '',
                    'description' => $odooProduct['description'] ?? '',
                    'price' => $odooProduct['list_price'],
                    'cost' => $odooProduct['standard_price'],
                    'quantity' => $odooProduct['qty_available'],
                    'category_id' => $this->syncCategory($odooProduct['categ_id'][0], $odooProduct['categ_id'][1]),
                    'status' => 'active'
                ]
            );
        }

        return true;
    }

    private function syncCategory($odoo_category_id, $category_name)
    {
        $category = Category::firstOrCreate(
            ['odoo_category_id' => $odoo_category_id],
            [
                'name' => $category_name,
                'description' => 'Imported from Odoo'
            ]
        );

        return $category->id;
    }

    public function syncTransactions($days = 30)
    {
        $date = date('Y-m-d', strtotime('-' . $days . ' days'));
        
        // Get stock moves from Odoo
        $stockMoves = $this->executeKw('stock.move', 'search_read', [
            [['date', '>=', $date]],
            ['id', 'product_id', 'product_qty', 'price_unit', 'state', 'reference', 'date', 'origin']
        ]);

        if (!$stockMoves) {
            return false;
        }

        foreach ($stockMoves as $move) {
            if ($move['state'] != 'done') {
                continue;
            }

            $product = Product::where('odoo_product_id', $move['product_id'][0])->first();
            
            if (!$product) {
                continue;
            }

            // Determine transaction type based on reference or other fields
            $type = 'adjustment';
            if (strpos(strtolower($move['reference']), 'purchase') !== false) {
                $type = 'purchase';
            } elseif (strpos(strtolower($move['reference']), 'sale') !== false) {
                $type = 'sale';
            }

            Transaction::updateOrCreate(
                ['odoo_transaction_id' => $move['id']],
                [
                    'product_id' => $product->id,
                    'type' => $type,
                    'quantity' => $move['product_qty'],
                    'unit_price' => $move['price_unit'],
                    'total_price' => $move['price_unit'] * $move['product_qty'],
                    'reference' => $move['reference'],
                    'notes' => $move['origin'] ?? ''
                ]
            );
        }

        return true;
    }

    public function createProduct($productData)
    {
        $odooProductData = [
            'name' => $productData['name'],
            'default_code' => $productData['sku'],
            'description' => $productData['description'],
            'list_price' => $productData['price'],
            'standard_price' => $productData['cost'],
            'type' => 'product',
            'categ_id' => $productData['odoo_category_id']
        ];

        $productId = $this->executeKw('product.product', 'create', [$odooProductData]);
        
        return $productId;
    }

    public function updateProduct($odooProductId, $productData)
    {
        $odooProductData = [
            'name' => $productData['name'],
            'default_code' => $productData['sku'],
            'description' => $productData['description'],
            'list_price' => $productData['price'],
            'standard_price' => $productData['cost']
        ];

        $result = $this->executeKw('product.product', 'write', [
            [intval($odooProductId)],
            $odooProductData
        ]);
        
        return $result;
    }

    public function updateStock($odooProductId, $quantity, $reason = '')
    {
        // This is a simplified version. In a real application, you would use
        // Odoo's inventory adjustment functionality
        $inventoryData = [
            'product_id' => intval($odooProductId),
            'product_qty' => $quantity,
            'location_id' => 2, // Assumes stock location ID 2, adjust as needed
            'name' => $reason
        ];

        $result = $this->executeKw('stock.inventory', 'create', [$inventoryData]);
        
        if ($result) {
            $this->executeKw('stock.inventory', 'action_validate', [[$result]]);
            return true;
        }
        
        return false;
    }
}